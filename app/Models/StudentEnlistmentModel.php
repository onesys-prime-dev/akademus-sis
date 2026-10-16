<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The enlistment cart's header / state machine:
 * draft -> submitted -> chair_recommended -> dean_noted -> enrolled
 * (or conversion_failed, the rare all-items-failed edge of the final step).
 * A 'return' from the chairperson, dean, or registrar sends it back to
 * draft — the spec's "goes back to DRAFT so the student can fix their
 * schedule" — recording who/why in the returned_* columns without wiping
 * enlistment_items (the student edits the existing cart, not a fresh one).
 *
 * Mirrors GradeChangeRequestModel's shape (decide()-style transitions,
 * program-scoped queue helpers) but the final "REGISTRAR_APPROVED /
 * ENROLLED" step (the spec itself writes it as one step) is synchronous:
 * approving immediately converts every cart item into a real enrollment
 * via EnrollmentModel::enrollOne(), so there is no separate lingering
 * registrar_approved status to track.
 */
class StudentEnlistmentModel extends Model
{
    protected $table      = 'student_enlistments';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'student_id', 'academic_year', 'status', 'submitted_at',
        'chair_recommended_by', 'chair_recommended_at', 'chair_notes',
        'dean_noted_by', 'dean_noted_at', 'dean_notes',
        'registrar_approved_by', 'registrar_approved_at', 'registrar_notes',
        'returned_by', 'returned_by_role', 'returned_at', 'return_notes',
    ];

    protected $useTimestamps = true;

    private const OPEN_STATUSES = ['draft', 'submitted', 'chair_recommended', 'dean_noted'];

    /**
     * One in-flight cart per (student, year) — same invariant shape as
     * GraduationClearanceModel::initiate()'s one-open-case guard. Returns
     * the existing open cart if present, otherwise creates a fresh draft.
     */
    public function openOrCreate(int $studentId, string $academicYear): array
    {
        $existing = $this->where('student_id', $studentId)
            ->where('academic_year', $academicYear)
            ->whereIn('status', self::OPEN_STATUSES)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $this->insert([
            'student_id'    => $studentId,
            'academic_year' => $academicYear,
            'status'        => 'draft',
        ]);

        return $this->find($this->getInsertID());
    }

    /**
     * Subject codes currently in the student's open cart for one academic
     * year — read-only counterpart to openOrCreate() (no insert-if-missing
     * side effect), used by EnrollmentModel::unmetCorequisites() to treat
     * "already added together in this cart" as satisfying a corequisite.
     *
     * @return string[]
     */
    public function cartSubjectCodes(int $studentId, string $academicYear): array
    {
        $enlistment = $this->where('student_id', $studentId)
            ->where('academic_year', $academicYear)
            ->whereIn('status', self::OPEN_STATUSES)
            ->first();

        if ($enlistment === null) {
            return [];
        }

        return array_column($this->itemsFor((int) $enlistment['id']), 'subject_code');
    }

    /**
     * Adds one (section, subject) to the cart. Re-validates eligibility via
     * EnrollmentModel::checkEligibility() (the same rules enrollOne() will
     * re-check for real at conversion time) and enforces the unit cap
     * (setting('Academic.maxStudentUnits')) against the cart's current sum.
     *
     * @return array{ok: bool, error?: string}
     */
    public function addItem(int $enlistmentId, int $studentUserId, int $sectionId, string $subjectCode): array
    {
        $enlistment = $this->find($enlistmentId);

        if ($enlistment === null || $enlistment['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'This cart can no longer be edited.'];
        }

        $eligibility = (new EnrollmentModel())->checkEligibility($studentUserId, $sectionId, $subjectCode);

        if (! $eligibility['ok']) {
            return ['ok' => false, 'error' => $eligibility['error']];
        }

        $units = (float) ($eligibility['units'] ?? 0);
        $cap   = (int) (setting('Academic.maxStudentUnits') ?? 24);
        $sum   = (float) ($this->db->table('enlistment_items')->selectSum('units')->where('enlistment_id', $enlistmentId)->get()->getRowArray()['units'] ?? 0);

        if ($sum + $units > $cap) {
            return ['ok' => false, 'error' => "Adding {$subjectCode} ({$units} units) would exceed the {$cap}-unit cap for this term."];
        }

        try {
            $this->db->table('enlistment_items')->insert([
                'enlistment_id' => $enlistmentId,
                'section_id'    => $sectionId,
                'subject_code'  => $subjectCode,
                'units'         => $units,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => "{$subjectCode} is already in your cart."];
        }

        return ['ok' => true];
    }

    public function removeItem(int $enlistmentId, int $itemId): array
    {
        $enlistment = $this->find($enlistmentId);

        if ($enlistment === null || $enlistment['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'This cart can no longer be edited.'];
        }

        $this->db->table('enlistment_items')->where('id', $itemId)->where('enlistment_id', $enlistmentId)->delete();

        return ['ok' => true];
    }

    public function itemsFor(int $enlistmentId): array
    {
        return $this->db->table('enlistment_items i')
            ->select('i.id, i.section_id, i.subject_code, i.units, s.academic_year, s.program, s.year_level, s.section')
            ->join('sections s', 's.section_id = i.section_id')
            ->where('i.enlistment_id', $enlistmentId)
            ->orderBy('i.subject_code')
            ->get()
            ->getResultArray();
    }

    /**
     * draft -> submitted. Refused when the cart is empty or the enlistment
     * window is closed. Notifies the owning chair(s) the same way
     * GradeChangeRequestModel::notifyOwningChairs() does, resolved from the
     * STUDENT's own program (not each item's section — a cart can span
     * several programs via shared subjects, but it is reviewed by the
     * student's own department).
     */
    public function submit(int $enlistmentId, int $studentId): array
    {
        $enlistment = $this->find($enlistmentId);

        if ($enlistment === null || (int) $enlistment['student_id'] !== $studentId || $enlistment['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'This cart can no longer be submitted.'];
        }

        if (! (new AcademicYearModel())->isEnlistmentOpen()) {
            return ['ok' => false, 'error' => 'Online enlistment is closed for the current term.'];
        }

        $cartItems = $this->itemsFor($enlistmentId);

        if ($cartItems === []) {
            return ['ok' => false, 'error' => 'Add at least one subject to your cart before submitting.'];
        }

        $student = (new StudentModel())->find($studentId);

        if ($student !== null) {
            $enrollments = new EnrollmentModel();
            $overrides   = new EnlistmentOverrideModel();

            foreach ($cartItems as $item) {
                $missing = $enrollments->unmetCorequisites((int) $student['user_id'], $item['subject_code'], $enlistment['academic_year']);

                if ($missing === [] || $overrides->hasOverride($studentId, $item['subject_code'], $enlistment['academic_year'])) {
                    continue;
                }

                return ['ok' => false, 'error' => "{$item['subject_code']} must be taken together with " . implode(', ', $missing) . '.'];
            }
        }

        $this->update($enlistmentId, [
            'status'          => 'submitted',
            'submitted_at'    => date('Y-m-d H:i:s'),
            'returned_by'     => null,
            'returned_by_role' => null,
            'returned_at'     => null,
            'return_notes'    => null,
        ]);

        (new AuditLogModel())->record(
            'enlistment.submitted',
            'student_enlistment',
            $enlistmentId,
            ['status' => 'draft'],
            ['status' => 'submitted'],
            null,
            $studentId,
            'student'
        );

        $this->notifyOwningChairs((int) $enlistment['student_id'], 'enlistment.submitted', 'A student has submitted their enlistment cart for your review.');

        return ['ok' => true];
    }

    public function chairRecommend(int $id, int $chairId, string $decision, ?string $notes = null): array
    {
        return $this->decide($id, 'submitted', $decision, [
            'chair_recommended_by' => $chairId,
            'chair_recommended_at' => date('Y-m-d H:i:s'),
            'chair_notes'          => $notes,
        ], 'chair_recommended', 'enlistment.chair_recommended', $chairId, 'chairperson', $notes);
    }

    public function deanNote(int $id, int $deanId, string $decision, ?string $notes = null): array
    {
        return $this->decide($id, 'chair_recommended', $decision, [
            'dean_noted_by' => $deanId,
            'dean_noted_at' => date('Y-m-d H:i:s'),
            'dean_notes'    => $notes,
        ], 'dean_noted', 'enlistment.dean_noted', $deanId, 'dean', $notes);
    }

    /**
     * Lets a student nudge whichever office currently owns the next review
     * step for their own cart, reusing the exact same notify targets this
     * model already resolves for its own automatic transition notifications
     * (see decide(), below) rather than duplicating that resolution logic.
     *
     * @return array{ok: bool, error?: string}
     */
    public function sendReminder(int $id, int $studentId): array
    {
        $enlistment = $this->find($id);

        if ($enlistment === null || (int) $enlistment['student_id'] !== $studentId) {
            return ['ok' => false, 'error' => 'Cart not found.'];
        }

        $status = $enlistment['status'];

        if ($status === 'submitted') {
            $this->notifyOwningChairs($studentId, 'enlistment.reminder', 'Reminder: a student enlistment cart is still awaiting your recommendation.');
        } elseif ($status === 'chair_recommended') {
            $this->notifyDeanOfStudent($studentId, 'enlistment.reminder', 'Reminder: a student enlistment cart is still awaiting your note.');
        } elseif ($status === 'dean_noted') {
            $this->notifyGroup('registrar', 'enlistment.reminder', 'Reminder: a student enlistment cart is still awaiting registrar approval.');
        } else {
            return ['ok' => false, 'error' => 'Nothing is currently awaiting review.'];
        }

        return ['ok' => true];
    }

    /**
     * dean_noted -> enrolled (or conversion_failed). The ONLY transition
     * that actually mutates enrollments — it calls EnrollmentModel::
     * enrollOne() once per cart item, with windowOverride: true (the
     * registrar's sign-off is the authoritative act, not bound by whether
     * the student-facing window has since closed). Each item is
     * re-validated fresh by enrollOne() itself — a section can fill up or a
     * prerequisite grade can change during the days the cart spent in
     * review, so failures here are real and are reported back, not
     * silently dropped. recomputeTuition() runs for free inside enrollOne()
     * for every item that converts — the spec's "Finance: triggers tuition
     * assessment" with no new Finance code.
     *
     * @return array{ok: bool, error?: string, enrolled?: list<string>, failed?: list<array{subject_code: string, error: string}>}
     */
    public function registrarApprove(int $id, int $registrarId, ?string $notes = null): array
    {
        $enlistment = $this->find($id);

        if ($enlistment === null || $enlistment['status'] !== 'dean_noted') {
            return ['ok' => false, 'error' => 'This cart is not awaiting final approval.'];
        }

        $student = (new StudentModel())->find((int) $enlistment['student_id']);

        if ($student === null) {
            return ['ok' => false, 'error' => 'The underlying student record no longer exists.'];
        }

        $this->update($id, [
            'registrar_approved_by' => $registrarId,
            'registrar_approved_at' => date('Y-m-d H:i:s'),
            'registrar_notes'       => $notes,
        ]);

        $items          = $this->itemsFor($id);
        $enrollmentModel = new EnrollmentModel();
        $enrolled       = [];
        $failed         = [];

        foreach ($items as $item) {
            $result = $enrollmentModel->enrollOne(
                (int) $student['user_id'],
                (int) $item['section_id'],
                $item['subject_code'],
                'enlistment',
                null,
                true
            );

            if ($result['ok']) {
                $enrolled[] = $item['subject_code'];
            } else {
                $failed[] = ['subject_code' => $item['subject_code'], 'error' => $result['error']];
            }
        }

        $finalStatus = $enrolled !== [] ? 'enrolled' : 'conversion_failed';
        $this->update($id, ['status' => $finalStatus]);

        (new AuditLogModel())->record(
            'enlistment.' . $finalStatus,
            'student_enlistment',
            $id,
            ['status' => 'dean_noted'],
            ['status' => $finalStatus, 'enrolled' => $enrolled, 'failed' => $failed],
            $notes,
            $registrarId,
            'registrar'
        );

        helper('url');
        $message = $enrolled !== []
            ? 'Your enlistment was approved. Enrolled: ' . implode(', ', $enrolled) . ($failed !== [] ? '. Could not enroll: ' . implode(', ', array_column($failed, 'subject_code')) : '') . '.'
            : 'Your enlistment could not be converted to enrollments: ' . implode('; ', array_column($failed, 'error'));

        (new NotificationModel())->notify((int) $student['user_id'], 'enlistment.' . $finalStatus, $message, site_url('student/enlistment'));

        return [
            'ok'            => true,
            'enrolled'      => $enrolled,
            'failed'        => $failed,
            'student_id'    => (int) $enlistment['student_id'],
            'academic_year' => $enlistment['academic_year'],
        ];
    }

    /**
     * Any non-terminal stage ('submitted', 'chair_recommended',
     * 'dean_noted') -> draft, by whichever office currently holds it. Used
     * by chairRecommend()/deanNote() for their 'return' decision AND
     * directly by the registrar's own return action.
     */
    public function returnToStudent(int $id, string $fromStatus, int $actorId, string $actorRole, string $notes): array
    {
        return $this->decide($id, $fromStatus, 'return', [], 'draft', 'enlistment', $actorId, $actorRole, $notes);
    }

    /**
     * Shared chair/dean/registrar decision shape, mirroring
     * GradeChangeRequestModel::decide(): either moves the cart to
     * $toStatus ('recommend'/'note'/'approve') or bounces it back to
     * 'draft' ('return'). Items are never touched by a return — only
     * submit() clears returned_* and the student edits the existing cart.
     */
    private function decide(int $id, string $fromStatus, string $decision, array $setOnAdvance, string $toStatus, string $auditAction, int $actorId, string $actorRole, ?string $notes): array
    {
        $enlistment = $this->find($id);

        if ($enlistment === null || $enlistment['status'] !== $fromStatus) {
            return ['ok' => false, 'error' => 'This cart is not awaiting your decision.'];
        }

        if (! in_array($decision, ['recommend', 'note', 'return'], true)) {
            return ['ok' => false, 'error' => 'Invalid decision.'];
        }

        if ($decision === 'return') {
            if (trim((string) $notes) === '') {
                return ['ok' => false, 'error' => 'A reason is required to return a cart to the student.'];
            }

            $this->update($id, [
                'status'           => 'draft',
                'returned_by'      => $actorId,
                'returned_by_role' => $actorRole,
                'returned_at'      => date('Y-m-d H:i:s'),
                'return_notes'     => $notes,
            ]);

            (new AuditLogModel())->record(
                $auditAction . '.returned',
                'student_enlistment',
                $id,
                ['status' => $fromStatus],
                ['status' => 'draft'],
                $notes,
                $actorId,
                $actorRole
            );

            $student = (new StudentModel())->find((int) $enlistment['student_id']);

            if ($student !== null) {
                helper('url');
                (new NotificationModel())->notify(
                    (int) $student['user_id'],
                    'enlistment.returned',
                    "Your enlistment cart was returned by {$actorRole} for revision: {$notes}",
                    site_url('student/enlistment')
                );
            }

            return ['ok' => true];
        }

        $this->update($id, $setOnAdvance + ['status' => $toStatus]);

        (new AuditLogModel())->record(
            $auditAction,
            'student_enlistment',
            $id,
            ['status' => $fromStatus],
            ['status' => $toStatus],
            $notes,
            $actorId,
            $actorRole
        );

        if ($toStatus === 'chair_recommended') {
            $this->notifyDeanOfStudent((int) $enlistment['student_id'], 'enlistment.chair_recommended', 'A student enlistment cart has been recommended by the chairperson and is awaiting your note.');
        } elseif ($toStatus === 'dean_noted') {
            $this->notifyGroup('registrar', 'enlistment.dean_noted', 'A student enlistment cart has been noted by the dean and is awaiting registrar approval.');
        }

        return ['ok' => true];
    }

    /**
     * Notifies every user in the given Shield group — the "notify the whole
     * office" idiom used wherever there's no single named actor (mirrors
     * GradeModel::notifyGroup()).
     */
    private function notifyGroup(string $group, string $type, string $message): void
    {
        helper('url');
        $userIds = array_unique(array_column(
            $this->db->table('auth_groups_users')->select('user_id')->where('group', $group)->get()->getResultArray(),
            'user_id'
        ));

        $notifications = new NotificationModel();

        foreach ($userIds as $userId) {
            $notifications->notify((int) $userId, $type, $message, site_url('registrar/enlistments'));
        }
    }

    /**
     * Resolves and notifies the dean(s) (primary + delegated) of
     * $studentId's program's college — the dean counterpart to
     * notifyOwningChairs(), keyed off the student's program.
     */
    private function notifyDeanOfStudent(int $studentId, string $type, string $message): void
    {
        $student = (new StudentModel())->find($studentId);

        if ($student === null || $student['program_id'] === null) {
            return;
        }

        $subDeptId = $this->db->table('programs')->select('sub_department_id')->where('program_id', $student['program_id'])->get()->getRowArray()['sub_department_id'] ?? null;

        if ($subDeptId === null) {
            return;
        }

        $collegeId = $this->db->table('sub_departments')->select('college_id')->where('sub_dept_id', $subDeptId)->get()->getRowArray()['college_id'] ?? null;

        if ($collegeId === null) {
            return;
        }

        $primary = $this->db->table('users u')
            ->select('u.id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'dean')
            ->where('u.department_id', $collegeId)
            ->get()
            ->getResultArray();

        $delegated = $this->db->table('department_deans')
            ->select('user_id')
            ->where('dept_id', $collegeId)
            ->get()
            ->getResultArray();

        $deanIds = array_unique(array_merge(array_column($primary, 'id'), array_column($delegated, 'user_id')));

        if ($deanIds === []) {
            return;
        }

        helper('url');
        $notifications = new NotificationModel();

        foreach ($deanIds as $userId) {
            $notifications->notify((int) $userId, $type, $message, site_url('dean/enlistment-approvals'));
        }
    }

    public function forStudent(int $studentId): array
    {
        return $this->where('student_id', $studentId)->orderBy('created_at', 'DESC')->findAll();
    }

    public function pendingChairRecommendation(array $programs): array
    {
        return $this->statusForPrograms($programs, 'submitted');
    }

    public function pendingDeanNote(array $programs): array
    {
        return $this->statusForPrograms($programs, 'chair_recommended');
    }

    public function pendingRegistrarApproval(): array
    {
        return $this->statusForPrograms(null, 'dean_noted');
    }

    /**
     * $programs holds the LEGACY_PROGRAM_NAMES-mapped short display names
     * (DepartmentModel::programsOfSubDept()/programNameById()'s format,
     * matching sections.program) — NOT programs.program_name, which is the
     * long institutional name and does not match. Filtering is done in PHP
     * after resolving each row's name via programNameById(), the one
     * authoritative mapping, rather than joining on the raw column and
     * silently matching nothing.
     */
    private function statusForPrograms(?array $programs, string $status): array
    {
        if ($programs !== null && $programs === []) {
            return [];
        }

        $rows = $this->db->table('student_enlistments se')
            ->select('se.id, se.academic_year, se.status, se.submitted_at, se.chair_recommended_at, se.dean_noted_at,
                      st.student_id, st.student_number, st.program_id, u.username')
            ->join('students st', 'st.student_id = se.student_id')
            ->join('users u', 'u.id = st.user_id')
            ->where('se.status', $status)
            ->orderBy('se.submitted_at', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_filter(array_map(static function (array $row) use ($programs): ?array {
            $row['program_name'] = $row['program_id'] !== null ? DepartmentModel::programNameById((int) $row['program_id']) : null;

            if ($programs !== null && ! in_array($row['program_name'], $programs, true)) {
                return null;
            }

            return $row;
        }, $rows)));
    }

    /**
     * Resolves and notifies the chair(s) owning $studentId's program's
     * sub-department — same primary + delegated resolution as
     * GradeChangeRequestModel::notifyOwningChairs(), but keyed off the
     * STUDENT's program rather than a single section.
     */
    private function notifyOwningChairs(int $studentId, string $type, string $message): void
    {
        $student = (new StudentModel())->find($studentId);

        if ($student === null || $student['program_id'] === null) {
            return;
        }

        $subDeptId = $this->db->table('programs')->select('sub_department_id')->where('program_id', $student['program_id'])->get()->getRowArray()['sub_department_id'] ?? null;

        if ($subDeptId === null) {
            return;
        }

        $primary = $this->db->table('users u')
            ->select('u.id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'chairperson')
            ->where('u.sub_department_id', $subDeptId)
            ->get()
            ->getResultArray();

        $delegated = $this->db->table('sub_department_schedulers')
            ->select('user_id')
            ->where('sub_dept_id', $subDeptId)
            ->get()
            ->getResultArray();

        $chairIds = array_unique(array_merge(array_column($primary, 'id'), array_column($delegated, 'user_id')));

        if ($chairIds === []) {
            return;
        }

        helper('url');
        $notifications = new NotificationModel();

        foreach ($chairIds as $userId) {
            $notifications->notify((int) $userId, $type, $message, site_url('chairperson/enlistment-approvals'));
        }
    }
}

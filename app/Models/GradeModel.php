<?php

namespace App\Models;

use App\Libraries\GradeScaleEvaluator;
use CodeIgniter\Model;

/**
 * One row per enrollment. Lifecycle: draft (faculty editing) -> submitted
 * (faculty done, awaiting chairperson) -> locked (official). A locked grade
 * is never directly editable again — GradeChangeRequestModel is the only
 * path to change one, mirroring how faculty_change_requests is the only
 * path to change an official teaching assignment.
 */
class GradeModel extends Model
{
    protected $table      = 'grades';
    protected $primaryKey = 'grade_id';

    protected $allowedFields = [
        'enrollment_id',
        'user_id',
        'subject_code',
        'section_id',
        'offering_id',
        'academic_year',
        'faculty_id',
        'grading_scale_id',
        'grade_value',
        'is_passing',
        'remarks',
        'status',
        'submitted_at',
        'submitted_by',
        'chair_recommended_at',
        'chair_recommended_by',
        'dean_noted_at',
        'dean_noted_by',
        'registrar_approved_at',
        'registrar_approved_by',
        'locked_at',
        'locked_by',
    ];

    protected $useTimestamps = true;

    /**
     * Resolves a class context down to its roster, each roster row carrying
     * its existing grade (if any). Handles merged offerings transparently:
     * if this (section, subject) pair is merged, the roster is the merged
     * pool (CourseOfferingModel::listRoster), otherwise it's just this
     * section's own enrolled students for this subject.
     *
     * @return array{section: ?array, offering: ?array, roster: list<array>}
     */
    public function rosterWithGrades(string $academicYear, string $program, string $yearLevel, string $section, string $subjectCode): array
    {
        $sectionRow = (new SectionModel())->forContext($academicYear, $program, $yearLevel, $section);

        if ($sectionRow === null) {
            return ['section' => null, 'offering' => null, 'roster' => []];
        }

        $sectionId = (int) $sectionRow['section_id'];
        $offering  = (new CourseOfferingModel())->offeringForSection($sectionId, $subjectCode);

        $roster = $offering !== null
            ? (new CourseOfferingModel())->listRoster((int) $offering['offering_id'])
            : (new EnrollmentModel())->listRosterForSectionSubject($sectionId, $subjectCode);

        if ($roster === []) {
            return ['section' => $sectionRow, 'offering' => $offering, 'roster' => []];
        }

        $enrollmentIds     = array_column($roster, 'enrollment_id');
        $gradeByEnrollment = [];

        foreach ($this->whereIn('enrollment_id', $enrollmentIds)->findAll() as $grade) {
            $gradeByEnrollment[(int) $grade['enrollment_id']] = $grade;
        }

        $gradeIds = array_map(static fn (array $g) => (int) $g['grade_id'], $gradeByEnrollment);
        $history  = (new GradeChangeRequestModel())->forGrades($gradeIds);

        foreach ($roster as &$row) {
            $grade        = $gradeByEnrollment[(int) $row['enrollment_id']] ?? null;
            $row['grade'] = $grade;
            $row['change_requests'] = $grade !== null ? ($history[(int) $grade['grade_id']] ?? []) : [];
        }
        unset($row);

        return ['section' => $sectionRow, 'offering' => $offering, 'roster' => $roster];
    }

    /**
     * Create-or-update the grade for one enrollment. Only legal while the
     * grade is still draft/submitted (or doesn't exist yet) — a locked grade
     * must go through requestChange() instead. Re-validated every save
     * against the scale the row was originally created under (or the
     * currently active scale, for a brand-new row), so a mid-class admin
     * scale change can never leave two students on different scales.
     *
     * @return array{ok: bool, error?: string, grade_id?: int}
     */
    public function saveOne(array $context, int $enrollmentId, string $value, ?string $remarks, int $facultyId, bool $override = false): array
    {
        $existing = $this->where('enrollment_id', $enrollmentId)->first();

        if ($existing !== null && $existing['status'] === 'locked') {
            return ['ok' => false, 'error' => 'This grade is locked. File a change request instead.'];
        }

        // Anything past draft is already out of the faculty's hands
        // (submitted, chair_recommended, dean_noted, registrar_approved) —
        // editing here would silently race whoever's reviewing it next.
        if ($existing !== null && $existing['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'This grade has already been submitted and is awaiting approval — it can no longer be edited here.'];
        }

        if (! $override && ! (new AcademicYearModel())->isGradingOpen()) {
            return ['ok' => false, 'error' => 'Grading is closed for the current term.'];
        }

        $scale = $existing !== null
            ? (new GradingScaleModel())->find((int) $existing['grading_scale_id'])
            : (new GradingScaleModel())->activeScale();

        if ($scale === null) {
            return ['ok' => false, 'error' => 'No grading scale is configured. Contact an administrator.'];
        }

        $value = trim($value);

        if (! GradeScaleEvaluator::isValidValue($scale, $value)) {
            return ['ok' => false, 'error' => "\"{$value}\" is not a valid value for the {$scale['name']} scale."];
        }

        $data = [
            'grade_value' => $value,
            'remarks'     => $remarks !== null ? trim($remarks) : null,
            'is_passing'  => GradeScaleEvaluator::isPassing($scale, $value),
        ];

        if ($existing !== null) {
            $this->update($existing['grade_id'], $data);

            return ['ok' => true, 'grade_id' => (int) $existing['grade_id']];
        }

        $this->insert(array_merge($data, [
            'enrollment_id'    => $enrollmentId,
            'user_id'          => $context['user_id'],
            'subject_code'     => $context['subject_code'],
            'section_id'       => $context['section_id'],
            'offering_id'      => $context['offering_id'],
            'academic_year'    => $context['academic_year'],
            'faculty_id'       => $facultyId,
            'grading_scale_id' => $scale['scale_id'],
            'status'           => 'draft',
        ]));

        return ['ok' => true, 'grade_id' => $this->getInsertID()];
    }

    /**
     * Bulk draft -> submitted for a whole class. Refuses if any roster
     * member is missing a grade (no partial submits) or already locked.
     *
     * @param int[] $enrollmentIds
     */
    public function submitClass(array $enrollmentIds, int $facultyId, bool $override = false): array
    {
        if ($enrollmentIds === []) {
            return ['ok' => false, 'error' => 'No students to submit.'];
        }

        if (! $override && ! (new AcademicYearModel())->isGradingOpen()) {
            return ['ok' => false, 'error' => 'Grading is closed for the current term.'];
        }

        $rows = $this->whereIn('enrollment_id', $enrollmentIds)->findAll();

        if (count($rows) < count($enrollmentIds)) {
            return ['ok' => false, 'error' => 'Every student must have a grade entered before submitting.'];
        }

        foreach ($rows as $row) {
            if ((int) $row['faculty_id'] !== $facultyId) {
                return ['ok' => false, 'error' => 'You are not the assigned faculty for this class.'];
            }

            if ($row['status'] === 'locked') {
                return ['ok' => false, 'error' => 'This class has already been locked.'];
            }

            // Anything past draft means this class was already submitted —
            // re-submitting would silently reset chair_recommended/
            // dean_noted/registrar_approved progress back to 'submitted'.
            if ($row['status'] !== 'draft') {
                return ['ok' => false, 'error' => 'This class has already been submitted and is awaiting approval.'];
            }
        }

        $this->builder()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->set(['status' => 'submitted', 'submitted_at' => date('Y-m-d H:i:s'), 'submitted_by' => $facultyId])
            ->update();

        $this->notifyOwningChairs((int) $rows[0]['section_id'], 'grade_submitted', 'A class has been submitted by faculty and is awaiting your review.');

        return ['ok' => true];
    }

    /**
     * Notifies the chairperson(s) (primary + delegated) owning the section's
     * sub-department that a class needs their attention — the first-stage
     * counterpart to notifyDeansOfCollegeFor()/notifyGroup() used at every
     * later hand-off in this same chain. Mirrors
     * GradeChangeRequestModel::notifyOwningChairs() (section -> owning
     * sub-department -> its chairs, since there's no single authenticated
     * actor to call authorizedSubDeptIds() against here).
     */
    private function notifyOwningChairs(int $sectionId, string $type, string $message): void
    {
        $section = (new SectionModel())->find($sectionId);

        if ($section === null || $section['program_id'] === null) {
            return;
        }

        $subDeptId = $this->db->table('programs')->select('sub_department_id')->where('program_id', $section['program_id'])->get()->getRowArray()['sub_department_id'] ?? null;

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

        $notifications = new NotificationModel();

        foreach ($chairIds as $userId) {
            $notifications->notify((int) $userId, $type, $message);
        }
    }

    /**
     * Bulk submitted -> chair_recommended, the chairperson's first-stage
     * sign-off. Same all-or-nothing shape as lockClass(): every grade in
     * the batch must already be submitted.
     *
     * @param int[] $enrollmentIds
     */
    public function recommendClass(array $enrollmentIds, int $chairId, ?string $comments = null): array
    {
        $rows = $this->transitionClass($enrollmentIds, 'submitted', 'chair_recommended', 'chair_recommended_at', 'chair_recommended_by', $chairId, 'Every grade must be submitted by faculty before it can be recommended.');

        if (! $rows['ok']) {
            return $rows;
        }

        $auditLog = new AuditLogModel();

        foreach ($rows['rows'] as $row) {
            $auditLog->record(
                'grade.chair_recommended',
                'grade',
                $row['grade_id'],
                ['status' => 'submitted'],
                ['status' => 'chair_recommended'],
                $comments,
                $chairId,
                'chairperson'
            );
        }

        $this->notifyDeansOfCollegeFor($rows['rows'], 'grade_chair_recommended', 'A class has been recommended by the chairperson and is awaiting your note.');

        return ['ok' => true];
    }

    /**
     * Bulk chair_recommended -> dean_noted, the dean's sign-off.
     *
     * @param int[] $enrollmentIds
     */
    public function noteClass(array $enrollmentIds, int $deanId, ?string $comments = null): array
    {
        $rows = $this->transitionClass($enrollmentIds, 'chair_recommended', 'dean_noted', 'dean_noted_at', 'dean_noted_by', $deanId, 'Every grade must be recommended by the chairperson before it can be noted.');

        if (! $rows['ok']) {
            return $rows;
        }

        $auditLog = new AuditLogModel();

        foreach ($rows['rows'] as $row) {
            $auditLog->record(
                'grade.dean_noted',
                'grade',
                $row['grade_id'],
                ['status' => 'chair_recommended'],
                ['status' => 'dean_noted'],
                $comments,
                $deanId,
                'dean'
            );
        }

        $this->notifyGroup('registrar', 'grade_dean_noted', 'A class has been noted by the dean and is awaiting registrar approval.');

        return ['ok' => true];
    }

    /**
     * Bulk dean_noted -> registrar_approved, the registrar's penultimate
     * sign-off (lockClass() is the final step, immediately after).
     *
     * @param int[] $enrollmentIds
     */
    public function approveClassForRegistrar(array $enrollmentIds, int $registrarId, ?string $comments = null): array
    {
        $rows = $this->transitionClass($enrollmentIds, 'dean_noted', 'registrar_approved', 'registrar_approved_at', 'registrar_approved_by', $registrarId, 'Every grade must be noted by the dean before the registrar can approve it.');

        if (! $rows['ok']) {
            return $rows;
        }

        $auditLog = new AuditLogModel();

        foreach ($rows['rows'] as $row) {
            $auditLog->record(
                'grade.registrar_approved',
                'grade',
                $row['grade_id'],
                ['status' => 'dean_noted'],
                ['status' => 'registrar_approved'],
                $comments,
                $registrarId,
                'registrar'
            );
        }

        return ['ok' => true];
    }

    /**
     * Bulk registrar_approved -> locked, the registrar's final, official
     * approval. Lock authority sits with the registrar (not the
     * chairperson) now that the chain has a dean + registrar stage above
     * the chairperson's own recommend step.
     *
     * @param int[] $enrollmentIds
     */
    public function lockClass(array $enrollmentIds, int $registrarId): array
    {
        $rows = $this->transitionClass($enrollmentIds, 'registrar_approved', 'locked', 'locked_at', 'locked_by', $registrarId, 'Every grade must be approved by the registrar before it can be locked.');

        if (! $rows['ok']) {
            return $rows;
        }

        $auditLog = new AuditLogModel();

        foreach ($rows['rows'] as $row) {
            $auditLog->record(
                'grade.locked',
                'grade',
                $row['grade_id'],
                ['status' => $row['status'], 'grade_value' => $row['grade_value']],
                ['status' => 'locked', 'grade_value' => $row['grade_value']],
                null,
                $registrarId,
                'registrar'
            );
        }

        // Locked is the moment a grade becomes visible to the student
        // (forStudent() only returns status = 'locked' rows) — the right
        // point to notify, not on draft/submit. helper('url') is loaded
        // explicitly rather than assumed, since Models (unlike
        // BaseController) don't get it autoloaded.
        helper('url');
        $notifications = new NotificationModel();

        foreach ($rows['rows'] as $row) {
            $notifications->notify(
                (int) $row['user_id'],
                'grade_posted',
                "Your grade for {$row['subject_code']} ({$row['academic_year']}) has been posted.",
                site_url('student/grades')
            );
        }

        return ['ok' => true];
    }

    /**
     * The single place that ever writes grade_value/is_passing on a row
     * that's already locked. Called only from approveChangeRequest() (the
     * legacy 1-step flow) and GradeChangeRequestModel::finalApprove() (the
     * 5-step chain) — no controller, and no other model method, should
     * ever call $this->update($gradeId, ['grade_value' => ...]) directly.
     * Always re-fetches grading_scale_id from the grade row itself (never
     * GradingScaleModel::activeScale()), so a scale change mid-correction
     * never reinterprets an in-flight request against the wrong scale.
     */
    public function applyApprovedChange(int $gradeId, string $newValue): void
    {
        $grade = $this->find($gradeId);

        if ($grade === null) {
            return;
        }

        $scale = (new GradingScaleModel())->find((int) $grade['grading_scale_id']);

        $this->update($gradeId, [
            'grade_value' => $newValue,
            'is_passing'  => $scale !== null ? GradeScaleEvaluator::isPassing($scale, $newValue) : null,
        ]);
    }

    /**
     * Shared bulk-transition shape behind recommendClass()/noteClass()/
     * approveClassForRegistrar()/lockClass(): refuses if any roster member
     * is missing, or isn't currently in $fromStatus (no partial transitions,
     * no skipping a stage).
     *
     * @param int[] $enrollmentIds
     * @return array{ok: bool, error?: string, rows?: list<array>}
     */
    private function transitionClass(array $enrollmentIds, string $fromStatus, string $toStatus, string $atColumn, string $byColumn, int $actorId, string $preconditionError): array
    {
        if ($enrollmentIds === []) {
            return ['ok' => false, 'error' => 'No students selected.'];
        }

        $rows = $this->whereIn('enrollment_id', $enrollmentIds)->findAll();

        if (count($rows) < count($enrollmentIds)) {
            return ['ok' => false, 'error' => 'Not every student has a grade at this stage yet.'];
        }

        foreach ($rows as $row) {
            if ($row['status'] !== $fromStatus) {
                return ['ok' => false, 'error' => $preconditionError];
            }
        }

        $this->builder()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->set([
                'status'   => $toStatus,
                $atColumn  => date('Y-m-d H:i:s'),
                $byColumn  => $actorId,
            ])
            ->update();

        return ['ok' => true, 'rows' => $rows];
    }

    /**
     * Notifies every user in the given Shield group — the "notify the whole
     * office" idiom used wherever there's no single named actor (mirrors
     * StudentModel::notifyAdminsOfNewProgram()'s auth_groups_users scan).
     */
    private function notifyGroup(string $group, string $type, string $message): void
    {
        $userIds = array_unique(array_column(
            $this->db->table('auth_groups_users')->select('user_id')->where('group', $group)->get()->getResultArray(),
            'user_id'
        ));

        $notifications = new NotificationModel();

        foreach ($userIds as $userId) {
            $notifications->notify((int) $userId, $type, $message);
        }
    }

    /**
     * Notifies the dean(s) (primary + delegated, via
     * SchedulingAuthorization::authorizedDepartmentIds() resolved in
     * reverse) of each distinct college represented in $rows. Degrades
     * silently when a college currently has no dean assigned — a missing
     * notify target must never block the chairperson's underlying action.
     *
     * @param list<array> $rows
     */
    private function notifyDeansOfCollegeFor(array $rows, string $type, string $message): void
    {
        $sectionIds = array_unique(array_column($rows, 'section_id'));

        if ($sectionIds === []) {
            return;
        }

        $collegeIds = array_unique(array_column(
            $this->db->table('sections s')
                ->select('p.sub_department_id')
                ->join('programs p', 'p.program_id = s.program_id')
                ->whereIn('s.section_id', $sectionIds)
                ->where('s.program_id IS NOT NULL')
                ->get()
                ->getResultArray(),
            'sub_department_id'
        ));

        if ($collegeIds === []) {
            return;
        }

        $subDeptToCollege = $this->db->table('sub_departments')
            ->select('sub_dept_id, college_id')
            ->whereIn('sub_dept_id', $collegeIds)
            ->get()
            ->getResultArray();

        $deanUserIds = [];

        foreach ($subDeptToCollege as $row) {
            $primary = $this->db->table('users u')
                ->select('u.id')
                ->join('auth_groups_users g', 'g.user_id = u.id')
                ->where('g.group', 'dean')
                ->where('u.department_id', $row['college_id'])
                ->get()
                ->getResultArray();

            $delegated = $this->db->table('department_deans')
                ->select('user_id')
                ->where('dept_id', $row['college_id'])
                ->get()
                ->getResultArray();

            foreach (array_merge(array_column($primary, 'id'), array_column($delegated, 'user_id')) as $userId) {
                $deanUserIds[(int) $userId] = true;
            }
        }

        if ($deanUserIds === []) {
            return;
        }

        $notifications = new NotificationModel();

        foreach (array_keys($deanUserIds) as $userId) {
            $notifications->notify($userId, $type, $message);
        }
    }

    /**
     * File a formal correction request against a locked grade. One open
     * request at a time per grade, same invariant as
     * FacultyChangeRequestModel::hasOpenRequest().
     */
    public function requestChange(int $gradeId, string $newValue, string $reason, int $requestedBy): array
    {
        $grade = $this->find($gradeId);

        if ($grade === null) {
            return ['ok' => false, 'error' => 'Grade not found.'];
        }

        if ($grade['status'] !== 'locked') {
            return ['ok' => false, 'error' => 'Only a locked grade can have a change request filed against it.'];
        }

        if ((int) $grade['faculty_id'] !== $requestedBy) {
            return ['ok' => false, 'error' => 'You are not the faculty of record for this grade.'];
        }

        $requests = new GradeChangeRequestModel();

        if ($requests->hasOpenRequest($gradeId)) {
            return ['ok' => false, 'error' => 'A change request is already pending for this grade.'];
        }

        $scale    = (new GradingScaleModel())->find((int) $grade['grading_scale_id']);
        $newValue = trim($newValue);

        if ($scale === null || ! GradeScaleEvaluator::isValidValue($scale, $newValue)) {
            return ['ok' => false, 'error' => "\"{$newValue}\" is not a valid value for this grade's scale."];
        }

        if ($newValue === $grade['grade_value']) {
            return ['ok' => false, 'error' => 'The requested value is the same as the current one.'];
        }

        $requests->insert([
            'grade_id'        => $gradeId,
            'requested_by'    => $requestedBy,
            'current_value'   => $grade['grade_value'],
            'requested_value' => $newValue,
            'reason'          => trim($reason),
            'status'          => 'pending',
        ]);

        $requestId = $requests->getInsertID();

        (new AuditLogModel())->record(
            'grade_change_request.filed',
            'grade_change_request',
            $requestId,
            null,
            ['grade_id' => $gradeId, 'current_value' => $grade['grade_value'], 'requested_value' => $newValue],
            trim($reason),
            $requestedBy,
            'faculty'
        );

        return ['ok' => true, 'request_id' => $requestId];
    }

    public function approveChangeRequest(int $requestId, int $reviewedBy, ?string $notes = null): array
    {
        $requests = new GradeChangeRequestModel();
        $request  = $requests->find($requestId);

        if ($request === null || $request['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Request not found or already reviewed.'];
        }

        $grade = $this->find((int) $request['grade_id']);

        if ($grade === null) {
            return ['ok' => false, 'error' => 'The underlying grade no longer exists.'];
        }

        $db = $this->db;
        $db->transStart();

        $this->applyApprovedChange((int) $grade['grade_id'], $request['requested_value']);

        $requests->update($requestId, [
            'status'       => 'approved',
            'reviewed_by'  => $reviewedBy,
            'review_notes' => $notes,
            'reviewed_at'  => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if ($db->transStatus() !== false) {
            (new AuditLogModel())->record(
                'grade_change_request.approved',
                'grade',
                $grade['grade_id'],
                ['grade_value' => $grade['grade_value']],
                ['grade_value' => $request['requested_value']],
                $notes,
                $reviewedBy,
                'chairperson'
            );

            helper('url');
            (new NotificationModel())->notify(
                (int) $request['requested_by'],
                'grade_change_approved',
                "Your grade change request for {$grade['subject_code']} was approved.",
                site_url('faculty/dashboard')
            );
        }

        return ['ok' => $db->transStatus() !== false];
    }

    public function rejectChangeRequest(int $requestId, int $reviewedBy, ?string $notes = null): array
    {
        $requests = new GradeChangeRequestModel();
        $request  = $requests->find($requestId);

        if ($request === null || $request['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Request not found or already reviewed.'];
        }

        $requests->update($requestId, [
            'status'       => 'rejected',
            'reviewed_by'  => $reviewedBy,
            'review_notes' => $notes,
            'reviewed_at'  => date('Y-m-d H:i:s'),
        ]);

        $grade = $this->find((int) $request['grade_id']);

        (new AuditLogModel())->record(
            'grade_change_request.rejected',
            'grade_change_request',
            $requestId,
            ['status' => 'pending'],
            ['status' => 'rejected'],
            $notes,
            $reviewedBy,
            'chairperson'
        );

        if ($grade !== null) {
            helper('url');
            (new NotificationModel())->notify(
                (int) $request['requested_by'],
                'grade_change_rejected',
                "Your grade change request for {$grade['subject_code']} was rejected." . ($notes ? " Reason: {$notes}" : ''),
                site_url('faculty/dashboard')
            );
        }

        return ['ok' => true];
    }

    /**
     * Distinct classes with at least one submitted-not-yet-locked grade,
     * scoped to the given programs (a chairperson's own-department
     * programsOfSubDept() list). Merge-aware: rows sharing one offering_id
     * are grouped as a single class even though they came from different
     * home sections.
     */
    public function submittedClasses(array $programs): array
    {
        return $this->classesByStatus($programs, 'submitted');
    }

    /**
     * Classes this chairperson already recommended — the Grade Approvals
     * page's "awaiting review" queue drops a class the moment it's
     * recommended (submittedClasses() only matches status='submitted'), so
     * without this there's no way back to a class once the chair has acted
     * on it. Mirrors submittedClasses() exactly but reports the
     * recommendation, not the submission.
     */
    public function recommendedClasses(array $programs): array
    {
        return $this->classesByStatus($programs, 'chair_recommended', 'chair_recommended_at');
    }

    /**
     * Classes this dean already noted — dean's counterpart to
     * recommendedClasses().
     */
    public function notedClasses(array $programs): array
    {
        return $this->classesByStatus($programs, 'dean_noted', 'dean_noted_at');
    }

    /**
     * Classes awaiting this dean's note — dean's counterpart to
     * submittedClasses().
     */
    public function recommendedForDean(array $programs): array
    {
        return $this->classesByStatus($programs, 'chair_recommended');
    }

    /**
     * Classes awaiting registrar approval (dean already noted) —
     * institution-wide, not program-scoped (registrar has no "own
     * department").
     */
    public function notedForRegistrar(): array
    {
        return $this->classesByStatus(null, 'dean_noted');
    }

    /**
     * Classes the registrar already approved, awaiting lock.
     */
    public function approvedForRegistrar(): array
    {
        return $this->classesByStatus(null, 'registrar_approved');
    }

    /**
     * Classes the registrar already locked — institution-wide.
     */
    public function lockedClasses(?array $programs = null): array
    {
        return $this->classesByStatus($programs, 'locked', 'locked_at');
    }

    /**
     * @param array<int, string>|null $programs Program display names to scope to
     *   (chairperson/dean's own departments), or null for institution-wide
     *   (the registrar has no "own department" to scope by).
     */
    private function classesByStatus(?array $programs, string $status, ?string $withTimestamp = null): array
    {
        if ($programs !== null && $programs === []) {
            return [];
        }

        $select = 'g.section_id, g.offering_id, g.subject_code, g.academic_year, s.program, s.year_level, s.section';

        if ($withTimestamp !== null) {
            $select .= ', g.' . $withTimestamp;
        }

        $query = $this->db->table('grades g')
            ->select($select)
            ->join('sections s', 's.section_id = g.section_id')
            ->where('g.status', $status);

        if ($programs !== null) {
            $query->whereIn('s.program', $programs);
        }

        $rows = $query->get()->getResultArray();

        $classes = [];

        foreach ($rows as $row) {
            $key = ($row['offering_id'] !== null ? 'off:' . $row['offering_id'] : 'sec:' . $row['section_id'])
                . '|' . $row['subject_code'] . '|' . $row['academic_year'];

            if (! isset($classes[$key])) {
                $classes[$key] = [
                    'section_id'    => (int) $row['section_id'],
                    'offering_id'   => $row['offering_id'] !== null ? (int) $row['offering_id'] : null,
                    'subject_code'  => $row['subject_code'],
                    'academic_year' => $row['academic_year'],
                    'program'       => $row['program'],
                    'year_level'    => $row['year_level'],
                    'section'       => $row['section'],
                    'student_count' => 0,
                ];

                if ($withTimestamp !== null) {
                    $classes[$key][$withTimestamp] = $row[$withTimestamp];
                }
            }

            $classes[$key]['student_count']++;
        }

        return array_values($classes);
    }

    /**
     * Locked grades matching a student username or subject code — backs
     * the registrar's "issue a correction form" search. Capped at 20
     * results since this is a type-ahead search, not a report.
     */
    public function searchLockedGrades(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        return $this->db->table('grades g')
            ->select('g.grade_id, g.subject_code, g.academic_year, g.grade_value, s.program, s.year_level, s.section, u.username AS student_username')
            ->join('sections s', 's.section_id = g.section_id')
            ->join('users u', 'u.id = g.user_id')
            ->where('g.status', 'locked')
            ->groupStart()
                ->like('u.username', $query)
                ->orLike('g.subject_code', $query)
            ->groupEnd()
            ->orderBy('g.academic_year', 'DESC')
            ->limit(20)
            ->get()
            ->getResultArray();
    }

    /**
     * Every locked grade for a student, newest term first — the student's
     * own read-only "My Grades" view. Enriched with the subject's title/units
     * and its class context (program/year/section + weekly meetings, the
     * same plotted_schedules rows StudentEnrollment::schedule() reads) so the
     * view can show a schedule/instructor column without a second round
     * trip per row.
     */
    public function forStudent(int $userId): array
    {
        $rows = $this->db->table('grades g')
            ->select('g.*, subj.subject_name, subj.units, s.program, s.year_level, s.section')
            ->join('sections s', 's.section_id = g.section_id')
            ->join('subjects subj', 'subj.subject_code = g.subject_code', 'left')
            ->where('g.user_id', $userId)
            ->where('g.status', 'locked')
            ->orderBy('g.academic_year', 'DESC')
            ->orderBy('subj.subject_name', 'ASC')
            ->get()
            ->getResultArray();

        $schedules = new PlottedScheduleModel();

        foreach ($rows as &$row) {
            $meetings = $schedules
                ->select('day, start_time, end_time, time_label, room, prof')
                ->where('academic_year', $row['academic_year'])
                ->where('program', $row['program'])
                ->where('year_level', $row['year_level'])
                ->where('section', $row['section'])
                ->where('subject_code', $row['subject_code'])
                ->orderBy('day')
                ->findAll();

            $row['meetings']   = $meetings;
            $row['instructor'] = $meetings[0]['prof'] ?? null;
            $row['room']       = $meetings[0]['room'] ?? null;
        }
        unset($row);

        return $rows;
    }
}

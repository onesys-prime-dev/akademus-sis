<?php

namespace App\Models;

use App\Libraries\GradeScaleEvaluator;
use CodeIgniter\Model;

/**
 * Mirrors FacultyChangeRequestModel (faculty_change_requests) exactly: a
 * one-directional request, reviewed once, fully audited. Here the "official
 * assignment" being corrected is a locked grade rather than a teaching load.
 */
class GradeChangeRequestModel extends Model
{
    protected $table      = 'grade_change_requests';
    protected $primaryKey = 'request_id';

    protected $allowedFields = [
        'grade_id',
        'requested_by',
        'current_value',
        'requested_value',
        'reason',
        'status',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
        'issued_by',
        'issued_at',
        'chair_recommended_by',
        'chair_recommended_at',
        'chair_notes',
        'dean_recommended_by',
        'dean_recommended_at',
        'dean_notes',
        'final_approved_by',
        'final_approved_at',
    ];

    protected $useTimestamps = true;

    /**
     * Every "open" state across both the legacy 1-step flow (pending) and
     * the 5-step chain (every state short of a terminal one) counts as
     * open — hasOpenRequest() is the invariant that keeps exactly one
     * in-flight correction per grade at a time, regardless of which flow
     * is in use.
     */
    private const OPEN_STATUSES = [
        'pending',
        'registrar_form_issued',
        'faculty_submitted',
        'chair_recommended',
        'dean_recommended',
    ];

    public function hasOpenRequest(int $gradeId): bool
    {
        return $this->where('grade_id', $gradeId)->whereIn('status', self::OPEN_STATUSES)->countAllResults() > 0;
    }

    /**
     * Registrar opens a correction window against an already-locked grade
     * — step 1 of the 5-step chain (REGISTRAR_FORM_ISSUED). Faculty
     * responds next via submitFacultyResponse().
     */
    public function issueForm(int $gradeId, int $registrarId): array
    {
        $grade = (new GradeModel())->find($gradeId);

        if ($grade === null) {
            return ['ok' => false, 'error' => 'Grade not found.'];
        }

        if ($grade['status'] !== 'locked') {
            return ['ok' => false, 'error' => 'Only a locked grade can have a correction window opened against it.'];
        }

        if ($this->hasOpenRequest($gradeId)) {
            return ['ok' => false, 'error' => 'A change request is already pending for this grade.'];
        }

        $this->insert([
            'grade_id'        => $gradeId,
            'requested_by'    => $grade['faculty_id'],
            'current_value'   => $grade['grade_value'],
            'requested_value' => $grade['grade_value'],
            'reason'          => '',
            'status'          => 'registrar_form_issued',
            'issued_by'       => $registrarId,
            'issued_at'       => date('Y-m-d H:i:s'),
        ]);

        $requestId = $this->getInsertID();

        (new AuditLogModel())->record(
            'grade_change_request.issued',
            'grade_change_request',
            $requestId,
            null,
            ['grade_id' => $gradeId],
            null,
            $registrarId,
            'registrar'
        );

        helper('url');
        (new NotificationModel())->notify(
            (int) $grade['faculty_id'],
            'grade_change_form_issued',
            "The registrar has opened a correction form for your {$grade['subject_code']} grade.",
            site_url('faculty/grading')
        );

        return ['ok' => true, 'request_id' => $requestId];
    }

    /**
     * Faculty's response to an issued form — step 2
     * (FACULTY_SUBMITTED). Re-validates $requestedValue against the
     * grade's original grading_scale_id (never the currently active
     * scale), same invariant as GradeModel::requestChange().
     *
     * @param list<\CodeIgniter\HTTP\Files\UploadedFile> $files
     */
    public function submitFacultyResponse(int $requestId, string $requestedValue, string $reason, int $facultyId, array $files = []): array
    {
        $request = $this->find($requestId);

        if ($request === null || $request['status'] !== 'registrar_form_issued') {
            return ['ok' => false, 'error' => 'This request is not awaiting your response.'];
        }

        if ((int) $request['requested_by'] !== $facultyId) {
            return ['ok' => false, 'error' => 'You are not the faculty of record for this request.'];
        }

        $grade = (new GradeModel())->find((int) $request['grade_id']);

        if ($grade === null) {
            return ['ok' => false, 'error' => 'The underlying grade no longer exists.'];
        }

        $scale          = (new GradingScaleModel())->find((int) $grade['grading_scale_id']);
        $requestedValue = trim($requestedValue);

        if ($scale === null || ! GradeScaleEvaluator::isValidValue($scale, $requestedValue)) {
            return ['ok' => false, 'error' => "\"{$requestedValue}\" is not a valid value for this grade's scale."];
        }

        $this->update($requestId, [
            'requested_value' => $requestedValue,
            'reason'          => trim($reason),
            'status'          => 'faculty_submitted',
        ]);

        $attachments = new GradeChangeAttachmentModel();

        foreach ($files as $file) {
            if ($file !== null && $file->isValid()) {
                $attachments->storeUploaded($requestId, $file, $facultyId);
            }
        }

        (new AuditLogModel())->record(
            'grade_change_request.faculty_submitted',
            'grade_change_request',
            $requestId,
            ['current_value' => $grade['grade_value']],
            ['requested_value' => $requestedValue],
            trim($reason),
            $facultyId,
            'faculty'
        );

        $this->notifyOwningChairs((int) $grade['section_id'], 'grade_change_request.faculty_submitted', 'A grade correction request is awaiting your recommendation.');

        return ['ok' => true];
    }

    /**
     * Chairperson's step — faculty_submitted -> chair_recommended (or
     * rejected). Step 3 (CHAIR_RECOMMENDED).
     */
    public function chairRecommend(int $requestId, int $chairId, string $decision, ?string $notes = null): array
    {
        return $this->decide($requestId, 'faculty_submitted', $decision, [
            'chair_recommended_by' => $chairId,
            'chair_recommended_at' => date('Y-m-d H:i:s'),
            'chair_notes'          => $notes,
        ], 'chair_recommended', 'grade_change_request.chair_recommended', $chairId, 'chairperson', $notes);
    }

    /**
     * Dean's step — chair_recommended -> dean_recommended (or rejected).
     * Step 4 (DEAN_RECOMMENDED).
     */
    public function deanRecommend(int $requestId, int $deanId, string $decision, ?string $notes = null): array
    {
        return $this->decide($requestId, 'chair_recommended', $decision, [
            'dean_recommended_by' => $deanId,
            'dean_recommended_at' => date('Y-m-d H:i:s'),
            'dean_notes'          => $notes,
        ], 'dean_recommended', 'grade_change_request.dean_recommended', $deanId, 'dean', $notes);
    }

    /**
     * Registrar's final approval — dean_recommended -> registrar_final_approved,
     * the ONLY state transition that actually mutates the locked grade's
     * value (via GradeModel::applyApprovedChange()). Step 5
     * (REGISTRAR_FINAL_APPROVED).
     */
    public function finalApprove(int $requestId, int $registrarId, ?string $notes = null): array
    {
        $request = $this->find($requestId);

        if ($request === null || $request['status'] !== 'dean_recommended') {
            return ['ok' => false, 'error' => 'This request is not awaiting final approval.'];
        }

        $gradeModel = new GradeModel();
        $grade      = $gradeModel->find((int) $request['grade_id']);

        if ($grade === null) {
            return ['ok' => false, 'error' => 'The underlying grade no longer exists.'];
        }

        $db = $this->db;
        $db->transStart();

        $gradeModel->applyApprovedChange((int) $grade['grade_id'], $request['requested_value']);

        $this->update($requestId, [
            'status'             => 'registrar_final_approved',
            'final_approved_by'  => $registrarId,
            'final_approved_at'  => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not apply the approved change. Please try again.'];
        }

        $auditLog = new AuditLogModel();

        $auditLog->record(
            'grade_change_request.final_approved',
            'grade_change_request',
            $requestId,
            null,
            ['status' => 'registrar_final_approved'],
            $notes,
            $registrarId,
            'registrar'
        );

        $auditLog->record(
            'grade.value_corrected',
            'grade',
            $grade['grade_id'],
            ['grade_value' => $grade['grade_value']],
            ['grade_value' => $request['requested_value']],
            $notes,
            $registrarId,
            'registrar'
        );

        helper('url');
        $notifications = new NotificationModel();
        $notifications->notify(
            (int) $request['requested_by'],
            'grade_change_approved',
            "Your grade correction for {$grade['subject_code']} was approved and applied.",
            site_url('faculty/dashboard')
        );
        $notifications->notify(
            (int) $grade['user_id'],
            'grade_posted',
            "Your grade for {$grade['subject_code']} ({$grade['academic_year']}) has been corrected.",
            site_url('student/grades')
        );

        return ['ok' => true];
    }

    /**
     * Shared chair/dean decision shape: either moves the request to
     * $toStatus or rejects it outright. $decision must be 'recommend' or
     * 'reject'.
     */
    private function decide(int $requestId, string $fromStatus, string $decision, array $setOnRecommend, string $toStatus, string $auditAction, int $actorId, string $actorRole, ?string $notes): array
    {
        $request = $this->find($requestId);

        if ($request === null || $request['status'] !== $fromStatus) {
            return ['ok' => false, 'error' => 'This request is not awaiting your decision.'];
        }

        if (! in_array($decision, ['recommend', 'reject'], true)) {
            return ['ok' => false, 'error' => 'Invalid decision.'];
        }

        if ($decision === 'reject') {
            $this->update($requestId, ['status' => 'rejected']);

            (new AuditLogModel())->record(
                $auditAction . '.rejected',
                'grade_change_request',
                $requestId,
                ['status' => $fromStatus],
                ['status' => 'rejected'],
                $notes,
                $actorId,
                $actorRole
            );

            $grade = (new GradeModel())->find((int) $request['grade_id']);

            if ($grade !== null) {
                helper('url');
                (new NotificationModel())->notify(
                    (int) $request['requested_by'],
                    'grade_change_rejected',
                    "Your grade correction for {$grade['subject_code']} was rejected." . ($notes ? " Reason: {$notes}" : ''),
                    site_url('faculty/dashboard')
                );
            }

            return ['ok' => true];
        }

        $this->update($requestId, $setOnRecommend + ['status' => $toStatus]);

        (new AuditLogModel())->record(
            $auditAction,
            'grade_change_request',
            $requestId,
            ['status' => $fromStatus],
            ['status' => $toStatus],
            $notes,
            $actorId,
            $actorRole
        );

        $grade = (new GradeModel())->find((int) $request['grade_id']);

        if ($grade !== null) {
            if ($toStatus === 'chair_recommended') {
                $this->notifyDeanOfSection((int) $grade['section_id'], 'grade_change_request.chair_recommended', "A grade correction for {$grade['subject_code']} was recommended by the chairperson and is awaiting your recommendation.");
            } elseif ($toStatus === 'dean_recommended') {
                $this->notifyGroup('registrar', 'grade_change_request.dean_recommended', "A grade correction for {$grade['subject_code']} was recommended by the dean and is awaiting final registrar approval.");
            }
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
     * Notifies the dean(s) (primary + delegated) of the section's college —
     * the single-section counterpart to GradeModel::notifyDeansOfCollegeFor().
     */
    private function notifyDeanOfSection(int $sectionId, string $type, string $message): void
    {
        $section = (new SectionModel())->find($sectionId);

        if ($section === null || $section['program_id'] === null) {
            return;
        }

        $collegeId = $this->db->table('programs')->select('sub_department_id')->where('program_id', $section['program_id'])->get()->getRowArray()['sub_department_id'] ?? null;

        if ($collegeId === null) {
            return;
        }

        $collegeId = $this->db->table('sub_departments')->select('college_id')->where('sub_dept_id', $collegeId)->get()->getRowArray()['college_id'] ?? null;

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

        $notifications = new NotificationModel();

        foreach ($deanIds as $userId) {
            $notifications->notify((int) $userId, $type, $message);
        }
    }

    /**
     * Notifies the chairperson(s) owning the section's sub-department —
     * resolved the same way SchedulingAuthorization::matchSection() would,
     * but in reverse (section -> owning sub-department -> its chairs)
     * since there's no single authenticated actor to call
     * authorizedSubDeptIds() against here.
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

        if ($chairIds === []) {
            return;
        }

        $notifications = new NotificationModel();

        foreach ($chairIds as $userId) {
            $notifications->notify((int) $userId, $type, $message);
        }
    }

    public function forFaculty(int $userId): array
    {
        return $this->where('requested_by', $userId)->orderBy('created_at', 'DESC')->findAll();
    }

    /**
     * Every request (any status) ever filed against these grades, grouped by
     * grade_id, newest first — the only place a grade's prior value survives
     * once a correction is approved and overwrites grade_value. Powers the
     * "what was it before?" history shown alongside a grade.
     *
     * @param int[] $gradeIds
     * @return array<int, list<array>>
     */
    public function forGrades(array $gradeIds): array
    {
        if ($gradeIds === []) {
            return [];
        }

        $byGrade = [];

        $rows = $this
            ->select('grade_change_requests.*, requester.username AS requested_by_username, reviewer.username AS reviewed_by_username')
            ->join('users requester', 'requester.id = grade_change_requests.requested_by')
            ->join('users reviewer', 'reviewer.id = grade_change_requests.reviewed_by', 'left')
            ->whereIn('grade_change_requests.grade_id', $gradeIds)
            ->orderBy('grade_change_requests.created_at', 'DESC')
            ->findAll();

        foreach ($rows as $row) {
            $byGrade[(int) $row['grade_id']][] = $row;
        }

        return $byGrade;
    }

    /**
     * Requests awaiting this faculty member's response to an issued form —
     * step 2 (FACULTY_SUBMITTED) of the chain.
     */
    public function awaitingFacultyResponse(int $facultyId): array
    {
        return $this->db->table('grade_change_requests r')
            ->select('r.request_id, r.grade_id, r.current_value, r.requested_value, r.reason, r.issued_at,
                      g.subject_code, g.academic_year, s.program, s.year_level, s.section')
            ->join('grades g', 'g.grade_id = r.grade_id')
            ->join('sections s', 's.section_id = g.section_id')
            ->where('r.status', 'registrar_form_issued')
            ->where('r.requested_by', $facultyId)
            ->orderBy('r.issued_at', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Requests awaiting this chairperson's recommendation — scoped to
     * their authorized programs, same idea as pendingForPrograms().
     */
    public function pendingChairRecommendation(array $programs): array
    {
        return $this->statusForPrograms($programs, 'faculty_submitted');
    }

    /**
     * Requests awaiting this dean's recommendation — scoped to their
     * authorized programs (college-wide).
     */
    public function pendingDeanRecommendation(array $programs): array
    {
        return $this->statusForPrograms($programs, 'chair_recommended');
    }

    /**
     * Requests awaiting the registrar's final approval — institution-wide,
     * the registrar has no "own department" to scope by.
     */
    public function pendingRegistrarFinal(): array
    {
        return $this->statusForPrograms(null, 'dean_recommended');
    }

    private function statusForPrograms(?array $programs, string $status): array
    {
        if ($programs !== null && $programs === []) {
            return [];
        }

        $query = $this->db->table('grade_change_requests r')
            ->select('r.request_id, r.grade_id, r.current_value, r.requested_value, r.reason, r.created_at,
                      g.subject_code, g.academic_year, g.section_id, s.program, s.year_level, s.section,
                      su.username AS student_username, fu.username AS requested_by_username')
            ->join('grades g', 'g.grade_id = r.grade_id')
            ->join('sections s', 's.section_id = g.section_id')
            ->join('users su', 'su.id = g.user_id')
            ->join('users fu', 'fu.id = r.requested_by')
            ->where('r.status', $status)
            ->orderBy('r.created_at', 'ASC');

        if ($programs !== null) {
            $query->whereIn('s.program', $programs);
        }

        return $query->get()->getResultArray();
    }

    /**
     * Pending requests against a grade belonging to any of these programs —
     * the chairperson's own-department scoping, same idea as
     * SectionModel::forSubDepartment().
     */
    public function pendingForPrograms(array $programs): array
    {
        if ($programs === []) {
            return [];
        }

        return $this->db->table('grade_change_requests r')
            ->select('r.request_id, r.grade_id, r.current_value, r.requested_value, r.reason, r.created_at,
                      g.subject_code, g.academic_year, s.program, s.year_level, s.section,
                      su.username AS student_username, fu.username AS requested_by_username')
            ->join('grades g', 'g.grade_id = r.grade_id')
            ->join('sections s', 's.section_id = g.section_id')
            ->join('users su', 'su.id = g.user_id')
            ->join('users fu', 'fu.id = r.requested_by')
            ->where('r.status', 'pending')
            ->whereIn('s.program', $programs)
            ->orderBy('r.created_at', 'ASC')
            ->get()
            ->getResultArray();
    }
}

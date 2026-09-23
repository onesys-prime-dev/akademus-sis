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

        foreach ($roster as &$row) {
            $row['grade'] = $gradeByEnrollment[(int) $row['enrollment_id']] ?? null;
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
    public function saveOne(array $context, int $enrollmentId, string $value, ?string $remarks, int $facultyId): array
    {
        $existing = $this->where('enrollment_id', $enrollmentId)->first();

        if ($existing !== null && $existing['status'] === 'locked') {
            return ['ok' => false, 'error' => 'This grade is locked. File a change request instead.'];
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
    public function submitClass(array $enrollmentIds, int $facultyId): array
    {
        if ($enrollmentIds === []) {
            return ['ok' => false, 'error' => 'No students to submit.'];
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
                return ['ok' => false, 'error' => 'This class has already been locked by the chairperson.'];
            }
        }

        $this->builder()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->set(['status' => 'submitted', 'submitted_at' => date('Y-m-d H:i:s'), 'submitted_by' => $facultyId])
            ->update();

        return ['ok' => true];
    }

    /**
     * Bulk submitted -> locked, the chairperson's official approval. Every
     * grade in the batch must already be submitted — chairperson cannot
     * lock a class faculty hasn't finished with.
     *
     * @param int[] $enrollmentIds
     */
    public function lockClass(array $enrollmentIds, int $chairpersonId): array
    {
        if ($enrollmentIds === []) {
            return ['ok' => false, 'error' => 'No students to lock.'];
        }

        $rows = $this->whereIn('enrollment_id', $enrollmentIds)->findAll();

        if (count($rows) < count($enrollmentIds)) {
            return ['ok' => false, 'error' => 'Not every student has a submitted grade yet.'];
        }

        foreach ($rows as $row) {
            if ($row['status'] !== 'submitted') {
                return ['ok' => false, 'error' => 'Every grade must be submitted by faculty before it can be locked.'];
            }
        }

        $this->builder()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->set(['status' => 'locked', 'locked_at' => date('Y-m-d H:i:s'), 'locked_by' => $chairpersonId])
            ->update();

        // Locked is the moment a grade becomes visible to the student
        // (forStudent() only returns status = 'locked' rows) — the right
        // point to notify, not on draft/submit. helper('url') is loaded
        // explicitly rather than assumed, since Models (unlike
        // BaseController) don't get it autoloaded.
        helper('url');
        $notifications = new NotificationModel();

        foreach ($rows as $row) {
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

        return ['ok' => true, 'request_id' => $requests->getInsertID()];
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

        $scale = (new GradingScaleModel())->find((int) $grade['grading_scale_id']);

        $db = $this->db;
        $db->transStart();

        $this->update($grade['grade_id'], [
            'grade_value' => $request['requested_value'],
            'is_passing'  => $scale !== null ? GradeScaleEvaluator::isPassing($scale, $request['requested_value']) : null,
        ]);

        $requests->update($requestId, [
            'status'       => 'approved',
            'reviewed_by'  => $reviewedBy,
            'review_notes' => $notes,
            'reviewed_at'  => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if ($db->transStatus() !== false) {
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
        if ($programs === []) {
            return [];
        }

        $rows = $this->db->table('grades g')
            ->select('g.section_id, g.offering_id, g.subject_code, g.academic_year, s.program, s.year_level, s.section')
            ->join('sections s', 's.section_id = g.section_id')
            ->whereIn('s.program', $programs)
            ->where('g.status', 'submitted')
            ->get()
            ->getResultArray();

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
            }

            $classes[$key]['student_count']++;
        }

        return array_values($classes);
    }

    /**
     * Every locked grade for a student, newest term first — the student's
     * own read-only "My Grades" view.
     */
    public function forStudent(int $userId): array
    {
        return $this->where('user_id', $userId)
            ->where('status', 'locked')
            ->orderBy('academic_year', 'DESC')
            ->orderBy('subject_code', 'ASC')
            ->findAll();
    }
}

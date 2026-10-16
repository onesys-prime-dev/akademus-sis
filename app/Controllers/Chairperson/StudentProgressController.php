<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Libraries\SchedulingAuthorization;
use App\Libraries\StudentProgressService;
use App\Models\AcademicYearModel;
use App\Models\EnlistmentOverrideModel;
use App\Models\StudentModel;

/**
 * Academic-progress monitoring and graduation-candidate review for the
 * chairperson's authorized programs — the two checklist items share one
 * underlying calculation (StudentProgressService) and one page, filtered by
 * a "view" toggle, rather than becoming two separate pages that would drift
 * out of sync with each other.
 *
 * Read-only by design: a chairperson can see curriculum progress, passed/
 * failed/in-progress subjects, and missing prerequisites, but cannot edit a
 * student's record here — that stays with Registrar (Admin\StudentRecordController).
 */
class StudentProgressController extends BaseController
{
    public function index()
    {
        $userId   = (int) auth()->id();
        $programs = $this->authorizedPrograms($userId);

        $programId = (int) ($this->request->getGet('program_id') ?? 0);
        $view      = (string) ($this->request->getGet('view') ?? 'all');
        $q         = trim((string) ($this->request->getGet('q') ?? ''));

        if (! in_array($view, ['all', 'candidates', 'deficient'], true)) {
            $view = 'all';
        }

        $programIds = array_column($programs, 'program_id');

        if ($programId !== 0 && ! in_array($programId, $programIds, true)) {
            $programId = 0;
        }

        $rows = $programIds === [] ? [] : $this->rosterFor($programId !== 0 ? [$programId] : $programIds, $q);

        $students = array_column($rows, null, 'student_id');
        $summaries = (new StudentProgressService())->summarizeStudents(array_values($students));

        // Tab counts reflect the full program+search scope regardless of
        // which tab is currently active — otherwise "Graduation candidates"
        // would show 0 while standing on the "Needs attention" tab, since
        // those two sets are mutually exclusive by definition.
        $candidateCount = 0;
        $deficientCount = 0;
        $list = [];

        foreach ($rows as $row) {
            $summary = $summaries[(int) $row['student_id']] ?? null;

            if ($summary === null) {
                continue;
            }

            if ($summary['is_candidate']) {
                $candidateCount++;
            }

            if ($summary['deficiency_count'] > 0) {
                $deficientCount++;
            }

            if ($view === 'candidates' && ! $summary['is_candidate']) {
                continue;
            }

            if ($view === 'deficient' && $summary['deficiency_count'] === 0) {
                continue;
            }

            $list[] = $row + $summary;
        }

        return view('chairperson/student_progress', [
            'pageTitle'      => 'Student Progress',
            'user'           => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
            'programs'       => $programs,
            'candidateCount' => $candidateCount,
            'deficientCount' => $deficientCount,
            'programId'  => $programId,
            'view'       => $view,
            'q'          => $q,
            'students'   => $list,
            'noUnits'    => $programs === [],
        ]);
    }

    /**
     * JSON: one student's full curriculum-progress breakdown, for the
     * detail drawer. Scoped — a student outside the chairperson's
     * authorized programs returns 404, not their data.
     */
    public function detail()
    {
        $userId    = (int) auth()->id();
        $studentId = (int) ($this->request->getGet('student_id') ?? 0);

        if ($studentId <= 0) {
            return $this->fail('Invalid student.');
        }

        $student = (new StudentModel())->find($studentId);

        if ($student === null || $student['program_id'] === null
            || ! in_array((int) $student['program_id'], array_column($this->authorizedPrograms($userId), 'program_id'), true)) {
            return $this->fail('Student not found.');
        }

        $progress = (new StudentProgressService())->forStudent($studentId);

        if ($progress === null) {
            return $this->fail('Student not found.');
        }

        $activeTerm = (new AcademicYearModel())->activeFormatted();
        $overrides  = $activeTerm !== null
            ? (new EnlistmentOverrideModel())->activeForStudent($studentId, $activeTerm)
            : [];

        $identity = db_connect()->table('students s')
            ->select('u.first_name, u.last_name, s.student_number, p.program_name, c.curriculum_code, c.curriculum_name, sec.year_level, sec.section')
            ->join('users u', 'u.id = s.user_id')
            ->join('programs p', 'p.program_id = s.program_id', 'left')
            ->join('curriculum c', 'c.curriculum_id = s.curriculum_id', 'left')
            ->join('sections sec', 'sec.section_id = u.section_id', 'left')
            ->where('s.student_id', $studentId)
            ->get()
            ->getRowArray();

        return $this->response->setJSON([
            'ok'          => true,
            'student'     => $identity,
            'progress'    => $progress,
            'academicYear' => $activeTerm,
            'overrides'   => array_map(static fn (array $o): array => [
                'id'           => (int) $o['id'],
                'subject_code' => $o['subject_code'],
                'reason'       => $o['reason'],
            ], $overrides),
        ]);
    }

    /**
     * Grants an enlistment override for a subject this chairperson's
     * authorized student is currently blocked from (missing prerequisite or
     * out-of-curriculum). Scoped the same way detail() is — a student
     * outside the chair's authorized programs is refused.
     */
    public function grantOverride()
    {
        $userId      = (int) auth()->id();
        $studentId   = (int) $this->request->getPost('student_id');
        $subjectCode = (string) $this->request->getPost('subject_code');
        $reason      = (string) $this->request->getPost('reason');

        $student = (new StudentModel())->find($studentId);

        if ($student === null || $student['program_id'] === null
            || ! in_array((int) $student['program_id'], array_column($this->authorizedPrograms($userId), 'program_id'), true)) {
            return $this->fail('Student not found.');
        }

        $activeTerm = (new AcademicYearModel())->activeFormatted();

        if ($activeTerm === null) {
            return $this->fail('No active academic term.');
        }

        $result = (new EnlistmentOverrideModel())->grant($studentId, $subjectCode, $activeTerm, $reason, $userId, 'chairperson');

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Revokes an override this chairperson (or another chair/registrar)
     * previously granted, for one of this chair's authorized students.
     */
    public function revokeOverride()
    {
        $userId     = (int) auth()->id();
        $overrideId = (int) $this->request->getPost('override_id');

        $overrides = new EnlistmentOverrideModel();
        $override  = $overrides->find($overrideId);

        if ($override === null) {
            return $this->fail('Override not found.');
        }

        $student = (new StudentModel())->find((int) $override['student_id']);

        if ($student === null || $student['program_id'] === null
            || ! in_array((int) $student['program_id'], array_column($this->authorizedPrograms($userId), 'program_id'), true)) {
            return $this->fail('Student not found.');
        }

        $result = $overrides->revoke($overrideId, $userId);

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * @param list<int> $programIds
     */
    private function rosterFor(array $programIds, string $q): array
    {
        $builder = db_connect()->table('students s')
            ->select('s.student_id, s.student_number, s.user_id, s.curriculum_id, s.student_status,
                      u.first_name, u.last_name, u.section_id,
                      p.program_code, p.program_name,
                      c.curriculum_code,
                      sec.year_level, sec.section')
            ->join('users u', 'u.id = s.user_id')
            ->join('programs p', 'p.program_id = s.program_id', 'left')
            ->join('curriculum c', 'c.curriculum_id = s.curriculum_id', 'left')
            ->join('sections sec', 'sec.section_id = u.section_id', 'left')
            ->whereIn('s.program_id', $programIds)
            ->where('s.student_status', 'active')
            ->where('u.deleted_at', null);

        if ($q !== '') {
            $builder->groupStart()
                ->like('u.first_name', $q)
                ->orLike('u.last_name', $q)
                ->orLike('s.student_number', $q)
            ->groupEnd();
        }

        $rows = $builder->orderBy('u.last_name')->orderBy('u.first_name')->get()->getResultArray();

        foreach ($rows as &$row) {
            $row['student_id'] = (int) $row['student_id'];
            $row['user_id']    = (int) $row['user_id'];
            $row['curriculum_id'] = $row['curriculum_id'] !== null ? (int) $row['curriculum_id'] : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<array{program_id: int, program_code: string, program_name: string}>
     */
    private function authorizedPrograms(int $userId): array
    {
        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds($userId);

        if ($subDeptIds === []) {
            return [];
        }

        $rows = db_connect()->table('programs')
            ->select('program_id, program_code, program_name')
            ->whereIn('sub_department_id', $subDeptIds)
            ->where('is_active', 1)
            ->orderBy('program_name')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['program_id'] = (int) $row['program_id'];
        }
        unset($row);

        return $rows;
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

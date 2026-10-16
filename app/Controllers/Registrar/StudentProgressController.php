<?php

namespace App\Controllers\Registrar;

use App\Controllers\BaseController;
use App\Libraries\RegistrarPortal;
use App\Libraries\StudentProgressService;
use App\Models\AcademicYearModel;
use App\Models\EnlistmentOverrideModel;
use App\Models\StudentModel;

/**
 * Registrar's institution-wide counterpart to Chairperson\StudentProgressController
 * — same page/JS/CSS, same StudentProgressService-backed calculation, just
 * unscoped (every program, not just a chair's authorized ones), matching
 * Registrar\EnlistmentController's own "no program scoping" convention.
 */
class StudentProgressController extends BaseController
{
    public function index()
    {
        $programId = (int) ($this->request->getGet('program_id') ?? 0);
        $view      = (string) ($this->request->getGet('view') ?? 'all');
        $q         = trim((string) ($this->request->getGet('q') ?? ''));

        if (! in_array($view, ['all', 'candidates', 'deficient'], true)) {
            $view = 'all';
        }

        $programs   = $this->allPrograms();
        $programIds = array_column($programs, 'program_id');

        if ($programId !== 0 && ! in_array($programId, $programIds, true)) {
            $programId = 0;
        }

        $rows = $this->rosterFor($programId !== 0 ? [$programId] : $programIds, $q);

        $students  = array_column($rows, null, 'student_id');
        $summaries = (new StudentProgressService())->summarizeStudents(array_values($students));

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

        return view('registrar/student_progress', [
            'pageTitle'      => 'Student Progress',
            'programs'       => $programs,
            'candidateCount' => $candidateCount,
            'deficientCount' => $deficientCount,
            'programId'      => $programId,
            'view'           => $view,
            'q'              => $q,
            'students'       => $list,
            'noUnits'        => false,
        ] + RegistrarPortal::shell('student-progress'));
    }

    /**
     * JSON: one student's full curriculum-progress breakdown, for the
     * detail drawer. Institution-wide — no program scoping.
     */
    public function detail()
    {
        $studentId = (int) ($this->request->getGet('student_id') ?? 0);

        if ($studentId <= 0) {
            return $this->fail('Invalid student.');
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
            'ok'           => true,
            'student'      => $identity,
            'progress'     => $progress,
            'academicYear' => $activeTerm,
            'overrides'    => array_map(static fn (array $o): array => [
                'id'           => (int) $o['id'],
                'subject_code' => $o['subject_code'],
                'reason'       => $o['reason'],
            ], $overrides),
        ]);
    }

    public function grantOverride()
    {
        $registrarId = (int) auth()->id();
        $studentId   = (int) $this->request->getPost('student_id');
        $subjectCode = (string) $this->request->getPost('subject_code');
        $reason      = (string) $this->request->getPost('reason');

        $student = (new StudentModel())->find($studentId);

        if ($student === null) {
            return $this->fail('Student not found.');
        }

        $activeTerm = (new AcademicYearModel())->activeFormatted();

        if ($activeTerm === null) {
            return $this->fail('No active academic term.');
        }

        $result = (new EnlistmentOverrideModel())->grant($studentId, $subjectCode, $activeTerm, $reason, $registrarId, 'registrar');

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    public function revokeOverride()
    {
        $registrarId = (int) auth()->id();
        $overrideId  = (int) $this->request->getPost('override_id');

        $result = (new EnlistmentOverrideModel())->revoke($overrideId, $registrarId);

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON(['ok' => true]);
    }

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
            ->where('s.student_status', 'active')
            ->where('u.deleted_at', null);

        if ($programIds !== []) {
            $builder->whereIn('s.program_id', $programIds);
        }

        if ($q !== '') {
            $builder->groupStart()
                ->like('u.first_name', $q)
                ->orLike('u.last_name', $q)
                ->orLike('s.student_number', $q)
            ->groupEnd();
        }

        $rows = $builder->orderBy('u.last_name')->orderBy('u.first_name')->get()->getResultArray();

        foreach ($rows as &$row) {
            $row['student_id']    = (int) $row['student_id'];
            $row['user_id']       = (int) $row['user_id'];
            $row['curriculum_id'] = $row['curriculum_id'] !== null ? (int) $row['curriculum_id'] : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<array{program_id: int, program_code: string, program_name: string}>
     */
    private function allPrograms(): array
    {
        $rows = db_connect()->table('programs')
            ->select('program_id, program_code, program_name')
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

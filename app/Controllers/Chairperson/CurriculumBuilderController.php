<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Libraries\SchedulingAuthorization;
use App\Models\CurriculumModel;
use App\Models\CurriculumSubjectModel;
use App\Models\SubjectModel;

/**
 * Step: Subject Ownership -> Curriculum Builder.
 *
 * Builds curriculum directly on the formal tables (curriculum /
 * curriculum_subjects) — the same source of truth SubjectDemandService /
 * SharedSubjectDashboardService / Coverage and EnrollmentModel already read
 * from. A chairperson may only edit curriculum for programs their
 * department owns (SchedulingAuthorization). Every program has exactly one
 * curriculum (CurriculumModel::liveFor()), always live and directly
 * editable — there is no draft/publish step.
 *
 * This is the sole supported curriculum source of truth — the legacy
 * curriculum_presets-backed Curriculum page and its preset-writing
 * endpoints have been retired.
 */
class CurriculumBuilderController extends BaseController
{
    /** 1 = 1st, 2 = 2nd, 3 = Summer — matches curriculum_subjects.semester / TermCurriculum::SEMESTERS. */
    private const SEMESTERS = ['1' => '1st Semester', '2' => '2nd Semester', '3' => 'Summer'];

    public function index()
    {
        $userId   = (int) auth()->id();
        $programs = $this->authorizedPrograms($userId);

        if ($programs === []) {
            return view('chairperson/curriculum_builder', [
                'pageTitle' => 'Curriculum Builder',
                'user'      => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
                'programs'  => [],
                'selectedProgram' => null,
                'curriculum' => null,
                'termSubjects' => [],
                'yearLevel' => 1,
                'semester'  => '1',
                'semesters' => self::SEMESTERS,
            ]);
        }

        $programId = (int) ($this->request->getGet('program_id') ?? $programs[0]['program_id']);

        if (! in_array($programId, array_column($programs, 'program_id'), true)) {
            $programId = (int) $programs[0]['program_id'];
        }

        $yearLevel = max(1, min(4, (int) ($this->request->getGet('year_level') ?? 1)));
        $semester  = (string) ($this->request->getGet('semester') ?? '1');

        if (! isset(self::SEMESTERS[$semester])) {
            $semester = '1';
        }

        $curriculum = (new CurriculumModel())->liveFor($programId);

        $termSubjects = $this->termSubjectsFor((int) $curriculum['curriculum_id'], $yearLevel, $semester);

        return view('chairperson/curriculum_builder', [
            'pageTitle' => 'Curriculum Builder',
            'user'      => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
            'programs'  => $programs,
            'selectedProgram' => $programId,
            'curriculum' => $curriculum,
            'termSubjects' => $termSubjects,
            'yearLevel' => $yearLevel,
            'semester'  => $semester,
            'semesters' => self::SEMESTERS,
        ]);
    }

    /**
     * JSON data for one year/semester cell — same query index() runs, just
     * without the full page around it. Backs the year-level/semester tabs'
     * client-side swap (see curriculum_builder.php's navigate()): switching
     * terms used to be a full page reload (~600-800ms, the whole console
     * chrome re-painting with it); this lets it re-render just the
     * workspace in place instead.
     */
    public function term()
    {
        $userId    = (int) auth()->id();
        $programId = (int) ($this->request->getGet('program_id') ?? 0);

        if (! $this->authorizedForProgram($userId, $programId)) {
            return $this->fail('Not authorized for this program.');
        }

        $yearLevel = max(1, min(4, (int) ($this->request->getGet('year_level') ?? 1)));
        $semester  = (string) ($this->request->getGet('semester') ?? '1');

        if (! isset(self::SEMESTERS[$semester])) {
            $semester = '1';
        }

        $curriculum = (new CurriculumModel())->liveFor($programId);

        $termSubjects = $this->termSubjectsFor((int) $curriculum['curriculum_id'], $yearLevel, $semester);

        $subjects = array_map(static function (array $ts): array {
            $isLab = $ts['assignment_is_laboratory'] !== null ? (bool) $ts['assignment_is_laboratory'] : (bool) $ts['default_is_laboratory'];

            return [
                'curriculum_subject_id' => (int) $ts['curriculum_subject_id'],
                'subject_code'          => $ts['subject_code'],
                'subject_name'          => $ts['subject_name'],
                'units'                 => (float) $ts['units'],
                'is_laboratory'         => $isLab,
            ];
        }, $termSubjects);

        return $this->response->setJSON([
            'ok'             => true,
            'year_level'     => $yearLevel,
            'semester'       => $semester,
            'semester_label' => self::SEMESTERS[$semester],
            'subjects'       => $subjects,
        ]);
    }

    /**
     * Adds (or reuses) one subject into the program's curriculum at a
     * specific year level + semester — the subject itself may come from ANY
     * department's catalog (see Chairperson\SubjectOwnershipController /
     * ChairpersonController::searchSubjects()); only the ASSIGNMENT is
     * scoped to this program's curriculum, never the catalog row's ownership.
     */
    public function addSubject()
    {
        $payload = $this->request->getJSON(true) ?? [];
        $userId       = (int) auth()->id();
        $curriculumId = (int) ($payload['curriculum_id'] ?? 0);
        $subjectId    = (int) ($payload['subject_id'] ?? 0);
        $yearLevel    = max(1, min(4, (int) ($payload['year_level'] ?? 0)));
        $semester     = (int) ($payload['semester'] ?? 0);
        $isLab        = $payload['is_laboratory'] ?? null;

        $curriculum = $this->curriculumFor($userId, $curriculumId);

        if ($curriculum === null) {
            return $this->fail('Curriculum not found, or not yours to edit.');
        }

        if (! isset(self::SEMESTERS[(string) $semester])) {
            return $this->fail('Invalid semester.');
        }

        $subject = (new SubjectModel())->where('subject_id', $subjectId)->where('is_active', 1)->first();

        if ($subject === null) {
            return $this->fail('Subject not found.');
        }

        $curriculumSubjects = new CurriculumSubjectModel();

        $existing = $curriculumSubjects
            ->where('curriculum_id', $curriculumId)
            ->where('subject_id', $subjectId)
            ->where('year_level', $yearLevel)
            ->where('semester', $semester)
            ->first();

        if ($existing !== null) {
            return $this->fail("{$subject['subject_code']} is already assigned to this term.");
        }

        $curriculumSubjects->insert([
            'curriculum_id' => $curriculumId,
            'subject_id'    => $subjectId,
            'year_level'    => $yearLevel,
            'semester'      => $semester,
            'is_required'   => 1,
            'is_laboratory' => $isLab === null ? null : (bool) $isLab,
            'is_active'     => 1,
        ]);

        return $this->response->setJSON(['ok' => true]);
    }

    public function removeSubject($curriculumSubjectId)
    {
        $userId = (int) auth()->id();
        $row    = (new CurriculumSubjectModel())->find((int) $curriculumSubjectId);

        if ($row === null) {
            return $this->fail('Not found.');
        }

        $curriculum = $this->curriculumFor($userId, (int) $row['curriculum_id']);

        if ($curriculum === null) {
            return $this->fail('Curriculum not found, or not yours to edit.');
        }

        (new CurriculumSubjectModel())->delete((int) $curriculumSubjectId);

        return $this->response->setJSON(['ok' => true]);
    }

    public function updateClassification($curriculumSubjectId)
    {
        $userId = (int) auth()->id();
        $row    = (new CurriculumSubjectModel())->find((int) $curriculumSubjectId);

        if ($row === null) {
            return $this->fail('Not found.');
        }

        $curriculum = $this->curriculumFor($userId, (int) $row['curriculum_id']);

        if ($curriculum === null) {
            return $this->fail('Curriculum not found, or not yours to edit.');
        }

        $payload = $this->request->getJSON(true) ?? [];
        $isLab   = $payload['is_laboratory'] ?? null;

        (new CurriculumSubjectModel())->update((int) $curriculumSubjectId, [
            'is_laboratory' => $isLab === null ? null : (bool) $isLab,
        ]);

        return $this->response->setJSON(['ok' => true]);
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

        // MySQLi results come back as strings for every column — cast here
        // so downstream strict in_array()/== comparisons against (int)
        // request values behave correctly instead of silently failing.
        foreach ($rows as &$row) {
            $row['program_id'] = (int) $row['program_id'];
        }
        unset($row);

        return $rows;
    }

    private function authorizedForProgram(int $userId, int $programId): bool
    {
        return in_array($programId, array_column($this->authorizedPrograms($userId), 'program_id'), true);
    }

    /**
     * The curriculum row, only if it belongs to a program this chairperson
     * is authorized for.
     */
    private function curriculumFor(int $userId, int $curriculumId): ?array
    {
        $curriculum = (new CurriculumModel())->find($curriculumId);

        if ($curriculum === null) {
            return null;
        }

        return $this->authorizedForProgram($userId, (int) $curriculum['program_id']) ? $curriculum : null;
    }

    private function termSubjectsFor(int $curriculumId, int $yearLevel, string $semester): array
    {
        return db_connect()->table('curriculum_subjects cs')
            ->select('cs.curriculum_subject_id, cs.is_required, cs.is_laboratory AS assignment_is_laboratory, s.subject_id, s.subject_code, s.subject_name, s.units, s.is_laboratory AS default_is_laboratory, s.sub_department_id')
            ->join('subjects s', 's.subject_id = cs.subject_id')
            ->where('cs.curriculum_id', $curriculumId)
            ->where('cs.year_level', $yearLevel)
            ->where('cs.semester', (int) $semester)
            ->where('cs.is_active', 1)
            ->orderBy('s.subject_code')
            ->get()
            ->getResultArray();
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

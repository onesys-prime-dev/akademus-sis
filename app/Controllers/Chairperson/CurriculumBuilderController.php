<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Libraries\SchedulingAuthorization;
use App\Models\CurriculumModel;
use App\Models\CurriculumSubjectModel;
use App\Models\SubjectModel;

/**
 * Step: Subject Ownership -> Curriculum Builder -> Admin Publish/Approval.
 *
 * Builds DRAFT curriculum versions on the formal, versioned tables
 * (curriculum / curriculum_subjects) — the same source of truth
 * SubjectDemandService / SharedSubjectDashboardService / Coverage and
 * EnrollmentModel already read from. A chairperson may only create/edit
 * drafts for programs their department owns (SchedulingAuthorization) and
 * only while status = 'draft' (CurriculumModel::isEditable()); a published
 * curriculum is immutable here exactly as it is in Admin's own tools —
 * publishing itself stays an explicit Admin action (Admin -> Curriculum).
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
                'versions'  => [],
                'draft'     => null,
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

        $curricula = new CurriculumModel();
        $versions  = $curricula->where('program_id', $programId)->orderBy('version_number', 'DESC')->findAll();
        $draft     = current(array_filter($versions, static fn (array $v) => $v['status'] === 'draft')) ?: null;

        $termSubjects = [];

        if ($draft !== null) {
            $termSubjects = db_connect()->table('curriculum_subjects cs')
                ->select('cs.curriculum_subject_id, cs.is_required, cs.is_laboratory AS assignment_is_laboratory, s.subject_id, s.subject_code, s.subject_name, s.units, s.is_laboratory AS default_is_laboratory, s.sub_department_id')
                ->join('subjects s', 's.subject_id = cs.subject_id')
                ->where('cs.curriculum_id', $draft['curriculum_id'])
                ->where('cs.year_level', $yearLevel)
                ->where('cs.semester', (int) $semester)
                ->where('cs.is_active', 1)
                ->orderBy('s.subject_code')
                ->get()
                ->getResultArray();
        }

        return view('chairperson/curriculum_builder', [
            'pageTitle' => 'Curriculum Builder',
            'user'      => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
            'programs'  => $programs,
            'selectedProgram' => $programId,
            'versions'  => $versions,
            'draft'     => $draft,
            'termSubjects' => $termSubjects,
            'yearLevel' => $yearLevel,
            'semester'  => $semester,
            'semesters' => self::SEMESTERS,
        ]);
    }

    /**
     * Starts a new draft for a program: version 1 if the program has no
     * curriculum at all yet, or the next version branched from its current
     * published one (CurriculumModel::createNewVersion() copies its subject
     * list forward so editing never starts from empty for an established
     * program). Refuses if a draft already exists — only one open draft per
     * program at a time, to avoid two chairpersons silently forking it.
     */
    public function createDraft()
    {
        $userId    = (int) auth()->id();
        $programId = (int) ($this->request->getPost('program_id') ?? 0);

        if (! $this->authorizedForProgram($userId, $programId)) {
            return $this->fail('You are not authorized to build curriculum for that program.');
        }

        $curricula = new CurriculumModel();

        if ($curricula->where('program_id', $programId)->where('status', 'draft')->countAllResults() > 0) {
            return $this->fail('A draft already exists for this program.');
        }

        $current = $curricula->currentPublishedFor($programId);

        if ($current !== null) {
            $result = $curricula->createNewVersion((int) $current['curriculum_id']);

            if (! $result['ok']) {
                return $this->fail($result['error'] ?? 'Could not create a new draft version.');
            }

            return redirect()->to('/chairperson/curriculum?program_id=' . $programId)->with('message', 'New draft version created from the current published curriculum.');
        }

        $program = db_connect()->table('programs')->where('program_id', $programId)->get()->getRowArray();

        if ($program === null) {
            return $this->fail('Program not found.');
        }

        $code = $program['program_code'] . '-' . date('Y');
        $n    = 1;

        while ($curricula->where('curriculum_code', $code)->countAllResults() > 0) {
            $code = $program['program_code'] . '-' . date('Y') . '-' . (++$n);
        }

        $curricula->insert([
            'program_id'      => $programId,
            'curriculum_code' => $code,
            'curriculum_name' => $program['program_name'] . ' Curriculum',
            'academic_year'   => date('Y') . '-' . (date('Y') + 1),
            'description'     => 'Draft started from the Chairperson Curriculum Builder.',
            'is_active'       => 1,
            'version_number'  => 1,
            'status'          => 'draft',
            'is_current'      => 0,
        ]);

        $id = $curricula->getInsertID();
        $curricula->update($id, ['base_curriculum_id' => $id]);

        return redirect()->to('/chairperson/curriculum?program_id=' . $programId)->with('message', 'New draft curriculum started.');
    }

    /**
     * Adds (or reuses) one subject into the draft at a specific
     * year level + semester — the subject itself may come from ANY
     * department's catalog (see Chairperson\SubjectOwnershipController /
     * ChairpersonController::searchSubjects()); only the ASSIGNMENT is
     * scoped to this program's draft, never the catalog row's ownership.
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

        $curriculum = $this->editableDraftFor($userId, $curriculumId);

        if ($curriculum === null) {
            return $this->fail('Draft not found, not yours to edit, or already published.');
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

        $curriculum = $this->editableDraftFor($userId, (int) $row['curriculum_id']);

        if ($curriculum === null) {
            return $this->fail('Draft not found, not yours to edit, or already published.');
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

        $curriculum = $this->editableDraftFor($userId, (int) $row['curriculum_id']);

        if ($curriculum === null) {
            return $this->fail('Draft not found, not yours to edit, or already published.');
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
     * The curriculum row, only if it's a DRAFT (CurriculumModel::isEditable())
     * belonging to a program this chairperson is authorized for.
     */
    private function editableDraftFor(int $userId, int $curriculumId): ?array
    {
        $curriculum = (new CurriculumModel())->find($curriculumId);

        if ($curriculum === null || ! (new CurriculumModel())->isEditable($curriculum)) {
            return null;
        }

        return $this->authorizedForProgram($userId, (int) $curriculum['program_id']) ? $curriculum : null;
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

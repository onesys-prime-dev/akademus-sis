<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CurriculumSubjectModel;
use App\Models\CurriculumModel;
use App\Models\SubjectModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class CurriculumSubjectController extends BaseController
{
    protected CurriculumSubjectModel $curriculumSubjectModel;
    protected CurriculumModel $curriculumModel;
    protected SubjectModel $subjectModel;

    public function __construct()
    {
        $this->curriculumSubjectModel = new CurriculumSubjectModel();
        $this->curriculumModel = new CurriculumModel();
        $this->subjectModel = new SubjectModel();
    }

    /** 1 = 1st, 2 = 2nd, 3 = Summer — matches curriculum_subjects.semester / Chairperson\CurriculumBuilderController::SEMESTERS. */
    private const SEMESTERS = ['1' => '1st Semester', '2' => '2nd Semester', '3' => 'Summer'];

    /**
     * Step 1: every curriculum, as a plain list — click one to manage its
     * subjects (see manage()).
     */
    public function index()
    {
        $curricula = db_connect()->table('curriculum c')
            ->select('c.curriculum_id, c.curriculum_code, c.curriculum_name, p.program_name')
            ->join('programs p', 'p.program_id = c.program_id', 'left')
            ->orderBy('p.program_name')
            ->orderBy('c.curriculum_code')
            ->get()
            ->getResultArray();

        $counts = [];

        if ($curricula !== []) {
            foreach (db_connect()->table('curriculum_subjects')
                ->select('curriculum_id, COUNT(*) AS cnt')
                ->whereIn('curriculum_id', array_column($curricula, 'curriculum_id'))
                ->where('is_active', 1)
                ->groupBy('curriculum_id')
                ->get()
                ->getResultArray() as $row) {
                $counts[(int) $row['curriculum_id']] = (int) $row['cnt'];
            }
        }

        foreach ($curricula as &$c) {
            $c['subject_count'] = $counts[(int) $c['curriculum_id']] ?? 0;
        }
        unset($c);

        return view('admin/curriculum-subjects/index', [
            'activeAdmin' => 'curriculum-subjects',
            'curricula'   => $curricula,
        ]);
    }

    /**
     * Step 2: builder-style workspace for ONE curriculum — pick a year
     * level + semester, manage its subjects inline. Mirrors the
     * Chairperson Curriculum Builder, but open to every curriculum (not
     * just one department's programs) since this is the admin-wide view.
     */
    public function manage($curriculumId)
    {
        $curriculum = $this->curriculumModel->find((int) $curriculumId);

        if ($curriculum === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $program = db_connect()->table('programs')->where('program_id', $curriculum['program_id'])->get()->getRowArray();

        $curricula = db_connect()->table('curriculum c')
            ->select('c.curriculum_id, c.curriculum_code, c.curriculum_name, p.program_name')
            ->join('programs p', 'p.program_id = c.program_id', 'left')
            ->orderBy('p.program_name')
            ->orderBy('c.curriculum_code')
            ->get()
            ->getResultArray();

        $yearLevel = max(1, min(4, (int) ($this->request->getGet('year_level') ?? 1)));
        $semester  = (string) ($this->request->getGet('semester') ?? '1');

        if (! isset(self::SEMESTERS[$semester])) {
            $semester = '1';
        }

        // owner_dept_name is shown per subject so admin sees, at a glance,
        // which department's chairperson this assignment will actually
        // appear for on Subject Ownership's "Course distribution" (that
        // page reads the very same curriculum_subjects rows — nothing extra
        // to sync, this view just surfaces the ownership that already
        // governs it).
        $termSubjects = db_connect()->table('curriculum_subjects cs')
            ->select('cs.curriculum_subject_id, cs.is_required, cs.is_laboratory AS assignment_is_laboratory, s.subject_id, s.subject_code, s.subject_name, s.units, s.is_laboratory AS default_is_laboratory, sd.name AS owner_dept_name')
            ->join('subjects s', 's.subject_id = cs.subject_id')
            ->join('sub_departments sd', 'sd.sub_dept_id = s.sub_department_id', 'left')
            ->where('cs.curriculum_id', $curriculum['curriculum_id'])
            ->where('cs.year_level', $yearLevel)
            ->where('cs.semester', (int) $semester)
            ->where('cs.is_active', 1)
            ->orderBy('s.subject_code')
            ->get()
            ->getResultArray();

        return view('admin/curriculum-subjects/manage', [
            'activeAdmin'    => 'curriculum-subjects',
            'curriculum'     => $curriculum,
            'programName'    => $program['program_name'] ?? null,
            'curricula'      => $curricula,
            'termSubjects'   => $termSubjects,
            'yearLevel'      => $yearLevel,
            'semester'       => $semester,
            'semesters'      => self::SEMESTERS,
        ]);
    }

    /** Catalog search for the inline "add a subject" widget — every active subject, no department restriction. */
    public function searchSubjects()
    {
        $query = trim((string) $this->request->getGet('q'));

        if ($query === '') {
            return $this->response->setJSON(['ok' => true, 'subjects' => []]);
        }

        $rows = db_connect()->table('subjects s')
            ->select('s.subject_id, s.subject_code, s.subject_name, s.units, s.is_laboratory, sd.name AS owner_dept_name')
            ->join('sub_departments sd', 'sd.sub_dept_id = s.sub_department_id', 'left')
            ->where('s.is_active', 1)
            ->groupStart()
                ->like('s.subject_code', $query)
                ->orLike('s.subject_name', $query)
            ->groupEnd()
            ->orderBy('s.subject_code')
            ->get(8)
            ->getResultArray();

        $subjects = array_map(static fn (array $s) => [
            'subject_id'      => (int) $s['subject_id'],
            'code'            => $s['subject_code'],
            'title'           => $s['subject_name'],
            'units'           => (float) $s['units'],
            'is_lab'          => (bool) $s['is_laboratory'],
            'owner_dept_name' => $s['owner_dept_name'],
        ], $rows);

        return $this->response->setJSON(['ok' => true, 'subjects' => $subjects]);
    }

    /** Adds (or reuses) one subject into a curriculum at a specific year level + semester. */
    public function addSubject()
    {
        $payload      = $this->request->getJSON(true) ?? [];
        $curriculumId = (int) ($payload['curriculum_id'] ?? 0);
        $subjectId    = (int) ($payload['subject_id'] ?? 0);
        $yearLevel    = max(1, min(4, (int) ($payload['year_level'] ?? 0)));
        $semester     = (int) ($payload['semester'] ?? 0);
        $isLab        = $payload['is_laboratory'] ?? null;

        if ($this->curriculumModel->find($curriculumId) === null) {
            return $this->fail('Curriculum not found.');
        }

        if (! isset(self::SEMESTERS[(string) $semester])) {
            return $this->fail('Invalid semester.');
        }

        $subject = $this->subjectModel->where('subject_id', $subjectId)->where('is_active', 1)->first();

        if ($subject === null) {
            return $this->fail('Subject not found.');
        }

        $existing = $this->curriculumSubjectModel
            ->where('curriculum_id', $curriculumId)
            ->where('subject_id', $subjectId)
            ->where('year_level', $yearLevel)
            ->where('semester', $semester)
            ->first();

        if ($existing !== null) {
            return $this->fail("{$subject['subject_code']} is already assigned to this term.");
        }

        $this->curriculumSubjectModel->insert([
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
        $row = $this->curriculumSubjectModel->find((int) $curriculumSubjectId);

        if ($row === null) {
            return $this->fail('Not found.');
        }

        $this->curriculumSubjectModel->delete((int) $curriculumSubjectId);

        return $this->response->setJSON(['ok' => true]);
    }

    public function updateClassification($curriculumSubjectId)
    {
        $row = $this->curriculumSubjectModel->find((int) $curriculumSubjectId);

        if ($row === null) {
            return $this->fail('Not found.');
        }

        $payload = $this->request->getJSON(true) ?? [];
        $isLab   = $payload['is_laboratory'] ?? null;

        $this->curriculumSubjectModel->update((int) $curriculumSubjectId, [
            'is_laboratory' => $isLab === null ? null : (bool) $isLab,
        ]);

        return $this->response->setJSON(['ok' => true]);
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

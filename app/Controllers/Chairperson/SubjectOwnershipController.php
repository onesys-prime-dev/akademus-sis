<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Libraries\SchedulingAuthorization;
use App\Models\PlottedScheduleModel;
use App\Models\SubjectModel;

/**
 * Step: Subject Catalog -> Subject Ownership.
 *
 * Which subjects a department owns — a separate concern from the
 * Curriculum Builder (which subjects a PROGRAM requires, in which term).
 * Ownership lives on subjects.sub_department_id; a subject is NOT tied to
 * one specific program here (subjects.program_id stays null unless the
 * chairperson explicitly ties it to one) — a department-owned subject is
 * discoverable and reusable by every program across every department via
 * the Curriculum Builder's subject search, never duplicated per program.
 *
 * Scoped via SchedulingAuthorization::authorizedSubDeptIds() rather than a
 * single owned department, so a chairperson delegated across more than one
 * department (see the Assign Chairperson / multi-department fix) manages
 * every department they're authorized for from one page.
 */
class SubjectOwnershipController extends BaseController
{
    private const ALLOWED_DURATIONS = [1.0, 1.5, 2.0, 2.5, 3.0];

    public function index()
    {
        $userId     = (int) auth()->id();
        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds($userId);

        $subjects = $subDeptIds === [] ? [] : (new SubjectModel())
            ->whereIn('sub_department_id', $subDeptIds)
            ->orderBy('is_active', 'DESC')
            ->orderBy('subject_code')
            ->findAll();

        $deptNames = [];

        if ($subDeptIds !== []) {
            foreach (db_connect()->table('sub_departments')->whereIn('sub_dept_id', $subDeptIds)->get()->getResultArray() as $row) {
                $deptNames[(int) $row['sub_dept_id']] = $row['name'];
            }
        }

        $courses = [];

        if ($subDeptIds !== []) {
            foreach (db_connect()->table('programs')
                ->select('program_id, program_code, program_name, sub_department_id')
                ->whereIn('sub_department_id', $subDeptIds)
                ->where('is_active', 1)
                ->orderBy('program_name')
                ->get()->getResultArray() as $row) {
                $courses[(int) $row['sub_department_id']][] = [
                    'id'   => (int) $row['program_id'],
                    'code' => $row['program_code'],
                    'name' => $row['program_name'],
                ];
            }
        }

        $subjectCourses = [];
        $subjectIds     = array_column($subjects, 'subject_id');

        if ($subjectIds !== []) {
            foreach (db_connect()->table('curriculum_subjects cs')
                ->select('cs.subject_id, cs.year_level, cs.semester, p.program_id, p.program_code, p.program_name')
                ->join('curriculum c', 'c.curriculum_id = cs.curriculum_id')
                ->join('programs p', 'p.program_id = c.program_id')
                ->whereIn('cs.subject_id', $subjectIds)
                ->where('cs.is_active', 1)
                ->orderBy('cs.year_level')
                ->orderBy('cs.semester')
                ->get()->getResultArray() as $row) {
                $subjectCourses[(int) $row['subject_id']][] = [
                    'program_id'   => (int) $row['program_id'],
                    'program_code' => $row['program_code'],
                    'program_name' => $row['program_name'],
                    'year_level'   => (int) $row['year_level'],
                    'semester'     => (int) $row['semester'],
                ];
            }
        }

        return view('chairperson/subject_ownership', [
            'pageTitle'   => 'Subject Ownership',
            'user'        => [
                'name' => auth()->user()->username ?? 'Chairperson',
                'role' => (count($deptNames) === 1 ? reset($deptNames) . ' - ' : '') . 'Chairperson',
            ],
            'deptNames'      => $deptNames,
            'hasAnyDept'     => $subDeptIds !== [],
            'subjects'       => $subjects,
            'durations'      => self::ALLOWED_DURATIONS,
            'courses'        => $courses,
            'subjectCourses' => $subjectCourses,
        ]);
    }

    public function store()
    {
        $userId     = (int) auth()->id();
        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds($userId);

        if ($subDeptIds === []) {
            return $this->fail('You are not assigned to a department yet.');
        }

        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->fail('Invalid payload.');
        }

        $subDeptId = (int) ($payload['sub_department_id'] ?? $subDeptIds[0]);

        if (! in_array($subDeptId, $subDeptIds, true)) {
            return $this->fail('You are not authorized to add subjects for that department.');
        }

        $code  = strtoupper(trim((string) ($payload['code'] ?? '')));
        $title = trim((string) ($payload['title'] ?? ''));

        if (! preg_match('/^[A-Z0-9][A-Z0-9 ._-]{1,19}$/', $code)) {
            return $this->fail('Subject code must be 2-20 letters/numbers (e.g. IT106 or ITE 108.1).');
        }

        if ($title === '' || mb_strlen($title) > 200) {
            return $this->fail('Subject title is required (max 200 characters).');
        }

        $units = (float) ($payload['units'] ?? 0);

        if ($units < 0.5 || $units > 6) {
            return $this->fail('Units must be between 0.5 and 6.');
        }

        $duration = (float) ($payload['duration'] ?? 0);

        if (! in_array($duration, self::ALLOWED_DURATIONS, true)) {
            return $this->fail('Duration must be one of: ' . implode(', ', self::ALLOWED_DURATIONS) . ' hours.');
        }

        $isLab = ! empty($payload['is_lab']);

        $subjects = new SubjectModel();
        $existing = $subjects->where('subject_code', $code)->first();

        if ($existing !== null && $existing['is_active']) {
            return $this->fail("Subject {$code} already exists in the catalog, owned by another department — it can be reused from the Curriculum Builder instead of being re-created here.");
        }

        $row = [
            'sub_department_id' => $subDeptId,
            'program_id'        => null,
            'subject_code'      => $code,
            'subject_name'      => $title,
            'units'             => $units,
            'contact_hours'     => $duration,
            'is_laboratory'     => $isLab ? 1 : 0,
            'is_active'         => 1,
        ];

        if ($existing === null) {
            $subjects->insert($row);
        } else {
            // Reactivating a previously-deactivated code — safe to reassign
            // ownership here since nothing references it while inactive.
            $subjects->update($existing['subject_id'], $row);
        }

        return $this->response->setJSON(['ok' => true, 'subject_id' => $existing['subject_id'] ?? $subjects->getInsertID()]);
    }

    public function update($id)
    {
        $userId     = (int) auth()->id();
        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds($userId);

        $subjects = new SubjectModel();
        $existing = $subjects->find((int) $id);

        if ($existing === null || ! in_array((int) $existing['sub_department_id'], $subDeptIds, true)) {
            return $this->fail('Subject not found, or not owned by your department.');
        }

        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->fail('Invalid payload.');
        }

        $title = trim((string) ($payload['title'] ?? ''));

        if ($title === '' || mb_strlen($title) > 200) {
            return $this->fail('Subject title is required (max 200 characters).');
        }

        $units = (float) ($payload['units'] ?? 0);

        if ($units < 0.5 || $units > 6) {
            return $this->fail('Units must be between 0.5 and 6.');
        }

        $duration = (float) ($payload['duration'] ?? 0);

        if (! in_array($duration, self::ALLOWED_DURATIONS, true)) {
            return $this->fail('Duration must be one of: ' . implode(', ', self::ALLOWED_DURATIONS) . ' hours.');
        }

        $isLab = ! empty($payload['is_lab']);

        $plotted = (new PlottedScheduleModel())->where('subject_code', $existing['subject_code'])->countAllResults();

        if ($plotted > 0) {
            $classes = $plotted . ' ' . ($plotted === 1 ? 'class' : 'classes');

            if (abs($duration - (float) $existing['contact_hours']) > 0.01 || $isLab !== (bool) $existing['is_laboratory']) {
                return $this->fail("{$existing['subject_code']} is plotted in {$classes} — its duration/laboratory setting cannot change until those are removed.");
            }
        }

        $subjects->update($existing['subject_id'], [
            'subject_name'  => $title,
            'units'         => $units,
            'contact_hours' => $duration,
            'is_laboratory' => $isLab ? 1 : 0,
        ]);

        if ($plotted > 0 && $title !== $existing['subject_name']) {
            (new PlottedScheduleModel())
                ->where('subject_code', $existing['subject_code'])
                ->set('subject_title', $title)
                ->update();
        }

        return $this->response->setJSON(['ok' => true]);
    }

    public function toggleStatus($id)
    {
        $userId     = (int) auth()->id();
        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds($userId);

        $subjects = new SubjectModel();
        $existing = $subjects->find((int) $id);

        if ($existing === null || ! in_array((int) $existing['sub_department_id'], $subDeptIds, true)) {
            return $this->fail('Subject not found, or not owned by your department.');
        }

        if ($existing['is_active']) {
            $plotted = (new PlottedScheduleModel())->where('subject_code', $existing['subject_code'])->countAllResults();

            if ($plotted > 0) {
                return $this->fail("{$existing['subject_code']} is plotted in {$plotted} " . ($plotted === 1 ? 'class' : 'classes') . ' — remove those from the schedules first.');
            }
        }

        $subjects->update($existing['subject_id'], ['is_active' => $existing['is_active'] ? 0 : 1]);

        return $this->response->setJSON(['ok' => true]);
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

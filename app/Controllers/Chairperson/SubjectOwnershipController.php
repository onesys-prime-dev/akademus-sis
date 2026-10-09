<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Libraries\AcademicHourResolver;
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
 *
 * A subject with both a lecture AND a laboratory component is represented
 * as two ordinary, single-type subject rows (one is_laboratory = 0, one
 * is_laboratory = 1), linked only by subjects.pair_subject_id. Every other
 * consumer of subjects (plotting, ScheduleSuggester, faculty load) keeps
 * treating subject_code as a 1:1 key to one is_lab/duration/weekly_hours —
 * exactly as it does today — because each row really is single-type. The
 * pairing only matters here, to present/edit the two rows as one subject.
 */
class SubjectOwnershipController extends BaseController
{
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

        $resolver = new AcademicHourResolver();

        return view('chairperson/subject_ownership', [
            'pageTitle'   => 'Subject Ownership',
            'user'        => [
                'name' => auth()->user()->username ?? 'Chairperson',
                'role' => (count($deptNames) === 1 ? reset($deptNames) . ' - ' : '') . 'Chairperson',
            ],
            'deptNames'      => $deptNames,
            'hasAnyDept'     => $subDeptIds !== [],
            'subjects'       => $subjects,
            'academicRates'  => ['lecture' => $resolver->lectureHoursPerUnit(), 'lab' => $resolver->labHoursPerUnit()],
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

        $components = $this->readComponents($payload);

        if ($components === null) {
            return $this->fail('Select at least one class type (Lecture and/or Laboratory).');
        }

        if ($error = $this->componentError($components)) {
            return $this->fail($error);
        }

        $subjects = new SubjectModel();
        $existing = $subjects->where('subject_code', $code)->first();

        if ($existing !== null && $existing['is_active']) {
            return $this->fail("Subject {$code} already exists in the catalog, owned by another department — it can be reused from the Curriculum Builder instead of being re-created here.");
        }

        $db = db_connect();
        $db->transException(true)->transStart();

        try {
            $lecId = null;
            $labId = null;

            if ($components['lecture'] !== null) {
                $lecId = $this->upsertRow($subjects, $code, $subDeptId, $title, false, $components['lecture']);
            }

            if ($components['lab'] !== null) {
                $labCode = $components['lecture'] !== null ? $this->deriveComponentCode($code, '-LAB') : $code;

                if ($components['lecture'] !== null) {
                    $labExisting = $subjects->where('subject_code', $labCode)->first();

                    if ($labExisting !== null && $labExisting['is_active']) {
                        throw new \RuntimeException("Could not auto-create the laboratory section as {$labCode} — that code is already used by another active subject. Rename or deactivate it first.");
                    }
                }

                $labId = $this->upsertRow($subjects, $labCode, $subDeptId, $title, true, $components['lab']);
            }

            if ($lecId !== null && $labId !== null) {
                $subjects->update($lecId, ['pair_subject_id' => $labId]);
                $subjects->update($labId, ['pair_subject_id' => $lecId]);
            }

            $db->transComplete();
        } catch (\RuntimeException $e) {
            $db->transRollback();

            return $this->fail($e->getMessage());
        }

        $rows = array_map(fn ($rowId) => $this->toCatalogRow($subjects->find($rowId)), array_filter([$lecId, $labId]));

        return $this->response->setJSON(['ok' => true, 'rows' => array_values($rows)]);
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

        $components = $this->readComponents($payload);

        if ($components === null) {
            return $this->fail('Select at least one class type (Lecture and/or Laboratory).');
        }

        if ($error = $this->componentError($components)) {
            return $this->fail($error);
        }

        $partner = $existing['pair_subject_id'] ? $subjects->find((int) $existing['pair_subject_id']) : null;
        $lecRow  = ((int) $existing['is_laboratory'] === 0) ? $existing : $partner;
        $labRow  = ((int) $existing['is_laboratory'] === 1) ? $existing : $partner;

        $plottedCountFor = static fn (string $code) => (new PlottedScheduleModel())->where('subject_code', $code)->countAllResults();

        $db = db_connect();
        $db->transException(true)->transStart();

        try {
            $finalLecId = $this->applyComponentEdit($subjects, $lecRow, $components['lecture'], false, $title, $plottedCountFor, $existing, '-LEC');
            $finalLabId = $this->applyComponentEdit($subjects, $labRow, $components['lab'], true, $title, $plottedCountFor, $existing, '-LAB');

            if ($finalLecId !== null && $finalLabId !== null) {
                $subjects->update($finalLecId, ['pair_subject_id' => $finalLabId]);
                $subjects->update($finalLabId, ['pair_subject_id' => $finalLecId]);
            } else {
                // One side is gone (removed or never existed) — the survivor, if any, is unpaired.
                if ($finalLecId !== null) {
                    $subjects->update($finalLecId, ['pair_subject_id' => null]);
                }
                if ($finalLabId !== null) {
                    $subjects->update($finalLabId, ['pair_subject_id' => null]);
                }
            }

            $db->transComplete();
        } catch (\RuntimeException $e) {
            $db->transRollback();

            return $this->fail($e->getMessage());
        }

        // Report every row that existed before or exists after the edit (including a
        // just-deactivated one, so the client can sync it out of the catalog display).
        $touchedIds = array_unique(array_filter([
            $lecRow ? $lecRow['subject_id'] : null, $labRow ? $labRow['subject_id'] : null, $finalLecId, $finalLabId,
        ]));
        $rows = array_map(fn ($rowId) => $this->toCatalogRow($subjects->find($rowId)), $touchedIds);

        return $this->response->setJSON(['ok' => true, 'rows' => array_values($rows)]);
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

        $partner = $existing['pair_subject_id'] ? $subjects->find((int) $existing['pair_subject_id']) : null;
        $rows    = array_filter([$existing, $partner]);
        $turningOn = ! $existing['is_active'];

        if (! $turningOn) {
            foreach ($rows as $row) {
                $plotted = (new PlottedScheduleModel())->where('subject_code', $row['subject_code'])->countAllResults();

                if ($plotted > 0) {
                    return $this->fail("{$row['subject_code']} is plotted in {$plotted} " . ($plotted === 1 ? 'class' : 'classes') . ' — remove those from the schedules first.');
                }
            }
        }

        foreach ($rows as $row) {
            $subjects->update($row['subject_id'], ['is_active' => $turningOn ? 1 : 0]);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    private function toCatalogRow(array $row): array
    {
        return [
            'id'         => (int) $row['subject_id'],
            'code'       => $row['subject_code'],
            'title'      => $row['subject_name'],
            'units'      => (float) $row['units'],
            'duration'   => (float) $row['contact_hours'],
            'is_lab'     => (bool) $row['is_laboratory'],
            'active'     => (bool) $row['is_active'],
            'department' => (int) $row['sub_department_id'],
            'pairId'     => $row['pair_subject_id'] !== null ? (int) $row['pair_subject_id'] : null,
        ];
    }

    /**
     * Reads {has_lecture, lecture_units, has_lab, lab_units} into
     * ['lecture' => float|null, 'lab' => float|null], null meaning "not
     * requested this type". Returns null overall if neither type is set.
     */
    private function readComponents(array $payload): ?array
    {
        $hasLecture = ! empty($payload['has_lecture']);
        $hasLab     = ! empty($payload['has_lab']);

        if (! $hasLecture && ! $hasLab) {
            return null;
        }

        return [
            'lecture' => $hasLecture ? (float) ($payload['lecture_units'] ?? 0) : null,
            'lab'     => $hasLab ? (float) ($payload['lab_units'] ?? 0) : null,
        ];
    }

    private function componentError(array $components): ?string
    {
        foreach (['lecture' => 'Lecture', 'lab' => 'Laboratory'] as $key => $label) {
            $units = $components[$key];

            if ($units !== null && ($units < 0.5 || $units > 6)) {
                return "{$label} units must be between 0.5 and 6.";
            }
        }

        return null;
    }

    /** units x hours-per-unit, rounded to the nearest 0.5h so it fits the plotting grid's 30-minute slots. */
    private function computeDuration(float $units, bool $isLab): float
    {
        $resolver = new AcademicHourResolver();
        $rate     = $isLab ? $resolver->labHoursPerUnit() : $resolver->lectureHoursPerUnit();

        return round(($units * $rate) * 2) / 2;
    }

    private function deriveComponentCode(string $anchorCode, string $suffix): string
    {
        $base    = preg_replace('/-(LAB|LEC)$/', '', $anchorCode);
        $maxBase = 20 - strlen($suffix);

        return substr($base, 0, $maxBase) . $suffix;
    }

    /** Inserts a new row, or reactivates/overwrites a matching inactive one. Returns the subject_id. */
    private function upsertRow(SubjectModel $subjects, string $code, int $subDeptId, string $title, bool $isLab, float $units): int
    {
        $existing = $subjects->where('subject_code', $code)->first();

        $row = [
            'sub_department_id' => $subDeptId,
            'program_id'        => null,
            'subject_code'      => $code,
            'subject_name'      => $title,
            'units'             => $units,
            'contact_hours'     => $this->computeDuration($units, $isLab),
            'is_laboratory'     => $isLab ? 1 : 0,
            'is_active'         => 1,
        ];

        if ($existing === null) {
            $subjects->insert($row);

            return (int) $subjects->getInsertID();
        }

        // Reactivating a previously-deactivated code — safe to reassign ownership here since nothing references it while inactive.
        $subjects->update($existing['subject_id'], $row);

        return (int) $existing['subject_id'];
    }

    /**
     * Applies the edit for one component (lecture or lab) of a subject being updated.
     * Returns the resulting row's subject_id, or null if that component doesn't exist afterward.
     *
     * @throws \RuntimeException on a plotted-lock violation or an auto-generated code collision.
     */
    private function applyComponentEdit(SubjectModel $subjects, ?array $currentRow, ?float $units, bool $isLab, string $title, callable $plottedCountFor, array $editedSubject, string $suffix): ?int
    {
        if ($units === null) {
            // This type isn't checked. If it previously existed, retire it.
            if ($currentRow === null) {
                return null;
            }

            $plotted = $plottedCountFor($currentRow['subject_code']);

            if ($plotted > 0) {
                $classes = $plotted . ' ' . ($plotted === 1 ? 'class' : 'classes');

                throw new \RuntimeException("{$currentRow['subject_code']} is plotted in {$classes} — remove those from the schedules first.");
            }

            $subjects->update($currentRow['subject_id'], ['is_active' => 0, 'pair_subject_id' => null]);

            return null;
        }

        $duration = $this->computeDuration($units, $isLab);

        if ($currentRow !== null) {
            $plotted = $plottedCountFor($currentRow['subject_code']);

            if ($plotted > 0 && abs($duration - (float) $currentRow['contact_hours']) > 0.01) {
                $classes = $plotted . ' ' . ($plotted === 1 ? 'class' : 'classes');

                throw new \RuntimeException("{$currentRow['subject_code']} is plotted in {$classes} — its meeting length cannot change until those are removed.");
            }

            $subjects->update($currentRow['subject_id'], [
                'subject_name'  => $title,
                'units'         => $units,
                'contact_hours' => $duration,
            ]);

            if ($plotted > 0 && $title !== $currentRow['subject_name']) {
                (new PlottedScheduleModel())->where('subject_code', $currentRow['subject_code'])->set('subject_title', $title)->update();
            }

            return (int) $currentRow['subject_id'];
        }

        // Newly checked — create it, anchored off whichever row already exists on this subject.
        $anchorCode = $editedSubject['subject_code'];
        $code       = $this->deriveComponentCode($anchorCode, $suffix);
        $collision  = $subjects->where('subject_code', $code)->first();

        if ($collision !== null && $collision['is_active']) {
            $typeLabel = $isLab ? 'laboratory' : 'lecture';

            throw new \RuntimeException("Could not auto-create the {$typeLabel} section as {$code} — that code is already used by another active subject. Rename or deactivate it first.");
        }

        return $this->upsertRow($subjects, $code, (int) $editedSubject['sub_department_id'], $title, $isLab, $units);
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

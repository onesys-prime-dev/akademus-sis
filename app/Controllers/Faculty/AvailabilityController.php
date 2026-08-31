<?php

namespace App\Controllers\Faculty;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;
use App\Models\DepartmentModel;
use App\Models\FacultyAvailabilityModel;
use App\Models\FacultySubjectPreferenceModel;
use App\Models\SubjectModel;

/**
 * Availability + subject preference submission for faculty. A faculty
 * member can submit separately for ANY academic year/semester on record
 * (1st, 2nd, Summer, ...) — not just a single fixed "upcoming" term —
 * since a chairperson may be plotting any of those terms ahead of time and
 * needs to see the right term's availability, not whichever one happens to
 * be "next".
 */
class AvailabilityController extends BaseController
{
    /** 0 = Monday .. 5 = Saturday, matching the plotting grid's days. */
    private const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    private const PREFERENCE_LEVELS = ['preferred', 'willing', 'qualified'];

    /** The term to work with: the requested ay_id if it's real, else the "upcoming" one as a sensible default. */
    private function resolveTerm(?string $requestedAyId): ?array
    {
        $ayModel = new AcademicYearModel();

        if ($requestedAyId !== null && ctype_digit($requestedAyId)) {
            $row = $ayModel->find((int) $requestedAyId);
            if ($row !== null) {
                return $row;
            }
        }

        return $ayModel->upcomingTerm();
    }

    public function edit()
    {
        $userId = (int) auth()->id();
        $allTerms = (new AcademicYearModel())->orderBy('label', 'desc')->orderBy('semester', 'asc')->findAll();
        $term   = $this->resolveTerm($this->request->getGet('ay_id'));

        $availabilityByDay = [];
        $subjects          = [];
        $preferencesById   = [];

        if ($term !== null) {
            foreach ((new FacultyAvailabilityModel())->forTerm($userId, (int) $term['ay_id']) as $row) {
                $availabilityByDay[(int) $row['day_of_week']] = $row;
            }

            $subDept   = (new DepartmentModel())->subDeptOfUser($userId);
            $subDeptId = $subDept['sub_dept_id'] ?? null;

            if ($subDeptId !== null) {
                $subjects = (new SubjectModel())
                    ->where('sub_department_id', $subDeptId)
                    ->where('is_active', 1)
                    ->orderBy('subject_code')
                    ->findAll();
            }

            foreach ((new FacultySubjectPreferenceModel())->forTerm($userId, (int) $term['ay_id']) as $row) {
                $preferencesById[(int) $row['subject_id']] = $row['preference_level'];
            }
        }

        $dept = (new DepartmentModel())->codeOfUser($userId);

        return view('faculty/availability/edit', [
            'user' => [
                'name' => auth()->user()->username ?? 'Faculty',
                'role' => ($dept !== null ? $dept . ' Department · ' : '') . 'Faculty',
            ],
            'term'              => $term,
            'termLabel'         => $term ? AcademicYearModel::format($term['label'], $term['semester']) : null,
            'allTerms'          => $allTerms,
            'dayLabels'         => self::DAY_LABELS,
            'availabilityByDay' => $availabilityByDay,
            'subjects'          => $subjects,
            'preferencesById'   => $preferencesById,
            'preferenceLevels'  => self::PREFERENCE_LEVELS,
        ]);
    }

    public function update()
    {
        $userId = (int) auth()->id();
        $term   = $this->resolveTerm($this->request->getPost('ay_id'));

        if ($term === null) {
            return redirect()->to('/faculty/availability')->with('errors', ['No academic term is configured yet — ask an admin to add one.']);
        }

        $backUrl = '/faculty/availability?ay_id=' . (int) $term['ay_id'];

        $rows = [];

        foreach (self::DAY_LABELS as $day => $label) {
            // '' = no opinion submitted for this day (skip entirely),
            // 'available' = available for the given time range,
            // 'unavailable' = explicitly not available all day.
            $status = $this->request->getPost("day_{$day}_status");

            if ($status === '' || $status === null) {
                continue;
            }

            $isAvailable = $status === 'available';
            $start       = $this->request->getPost("day_{$day}_start");
            $end         = $this->request->getPost("day_{$day}_end");

            if ($isAvailable && (! $start || ! $end || $start >= $end)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('errors', ["{$label}: start time must be before end time."]);
            }

            $rows[] = [
                'user_id'      => $userId,
                'ay_id'        => (int) $term['ay_id'],
                'day_of_week'  => $day,
                'start_time'   => $isAvailable ? $start : null,
                'end_time'     => $isAvailable ? $end : null,
                'is_available' => $isAvailable ? 1 : 0,
                'notes'        => $this->request->getPost("day_{$day}_notes") ?: null,
            ];
        }

        (new FacultyAvailabilityModel())->replaceForTerm($userId, (int) $term['ay_id'], $rows);

        return redirect()
            ->to($backUrl)
            ->with('message', 'Your availability has been saved.');
    }

    public function updatePreferences()
    {
        $userId = (int) auth()->id();
        $term   = $this->resolveTerm($this->request->getPost('ay_id'));

        if ($term === null) {
            return redirect()->to('/faculty/availability')->with('errors', ['No academic term is configured yet — ask an admin to add one.']);
        }

        $backUrl = '/faculty/availability?ay_id=' . (int) $term['ay_id'];

        $subDept   = (new DepartmentModel())->subDeptOfUser($userId);
        $subDeptId = $subDept['sub_dept_id'] ?? null;
        $validSubjectIds = $subDeptId === null ? [] : array_column(
            (new SubjectModel())->where('sub_department_id', $subDeptId)->where('is_active', 1)->findAll(),
            'subject_id'
        );

        $rows = [];

        foreach ($this->request->getPost('preference') ?? [] as $subjectId => $level) {
            $subjectId = (int) $subjectId;

            if (! in_array($subjectId, $validSubjectIds, true) || ! in_array($level, self::PREFERENCE_LEVELS, true)) {
                continue;
            }

            $rows[] = [
                'user_id'          => $userId,
                'ay_id'            => (int) $term['ay_id'],
                'subject_id'       => $subjectId,
                'preference_level' => $level,
            ];
        }

        (new FacultySubjectPreferenceModel())->replaceForTerm($userId, (int) $term['ay_id'], $rows);

        return redirect()
            ->to($backUrl)
            ->with('message', 'Your subject preferences have been saved.');
    }
}

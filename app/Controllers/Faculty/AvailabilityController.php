<?php

namespace App\Controllers\Faculty;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;
use App\Models\DepartmentModel;
use App\Models\FacultyAvailabilityModel;
use App\Models\FacultySubjectPreferenceModel;
use App\Models\SubjectModel;

/**
 * Pre-semester availability + subject preference submission for faculty.
 * Scoped to the "upcoming" term (AcademicYearModel::upcomingTerm()), not the
 * currently active/being-taught term — this is what a faculty member fills
 * in BEFORE the chairperson starts plotting the next semester.
 */
class AvailabilityController extends BaseController
{
    /** 0 = Monday .. 5 = Saturday, matching the plotting grid's days. */
    private const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    private const PREFERENCE_LEVELS = ['preferred', 'willing', 'qualified'];

    public function edit()
    {
        $userId = (int) auth()->id();
        $term   = (new AcademicYearModel())->upcomingTerm();

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

        return view('faculty/availability/edit', [
            'term'              => $term,
            'termLabel'         => $term ? AcademicYearModel::format($term['label'], $term['semester']) : null,
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
        $term   = (new AcademicYearModel())->upcomingTerm();

        if ($term === null) {
            return redirect()->to('/faculty/availability')->with('errors', ['No upcoming term is configured yet — ask an admin to add one.']);
        }

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
            ->to('/faculty/availability')
            ->with('message', 'Your availability has been saved.');
    }

    public function updatePreferences()
    {
        $userId = (int) auth()->id();
        $term   = (new AcademicYearModel())->upcomingTerm();

        if ($term === null) {
            return redirect()->to('/faculty/availability')->with('errors', ['No upcoming term is configured yet — ask an admin to add one.']);
        }

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
            ->to('/faculty/availability')
            ->with('message', 'Your subject preferences have been saved.');
    }
}

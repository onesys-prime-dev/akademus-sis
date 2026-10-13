<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\AcademicHourResolver;

class AcademicSettingsController extends BaseController
{
    public function index()
    {
        return view('admin/academic_settings/index', [
            'lectureHoursPerUnit' => setting('Academic.lectureHoursPerUnit') ?? AcademicHourResolver::DEFAULT_LECTURE_HOURS_PER_UNIT,
            'labHoursPerUnit'     => setting('Academic.labHoursPerUnit') ?? AcademicHourResolver::DEFAULT_LAB_HOURS_PER_UNIT,
            'maxFacultyUnits'     => setting('Academic.maxFacultyUnits') ?? 24,
            'maxMeetingsPerWeek'  => setting('Academic.maxMeetingsPerWeek') ?? 3,
            'partTimeMaxUnits'    => setting('Academic.partTimeMaxUnits'),
            'maxStudentUnits'     => setting('Academic.maxStudentUnits') ?? 24,
            'activeAdmin'         => 'academic-settings',
        ]);
    }

    public function update()
    {
        $rules = [
            'lecture_hours_per_unit' => 'required|decimal',
            'lab_hours_per_unit'     => 'required|decimal',
            'max_faculty_units'      => 'required|integer',
            'max_meetings_per_week'  => 'required|integer',
            'part_time_max_units'    => 'permit_empty|integer',
            'max_student_units'      => 'required|integer',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        setting('Academic.lectureHoursPerUnit', (float) $this->request->getPost('lecture_hours_per_unit'));
        setting('Academic.labHoursPerUnit', (float) $this->request->getPost('lab_hours_per_unit'));
        setting('Academic.maxFacultyUnits', (int) $this->request->getPost('max_faculty_units'));
        setting('Academic.maxMeetingsPerWeek', (int) $this->request->getPost('max_meetings_per_week'));

        $partTimeMaxUnits = $this->request->getPost('part_time_max_units');
        setting('Academic.partTimeMaxUnits', $partTimeMaxUnits !== '' ? (int) $partTimeMaxUnits : null);
        setting('Academic.maxStudentUnits', (int) $this->request->getPost('max_student_units'));

        return redirect()
            ->to('/admin/academic-settings')
            ->with('message', 'Academic scheduling rules updated.');
    }
}

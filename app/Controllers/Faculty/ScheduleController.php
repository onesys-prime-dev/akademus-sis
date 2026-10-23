<?php

namespace App\Controllers\Faculty;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;
use App\Models\DepartmentModel;
use App\Models\PlottedScheduleModel;

/**
 * Read-only weekly view of this faculty member's own plotted classes —
 * the same grid the chairperson plots on, but scoped to just this person's
 * assignments for the active term and with no editing controls.
 */
class ScheduleController extends BaseController
{
    private const DAY_LABELS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function index()
    {
        $account    = auth()->user();
        $username   = $account->username ?? '';
        $dept       = (new DepartmentModel())->codeOfUser((int) auth()->id());
        $activeYear = (new AcademicYearModel())->activeFormatted();

        $meetings = $activeYear === null || $username === ''
            ? []
            : (new PlottedScheduleModel())
                ->where('prof', $username)
                ->where('academic_year', $activeYear)
                ->orderBy('day')
                ->orderBy('start_time')
                ->findAll();

        return view('faculty/schedule/index', [
            'user' => [
                'name' => $username ?: 'Faculty',
                'role' => ($dept !== null ? $dept . ' Department · ' : '') . 'Faculty',
            ],
            'activeYear' => $activeYear,
            'meetings'   => $meetings,
            'dayLabels'  => self::DAY_LABELS,
        ]);
    }
}

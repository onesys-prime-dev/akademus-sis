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

    /** Mirrors ChairpersonController::COLORS so blocks use the same accent per subject. */
    private const COLORS = ['blue', 'purple', 'cyan', 'red', 'green', 'yellow'];

    public function index()
    {
        $account   = auth()->user();
        $username  = $account->username ?? '';
        $dept      = (new DepartmentModel())->codeOfUser((int) auth()->id());
        $activeYear = (new AcademicYearModel())->activeFormatted();

        $rows = $activeYear === null || $username === ''
            ? []
            : (new PlottedScheduleModel())
                ->where('prof', $username)
                ->where('academic_year', $activeYear)
                ->orderBy('day')
                ->orderBy('start_time')
                ->findAll();

        $blocks = array_map(static function (array $row): array {
            return [
                'day'        => (int) $row['day'],
                'code'       => $row['subject_code'],
                'title'      => $row['subject_title'],
                'room'       => $row['room'],
                'section'    => $row['section'],
                'year_level' => $row['year_level'],
                'time_label' => $row['time_label'],
                'start'      => substr($row['start_time'], 0, 5),
                'end'        => substr($row['end_time'], 0, 5),
                'color'      => self::COLORS[array_sum(array_map('ord', str_split($row['subject_code']))) % count(self::COLORS)],
            ];
        }, $rows);

        return view('faculty/schedule/index', [
            'user' => [
                'name' => $username ?: 'Faculty',
                'role' => ($dept !== null ? $dept . ' Department · ' : '') . 'Faculty',
            ],
            'activeYear'  => $activeYear,
            'dayLabels'   => self::DAY_LABELS,
            'blocks'      => $blocks,
            'unitsTaught' => count(array_unique(array_column($blocks, 'code'))),
        ]);
    }
}

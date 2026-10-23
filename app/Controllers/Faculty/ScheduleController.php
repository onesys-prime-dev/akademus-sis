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

        // program_code (e.g. "BSIT") is joined in via sections/programs so a
        // merged class — the same room/time slot shared by two sections —
        // can show a short "BSCS, BSIT" label instead of two separate rows;
        // plotted_schedules itself only stores the spelled-out program name.
        $meetings = $activeYear === null || $username === ''
            ? []
            : (new PlottedScheduleModel())
                ->select('plotted_schedules.*, programs.program_code')
                // sections.academic_year spans the whole school year (no
                // semester suffix) while plotted_schedules.academic_year is
                // term-scoped ("... · 1st Semester"), so it's matched with a
                // prefix LIKE rather than "=" — dropping it entirely would
                // risk joining a same-named section from a different year.
                ->join('sections', "sections.program = plotted_schedules.program
                    AND sections.year_level = plotted_schedules.year_level
                    AND sections.section = plotted_schedules.section
                    AND plotted_schedules.academic_year LIKE CONCAT(sections.academic_year, '%')", 'left')
                ->join('programs', 'programs.program_id = sections.program_id', 'left')
                ->where('plotted_schedules.prof', $username)
                ->where('plotted_schedules.academic_year', $activeYear)
                ->orderBy('plotted_schedules.day')
                ->orderBy('plotted_schedules.start_time')
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

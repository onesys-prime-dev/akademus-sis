<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Libraries\SharedSubjectDashboardService;
use App\Models\AcademicYearModel;

/**
 * The owning-scheduler operational overview for shared subjects (Step 6):
 * demand, placement requests, and offerings/schedules in one read-only
 * screen. Purely a thin wrapper — SharedSubjectDashboardService does the
 * actual aggregation; every linked action still goes through its own
 * existing endpoint/authorization (offerings, placement-requests,
 * offering schedule sync).
 */
class SharedSubjectDashboardController extends BaseController
{
    public function index()
    {
        $requested = $this->request->getGet('academic_year');
        $academicYear = is_string($requested) && $requested !== '' ? $requested : (new AcademicYearModel())->activeFormatted();

        $terms = array_values(array_unique(array_map(
            static fn (array $row) => AcademicYearModel::format($row['label'], $row['semester']),
            (new AcademicYearModel())->orderBy('is_active', 'desc')->orderBy('label', 'desc')->orderBy('semester', 'asc')->findAll()
        )));

        $includeInactive = $this->request->getGet('show_inactive') === '1';

        $dashboard = $academicYear === null
            ? ['term' => null, 'subjects' => [], 'inactive_count' => 0]
            : (new SharedSubjectDashboardService())->build((int) auth()->id(), $academicYear, $includeInactive);

        return view('chairperson/shared_subjects', [
            'pageTitle'       => 'Shared Subject Dashboard',
            'user'            => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
            'terms'           => $terms,
            'selectedTerm'    => $academicYear,
            'dashboard'       => $dashboard,
            'includeInactive' => $includeInactive,
        ]);
    }
}

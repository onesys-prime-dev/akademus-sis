<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Libraries\SharedSubjectCoverageService;
use App\Models\AcademicYearModel;

/**
 * "What do MY students need that someone else owns, and are they covered?"
 * (Step 7) — the counterpart to Step 6's owning-scheduler dashboard. Purely
 * read-only: every action link on this page posts to the existing Step 3
 * placement-request endpoint, unchanged.
 */
class SharedSubjectCoverageController extends BaseController
{
    public function index()
    {
        $requested = $this->request->getGet('academic_year');
        $academicYear = is_string($requested) && $requested !== '' ? $requested : (new AcademicYearModel())->activeFormatted();

        $terms = array_values(array_unique(array_map(
            static fn (array $row) => AcademicYearModel::format($row['label'], $row['semester']),
            (new AcademicYearModel())->orderBy('is_active', 'desc')->orderBy('label', 'desc')->orderBy('semester', 'asc')->findAll()
        )));

        $coverage = $academicYear === null
            ? ['term' => null, 'subjects' => [], 'my_section_count' => 0]
            : (new SharedSubjectCoverageService())->build((int) auth()->id(), $academicYear);

        return view('chairperson/shared_subject_coverage', [
            'pageTitle'    => 'Shared Subject Coverage',
            'user'         => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
            'terms'        => $terms,
            'selectedTerm' => $academicYear,
            'coverage'     => $coverage,
        ]);
    }
}

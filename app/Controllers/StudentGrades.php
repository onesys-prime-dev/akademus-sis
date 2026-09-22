<?php

namespace App\Controllers;

use App\Models\GradeModel;
use App\Models\GradingScaleModel;

/**
 * The student's own read-only grade view — split from StudentEnrollment
 * since grading is its own concern, not an enrollment action.
 */
class StudentGrades extends BaseController
{
    public function index()
    {
        $userId = (int) auth()->id();
        $grades = (new GradeModel())->forStudent($userId);

        $scaleIds = array_unique(array_column($grades, 'grading_scale_id'));
        $scales   = [];

        foreach ($scaleIds as $scaleId) {
            $scale = (new GradingScaleModel())->find((int) $scaleId);
            if ($scale !== null) {
                $scales[(int) $scaleId] = $scale;
            }
        }

        $byYear = [];
        foreach ($grades as $g) {
            $byYear[$g['academic_year']][] = $g;
        }

        return view('student/grades', [
            'pageTitle' => 'My Grades',
            'byYear'    => $byYear,
            'scales'    => $scales,
        ]);
    }
}

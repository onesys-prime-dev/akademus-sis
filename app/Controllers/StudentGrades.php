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
    private const DAY_LABELS = ['M', 'T', 'W', 'TH', 'F', 'S', 'SU'];

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
            $g['schedule_label'] = $this->scheduleLabel($g['meetings']);
            $byYear[$g['academic_year']][] = $g;
        }

        $terms = [];
        foreach (array_keys($byYear) as $term) {
            $terms[$term] = $this->splitTerm($term);
        }

        $gwaByTerm = [];
        foreach ($byYear as $term => $rows) {
            $gwaByTerm[$term] = $this->computeGwa($rows, $scales);
        }

        return view('student/grades', [
            'pageTitle' => 'My Grades',
            'byYear'    => $byYear,
            'scales'    => $scales,
            'terms'     => $terms,
            'gwaByTerm' => $gwaByTerm,
        ]);
    }

    /**
     * "W (2:30 PM - 5:30 PM); S (9:00 AM - 12:00 PM)" from plotted_schedules
     * meeting rows — same day-index convention as StudentEnrollment::schedule
     * (0 = Monday .. 6 = Sunday).
     */
    private function scheduleLabel(array $meetings): string
    {
        if ($meetings === []) {
            return 'No schedule posted';
        }

        $parts = array_map(function (array $m): string {
            $day = self::DAY_LABELS[(int) $m['day']] ?? '?';

            return "{$day} ({$m['time_label']})";
        }, $meetings);

        return implode('; ', $parts);
    }

    /**
     * "AY 2025 - 2026 · 1st Semester" -> ['year' => '2025 - 2026', 'semester'
     * => '1st Semester'], matching AcademicYearModel::format()'s output so
     * the view can offer separate school-year/semester filters over one
     * free-text column.
     */
    private function splitTerm(string $term): array
    {
        if (preg_match('/^AY (.+?)(?: · (.+))?$/', $term, $m) === 1) {
            return ['year' => $m[1], 'semester' => $m[2] ?? ''];
        }

        return ['year' => $term, 'semester' => ''];
    }

    /**
     * Unit-weighted average of a term's numeric grades. Only numeric_gwa/
     * percentage scales contribute (a letter scale has no number to weight),
     * and a grade value that isn't numeric for its scale is skipped rather
     * than crashing the average — mirrors GradeScaleEvaluator's "unknown,
     * not failing" stance on anything it can't evaluate.
     */
    private function computeGwa(array $rows, array $scales): ?float
    {
        $weightedSum = 0.0;
        $totalUnits  = 0.0;

        foreach ($rows as $row) {
            $scale = $scales[(int) $row['grading_scale_id']] ?? null;

            if ($scale === null || $scale['type'] === 'letter' || ! is_numeric($row['grade_value'])) {
                continue;
            }

            $units = (float) ($row['units'] ?? 0);
            $weightedSum += (float) $row['grade_value'] * $units;
            $totalUnits  += $units;
        }

        return $totalUnits > 0 ? round($weightedSum / $totalUnits, 2) : null;
    }
}

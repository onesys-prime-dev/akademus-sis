<?php

namespace App\Libraries;

use App\Models\AcademicYearModel;
use App\Models\EnrollmentModel;
use App\Models\StudentModel;
use App\Models\SubjectModel;

/**
 * The one place that answers "how far along is this student in their
 * curriculum?" — previously nowhere in the codebase (confirmed by audit: no
 * completion calculator existed anywhere; app/Views/components/profile/
 * academic-history.php is a static, unwired mockup). Built entirely on
 * existing primitives rather than inventing a second rule set:
 *
 *  - A curriculum's required subjects: curriculum_subjects (Step 6/7).
 *  - Whether a required subject is satisfied: SubjectRequirementService
 *    (Step 8 — exact match or an active substitution), the same service
 *    SubjectDemandService and EnrollmentModel's prerequisite gate use.
 *  - Missing prerequisites: EnrollmentModel::unmetPrerequisites(), the same
 *    check that already gates new enrollment — not re-derived here.
 *
 * "Passed"/"failed" is only ever as fine-grained as the data actually is:
 * a locked grade's is_passing is a nullable boolean (see
 * GradeScaleEvaluator::isPassing — null only when a scale has no passing
 * rule configured for its type). There is no system-level "INC" concept
 * anywhere in the schema or grading-scale logic; a scale's own letter/value
 * vocabulary is whatever the admin configured, so an ambiguous locked grade
 * (is_passing === null) is reported as "unresolved" rather than invented as
 * "INC".
 */
class StudentProgressService
{
    /**
     * Lightweight summary rows for a whole student roster — safe for a list
     * page covering a program's full population. Each student costs exactly
     * zero extra queries beyond the batched ones this method itself issues;
     * no per-student prerequisite lookups happen here (those are reserved
     * for forStudent(), which is called one student at a time).
     *
     * @param list<array> $students Raw `students` rows (must include
     *        student_id, user_id, curriculum_id).
     *
     * @return array<int, array> keyed by student_id
     */
    public function summarizeStudents(array $students): array
    {
        if ($students === []) {
            return [];
        }

        $curriculumIds = array_values(array_unique(array_filter(array_column($students, 'curriculum_id'))));
        $requiredByCurriculum = $this->requiredSubjectsByCurriculum($curriculumIds);

        $userIds = array_map('intval', array_column($students, 'user_id'));
        $gradesByUser = $this->lockedGradesByUser($userIds);
        $enrolledByUser = $this->currentEnrollmentsByUser($userIds);

        $allSubjectIds = [];
        foreach ($requiredByCurriculum as $rows) {
            foreach ($rows as $row) {
                $allSubjectIds[] = (int) $row['subject_id'];
            }
        }

        $acceptableIdsMap = (new SubjectRequirementService())->acceptableSubjectIdsFor($allSubjectIds);
        $codeById = $this->codeById($acceptableIdsMap);

        $summaries = [];

        foreach ($students as $student) {
            $curriculumId = $student['curriculum_id'] !== null ? (int) $student['curriculum_id'] : null;
            $required = $curriculumId !== null ? ($requiredByCurriculum[$curriculumId] ?? []) : [];
            $userId = (int) $student['user_id'];

            $summaries[(int) $student['student_id']] = $this->summarize(
                $required,
                $gradesByUser[$userId] ?? [],
                $enrolledByUser[$userId] ?? [],
                $acceptableIdsMap,
                $codeById
            );
        }

        return $summaries;
    }

    /**
     * Full breakdown for one student — passed/failed/unresolved/in-progress/
     * remaining subjects, plus missing prerequisites for each remaining
     * subject the student hasn't attempted or started yet. Costs a handful
     * of extra queries (one per not-yet-attempted remaining subject, via
     * EnrollmentModel::unmetPrerequisites()) — acceptable for an on-demand
     * single-student detail view, not for a roster-wide scan.
     */
    public function forStudent(int $studentId): ?array
    {
        $student = (new StudentModel())->find($studentId);

        if ($student === null) {
            return null;
        }

        $curriculumId = $student['curriculum_id'] !== null ? (int) $student['curriculum_id'] : null;
        $required = $curriculumId !== null ? ($this->requiredSubjectsByCurriculum([$curriculumId])[$curriculumId] ?? []) : [];

        $userId = (int) $student['user_id'];
        $grades = $this->lockedGradesByUser([$userId])[$userId] ?? [];
        $enrolled = $this->currentEnrollmentsByUser([$userId])[$userId] ?? [];

        $subjectIds = array_column($required, 'subject_id');
        $acceptableIdsMap = (new SubjectRequirementService())->acceptableSubjectIdsFor($subjectIds);
        $codeById = $this->codeById($acceptableIdsMap);

        $detail = $this->classify($required, $grades, $enrolled, $acceptableIdsMap, $codeById);

        $enrollments = new EnrollmentModel();

        foreach ($detail['remaining'] as &$item) {
            if ($item['status'] !== 'not_started') {
                continue;
            }

            $missing = $enrollments->unmetPrerequisites($userId, $item['code']);

            if ($missing !== []) {
                $item['missing_prerequisites'] = $missing;
            }
        }
        unset($item);

        $totalUnits = $detail['total_units'];
        $completedUnits = $detail['completed_units'];

        return [
            'student_id'       => (int) $student['student_id'],
            'completion_pct'   => $totalUnits > 0 ? round($completedUnits / $totalUnits * 100, 1) : null,
            'completed_units'  => $completedUnits,
            'total_units'      => $totalUnits,
            'passed'           => $detail['passed'],
            'failed'           => $detail['failed'],
            'unresolved'       => $detail['unresolved'],
            'in_progress'      => $detail['in_progress'],
            'remaining'        => $detail['remaining'],
            'deficiency_count' => $detail['deficiency_count'],
            'is_candidate'     => $totalUnits > 0 && $detail['not_started_or_failed_count'] === 0,
        ];
    }

    /**
     * @return array{completion_pct: ?float, deficiency_count: int, is_candidate: bool, remaining_count: int, in_progress_count: int}
     */
    private function summarize(array $required, array $grades, array $enrolled, array $acceptableIdsMap, array $codeById): array
    {
        $detail = $this->classify($required, $grades, $enrolled, $acceptableIdsMap, $codeById);

        $totalUnits = $detail['total_units'];
        $completedUnits = $detail['completed_units'];

        return [
            'completion_pct'    => $totalUnits > 0 ? round($completedUnits / $totalUnits * 100, 1) : null,
            'deficiency_count'  => $detail['deficiency_count'],
            'remaining_count'   => count($detail['remaining']),
            'in_progress_count' => count($detail['in_progress']),
            // A graduation candidate has nothing left except subjects
            // already in progress this term (or nothing left at all) —
            // passing whatever's in progress finishes the curriculum.
            // Zero required subjects at all (no curriculum assigned) is NOT
            // a candidate; that's a data gap, not progress.
            'is_candidate'      => $totalUnits > 0 && $detail['not_started_or_failed_count'] === 0,
        ];
    }

    /**
     * The shared classification core behind both summarize() and
     * forStudent() — one required-subjects list, one pass through it.
     *
     * @return array{
     *   total_units: float, completed_units: float,
     *   passed: list<array>, failed: list<array>, unresolved: list<array>,
     *   in_progress: list<array>, remaining: list<array>,
     *   deficiency_count: int, not_started_or_failed_count: int
     * }
     */
    private function classify(array $required, array $grades, array $enrolled, array $acceptableIdsMap, array $codeById): array
    {
        $totalUnits = 0.0;
        $completedUnits = 0.0;
        $passed = [];
        $failed = [];
        $unresolved = [];
        $inProgress = [];
        $remaining = [];
        $deficiencyCount = 0;
        $notStartedOrFailedCount = 0;

        foreach ($required as $req) {
            $units = (float) $req['units'];
            $totalUnits += $units;
            $reqId = (int) $req['subject_id'];
            $acceptableIds = $acceptableIdsMap[$reqId] ?? [$reqId];

            $passingVia = null;
            $attemptCode = null;
            $attemptGrade = null;

            foreach ($acceptableIds as $id) {
                $code = $codeById[$id] ?? null;

                if ($code === null || ! isset($grades[$code])) {
                    continue;
                }

                $g = $grades[$code];

                if ($g['is_passing'] === true) {
                    $passingVia = $code;
                    break;
                }

                if ($attemptGrade === null) {
                    $attemptCode = $code;
                    $attemptGrade = $g;
                }
            }

            if ($passingVia !== null) {
                $completedUnits += $units;
                $passed[] = [
                    'code'  => $req['subject_code'],
                    'title' => $req['subject_name'],
                    'units' => $units,
                    'via'   => $passingVia !== $req['subject_code'] ? $passingVia : null,
                ];
                continue;
            }

            $entry = ['code' => $req['subject_code'], 'title' => $req['subject_name'], 'units' => $units];

            if ($attemptGrade !== null) {
                if ($attemptGrade['is_passing'] === false) {
                    $failed[] = $entry + ['grade_value' => $attemptGrade['grade_value']];
                    $entry['status'] = 'failed';
                    $deficiencyCount++;
                    $notStartedOrFailedCount++;
                } else {
                    $unresolved[] = $entry + ['grade_value' => $attemptGrade['grade_value']];
                    $entry['status'] = 'unresolved';
                    $deficiencyCount++;
                    $notStartedOrFailedCount++;
                }
            } elseif (isset($enrolled[$req['subject_code']])) {
                $inProgress[] = $entry;
                $entry['status'] = 'in_progress';
            } else {
                $entry['status'] = 'not_started';
                $notStartedOrFailedCount++;
            }

            $remaining[] = $entry;
        }

        return [
            'total_units'                 => $totalUnits,
            'completed_units'             => $completedUnits,
            'passed'                      => $passed,
            'failed'                      => $failed,
            'unresolved'                  => $unresolved,
            'in_progress'                 => $inProgress,
            'remaining'                   => $remaining,
            'deficiency_count'            => $deficiencyCount,
            'not_started_or_failed_count' => $notStartedOrFailedCount,
        ];
    }

    /**
     * @param list<int> $curriculumIds
     *
     * @return array<int, list<array{subject_id: int, subject_code: string, subject_name: string, units: float}>>
     */
    private function requiredSubjectsByCurriculum(array $curriculumIds): array
    {
        if ($curriculumIds === []) {
            return [];
        }

        $rows = db_connect()->table('curriculum_subjects cs')
            ->select('cs.curriculum_id, s.subject_id, s.subject_code, s.subject_name, s.units')
            ->join('subjects s', 's.subject_id = cs.subject_id')
            ->whereIn('cs.curriculum_id', $curriculumIds)
            ->where('cs.is_active', 1)
            ->orderBy('cs.year_level')
            ->orderBy('cs.semester')
            ->orderBy('s.subject_code')
            ->get()
            ->getResultArray();

        $byCurriculum = [];

        foreach ($rows as $row) {
            $byCurriculum[(int) $row['curriculum_id']][] = [
                'subject_id'   => (int) $row['subject_id'],
                'subject_code' => $row['subject_code'],
                'subject_name' => $row['subject_name'],
                'units'        => (float) $row['units'],
            ];
        }

        return $byCurriculum;
    }

    /**
     * Every locked grade per user, keyed by subject_code. On a retake, a
     * passing grade always wins and is permanently kept (prerequisite/
     * completion credit doesn't expire, mirroring EnrollmentModel's own
     * rule) — otherwise the most recent attempt's value is kept.
     *
     * @param list<int> $userIds
     *
     * @return array<int, array<string, array{grade_value: string, is_passing: ?bool}>>
     */
    private function lockedGradesByUser(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = db_connect()->table('grades')
            ->select('user_id, subject_code, grade_value, is_passing, locked_at')
            ->whereIn('user_id', $userIds)
            ->where('status', 'locked')
            ->orderBy('locked_at', 'ASC')
            ->get()
            ->getResultArray();

        $byUser = [];

        foreach ($rows as $row) {
            $userId = (int) $row['user_id'];
            $code = $row['subject_code'];
            $current = $byUser[$userId][$code] ?? null;

            if ($current !== null && $current['is_passing'] === true) {
                continue;
            }

            $byUser[$userId][$code] = [
                'grade_value' => $row['grade_value'],
                'is_passing'  => $row['is_passing'] === null ? null : (bool) $row['is_passing'],
            ];
        }

        return $byUser;
    }

    /**
     * Subjects a user is actively enrolled in for the active academic term,
     * keyed by subject_code for O(1) lookup.
     *
     * @param list<int> $userIds
     *
     * @return array<int, array<string, true>>
     */
    private function currentEnrollmentsByUser(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $activeTerm = (new AcademicYearModel())->activeFormatted();

        if ($activeTerm === null) {
            return [];
        }

        $rows = db_connect()->table('enrollments e')
            ->select('e.user_id, e.subject_code')
            ->whereIn('e.user_id', $userIds)
            ->where('e.status', 'enrolled')
            ->where('e.academic_year', $activeTerm)
            ->get()
            ->getResultArray();

        $byUser = [];

        foreach ($rows as $row) {
            $byUser[(int) $row['user_id']][$row['subject_code']] = true;
        }

        return $byUser;
    }

    /**
     * @param array<int, list<int>> $acceptableIdsMap
     *
     * @return array<int, string>
     */
    private function codeById(array $acceptableIdsMap): array
    {
        $ids = [];

        foreach ($acceptableIdsMap as $group) {
            foreach ($group as $id) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        return array_column((new SubjectModel())->whereIn('subject_id', $ids)->findAll(), 'subject_code', 'subject_id');
    }
}

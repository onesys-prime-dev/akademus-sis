<?php

namespace App\Libraries;

/**
 * Centralizes "who needs this subject this term, who's already placed, and
 * who's left" — the shared-subject demand aggregation layer (Step 4 of the
 * shared-subject architecture work). Everything here is computed live from
 * students/curriculum_subjects/enrollments/grades/offering_placement_requests
 * — nothing is stored or cached.
 *
 * ARCHITECTURAL BOUNDARY — read before trusting a result for any term other
 * than the current one:
 *
 * A student's year_level and academic_year are both derived through their
 * SINGLE current section pointer (users.section_id -> sections). There is no
 * section-membership history in this schema. That means:
 *  - current/active term: fully supported by the data that exists.
 *  - historical terms: NOT reconstructable — a student's current section
 *    tells you nothing about which section they were in during a past term.
 *  - future terms: only accurate once students have actually been assigned
 *    to sections representing that future term (i.e., it behaves like
 *    "current" once that assignment exists, and like an empty/garbage
 *    result before then).
 * This service accepts an explicit $academicYear parameter for testability
 * and so historical/future terms CAN be queried once real section data for
 * them exists — but it never fabricates placement for a term nobody has
 * actually been sectioned into. summary() always surfaces this boundary via
 * `limitations`, not just as a warning on failure.
 *
 * Also documented, not hidden: assigned_count and enrolled_count are
 * computed from the SAME underlying query today, because
 * `enrollments.status` only ever has two real values ('enrolled','dropped')
 * — there is no intermediate "assigned but not yet confirmed" state in this
 * schema. `assigned_equals_enrolled` is always true until that changes.
 */
class SubjectDemandService
{
    /**
     * - no_automatic_subject_equivalency: similar titles/codes/prefixes
     *   never count as the same subject — only an explicit, active
     *   `subject_substitutions` row does (Step 8).
     * - substitution_rules_not_historically_versioned: `is_active` is a
     *   live on/off switch, not a time-travel record. A substitution rule
     *   is evaluated using whatever is active RIGHT NOW. Locked grades
     *   themselves never change, but recomputing demand/prerequisite
     *   satisfaction for a past term uses today's rule set, which may
     *   differ from what was configured back then — there is no dated
     *   "as of this term" snapshot of the rule set itself.
     */
    private const LIMITATIONS = [
        'current_section_only_term_resolution',
        'assigned_equals_enrolled',
        'no_retake_tracking',
        'no_credit_transfer_tracking',
        'no_automatic_subject_equivalency',
        'substitution_rules_not_historically_versioned',
    ];

    /**
     * @return array{
     *   subject_id: int, academic_year: string, semester: ?int,
     *   expected_count: int, assigned_count: int, enrolled_count: int, unassigned_count: int,
     *   assigned_equals_enrolled: bool,
     *   by_program: list<array{program_id: ?int, program_name: ?string, expected_count: int}>,
     *   by_section: list<array{section_id: int, section_label: string, expected_count: int}>,
     *   placement_requests: array{pending: int, approved_not_fulfilled: int},
     *   skipped_unparseable_students: int,
     *   limitations: list<string>
     * }
     */
    public function summary(int $subjectId, string $academicYear): array
    {
        $expected = $this->expectedStudentRowsInternal($subjectId, $academicYear);

        $expectedIds = array_map(static fn (array $r) => (int) $r['user_id'], $expected['rows']);
        $assignedIds = $this->assignedUserIds($subjectId, $academicYear);
        $unassignedIds = array_values(array_diff($expectedIds, $assignedIds));

        return [
            'subject_id'                   => $subjectId,
            'academic_year'                => $academicYear,
            'semester'                     => $expected['semester'],
            'expected_count'               => count($expectedIds),
            'assigned_count'               => count($assignedIds),
            'enrolled_count'               => count($assignedIds),
            'unassigned_count'             => count($unassignedIds),
            'assigned_equals_enrolled'     => true,
            'by_program'                   => $this->groupBy($expected['rows'], 'program_id', 'program_name'),
            'by_section'                   => $this->groupBy($expected['rows'], 'section_id', 'section_label'),
            'placement_requests'           => $this->placementRequestCounts($subjectId, $academicYear),
            'skipped_unparseable_students' => $expected['skipped_unparseable'],
            'limitations'                  => self::LIMITATIONS,
        ];
    }

    /**
     * Batched equivalent of calling summary() once per subject — built for
     * dashboards showing many subjects at once. Produces the same numbers
     * summary() would for each subject_id (verified in tests), but the
     * expensive "which students are active this term" query runs ONCE for
     * the whole batch instead of once per subject, since that query never
     * actually filtered by subject in the first place.
     *
     * @param list<int> $subjectIds
     *
     * @return array<int, array{
     *   subject_id: int, academic_year: string, semester: ?int,
     *   expected_count: int, assigned_count: int, enrolled_count: int, unassigned_count: int,
     *   assigned_equals_enrolled: bool,
     *   by_program: list<array{program_id: ?int, program_name: ?string, expected_count: int}>,
     *   placement_requests: array{pending: int, approved_not_fulfilled: int},
     *   limitations: list<string>
     * }> keyed by subject_id
     */
    public function summaryForSubjects(array $subjectIds, string $academicYear): array
    {
        $subjectIds = array_values(array_unique(array_map('intval', $subjectIds)));

        if ($subjectIds === []) {
            return [];
        }

        $limitations = self::LIMITATIONS;

        $semester = TermCurriculum::semesterOf($academicYear);

        $empty = static fn (int $subjectId) => [
            'subject_id' => $subjectId, 'academic_year' => $academicYear, 'semester' => $semester,
            'expected_count' => 0, 'assigned_count' => 0, 'enrolled_count' => 0, 'unassigned_count' => 0,
            'assigned_equals_enrolled' => true, 'by_program' => [],
            'placement_requests' => ['pending' => 0, 'approved_not_fulfilled' => 0],
            'limitations' => $limitations,
        ];

        if ($semester === null) {
            return array_combine($subjectIds, array_map($empty, $subjectIds));
        }

        $db = db_connect();

        // Acceptable completed subjects per required subject (itself + any
        // explicitly configured active substitute, Step 8) — one batched
        // query for the whole set, never per-subject.
        $acceptableBySubject = (new SubjectRequirementService())->acceptableSubjectIdsFor($subjectIds);
        $allAcceptableIds = array_values(array_unique(array_merge(...array_values($acceptableBySubject))));

        $codeRows   = $db->table('subjects')->select('subject_id, subject_code')->whereIn('subject_id', $allAcceptableIds)->get()->getResultArray();
        $codeById   = array_column($codeRows, 'subject_code', 'subject_id');
        // "Assigned" stays scoped to each subject's OWN code only —
        // substitution affects which PASSED grades satisfy a requirement,
        // never what counts as an actual enrollment/assignment.
        $allCodes   = array_values(array_intersect_key($codeById, array_flip($subjectIds)));

        $results = array_combine($subjectIds, array_map($empty, $subjectIds));

        // Placement-request counts and assigned/enrolled counts are both
        // independent of the "expected demand" student population below —
        // they must still be correct even when zero students currently
        // match this term's curriculum requirements, so they're computed
        // before (not gated behind) the candidates-empty check.
        $requestRows = $db->table('offering_placement_requests opr')
            ->select('opr.subject_id, opr.status, opr.request_type')
            ->join('sections sec', 'sec.section_id = opr.section_id')
            ->whereIn('opr.subject_id', $subjectIds)
            ->where('sec.academic_year', $academicYear)
            ->get()
            ->getResultArray();

        $requestCountsBySubject = [];

        foreach ($requestRows as $r) {
            $sid = (int) $r['subject_id'];
            $requestCountsBySubject[$sid] ??= ['pending' => 0, 'approved_not_fulfilled' => 0];

            if ($r['status'] === 'pending') {
                $requestCountsBySubject[$sid]['pending']++;
            } elseif ($r['status'] === 'approved' && $r['request_type'] === 'placement') {
                $requestCountsBySubject[$sid]['approved_not_fulfilled']++;
            }
        }

        $assignedBySubjectCode = [];

        if ($allCodes !== []) {
            $assignedRows = $db->table('enrollments e')
                ->distinct()
                ->select('e.subject_code, e.user_id')
                ->join('sections sec', 'sec.section_id = e.section_id')
                ->whereIn('e.subject_code', $allCodes)
                ->where('e.status', 'enrolled')
                ->where('sec.academic_year', $academicYear)
                ->get()
                ->getResultArray();

            foreach ($assignedRows as $r) {
                $assignedBySubjectCode[$r['subject_code']][] = (int) $r['user_id'];
            }
        }

        foreach ($subjectIds as $subjectId) {
            $results[$subjectId]['placement_requests'] = $requestCountsBySubject[$subjectId] ?? ['pending' => 0, 'approved_not_fulfilled' => 0];

            $code = $codeById[$subjectId] ?? null;
            $assignedIds = $code !== null ? ($assignedBySubjectCode[$code] ?? []) : [];
            $results[$subjectId]['assigned_count'] = count($assignedIds);
            $results[$subjectId]['enrolled_count'] = count($assignedIds);
        }

        // The "active students this term" population — subject-independent,
        // fetched once for every subject in the batch. Only the EXPECTED
        // side of the result depends on this.
        $candidates = $db->table('students st')
            ->select('st.student_id, st.user_id, st.curriculum_id, st.program_id, p.program_name, sec.section_id, sec.year_level')
            ->join('users u', 'u.id = st.user_id')
            ->join('sections sec', 'sec.section_id = u.section_id')
            ->join('programs p', 'p.program_id = st.program_id', 'left')
            ->where('st.student_status', 'active')
            ->where('sec.academic_year', $academicYear)
            ->where('st.curriculum_id IS NOT NULL')
            ->get()
            ->getResultArray();

        if ($candidates === [] || $allCodes === []) {
            // Unassigned still needs expected (0) minus assigned, already
            // set above — but unassigned_count defaults to 0 in $empty, and
            // with expected=0 that's correct only when assigned is also 0.
            foreach ($subjectIds as $subjectId) {
                $results[$subjectId]['unassigned_count'] = max(0, $results[$subjectId]['expected_count'] - $results[$subjectId]['assigned_count']);
            }

            return $results;
        }

        $curriculumIds = array_values(array_unique(array_map(static fn (array $r) => (int) $r['curriculum_id'], $candidates)));

        // Every (subject, curriculum, year_level) requirement in this batch, in one query.
        $reqRows = $db->table('curriculum_subjects')
            ->select('subject_id, curriculum_id, year_level')
            ->whereIn('subject_id', $subjectIds)
            ->where('semester', $semester)
            ->where('is_active', 1)
            ->whereIn('curriculum_id', $curriculumIds)
            ->get()
            ->getResultArray();

        $requiredPairsBySubject = [];

        foreach ($reqRows as $r) {
            $requiredPairsBySubject[$r['subject_id']][$r['curriculum_id'] . ':' . $r['year_level']] = true;
        }

        $matchedBySubject = array_fill_keys($subjectIds, []);

        foreach ($candidates as $row) {
            $level = TermCurriculum::yearLevelOf($row['year_level']);

            if ($level === null) {
                continue;
            }

            $pairKey = $row['curriculum_id'] . ':' . $level;

            foreach ($subjectIds as $subjectId) {
                if (! empty($requiredPairsBySubject[$subjectId][$pairKey])) {
                    $matchedBySubject[$subjectId][(int) $row['user_id']] = $row;
                }
            }
        }

        $allMatchedUserIds = array_values(array_unique(array_merge(...array_map('array_keys', $matchedBySubject)) ?: []));

        // Every (user, subject_code) that's already passed, across EVERY
        // acceptable code (own + substitutes) for the whole batch, in one query.
        $allAcceptableCodes = array_values(array_unique(array_intersect_key($codeById, array_flip($allAcceptableIds))));
        $passedBySubjectCode = [];

        if ($allMatchedUserIds !== [] && $allAcceptableCodes !== []) {
            $passedRows = $db->table('grades')
                ->select('subject_code, user_id')
                ->whereIn('subject_code', $allAcceptableCodes)
                ->where('status', 'locked')
                ->where('is_passing', 1)
                ->whereIn('user_id', $allMatchedUserIds)
                ->get()
                ->getResultArray();

            foreach ($passedRows as $r) {
                $passedBySubjectCode[$r['subject_code']][(int) $r['user_id']] = true;
            }
        }

        // $assignedBySubjectCode and $requestCountsBySubject were already
        // computed above (independent of this student population).
        foreach ($subjectIds as $subjectId) {
            $matched = $matchedBySubject[$subjectId];
            $code    = $codeById[$subjectId] ?? null;

            // Exclude a student if they've passed the exact subject OR any
            // explicitly configured active substitute for it.
            foreach ($acceptableBySubject[$subjectId] ?? [$subjectId] as $acceptableId) {
                $acceptableCode = $codeById[$acceptableId] ?? null;

                if ($acceptableCode === null || ! isset($passedBySubjectCode[$acceptableCode])) {
                    continue;
                }

                foreach (array_keys($passedBySubjectCode[$acceptableCode]) as $passedUserId) {
                    unset($matched[$passedUserId]);
                }
            }

            $expectedIds = array_keys($matched);
            $assignedIds = $code !== null ? ($assignedBySubjectCode[$code] ?? []) : [];
            $unassignedCount = count(array_diff($expectedIds, $assignedIds));

            $results[$subjectId] = [
                'subject_id'               => $subjectId,
                'academic_year'            => $academicYear,
                'semester'                 => $semester,
                'expected_count'           => count($expectedIds),
                'assigned_count'           => count($assignedIds),
                'enrolled_count'           => count($assignedIds),
                'unassigned_count'         => $unassignedCount,
                'assigned_equals_enrolled' => true,
                'by_program'               => $this->groupBy(array_map(static fn (array $r) => [
                    'program_id'   => $r['program_id'] !== null ? (int) $r['program_id'] : null,
                    'program_name' => $r['program_name'],
                ], array_values($matched)), 'program_id', 'program_name'),
                'placement_requests' => $requestCountsBySubject[$subjectId] ?? ['pending' => 0, 'approved_not_fulfilled' => 0],
                'limitations'        => $limitations,
            ];
        }

        return $results;
    }

    /**
     * @return list<array{user_id: int, student_id: int, program_id: ?int, program_name: ?string, section_id: int, section_label: string, curriculum_id: int}>
     */
    public function expectedStudents(int $subjectId, string $academicYear): array
    {
        return $this->expectedStudentRowsInternal($subjectId, $academicYear)['rows'];
    }

    /**
     * @return list<array{user_id: int, student_id: int, program_id: ?int, program_name: ?string, section_id: int, section_label: string, curriculum_id: int}>
     */
    public function assignedStudents(int $subjectId, string $academicYear): array
    {
        $ids = $this->assignedUserIds($subjectId, $academicYear);

        return $this->studentRowsFor($ids, $academicYear);
    }

    /**
     * Identical to assignedStudents() today — see the class docblock on
     * assigned_equals_enrolled. Kept as a separate method so the dashboard
     * contract doesn't need to change if the enrollment domain later gains
     * a real "assigned but not yet confirmed" state.
     *
     * @return list<array{user_id: int, student_id: int, program_id: ?int, program_name: ?string, section_id: int, section_label: string, curriculum_id: int}>
     */
    public function enrolledStudents(int $subjectId, string $academicYear): array
    {
        return $this->assignedStudents($subjectId, $academicYear);
    }

    /**
     * @return list<array{user_id: int, student_id: int, program_id: ?int, program_name: ?string, section_id: int, section_label: string, curriculum_id: int}>
     */
    public function unassignedStudents(int $subjectId, string $academicYear): array
    {
        $expected = $this->expectedStudentRowsInternal($subjectId, $academicYear)['rows'];
        $assignedIds = $this->assignedUserIds($subjectId, $academicYear);

        return array_values(array_filter($expected, static fn (array $r) => ! in_array((int) $r['user_id'], $assignedIds, true)));
    }

    /**
     * @return list<array{program_id: ?int, program_name: ?string, expected_count: int}>
     */
    public function demandByProgram(int $subjectId, string $academicYear): array
    {
        return $this->groupBy($this->expectedStudentRowsInternal($subjectId, $academicYear)['rows'], 'program_id', 'program_name');
    }

    /**
     * @return list<array{section_id: int, section_label: string, expected_count: int}>
     */
    public function demandBySection(int $subjectId, string $academicYear): array
    {
        return $this->groupBy($this->expectedStudentRowsInternal($subjectId, $academicYear)['rows'], 'section_id', 'section_label');
    }

    /**
     * Term-scoped via offering_placement_requests.section_id ->
     * sections.academic_year (the table has no academic_year column of its
     * own — see Step 4 adjustment 2). A request from a different term never
     * affects this term's counts, and vice versa.
     *
     * @return array{pending: int, approved_not_fulfilled: int}
     */
    public function placementRequestCounts(int $subjectId, string $academicYear): array
    {
        $base = static fn () => db_connect()->table('offering_placement_requests opr')
            ->join('sections sec', 'sec.section_id = opr.section_id')
            ->where('opr.subject_id', $subjectId)
            ->where('sec.academic_year', $academicYear);

        $pending = $base()->where('opr.status', 'pending')->countAllResults();

        $approvedNotFulfilled = $base()
            ->where('opr.status', 'approved')
            ->where('opr.request_type', 'placement')
            ->countAllResults();

        return ['pending' => $pending, 'approved_not_fulfilled' => $approvedNotFulfilled];
    }

    /**
     * @return array{rows: list<array>, skipped_unparseable: int, semester: ?int}
     */
    private function expectedStudentRowsInternal(int $subjectId, string $academicYear): array
    {
        $semester = TermCurriculum::semesterOf($academicYear);

        if ($semester === null) {
            return ['rows' => [], 'skipped_unparseable' => 0, 'semester' => null];
        }

        $candidates = db_connect()->table('students st')
            ->select('st.student_id, st.user_id, st.curriculum_id, st.program_id, p.program_name, sec.section_id, sec.year_level, sec.section AS section_label')
            ->join('users u', 'u.id = st.user_id')
            ->join('sections sec', 'sec.section_id = u.section_id')
            ->join('programs p', 'p.program_id = st.program_id', 'left')
            ->where('st.student_status', 'active')
            ->where('sec.academic_year', $academicYear)
            ->where('st.curriculum_id IS NOT NULL')
            ->get()
            ->getResultArray();

        if ($candidates === []) {
            return ['rows' => [], 'skipped_unparseable' => 0, 'semester' => $semester];
        }

        $curriculumIds = array_values(array_unique(array_map(static fn (array $r) => (int) $r['curriculum_id'], $candidates)));

        $requiredPairs = [];

        $reqRows = db_connect()->table('curriculum_subjects')
            ->select('curriculum_id, year_level')
            ->where('subject_id', $subjectId)
            ->where('semester', $semester)
            ->where('is_active', 1)
            ->whereIn('curriculum_id', $curriculumIds)
            ->get()
            ->getResultArray();

        foreach ($reqRows as $r) {
            $requiredPairs[$r['curriculum_id'] . ':' . $r['year_level']] = true;
        }

        $matched = [];
        $skippedUnparseable = 0;

        foreach ($candidates as $row) {
            $level = TermCurriculum::yearLevelOf($row['year_level']);

            if ($level === null) {
                $skippedUnparseable++;

                continue;
            }

            $key = $row['curriculum_id'] . ':' . $level;

            if (isset($requiredPairs[$key])) {
                $matched[(int) $row['user_id']] = [
                    'user_id'       => (int) $row['user_id'],
                    'student_id'    => (int) $row['student_id'],
                    'program_id'    => $row['program_id'] !== null ? (int) $row['program_id'] : null,
                    'program_name'  => $row['program_name'],
                    'section_id'    => (int) $row['section_id'],
                    'section_label' => $row['section_label'],
                    'curriculum_id' => (int) $row['curriculum_id'],
                ];
            }
        }

        if ($matched !== []) {
            // Acceptable completed subjects: the required subject itself,
            // plus any explicitly configured active substitute (Step 8) —
            // never a title/code similarity guess.
            $acceptableIds = (new SubjectRequirementService())->satisfyingSubjectIds($subjectId);

            $acceptableCodes = array_values(array_filter(array_map(
                fn (int $id) => $this->subjectCodeFor($id),
                $acceptableIds
            )));

            if ($acceptableCodes !== []) {
                $passedIds = array_column(
                    db_connect()->table('grades')
                        ->distinct()
                        ->select('user_id')
                        ->whereIn('subject_code', $acceptableCodes)
                        ->where('status', 'locked')
                        ->where('is_passing', 1)
                        ->whereIn('user_id', array_keys($matched))
                        ->get()
                        ->getResultArray(),
                    'user_id'
                );

                foreach ($passedIds as $passedId) {
                    unset($matched[(int) $passedId]);
                }
            }
        }

        return ['rows' => array_values($matched), 'skipped_unparseable' => $skippedUnparseable, 'semester' => $semester];
    }

    /**
     * @return list<int>
     */
    private function assignedUserIds(int $subjectId, string $academicYear): array
    {
        $subjectCode = $this->subjectCodeFor($subjectId);

        if ($subjectCode === null) {
            return [];
        }

        $rows = db_connect()->table('enrollments e')
            ->distinct()
            ->select('e.user_id')
            ->join('sections sec', 'sec.section_id = e.section_id')
            ->where('e.subject_code', $subjectCode)
            ->where('e.status', 'enrolled')
            ->where('sec.academic_year', $academicYear)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $r) => (int) $r['user_id'], $rows);
    }

    /**
     * @param list<int> $userIds
     *
     * @return list<array>
     */
    private function studentRowsFor(array $userIds, string $academicYear): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = db_connect()->table('students st')
            ->select('st.student_id, st.user_id, st.curriculum_id, st.program_id, p.program_name, sec.section_id, sec.section AS section_label')
            ->join('users u', 'u.id = st.user_id')
            ->join('sections sec', 'sec.section_id = u.section_id', 'left')
            ->join('programs p', 'p.program_id = st.program_id', 'left')
            ->whereIn('st.user_id', $userIds)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $r) => [
            'user_id'       => (int) $r['user_id'],
            'student_id'    => (int) $r['student_id'],
            'program_id'    => $r['program_id'] !== null ? (int) $r['program_id'] : null,
            'program_name'  => $r['program_name'],
            'section_id'    => $r['section_id'] !== null ? (int) $r['section_id'] : null,
            'section_label' => $r['section_label'],
            'curriculum_id' => $r['curriculum_id'] !== null ? (int) $r['curriculum_id'] : null,
        ], $rows);
    }

    /**
     * @param list<array> $rows
     *
     * @return list<array{0: mixed, expected_count: int}>
     */
    private function groupBy(array $rows, string $idField, string $labelField): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $id = $row[$idField];
            $key = $id ?? 'null';

            if (! isset($groups[$key])) {
                $groups[$key] = [$idField => $id, $labelField => $row[$labelField], 'expected_count' => 0];
            }

            $groups[$key]['expected_count']++;
        }

        return array_values($groups);
    }

    private function subjectCodeFor(int $subjectId): ?string
    {
        $row = db_connect()->table('subjects')->select('subject_code')->where('subject_id', $subjectId)->get()->getRowArray();

        return $row['subject_code'] ?? null;
    }
}

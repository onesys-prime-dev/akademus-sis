<?php

namespace App\Libraries;

use App\Models\CourseOfferingModel;
use App\Models\DepartmentModel;

/**
 * Answers the inverse question from Step 6's SharedSubjectDashboardService:
 * not "what do I own and need to act on," but "what do MY students need
 * that someone ELSE owns, and are they covered?" (Step 7).
 *
 * A subject appears here only when:
 *  - at least one of MY students (in a section I'm authorized to manage,
 *    via SchedulingAuthorization) requires it this term, AND
 *  - its owning sub-department is NOT one I'm authorized to manage
 *    (own or delegated) — so it can never appear here AND on the Step 6
 *    dashboard for the same user; both read from the same
 *    authorizedSubDeptIds() call, making the split mutually exclusive by
 *    construction, not by a second ad hoc rule.
 *
 * Coverage is computed only from real enrollments — never from pending or
 * approved request status. Nothing here can mutate another unit's offering;
 * every action this view offers reuses the existing Step 3 request endpoint
 * exactly as it exists today.
 */
class SharedSubjectCoverageService
{
    public function build(int $userId, string $academicYear): array
    {
        $empty = ['term' => $academicYear, 'subjects' => [], 'my_section_count' => 0];

        $auth = new SchedulingAuthorization();
        $subDeptIds = $auth->authorizedSubDeptIds($userId);

        if ($subDeptIds === []) {
            return $empty;
        }

        $db = db_connect();
        $deptModel = new DepartmentModel();

        $myProgramNames = [];
        $myProgramIds   = [];

        foreach ($subDeptIds as $subDeptId) {
            foreach ($deptModel->programsOfSubDept($subDeptId) as $name) {
                $myProgramNames[$name] = true;
            }
            foreach ($deptModel->programIdsOfSubDept($subDeptId) as $programId) {
                $myProgramIds[$programId] = true;
            }
        }

        $myProgramNames = array_keys($myProgramNames);
        $myProgramIds   = array_keys($myProgramIds);

        if ($myProgramNames === [] && $myProgramIds === []) {
            return $empty;
        }

        // Prefer the FK (program_id); OR in the legacy text match so a
        // section not yet backfilled doesn't silently drop out of its
        // owner's coverage view during the transition.
        $mySections = $db->table('sections')
            ->groupStart()
                ->whereIn('program_id', $myProgramIds === [] ? [0] : $myProgramIds)
                ->orWhereIn('program', $myProgramNames === [] ? [''] : $myProgramNames)
            ->groupEnd()
            ->where('academic_year', $academicYear)
            ->get()
            ->getResultArray();

        if ($mySections === []) {
            return $empty;
        }

        $mySectionById = [];

        foreach ($mySections as $s) {
            $mySectionById[(int) $s['section_id']] = $s;
        }

        $mySectionIds = array_keys($mySectionById);

        $semester = TermCurriculum::semesterOf($academicYear);

        $limitations = [
            'current_section_only_term_resolution',
            'assigned_equals_enrolled',
            'no_retake_tracking',
            'no_credit_transfer_tracking',
            'no_subject_equivalency',
        ];

        if ($semester === null) {
            return ['term' => $academicYear, 'subjects' => [], 'my_section_count' => count($mySectionIds)];
        }

        // My active students this term, in sections I manage.
        $myStudents = $db->table('students st')
            ->select('st.user_id, st.curriculum_id, u.section_id')
            ->join('users u', 'u.id = st.user_id')
            ->whereIn('u.section_id', $mySectionIds)
            ->where('st.student_status', 'active')
            ->where('st.curriculum_id IS NOT NULL')
            ->get()
            ->getResultArray();

        if ($myStudents === []) {
            return ['term' => $academicYear, 'subjects' => [], 'my_section_count' => count($mySectionIds)];
        }

        $curriculumIds = array_values(array_unique(array_map(static fn (array $r) => (int) $r['curriculum_id'], $myStudents)));

        // EVERY subject my population's curricula require this semester —
        // not filtered to one subject, since we don't know in advance which
        // ones are external.
        $reqRows = $db->table('curriculum_subjects cs')
            ->select('cs.curriculum_id, cs.year_level, s.subject_id, s.subject_code, s.subject_name, s.sub_department_id')
            ->join('subjects s', 's.subject_id = cs.subject_id')
            ->whereIn('cs.curriculum_id', $curriculumIds)
            ->where('cs.semester', $semester)
            ->where('cs.is_active', 1)
            ->where('s.is_active', 1)
            ->get()
            ->getResultArray();

        if ($reqRows === []) {
            return ['term' => $academicYear, 'subjects' => [], 'my_section_count' => count($mySectionIds)];
        }

        $subjectMeta = [];
        $requiredPairsBySubject = [];

        foreach ($reqRows as $r) {
            $sid = (int) $r['subject_id'];
            $subjectMeta[$sid] ??= [
                'subject_code' => $r['subject_code'],
                'subject_name' => $r['subject_name'],
                'owner_sub_dept_id' => $r['sub_department_id'] !== null ? (int) $r['sub_department_id'] : null,
            ];
            $requiredPairsBySubject[$sid][$r['curriculum_id'] . ':' . $r['year_level']] = true;
        }

        // Match my students to subjects.
        $matchedBySubject = []; // subjectId => userId => sectionId

        foreach ($myStudents as $row) {
            $section = $mySectionById[(int) $row['section_id']] ?? null;

            if ($section === null) {
                continue;
            }

            $level = TermCurriculum::yearLevelOf($section['year_level']);

            if ($level === null) {
                continue;
            }

            $pairKey = $row['curriculum_id'] . ':' . $level;

            foreach ($requiredPairsBySubject as $subjectId => $pairs) {
                if (! empty($pairs[$pairKey])) {
                    $matchedBySubject[$subjectId][(int) $row['user_id']] = (int) $row['section_id'];
                }
            }
        }

        if ($matchedBySubject === []) {
            return ['term' => $academicYear, 'subjects' => [], 'my_section_count' => count($mySectionIds)];
        }

        // Exclude already-passed, batched across every matched subject.
        $allMatchedUserIds = array_values(array_unique(array_merge(...array_map('array_keys', $matchedBySubject))));
        $allCodes = array_column($subjectMeta, 'subject_code');

        $passedBySubjectCode = [];

        if ($allMatchedUserIds !== [] && $allCodes !== []) {
            $passedRows = $db->table('grades')
                ->select('subject_code, user_id')
                ->whereIn('subject_code', $allCodes)
                ->where('status', 'locked')
                ->where('is_passing', 1)
                ->whereIn('user_id', $allMatchedUserIds)
                ->get()
                ->getResultArray();

            foreach ($passedRows as $r) {
                $passedBySubjectCode[$r['subject_code']][(int) $r['user_id']] = true;
            }
        }

        foreach ($matchedBySubject as $subjectId => $users) {
            $code = $subjectMeta[$subjectId]['subject_code'];

            foreach (array_keys($passedBySubjectCode[$code] ?? []) as $passedUserId) {
                unset($matchedBySubject[$subjectId][$passedUserId]);
            }
        }

        // Split: only EXTERNAL subjects (owner not in my authorized units) go further.
        $externalSubjectIds = array_values(array_filter(
            array_keys($matchedBySubject),
            static fn ($sid) => ! in_array($subjectMeta[$sid]['owner_sub_dept_id'], $subDeptIds, true)
        ));

        if ($externalSubjectIds === []) {
            return ['term' => $academicYear, 'subjects' => [], 'my_section_count' => count($mySectionIds)];
        }

        $externalCodes = array_map(static fn ($sid) => $subjectMeta[$sid]['subject_code'], $externalSubjectIds);

        // Assigned (enrolled) among MY matched students, batched.
        $assignedUserIdsByCode = [];

        $assignedRows = $db->table('enrollments')
            ->distinct()
            ->select('subject_code, user_id')
            ->whereIn('subject_code', $externalCodes)
            ->where('status', 'enrolled')
            ->whereIn('user_id', $allMatchedUserIds)
            ->get()
            ->getResultArray();

        foreach ($assignedRows as $r) {
            $assignedUserIdsByCode[$r['subject_code']][(int) $r['user_id']] = true;
        }

        // Owner unit display names.
        $ownerIds = array_values(array_unique(array_filter(array_map(static fn ($sid) => $subjectMeta[$sid]['owner_sub_dept_id'], $externalSubjectIds))));
        $ownerNames = $ownerIds === [] ? [] : array_column(
            $db->table('sub_departments')->select('sub_dept_id, name')->whereIn('sub_dept_id', $ownerIds)->get()->getResultArray(),
            'name',
            'sub_dept_id'
        );

        // Candidate offerings for these external subjects, this term only.
        $offeringRows = $db->table('course_offerings')
            ->whereIn('subject_id', $externalSubjectIds)
            ->where('academic_year', $academicYear)
            ->get()
            ->getResultArray();

        $offeringIds = array_map(static fn (array $o) => (int) $o['offering_id'], $offeringRows);
        $offeringsBySubject = [];

        foreach ($offeringRows as $o) {
            $offeringsBySubject[(int) $o['subject_id']][] = $o;
        }

        $rosterCounts = (new CourseOfferingModel())->rosterCountsFor($offeringIds);

        $sectionsByOffering = [];

        if ($offeringIds !== []) {
            $linkRows = $db->table('offering_sections os')
                ->join('sections sec', 'sec.section_id = os.section_id')
                ->whereIn('os.offering_id', $offeringIds)
                ->select('os.offering_id, sec.program, sec.year_level, sec.section')
                ->get()
                ->getResultArray();

            foreach ($linkRows as $r) {
                $sectionsByOffering[(int) $r['offering_id']][] = [
                    'label'   => "{$r['program']} · {$r['year_level']} · {$r['section']}",
                    'program' => $r['program'],
                ];
            }
        }

        $meetingsByOffering = [];

        if ($offeringIds !== []) {
            $scheduleRows = $db->table('plotted_schedules')->whereIn('offering_id', $offeringIds)->get()->getResultArray();

            foreach ($scheduleRows as $r) {
                $meetingsByOffering[(int) $r['offering_id']][] = [
                    'day'       => (int) $r['day'],
                    'start'     => substr($r['start_time'], 0, 5),
                    'end'       => substr($r['end_time'], 0, 5),
                    'day_label' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][(int) $r['day']] ?? '?',
                    'room'      => $r['room'],
                    'faculty'   => $r['prof'],
                ];
            }
        }

        // My sections' own existing commitments — once per section I manage
        // that actually has demand for one of these subjects, never once
        // per subject/offering pair.
        $relevantSectionIds = array_values(array_unique(array_merge(...array_values(array_map(
            static fn (array $users) => array_values($users),
            array_intersect_key($matchedBySubject, array_flip($externalSubjectIds))
        )))));

        $conflictService = new SchedulingConflictService();
        $myCommitmentsBySection = [];

        foreach ($relevantSectionIds as $sectionId) {
            $myCommitmentsBySection[$sectionId] = $conflictService->meetingsForSection($sectionId);
        }

        // Active (pending/approved) requests tied to my sections, batched.
        $requestRows = $offeringIds === [] && $externalSubjectIds === []
            ? []
            : $db->table('offering_placement_requests')
                ->whereIn('section_id', $mySectionIds)
                ->whereIn('subject_id', $externalSubjectIds)
                ->whereIn('status', ['pending', 'approved'])
                ->get()
                ->getResultArray();

        $requestsBySectionSubject = [];

        foreach ($requestRows as $r) {
            $requestsBySectionSubject[(int) $r['section_id']][(int) $r['subject_id']][] = [
                'request_type' => $r['request_type'],
                'status'       => $r['status'],
                'target_offering_id' => $r['target_offering_id'] !== null ? (int) $r['target_offering_id'] : null,
            ];
        }

        // Assemble.
        $subjectEntries = [];

        foreach ($externalSubjectIds as $subjectId) {
            $users = $matchedBySubject[$subjectId];
            $code  = $subjectMeta[$subjectId]['subject_code'];
            $assignedSet = $assignedUserIdsByCode[$code] ?? [];

            $bySection = [];

            foreach ($users as $userId => $sectionId) {
                $bySection[$sectionId]['expected'][] = $userId;

                if (isset($assignedSet[$userId])) {
                    $bySection[$sectionId]['assigned'][] = $userId;
                }
            }

            $sectionEntries = [];
            $myExpectedTotal = 0;
            $myAssignedTotal = 0;

            foreach ($bySection as $sectionId => $counts) {
                $expected = $counts['expected'] ?? [];
                $assigned = $counts['assigned'] ?? [];
                $unassigned = array_diff($expected, $assigned);

                $myExpectedTotal += count($expected);
                $myAssignedTotal += count($assigned);

                $section = $mySectionById[$sectionId];
                $existingRequests = $requestsBySectionSubject[$sectionId][$subjectId] ?? [];

                $sectionEntries[] = [
                    'section_id'      => $sectionId,
                    'label'           => "{$section['program']} · {$section['year_level']} · {$section['section']}",
                    'expected_count'  => count($expected),
                    'assigned_count'  => count($assigned),
                    'unassigned_count' => count($unassigned),
                    'status'          => count($unassigned) === 0 ? 'covered' : (count($assigned) > 0 ? 'partial' : 'not_covered'),
                    'has_pending_placement'  => (bool) array_filter($existingRequests, static fn ($r) => $r['request_type'] === 'placement' && $r['status'] === 'pending'),
                    'has_approved_placement' => (bool) array_filter($existingRequests, static fn ($r) => $r['request_type'] === 'placement' && $r['status'] === 'approved'),
                    'has_pending_additional' => (bool) array_filter($existingRequests, static fn ($r) => $r['request_type'] === 'additional_offering' && $r['status'] === 'pending'),
                    'has_pending_conflict'   => (bool) array_filter($existingRequests, static fn ($r) => $r['request_type'] === 'conflict_report' && $r['status'] === 'pending'),
                    'conflict_offering_ids'  => array_column(array_filter($existingRequests, static fn ($r) => $r['request_type'] === 'conflict_report'), 'target_offering_id'),
                ];
            }

            $myUnassignedTotal = $myExpectedTotal - $myAssignedTotal;

            $coverage = $myExpectedTotal === 0
                ? 'no_demand'
                : ($myUnassignedTotal === 0 ? 'fully_covered' : ($myAssignedTotal > 0 ? 'partially_covered' : 'not_covered'));

            $offerings = [];

            foreach ($offeringsBySubject[$subjectId] ?? [] as $o) {
                $offeringId = (int) $o['offering_id'];
                $roster     = $rosterCounts[$offeringId] ?? 0;
                $capacity   = $o['capacity'] !== null ? (int) $o['capacity'] : null;
                $meetings   = $meetingsByOffering[$offeringId] ?? [];

                // Conflict against EVERY section of mine that needs this subject.
                $conflictsBySection = [];

                foreach (array_keys($bySection) as $sectionId) {
                    $myMeetings = $myCommitmentsBySection[$sectionId] ?? [];
                    $hits = [];

                    foreach ($myMeetings as $mine) {
                        $mineBlock = ['day' => (int) $mine['day'], 'start' => substr((string) $mine['start_time'], 0, 5), 'end' => substr((string) $mine['end_time'], 0, 5)];

                        foreach ($meetings as $theirs) {
                            if (ScheduleOverlap::clashes($mineBlock, $theirs)) {
                                $hits[] = "{$theirs['day_label']} {$theirs['start']}–{$theirs['end']} overlaps {$mine['subject_code']}";
                            }
                        }
                    }

                    $conflictsBySection[$sectionId] = $hits;
                }

                $offerings[] = [
                    'offering_id'      => $offeringId,
                    'label'            => $code . ' · Offering #' . $offeringId,
                    'capacity'         => $capacity,
                    'roster_count'     => $roster,
                    'available_seats'  => $capacity !== null ? $capacity - $roster : null,
                    'is_full'          => $capacity !== null && $roster >= $capacity,
                    'sections'         => array_column($sectionsByOffering[$offeringId] ?? [], 'label'),
                    'programs'         => array_values(array_unique(array_column($sectionsByOffering[$offeringId] ?? [], 'program'))),
                    'meetings'         => $meetings,
                    'is_scheduled'     => $meetings !== [],
                    'conflicts_by_section' => $conflictsBySection,
                ];
            }

            $subjectEntries[] = [
                'subject_id'    => $subjectId,
                'subject_code'  => $code,
                'subject_name'  => $subjectMeta[$subjectId]['subject_name'],
                'owner_name'    => $ownerNames[$subjectMeta[$subjectId]['owner_sub_dept_id']] ?? 'Unknown unit',
                'my_expected'   => $myExpectedTotal,
                'my_assigned'   => $myAssignedTotal,
                'my_unassigned' => $myUnassignedTotal,
                'coverage'      => $coverage,
                'sections'      => $sectionEntries,
                'offerings'     => $offerings,
                'limitations'   => $limitations,
            ];
        }

        return ['term' => $academicYear, 'subjects' => $subjectEntries, 'my_section_count' => count($mySectionIds)];
    }
}

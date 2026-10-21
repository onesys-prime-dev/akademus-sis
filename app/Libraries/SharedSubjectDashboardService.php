<?php

namespace App\Libraries;

use App\Models\AcademicYearModel;
use App\Models\CourseOfferingModel;
use App\Models\OfferingComponentModel;

/**
 * Unifies Steps 1-5 into one operational view for the scheduler who OWNS a
 * subject: current demand, pending/approved placement requests, and every
 * current-term offering's capacity/sections/schedule. Pure orchestration —
 * every number here is computed by an existing service/model; nothing new
 * is calculated here beyond grouping/shaping for display.
 *
 * Performance: a fixed, small number of queries regardless of how many
 * subjects or students exist (see each query below) — never one query per
 * subject, never one query per student.
 */
class SharedSubjectDashboardService
{
    /**
     * @return array{term: string, subjects: list<array>, inactive_count: int}
     */
    public function build(int $userId, string $academicYear, bool $includeInactive = false): array
    {
        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds($userId);

        if ($subDeptIds === []) {
            return ['term' => $academicYear, 'subjects' => [], 'inactive_count' => 0];
        }

        $db = db_connect();

        // 1: authorized subjects.
        $subjects = $db->table('subjects')
            ->select('subject_id, subject_code, subject_name')
            ->whereIn('sub_department_id', $subDeptIds)
            ->where('is_active', 1)
            ->orderBy('subject_code')
            ->get()
            ->getResultArray();

        if ($subjects === []) {
            return ['term' => $academicYear, 'subjects' => [], 'inactive_count' => 0];
        }

        $subjectIds = array_map(static fn (array $s) => (int) $s['subject_id'], $subjects);

        // 2: demand + request aggregate, batched (SubjectDemandService owns this).
        $demandBySubject = (new SubjectDemandService())->summaryForSubjects($subjectIds, $academicYear);

        // 3: offerings for these subjects, this term.
        $offeringRows = $db->table('course_offerings')
            ->whereIn('subject_id', $subjectIds)
            ->where('academic_year', $academicYear)
            ->get()
            ->getResultArray();

        $offeringIds      = array_map(static fn (array $o) => (int) $o['offering_id'], $offeringRows);
        $offeringsBySubject = [];

        foreach ($offeringRows as $o) {
            $offeringsBySubject[(int) $o['subject_id']][] = $o;
        }

        // 4: roster counts, batched.
        $rosterCounts = (new CourseOfferingModel())->rosterCountsFor($offeringIds);

        // 4b: which offerings have Step 9 components (batched) — for those,
        // "scheduled" is decided by OfferingComponentModel::completeness(),
        // never by the mere existence of plotted_schedules rows. Simple
        // (component-less) offerings keep exact Step 6 behavior below.
        $componentEnabledOfferingIds = $offeringIds === [] ? [] : array_map('intval', array_column(
            $db->table('offering_components')->distinct()->select('offering_id')->whereIn('offering_id', $offeringIds)->get()->getResultArray(),
            'offering_id'
        ));

        $completenessByOffering = [];
        $componentModel = new OfferingComponentModel();

        foreach ($componentEnabledOfferingIds as $cid) {
            $completenessByOffering[$cid] = $componentModel->completeness($cid);
        }

        // 5: attached sections (+ programs), batched.
        $sectionsByOffering = [];

        if ($offeringIds !== []) {
            $sectionRows = $db->table('offering_sections os')
                ->join('sections sec', 'sec.section_id = os.section_id')
                ->whereIn('os.offering_id', $offeringIds)
                ->select('os.offering_id, sec.program, sec.year_level, sec.section')
                ->get()
                ->getResultArray();

            foreach ($sectionRows as $r) {
                $sectionsByOffering[(int) $r['offering_id']][] = [
                    'label'   => "{$r['program']} · {$r['year_level']} · {$r['section']}",
                    'program' => $r['program'],
                ];
            }
        }

        // 6: schedules, batched. "No faculty" is detected the same way the
        // app itself writes it (sync()/syncOfferingSchedule() always store
        // either a real username or NULL — never '' — but the check also
        // tolerates blank/whitespace defensively without redesigning how
        // faculty is stored).
        $meetingsByOffering = [];

        if ($offeringIds !== []) {
            $scheduleRows = $db->table('plotted_schedules')
                ->whereIn('offering_id', $offeringIds)
                ->get()
                ->getResultArray();

            foreach ($scheduleRows as $r) {
                $meetingsByOffering[(int) $r['offering_id']][] = [
                    'day_label'  => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][(int) $r['day']] ?? '?',
                    'start'      => substr($r['start_time'], 0, 5),
                    'end'        => substr($r['end_time'], 0, 5),
                    'room'       => $r['room'],
                    'faculty'    => $r['prof'],
                    'no_faculty' => $r['prof'] === null || trim((string) $r['prof']) === '',
                ];
            }
        }

        // 7: request-type breakdown (pending only), batched — more granular
        // than SubjectDemandService's own pending/approved aggregate, purely
        // for display ("Placement: 2, Additional Offering: 1").
        $requestTypeCounts = [];

        $typeRows = $db->table('offering_placement_requests opr')
            ->join('sections sec', 'sec.section_id = opr.section_id')
            ->whereIn('opr.subject_id', $subjectIds)
            ->where('sec.academic_year', AcademicYearModel::yearOnly($academicYear))
            ->where('opr.status', 'pending')
            ->select('opr.subject_id, opr.request_type, COUNT(*) AS cnt')
            ->groupBy('opr.subject_id, opr.request_type')
            ->get()
            ->getResultArray();

        foreach ($typeRows as $r) {
            $requestTypeCounts[(int) $r['subject_id']][$r['request_type']] = (int) $r['cnt'];
        }

        $subjectEntries = [];
        $inactiveCount  = 0;

        foreach ($subjects as $subject) {
            $subjectId = (int) $subject['subject_id'];
            $demand    = $demandBySubject[$subjectId] ?? null;

            $offerings = [];

            foreach ($offeringsBySubject[$subjectId] ?? [] as $o) {
                $offeringId = (int) $o['offering_id'];
                $roster     = $rosterCounts[$offeringId] ?? 0;
                $sections   = $sectionsByOffering[$offeringId] ?? [];

                // An offering with no section attached and nobody rostered
                // to it is an empty shell — created (e.g. mid-setup, or by a
                // since-abandoned flow) but never actually tied to any real
                // class. Showing it would flag a false "needs schedule" for
                // something nobody is waiting on; the subject-level
                // unassigned_demand attention already covers "this subject
                // has real demand with nowhere to go" without it.
                if ($sections === [] && $roster <= 0) {
                    continue;
                }

                $capacity   = $o['capacity'] !== null ? (int) $o['capacity'] : null;
                $overBy     = $capacity !== null && $roster > $capacity ? $roster - $capacity : 0;
                // Never silently clamped to 0 — a negative value here IS
                // the "how far over capacity" signal; over_capacity_by
                // mirrors it for display convenience only.
                $meetings   = $meetingsByOffering[$offeringId] ?? [];
                $programs   = array_values(array_unique(array_column($sections, 'program')));

                $completeness = $completenessByOffering[$offeringId] ?? null;
                // Component-enabled offering: scheduled/complete comes ONLY
                // from the centralized completeness contract — meetings
                // existing is not sufficient (e.g. Lecture complete, one
                // required Lab instance unscheduled must still read
                // incomplete). Simple offering: unchanged Step 6 behavior.
                $isScheduled = $completeness !== null ? $completeness['complete'] : $meetings !== [];

                $offerings[] = [
                    'offering_id'    => $offeringId,
                    'label'          => $subject['subject_code'] . ' · Offering #' . $offeringId,
                    'capacity'       => $capacity,
                    'roster_count'   => $roster,
                    'available_seats' => $capacity !== null ? $capacity - $roster : null,
                    'over_capacity_by' => $overBy,
                    'sections'       => array_column($sections, 'label'),
                    'programs'       => $programs,
                    'meetings'       => $meetings,
                    'is_scheduled'   => $isScheduled,
                    'has_no_faculty' => $meetings !== [] && array_reduce($meetings, static fn ($carry, $m) => $carry || $m['no_faculty'], false),
                    'components'     => $completeness,
                ];
            }

            $pendingByType = $requestTypeCounts[$subjectId] ?? [];
            $pendingTotal  = array_sum($pendingByType);
            $approvedNotFulfilled = $demand['placement_requests']['approved_not_fulfilled'] ?? 0;

            $hasDemandActivity = ($demand['expected_count'] ?? 0) > 0
                || ($demand['assigned_count'] ?? 0) > 0
                || ($demand['unassigned_count'] ?? 0) > 0;

            $hasOfferingActivity = $offerings !== [];
            $hasRequestActivity  = $pendingTotal > 0 || $approvedNotFulfilled > 0;

            $isActive = $hasDemandActivity || $hasOfferingActivity || $hasRequestActivity;

            if (! $isActive) {
                $inactiveCount++;

                if (! $includeInactive) {
                    continue;
                }
            }

            $attention = [];

            if (($demand['unassigned_count'] ?? 0) > 0) {
                $attention[] = 'unassigned_demand';
            }
            if ($pendingTotal > 0) {
                $attention[] = 'pending_requests';
            }
            if ($approvedNotFulfilled > 0) {
                $attention[] = 'approved_awaiting_fulfillment';
            }
            foreach ($offerings as $o) {
                if ($o['over_capacity_by'] > 0) {
                    $attention[] = 'over_capacity';
                }
                if (! $o['is_scheduled']) {
                    $attention[] = 'needs_schedule';
                }
                if ($o['has_no_faculty']) {
                    $attention[] = 'no_faculty';
                }
            }

            $subjectEntries[] = [
                'subject_id'   => $subjectId,
                'subject_code' => $subject['subject_code'],
                'subject_name' => $subject['subject_name'],
                'demand'       => $demand,
                'requests'     => [
                    'pending_by_type'        => $pendingByType,
                    'pending_total'          => $pendingTotal,
                    'approved_not_fulfilled' => $approvedNotFulfilled,
                ],
                'offerings'  => $offerings,
                'attention'  => array_values(array_unique($attention)),
                'is_active'  => $isActive,
            ];
        }

        return ['term' => $academicYear, 'subjects' => $subjectEntries, 'inactive_count' => $inactiveCount];
    }
}

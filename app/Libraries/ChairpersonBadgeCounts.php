<?php

namespace App\Libraries;

use App\Models\AcademicYearModel;
use App\Models\DepartmentModel;
use App\Models\GradeChangeRequestModel;
use App\Models\GradeModel;
use App\Models\StudentEnlistmentModel;

/**
 * Live "needs your action" counts for every chairperson sidebar item that
 * has one — computed here once so the nav badges (chairperson/_nav.php,
 * required by every chairperson view) and the Dashboard's "Attention
 * Needed" panel (ChairpersonController::overview()) read the same numbers
 * instead of two independent reimplementations silently drifting apart.
 */
class ChairpersonBadgeCounts
{
    /** @return array<string, int> keyed by the same key chairperson/_nav.php uses for that item */
    public function forUser(int $userId): array
    {
        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds($userId);

        if ($subDeptIds === []) {
            return [
                'schedule'              => 0,
                'grade-approvals'       => 0,
                'grade-change-requests' => 0,
                'enlistment-approvals'  => 0,
                'placement-requests'    => 0,
                'shared-subjects'       => 0,
            ];
        }

        $deptModel = new DepartmentModel();
        $programs  = [];

        foreach ($subDeptIds as $id) {
            $programs = array_merge($programs, $deptModel->programsOfSubDept($id));
        }

        $programs = array_values(array_unique($programs));

        return [
            'schedule'              => $this->swapApprovalsCount($subDeptIds),
            'grade-approvals'       => count((new GradeModel())->submittedClasses($programs)),
            'grade-change-requests' => count((new GradeChangeRequestModel())->pendingChairRecommendation($programs)),
            'enlistment-approvals'  => count((new StudentEnlistmentModel())->pendingChairRecommendation($programs)),
            'placement-requests'    => $this->placementRequestsCount($subDeptIds),
            'shared-subjects'       => $this->needsPlottingCount($subDeptIds),
        ];
    }

    /**
     * Mirrors ChairpersonController::overview()'s own $needsPlottingCount —
     * classes (any department's section) that need one of this
     * chairperson's owned subjects plotted, computed fresh from current
     * demand (SubjectDemandService), never cached. See Shared Subject
     * Dashboard / Merged Classes -> "Needs Plotting" to act on them.
     *
     * "Plotted" is decided against plotted_schedules — the table the actual
     * Schedule Plotting grid writes to (ChairpersonController::index()/
     * sync(), matched the same way via PlottedScheduleModel::forContext():
     * academic_year + program + year_level + section + subject_code) — not
     * against course_offerings/offering_sections. That pair backs the
     * separate offering-based Shared Subject Dashboard/Merged Classes model
     * and is never populated by plotting a class, so checking it here left
     * this count stuck at its original value forever, no matter how much a
     * chairperson actually plotted.
     */
    private function needsPlottingCount(array $subDeptIds): int
    {
        $activeYearRow = (new AcademicYearModel())->where('is_active', 1)->first();
        $activeYear    = $activeYearRow === null ? null : AcademicYearModel::format($activeYearRow['label'], $activeYearRow['semester']);

        if ($activeYear === null) {
            return 0;
        }

        $ownedSubjects = db_connect()->table('subjects')
            ->select('subject_id, subject_code')
            ->whereIn('sub_department_id', $subDeptIds)
            ->where('is_active', 1)
            ->get()
            ->getResultArray();

        if ($ownedSubjects === []) {
            return 0;
        }

        $demandService = new SubjectDemandService();
        $db            = db_connect();

        // Every (subject_code, section_id) pair with unmet demand, across
        // every owned subject — gathered first so sections/plotted_schedules
        // are each queried once for the whole batch, not once per pair.
        $pairs      = [];
        $sectionIds = [];

        foreach ($ownedSubjects as $subject) {
            $summary = $demandService->summary((int) $subject['subject_id'], $activeYear);

            // Every expected student already has a real seat somewhere
            // (direct enrollment, a merged class, etc.) — nothing left to
            // plot for them. Without this gate a fully-served subject
            // falsely counts as "needs plotting" forever.
            if (($summary['unassigned_count'] ?? 0) <= 0) {
                continue;
            }

            foreach ($summary['by_section'] as $bySection) {
                if ($bySection['expected_count'] <= 0 || $bySection['section_id'] === null) {
                    continue;
                }

                $sectionId    = (int) $bySection['section_id'];
                $pairs[]      = ['subject_code' => $subject['subject_code'], 'section_id' => $sectionId];
                $sectionIds[] = $sectionId;
            }
        }

        if ($pairs === []) {
            return 0;
        }

        $sectionById = [];

        foreach ($db->table('sections')->select('section_id, program, year_level, section')->whereIn('section_id', array_values(array_unique($sectionIds)))->get()->getResultArray() as $r) {
            $sectionById[(int) $r['section_id']] = $r;
        }

        $subjectCodes = array_values(array_unique(array_column($pairs, 'subject_code')));

        $plottedKeys = [];

        foreach ($db->table('plotted_schedules')->select('subject_code, program, year_level, section')->where('academic_year', $activeYear)->whereIn('subject_code', $subjectCodes)->get()->getResultArray() as $r) {
            $plottedKeys[$r['subject_code'] . '|' . $r['program'] . '|' . $r['year_level'] . '|' . $r['section']] = true;
        }

        $count = 0;

        foreach ($pairs as $pair) {
            $section = $sectionById[$pair['section_id']] ?? null;

            if ($section === null) {
                $count++;

                continue;
            }

            $key = $pair['subject_code'] . '|' . $section['program'] . '|' . $section['year_level'] . '|' . $section['section'];

            if (! isset($plottedKeys[$key])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Mirrors ChairpersonController::swapApprovals()'s own filter — approval
     * authority follows SUBJECT ownership, not the class's enrolled program
     * — as a plain count; that method also joins in requester/target
     * usernames and builds display labels a badge doesn't need.
     */
    private function swapApprovalsCount(array $subDeptIds): int
    {
        return db_connect()->table('swap_requests sr')
            ->join('faculty_assignments fa1', 'fa1.assignment_id = sr.assignment_id')
            ->join('subjects su1', 'su1.subject_code = fa1.subject_code', 'left')
            ->where('sr.status', 'accepted')
            ->whereIn('su1.sub_department_id', $subDeptIds)
            ->countAllResults();
    }

    /** Mirrors Chairperson\PlacementRequestController::index()'s $toReview filter. */
    private function placementRequestsCount(array $subDeptIds): int
    {
        return db_connect()->table('offering_placement_requests opr')
            ->join('subjects s', 's.subject_id = opr.subject_id', 'left')
            ->whereIn('s.sub_department_id', $subDeptIds)
            ->where('opr.status', 'pending')
            ->countAllResults();
    }
}

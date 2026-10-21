<?php

namespace App\Libraries;

use App\Models\AcademicYearModel;
use App\Models\CourseOfferingModel;
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
        $offeringModel = new CourseOfferingModel();
        $count         = 0;

        foreach ($ownedSubjects as $subject) {
            $summary = $demandService->summary((int) $subject['subject_id'], $activeYear);

            // Every expected student already has a real seat somewhere
            // (direct enrollment, a merged class, etc.) — "no course_offerings
            // row exists yet" is just missing Step-6 bookkeeping at that
            // point, not an unmet need. Without this gate a fully-served
            // subject falsely counts as "needs plotting" forever, since
            // nothing ever prompts anyone to create that offering row.
            if (($summary['unassigned_count'] ?? 0) <= 0) {
                continue;
            }

            foreach ($summary['by_section'] as $bySection) {
                if ($bySection['expected_count'] <= 0 || $bySection['section_id'] === null) {
                    continue;
                }

                if ($offeringModel->offeringForSection((int) $bySection['section_id'], $subject['subject_code']) === null) {
                    $count++;
                }
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

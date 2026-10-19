<?php

namespace App\Libraries;

/**
 * Shell settings (sidebar, brand, role label) for every Registrar page, so the
 * dashboard, student records and profile all share one navigation definition
 * instead of each view repeating it — the layout falls back to the admin
 * console's navigation when none is passed.
 */
class RegistrarPortal
{
    /**
     * @return array<string, mixed> view data to merge into a page's data
     */
    public static function shell(string $active): array
    {
        return [
            'activeAdmin'   => $active,
            'basePath'      => 'registrar',
            'brandSub'      => 'Registrar Portal',
            'userName'      => auth()->user()->username ?? 'Registrar',
            'userRoleLabel' => 'Registrar',
            'navGroups'     => [
                '' => [
                    'dashboard' => ['label' => 'Dashboard',       'icon' => 'dashboard', 'url' => 'registrar/dashboard', 'help' => [
                        'title' => 'Registrar Dashboard',
                        'text'  => "Student records and enrollment at a glance: total/active students, recent enrollment and status changes, and students flagged with no curriculum assigned yet.",
                    ]],
                    'grade-approvals' => ['label' => 'Grade Approvals', 'icon' => 'grade', 'url' => 'registrar/grade-approvals', 'help' => [
                        'title' => 'Grade Approvals',
                        'text'  => "The final step in the grade chain: classes noted by the dean land here for your approval, then locking — which makes grades official and visible to students. Locked grades can only change through an approved correction request.",
                    ]],
                    'grade-change-requests' => ['label' => 'Correction Requests', 'icon' => 'history_edu', 'url' => 'registrar/grade-change-requests', 'help' => [
                        'title' => 'Correction Requests',
                        'text'  => "Requests to change an already-locked grade, after being recommended by the chairperson and dean. Your approval is the final, binding step — it's the only action that actually mutates the official grade.",
                    ]],
                    'academic-calendar' => ['label' => 'Academic Calendar', 'icon' => 'event', 'url' => 'registrar/academic-calendar', 'help' => [
                        'title' => 'Academic Calendar',
                        'text'  => "Set the active academic year/term and the enrollment, grading, and enlistment windows that gate what students and faculty can do system-wide.",
                    ]],
                    'enlistments' => ['label' => 'Enlistment Approvals', 'icon' => 'playlist_add_check', 'url' => 'registrar/enlistments', 'help' => [
                        'title' => 'Enlistment Approvals',
                        'text'  => "The final stage of the enlistment chain: enlistments noted by the dean land here. Approving converts it into real enrollments (recomputing tuition automatically); returning sends it back to the student with your reason.",
                    ]],
                    'clearance' => ['label' => 'Graduation Clearance', 'icon' => 'checklist', 'url' => 'registrar/clearance', 'help' => [
                        'title' => 'Graduation Clearance',
                        'text'  => "Track and finalize a graduating student's clearance checklist across departments (Finance, Library, etc.) — finalizing here is what actually marks them graduated.",
                    ]],
                    'documents' => ['label' => 'Document Requests', 'icon' => 'description', 'url' => 'registrar/documents', 'help' => [
                        'title' => 'Document Requests',
                        'text'  => "Generate and release official documents (TOR, COR, certificates) — either from a student's self-submitted request, or ad hoc via the search tool for a walk-in request.",
                    ]],
                    'students'  => ['label' => 'Student Records', 'icon' => 'school',    'url' => 'registrar/students', 'help' => [
                        'title' => 'Student Records',
                        'text'  => "Search and manage every student's record: enrollment status, curriculum assignment, grades, and tuition — the registrar's full administrative view of a student.",
                    ]],
                    'student-progress' => ['label' => 'Student Progress', 'icon' => 'school', 'url' => 'registrar/student-progress', 'help' => [
                        'title' => 'Student Progress',
                        'text'  => "Curriculum completion and deficiencies across every program. Grant an enlistment override here to clear a specific student to enlist in a subject they're otherwise blocked from (e.g. an irregular/catch-up case) — audit-logged, with the student notified.",
                    ]],
                ],
            ],
        ];
    }
}

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
                    'dashboard' => ['label' => 'Dashboard',       'icon' => 'dashboard', 'url' => 'registrar/dashboard'],
                    'grade-approvals' => ['label' => 'Grade Approvals', 'icon' => 'grade', 'url' => 'registrar/grade-approvals'],
                    'grade-change-requests' => ['label' => 'Correction Requests', 'icon' => 'history_edu', 'url' => 'registrar/grade-change-requests'],
                    'academic-calendar' => ['label' => 'Academic Calendar', 'icon' => 'event', 'url' => 'registrar/academic-calendar'],
                    'enlistments' => ['label' => 'Enlistment Approvals', 'icon' => 'playlist_add_check', 'url' => 'registrar/enlistments'],
                    'clearance' => ['label' => 'Graduation Clearance', 'icon' => 'checklist', 'url' => 'registrar/clearance'],
                    'documents' => ['label' => 'Document Requests', 'icon' => 'description', 'url' => 'registrar/documents'],
                    'students'  => ['label' => 'Student Records', 'icon' => 'school',    'url' => 'registrar/students'],
                ],
            ],
        ];
    }
}

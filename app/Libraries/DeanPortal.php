<?php

namespace App\Libraries;

/**
 * Shell settings (sidebar, brand, role label) for every Dean page — mirrors
 * RegistrarPortal exactly, one level up the hierarchy.
 */
class DeanPortal
{
    /**
     * @return array<string, mixed> view data to merge into a page's data
     */
    public static function shell(string $active): array
    {
        return [
            'activeAdmin'   => $active,
            'basePath'      => 'dean',
            'brandSub'      => 'Dean Portal',
            'userName'      => auth()->user()->username ?? 'Dean',
            'userRoleLabel' => 'Dean',
            'navGroups'     => [
                '' => [
                    'dashboard'       => ['label' => 'Dashboard',       'icon' => 'dashboard', 'url' => 'dean/dashboard', 'help' => [
                        'title' => 'Dean Dashboard',
                        'text'  => "An overview of your college: classes awaiting your note, and enlistments awaiting your sign-off, across every department under you.",
                    ]],
                    'grade-approvals' => ['label' => 'Grade Approvals', 'icon' => 'grade',     'url' => 'dean/grade-approvals', 'help' => [
                        'title' => 'Grade Approvals',
                        'text'  => "Classes recommended by their chairperson land here for your note, before moving on to the registrar for final approval and locking.",
                    ]],
                    'grade-change-requests' => ['label' => 'Correction Requests', 'icon' => 'history_edu', 'url' => 'dean/grade-change-requests', 'help' => [
                        'title' => 'Correction Requests',
                        'text'  => "Requests to change an already-locked grade, after the chairperson has recommended them. Your recommendation sends it on to the registrar for the final, binding decision.",
                    ]],
                    'enlistment-approvals' => ['label' => 'Enlistment Approvals', 'icon' => 'playlist_add_check', 'url' => 'dean/enlistment-approvals', 'help' => [
                        'title' => 'Enlistment Approvals',
                        'text'  => "Enlistments recommended by the chairperson land here for your note, before the registrar finalizes them into real enrollments.",
                    ]],
                ],
            ],
        ];
    }
}

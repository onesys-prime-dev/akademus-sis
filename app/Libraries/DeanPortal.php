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
                    'dashboard'       => ['label' => 'Dashboard',       'icon' => 'dashboard', 'url' => 'dean/dashboard'],
                    'grade-approvals' => ['label' => 'Grade Approvals', 'icon' => 'grade',     'url' => 'dean/grade-approvals'],
                    'grade-change-requests' => ['label' => 'Correction Requests', 'icon' => 'history_edu', 'url' => 'dean/grade-change-requests'],
                    'enlistment-approvals' => ['label' => 'Enlistment Approvals', 'icon' => 'playlist_add_check', 'url' => 'dean/enlistment-approvals'],
                    'profile'         => ['label' => 'Profile',         'icon' => 'person',    'url' => 'dean/profile'],
                ],
            ],
        ];
    }
}

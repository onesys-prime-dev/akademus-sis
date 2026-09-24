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
                    'students'  => ['label' => 'Student Records', 'icon' => 'school',    'url' => 'registrar/students'],
                    'profile'   => ['label' => 'Profile',         'icon' => 'person',    'url' => 'registrar/profile'],
                ],
            ],
        ];
    }
}

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
                    'tuition-rates' => ['label' => 'Tuition Rates', 'icon' => 'payments', 'url' => 'registrar/tuition-rates'],
                    'fee-types'     => ['label' => 'Fee Types', 'icon' => 'sell', 'url' => 'registrar/fee-types'],
                    'payment-proofs' => ['label' => 'Payment Proofs', 'icon' => 'receipt_long', 'url' => 'registrar/payment-proofs'],
                    'profile'   => ['label' => 'Profile',         'icon' => 'person',    'url' => 'registrar/profile'],
                ],
            ],
        ];
    }
}

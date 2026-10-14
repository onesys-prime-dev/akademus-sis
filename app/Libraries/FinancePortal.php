<?php

namespace App\Libraries;

/**
 * Shell settings (sidebar, brand, role label) for every Finance page —
 * mirrors RegistrarPortal/DeanPortal exactly.
 */
class FinancePortal
{
    /**
     * @return array<string, mixed> view data to merge into a page's data
     */
    public static function shell(string $active): array
    {
        return [
            'activeAdmin'   => $active,
            'basePath'      => 'finance',
            'brandSub'      => 'Finance Portal',
            'userName'      => auth()->user()->username ?? 'Finance',
            'userRoleLabel' => 'Finance',
            'navGroups'     => [
                '' => [
                    'dashboard'       => ['label' => 'Dashboard',          'icon' => 'dashboard',      'url' => 'finance/dashboard'],
                    'cashier'         => ['label' => 'Cashier',            'icon' => 'point_of_sale',  'url' => 'finance/cashier'],
                    'payment-proofs'  => ['label' => 'Payment Proofs',     'icon' => 'receipt_long',   'url' => 'finance/payment-proofs'],
                    'holds'           => ['label' => 'Financial Holds',    'icon' => 'block',          'url' => 'finance/holds'],
                    'discounts'       => ['label' => 'Discounts & Scholarships', 'icon' => 'redeem',   'url' => 'finance/discounts'],
                    'clearance'       => ['label' => 'Graduation Clearance', 'icon' => 'checklist',    'url' => 'finance/clearance'],
                    'reports'         => ['label' => 'Reports',            'icon' => 'bar_chart',      'url' => 'finance/reports'],
                    'tuition-rates'   => ['label' => 'Tuition Rates',      'icon' => 'payments',       'url' => 'finance/tuition-rates'],
                    'fee-types'       => ['label' => 'Fee Types',         'icon' => 'sell',            'url' => 'finance/fee-types'],
                ],
            ],
        ];
    }
}

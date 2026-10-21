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
                    'dashboard'       => ['label' => 'Dashboard',          'icon' => 'dashboard',      'url' => 'finance/dashboard', 'help' => [
                        'title' => 'Finance Dashboard',
                        'text'  => "An overview of tuition collection: outstanding balances, pending payment proofs, and active holds across the student body. Use Cashier to record a payment directly, or Payment Proofs to review what students have submitted for approval.",
                    ]],
                    'cashier'         => ['label' => 'Cashier',            'icon' => 'point_of_sale',  'url' => 'finance/cashier', 'help' => [
                        'title' => 'Cashier',
                        'text'  => "Look up a student and record a cash/over-the-counter payment directly against their current assessment. Recording a payment here issues a real Official Receipt immediately and notifies the student.",
                    ]],
                    'payment-proofs'  => ['label' => 'Payment Proofs',     'icon' => 'receipt_long',   'url' => 'finance/payment-proofs', 'help' => [
                        'title' => 'Payment Proofs',
                        'text'  => "Students can upload proof of an off-site payment (bank deposit, online transfer) for your review. Approving one here records it as a real payment on their ledger, exactly like a Cashier transaction; rejecting it asks them to resubmit.",
                    ]],
                    'holds'           => ['label' => 'Financial Holds',    'icon' => 'block',          'url' => 'finance/holds', 'help' => [
                        'title' => 'Financial Holds',
                        'text'  => "Place a hold to block a student from enrolling, dropping a class, or requesting documents until a financial issue is resolved. The student is notified the moment a hold is placed or released — there's no automatic/cron-based holding in this system, it's always a deliberate staff action.",
                    ]],
                    'discounts'       => ['label' => 'Discounts & Scholarships', 'icon' => 'redeem',   'url' => 'finance/discounts', 'help' => [
                        'title' => 'Discounts & Scholarships',
                        'text'  => "Define discount/scholarship types (percentage or fixed amount) in the catalog, then grant them to individual students. A grant is applied to the student's balance immediately, and they're notified when one is granted or revoked.",
                    ]],
                    'additional-fees' => ['label' => 'Additional Fees',     'icon' => 'add_card', 'url' => 'finance/additional-fees', 'help' => [
                        'title' => 'Additional Fees',
                        'text'  => "One-off charges outside the regular tuition/miscellaneous structure — campus event fees, penalties, surcharges. Charge one student from a search, or apply the same fee to an entire program/year level/section at once from Bulk Apply. Applied to balances immediately; students are notified either way.",
                    ]],
                    'clearance'       => ['label' => 'Graduation Clearance', 'icon' => 'checklist',    'url' => 'finance/clearance', 'help' => [
                        'title' => 'Graduation Clearance',
                        'text'  => "Finance's sign-off on a graduating student's clearance checklist — confirming they have no outstanding financial obligations before the registrar can finalize their graduation.",
                    ]],
                    'reports'         => ['label' => 'Reports',            'icon' => 'bar_chart',      'url' => 'finance/reports', 'help' => [
                        'title' => 'Reports',
                        'text'  => "Collection and balance summaries across terms and programs — for reconciling cashier activity and spotting students with significant outstanding balances.",
                    ]],
                    'tuition-rates'   => ['label' => 'Tuition Rates',      'icon' => 'payments',       'url' => 'finance/tuition-rates', 'help' => [
                        'title' => 'Tuition Rates',
                        'text'  => "The per-unit tuition rate for each program/academic year, used to compute every student's assessment automatically on enrollment. A program with no rate set here will show as \"unassessed\" until one is added.",
                    ]],
                    'fee-types'       => ['label' => 'Fee Types',         'icon' => 'sell',            'url' => 'finance/fee-types', 'help' => [
                        'title' => 'Fee Types',
                        'text'  => "The itemized fee lines (Miscellaneous, Laboratory, etc.) added on top of per-unit tuition in a student's assessment. Priced per program/year via Tuition Rates.",
                    ]],
                    'settings'        => ['label' => 'Settings',         'icon' => 'tune',            'url' => 'finance/settings', 'help' => [
                        'title' => 'Finance Settings',
                        'text'  => "Policies that affect what students can do online while they still owe a balance — e.g. whether an outstanding balance blocks online enlistment.",
                    ]],
                ],
            ],
        ];
    }
}

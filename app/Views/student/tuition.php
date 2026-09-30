<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'tuition';
$pageTitle     = 'Tuition & Fees';
$brandSub      = 'Student Portal';
$userName      = auth()->user()->username;
$userRoleLabel = 'Student';
$navGroups     = [
    '' => [
        'dashboard' => ['label' => 'Dashboard',   'icon' => 'home',           'url' => 'student/dashboard'],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule'],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades'],
        'tuition'   => ['label' => 'Tuition & Fees', 'icon' => 'payments',    'url' => 'student/tuition'],
        'profile'   => ['label' => 'Profile',     'icon' => 'person',         'url' => 'student/profile'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

$current = $assessments[0] ?? null;
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Tuition & Fees</h2></div>
</div>

<div class="panel">
    <?php if ($current === null): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">payments</div>
            <p>No tuition assessment yet. This appears once you're enrolled and your program's rates are set.</p>
        </div>
    <?php elseif ($current['status'] === 'unassessed'): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">payments</div>
            <p>Your program doesn't have tuition rates set for <?= esc($current['academic_year']) ?> yet. Contact the Registrar's Office.</p>
        </div>
    <?php else: ?>
        <h2><?= esc($current['academic_year']) ?></h2>
        <table class="grade-table">
            <tbody>
                <tr><td>Total units enrolled</td><td><?= esc((string) $current['total_units']) ?></td></tr>
                <tr><td>Tuition</td><td><?= number_format((float) $current['tuition_amount'], 2) ?></td></tr>
                <tr><td>Miscellaneous fees</td><td><?= number_format((float) $current['misc_fee_amount'], 2) ?></td></tr>
                <tr><td><strong>Total assessment</strong></td><td><strong><?= number_format((float) $current['total_amount'], 2) ?></strong></td></tr>
                <tr><td>Amount paid</td><td><?= number_format((float) $current['amount_paid'], 2) ?></td></tr>
                <tr>
                    <td><strong>Balance</strong></td>
                    <td>
                        <strong><?= number_format((float) $current['balance'], 2) ?></strong>
                        <?php if ($current['status'] === 'paid'): ?>
                            <span class="pass-pill pass">Paid</span>
                        <?php elseif ($current['status'] === 'partial'): ?>
                            <span class="pass-pill">Partial</span>
                        <?php else: ?>
                            <span class="pass-pill fail">Unpaid</span>
                        <?php endif ?>
                    </td>
                </tr>
            </tbody>
        </table>
    <?php endif ?>
</div>

<div class="panel">
    <h2>Payment History</h2>
    <?php if (empty($payments)): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">receipt_long</div>
            <p>No payments recorded yet.</p>
        </div>
    <?php else: ?>
        <table class="grade-table">
            <thead>
                <tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th></tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= esc(date('M j, Y', strtotime($p['payment_date']))) ?></td>
                        <td><?= number_format((float) $p['amount'], 2) ?></td>
                        <td><?= esc($p['method']) ?></td>
                        <td><?= $p['reference_no'] !== null ? esc($p['reference_no']) : '—' ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<?php if (count($assessments) > 1): ?>
<div class="panel">
    <h2>Previous Terms</h2>
    <table class="grade-table">
        <thead>
            <tr><th>Academic Year</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($assessments, 1) as $a): ?>
                <tr>
                    <td><?= esc($a['academic_year']) ?></td>
                    <td><?= number_format((float) $a['total_amount'], 2) ?></td>
                    <td><?= number_format((float) $a['amount_paid'], 2) ?></td>
                    <td><?= number_format((float) $a['balance'], 2) ?></td>
                    <td><?= esc(ucfirst($a['status'])) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>

<?= $this->endSection() ?>

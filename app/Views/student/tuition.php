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
        'profile'   => ['label' => 'Profile',     'icon' => 'person',         'url' => 'student/profile'],
        'tuition'   => ['label' => 'Tuition & Fees', 'icon' => 'payments',    'url' => 'student/tuition'],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule'],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

$current = $assessments[0] ?? null;

// Tuition-first waterfall for the two-bucket Balances split: payments apply
// to the tuition line before the itemized/added fees.
$addedFeesTotal = 0.0;
foreach ($itemsByCategory as $category => $rows) {
    foreach ($rows as $row) {
        $addedFeesTotal += (float) $row['amount'];
    }
}

$tuitionAmount   = (float) ($current['tuition_amount'] ?? 0);
$amountPaid      = (float) ($current['amount_paid'] ?? 0);
$tuitionPaid     = min($tuitionAmount, $amountPaid);
$addedFeesPaid   = min($addedFeesTotal, max(0, $amountPaid - $tuitionAmount));
$tuitionBalance  = max(0, $tuitionAmount - $tuitionPaid);
$addedFeesBalance = max(0, $addedFeesTotal - $addedFeesPaid);
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Tuition & Fees</h2></div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<?php if (session()->has('errors')): ?>
    <div class="ac-notice" style="margin-bottom:16px; border-color:var(--color-danger,#dc2626);">
        <?php foreach ((array) session('errors') as $error): ?>
            <span class="ac-notice__text"><?= esc($error) ?></span><br>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?php if ($current === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">payments</div>
            <p>No tuition assessment yet. This appears once you're enrolled and your program's rates are set.</p>
        </div>
    </div>
<?php elseif ($current['status'] === 'unassessed'): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">payments</div>
            <p>Your program doesn't have tuition rates set for <?= esc($current['academic_year']) ?> yet. Contact the Registrar's Office.</p>
        </div>
    </div>
<?php else: ?>

<div class="panel">
    <h2><?= esc($current['academic_year']) ?></h2>
    <table class="grade-table">
        <tbody>
            <tr><td>Tuition Fee Balance</td><td><?= number_format($tuitionBalance, 2) ?></td></tr>
            <tr><td>Added Fees Balance</td><td><?= number_format($addedFeesBalance, 2) ?></td></tr>
            <tr>
                <td><strong>Total Balance</strong></td>
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
</div>

<div class="panel">
    <h2>Assessment</h2>
    <table class="grade-table">
        <thead>
            <tr><th>Fee Name</th><th style="text-align:right;">Amount</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Tuition Fee</strong></td>
                <td style="text-align:right;"><?= number_format($tuitionAmount, 2) ?></td>
            </tr>
            <?php foreach (['miscellaneous' => 'Miscellaneous', 'other' => 'Other Fee', 'additional' => 'Additional Fee', 'course' => 'Course Fees'] as $catKey => $catLabel): ?>
                <?php if (empty($itemsByCategory[$catKey])) continue; ?>
                <?php $catTotal = array_sum(array_column($itemsByCategory[$catKey], 'amount')); ?>
                <?php foreach ($itemsByCategory[$catKey] as $i => $item): ?>
                    <tr>
                        <td><?= $i === 0 ? '<strong>' . esc($catLabel) . '</strong><br>' : '' ?><?= esc($item['label']) ?></td>
                        <td style="text-align:right;"><?= number_format((float) $item['amount'], 2) ?></td>
                    </tr>
                <?php endforeach ?>
                <tr>
                    <td><strong><?= esc($catLabel) ?> Total</strong></td>
                    <td style="text-align:right;"><strong><?= number_format($catTotal, 2) ?></strong></td>
                </tr>
            <?php endforeach ?>
            <tr>
                <td><strong>Total Assessment Fee</strong></td>
                <td style="text-align:right;"><strong><?= number_format((float) $current['total_amount'], 2) ?></strong></td>
            </tr>
        </tbody>
    </table>
</div>

<?php if (!empty($schedule)): ?>
<div class="panel">
    <h2>Payment Schedule</h2>
    <table class="grade-table">
        <thead>
            <tr><th>Payment For</th><th>Due Date</th><th style="text-align:right;">Amount</th><th style="text-align:right;">Balance</th></tr>
        </thead>
        <tbody>
            <?php foreach ($schedule as $row): ?>
                <tr>
                    <td><?= esc($row['label']) ?></td>
                    <td><?= $row['due_date'] ? esc(date('M j, Y', strtotime($row['due_date']))) : '—' ?></td>
                    <td style="text-align:right;"><?= number_format((float) $row['amount'], 2) ?></td>
                    <td style="text-align:right;"><?= number_format((float) $row['balance'], 2) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>

<div class="panel">
    <h2>Upload Proof of Payment</h2>
    <form method="post" action="<?= site_url('student/tuition/proof/upload') ?>" enctype="multipart/form-data" style="display:grid; gap:12px; max-width:520px;">
        <?= csrf_field() ?>
        <?php if (!empty($schedule)): ?>
        <div class="ac-field" style="margin:0;">
            <label>Payment For</label>
            <select name="schedule_id" class="ac-input">
                <option value="">General Payment</option>
                <?php foreach ($schedule as $row): ?>
                    <option value="<?= $row['schedule_id'] ?>"><?= esc($row['label']) ?> (<?= number_format((float) $row['amount'], 2) ?>)</option>
                <?php endforeach ?>
            </select>
        </div>
        <?php endif ?>
        <div class="ac-field" style="margin:0;">
            <label>Payment Remarks</label>
            <textarea name="remarks" class="ac-input" rows="2" required placeholder="e.g. Bank transfer ref. 12345"></textarea>
        </div>
        <div class="ac-field" style="margin:0;">
            <label>Proof File (jpg, png, or pdf — max 5MB)</label>
            <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" required>
        </div>
        <button type="submit" class="ac-btn ac-btn-primary" style="justify-self:start;">Upload Proof of Payment</button>
    </form>

    <?php if (!empty($proofs)): ?>
        <table class="grade-table" style="margin-top:18px;">
            <thead>
                <tr><th>File</th><th>Uploaded</th><th>Status</th><th>Cashier Remarks</th></tr>
            </thead>
            <tbody>
                <?php foreach ($proofs as $p): ?>
                    <tr>
                        <td><a href="<?= site_url('student/tuition/proof/' . $p['proof_id']) ?>" target="_blank"><?= esc($p['original_filename']) ?></a></td>
                        <td><?= esc(date('M j, Y', strtotime($p['created_at']))) ?></td>
                        <td>
                            <?php if ($p['status'] === 'approved'): ?>
                                <span class="pass-pill pass">Approved</span>
                            <?php elseif ($p['status'] === 'rejected'): ?>
                                <span class="pass-pill fail">Rejected</span>
                            <?php else: ?>
                                <span class="pass-pill">Pending</span>
                            <?php endif ?>
                        </td>
                        <td><?= $p['cashier_remarks'] !== null ? esc($p['cashier_remarks']) : '—' ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<?php endif ?>

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

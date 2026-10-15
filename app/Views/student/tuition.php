<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'tuition';
$pageTitle     = 'Tuition & Fees';
$brandSub      = 'Student Portal';
$userName      = auth()->user()->username;
$userRoleLabel = 'Student';
$navGroups     = [
    '' => [
        'dashboard' => ['label' => 'Dashboard',   'icon' => 'home',           'url' => 'student/dashboard', 'help' => [
            'title' => 'Student Dashboard',
            'text'  => "Your at-a-glance view: today's schedule, upcoming classes, and recent grade/tuition activity. Use the sidebar to get to any other part of your student portal.",
        ]],
        'tuition'   => ['label' => 'Tuition & Fees', 'icon' => 'payments',    'url' => 'student/tuition', 'help' => [
            'title' => 'Tuition & Fees',
            'text'  => "Your current assessment, payment history, and balance. Upload proof of an off-site payment here for Finance to review, or see your installment schedule.",
        ]],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule', 'help' => [
            'title' => 'My Schedule',
            'text'  => "Your weekly class schedule for the current term — days, times, rooms, and instructors for everything you're enrolled in.",
        ]],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades', 'help' => [
            'title' => 'My Grades',
            'text'  => "Your official grades, term by term. A grade only appears here once it's been locked by the registrar.",
        ]],
        'enlistment' => ['label' => 'Enlistment', 'icon' => 'playlist_add_check', 'url' => 'student/enlistment', 'help' => [
            'title' => 'Enlistment',
            'text'  => "Build your subject cart for next term and submit it for chair → dean → registrar approval. Only available while your department's enlistment window is open; otherwise you'll enroll directly once sections open.",
        ]],
        'documents' => ['label' => 'Documents',   'icon' => 'description',        'url' => 'student/documents', 'help' => [
            'title' => 'Documents',
            'text'  => "Request a Certificate of Registration (needs at least a downpayment) or Certificate of Enrollment (available once you're enrolled, balance or not), and track their status.",
        ]],
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
<link rel="stylesheet" href="<?= base_url('assets/css/pages/student-tuition.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/student-tuition.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="tf-page">
<header class="tf-heading">
    <div>
        <span class="tf-eyebrow">STUDENT FINANCES</span>
        <h1>Tuition &amp; Fees</h1>
        <p>Review your assessment, payment schedule, and account activity.</p>
    </div>
    <?php if ($current !== null): ?>
        <span class="tf-term"><span>Current term</span><?= esc($current['academic_year']) ?></span>
    <?php endif ?>
</header>

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

<?php
$totalAssessment = (float) $current['total_amount'];
$paidPercent = $totalAssessment > 0 ? min(100, max(0, $amountPaid / $totalAssessment * 100)) : 0;
$statusLabel = ['paid' => 'Paid in full', 'partial' => 'Partially paid'][$current['status']] ?? 'Unpaid';
$statusClass = ['paid' => 'paid', 'partial' => 'partial'][$current['status']] ?? 'unpaid';
?>
<section class="tf-summary" aria-label="Account overview">
    <div class="tf-balance">
        <div class="tf-balance-top"><span>Amount due</span><span class="tf-status tf-status--<?= $statusClass ?>"><?= esc($statusLabel) ?></span></div>
        <strong class="tf-amount"><span>₱</span><?= number_format((float) $current['balance'], 2) ?></strong>
        <p>Outstanding balance for <?= esc($current['academic_year']) ?></p>
        <a class="tf-action" href="#payment-proof">Submit payment proof<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
    </div>
    <div class="tf-overview">
        <div class="tf-overview-label">Account summary</div>
        <div class="tf-metrics"><div><span>Total assessment</span><strong>₱<?= number_format($totalAssessment, 2) ?></strong></div><div><span>Payments received</span><strong class="tf-paid">₱<?= number_format($amountPaid, 2) ?></strong></div></div>
        <div class="tf-progress-label"><span>Payment progress</span><strong><?= number_format($paidPercent, 0) ?>%</strong></div>
        <progress class="tf-progress" value="<?= $paidPercent ?>" max="100" aria-label="Assessment paid"><?= number_format($paidPercent, 0) ?>%</progress>
        <dl class="tf-breakdown"><div><dt>Tuition</dt><dd>₱<?= number_format($tuitionBalance, 2) ?> remaining</dd></div><div><dt>Other fees</dt><dd>₱<?= number_format($addedFeesBalance, 2) ?> remaining</dd></div></dl>
    </div>
</section>
<div class="tf-layout">

<section class="panel tf-assessment" aria-labelledby="assessment-title">
    <header class="tf-section-heading"><div><h2 id="assessment-title">Assessment breakdown</h2><p>An itemized statement of charges for this term.</p></div></header>
    <div class="tf-column-labels"><span>Fee description</span><span>Amount · PHP</span></div>
    <div class="tf-tuition-line"><div><strong>Tuition fee</strong><span>Academic tuition for this term</span></div><strong>₱<?= number_format($tuitionAmount, 2) ?></strong></div>
    <?php foreach (['miscellaneous' => 'Miscellaneous fees', 'other' => 'Other fees', 'additional' => 'Additional fees', 'course' => 'Course fees'] as $catKey => $catLabel): ?>
        <?php if (empty($itemsByCategory[$catKey])) continue; ?>
        <?php $catTotal = array_sum(array_column($itemsByCategory[$catKey], 'amount')); ?>
        <details class="tf-fee-group" <?= $catKey === 'miscellaneous' ? 'open' : '' ?>>
            <summary><span class="material-symbols-outlined tf-chevron" aria-hidden="true">chevron_right</span><span class="tf-category"><strong><?= esc($catLabel) ?></strong><small><?= count($itemsByCategory[$catKey]) ?> <?= count($itemsByCategory[$catKey]) === 1 ? 'fee item' : 'fee items' ?></small></span><strong>₱<?= number_format($catTotal, 2) ?></strong></summary>
            <dl class="tf-fee-items"><?php foreach ($itemsByCategory[$catKey] as $item): ?><div><dt><?= esc($item['label']) ?></dt><dd>₱<?= number_format((float) $item['amount'], 2) ?></dd></div><?php endforeach ?></dl>
        </details>
    <?php endforeach ?>
    <div class="tf-assessment-total"><span>Total assessment</span><strong>₱<?= number_format($totalAssessment, 2) ?></strong></div>
    <p class="tf-note"><span class="material-symbols-outlined" aria-hidden="true">info</span>Assessment amounts are before recorded payments.</p>
</section>
<div class="tf-side">

<?php if (!empty($schedule)): ?>
<div class="panel tf-schedule">
    <header class="tf-section-heading"><div><h2>Payment schedule</h2><p>Installment amounts and their current status.</p></div></header>
    <div class="tf-table-scroll" role="region" aria-label="Payment details" tabindex="0"><table class="grade-table">
        <thead>
            <tr><th>Installment</th><th>Due date</th><th class="tf-money">Amount</th><th class="tf-money">Remaining</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php foreach ($schedule as $row): ?>
                <?php
                $installmentAmount = (float) $row['amount'];
                $installmentBalance = (float) $row['balance'];
                $installmentStatus = $installmentBalance <= 0 ? 'Paid' : ($installmentBalance < $installmentAmount ? 'Partial' : 'Due');
                $installmentClass = strtolower($installmentStatus);
                ?>
                <tr>
                    <td data-label="Installment"><strong><?= esc($row['label']) ?></strong></td>
                    <td data-label="Due date"><?= $row['due_date'] ? esc(date('M j, Y', strtotime($row['due_date']))) : 'Not set' ?></td>
                    <td data-label="Amount" class="tf-money">&#8369;<?= number_format($installmentAmount, 2) ?></td>
                    <td data-label="Remaining" class="tf-money"><strong>&#8369;<?= number_format($installmentBalance, 2) ?></strong></td>
                    <td data-label="Status"><span class="tf-installment-status tf-installment-status--<?= $installmentClass ?>"><?= $installmentStatus ?></span></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table></div>
</div>
<?php endif ?>

<section class="panel tf-upload" id="payment-proof" tabindex="-1">
    <header class="tf-section-heading"><div><h2>Submit payment proof</h2><p>Send a receipt after completing a payment.</p></div></header>
    <form method="post" action="<?= site_url('student/tuition/proof/upload') ?>" enctype="multipart/form-data" class="tf-upload-form">
        <?= csrf_field() ?>
        <?php if (!empty($schedule)): ?>
        <div class="ac-field" style="margin:0;">
            <label for="tf-schedule">Payment for</label>
            <select id="tf-schedule" name="schedule_id" class="ac-input">
                <option value="">General Payment</option>
                <?php foreach ($schedule as $row): ?>
                    <?php if ((float) $row['balance'] <= 0) continue; ?>
                    <option value="<?= $row['schedule_id'] ?>"><?= esc($row['label']) ?> (<?= number_format((float) $row['balance'], 2) ?> due)</option>
                <?php endforeach ?>
            </select>
        </div>
        <?php endif ?>
        <div class="ac-field" style="margin:0;">
            <label for="tf-remarks">Payment reference or remarks</label>
            <textarea id="tf-remarks" name="remarks" class="ac-input" rows="2" required placeholder="e.g. Bank transfer ref. 12345"></textarea>
        </div>
        <div class="ac-field" style="margin:0;">
            <label for="tf-proof">Receipt or proof of payment</label>
            <div class="tf-file-field"><input id="tf-proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" aria-describedby="tf-file-help" required><small id="tf-file-help">JPG, PNG, or PDF · Maximum 5 MB</small></div>
        </div>
        <button type="submit" class="ac-btn ac-btn-primary"><span class="material-symbols-outlined" aria-hidden="true">upload</span>Submit for review</button>
        <p class="tf-form-help">Your submission will be reviewed by the cashier before it is reflected in your balance.</p>
    </form>

    <?php if (!empty($proofs)): ?>
        <h3 class="tf-subheading">Submitted proofs</h3>
        <div class="tf-table-scroll" role="region" aria-label="Payment details" tabindex="0"><table class="grade-table">
            <thead>
                <tr><th>File</th><th>Uploaded</th><th>Status</th><th>Cashier Remarks</th></tr>
            </thead>
            <tbody>
                <?php foreach ($proofs as $p): ?>
                    <tr>
                        <td><a href="<?= site_url('student/tuition/proof/' . $p['proof_id']) ?>" target="_blank" rel="noopener"><?= esc($p['original_filename']) ?></a></td>
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
        </table></div>
    <?php endif ?>
</section>
</div>
</div>
<?php endif ?>

<div class="panel">
    <header class="tf-section-heading"><div><h2>Payment history</h2><p>Payments posted to your student account.</p></div></header>
    <?php if (empty($payments)): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">receipt_long</div>
            <p>No payments recorded yet.</p>
        </div>
    <?php else: ?>
        <div class="tf-table-scroll" role="region" aria-label="Payment details" tabindex="0"><table class="grade-table">
            <thead>
                <tr><th>Date</th><th class="tf-money">Amount</th><th>Method</th><th>Reference</th><th>Receipt</th></tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= esc(date('M j, Y', strtotime($p['payment_date']))) ?></td>
                        <td class="tf-money">&#8369;<?= number_format((float) $p['amount'], 2) ?></td>
                        <td><?= esc(ucfirst(str_replace('_', ' ', $p['method']))) ?></td>
                        <td><?= $p['reference_no'] !== null ? esc($p['reference_no']) : '—' ?></td>
                        <td>
                            <?php if (! empty($p['or_number'])): ?>
                                <a href="<?= site_url('student/tuition/receipt/' . $p['receipt_id']) ?>" target="_blank"><?= esc($p['or_number']) ?></a>
                            <?php else: ?>
                                —
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table></div>
    <?php endif ?>
</div>

<?php if (count($assessments) > 1): ?>
<div class="panel">
    <h2>Previous Terms</h2>
    <div class="tf-table-scroll" role="region" aria-label="Payment details" tabindex="0"><table class="grade-table">
        <thead>
            <tr><th>Academic Year</th><th class="tf-money">Total</th><th class="tf-money">Paid</th><th class="tf-money">Balance</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($assessments, 1) as $a): ?>
                <tr>
                    <td><?= esc($a['academic_year']) ?></td>
                    <td class="tf-money">&#8369;<?= number_format((float) $a['total_amount'], 2) ?></td>
                    <td class="tf-money">&#8369;<?= number_format((float) $a['amount_paid'], 2) ?></td>
                    <td class="tf-money">&#8369;<?= number_format((float) $a['balance'], 2) ?></td>
                    <td><?= esc(ucfirst($a['status'])) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table></div>
</div>
<?php endif ?>

</div>
<?= $this->endSection() ?>

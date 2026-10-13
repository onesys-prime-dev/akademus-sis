<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<?php $s = $student; ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/cashier') ?>" style="font-size:12.5px; color:var(--color-text-secondary);">&larr; Back to Cashier</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;"><?= esc(trim($s['first_name'] . ' ' . $s['last_name'])) ?></h2>
        <p class="ac-page-header__sub"><?= esc($s['student_number']) ?> &middot; <?= esc($s['program_name'] ?? 'No program set') ?></p>
    </div>
</div>

<h3>Assessments</h3>
<div class="ac-table-card" style="margin-bottom:20px;">
    <table class="ac-table">
        <thead><tr><th>Academic Year</th><th>Total</th><th>Discounts</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
        <tbody>
            <?php if ($assessments === []): ?>
                <tr><td colspan="6" class="ac-muted">No assessments yet.</td></tr>
            <?php endif ?>
            <?php foreach ($assessments as $a): ?>
                <tr>
                    <td><?= esc($a['academic_year']) ?></td>
                    <td><?= number_format((float) $a['total_amount'], 2) ?></td>
                    <td><?= ((float) ($a['discount_amount'] ?? 0)) > 0 ? '−' . number_format((float) $a['discount_amount'], 2) : '—' ?></td>
                    <td><?= number_format((float) $a['amount_paid'], 2) ?></td>
                    <td><?= number_format((float) $a['balance'], 2) ?></td>
                    <td><span class="ac-status <?= $a['status'] === 'paid' ? 'locked' : '' ?>"><?= esc(ucfirst($a['status'])) ?></span></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<?php if ($assessments !== []): ?>
<div class="ac-card" style="margin-bottom:20px;">
    <h3>Record a Payment</h3>
    <form method="post" action="<?= site_url('finance/cashier/student/' . $s['student_id'] . '/payment') ?>" style="display:grid; grid-template-columns:1.5fr 1fr 1fr 1fr auto; gap:10px; align-items:end;">
        <?= csrf_field() ?>
        <div class="ac-field" style="margin:0;">
            <label>Academic Year</label>
            <select name="academic_year" class="ac-input" required>
                <?php foreach ($assessments as $a): ?>
                    <option value="<?= esc($a['academic_year']) ?>"><?= esc($a['academic_year']) ?> (Bal: <?= number_format((float) $a['balance'], 2) ?>)</option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="ac-field" style="margin:0;">
            <label>Amount</label>
            <input type="number" name="amount" class="ac-input" step="0.01" min="0.01" required>
        </div>
        <div class="ac-field" style="margin:0;">
            <label>Method</label>
            <select name="method" class="ac-input" required>
                <?php foreach ($methods as $m): ?>
                    <option value="<?= esc($m) ?>"><?= esc(ucfirst(str_replace('_', ' ', $m))) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="ac-field" style="margin:0;">
            <label>Reference No.</label>
            <input type="text" name="reference_no" class="ac-input" placeholder="Optional">
        </div>
        <button type="submit" class="ac-btn ac-btn-primary" data-confirm="Record this payment and issue an Official Receipt?">Record &amp; Issue OR</button>
    </form>
</div>
<?php endif ?>

<h3>Payment History</h3>
<div class="ac-table-card">
    <table class="ac-table">
        <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Receipt</th></tr></thead>
        <tbody>
            <?php if ($payments === []): ?>
                <tr><td colspan="5" class="ac-muted">No payments recorded yet.</td></tr>
            <?php endif ?>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= esc(date('M j, Y g:i A', strtotime($p['payment_date']))) ?></td>
                    <td><?= number_format((float) $p['amount'], 2) ?></td>
                    <td><?= esc(ucfirst(str_replace('_', ' ', $p['method']))) ?></td>
                    <td><?= $p['reference_no'] !== null ? esc($p['reference_no']) : '—' ?></td>
                    <td>
                        <?php if (! empty($p['or_number'])): ?>
                            <a href="<?= site_url('student/tuition/receipt/' . $p['receipt_id']) ?>" target="_blank"><?= esc($p['or_number']) ?></a>
                            <?php if ($p['receipt_status'] === 'voided'): ?><span class="ac-muted">(voided)</span><?php endif ?>
                        <?php else: ?>
                            —
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>

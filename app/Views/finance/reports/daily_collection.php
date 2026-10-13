<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Daily Collection Report</h2></div>
</div>

<form method="get" action="<?= site_url('finance/reports/daily-collection') ?>" style="margin-bottom:20px; display:flex; gap:10px; align-items:end;">
    <div class="ac-field" style="margin:0;">
        <label>Date</label>
        <input type="date" name="date" class="ac-input" value="<?= esc($date) ?>">
    </div>
    <button type="submit" class="ac-btn ac-btn-primary">View</button>
</form>

<div class="ac-bento" style="margin-bottom:20px;">
    <div class="ac-bento-card">
        <p class="ac-bento-card__label">Total Collected</p>
        <h3 class="ac-bento-card__value">₱<?= number_format($total, 2) ?></h3>
    </div>
    <?php foreach ($byMethod as $method => $amount): ?>
        <div class="ac-bento-card">
            <p class="ac-bento-card__label"><?= esc(ucfirst(str_replace('_', ' ', $method))) ?></p>
            <h3 class="ac-bento-card__value" style="font-size:20px;">₱<?= number_format($amount, 2) ?></h3>
        </div>
    <?php endforeach ?>
</div>

<div class="ac-table-card">
    <table class="ac-table">
        <thead><tr><th>Time</th><th>Student</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
        <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="5" class="ac-muted">No payments recorded on this date.</td></tr>
            <?php endif ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= esc(date('g:i A', strtotime($r['payment_date']))) ?></td>
                    <td><?= esc($r['username']) ?> (<?= esc($r['student_number']) ?>)</td>
                    <td><?= number_format((float) $r['amount'], 2) ?></td>
                    <td><?= esc(ucfirst(str_replace('_', ' ', $r['method']))) ?></td>
                    <td><?= $r['reference_no'] !== null ? esc($r['reference_no']) : '—' ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>

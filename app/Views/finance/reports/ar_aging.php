<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Accounts Receivable Aging</h2><p class="ac-page-header__sub">Bucketed by days since the assessment was billed.</p></div>
</div>

<div class="ac-bento" style="margin-bottom:20px;">
    <div class="ac-bento-card"><p class="ac-bento-card__label">Current (0–30 days)</p><h3 class="ac-bento-card__value" style="font-size:20px;">₱<?= number_format($totals['current'], 2) ?></h3></div>
    <div class="ac-bento-card"><p class="ac-bento-card__label">31–60 days</p><h3 class="ac-bento-card__value" style="font-size:20px;">₱<?= number_format($totals['30'], 2) ?></h3></div>
    <div class="ac-bento-card"><p class="ac-bento-card__label">61–90 days</p><h3 class="ac-bento-card__value" style="font-size:20px;">₱<?= number_format($totals['60'], 2) ?></h3></div>
    <div class="ac-bento-card"><p class="ac-bento-card__label">90+ days</p><h3 class="ac-bento-card__value" style="font-size:20px;">₱<?= number_format($totals['90'], 2) ?></h3></div>
</div>

<?php $labels = ['current' => 'Current (0–30 days)', '30' => '31–60 days', '60' => '61–90 days', '90' => '90+ days']; ?>
<?php foreach ($buckets as $key => $rows): ?>
    <h3><?= esc($labels[$key]) ?> (<?= count($rows) ?>)</h3>
    <div class="ac-table-card" style="margin-bottom:20px;">
        <table class="ac-table">
            <thead><tr><th>Student</th><th>Academic Year</th><th>Balance</th><th>Days Outstanding</th></tr></thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="4" class="ac-muted">None.</td></tr>
                <?php endif ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= esc($r['username']) ?> (<?= esc($r['student_number']) ?>)</td>
                        <td><?= esc($r['academic_year']) ?></td>
                        <td><?= number_format((float) $r['balance'], 2) ?></td>
                        <td><?= esc($r['days_outstanding']) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endforeach ?>

<?= $this->endSection() ?>

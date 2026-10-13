<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<?php $s = $student; ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/reports') ?>" style="font-size:12.5px; color:var(--color-text-secondary);">&larr; Back to Reports</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;"><?= esc(trim($s['first_name'] . ' ' . $s['last_name'])) ?></h2>
        <p class="ac-page-header__sub"><?= esc($s['student_number']) ?> &middot; Ledger Summary</p>
    </div>
</div>

<div class="ac-table-card">
    <table class="ac-table">
        <thead><tr><th>Date</th><th>Type</th><th>Description</th><th style="text-align:right;">Debit</th><th style="text-align:right;">Credit</th><th style="text-align:right;">Running Balance</th></tr></thead>
        <tbody>
            <?php if ($entries === []): ?>
                <tr><td colspan="6" class="ac-muted">No ledger activity yet.</td></tr>
            <?php endif ?>
            <?php foreach ($entries as $e): ?>
                <tr>
                    <td><?= esc(date('M j, Y', strtotime($e['date']))) ?></td>
                    <td><?= esc($e['type']) ?></td>
                    <td><?= esc($e['label']) ?></td>
                    <td style="text-align:right;"><?= $e['debit'] > 0 ? number_format($e['debit'], 2) : '—' ?></td>
                    <td style="text-align:right;"><?= $e['credit'] > 0 ? number_format($e['credit'], 2) : '—' ?></td>
                    <td style="text-align:right; font-weight:700;"><?= number_format($e['running_balance'], 2) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>

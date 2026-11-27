<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/finance-reports.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/finance-reports.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$s = $student;
$photoUrl = avatar_photo_url((int) $s['user_id'], $s['photo_mime'] ?? null);
$totalDebit = array_sum(array_column($entries, 'debit'));
$totalCredit = array_sum(array_column($entries, 'credit'));
$currentBalance = $entries === [] ? 0.0 : end($entries)['running_balance'];
?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/reports') ?>" class="ac-back-link"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Back to Reports</a>
        <div style="display:flex; align-items:center; gap:12px; margin-top:6px;">
            <span class="fr-row__avatar" style="width:40px; height:40px; font-size:14px; <?= $photoUrl === null ? 'background:' . esc(avatar_color($s['username']), 'attr') . ';color:#fff;' : '' ?>">
                <?php if ($photoUrl !== null): ?>
                    <img src="<?= esc($photoUrl) ?>" alt="">
                <?php else: ?>
                    <?= esc(strtoupper(substr($s['username'], 0, 2))) ?>
                <?php endif ?>
            </span>
            <div>
                <h2 class="ac-page-header__title"><?= esc(trim($s['first_name'] . ' ' . $s['last_name'])) ?></h2>
                <p class="ac-page-header__sub" style="margin:0;"><?= esc($s['student_number']) ?> &middot; Ledger Summary</p>
            </div>
        </div>
    </div>
</div>

<div class="fr-section">

    <div class="ac-stat-grid" style="margin-bottom:20px;">
        <div class="ac-metric-card">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">account_balance</span><span>Current Balance</span></div>
            <div class="ac-metric-card__value">₱<?= number_format($currentBalance, 2) ?></div>
        </div>
        <div class="ac-metric-card">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">trending_up</span><span>Total Charged</span></div>
            <div class="ac-metric-card__value" style="font-size:20px;">₱<?= number_format($totalDebit, 2) ?></div>
        </div>
        <div class="ac-metric-card">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">trending_down</span><span>Total Paid</span></div>
            <div class="ac-metric-card__value" style="font-size:20px;">₱<?= number_format($totalCredit, 2) ?></div>
        </div>
    </div>

    <div class="fr-card" style="padding:0; overflow:hidden;">
        <div class="ac-table-card" style="border:none; border-radius:0; margin:0; box-shadow:none;">
            <table class="ac-table">
                <thead><tr><th>Date</th><th>Type</th><th>Description</th><th style="text-align:right;">Debit</th><th style="text-align:right;">Credit</th><th style="text-align:right;">Running Balance</th></tr></thead>
                <tbody>
                    <?php if ($entries === []): ?>
                        <tr><td colspan="6" class="ac-muted" style="padding:24px 16px; text-align:center;">No ledger activity yet.</td></tr>
                    <?php endif ?>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td><?= esc(date('M j, Y', strtotime($e['date']))) ?></td>
                            <td><?= esc($e['type']) ?></td>
                            <td><?= esc($e['label']) ?></td>
                            <td style="text-align:right;"><?= $e['debit'] > 0 ? number_format($e['debit'], 2) : '—' ?></td>
                            <td style="text-align:right;"><?= $e['credit'] > 0 ? number_format($e['credit'], 2) : '—' ?></td>
                            <td style="text-align:right;" class="fr-amount"><?= number_format($e['running_balance'], 2) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?= $this->endSection() ?>

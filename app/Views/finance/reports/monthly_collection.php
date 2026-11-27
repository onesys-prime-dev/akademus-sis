<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/finance-reports.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/finance-reports.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']; ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/reports') ?>" class="ac-back-link"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Back to Reports</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;">Monthly Collection Summary</h2>
        <p class="ac-page-header__sub">Total payments collected each month of <?= esc((string) $year) ?>.</p>
    </div>
</div>

<div class="fr-section">

    <form method="get" action="<?= site_url('finance/reports/monthly-collection') ?>" class="fr-filter-form">
        <div class="fr-field">
            <label for="frYear">Year</label>
            <input type="number" name="year" id="frYear" class="ac-input" value="<?= esc((string) $year) ?>" min="2000" max="2100" style="max-width:140px;">
        </div>
        <button type="submit" class="ac-btn ac-btn-primary"><span class="material-symbols-outlined" aria-hidden="true">filter_alt</span>View</button>
    </form>

    <div class="ac-metric-card" style="max-width:260px; margin-bottom:20px;">
        <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">payments</span><span>Total Collected (<?= esc((string) $year) ?>)</span></div>
        <div class="ac-metric-card__value">₱<?= number_format($total, 2) ?></div>
    </div>

    <div class="fr-card">
        <h3 class="fr-card__title">By Month</h3>
        <div class="fr-bars">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <?php $amount = $byMonth[$m]['total']; ?>
                <div class="fr-bar <?= $amount <= 0 ? 'fr-bar--empty' : '' ?>">
                    <span class="fr-bar__label"><?= esc($monthNames[$m]) ?></span>
                    <span class="fr-bar__track"><span class="fr-bar__fill" style="width:<?= $amount > 0 ? max(2, round($amount / $max * 100)) : 0 ?>%;"></span></span>
                    <span class="fr-bar__value">₱<?= number_format($amount, 2) ?></span>
                </div>
            <?php endfor ?>
        </div>
    </div>

</div>

<?= $this->endSection() ?>

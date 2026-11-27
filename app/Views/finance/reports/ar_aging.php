<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/finance-reports.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/finance-reports.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/reports') ?>" class="ac-back-link"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Back to Reports</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;">Accounts Receivable Aging</h2>
        <p class="ac-page-header__sub">Every outstanding balance, bucketed by days since the assessment was billed.</p>
    </div>
</div>

<div class="fr-section">

    <div class="ac-stat-grid" style="margin-bottom:24px;">
        <div class="ac-metric-card">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px; color:#157347;" aria-hidden="true">check_circle</span><span>Current (0–30 days)</span></div>
            <div class="ac-metric-card__value" style="font-size:20px;">₱<?= number_format($totals['current'], 2) ?></div>
        </div>
        <div class="ac-metric-card">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">schedule</span><span>31–60 days</span></div>
            <div class="ac-metric-card__value" style="font-size:20px;">₱<?= number_format($totals['30'], 2) ?></div>
        </div>
        <div class="ac-metric-card">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px; color:#b8860b;" aria-hidden="true">warning</span><span>61–90 days</span></div>
            <div class="ac-metric-card__value" style="font-size:20px;">₱<?= number_format($totals['60'], 2) ?></div>
        </div>
        <div class="ac-metric-card">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px; color:#b42318;" aria-hidden="true">error</span><span>90+ days</span></div>
            <div class="ac-metric-card__value" style="font-size:20px;">₱<?= number_format($totals['90'], 2) ?></div>
        </div>
    </div>

    <?php $labels = ['current' => 'Current (0–30 days)', '30' => '31–60 days', '60' => '61–90 days', '90' => '90+ days']; ?>
    <?php foreach ($buckets as $key => $rows): ?>
        <div class="fr-bucket fr-bucket--<?= esc($key, 'attr') ?>">
            <div class="fr-bucket__head">
                <h3 class="fr-bucket__title"><?= esc($labels[$key]) ?></h3>
                <span class="fr-bucket__count"><?= count($rows) ?></span>
            </div>
            <div class="fr-card" style="padding:0; overflow:hidden; margin-bottom:0;">
                <div class="ac-table-card" style="border:none; border-radius:0; margin:0; box-shadow:none;">
                    <table class="ac-table">
                        <thead><tr><th>Student</th><th>Academic Year</th><th style="text-align:right;">Balance</th><th style="text-align:right;">Days Outstanding</th></tr></thead>
                        <tbody>
                            <?php if ($rows === []): ?>
                                <tr><td colspan="4" class="ac-muted" style="padding:20px 16px; text-align:center;">None.</td></tr>
                            <?php endif ?>
                            <?php foreach ($rows as $r): ?>
                                <?php $photoUrl = avatar_photo_url((int) $r['user_id'], $r['photo_mime'] ?? null); ?>
                                <tr>
                                    <td>
                                        <div class="fr-row">
                                            <span class="fr-row__avatar" style="<?= $photoUrl === null ? 'background:' . esc(avatar_color($r['username']), 'attr') . ';color:#fff;' : '' ?>">
                                                <?php if ($photoUrl !== null): ?>
                                                    <img src="<?= esc($photoUrl) ?>" alt="">
                                                <?php else: ?>
                                                    <?= esc(strtoupper(substr($r['username'], 0, 2))) ?>
                                                <?php endif ?>
                                            </span>
                                            <span>
                                                <span class="fr-row__name"><?= esc($r['username']) ?></span><br>
                                                <span class="fr-row__meta"><?= esc($r['student_number']) ?></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?= esc($r['academic_year']) ?></td>
                                    <td style="text-align:right;" class="fr-amount">₱<?= number_format((float) $r['balance'], 2) ?></td>
                                    <td style="text-align:right;"><?= esc($r['days_outstanding']) ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach ?>

</div>

<?= $this->endSection() ?>

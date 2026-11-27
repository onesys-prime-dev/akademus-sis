<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/finance-reports.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/finance-reports.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/reports') ?>" class="ac-back-link"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Back to Reports</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;">Daily Collection Report</h2>
        <p class="ac-page-header__sub">Every payment recorded on <?= esc(date('F j, Y', strtotime($date))) ?>, grouped by payment method.</p>
    </div>
</div>

<div class="fr-section">

    <form method="get" action="<?= site_url('finance/reports/daily-collection') ?>" class="fr-filter-form">
        <div class="fr-field">
            <label for="frDate">Date</label>
            <div class="ac-date-field">
                <span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>
                <input type="date" name="date" id="frDate" value="<?= esc($date) ?>">
            </div>
        </div>
        <button type="submit" class="ac-btn ac-btn-primary"><span class="material-symbols-outlined" aria-hidden="true">filter_alt</span>View</button>
    </form>

    <div class="ac-stat-grid" style="margin-bottom:20px;">
        <div class="ac-metric-card">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">payments</span><span>Total Collected</span></div>
            <div class="ac-metric-card__value">₱<?= number_format($total, 2) ?></div>
        </div>
        <?php foreach ($byMethod as $method => $amount): ?>
            <div class="ac-metric-card">
                <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">account_balance_wallet</span><span><?= esc(ucfirst(str_replace('_', ' ', $method))) ?></span></div>
                <div class="ac-metric-card__value" style="font-size:20px;">₱<?= number_format($amount, 2) ?></div>
            </div>
        <?php endforeach ?>
    </div>

    <div class="fr-card" style="padding:0; overflow:hidden;">
        <div class="ac-table-card" style="border:none; border-radius:0; margin:0; box-shadow:none;">
            <table class="ac-table">
                <thead><tr><th>Time</th><th>Student</th><th style="text-align:right;">Amount</th><th>Method</th><th>Reference</th></tr></thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="5" class="ac-muted" style="padding:24px 16px; text-align:center;">No payments recorded on this date.</td></tr>
                    <?php endif ?>
                    <?php foreach ($rows as $r): ?>
                        <?php $photoUrl = avatar_photo_url((int) $r['user_id'], $r['photo_mime'] ?? null); ?>
                        <tr>
                            <td><?= esc(date('g:i A', strtotime($r['payment_date']))) ?></td>
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
                            <td style="text-align:right;" class="fr-amount">₱<?= number_format((float) $r['amount'], 2) ?></td>
                            <td><?= esc(ucfirst(str_replace('_', ' ', $r['method']))) ?></td>
                            <td><?= $r['reference_no'] !== null ? esc($r['reference_no']) : '—' ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?= $this->endSection() ?>

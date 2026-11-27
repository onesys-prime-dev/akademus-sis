<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/finance-reports.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/finance-reports.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/reports') ?>" class="ac-back-link"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Back to Reports</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;">Discounts &amp; Additional Fees Summary</h2>
        <p class="ac-page-header__sub">What was actually granted over this period, by discount type and fee label.</p>
    </div>
</div>

<div class="fr-section">

    <form method="get" action="<?= site_url('finance/reports/discounts-fees-summary') ?>" class="fr-filter-form">
        <div class="fr-field">
            <label for="frFrom">From</label>
            <input type="date" name="from" id="frFrom" class="ac-input" value="<?= esc($from) ?>">
        </div>
        <div class="fr-field">
            <label for="frTo">To</label>
            <input type="date" name="to" id="frTo" class="ac-input" value="<?= esc($to) ?>">
        </div>
        <button type="submit" class="ac-btn ac-btn-primary"><span class="material-symbols-outlined" aria-hidden="true">filter_alt</span>View</button>
    </form>

    <div class="ac-stat-grid" style="margin-bottom:20px;">
        <div class="ac-metric-card">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px; color:#157347;" aria-hidden="true">sell</span><span>Total Discounts Granted</span></div>
            <div class="ac-metric-card__value" style="font-size:20px;">₱<?= number_format($discountsTotal, 2) ?></div>
        </div>
        <div class="ac-metric-card">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px; color:#b8860b;" aria-hidden="true">receipt</span><span>Total Additional Fees Charged</span></div>
            <div class="ac-metric-card__value" style="font-size:20px;">₱<?= number_format($feesTotal, 2) ?></div>
        </div>
    </div>

    <div class="fr-card">
        <h3 class="fr-card__title">Discounts by Type</h3>
        <p class="fr-card__hint">Active, never-reversed discount credits granted in this period.</p>
        <div class="ac-table-card" style="margin:0;">
            <table class="ac-table">
                <thead><tr><th>Discount Type</th><th style="text-align:right;">Grants</th><th style="text-align:right;">Total</th></tr></thead>
                <tbody>
                    <?php if ($discounts === []): ?>
                        <tr><td colspan="3" class="ac-muted" style="padding:20px 16px; text-align:center;">No discounts granted in this period.</td></tr>
                    <?php endif ?>
                    <?php foreach ($discounts as $d): ?>
                        <tr>
                            <td><?= esc($d['label']) ?></td>
                            <td style="text-align:right;"><?= (int) $d['count'] ?></td>
                            <td style="text-align:right;" class="fr-amount">₱<?= number_format((float) $d['total'], 2) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="fr-card">
        <h3 class="fr-card__title">Additional Fees by Label</h3>
        <p class="fr-card__hint">Active, never-reversed one-off fee charges granted in this period.</p>
        <div class="ac-table-card" style="margin:0;">
            <table class="ac-table">
                <thead><tr><th>Fee Label</th><th style="text-align:right;">Grants</th><th style="text-align:right;">Total</th></tr></thead>
                <tbody>
                    <?php if ($fees === []): ?>
                        <tr><td colspan="3" class="ac-muted" style="padding:20px 16px; text-align:center;">No additional fees charged in this period.</td></tr>
                    <?php endif ?>
                    <?php foreach ($fees as $f): ?>
                        <tr>
                            <td><?= esc($f['label']) ?></td>
                            <td style="text-align:right;"><?= (int) $f['count'] ?></td>
                            <td style="text-align:right;" class="fr-amount">₱<?= number_format((float) $f['total'], 2) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?= $this->endSection() ?>

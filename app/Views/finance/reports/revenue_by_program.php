<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/finance-reports.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/finance-reports.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/reports') ?>" class="ac-back-link"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Back to Reports</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;">Revenue by Program</h2>
        <p class="ac-page-header__sub">Billed, collected, and outstanding totals for the selected term, grouped by program and year level.</p>
    </div>
</div>

<div class="fr-section">

    <form method="get" action="<?= site_url('finance/reports/revenue-by-program') ?>" class="fr-filter-form">
        <div class="fr-field" style="min-width:280px;">
            <label for="frTerm">Term</label>
            <select name="term" id="frTerm" class="ac-input" onchange="this.form.submit()">
                <?php foreach ($termOptions as $opt): ?>
                    <option value="<?= esc($opt['value'], 'attr') ?>" <?= $opt['value'] === $term ? 'selected' : '' ?>><?= esc($opt['label']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </form>

    <?php if ($term === ''): ?>
        <div class="ac-empty-block">No academic term is set up yet.</div>
    <?php else: ?>

        <div class="ac-stat-grid" style="margin-bottom:20px;">
            <div class="ac-metric-card">
                <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">groups</span><span>Students Billed</span></div>
                <div class="ac-metric-card__value" style="font-size:20px;"><?= $grandTotal['students'] ?></div>
            </div>
            <div class="ac-metric-card">
                <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">receipt_long</span><span>Billed</span></div>
                <div class="ac-metric-card__value" style="font-size:20px;">₱<?= number_format($grandTotal['billed'], 2) ?></div>
            </div>
            <div class="ac-metric-card">
                <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px; color:#157347;" aria-hidden="true">payments</span><span>Collected</span></div>
                <div class="ac-metric-card__value" style="font-size:20px;">₱<?= number_format($grandTotal['collected'], 2) ?></div>
            </div>
            <div class="ac-metric-card">
                <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px; color:#b8860b;" aria-hidden="true">schedule</span><span>Outstanding</span></div>
                <div class="ac-metric-card__value" style="font-size:20px;">₱<?= number_format($grandTotal['outstanding'], 2) ?></div>
            </div>
        </div>

        <div class="fr-card" style="padding:0; overflow:hidden;">
            <div class="ac-table-card" style="border:none; border-radius:0; margin:0; box-shadow:none;">
                <table class="ac-table">
                    <thead><tr><th>Program</th><th>Year Level</th><th style="text-align:right;">Students</th><th style="text-align:right;">Billed</th><th style="text-align:right;">Collected</th><th style="text-align:right;">Outstanding</th></tr></thead>
                    <tbody>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="6" class="ac-muted" style="padding:24px 16px; text-align:center;">No assessments found for this term.</td></tr>
                        <?php endif ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= esc($r['program']) ?></td>
                                <td><?= esc($r['year_level']) ?></td>
                                <td style="text-align:right;"><?= (int) $r['student_count'] ?></td>
                                <td style="text-align:right;"><?= number_format((float) $r['billed'], 2) ?></td>
                                <td style="text-align:right;"><?= number_format((float) $r['collected'], 2) ?></td>
                                <td style="text-align:right;" class="fr-amount">₱<?= number_format((float) $r['outstanding'], 2) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif ?>

</div>

<?= $this->endSection() ?>

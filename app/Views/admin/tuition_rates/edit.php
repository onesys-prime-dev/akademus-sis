<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url($basePath . '/tuition-rates') ?>" style="font-size:12.5px; color:var(--color-text-secondary);">&larr; Back to Tuition Rates</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;"><?= esc($program['program_code']) ?> — <?= esc($academicYear) ?></h2>
        <p class="ac-page-header__sub">Every active fee type appears below, grouped by category. Leave an amount blank to not charge that fee to this program.</p>
    </div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<form method="post" action="<?= site_url($basePath . '/tuition-rates/store') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="program_id" value="<?= $program['program_id'] ?>">
    <input type="hidden" name="academic_year" value="<?= esc($academicYear, 'attr') ?>">

    <div class="ac-table-card" style="margin-bottom:16px;">
        <div style="padding:20px 24px; display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div class="ac-field" style="margin:0;">
                <label>Per-unit Tuition Rate</label>
                <input type="number" name="per_unit_rate" class="ac-input" step="0.01" min="0" value="<?= esc((string) ($rate['per_unit_rate'] ?? '0')) ?>" required>
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Number of Installments</label>
                <input type="number" name="installment_count" class="ac-input" step="1" min="1" value="<?= esc((string) ($rate['installment_count'] ?? '1')) ?>" required>
            </div>
        </div>
    </div>

    <?php if (empty($feesByCategory)): ?>
        <div class="ac-empty-block" style="margin-bottom:16px;">
            <p>No fee types exist yet. <a href="<?= site_url($basePath . '/fee-types') ?>">Add one</a> to start pricing Miscellaneous/Other/Additional/Course fees.</p>
        </div>
    <?php endif ?>

    <?php foreach ($feesByCategory as $category => $feeTypes): ?>
        <div class="ac-table-card" style="margin-bottom:16px;">
            <h4 style="padding:16px 24px 0; font-size:13.5px; text-transform:capitalize;"><?= esc($category) ?> Fees</h4>
            <div style="padding:8px 24px 20px; display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <?php foreach ($feeTypes as $feeType): ?>
                    <div class="ac-field" style="margin:0;">
                        <label><?= esc($feeType['name']) ?></label>
                        <input type="number" name="fee[<?= $feeType['fee_type_id'] ?>]" class="ac-input" step="0.01" min="0"
                               value="<?= isset($feeAmounts[$feeType['fee_type_id']]) ? esc((string) $feeAmounts[$feeType['fee_type_id']]) : '' ?>"
                               placeholder="Not charged">
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    <?php endforeach ?>

    <button type="submit" class="ac-btn ac-btn-primary">Save Rate Sheet</button>
</form>

<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Tuition Rates</h2>
        <p class="ac-page-header__sub">Per-program, per-academic-year rate sheet used to compute every student's tuition assessment. A student in a program/year with no rate sheet here shows as "unassessed" until one is set. New fees are added under <a href="<?= site_url($basePath . '/fee-types') ?>">Fee Types</a> and immediately appear on every program's rate sheet below.</p>
    </div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<div class="ac-table-card" style="margin-bottom:20px;">
    <div style="padding:20px 24px;">
        <h4 style="margin:0 0 12px; font-size:14px;">Open or Create a Rate Sheet</h4>
        <form method="get" action="" onsubmit="event.preventDefault(); if(this.program_id.value && this.academic_year.value) window.location = '<?= site_url($basePath . '/tuition-rates/edit') ?>/' + this.program_id.value + '/' + encodeURIComponent(this.academic_year.value);" style="display:grid; grid-template-columns:2fr 1fr auto; gap:10px; align-items:end;">
            <div class="ac-field" style="margin:0;">
                <label>Program</label>
                <select name="program_id" class="ac-input" required>
                    <option value="">— Select —</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?= $p['program_id'] ?>"><?= esc($p['program_code']) ?> — <?= esc($p['program_name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Academic Year</label>
                <input type="text" name="academic_year" class="ac-input" placeholder="e.g. AY 2025 - 2026 &middot; 1st Semester" required>
            </div>
            <button type="submit" class="ac-btn ac-btn-primary">Open Rate Sheet</button>
        </form>
        <p style="font-size:11.5px; color:var(--color-text-secondary); margin:10px 0 0;">Enter the academic year exactly as it appears on sections/enrollment (e.g. the string shown on Student Records &rarr; Current Section).</p>
    </div>
</div>

<?php if (empty($rates)): ?>
    <div class="ac-empty-block">
        <p>No tuition rate sheets set yet.</p>
    </div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead>
                <tr>
                    <th>Program</th>
                    <th>Academic Year</th>
                    <th>Per-unit Rate</th>
                    <th>Installments</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rates as $rate): ?>
                    <?php $program = $programsById[(int) $rate['program_id']] ?? null; ?>
                    <tr>
                        <td><span class="ac-cell-bold"><?= $program !== null ? esc($program['program_code']) : 'Program #' . esc((string) $rate['program_id']) ?></span></td>
                        <td><?= esc($rate['academic_year']) ?></td>
                        <td><?= number_format((float) $rate['per_unit_rate'], 2) ?></td>
                        <td><?= (int) $rate['installment_count'] ?></td>
                        <td class="ac-actions-cell">
                            <a href="<?= site_url($basePath . '/tuition-rates/edit/' . $rate['program_id'] . '/' . urlencode($rate['academic_year'])) ?>" class="ac-btn-outline">Edit</a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Tuition Rates</h2>
        <p class="ac-page-header__sub">Per-program, per-academic-year rate used to compute every student's tuition assessment. A student in a program/year with no rate here shows as "unassessed" until one is set.</p>
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
        <h4 style="margin:0 0 12px; font-size:14px;">Set a Rate</h4>
        <form method="post" action="<?= site_url($basePath . '/tuition-rates/store') ?>" style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto; gap:10px; align-items:end;">
            <?= csrf_field() ?>
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
                <input type="text" name="academic_year" class="ac-input" placeholder="2025-2026" required>
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Per-unit Rate</label>
                <input type="number" name="per_unit_rate" class="ac-input" step="0.01" min="0" required>
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Misc. Fee</label>
                <input type="number" name="misc_fee" class="ac-input" step="0.01" min="0" value="0">
            </div>
            <button type="submit" class="ac-btn ac-btn-primary">Save</button>
        </form>
        <p style="font-size:11.5px; color:var(--color-text-secondary); margin:10px 0 0;">Setting a rate for a program/year that already has one updates it — every affected student's assessment is recomputed the next time their enrollment changes.</p>
    </div>
</div>

<?php if (empty($rates)): ?>
    <div class="ac-empty-block">
        <p>No tuition rates set yet.</p>
    </div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead>
                <tr>
                    <th>Program</th>
                    <th>Academic Year</th>
                    <th>Per-unit Rate</th>
                    <th>Misc. Fee</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rates as $rate): ?>
                    <?php $program = $programsById[(int) $rate['program_id']] ?? null; ?>
                    <tr>
                        <td><span class="ac-cell-bold"><?= $program !== null ? esc($program['program_code']) : 'Program #' . esc((string) $rate['program_id']) ?></span></td>
                        <td><?= esc($rate['academic_year']) ?></td>
                        <td><?= number_format((float) $rate['per_unit_rate'], 2) ?></td>
                        <td><?= number_format((float) $rate['misc_fee'], 2) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

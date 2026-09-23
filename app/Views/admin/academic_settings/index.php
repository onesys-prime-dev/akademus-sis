<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Academic Scheduling Rules</h2>
        <p class="ac-page-header__sub">Institution-wide defaults used to derive a subject's weekly contact hours when it doesn't specify its own. A subject's own explicit values always override these.</p>
    </div>
</div>

<?php if (session()->has('errors')): ?>
    <div class="ac-alert" style="background:var(--color-danger-soft); border-color:var(--color-danger-border);">
        <span class="material-symbols-outlined" style="color:var(--color-danger);" aria-hidden="true">error</span>
        <div class="ac-alert__text" style="color:var(--color-danger);">
            <?php foreach (session('errors') as $error): ?>
                <p><?= esc($error) ?></p>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<div class="ac-card" style="max-width:560px;">
    <form method="post" action="<?= site_url('admin/academic-settings/update') ?>">
        <?= csrf_field() ?>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="ac-field">
                <label for="lecture_hours_per_unit">Lecture Hours per Unit</label>
                <input type="number" step="0.5" id="lecture_hours_per_unit" name="lecture_hours_per_unit" class="ac-input" value="<?= old('lecture_hours_per_unit', $lectureHoursPerUnit) ?>">
            </div>
            <div class="ac-field">
                <label for="lab_hours_per_unit">Laboratory Hours per Unit</label>
                <input type="number" step="0.5" id="lab_hours_per_unit" name="lab_hours_per_unit" class="ac-input" value="<?= old('lab_hours_per_unit', $labHoursPerUnit) ?>">
            </div>
        </div>

        <div class="ac-field">
            <label for="max_faculty_units">Max Faculty Units per Semester</label>
            <input type="number" step="1" id="max_faculty_units" name="max_faculty_units" class="ac-input" value="<?= old('max_faculty_units', $maxFacultyUnits) ?>">
        </div>

        <div class="ac-field">
            <label for="max_meetings_per_week">Max Meetings per Week (per subject)</label>
            <input type="number" step="1" id="max_meetings_per_week" name="max_meetings_per_week" class="ac-input" value="<?= old('max_meetings_per_week', $maxMeetingsPerWeek) ?>">
        </div>

        <div class="ac-field">
            <label for="part_time_max_units">Part-Time Faculty Max Units</label>
            <input type="number" step="1" id="part_time_max_units" name="part_time_max_units" class="ac-input" placeholder="Leave blank to use the same cap as full-time" value="<?= old('part_time_max_units', $partTimeMaxUnits) ?>">
        </div>

        <div style="margin-top:8px;">
            <button type="submit" class="ac-btn ac-btn-primary">Save Rules</button>
        </div>
    </form>
</div>

<?= $this->endSection() ?>

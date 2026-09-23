<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <div class="ac-breadcrumb">
            <a href="<?= site_url('admin/subjects') ?>">Subjects</a>
            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            <span class="current">Edit</span>
        </div>
        <h2 class="ac-page-header__title">Edit Subject</h2>
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

<div class="ac-card" style="max-width:640px;">
    <form method="post" action="<?= site_url('admin/subjects/update/' . $subject['subject_id']) ?>">
        <?= csrf_field() ?>

        <div class="ac-field">
            <label for="subject_code">Subject Code</label>
            <input type="text" id="subject_code" name="subject_code" class="ac-input" value="<?= old('subject_code', $subject['subject_code']) ?>" required>
        </div>

        <div class="ac-field">
            <label for="subject_name">Subject Name</label>
            <input type="text" id="subject_name" name="subject_name" class="ac-input" value="<?= old('subject_name', $subject['subject_name']) ?>" required>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="ac-field">
                <label for="units">Units</label>
                <input type="number" step="0.5" id="units" name="units" class="ac-input" value="<?= old('units', $subject['units']) ?>">
            </div>
            <div class="ac-field">
                <label style="display:flex; align-items:center; gap:8px; margin-top:22px;">
                    <input type="checkbox" name="is_laboratory" value="1" <?= old('is_laboratory', $subject['is_laboratory']) ? 'checked' : '' ?>>
                    <span style="text-transform:none; font-weight:600; color:var(--color-text-primary); font-size:13px;">Laboratory subject</span>
                </label>
                <p style="font-size:11.5px; color:var(--color-text-secondary); margin-top:4px;">May only be plotted on Saturdays.</p>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="ac-field">
                <label for="lecture_units">Lecture Units</label>
                <input type="number" step="0.5" id="lecture_units" name="lecture_units" class="ac-input" value="<?= old('lecture_units', $subject['lecture_units']) ?>">
                <p style="font-size:11.5px; color:var(--color-text-secondary); margin-top:4px;">Optional split; overrides Units above for hour calc.</p>
            </div>
            <div class="ac-field">
                <label for="laboratory_units">Laboratory Units</label>
                <input type="number" step="0.5" id="laboratory_units" name="laboratory_units" class="ac-input" value="<?= old('laboratory_units', $subject['laboratory_units']) ?>">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="ac-field">
                <label for="contact_hours">Contact Hours per Meeting</label>
                <input type="number" step="0.5" id="contact_hours" name="contact_hours" class="ac-input" value="<?= old('contact_hours', $subject['contact_hours']) ?>">
            </div>
            <div class="ac-field">
                <label for="meetings_per_week">Meetings per Week</label>
                <input type="number" step="1" id="meetings_per_week" name="meetings_per_week" class="ac-input" value="<?= old('meetings_per_week', $subject['meetings_per_week']) ?>">
            </div>
        </div>

        <div class="ac-field">
            <label for="weekly_hours">Weekly Hours (derived, read-only)</label>
            <input type="text" id="weekly_hours" class="ac-input" value="<?= esc($subject['weekly_hours']) ?>" disabled>
        </div>

        <div class="ac-field">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="ac-input" rows="3"><?= esc($subject['description']) ?></textarea>
        </div>

        <div style="display:flex; gap:10px; margin-top:8px;">
            <a href="<?= site_url('admin/subjects') ?>" class="ac-btn ac-btn-secondary">Cancel</a>
            <button type="submit" class="ac-btn ac-btn-primary">Update Subject</button>
        </div>
    </form>
</div>

<?= $this->endSection() ?>

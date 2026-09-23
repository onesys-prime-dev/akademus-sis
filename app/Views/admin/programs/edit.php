<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <div class="ac-breadcrumb">
            <a href="<?= site_url('admin/programs') ?>">Programs</a>
            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            <span class="current">Edit</span>
        </div>
        <h2 class="ac-page-header__title">Edit Program</h2>
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

<div class="ac-card" style="max-width:560px;">
    <form method="post" action="<?= site_url('admin/programs/update/' . $program['program_id']) ?>">
        <?= csrf_field() ?>

        <div class="ac-field">
            <label for="program_code">Program Code</label>
            <input type="text" id="program_code" name="program_code" class="ac-input" value="<?= old('program_code', $program['program_code']) ?>" required>
        </div>

        <div class="ac-field">
            <label for="program_name">Program Name</label>
            <input type="text" id="program_name" name="program_name" class="ac-input" value="<?= old('program_name', $program['program_name']) ?>" required>
        </div>

        <div class="ac-field">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="ac-input" rows="3"><?= esc($program['description']) ?></textarea>
        </div>

        <div style="display:flex; gap:10px; margin-top:8px;">
            <a href="<?= site_url('admin/programs') ?>" class="ac-btn ac-btn-secondary">Cancel</a>
            <button type="submit" class="ac-btn ac-btn-primary">Update Program</button>
        </div>
    </form>
</div>

<?= $this->endSection() ?>

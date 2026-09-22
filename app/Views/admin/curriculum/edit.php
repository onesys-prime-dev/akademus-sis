<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <div class="ac-breadcrumb">
            <a href="<?= site_url('admin/curriculum') ?>">Curricula</a>
            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            <span class="current">Edit</span>
        </div>
        <h2 class="ac-page-header__title"><?= $editable ? 'Edit Curriculum' : 'View Curriculum (Published — Read Only)' ?></h2>
    </div>
</div>

<?php if (! $editable): ?>
    <div class="ac-notice" style="margin-bottom:16px; background:#fef9e7; border-color:#f5e6b3;">
        <span class="material-symbols-outlined" style="font-size:16px; margin-top:1px;" aria-hidden="true">lock</span>
        <span class="ac-notice__text">
            This curriculum is <strong><?= esc(ucfirst($curriculum['status'])) ?></strong> — published versions are immutable snapshots so past students' records never silently change.
            <form method="post" action="<?= site_url('admin/curriculum/new-version/' . $curriculum['curriculum_id']) ?>" style="display:inline; margin-left:6px;">
                <?= csrf_field() ?>
                <button type="submit" class="ac-btn-outline">Create New Version to Edit</button>
            </form>
        </span>
    </div>
<?php endif ?>

<?php if (! empty($versions) && count($versions) > 1): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="ac-notice__text">
            Version history:
            <?php foreach ($versions as $v): ?>
                <a href="<?= site_url('admin/curriculum/edit/' . $v['curriculum_id']) ?>" style="margin-right:10px; <?= (int) $v['curriculum_id'] === (int) $curriculum['curriculum_id'] ? 'font-weight:600;' : '' ?>">
                    v<?= esc($v['version_number']) ?> (<?= esc(ucfirst($v['status'])) ?><?= $v['is_current'] ? ', current' : '' ?>)
                </a>
            <?php endforeach ?>
        </span>
    </div>
<?php endif ?>

<?php if (session()->has('errors')): ?>
    <div class="ac-alert" style="background:#fef2f2; border-color:#fecaca;">
        <span class="material-symbols-outlined" style="color:var(--color-danger);" aria-hidden="true">error</span>
        <div class="ac-alert__text" style="color:var(--color-danger);">
            <?php foreach (session('errors') as $error): ?>
                <p><?= esc($error) ?></p>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<div class="ac-card" style="max-width:560px;">
    <form method="post" action="<?= site_url('admin/curriculum/update/' . $curriculum['curriculum_id']) ?>">
        <?= csrf_field() ?>

        <?php $dis = $editable ? '' : 'disabled'; ?>

        <div class="ac-field">
            <label for="program_id">Program</label>
            <select id="program_id" name="program_id" class="ac-input" <?= $dis ?>>
                <?php foreach ($programs as $program): ?>
                    <option value="<?= $program['program_id'] ?>" <?= old('program_id', $curriculum['program_id']) == $program['program_id'] ? 'selected' : '' ?>>
                        <?= esc($program['program_name']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="ac-field">
            <label for="curriculum_code">Curriculum Code</label>
            <input type="text" id="curriculum_code" name="curriculum_code" class="ac-input" value="<?= old('curriculum_code', $curriculum['curriculum_code']) ?>" required <?= $dis ?>>
        </div>

        <div class="ac-field">
            <label for="curriculum_name">Curriculum Name</label>
            <input type="text" id="curriculum_name" name="curriculum_name" class="ac-input" value="<?= old('curriculum_name', $curriculum['curriculum_name']) ?>" required <?= $dis ?>>
        </div>

        <div class="ac-field">
            <label for="academic_year">Academic Year</label>
            <input type="text" id="academic_year" name="academic_year" class="ac-input" value="<?= old('academic_year', $curriculum['academic_year']) ?>" required <?= $dis ?>>
        </div>

        <div class="ac-field">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="ac-input" rows="3" <?= $dis ?>><?= old('description', $curriculum['description']) ?></textarea>
        </div>

        <div style="display:flex; gap:10px; margin-top:8px;">
            <a href="<?= site_url('admin/curriculum') ?>" class="ac-btn ac-btn-secondary">Cancel</a>
            <?php if ($editable): ?>
                <button type="submit" class="ac-btn ac-btn-primary">Update Curriculum</button>
            <?php endif ?>
        </div>
    </form>
</div>

<?= $this->endSection() ?>

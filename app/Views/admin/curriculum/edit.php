<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <div class="ac-breadcrumb">
            <a href="<?= site_url('admin/curriculum') ?>">Curricula</a>
            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            <span class="current">Edit</span>
        </div>
        <h2 class="ac-page-header__title">Edit Curriculum</h2>
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
    <form method="post" action="<?= site_url('admin/curriculum/update/' . $curriculum['curriculum_id']) ?>">
        <?= csrf_field() ?>

        <div class="ac-field">
            <label for="program_id">Program</label>
            <select id="program_id" name="program_id" class="ac-input">
                <?php foreach ($programs as $program): ?>
                    <option value="<?= $program['program_id'] ?>" <?= old('program_id', $curriculum['program_id']) == $program['program_id'] ? 'selected' : '' ?>>
                        <?= esc($program['program_name']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="ac-field">
            <label for="curriculum_code">Curriculum Code</label>
            <input type="text" id="curriculum_code" name="curriculum_code" class="ac-input" value="<?= old('curriculum_code', $curriculum['curriculum_code']) ?>" required>
        </div>

        <div class="ac-field">
            <label for="curriculum_name">Curriculum Name</label>
            <input type="text" id="curriculum_name" name="curriculum_name" class="ac-input" value="<?= old('curriculum_name', $curriculum['curriculum_name']) ?>" required>
        </div>

        <div class="ac-field">
            <label for="academic_year">Academic Year</label>
            <input type="text" id="academic_year" name="academic_year" class="ac-input" value="<?= old('academic_year', $curriculum['academic_year']) ?>" required>
        </div>

        <div class="ac-field">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="ac-input" rows="3"><?= old('description', $curriculum['description']) ?></textarea>
        </div>

        <div style="display:flex; gap:10px; margin-top:8px;">
            <a href="<?= site_url('admin/curriculum') ?>" class="ac-btn ac-btn-secondary">Cancel</a>
            <button type="submit" class="ac-btn ac-btn-primary">Update Curriculum</button>
        </div>
    </form>
</div>

<?= $this->endSection() ?>

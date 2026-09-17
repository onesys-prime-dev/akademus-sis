<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <div class="ac-breadcrumb">
            <a href="<?= site_url('admin/curriculum-subjects') ?>">Curriculum Subjects</a>
            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            <span class="current">Assign Subject</span>
        </div>
        <h2 class="ac-page-header__title">Assign Subject to Curriculum</h2>
    </div>
</div>

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
    <form method="post" action="<?= site_url('admin/curriculum-subjects/store') ?>">
        <?= csrf_field() ?>

        <div class="ac-field">
            <label for="curriculum_id">Curriculum</label>
            <select id="curriculum_id" name="curriculum_id" class="ac-input" required>
                <option value="">— Select a curriculum —</option>
                <?php foreach ($curricula as $curriculum): ?>
                    <option value="<?= $curriculum['curriculum_id'] ?>" <?= old('curriculum_id') == $curriculum['curriculum_id'] ? 'selected' : '' ?>>
                        <?= esc($curriculum['curriculum_code']) ?> — <?= esc($curriculum['curriculum_name']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="ac-field">
            <label for="subject_id">Subject</label>
            <select id="subject_id" name="subject_id" class="ac-input" required>
                <option value="">— Select a subject —</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= $subject['subject_id'] ?>" <?= old('subject_id') == $subject['subject_id'] ? 'selected' : '' ?>>
                        <?= esc($subject['subject_code']) ?> — <?= esc($subject['subject_name']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="ac-field">
                <label for="year_level">Year Level</label>
                <select id="year_level" name="year_level" class="ac-input" required>
                    <?php foreach ([1, 2, 3, 4] as $lvl): ?>
                        <option value="<?= $lvl ?>" <?= old('year_level') == $lvl ? 'selected' : '' ?>>Year <?= $lvl ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="ac-field">
                <label for="semester">Semester</label>
                <select id="semester" name="semester" class="ac-input" required>
                    <?php foreach ([1, 2, 3] as $sem): ?>
                        <option value="<?= $sem ?>" <?= old('semester') == $sem ? 'selected' : '' ?>>Semester <?= $sem ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:8px;">
            <a href="<?= site_url('admin/curriculum-subjects') ?>" class="ac-btn ac-btn-secondary">Cancel</a>
            <button type="submit" class="ac-btn ac-btn-primary">Assign Subject</button>
        </div>
    </form>
</div>

<?= $this->endSection() ?>

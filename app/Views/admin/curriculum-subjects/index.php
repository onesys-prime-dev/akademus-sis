<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('headerActions') ?>
<a href="<?= site_url('admin/curriculum-subjects/create') ?>" class="ac-btn ac-btn-primary">
    <span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">add</span>
    <span>Assign Subject</span>
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Curriculum Subjects</h2>
        <p class="ac-page-header__sub">Which subjects belong to each curriculum, and in which year level and semester.</p>
    </div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<?php if (empty($curriculumSubjects)): ?>
    <div class="ac-empty-block">
        <p>No subjects assigned to a curriculum yet.</p>
        <p style="margin-top:6px;"><a href="<?= site_url('admin/curriculum-subjects/create') ?>">Assign the first subject</a> to a curriculum.</p>
    </div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead>
                <tr>
                    <th>Curriculum</th>
                    <th>Subject</th>
                    <th>Year Level</th>
                    <th>Semester</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($curriculumSubjects as $cs): ?>
                    <tr>
                        <td>
                            <span class="ac-cell-bold"><?= esc($cs['curriculum_code']) ?></span>
                            <div class="ac-muted" style="font-size:12px;"><?= esc($cs['curriculum_name']) ?></div>
                        </td>
                        <td>
                            <span class="ac-cell-bold"><?= esc($cs['subject_code']) ?></span>
                            <div class="ac-muted" style="font-size:12px;"><?= esc($cs['subject_name']) ?></div>
                        </td>
                        <td class="ac-muted">Year <?= esc($cs['year_level']) ?></td>
                        <td class="ac-muted">Semester <?= esc($cs['semester']) ?></td>
                        <td class="ac-actions-cell">
                            <a href="<?= site_url('admin/curriculum-subjects/edit/' . $cs['curriculum_subject_id']) ?>" class="ac-btn-outline">Edit</a>
                            <form method="post" action="<?= site_url('admin/curriculum-subjects/delete/' . $cs['curriculum_subject_id']) ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <button type="submit" class="ac-btn-outline" data-confirm="Remove this subject from the curriculum?">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

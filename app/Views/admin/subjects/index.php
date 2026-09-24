<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('headerActions') ?>
<a href="<?= site_url('admin/subjects/create') ?>" class="ac-btn ac-btn-primary">
    <span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">add</span>
    <span>Add Subject</span>
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Subjects</h2>
        <p class="ac-page-header__sub">The subject catalog — units, contact hours, and lecture/laboratory type used when building curricula.</p>
    </div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<?php if (empty($subjects)): ?>
    <div class="ac-empty-block">
        <p>No subjects yet.</p>
        <p style="margin-top:6px;"><a href="<?= site_url('admin/subjects/create') ?>">Add the first subject</a> to start building curricula.</p>
    </div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Units</th>
                    <th>Type</th>
                    <th>Weekly Hours</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $subject): ?>
                    <tr>
                        <td><span class="ac-cell-bold"><?= esc($subject['subject_code']) ?></span></td>
                        <td><?= esc($subject['subject_name']) ?></td>
                        <td><?= esc($subject['units']) ?></td>
                        <td class="ac-muted"><?= $subject['is_laboratory'] ? 'Laboratory' : 'Lecture' ?></td>
                        <td class="ac-muted"><?= esc($subject['weekly_hours']) ?></td>
                        <td>
                            <span class="ac-status-dot <?= $subject['is_active'] ? 'ac-status-dot--active' : 'ac-status-dot--inactive' ?>">
                                <?= $subject['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="ac-actions-cell">
                            <a href="<?= site_url('admin/subjects/edit/' . $subject['subject_id']) ?>" class="ac-btn-outline">Edit</a>
                            <form method="post" action="<?= site_url('admin/subjects/toggle-status/' . $subject['subject_id']) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="ac-btn-outline" data-confirm="Change subject status?">
                                    <?= $subject['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

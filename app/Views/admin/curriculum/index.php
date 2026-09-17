<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('headerActions') ?>
<a href="<?= site_url('admin/curriculum/create') ?>" class="ac-btn ac-btn-primary">
    <span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">add</span>
    <span>Add Curriculum</span>
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Curricula</h2>
        <p class="ac-page-header__sub">Curriculum sets per program and academic year — subjects are assigned to these from Curriculum Subjects.</p>
    </div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<?php if (empty($curriculum)): ?>
    <div class="ac-empty-block">
        <p>No curricula yet.</p>
        <p style="margin-top:6px;"><a href="<?= site_url('admin/curriculum/create') ?>">Add the first curriculum</a> to get started.</p>
    </div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Curriculum</th>
                    <th>Program</th>
                    <th>Academic Year</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($curriculum as $item): ?>
                    <tr>
                        <td><span class="ac-cell-bold"><?= esc($item['curriculum_code']) ?></span></td>
                        <td><?= esc($item['curriculum_name']) ?></td>
                        <td class="ac-muted"><?= esc($item['program_name']) ?></td>
                        <td class="ac-muted"><?= esc($item['academic_year']) ?></td>
                        <td>
                            <span class="ac-status-dot <?= $item['is_active'] ? 'ac-status-dot--active' : 'ac-status-dot--inactive' ?>">
                                <?= $item['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="ac-actions-cell">
                            <a href="<?= site_url('admin/curriculum/edit/' . $item['curriculum_id']) ?>" class="ac-btn-outline">Edit</a>
                            <form method="post" action="<?= site_url('admin/curriculum/toggle/' . $item['curriculum_id']) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="ac-btn-outline" onclick="return confirm('Change curriculum status?')">
                                    <?= $item['is_active'] ? 'Deactivate' : 'Activate' ?>
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

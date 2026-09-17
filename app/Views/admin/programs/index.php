<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('headerActions') ?>
<a href="<?= site_url('admin/programs/create') ?>" class="ac-btn ac-btn-primary">
    <span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">add</span>
    <span>Add Program</span>
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Programs</h2>
        <p class="ac-page-header__sub">Degree programs offered across colleges — the basis for curricula and student sections.</p>
    </div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<?php if (empty($programs)): ?>
    <div class="ac-empty-block">
        <p>No programs yet.</p>
        <p style="margin-top:6px;"><a href="<?= site_url('admin/programs/create') ?>">Add the first program</a> to get started.</p>
    </div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($programs as $program): ?>
                    <tr>
                        <td><span class="ac-cell-bold"><?= esc($program['program_code']) ?></span></td>
                        <td><?= esc($program['program_name']) ?></td>
                        <td class="ac-muted"><?= esc($program['description']) ?></td>
                        <td>
                            <span class="ac-status-dot <?= $program['is_active'] ? 'ac-status-dot--active' : 'ac-status-dot--inactive' ?>">
                                <?= $program['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="ac-actions-cell">
                            <a href="<?= site_url('admin/programs/edit/' . $program['program_id']) ?>" class="ac-btn-outline">Edit</a>
                            <form method="post" action="<?= site_url('admin/programs/toggle-status/' . $program['program_id']) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="ac-btn-outline" onclick="return confirm('Change program status?')">
                                    <?= $program['is_active'] ? 'Deactivate' : 'Activate' ?>
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

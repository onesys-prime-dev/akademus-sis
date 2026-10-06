<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Curriculum Subjects</h2>
        <p class="ac-page-header__sub">Pick a curriculum to manage which subjects belong to it, by year level and semester.</p>
    </div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<?php if (empty($curricula)): ?>
    <div class="ac-empty-block">
        <p>No curricula yet.</p>
        <p style="margin-top:6px;"><a href="<?= site_url('admin/curriculum/create') ?>">Add a curriculum</a> first, then come back here to assign its subjects.</p>
    </div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead>
                <tr>
                    <th>Curriculum</th>
                    <th>Program</th>
                    <th>Subjects assigned</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($curricula as $c): ?>
                    <tr>
                        <td>
                            <span class="ac-cell-bold"><?= esc($c['curriculum_code']) ?></span>
                            <div class="ac-muted" style="font-size:12px;"><?= esc($c['curriculum_name']) ?></div>
                        </td>
                        <td class="ac-muted"><?= esc($c['program_name'] ?? 'Unassigned') ?></td>
                        <td class="ac-muted"><?= (int) $c['subject_count'] ?></td>
                        <td class="ac-actions-cell">
                            <a href="<?= site_url('admin/curriculum-subjects/manage/' . $c['curriculum_id']) ?>" class="ac-btn-outline">Manage subjects</a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

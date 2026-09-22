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
                    <th>Version</th>
                    <th>Lifecycle</th>
                    <th>Visibility</th>
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
                        <td class="ac-muted">
                            v<?= esc($item['version_number']) ?>
                            <?= $item['is_current'] ? '<span class="ac-status-dot ac-status-dot--active" style="margin-left:4px;">Current</span>' : '' ?>
                        </td>
                        <td>
                            <?php
                                $lifecycleClass = $item['status'] === 'published' ? 'active' : ($item['status'] === 'archived' ? 'inactive' : 'pending');
                            ?>
                            <span class="ac-status-dot ac-status-dot--<?= $lifecycleClass ?>"><?= esc(ucfirst($item['status'])) ?></span>
                        </td>
                        <td>
                            <span class="ac-status-dot <?= $item['is_active'] ? 'ac-status-dot--active' : 'ac-status-dot--inactive' ?>">
                                <?= $item['is_active'] ? 'Visible' : 'Hidden' ?>
                            </span>
                        </td>
                        <td class="ac-actions-cell">
                            <a href="<?= site_url('admin/curriculum/edit/' . $item['curriculum_id']) ?>" class="ac-btn-outline"><?= $item['status'] === 'draft' ? 'Edit' : 'View' ?></a>
                            <?php if ($item['status'] === 'draft'): ?>
                                <form method="post" action="<?= site_url('admin/curriculum/publish/' . $item['curriculum_id']) ?>" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ac-btn-outline" onclick="return confirm('Publish this curriculum? It becomes immutable and the current version for its program.')">Publish</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= site_url('admin/curriculum/new-version/' . $item['curriculum_id']) ?>" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ac-btn-outline">New Version</button>
                                </form>
                            <?php endif ?>
                            <form method="post" action="<?= site_url('admin/curriculum/toggle/' . $item['curriculum_id']) ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <button type="submit" class="ac-btn-outline" onclick="return confirm('Change curriculum visibility?')">
                                    <?= $item['is_active'] ? 'Hide' : 'Show' ?>
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

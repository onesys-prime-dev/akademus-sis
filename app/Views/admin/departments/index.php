<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('headerActions') ?>
<button type="button" onclick="acOpenModal('modalCreateDept')" class="ac-btn ac-btn-primary">
    <span class="material-symbols-outlined" style="font-size:18px;">add</span>
    <span>New College</span>
</button>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h1 class="ac-page-header__title">Colleges</h1>
        <p class="ac-page-header__sub">Manage institution colleges, their deans, and departments.</p>
    </div>
</div>

<?php if (empty($departments)): ?>
    <div class="ac-empty-block">No colleges yet. Create the first one to get started.</div>
<?php else: ?>
    <section class="ac-tile-grid">
        <?php foreach ($departments as $dept): ?>
            <?php $deaned = (bool) $dept['dean']; ?>
            <a href="<?= site_url('admin/departments/' . $dept['dept_id']) ?>" class="ac-tile">
                <div class="ac-tile__icon" style="<?= $deaned ? '' : 'background:rgba(217,119,6,.15); color:#d97706;' ?>">
                    <span class="material-symbols-outlined icon-fill">domain</span>
                </div>
                <div>
                    <h3 class="ac-tile__title"><?= esc($dept['code']) ?> &middot; <?= esc($dept['name']) ?></h3>
                    <p class="ac-tile__meta">
                        <?= esc($dept['dept_count']) ?> Dept<?= $dept['dept_count'] === 1 ? '' : 's' ?> &bull; <?= esc($dept['faculty_count']) ?> Faculty &bull;
                        <span style="color: <?= $deaned ? '#16a34a' : '#d97706' ?>; font-weight:700;">
                            <?= $deaned ? 'Has Dean' : 'No Dean' ?>
                        </span>
                    </p>
                </div>
            </a>
        <?php endforeach ?>
    </section>
<?php endif ?>

<!-- Create college modal -->
<div class="ac-modal" id="modalCreateDept">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title">Create College</div>
                <div class="ac-modal__subtitle">A college is a tenant — it owns its subjects, faculty, and schedules.</div>
            </div>
            <button type="button" class="ac-modal__close" onclick="acCloseModal('modalCreateDept')">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/departments/store') ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>College Code</label>
                    <input type="text" name="code" class="ac-input" placeholder="ENG" value="<?= old('code') ?>" required>
                </div>
                <div class="ac-field">
                    <label>College Name</label>
                    <input type="text" name="name" class="ac-input" placeholder="College of Engineering" value="<?= old('name') ?>" required>
                </div>
            </div>
            <div class="ac-modal__actions">
                <button type="button" class="ac-btn ac-btn-secondary" onclick="acCloseModal('modalCreateDept')">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (session('errors')): ?>
<script>document.getElementById('modalCreateDept').classList.add('is-open');</script>
<?php endif ?>
<?= $this->endSection() ?>

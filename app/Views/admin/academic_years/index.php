<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<button type="button" class="ac-btn ac-btn-primary" onclick="acOpenModal('modalAddAy')">
    <?= admin_icon('plus', 16) ?> Add Academic Year
</button>

<?php if (empty($years)): ?>
    <div class="ac-empty-block" style="margin-top:16px;">No academic years yet.</div>
<?php else: ?>
    <div class="ac-grid ac-grid--sm" style="margin-top:16px;">
        <?php foreach ($years as $ay): ?>
            <div class="ac-card" style="<?= $ay['is_active'] ? 'border-color:#bbf7d0;' : '' ?>">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="ac-dept-name"><?= esc($ay['label']) ?></span>
                    <?php if ($ay['is_active']): ?>
                        <span class="ac-badge-active">ACTIVE</span>
                    <?php endif ?>
                </div>
                <div class="ac-dept-meta"><?= esc($ay['semester']) ?> Semester</div>

                <?php if (! $ay['is_active']): ?>
                    <form method="post" action="<?= site_url('admin/academic-years/set-active/' . $ay['ay_id']) ?>" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <button type="submit" class="ac-btn ac-btn-secondary ac-btn-block">Set as active term</button>
                    </form>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<!-- Add academic year modal -->
<div class="ac-modal" id="modalAddAy">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div class="ac-modal__title">Add Academic Year</div>
            <button type="button" class="ac-modal__close" onclick="acCloseModal('modalAddAy')"><?= admin_icon('x', 16) ?></button>
        </div>

        <form method="post" action="<?= site_url('admin/academic-years/store') ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Academic Year</label>
                    <input type="text" name="label" class="ac-input" placeholder="2027-2028" value="<?= old('label') ?>" required>
                </div>
                <div class="ac-field">
                    <label>Semester</label>
                    <select name="semester" class="ac-input" required>
                        <option value="1st">1st</option>
                        <option value="2nd">2nd</option>
                        <option value="Summer">Summer</option>
                    </select>
                </div>
            </div>
            <div class="ac-modal__actions">
                <button type="button" class="ac-btn ac-btn-secondary" onclick="acCloseModal('modalAddAy')">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Add</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (session('errors')): ?>
<script>document.getElementById('modalAddAy').classList.add('is-open');</script>
<?php endif ?>
<?= $this->endSection() ?>

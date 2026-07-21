<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<button type="button" class="ac-btn ac-btn-primary" onclick="acOpenModal('modalCreateDept')">
    <?= admin_icon('plus', 16) ?> Create Department
</button>

<?php if (empty($departments)): ?>
    <div class="ac-empty-block" style="margin-top:16px;">No departments yet. Create the first one to get started.</div>
<?php else: ?>
    <div class="ac-grid" style="margin-top:16px;">
        <?php foreach ($departments as $dept): ?>
            <div class="ac-card">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="ac-dept-code"><?= esc($dept['code']) ?></span>
                    <span class="ac-dept-name"><?= esc($dept['name']) ?></span>
                </div>
                <div class="ac-dept-meta"><?= esc($dept['faculty_count']) ?> faculty</div>

                <?php if ($dept['chair']): ?>
                    <div class="ac-chair-box ac-chair-box--filled" style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div class="ac-chair-box__label">Chairperson</div>
                            <div class="ac-chair-box__name"><?= esc($dept['chair']['username']) ?></div>
                            <div class="ac-chair-box__email"><?= esc($dept['chair']['email']) ?></div>
                        </div>
                        <button type="button" class="ac-icon-btn" title="Reassign" onclick="acOpenModal('modalChair<?= $dept['dept_id'] ?>')"><?= admin_icon('edit', 14) ?></button>
                    </div>
                <?php else: ?>
                    <div class="ac-chair-box ac-chair-box--empty">
                        <div class="ac-chair-box__label">Chairperson</div>
                        <button type="button" class="ac-btn ac-btn-warning ac-btn-block" style="margin-top:7px;" onclick="acOpenModal('modalChair<?= $dept['dept_id'] ?>')">
                            <?= admin_icon('plus', 14) ?> Assign Chairperson
                        </button>
                    </div>
                <?php endif ?>
            </div>

            <!-- Assign / reassign chairperson modal -->
            <div class="ac-modal" id="modalChair<?= $dept['dept_id'] ?>">
                <div class="ac-modal__panel">
                    <div class="ac-modal__header">
                        <div>
                            <div class="ac-modal__title">Assign Chairperson</div>
                            <div class="ac-modal__subtitle">Creates a Chairperson account scoped to <?= esc($dept['name']) ?>.</div>
                        </div>
                        <button type="button" class="ac-modal__close" onclick="acCloseModal('modalChair<?= $dept['dept_id'] ?>')"><?= admin_icon('x', 16) ?></button>
                    </div>

                    <form method="post" action="<?= site_url('admin/departments/store-chair/' . $dept['dept_id']) ?>">
                        <?= csrf_field() ?>
                        <div class="ac-modal__body">
                            <div class="ac-field">
                                <label>Full Name</label>
                                <input type="text" name="username" class="ac-input" placeholder="Prof. Maria Reyes" value="<?= esc($dept['chair']['username'] ?? '') ?>" required>
                            </div>
                            <div class="ac-field">
                                <label>Email</label>
                                <input type="email" name="email" class="ac-input" placeholder="mreyes@spum.edu.ph" value="<?= esc($dept['chair']['email'] ?? '') ?>" required>
                            </div>
                            <div class="ac-notice">
                                <span style="color:#1d4ed8; margin-top:1px;"><?= admin_icon('alert', 14) ?></span>
                                <span class="ac-notice__text">This will set the account as <?= esc($dept['name']) ?>'s chairperson, replacing the current one if any.</span>
                            </div>
                        </div>
                        <div class="ac-modal__actions">
                            <button type="button" class="ac-btn ac-btn-secondary" onclick="acCloseModal('modalChair<?= $dept['dept_id'] ?>')">Cancel</button>
                            <button type="submit" class="ac-btn ac-btn-primary"><?= $dept['chair'] ? 'Reassign' : 'Assign' ?></button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<!-- Create department modal -->
<div class="ac-modal" id="modalCreateDept">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title">Create Department</div>
                <div class="ac-modal__subtitle">A department is a tenant — it owns its subjects, faculty, and schedules.</div>
            </div>
            <button type="button" class="ac-modal__close" onclick="acCloseModal('modalCreateDept')"><?= admin_icon('x', 16) ?></button>
        </div>

        <form method="post" action="<?= site_url('admin/departments/store') ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Department Code</label>
                    <input type="text" name="code" class="ac-input" placeholder="ENG" value="<?= old('code') ?>" required>
                </div>
                <div class="ac-field">
                    <label>Department Name</label>
                    <input type="text" name="name" class="ac-input" placeholder="Engineering" value="<?= old('name') ?>" required>
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

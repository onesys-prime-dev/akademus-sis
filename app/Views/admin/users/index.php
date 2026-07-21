<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('headerActions') ?>
<button type="button" onclick="acOpenModal('modalCreateAccount')" class="ac-btn ac-btn-primary">
    <span class="material-symbols-outlined" style="font-size:18px;">add</span>
    <span>Create Account</span>
</button>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">User Accounts</h2>
        <p class="ac-page-header__sub">Manage all user profiles and roles across the institution.</p>
    </div>

    <div class="ac-segmented">
        <a href="<?= site_url('admin/users') ?>" class="<?= $roleFilter === 'all' ? 'is-active' : '' ?>">All</a>
        <?php foreach ($roles as $r): ?>
            <a href="<?= site_url('admin/users?role=' . $r) ?>" class="<?= $roleFilter === $r ? 'is-active' : '' ?>"><?= esc(ucfirst($r)) ?></a>
        <?php endforeach ?>
    </div>
</div>

<div class="ac-dtable ac-utable">

    <div class="ac-dtable__head">
        <div>Name</div>
        <div>Email</div>
        <div>Role</div>
        <div>Department</div>
        <div>Status</div>
        <div style="text-align:right;">Actions</div>
    </div>

    <?php if (empty($users)): ?>
        <div class="ac-empty-panel">No accounts for this role.</div>
    <?php endif ?>

    <?php foreach ($users as $u): ?>
        <?php $initials = strtoupper(substr($u['username'], 0, 1) . (strpos($u['username'], ' ') !== false ? substr(strrchr($u['username'], ' '), 1, 1) : '')); ?>
        <div class="ac-dtable__row">

            <div class="ac-user-cell">
                <div class="ac-avatar ac-avatar--primary"><?= esc($initials) ?></div>
                <div>
                    <p class="ac-user-cell__name"><?= esc($u['username']) ?></p>
                    <p class="ac-user-cell__id">ID #<?= esc($u['id']) ?></p>
                </div>
            </div>

            <div class="ac-cell-muted"><?= esc($u['email']) ?></div>

            <div><span class="ac-badge ac-badge--<?= esc($u['group']) ?>"><?= esc(ucfirst($u['group'])) ?></span></div>

            <div class="ac-cell"><?= esc($u['dept_name'] ?? '—') ?></div>

            <div>
                <span class="ac-status-dot <?= $u['active'] ? 'ac-status-dot--active' : 'ac-status-dot--pending' ?>">
                    <?= $u['active'] ? 'Active' : 'Pending' ?>
                </span>
            </div>

            <div class="ac-actions-cell">
                <?php if (! $u['active']): ?>
                    <form method="post" action="<?= site_url('admin/users/activate/' . $u['id']) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="ac-btn-outline">Activate</button>
                    </form>
                <?php endif ?>
            </div>

        </div>
    <?php endforeach ?>

    <div class="ac-dtable__footer">Showing <?= count($users) ?> of <?= count($users) ?> users</div>

</div>

<!-- Create account modal -->
<div class="ac-modal" id="modalCreateAccount">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title">Create Account</div>
                <div class="ac-modal__subtitle">An invite is created; the user sets their password on first sign-in.</div>
            </div>
            <button type="button" onclick="acCloseModal('modalCreateAccount')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/users/store') ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Full Name</label>
                    <input type="text" name="username" class="ac-input" placeholder="Prof. Maria Reyes" value="<?= old('username') ?>" required>
                </div>
                <div class="ac-field">
                    <label>Email</label>
                    <input type="email" name="email" class="ac-input" placeholder="mreyes@spum.edu.ph" value="<?= old('email') ?>" required>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="ac-field">
                        <label>Role</label>
                        <select name="role" id="acRoleSelect" class="ac-input" required onchange="acToggleDeptField()">
                            <option value="faculty" <?= old('role') === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                            <option value="chairperson" <?= old('role') === 'chairperson' ? 'selected' : '' ?>>Chairperson</option>
                            <option value="student" <?= old('role') === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="admin" <?= old('role') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="ac-field" id="acDeptField">
                        <label>Department</label>
                        <select name="department_id" class="ac-input">
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= esc($d['dept_id']) ?>"><?= esc($d['code']) ?> — <?= esc($d['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <div class="ac-notice" id="acChairNotice" style="display:none;">
                    <span class="material-symbols-outlined" style="font-size:16px; color:#1d4ed8; margin-top:1px;">info</span>
                    <span class="ac-notice__text">This will set the account as the selected department's chairperson, replacing the current one if any.</span>
                </div>
            </div>

            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalCreateAccount')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Create &amp; Invite</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function acToggleDeptField() {
        var role = document.getElementById('acRoleSelect').value;
        document.getElementById('acDeptField').style.display = role === 'admin' ? 'none' : 'block';
        document.getElementById('acChairNotice').style.display = role === 'chairperson' ? 'flex' : 'none';
    }
    document.addEventListener('DOMContentLoaded', acToggleDeptField);
</script>
<?php if (session('errors')): ?>
<script>document.getElementById('modalCreateAccount').classList.add('is-open'); acToggleDeptField();</script>
<?php endif ?>
<?= $this->endSection() ?>

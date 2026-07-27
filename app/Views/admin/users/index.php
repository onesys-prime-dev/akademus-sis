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
                <?php else: ?>
                    <form method="post" action="<?= site_url('admin/users/deactivate/' . $u['id']) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="ac-icon-btn ac-icon-btn--danger" title="Deactivate">
                            <span class="material-symbols-outlined">block</span>
                        </button>
                    </form>
                <?php endif ?>

                <button type="button" class="ac-icon-btn"
                        title="Edit"
                        onclick='acOpenEdit(<?= json_encode([
                            'id'            => (int) $u['id'],
                            'username'      => $u['username'],
                            'email'         => $u['email'],
                            'group'         => $u['group'],
                            'department_id' => $u['department_id'],
                        ]) ?>)'>
                    <span class="material-symbols-outlined">edit</span>
                </button>

                <button type="button" class="ac-icon-btn"
                        title="Reset password"
                        onclick="acOpenResetPassword(<?= (int) $u['id'] ?>, '<?= esc($u['username'], 'js') ?>')">
                    <span class="material-symbols-outlined">key</span>
                </button>
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
                <div class="ac-modal__subtitle">Set a temporary password, or leave it blank to auto-generate one.</div>
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

                <div class="ac-field">
                    <label>Password</label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" name="password" id="acNewPassword" class="ac-input" placeholder="Leave blank to auto-generate" minlength="8" value="<?= old('password') ?>">
                        <button type="button" class="ac-btn-outline" style="white-space:nowrap;" onclick="acGeneratePassword()">Generate</button>
                    </div>
                    <p style="font-size:11.5px; color:#515f74; margin-top:4px;">Minimum 8 characters. You'll see it again right after creating the account.</p>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="ac-field">
                        <label>Role</label>
                        <select name="role" id="acRoleSelect" class="ac-input" required onchange="acToggleDeptField()">
                            <option value="faculty" <?= old('role') === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                            <option value="dean" <?= old('role') === 'dean' ? 'selected' : '' ?>>Dean</option>
                            <option value="student" <?= old('role') === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="admin" <?= old('role') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <p style="font-size:11.5px; color:#515f74; margin-top:4px;">Chairpersons are assigned from a department's own page.</p>
                    </div>
                    <div class="ac-field" id="acDeptField">
                        <label>College</label>
                        <select name="department_id" class="ac-input">
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= esc($d['dept_id']) ?>"><?= esc($d['code']) ?> — <?= esc($d['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <div class="ac-notice" id="acChairNotice" style="display:none;">
                    <span class="material-symbols-outlined" style="font-size:16px; color:#1d4ed8; margin-top:1px;">info</span>
                    <span class="ac-notice__text">This will set the account as the selected college's dean, replacing the current one if any.</span>
                </div>
            </div>

            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalCreateAccount')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Create &amp; Invite</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit account modal -->
<div class="ac-modal" id="modalEditAccount">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title">Edit Account</div>
                <div class="ac-modal__subtitle" id="acEditSubtitle"></div>
            </div>
            <button type="button" onclick="acCloseModal('modalEditAccount')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" id="acEditForm">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Full Name</label>
                    <input type="text" name="username" id="acEditUsername" class="ac-input" required>
                </div>
                <div class="ac-field">
                    <label>Email</label>
                    <input type="email" name="email" id="acEditEmail" class="ac-input" required>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" id="acEditRoleRow">
                    <div class="ac-field">
                        <label>Role</label>
                        <select name="role" id="acEditRoleSelect" class="ac-input" required onchange="acToggleEditDeptField()">
                            <option value="faculty">Faculty</option>
                            <option value="dean">Dean</option>
                            <option value="student">Student</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="ac-field" id="acEditDeptField">
                        <label>College</label>
                        <select name="department_id" id="acEditDeptSelect" class="ac-input">
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= esc($d['dept_id']) ?>"><?= esc($d['code']) ?> — <?= esc($d['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <div class="ac-notice" id="acEditChairNotice" style="display:none;">
                    <span class="material-symbols-outlined" style="font-size:16px; color:#1d4ed8; margin-top:1px;">info</span>
                    <span class="ac-notice__text">This is the department's chairperson. Reassign chairpersons from the department's own page — only their name and email can be changed here.</span>
                </div>
            </div>

            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalEditAccount')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset password confirm modal -->
<div class="ac-modal" id="modalResetPassword">
    <div class="ac-modal__panel" style="max-width:400px;">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title">Reset Password</div>
                <div class="ac-modal__subtitle">A new temporary password will be generated for <b id="acResetUsername"></b>.</div>
            </div>
            <button type="button" onclick="acCloseModal('modalResetPassword')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" id="acResetForm">
            <?= csrf_field() ?>
            <div class="ac-notice" style="margin-top:16px;">
                <span class="material-symbols-outlined" style="font-size:16px; color:#1d4ed8; margin-top:1px;">info</span>
                <span class="ac-notice__text">Their current password stops working immediately. Share the new one with them directly — it's only shown once.</span>
            </div>
            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalResetPassword')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Credential reveal modal: shown once right after create/reset -->
<?php $cred = session('credential'); ?>
<div class="ac-modal" id="modalCredential">
    <div class="ac-modal__panel" style="max-width:400px;">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title"><?= esc($cred['heading'] ?? '') ?></div>
                <div class="ac-modal__subtitle">Copy this now — it won't be shown again.</div>
            </div>
            <button type="button" onclick="acCloseModal('modalCredential')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <div class="ac-modal__body">
            <div class="ac-cred-row">
                <span class="ac-cred-row__label">Name</span>
                <span class="ac-cred-row__value"><?= esc($cred['username'] ?? '') ?></span>
            </div>
            <div class="ac-cred-row">
                <span class="ac-cred-row__label">Email</span>
                <span class="ac-cred-row__value"><?= esc($cred['email'] ?? '') ?></span>
            </div>

            <div>
                <label style="font-size:11px; font-weight:700; color:#64748b; margin-bottom:5px; display:block;">Temporary Password</label>
                <div class="ac-cred-pass">
                    <span class="ac-cred-pass__value" id="acCredPasswordValue"><?= esc($cred['password'] ?? '') ?></span>
                    <button type="button" class="ac-cred-copy" onclick="acCopyPassword()">
                        <span class="material-symbols-outlined">content_copy</span>
                        <span id="acCredCopyLabel">Copy</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="ac-modal__actions">
            <button type="button" onclick="acCloseModal('modalCredential')" class="ac-btn ac-btn-primary">Done</button>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    var AC_USERS_BASE = '<?= site_url('admin/users') ?>';

    function acToggleDeptField() {
        var role = document.getElementById('acRoleSelect').value;
        document.getElementById('acDeptField').style.display = role === 'admin' ? 'none' : 'block';
        document.getElementById('acChairNotice').style.display = role === 'dean' ? 'flex' : 'none';
    }

    function acGeneratePassword() {
        var bytes = new Uint8Array(9);
        crypto.getRandomValues(bytes);
        var pass = Array.from(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
        document.getElementById('acNewPassword').value = pass;
    }

    function acOpenEdit(user) {
        document.getElementById('acEditForm').action = AC_USERS_BASE + '/update/' + user.id;
        document.getElementById('acEditSubtitle').textContent = 'ID #' + user.id;
        document.getElementById('acEditUsername').value = user.username;
        document.getElementById('acEditEmail').value = user.email;

        var isChair = user.group === 'chairperson';
        document.getElementById('acEditRoleRow').style.display = isChair ? 'none' : 'grid';
        document.getElementById('acEditChairNotice').style.display = isChair ? 'flex' : 'none';

        if (!isChair) {
            document.getElementById('acEditRoleSelect').value = user.group;
            document.getElementById('acEditDeptSelect').value = user.department_id || '';
            acToggleEditDeptField();
        }

        acOpenModal('modalEditAccount');
    }

    function acToggleEditDeptField() {
        var role = document.getElementById('acEditRoleSelect').value;
        document.getElementById('acEditDeptField').style.display = role === 'admin' ? 'none' : 'block';
    }

    function acOpenResetPassword(id, username) {
        document.getElementById('acResetForm').action = AC_USERS_BASE + '/reset-password/' + id;
        document.getElementById('acResetUsername').textContent = username;
        acOpenModal('modalResetPassword');
    }

    function acCopyPassword() {
        var value = document.getElementById('acCredPasswordValue').textContent;
        navigator.clipboard.writeText(value).then(function () {
            var label = document.getElementById('acCredCopyLabel');
            label.textContent = 'Copied!';
            setTimeout(function () { label.textContent = 'Copy'; }, 1800);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        acToggleDeptField();
        <?php if ($cred): ?>
        acOpenModal('modalCredential');
        <?php endif ?>
    });
</script>
<?php if (session('errors')): ?>
<script>document.getElementById('modalCreateAccount').classList.add('is-open'); acToggleDeptField();</script>
<?php endif ?>
<?= $this->endSection() ?>

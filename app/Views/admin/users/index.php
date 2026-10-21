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
        <a href="<?= site_url('admin/users') . ($search !== '' ? '?q=' . urlencode($search) : '') ?>" class="<?= $roleFilter === 'all' ? 'is-active' : '' ?>">All</a>
        <?php foreach ($roles as $r): ?>
            <a href="<?= site_url('admin/users?role=' . $r) . ($search !== '' ? '&q=' . urlencode($search) : '') ?>" class="<?= $roleFilter === $r ? 'is-active' : '' ?>"><?= esc(ucfirst($r)) ?></a>
        <?php endforeach ?>
    </div>
</div>

<form method="get" action="<?= site_url('admin/users') ?>" class="ac-search-form" style="display:flex; gap:10px; margin-bottom:16px; max-width:420px;">
    <?php if ($roleFilter !== 'all'): ?>
        <input type="hidden" name="role" value="<?= esc($roleFilter) ?>">
    <?php endif ?>
    <div class="ac-field" style="margin:0; flex:1;">
        <input type="text" name="q" class="ac-input" placeholder="Search by name, email, or student number" value="<?= esc($search) ?>">
    </div>
    <button type="submit" class="ac-btn ac-btn-secondary">Search</button>
    <?php if ($search !== ''): ?>
        <a href="<?= site_url('admin/users') . ($roleFilter !== 'all' ? '?role=' . $roleFilter : '') ?>" class="ac-btn ac-btn-secondary" style="white-space:nowrap;">Clear</a>
    <?php endif ?>
</form>

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
        <div class="ac-empty-panel">
            <strong>No accounts in this view</strong>
            <p><?= $search !== '' ? 'No accounts match "' . esc($search) . '". Try a different name, email, or student number.' : 'Choose another role, or use Create Account to add someone to Akademus.' ?></p>
        </div>
    <?php endif ?>
    <?php foreach ($users as $u): ?>
        <?php $initials = strtoupper(substr($u['username'], 0, 1) . (strpos($u['username'], ' ') !== false ? substr(strrchr($u['username'], ' '), 1, 1) : '')); ?>
        <div class="ac-dtable__row">

            <div class="ac-user-cell" data-label="Name">
                <div class="ac-avatar" style="background:<?= esc(avatar_color($u['username']), 'attr') ?>;color:#fff;" aria-hidden="true"><?= esc($initials) ?></div>
                <div>
                    <p class="ac-user-cell__name"><?= esc($u['username']) ?></p>
                    <p class="ac-user-cell__id">ID #<?= esc($u['id']) ?></p>
                </div>
            </div>

            <div class="ac-cell-muted" data-label="Email"><?= esc($u['email']) ?></div>

            <div data-label="Role"><span class="ac-badge ac-badge--<?= esc($u['group']) ?>"><?= esc(ucfirst($u['group'])) ?></span></div>

            <div class="ac-cell" data-label="Department">
                <?= esc($u['dept_name'] ?? '—') ?>
                <?php if ($u['group'] === 'faculty' && $u['employment_status']): ?>
                    <br><span style="font-size:11px; color:#4338ca;"><?= $u['employment_status'] === 'full_time' ? 'Full-Time' : 'Part-Time' ?></span>
                <?php endif ?>
            </div>

            <div data-label="Status">
                <span class="ac-status-dot <?= $u['active'] ? 'ac-status-dot--active' : 'ac-status-dot--pending' ?>">
                    <?= $u['active'] ? 'Active' : 'Pending' ?>
                </span>
            </div>

            <div class="ac-actions-cell" data-label="Actions">
                <?php if (! $u['active']): ?>
                    <form method="post" action="<?= site_url('admin/users/activate/' . $u['id']) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="ac-btn-outline">Activate</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= site_url('admin/users/deactivate/' . $u['id']) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="ac-icon-btn ac-icon-btn--danger" title="Deactivate" aria-label="Deactivate <?= esc($u['username']) ?>">
                            <span class="material-symbols-outlined" aria-hidden="true">block</span>
                        </button>
                    </form>
                <?php endif ?>

                <button type="button" class="ac-icon-btn"
                        title="Edit"
                        onclick='acOpenEdit(<?= json_encode([
                            'id'                => (int) $u['id'],
                            'username'          => $u['username'],
                            'email'             => $u['email'],
                            'group'             => $u['group'],
                            // Cast every id field so the JS side never has to
                            // guess whether it got a number or a numeric
                            // string out of MySQLi — AC_SECTIONS/AC_SUBDEPTS
                            // below are built the same way, so a strict ===
                            // lookup between them is guaranteed to work.
                            'department_id'     => $u['department_id'] !== null ? (int) $u['department_id'] : null,
                            'section_id'        => $u['section_id'] !== null ? (int) $u['section_id'] : null,
                            'employment_status' => $u['employment_status'],
                            'personal'          => [
                                'date_of_birth'            => $u['date_of_birth'],
                                'gender'                   => $u['gender'],
                                'home_address'             => $u['home_address'],
                                'city'                     => $u['city'],
                                'emergency_contact_name'   => $u['emergency_contact_name'],
                                'emergency_contact_number' => $u['emergency_contact_number'],
                            ],
                        ]) ?>)'
                        aria-label="Edit <?= esc($u['username']) ?>">
                    <span class="material-symbols-outlined" aria-hidden="true">edit</span>
                </button>

                <button type="button" class="ac-icon-btn"
                        title="Reset password"
                        onclick="acOpenResetPassword(<?= (int) $u['id'] ?>, '<?= esc($u['username'], 'js') ?>')"
                        aria-label="Reset password for <?= esc($u['username']) ?>">
                    <span class="material-symbols-outlined" aria-hidden="true">key</span>
                </button>

                <?php if ($u['group'] === 'student' && $u['section_id'] !== null): ?>
                    <form method="post" action="<?= site_url('admin/users/sync-enrollment/' . $u['id']) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="ac-icon-btn"
                                title="Sync enrollment — enroll into any newly-plotted subjects for their section"
                                aria-label="Sync enrollment for <?= esc($u['username']) ?>">
                            <span class="material-symbols-outlined" aria-hidden="true">sync</span>
                        </button>
                    </form>
                <?php endif ?>
            </div>

        </div>
    <?php endforeach ?>

    <?php
        $rangeStart = $totalUsers === 0 ? 0 : (($page - 1) * $perPage) + 1;
        $rangeEnd   = min($page * $perPage, $totalUsers);
        $baseParams = array_filter(['role' => $roleFilter !== 'all' ? $roleFilter : null, 'q' => $search !== '' ? $search : null]);
        $baseQuery  = ($baseParams === [] ? '?' : '?' . http_build_query($baseParams) . '&') . 'page=';
    ?>
    <div class="ac-pagination">
        <span>Showing <?= $rangeStart ?>–<?= $rangeEnd ?> of <?= $totalUsers ?> users</span>

        <?php if ($totalPages > 1): ?>
            <div class="ac-pagination__pages">
                <a class="ac-pagination__link" href="<?= site_url('admin/users' . $baseQuery . max(1, $page - 1)) ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>" aria-label="Previous page">
                    <span class="material-symbols-outlined" style="font-size:16px;" aria-hidden="true">chevron_left</span>
                </a>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a class="ac-pagination__link <?= $p === $page ? 'is-active' : '' ?>" href="<?= site_url('admin/users' . $baseQuery . $p) ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
                <?php endfor ?>
                <a class="ac-pagination__link" href="<?= site_url('admin/users' . $baseQuery . min($totalPages, $page + 1)) ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>" aria-label="Next page">
                    <span class="material-symbols-outlined" style="font-size:16px;" aria-hidden="true">chevron_right</span>
                </a>
            </div>
        <?php endif ?>
    </div>

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

        <form method="post" action="<?= site_url('admin/users/store') ?>" id="formCreateAccount">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Full Name</label>
                    <input type="text" name="username" class="ac-input" placeholder="Prof. Maria Reyes" value="<?= old('username') ?>" required>
                </div>
                <div class="ac-field">
                    <label>Email</label>
                    <input type="email" name="email" class="ac-input" placeholder="name@example.com" value="<?= old('email') ?>" required>
                </div>

                <div class="ac-field" id="acPasswordField">
                    <label>Password</label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" name="password" id="acNewPassword" class="ac-input" placeholder="Leave blank to auto-generate" minlength="8" value="<?= old('password') ?>">
                        <button type="button" class="ac-btn-outline" style="white-space:nowrap;" onclick="acGeneratePassword()">Generate</button>
                    </div>
                    <p style="font-size:11.5px; color:var(--color-text-secondary); margin-top:4px;">Minimum 8 characters. You'll see it again right after creating the account.</p>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="ac-field">
                        <label>Role</label>
                        <select name="role" id="acRoleSelect" class="ac-input" required onchange="acToggleDeptField()">
                            <option value="faculty" <?= old('role') === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                            <option value="dean" <?= old('role') === 'dean' ? 'selected' : '' ?>>Dean</option>
                            <option value="chairperson" <?= old('role') === 'chairperson' ? 'selected' : '' ?>>Chairperson</option>
                            <option value="registrar" <?= old('role') === 'registrar' ? 'selected' : '' ?>>Registrar</option>
                            <option value="student" <?= old('role') === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="admin" <?= old('role') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="ac-field" id="acDeptField">
                        <label>College</label>
                        <select name="department_id" id="acCollegeSelect" class="ac-input" onchange="acCascadeSections(''); acCascadeChairDept();">
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= esc($d['dept_id']) ?>"><?= esc($d['code']) ?> — <?= esc($d['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <div class="ac-field" id="acChairDeptField" style="display:none;">
                    <label>Department to chair</label>
                    <select id="acChairDeptSelect" class="ac-input">
                        <option value="">— Select a department —</option>
                    </select>
                </div>

                <div class="ac-field" id="acEmpStatusField" style="display:none;">
                    <label>Employment Status</label>
                    <select name="employment_status" class="ac-input">
                        <option value="">Not set</option>
                        <option value="full_time" <?= old('employment_status') === 'full_time' ? 'selected' : '' ?>>Full-Time</option>
                        <option value="part_time" <?= old('employment_status') === 'part_time' ? 'selected' : '' ?>>Part-Time</option>
                    </select>
                </div>

                <div id="acSectionRow" style="display:none; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <div class="ac-field" id="acSubDeptField">
                        <label>Department</label>
                        <select id="acSubDeptSelect" class="ac-input" onchange="acCascadeSections('')">
                            <option value="">— Select a department —</option>
                        </select>
                    </div>
                    <div class="ac-field">
                        <label>Year Level</label>
                        <select id="acYearLevelSelect" class="ac-input" onchange="acCascadeSections('')">
                            <option value="">— Any —</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div class="ac-field" id="acSectionField">
                        <label>Section</label>
                        <select name="section_id" id="acSectionSelect" class="ac-input">
                            <option value="">— No section yet —</option>
                        </select>
                    </div>
                </div>
                <p id="acSectionHint" style="display:none; font-size:11.5px; color:var(--color-text-secondary); margin-top:-4px; margin-bottom:16px;">A section only appears here once its chairperson has plotted at least one class for it. Needed for block enrollment.</p>

                <div id="acPersonalRow" style="display:none;">
                    <p style="font-size:11.5px; font-weight:650; color:var(--color-text-secondary); margin:0 0 8px; text-transform:uppercase; letter-spacing:.04em;">Personal Details (optional — the student can also fill these in themselves)</p>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="ac-field">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" class="ac-input">
                        </div>
                        <div class="ac-field">
                            <label>Gender</label>
                            <input type="text" name="gender" class="ac-input" maxlength="20" placeholder="e.g. Male, Female">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="ac-field">
                            <label>Home Address</label>
                            <input type="text" name="home_address" class="ac-input" maxlength="255">
                        </div>
                        <div class="ac-field">
                            <label>City</label>
                            <input type="text" name="city" class="ac-input" maxlength="100">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="ac-field">
                            <label>Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="ac-input" maxlength="150">
                        </div>
                        <div class="ac-field">
                            <label>Emergency Contact Number</label>
                            <input type="text" name="emergency_contact_number" class="ac-input" maxlength="20">
                        </div>
                    </div>
                </div>

                <div class="ac-notice" id="acChairNotice" style="display:none;">
                    <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-info); margin-top:1px;">info</span>
                    <span class="ac-notice__text">This will set the account as the selected college's dean, replacing the current one if any.</span>
                </div>

                <div class="ac-notice" id="acChairDeptNotice" style="display:none;">
                    <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-info); margin-top:1px;">info</span>
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
                            <option value="registrar">Registrar</option>
                            <option value="student">Student</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="ac-field" id="acEditDeptField">
                        <label>College</label>
                        <select name="department_id" id="acEditDeptSelect" class="ac-input" onchange="acCascadeSections('Edit')">
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= esc($d['dept_id']) ?>"><?= esc($d['code']) ?> — <?= esc($d['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <div class="ac-field" id="acEditEmpStatusField" style="display:none;">
                    <label>Employment Status</label>
                    <select name="employment_status" id="acEditEmpStatusSelect" class="ac-input">
                        <option value="">Not set</option>
                        <option value="full_time">Full-Time</option>
                        <option value="part_time">Part-Time</option>
                    </select>
                </div>

                <div id="acEditSectionRow" style="display:none; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <div class="ac-field">
                        <label>Department</label>
                        <select id="acEditSubDeptSelect" class="ac-input" onchange="acCascadeSections('Edit')">
                            <option value="">— Select a department —</option>
                        </select>
                    </div>
                    <div class="ac-field">
                        <label>Year Level</label>
                        <select id="acEditYearLevelSelect" class="ac-input" onchange="acCascadeSections('Edit')">
                            <option value="">— Any —</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div class="ac-field">
                        <label>Section</label>
                        <select name="section_id" id="acEditSectionSelect" class="ac-input">
                            <option value="">— No section yet —</option>
                        </select>
                    </div>
                </div>
                <p id="acEditSectionHint" style="display:none; font-size:11.5px; color:var(--color-text-secondary); margin-top:-4px; margin-bottom:16px;">A section only appears here once its chairperson has plotted at least one class for it.</p>

                <div id="acEditPersonalRow" style="display:none;">
                    <p style="font-size:11.5px; font-weight:650; color:var(--color-text-secondary); margin:0 0 8px; text-transform:uppercase; letter-spacing:.04em;">Personal Details</p>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="ac-field">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" id="acEditDob" class="ac-input">
                        </div>
                        <div class="ac-field">
                            <label>Gender</label>
                            <input type="text" name="gender" id="acEditGender" class="ac-input" maxlength="20" placeholder="e.g. Male, Female">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="ac-field">
                            <label>Home Address</label>
                            <input type="text" name="home_address" id="acEditHomeAddress" class="ac-input" maxlength="255">
                        </div>
                        <div class="ac-field">
                            <label>City</label>
                            <input type="text" name="city" id="acEditCity" class="ac-input" maxlength="100">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="ac-field">
                            <label>Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" id="acEditEmName" class="ac-input" maxlength="150">
                        </div>
                        <div class="ac-field">
                            <label>Emergency Contact Number</label>
                            <input type="text" name="emergency_contact_number" id="acEditEmNumber" class="ac-input" maxlength="20">
                        </div>
                    </div>
                </div>

                <div class="ac-notice" id="acEditChairNotice" style="display:none;">
                    <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-info); margin-top:1px;">info</span>
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
                <div class="ac-modal__subtitle">Set a new password for <b id="acResetUsername"></b>, or leave it blank to auto-generate one.</div>
            </div>
            <button type="button" onclick="acCloseModal('modalResetPassword')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" id="acResetForm">
            <?= csrf_field() ?>
            <div class="ac-field" style="margin-top:16px;">
                <label>New Password</label>
                <div style="display:flex; gap:8px;">
                    <input type="text" name="password" id="acResetPassword" class="ac-input" placeholder="Leave blank to auto-generate" minlength="8">
                    <button type="button" class="ac-btn-outline" style="white-space:nowrap;" onclick="acGeneratePassword('acResetPassword')">Generate</button>
                </div>
                <p style="font-size:11.5px; color:var(--color-text-secondary); margin-top:4px;">Minimum 8 characters if set manually.</p>
            </div>
            <div class="ac-notice" style="margin-top:12px;">
                <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-info); margin-top:1px;">info</span>
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
                <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); margin-bottom:5px; display:block;">Temporary Password</label>
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
    var AC_SUBDEPT_BASE = '<?= site_url('admin/sub-departments') ?>';

    // College -> Department -> Section cascade data. A section carries a
    // program display-name string, not an FK, so this list is pre-resolved
    // server-side (UserController::index) to each section's sub_dept_id /
    // college_id via a join through `programs`.
    var AC_SUBDEPTS = <?= json_encode(array_map(static fn (array $sd) => [
        'id'        => (int) $sd['sub_dept_id'],
        'label'     => $sd['code'] . ' — ' . $sd['name'],
        'college_id' => (int) $sd['college_id'],
    ], $subDepartments)) ?>;

    var AC_SECTIONS = <?= json_encode(array_map(static fn (array $s) => [
        'id'          => (int) $s['section_id'],
        'label'       => $s['program'] . ' · ' . $s['year_level'] . ' · Section ' . $s['section'] . ' (' . $s['academic_year'] . ')',
        'sub_dept_id' => $s['sub_dept_id'] !== null ? (int) $s['sub_dept_id'] : null,
        'year_level'  => $s['year_level'],
    ], $sections)) ?>;

    /**
     * Every id coming out of PHP (user fields, AC_SECTIONS/AC_SUBDEPTS) is
     * already explicitly cast server-side, but every lookup here still
     * normalizes through Number() before comparing — so a strict-equality
     * mismatch (a number vs. the numeric string MySQLi can return) can
     * never again silently break the auto-fill, even if some future data
     * source forgets to cast. '' and null both become null (no id).
     */
    function acNum(value) {
        return value === '' || value === null || value === undefined ? null : Number(value);
    }

    /**
     * Rebuilds the Department select from the chosen College, and the
     * Section select from the chosen Department (and Year Level), for
     * either the create form (suffix '') or the edit form (suffix 'Edit').
     * `preselect`, when given, is {subDeptId, yearLevel, sectionId} to
     * restore after rebuilding — used when opening Edit for an existing
     * student, where the values come from their current section, not
     * from whatever the selects currently contain.
     */
    function acCascadeSections(suffix, preselect) {
        var collegeEl   = document.getElementById(suffix === 'Edit' ? 'acEditDeptSelect' : 'acCollegeSelect');
        var subDeptEl   = document.getElementById('ac' + suffix + 'SubDeptSelect');
        var yearLevelEl = document.getElementById('ac' + suffix + 'YearLevelSelect');
        var sectionEl   = document.getElementById('ac' + suffix + 'SectionSelect');
        var collegeId   = acNum(collegeEl.value);

        var keepSubDept = preselect ? acNum(preselect.subDeptId) : acNum(subDeptEl.value);

        subDeptEl.innerHTML = '<option value="">— Select a department —</option>';
        AC_SUBDEPTS.filter(function (sd) { return collegeId === null || sd.college_id === collegeId; })
            .forEach(function (sd) {
                var opt = document.createElement('option');
                opt.value = sd.id;
                opt.textContent = sd.label;
                subDeptEl.appendChild(opt);
            });
        subDeptEl.value = keepSubDept !== null ? String(keepSubDept) : '';

        var subDeptId = acNum(subDeptEl.value);

        if (preselect) {
            yearLevelEl.value = preselect.yearLevel || '';
        }
        var yearLevel = yearLevelEl.value || null;

        var keepSection = preselect ? acNum(preselect.sectionId) : null;

        sectionEl.innerHTML = '<option value="">— No section yet —</option>';
        AC_SECTIONS.filter(function (s) {
                return subDeptId !== null && s.sub_dept_id === subDeptId
                    && (yearLevel === null || s.year_level === yearLevel);
            })
            .forEach(function (s) {
                var opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.label;
                sectionEl.appendChild(opt);
            });
        sectionEl.value = keepSection !== null ? String(keepSection) : '';
    }

    function acToggleDeptField() {
        var role = document.getElementById('acRoleSelect').value;
        document.getElementById('acDeptField').style.display = (role === 'admin' || role === 'registrar') ? 'none' : 'block';
        document.getElementById('acChairNotice').style.display = role === 'dean' ? 'flex' : 'none';
        document.getElementById('acChairDeptField').style.display = role === 'chairperson' ? 'block' : 'none';
        document.getElementById('acChairDeptNotice').style.display = role === 'chairperson' ? 'flex' : 'none';
        document.getElementById('acSectionRow').style.display = role === 'student' ? 'grid' : 'none';
        document.getElementById('acSectionHint').style.display = role === 'student' ? 'block' : 'none';
        document.getElementById('acPersonalRow').style.display = role === 'student' ? 'block' : 'none';
        document.getElementById('acEmpStatusField').style.display = role === 'faculty' ? 'block' : 'none';
        if (role === 'student') acCascadeSections('');
        if (role === 'chairperson') acCascadeChairDept();
    }

    /**
     * Fills "Department to chair" from AC_SUBDEPTS (already loaded for the
     * student section cascade above), narrowed to the chosen College — same
     * source data, independent of the student cascade's own Department
     * select so picking a student's department elsewhere on this form can
     * never cross-contaminate this one.
     */
    function acCascadeChairDept() {
        var collegeId = acNum(document.getElementById('acCollegeSelect').value);
        var chairDeptEl = document.getElementById('acChairDeptSelect');
        var keep = acNum(chairDeptEl.value);

        chairDeptEl.innerHTML = '<option value="">— Select a department —</option>';
        AC_SUBDEPTS.filter(function (sd) { return collegeId === null || sd.college_id === collegeId; })
            .forEach(function (sd) {
                var opt = document.createElement('option');
                opt.value = sd.id;
                opt.textContent = sd.label;
                chairDeptEl.appendChild(opt);
            });
        chairDeptEl.value = keep !== null ? String(keep) : '';
    }

    // Reuses the same create/reassign-chairperson endpoint and business
    // rules (one chair per department, replacing the current one) that the
    // department's own "Assign Chairperson" modal uses — this form just
    // retargets itself there instead of duplicating that logic, so the two
    // entry points can never drift apart.
    document.getElementById('formCreateAccount').addEventListener('submit', function (e) {
        // Always reset first — otherwise a chairperson attempt that got
        // retargeted here on a previous submit would stick around and
        // hijack a later, different-role submission from the same open
        // modal.
        this.action = AC_USERS_BASE + '/store';

        if (document.getElementById('acRoleSelect').value !== 'chairperson') return;

        var subDeptId = document.getElementById('acChairDeptSelect').value;
        if (!subDeptId) {
            e.preventDefault();
            alert('Choose which department this account will chair.');
            return;
        }

        this.action = AC_SUBDEPT_BASE + '/' + subDeptId + '/store-chair';
    });

    function acGeneratePassword(targetId) {
        var bytes = new Uint8Array(9);
        crypto.getRandomValues(bytes);
        var pass = Array.from(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
        document.getElementById(targetId || 'acNewPassword').value = pass;
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
            var departmentId = acNum(user.department_id);
            var sectionId    = acNum(user.section_id);

            document.getElementById('acEditRoleSelect').value = user.group;
            document.getElementById('acEditDeptSelect').value = departmentId !== null ? String(departmentId) : '';
            document.getElementById('acEditEmpStatusSelect').value = user.employment_status || '';

            // The student's actual department/year-level/section is
            // derived from their current section (the source of truth),
            // not from a possibly-stale users.department_id — matches
            // AC_SECTIONS by id with both sides normalized through
            // acNum(), so this never silently fails to find it.
            var currentSection = sectionId !== null
                ? AC_SECTIONS.find(function (s) { return s.id === sectionId; })
                : null;

            acToggleEditDeptField({
                subDeptId: currentSection ? currentSection.sub_dept_id : null,
                yearLevel: currentSection ? currentSection.year_level : null,
                sectionId: currentSection ? sectionId : null,
            });

            var personal = user.personal || {};
            document.getElementById('acEditDob').value = personal.date_of_birth || '';
            document.getElementById('acEditGender').value = personal.gender || '';
            document.getElementById('acEditHomeAddress').value = personal.home_address || '';
            document.getElementById('acEditCity').value = personal.city || '';
            document.getElementById('acEditEmName').value = personal.emergency_contact_name || '';
            document.getElementById('acEditEmNumber').value = personal.emergency_contact_number || '';
        }

        acOpenModal('modalEditAccount');
    }

    function acToggleEditDeptField(preselect) {
        var role = document.getElementById('acEditRoleSelect').value;
        document.getElementById('acEditDeptField').style.display = (role === 'admin' || role === 'registrar') ? 'none' : 'block';
        document.getElementById('acEditSectionRow').style.display = role === 'student' ? 'grid' : 'none';
        document.getElementById('acEditSectionHint').style.display = role === 'student' ? 'block' : 'none';
        document.getElementById('acEditPersonalRow').style.display = role === 'student' ? 'block' : 'none';
        document.getElementById('acEditEmpStatusField').style.display = role === 'faculty' ? 'block' : 'none';
        if (role === 'student') acCascadeSections('Edit', preselect);
    }

    function acOpenResetPassword(id, username) {
        document.getElementById('acResetForm').action = AC_USERS_BASE + '/reset-password/' + id;
        document.getElementById('acResetUsername').textContent = username;
        document.getElementById('acResetPassword').value = '';
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
<?php $modalFlash = session('modal'); ?>
<?php if (session('errors') && is_array($modalFlash) && ($modalFlash['type'] ?? null) === 'reset-password'): ?>
<script>
    acOpenResetPassword(<?= (int) $modalFlash['id'] ?>, <?= json_encode($modalFlash['username']) ?>);
    document.getElementById('acResetPassword').value = <?= json_encode(old('password') ?? '') ?>;
</script>
<?php elseif (session('errors')): ?>
<script>document.getElementById('modalCreateAccount').classList.add('is-open'); acToggleDeptField();</script>
<?php endif ?>
<?= $this->endSection() ?>

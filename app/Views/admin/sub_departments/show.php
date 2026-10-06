<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('headerActions') ?>
<button type="button" onclick="acOpenModal('modalEditDetails')" class="ac-btn ac-btn-secondary">
    <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
    <span>Edit Details</span>
</button>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-breadcrumb">
    <a href="<?= site_url('admin/departments') ?>">Colleges</a>
    <span class="material-symbols-outlined">chevron_right</span>
    <a href="<?= site_url('admin/departments/' . $college['dept_id']) ?>"><?= esc($college['name']) ?></a>
    <span class="material-symbols-outlined">chevron_right</span>
    <span class="current"><?= esc($department['name']) ?></span>
</div>

<div class="ac-detail-header">
    <div>
        <h1 class="ac-page-header__title"><?= esc($department['name']) ?> (<?= esc($department['code']) ?>)</h1>
        <div class="ac-detail-header__meta">
            <span class="material-symbols-outlined">person</span>
            <?php if ($department['chairs']): ?>
                <span><?= count($department['chairs']) > 1 ? 'Chairpersons:' : 'Chairperson:' ?></span>
                <span class="ac-chair-list">
                    <?php foreach ($department['chairs'] as $c): ?>
                        <span class="ac-chair-chip">
                            <?= esc($c['username']) ?><?= $c['is_primary'] ? '' : ' (shared)' ?>
                            <form method="post" action="<?= site_url('admin/sub-departments/' . $department['sub_dept_id'] . '/chair/' . $c['id'] . '/remove') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="ac-chair-chip__remove" title="Remove <?= esc($c['username'], 'attr') ?> as chairperson" data-confirm="Remove <?= esc($c['username'], 'attr') ?> as chairperson of <?= esc($department['name'], 'attr') ?>?">
                                    <span class="material-symbols-outlined">close</span>
                                </button>
                            </form>
                        </span>
                    <?php endforeach ?>
                </span>
            <?php else: ?>
                <span style="color:var(--color-warning); font-weight:700;">No chairperson assigned</span>
            <?php endif ?>
            <span class="ac-status-dot <?= $department['is_active'] ? 'ac-status-dot--active' : 'ac-status-dot--inactive' ?>" style="margin-left:12px;">
                <?= $department['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
        </div>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <button type="button" onclick="acOpenModal('modalTransfer')" class="ac-btn ac-btn-secondary">
            <span class="material-symbols-outlined" style="font-size:18px;">swap_horiz</span>
            <span>Transfer College</span>
        </button>
        <button type="button" onclick="acOpenModal('modalChair')" class="ac-btn <?= $department['chair'] ? 'ac-btn-secondary' : 'ac-btn-primary' ?>">
            <span class="material-symbols-outlined" style="font-size:18px;"><?= $department['chair'] ? 'sync_alt' : 'person_add' ?></span>
            <span><?= $department['chair'] ? 'Reassign Chairperson' : 'Assign Chairperson' ?></span>
        </button>
        <form method="post" action="<?= site_url('admin/sub-departments/' . $department['sub_dept_id'] . '/toggle-status') ?>">
            <?= csrf_field() ?>
            <button type="submit" data-confirm="<?= $department['is_active'] ? 'Deactivate' : 'Activate' ?> this department?" class="ac-btn ac-btn-secondary">
                <span class="material-symbols-outlined" style="font-size:18px;"><?= $department['is_active'] ? 'visibility_off' : 'visibility' ?></span>
                <span><?= $department['is_active'] ? 'Deactivate' : 'Activate' ?></span>
            </button>
        </form>
        <form method="post" action="<?= site_url('admin/sub-departments/' . $department['sub_dept_id'] . '/delete') ?>">
            <?= csrf_field() ?>
            <button type="submit" data-confirm="Delete <?= esc($department['name'], 'attr') ?>? Its chairperson/faculty will be unassigned and its programs unlinked. This cannot be undone." class="ac-icon-action ac-icon-action--danger" title="Delete Department" style="height:38px; width:38px;">
                <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
            </button>
        </form>
    </div>
</div>

<div class="ac-detail-grid">

    <div class="ac-metric-stack">

        <div class="ac-metric-card">
            <div class="ac-metric-card__top">
                <div class="ac-metric-card__icon"><span class="material-symbols-outlined icon-fill">groups</span></div>
                <span style="font-size:11px; font-weight:800; letter-spacing:.04em; text-transform:uppercase;">Total Students</span>
            </div>
            <div class="ac-metric-card__value"><?= esc($studentCount) ?></div>
        </div>

        <div class="ac-metric-card">
            <div class="ac-metric-card__top">
                <div class="ac-metric-card__icon"><span class="material-symbols-outlined icon-fill">badge</span></div>
                <span style="font-size:11px; font-weight:800; letter-spacing:.04em; text-transform:uppercase;">Faculty Members</span>
            </div>
            <div class="ac-metric-card__value"><?= esc($facultyCount) ?></div>
        </div>

        <div class="ac-metric-card">
            <div class="ac-metric-card__top">
                <span class="material-symbols-outlined" style="color:var(--color-text-secondary);">account_balance</span>
                <span style="font-size:11px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:var(--color-text-secondary);">College</span>
            </div>
            <p class="ac-metric-card__text"><?= esc($college['name']) ?> (<?= esc($college['code']) ?>)</p>
            <p class="ac-metric-card__note"><a href="<?= site_url('admin/departments/' . $college['dept_id']) ?>" style="color:var(--color-primary); text-decoration:none;">View college &rarr;</a></p>
        </div>

    </div>

    <div style="display:flex; flex-direction:column; gap:16px;">

        <!-- Faculty members -->
        <div class="ac-panel">
            <div class="ac-panel__head">
                <h3><span class="material-symbols-outlined" style="vertical-align:-5px; color:var(--color-primary); margin-right:6px;">badge</span>Faculty Members</h3>
                <button type="button" onclick="acOpenModal('modalAddFaculty')" class="ac-btn-outline">+ Add Faculty</button>
            </div>
            <div>
                <?php if (empty($facultyMembers)): ?>
                    <div class="ac-empty-panel">No faculty members in this department yet.</div>
                <?php else: ?>
                    <?php foreach ($facultyMembers as $fm): ?>
                        <div class="ac-program-row">
                            <div class="ac-program-row__left">
                                <div class="ac-avatar ac-avatar--primary"><?= esc(strtoupper(substr($fm['username'], 0, 1))) ?></div>
                                <div>
                                    <p class="ac-program-title"><?= esc($fm['username']) ?></p>
                                    <p class="ac-program-sub"><?= esc($fm['email']) ?></p>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <?php if ($fm['employment_status']): ?>
                                    <span class="ac-status-dot" style="background:#eef2ff; color:#4338ca;">
                                        <?= $fm['employment_status'] === 'full_time' ? 'Full-Time' : 'Part-Time' ?>
                                    </span>
                                <?php endif ?>
                                <span class="ac-status-dot <?= $fm['active'] ? 'ac-status-dot--active' : 'ac-status-dot--pending' ?>">
                                    <?= $fm['active'] ? 'Active' : 'Invited' ?>
                                </span>
                                <button type="button" onclick="acOpenModal('modalEditFaculty<?= $fm['id'] ?>')" class="ac-icon-action" title="Edit">
                                    <span class="material-symbols-outlined" style="font-size:16px;">edit</span>
                                </button>
                                <form method="post" action="<?= site_url('admin/sub-departments/' . $department['sub_dept_id'] . '/faculty/' . $fm['id'] . '/toggle-status') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ac-icon-action" title="<?= $fm['active'] ? 'Deactivate' : 'Activate' ?>">
                                        <span class="material-symbols-outlined" style="font-size:16px;"><?= $fm['active'] ? 'visibility_off' : 'visibility' ?></span>
                                    </button>
                                </form>
                                <form method="post" action="<?= site_url('admin/sub-departments/' . $department['sub_dept_id'] . '/faculty/' . $fm['id'] . '/delete') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" data-confirm="Remove <?= esc($fm['username'], 'attr') ?> from this department? This deletes their account." class="ac-icon-action ac-icon-action--danger" title="Delete">
                                        <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Edit faculty member modal -->
                        <div class="ac-modal" id="modalEditFaculty<?= $fm['id'] ?>">
                            <div class="ac-modal__panel">
                                <div class="ac-modal__header">
                                    <div class="ac-modal__title">Edit Faculty Member</div>
                                    <button type="button" onclick="acCloseModal('modalEditFaculty<?= $fm['id'] ?>')" class="ac-modal__close">
                                        <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                                    </button>
                                </div>
                                <form method="post" action="<?= site_url('admin/sub-departments/' . $department['sub_dept_id'] . '/faculty/' . $fm['id'] . '/update') ?>">
                                    <?= csrf_field() ?>
                                    <div class="ac-modal__body">
                                        <div class="ac-field">
                                            <label>Full Name</label>
                                            <input type="text" name="username" class="ac-input" value="<?= esc($fm['username']) ?>" required>
                                        </div>
                                        <div class="ac-field">
                                            <label>Email</label>
                                            <input type="email" name="email" class="ac-input" value="<?= esc($fm['email']) ?>" required>
                                        </div>
                                        <div class="ac-field">
                                            <label>Employment Status</label>
                                            <select name="employment_status" class="ac-input">
                                                <option value="">Not set</option>
                                                <option value="full_time" <?= $fm['employment_status'] === 'full_time' ? 'selected' : '' ?>>Full-Time</option>
                                                <option value="part_time" <?= $fm['employment_status'] === 'part_time' ? 'selected' : '' ?>>Part-Time</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="ac-modal__actions">
                                        <button type="button" onclick="acCloseModal('modalEditFaculty<?= $fm['id'] ?>')" class="ac-btn ac-btn-secondary">Cancel</button>
                                        <button type="submit" class="ac-btn ac-btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>

        <!-- Programs -->
        <div class="ac-panel">
            <div class="ac-panel__head">
                <h3><span class="material-symbols-outlined" style="vertical-align:-5px; color:var(--color-primary); margin-right:6px;">local_library</span>Programs</h3>
                <button type="button" onclick="acOpenModal('modalAddProgram')" class="ac-btn-outline">+ Add Program</button>
            </div>
            <div>
                <?php if (empty($programs)): ?>
                    <div class="ac-empty-panel">No programs linked to this department yet.</div>
                <?php else: ?>
                    <?php foreach ($programs as $program): ?>
                        <div class="ac-program-row">
                            <div class="ac-program-row__left">
                                <div class="ac-program-icon"><span class="material-symbols-outlined">school</span></div>
                                <div>
                                    <p class="ac-program-title"><?= esc($program['program_name']) ?></p>
                                    <p class="ac-program-sub"><?= esc($program['program_code']) ?></p>
                                </div>
                            </div>
                            <span class="ac-status-dot <?= $program['is_active'] ? 'ac-status-dot--active' : 'ac-status-dot--inactive' ?>">
                                <?= $program['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>

    </div>

</div>

<!-- Edit details modal -->
<div class="ac-modal" id="modalEditDetails">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div class="ac-modal__title">Edit Department Details</div>
            <button type="button" onclick="acCloseModal('modalEditDetails')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/sub-departments/' . $department['sub_dept_id'] . '/update') ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Department Code</label>
                    <input type="text" name="code" class="ac-input" value="<?= esc($department['code']) ?>" required>
                </div>
                <div class="ac-field">
                    <label>Department Name</label>
                    <input type="text" name="name" class="ac-input" value="<?= esc($department['name']) ?>" required>
                </div>
            </div>
            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalEditDetails')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Transfer to another college modal -->
<div class="ac-modal" id="modalTransfer">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title">Transfer Department</div>
                <div class="ac-modal__subtitle">Move <?= esc($department['name']) ?> to a different college.</div>
            </div>
            <button type="button" onclick="acCloseModal('modalTransfer')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/sub-departments/' . $department['sub_dept_id'] . '/transfer') ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Target College</label>
                    <select name="college_id" class="ac-input" required>
                        <?php foreach ($colleges as $c): ?>
                            <option value="<?= esc($c['dept_id']) ?>" <?= (int) $c['dept_id'] === (int) $department['college_id'] ? 'selected' : '' ?>>
                                <?= esc($c['code']) ?> &mdash; <?= esc($c['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="ac-notice">
                    <span class="material-symbols-outlined" style="font-size:14px; color:var(--color-info); margin-top:1px;">info</span>
                    <span class="ac-notice__text">Currently under <?= esc($college['name']) ?>. Transferring moves this department (and its chairperson/faculty scheduling scope) to the selected college.</span>
                </div>
            </div>
            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalTransfer')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Transfer</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign / reassign chairperson modal -->
<div class="ac-modal" id="modalChair">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title">Assign Chairperson</div>
                <div class="ac-modal__subtitle">Creates a Chairperson account scoped to <?= esc($department['name']) ?>.</div>
            </div>
            <button type="button" onclick="acCloseModal('modalChair')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/sub-departments/' . $department['sub_dept_id'] . '/store-chair') ?>" id="formChair">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <div style="display:flex; gap:16px; margin-bottom:4px;">
                        <label style="display:flex; align-items:center; gap:6px; font-weight:400; text-transform:none;">
                            <input type="radio" name="chair_mode" value="existing" checked onchange="acToggleChairMode(this)"> Existing user
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; font-weight:400; text-transform:none;">
                            <input type="radio" name="chair_mode" value="new" onchange="acToggleChairMode(this)"> New account
                        </label>
                    </div>
                </div>

                <div class="ac-field" id="chairExistingField">
                    <label>User</label>
                    <select name="existing_user_id" class="ac-input" id="chairExistingSelect" required>
                        <option value="">Choose a user…</option>
                        <?php foreach ($assignableUsers as $u): ?>
                            <option value="<?= (int) $u['id'] ?>">
                                <?= esc($u['username']) ?><?= $u['email'] ? ' — ' . esc($u['email']) : '' ?><?= $u['groups'] !== [] ? ' (' . esc(implode(', ', $u['groups'])) . ')' : '' ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div id="chairNewFields" style="display:none;">
                    <div class="ac-field">
                        <label>Full Name</label>
                        <input type="text" name="username" class="ac-input" placeholder="Prof. Maria Reyes" value="<?= esc($department['chair']['username'] ?? '') ?>">
                    </div>
                    <div class="ac-field">
                        <label>Email</label>
                        <input type="email" name="email" class="ac-input" placeholder="mreyes@spum.edu.ph" value="<?= esc($department['chair']['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="ac-notice">
                    <span class="material-symbols-outlined" style="font-size:14px; color:var(--color-info); margin-top:1px;">info</span>
                    <span class="ac-notice__text">This will set the account as <?= esc($department['name']) ?>'s chairperson, replacing the current one if any. An existing user keeps their other roles/departments — this only adds chairperson access here.</span>
                </div>
            </div>
            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalChair')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary"><?= $department['chair'] ? 'Reassign' : 'Assign' ?></button>
            </div>
        </form>
        <script>
            function acToggleChairMode(radio) {
                const existing = radio.form.querySelector('#chairExistingField');
                const existingSelect = radio.form.querySelector('#chairExistingSelect');
                const newFields = radio.form.querySelector('#chairNewFields');
                const newInputs = newFields.querySelectorAll('input');
                const isExisting = radio.value === 'existing';
                existing.style.display = isExisting ? '' : 'none';
                newFields.style.display = isExisting ? 'none' : '';
                existingSelect.required = isExisting;
                newInputs.forEach(input => { input.required = !isExisting; });
            }
        </script>
    </div>
</div>

<!-- Add faculty member modal -->
<div class="ac-modal" id="modalAddFaculty">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title">Add Faculty Member</div>
                <div class="ac-modal__subtitle">Creates a Faculty account scoped to <?= esc($department['name']) ?>.</div>
            </div>
            <button type="button" onclick="acCloseModal('modalAddFaculty')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/sub-departments/' . $department['sub_dept_id'] . '/faculty') ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Full Name</label>
                    <input type="text" name="username" class="ac-input" placeholder="Prof. Roy Consulta" value="<?= old('username') ?>" required>
                </div>
                <div class="ac-field">
                    <label>Email</label>
                    <input type="email" name="email" class="ac-input" placeholder="rconsulta@spum.edu.ph" value="<?= old('email') ?>" required>
                </div>
                <div class="ac-field">
                    <label>Employment Status</label>
                    <select name="employment_status" class="ac-input">
                        <option value="">Not set</option>
                        <option value="full_time" <?= old('employment_status') === 'full_time' ? 'selected' : '' ?>>Full-Time</option>
                        <option value="part_time" <?= old('employment_status') === 'part_time' ? 'selected' : '' ?>>Part-Time</option>
                    </select>
                </div>
            </div>
            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalAddFaculty')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Add &amp; Invite</button>
            </div>
        </form>
    </div>
</div>

<!-- Add program modal -->
<div class="ac-modal" id="modalAddProgram">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title">Add Program</div>
                <div class="ac-modal__subtitle">Adds a new program under <?= esc($department['name']) ?>.</div>
            </div>
            <button type="button" onclick="acCloseModal('modalAddProgram')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/sub-departments/' . $department['sub_dept_id'] . '/programs') ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Program Code</label>
                    <input type="text" name="program_code" class="ac-input" placeholder="BSCE" value="<?= old('program_code') ?>" required>
                </div>
                <div class="ac-field">
                    <label>Program Name</label>
                    <input type="text" name="program_name" class="ac-input" placeholder="BS Civil Engineering" value="<?= old('program_name') ?>" required>
                </div>
            </div>
            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalAddProgram')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Add</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (session('errors')): ?>
<script>
    <?php if (old('program_code') || old('program_name')): ?>
        document.getElementById('modalAddProgram').classList.add('is-open');
    <?php elseif (old('username') || old('email')): ?>
        document.getElementById('modalAddFaculty').classList.add('is-open');
    <?php else: ?>
        document.getElementById('modalEditDetails').classList.add('is-open');
    <?php endif ?>
</script>
<?php endif ?>
<?= $this->endSection() ?>

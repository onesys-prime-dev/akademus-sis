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
    <span class="current"><?= esc($college['name']) ?></span>
</div>

<div class="ac-detail-header">
    <div>
        <h1 class="ac-page-header__title"><?= esc($college['name']) ?> (<?= esc($college['code']) ?>)</h1>
        <div class="ac-detail-header__meta">
            <span class="material-symbols-outlined">person</span>
            <?php if ($college['dean']): ?>
                Dean: <?= esc($college['dean']['username']) ?>
            <?php else: ?>
                <span style="color:#d97706; font-weight:700;">No dean assigned</span>
            <?php endif ?>
        </div>
    </div>
    <button type="button" onclick="acOpenModal('modalDean')" class="ac-btn <?= $college['dean'] ? 'ac-btn-secondary' : 'ac-btn-primary' ?>">
        <span class="material-symbols-outlined" style="font-size:18px;"><?= $college['dean'] ? 'sync_alt' : 'person_add' ?></span>
        <span><?= $college['dean'] ? 'Reassign Dean' : 'Assign Dean' ?></span>
    </button>
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
                <span class="material-symbols-outlined" style="color:#515f74;">location_on</span>
                <span style="font-size:11px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:#515f74;">Location</span>
            </div>
            <p class="ac-metric-card__text"><?= esc($college['location'] ?: 'Not set') ?></p>
            <?php if ($college['established_year']): ?>
                <p class="ac-metric-card__note">Established <?= esc($college['established_year']) ?></p>
            <?php endif ?>
        </div>

    </div>

    <div class="ac-panel">
        <div class="ac-panel__head">
            <h3><span class="material-symbols-outlined" style="vertical-align:-5px; color:#0037b0; margin-right:6px;">hub</span>Departments</h3>
            <button type="button" onclick="acOpenModal('modalAddDept')" class="ac-btn-outline">+ Add Department</button>
        </div>
        <div>
            <?php if (empty($subDepartments)): ?>
                <div class="ac-empty-panel">No departments under this college yet.</div>
            <?php else: ?>
                <?php foreach ($subDepartments as $dept): ?>
                    <a href="<?= site_url('admin/sub-departments/' . $dept['sub_dept_id']) ?>" class="ac-program-row" style="text-decoration:none; cursor:pointer; <?= $dept['is_active'] ? '' : 'opacity:.6;' ?>">
                        <div class="ac-program-row__left">
                            <div class="ac-program-icon"><span class="material-symbols-outlined">domain</span></div>
                            <div>
                                <p class="ac-program-title"><?= esc($dept['code']) ?> &middot; <?= esc($dept['name']) ?></p>
                                <p class="ac-program-sub">
                                    <?= $dept['chair'] ? 'Chair: ' . esc($dept['chair']['username']) : 'No chairperson' ?>
                                    &bull; <?= esc($dept['faculty_count']) ?> faculty
                                    <?php if (! $dept['is_active']): ?>
                                        &bull; <span style="color:#747686; font-weight:700;">Inactive</span>
                                    <?php endif ?>
                                </p>
                            </div>
                        </div>
                        <span class="material-symbols-outlined" style="color:#c4c5d7;">arrow_forward</span>
                    </a>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>

</div>

<!-- Edit details modal -->
<div class="ac-modal" id="modalEditDetails">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div class="ac-modal__title">Edit College Details</div>
            <button type="button" onclick="acCloseModal('modalEditDetails')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/departments/' . $college['dept_id'] . '/update') ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>College Code</label>
                    <input type="text" name="code" class="ac-input" value="<?= esc($college['code']) ?>" required>
                </div>
                <div class="ac-field">
                    <label>College Name</label>
                    <input type="text" name="name" class="ac-input" value="<?= esc($college['name']) ?>" required>
                </div>
                <div class="ac-field">
                    <label>Location</label>
                    <input type="text" name="location" class="ac-input" placeholder="North Campus, Building 4" value="<?= esc($college['location'] ?? '') ?>">
                </div>
                <div class="ac-field">
                    <label>Established Year</label>
                    <input type="number" name="established_year" class="ac-input" placeholder="1985" value="<?= esc($college['established_year'] ?? '') ?>">
                </div>
            </div>
            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalEditDetails')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign / reassign dean modal -->
<div class="ac-modal" id="modalDean">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title">Assign Dean</div>
                <div class="ac-modal__subtitle">Creates a Dean account scoped to <?= esc($college['name']) ?>.</div>
            </div>
            <button type="button" onclick="acCloseModal('modalDean')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/departments/store-dean/' . $college['dept_id']) ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Full Name</label>
                    <input type="text" name="username" class="ac-input" placeholder="Dr. Sarah Jenkins" value="<?= esc($college['dean']['username'] ?? '') ?>" required>
                </div>
                <div class="ac-field">
                    <label>Email</label>
                    <input type="email" name="email" class="ac-input" placeholder="sjenkins@spum.edu.ph" value="<?= esc($college['dean']['email'] ?? '') ?>" required>
                </div>
                <div class="ac-notice">
                    <span class="material-symbols-outlined" style="font-size:14px; color:#1d4ed8; margin-top:1px;">info</span>
                    <span class="ac-notice__text">This will set the account as <?= esc($college['name']) ?>'s dean, replacing the current one if any.</span>
                </div>
            </div>
            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalDean')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary"><?= $college['dean'] ? 'Reassign' : 'Assign' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Add department modal -->
<div class="ac-modal" id="modalAddDept">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title">Add Department</div>
                <div class="ac-modal__subtitle">Adds a new department under <?= esc($college['name']) ?>.</div>
            </div>
            <button type="button" onclick="acCloseModal('modalAddDept')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/departments/' . $college['dept_id'] . '/sub-departments') ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Department Code</label>
                    <input type="text" name="dept_code" class="ac-input" placeholder="CS-DEPT" value="<?= old('dept_code') ?>" required>
                </div>
                <div class="ac-field">
                    <label>Department Name</label>
                    <input type="text" name="dept_name" class="ac-input" placeholder="Department of Computer Studies" value="<?= old('dept_name') ?>" required>
                </div>
            </div>
            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalAddDept')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Add</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (session('errors')): ?>
<script>
    <?php if (old('dept_code') || old('dept_name')): ?>
        document.getElementById('modalAddDept').classList.add('is-open');
    <?php elseif (old('username') || old('email')): ?>
        document.getElementById('modalDean').classList.add('is-open');
    <?php else: ?>
        document.getElementById('modalEditDetails').classList.add('is-open');
    <?php endif ?>
</script>
<?php endif ?>
<?= $this->endSection() ?>

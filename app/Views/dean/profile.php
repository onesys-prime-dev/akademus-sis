<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<?= $this->endSection() ?>

<?php $photoUrl = avatar_photo_url((int) $account['id'], $account['photo_mime'] ?? null); ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Profile</h2></div>
</div>

<?php if (session('profile_message')): ?>
    <div class="flash success"><?= esc(session('profile_message')) ?></div>
<?php endif ?>
<?php if (session('profile_error')): ?>
    <div class="flash error"><?= esc(session('profile_error')) ?></div>
<?php endif ?>

<div class="panel">
    <div class="avatar-row">
        <div class="avatar-wrap">
            <div class="avatar" style="<?= $photoUrl === null ? 'background:' . esc(avatar_color($account['username']), 'attr') . ';' : '' ?> overflow:hidden;">
                <?php if ($photoUrl !== null): ?>
                    <img src="<?= esc($photoUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <?= esc(mb_strtoupper(mb_substr($account['username'], 0, 1))) ?>
                <?php endif ?>
            </div>

            <form method="post" action="<?= site_url('dean/profile/photo') ?>" enctype="multipart/form-data" id="avatarUploadForm" hidden>
                <?= csrf_field() ?>
                <input type="file" name="photo" id="avatarFileInput" accept="image/jpeg,image/png,image/webp" hidden>
            </form>
            <?php if ($photoUrl !== null): ?>
                <form method="post" action="<?= site_url('dean/profile/photo/remove') ?>" id="avatarRemoveForm" hidden>
                    <?= csrf_field() ?>
                </form>
            <?php endif ?>

            <button type="button" class="avatar-edit-btn" id="avatarEditTrigger" aria-haspopup="true" aria-expanded="false" aria-controls="avatarEditMenu" aria-label="Edit profile photo">
                <span class="material-symbols-outlined" aria-hidden="true">photo_camera</span>
            </button>
            <div class="avatar-edit-menu" id="avatarEditMenu" role="menu" hidden>
                <label class="avatar-edit-menu__item" for="avatarFileInput" role="menuitem">
                    <span class="material-symbols-outlined" aria-hidden="true">photo_camera</span>
                    <?= $photoUrl !== null ? 'Change photo' : 'Upload photo' ?>
                </label>
                <?php if ($photoUrl !== null): ?>
                    <button type="button" class="avatar-edit-menu__item avatar-edit-menu__item--danger" id="avatarRemoveBtn" role="menuitem">
                        <span class="material-symbols-outlined" aria-hidden="true">delete</span>
                        Remove photo
                    </button>
                <?php endif ?>
            </div>
        </div>
        <div>
            <div class="avatar-name"><?= esc($account['username']) ?></div>
            <div class="avatar-role">Dean</div>
        </div>
    </div>

        <form method="post" action="<?= site_url('dean/profile/update') ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label>Display Name</label>
                <input type="text" name="username" value="<?= esc($account['username']) ?>" maxlength="100" required>
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" value="<?= esc($account['email']) ?>" disabled>
                <div class="hint">Email is your login and can't be changed here — contact an administrator.</div>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>

    <div class="panel">
        <h2>Change Password</h2>
        <div class="panel-sub">Choose a new password of at least 8 characters.</div>

        <form method="post" action="<?= site_url('dean/profile/password') ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
            </div>
            <div class="field-row">
                <div class="field">
                    <label>New Password</label>
                    <input type="password" name="new_password" minlength="8" required>
                </div>
                <div class="field">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" minlength="8" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Change Password</button>
        </form>
    </div>

<div class="avatar-crop-backdrop" id="avatarCropBackdrop" hidden>
    <div class="avatar-crop-modal" role="dialog" aria-modal="true" aria-labelledby="avatarCropTitle">
        <div class="avatar-crop-head">
            <h3 id="avatarCropTitle">Choose profile picture</h3>
            <button type="button" class="icon-btn" id="avatarCropClose" aria-label="Cancel">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="avatar-crop-stage" id="avatarCropStage">
            <img id="avatarCropImg" alt="" draggable="false" hidden>
            <div class="avatar-crop-status" id="avatarCropStatus">Loading photo&hellip;</div>
        </div>
        <div class="avatar-crop-zoom">
            <span class="material-symbols-outlined">remove</span>
            <input type="range" id="avatarCropZoom" min="100" max="300" value="100">
            <span class="material-symbols-outlined">add</span>
        </div>
        <div class="avatar-crop-actions">
            <button type="button" class="btn btn-secondary" id="avatarCropCancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="avatarCropSave">Save</button>
        </div>
    </div>
</div>

<div class="avatar-crop-backdrop" id="avatarRemoveBackdrop" hidden>
    <div class="avatar-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="avatarRemoveTitle">
        <h3 id="avatarRemoveTitle">Remove profile photo?</h3>
        <p>You'll go back to your letter avatar. This can't be undone.</p>
        <div class="avatar-crop-actions">
            <button type="button" class="btn btn-secondary" id="avatarRemoveCancel">Cancel</button>
            <button type="button" class="btn btn-danger" id="avatarRemoveConfirm">Remove photo</button>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/js/avatar-crop.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/avatar-crop.js') ?>"></script>

<?= $this->endSection() ?>

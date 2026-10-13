<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    .container { max-width: 640px; margin: 0 auto; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="container">

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
            <div class="avatar"><?= esc(mb_strtoupper(mb_substr($account['username'], 0, 1))) ?></div>
            <div>
                <div class="avatar-name"><?= esc($account['username']) ?></div>
                <div class="avatar-role">Finance</div>
            </div>
        </div>

        <form method="post" action="<?= site_url('finance/profile/update') ?>">
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

        <form method="post" action="<?= site_url('finance/profile/password') ?>">
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

</div>

<?= $this->endSection() ?>

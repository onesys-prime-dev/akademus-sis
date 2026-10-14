<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'profile';
$pageTitle     = 'Profile';
$brandSub      = 'Student Portal';
$userName      = $account->username;
$userRoleLabel = $section !== null ? "{$section['program']} · {$section['year_level']}" : 'Student';
$navGroups     = [
    '' => [
        'dashboard' => ['label' => 'Dashboard',   'icon' => 'home',           'url' => 'student/dashboard'],
        'tuition'   => ['label' => 'Tuition & Fees', 'icon' => 'payments',    'url' => 'student/tuition'],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule'],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades'],
        'enlistment' => ['label' => 'Enlistment', 'icon' => 'playlist_add_check', 'url' => 'student/enlistment'],
        'documents' => ['label' => 'Documents',   'icon' => 'description',        'url' => 'student/documents'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<?= $this->endSection() ?>

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
        <div class="avatar"><?= esc(mb_strtoupper(mb_substr($account->username, 0, 1))) ?></div>
        <div>
            <div class="avatar-name"><?= esc($account->username) ?></div>
            <div class="avatar-role">
                <?= $section !== null ? esc($section['program']) . ' · ' . esc($section['year_level']) . ' · Section ' . esc($section['section']) : 'No section assigned' ?>
            </div>
        </div>
    </div>

    <form method="post" action="<?= site_url('student/profile/update') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>Display Name</label>
            <input type="text" name="username" value="<?= esc($account->username) ?>" maxlength="100" required>
        </div>
        <div class="field">
            <label>Email</label>
            <input type="email" value="<?= esc($account->email) ?>" disabled>
            <div class="hint">Email is your login and can't be changed here — contact an administrator.</div>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
</div>

<div class="panel">
    <h2>Change Password</h2>
    <div class="panel-sub">Choose a new password of at least 8 characters.</div>

    <form method="post" action="<?= site_url('student/profile/password') ?>">
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

<div class="panel">
    <h2>Academic Information</h2>
    <?php if ($section === null): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>You're not assigned to a section yet. Contact an administrator.</p>
        </div>
    <?php else: ?>
        <div class="field-row">
            <div class="field">
                <label>Program</label>
                <input type="text" value="<?= esc($section['program']) ?>" disabled>
            </div>
            <div class="field">
                <label>Year Level</label>
                <input type="text" value="<?= esc($section['year_level']) ?>" disabled>
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Section</label>
                <input type="text" value="<?= esc($section['section']) ?>" disabled>
            </div>
            <div class="field">
                <label>Academic Year</label>
                <input type="text" value="<?= esc($section['academic_year']) ?>" disabled>
            </div>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>

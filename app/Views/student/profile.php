<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        <?= $this->include('components/shell/style') ?>
    </style>
</head>

<body>

    <?php
    $activePage  = 'profile';
    $pageTitle   = 'Profile';
    $userName    = $account->username;
    $userRole    = $section !== null ? "{$section['program']} · {$section['year_level']}" : 'Student';
    $settingsUrl = site_url('student/profile');
    $this->setData(compact('activePage', 'pageTitle', 'userName', 'userRole', 'settingsUrl'));
    ?>

    <?= $this->include('components/shell/student_nav') ?>

    <div class="app-main">

        <?= $this->include('components/shell/topbar') ?>

        <div class="content">

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

        </div>

    </div>

</body>

</html>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        <?= $this->include('components/shell/style') ?>
    </style>
</head>

<body>

    <?php
    $activePage  = 'dashboard';
    $pageTitle   = 'Dashboard';
    $userName    = $account->username;
    $userRole    = $section !== null ? "{$section['program']} · {$section['year_level']}" : 'Student';
    $settingsUrl = site_url('student/profile');
    $this->setData(compact('activePage', 'pageTitle', 'userName', 'userRole', 'settingsUrl'));
    ?>

    <?= $this->include('components/shell/student_nav') ?>

    <div class="app-main">

        <?= $this->include('components/shell/topbar') ?>

        <div class="content">

            <?php if ($section === null): ?>
                <div class="panel">
                    <div class="empty-state">
                        <div class="material-symbols-outlined">domain_disabled</div>
                        <p>You're not assigned to a section yet. Contact an administrator.</p>
                    </div>
                </div>
            <?php else: ?>

                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><span class="material-symbols-outlined">menu_book</span></div>
                        <div class="stat-value"><?= esc($stats['enrolled_subjects']) ?></div>
                        <div class="stat-label">Subjects enrolled</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><span class="material-symbols-outlined">event</span></div>
                        <div class="stat-value"><?= esc($stats['weekly_meetings']) ?></div>
                        <div class="stat-label">Weekly class meetings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><span class="material-symbols-outlined">groups</span></div>
                        <div class="stat-value"><?= esc($section['section']) ?></div>
                        <div class="stat-label">Section</div>
                    </div>
                </div>

                <div class="panel">
                    <h2>Your Cohort</h2>
                    <p style="color:var(--on-surface-variant); margin-bottom:10px;">
                        <?= esc($section['program']) ?> &middot; <?= esc($section['year_level']) ?> &middot; Section <?= esc($section['section']) ?>
                    </p>
                    <p style="color:var(--on-surface-variant); font-size:13px;">Active term: <?= esc($section['academic_year']) ?></p>
                    <a class="btn btn-primary" style="margin-top:16px;" href="<?= site_url('student/schedule') ?>">
                        <span class="material-symbols-outlined" style="font-size:18px;">calendar_month</span>
                        View My Schedule
                    </a>
                </div>

            <?php endif ?>

        </div>

    </div>

</body>

</html>

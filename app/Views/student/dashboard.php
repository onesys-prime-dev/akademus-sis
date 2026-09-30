<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'dashboard';
$pageTitle     = 'Dashboard';
$brandSub      = 'Student Portal';
$userName      = $account->username;
$userRoleLabel = $section !== null ? "{$section['program']} · {$section['year_level']}" : 'Student';
$navGroups     = [
    '' => [
        'dashboard' => ['label' => 'Dashboard',   'icon' => 'home',           'url' => 'student/dashboard'],
        'profile'   => ['label' => 'Profile',     'icon' => 'person',         'url' => 'student/profile'],
        'tuition'   => ['label' => 'Tuition & Fees', 'icon' => 'payments',    'url' => 'student/tuition'],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule'],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Dashboard</h2>
        <p class="ac-page-header__sub">Welcome back, <?= esc($account->username) ?>.</p>
    </div>
</div>

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
        <p style="color:var(--color-text-secondary); margin-bottom:10px;">
            <?= esc($section['program']) ?> &middot; <?= esc($section['year_level']) ?> &middot; Section <?= esc($section['section']) ?>
        </p>
        <p style="color:var(--color-text-secondary); font-size:13px;">Active term: <?= esc($section['academic_year']) ?></p>
        <a class="btn btn-primary" style="margin-top:16px;" href="<?= site_url('student/schedule') ?>">
            <span class="material-symbols-outlined" style="font-size:18px;">calendar_month</span>
            View My Schedule
        </a>
    </div>

<?php endif ?>

<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'overview';
$pageTitle     = 'Dashboard';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = [
    '' => [
        'overview'        => ['label' => 'Dashboard',        'icon' => 'home',           'url' => 'chairperson/overview'],
        'profile'         => ['label' => 'Profile',           'icon' => 'person',          'url' => 'chairperson/profile'],
        'schedule'        => ['label' => 'Schedule Plotting', 'icon' => 'calendar_month',  'url' => 'chairperson/dashboard'],
        'sections'        => ['label' => 'Sections',          'icon' => 'groups',          'url' => 'chairperson/sections'],
        'offerings'       => ['label' => 'Merged Classes',    'icon' => 'merge_type',      'url' => 'chairperson/offerings'],
        'grade-approvals' => ['label' => 'Grade Approvals',   'icon' => 'grade',           'url' => 'chairperson/grade-approvals'],
        'curriculum'      => ['label' => 'Curriculum',        'icon' => 'menu_book',       'url' => 'chairperson/curriculum'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Dashboard</h2></div>
</div>

<?php if ($deptName === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>You're not assigned to a department yet. Contact an administrator.</p>
        </div>
    </div>
<?php elseif ($activeYear === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">event_busy</div>
            <p>No active academic year is set. An administrator needs to activate one under Admin &gt; Academic Years.</p>
        </div>
    </div>
<?php else: ?>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon"><span class="material-symbols-outlined">calendar_month</span></div>
            <div class="stat-value"><?= esc($stats['plotted_classes']) ?></div>
            <div class="stat-label">Classes plotted this term</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><span class="material-symbols-outlined">groups</span></div>
            <div class="stat-value"><?= esc($stats['faculty_count']) ?></div>
            <div class="stat-label">Faculty in department</div>
        </div>
        <div class="stat-card <?= $stats['pending_approvals'] > 0 ? 'warning' : '' ?>">
            <div class="stat-icon"><span class="material-symbols-outlined">swap_horiz</span></div>
            <div class="stat-value"><?= esc($stats['pending_approvals']) ?></div>
            <div class="stat-label">Swap approvals pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><span class="material-symbols-outlined">checklist</span></div>
            <div class="stat-value"><?= esc($stats['curriculum_set']) ?> / <?= esc($stats['curriculum_slots']) ?></div>
            <div class="stat-label">Curriculum presets set</div>
        </div>
    </div>

    <div class="panel" style="margin-bottom:16px;">
        <h2>Your Department</h2>
        <p style="color:var(--color-text-secondary); margin-bottom:10px;"><?= esc($deptName) ?> &middot; Active term: <?= esc($activeYear) ?></p>
        <?php if ($programs === []): ?>
            <p style="color:var(--color-text-secondary); font-size:13px;">No programs registered under this department yet.</p>
        <?php else: ?>
            <?php foreach ($programs as $program): ?>
                <span class="program-chip"><?= esc($program) ?></span>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Quick Actions</h2>
        <a class="btn btn-primary" href="<?= site_url('chairperson/dashboard') ?>">
            <span class="material-symbols-outlined" style="font-size:18px;">calendar_month</span>
            Go to Schedule Plotting
        </a>
    </div>

<?php endif; ?>

<?= $this->endSection() ?>

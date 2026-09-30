<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'grades';
$pageTitle     = 'My Grades';
$brandSub      = 'Student Portal';
$userName      = auth()->user()->username;
$userRoleLabel = 'Student';
$navGroups     = [
    '' => [
        'dashboard' => ['label' => 'Dashboard',   'icon' => 'home',           'url' => 'student/dashboard'],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule'],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades'],
        'tuition'   => ['label' => 'Tuition & Fees', 'icon' => 'payments',    'url' => 'student/tuition'],
        'profile'   => ['label' => 'Profile',     'icon' => 'person',         'url' => 'student/profile'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">My Grades</h2></div>
</div>

<div class="panel">
    <?php if (empty($byYear)): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">grade</div>
            <p>No official grades have been posted yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($byYear as $year => $grades): ?>
            <h2><?= esc($year) ?></h2>
            <table class="grade-table">
                <thead>
                    <tr><th>Subject</th><th>Grade</th><th>Remarks</th><th>Result</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($grades as $g): ?>
                        <tr>
                            <td><?= esc($g['subject_code']) ?></td>
                            <td><?= esc($g['grade_value']) ?></td>
                            <td><?= $g['remarks'] !== null ? esc($g['remarks']) : '—' ?></td>
                            <td>
                                <?php if ($g['is_passing'] === null): ?>
                                    —
                                <?php elseif ($g['is_passing']): ?>
                                    <span class="pass-pill pass">Passed</span>
                                <?php else: ?>
                                    <span class="pass-pill fail">Failed</span>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endforeach ?>
    <?php endif ?>
</div>

<?= $this->endSection() ?>

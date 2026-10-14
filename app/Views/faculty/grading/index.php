<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'grading';
$pageTitle     = 'Grading';
$brandSub      = 'Faculty Portal';
$userName      = $user['name'];
$userRoleLabel = 'Faculty';
$navGroups     = [
    '' => [
        'dashboard'    => ['label' => 'Dashboard',    'icon' => 'dashboard',        'url' => 'faculty/dashboard'],
        'schedule'     => ['label' => 'Schedule',     'icon' => 'calendar_month',   'url' => 'faculty/schedule'],
        'availability' => ['label' => 'Availability', 'icon' => 'event_available',  'url' => 'faculty/availability'],
        'grading'      => ['label' => 'Grading',      'icon' => 'grade',            'url' => 'faculty/grading'],
        'grading-change-requests' => ['label' => 'Correction Requests', 'icon' => 'history_edu', 'url' => 'faculty/grading/change-requests'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    .class-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--color-border); }
    .class-row:last-child { border-bottom: none; }
    .class-title { font-weight: 700; font-size: 14px; color: var(--color-text-primary); }
    .class-meta { font-size: 12px; color: var(--color-text-secondary); }
    .status-pill { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); }
    .status-pill.locked { background: var(--color-success-soft); color: #14532d; }
    .status-pill.submitted { background: #dff8fb; color: #164e63; }
    .status-pill.pending { background: var(--color-warning-soft); color: #713f12; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Grading</h2></div>
</div>

<div class="panel">
    <h2>My Classes</h2>
    <p class="panel-sub">Every subject you're assigned to teach. Enter grades, submit for chairperson approval, and track lock status here.</p>

    <?php if (empty($classes)): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">grade</div>
            <p>You have no claimed teaching loads yet.</p>
        </div>
    <?php endif ?>

    <?php foreach ($classes as $c): ?>
        <?php $a = $c['assignment']; ?>
        <div class="class-row">
            <div>
                <div class="class-title"><?= esc($a['subject_code']) ?></div>
                <div class="class-meta">
                    <?= esc($a['program']) ?> &middot; <?= esc($a['year_level']) ?> &middot; Section <?= esc($a['section']) ?> &middot; <?= esc($a['academic_year']) ?>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <span class="class-meta"><?= esc($c['graded_count']) ?> / <?= esc($c['student_count']) ?> graded</span>
                <?php if ($c['locked']): ?>
                    <span class="status-pill locked">Locked</span>
                <?php elseif ($c['in_review']): ?>
                    <span class="status-pill submitted">In review (chair/dean/registrar)</span>
                <?php elseif ($c['submitted']): ?>
                    <span class="status-pill submitted">Submitted</span>
                <?php elseif ($c['graded_count'] > 0): ?>
                    <span class="status-pill pending">In progress</span>
                <?php endif ?>
                <a class="btn btn-primary" href="<?= site_url('faculty/grading/class') ?>?<?= http_build_query([
                    'academic_year' => $a['academic_year'],
                    'program'       => $a['program'],
                    'year_level'    => $a['year_level'],
                    'section'       => $a['section'],
                    'subject_code'  => $a['subject_code'],
                ]) ?>">Open</a>
            </div>
        </div>
    <?php endforeach ?>
</div>

<?= $this->endSection() ?>

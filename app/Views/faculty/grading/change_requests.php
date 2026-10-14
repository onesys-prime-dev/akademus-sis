<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'grading-change-requests';
$pageTitle     = 'Grade Correction Requests';
$brandSub      = 'Faculty Portal';
$userName      = $user['name'];
$userRoleLabel = 'Faculty';
$navGroups     = [
    '' => [
        'dashboard'    => ['label' => 'Dashboard',    'icon' => 'dashboard',        'url' => 'faculty/dashboard', 'help' => [
            'title' => 'Teaching Loads',
            'text'  => "Your assigned classes and units for the term, plus any loads still unassigned that you could pick up. Offer or request a swap with a colleague here — it only transfers once your chairperson approves it.",
        ]],
        'schedule'     => ['label' => 'Schedule',     'icon' => 'calendar_month',   'url' => 'faculty/schedule', 'help' => [
            'title' => 'My Schedule',
            'text'  => "Your weekly teaching schedule — days, times, rooms, and sections for everything you're currently assigned.",
        ]],
        'availability' => ['label' => 'Availability', 'icon' => 'event_available',  'url' => 'faculty/availability', 'help' => [
            'title' => 'Availability',
            'text'  => "Set the times you're available to teach — what your chairperson sees when assigning you to a class while plotting the schedule.",
        ]],
        'grading'      => ['label' => 'Grading',      'icon' => 'grade',            'url' => 'faculty/grading', 'help' => [
            'title' => 'Grading',
            'text'  => "Enter and submit grades for your classes. Once submitted, a class moves into the chair → dean → registrar approval chain and you can no longer edit it directly — use Correction Requests for that.",
        ]],
        'grading-change-requests' => ['label' => 'Correction Requests', 'icon' => 'history_edu', 'url' => 'faculty/grading/change-requests', 'help' => [
            'title' => 'Correction Requests',
            'text'  => "Request a change to a grade that's already locked and official. It needs chair, then dean, then registrar sign-off before the correction actually applies.",
        ]],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    .req-card { border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 16px; margin-bottom: 16px; }
    .req-card h3 { margin: 0 0 4px; }
    .req-meta { font-size: 12px; color: var(--color-text-secondary); margin-bottom: 12px; }
    .req-field { margin-bottom: 12px; }
    .req-field label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px; }
    .req-field input, .req-field textarea { width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Grade Correction Requests</h2><p class="ac-page-header__sub">The registrar has opened these for a response — provide the new value, your reason, and any supporting documents.</p></div>
</div>

<?php if (session('message')): ?><div class="flash success"><?= esc(session('message')) ?></div><?php endif ?>
<?php if (session('errors')): ?><div class="flash error"><?= esc(implode(' ', session('errors'))) ?></div><?php endif ?>

<?php if ($requests === []): ?>
    <div class="panel"><p class="panel-sub">No correction forms are currently awaiting your response.</p></div>
<?php else: ?>
    <?php foreach ($requests as $r): ?>
        <div class="req-card">
            <h3><?= esc($r['subject_code']) ?> &middot; Current grade: <?= esc($r['current_value']) ?></h3>
            <div class="req-meta"><?= esc($r['program']) ?> &middot; <?= esc($r['year_level']) ?> &middot; Section <?= esc($r['section']) ?> &middot; <?= esc($r['academic_year']) ?></div>
            <form method="post" action="<?= site_url('faculty/grading/change-requests/submit') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                <div class="req-field">
                    <label>New Value</label>
                    <input type="text" name="requested_value" required>
                </div>
                <div class="req-field">
                    <label>Reason for Correction</label>
                    <textarea name="reason" rows="3" required></textarea>
                </div>
                <div class="req-field">
                    <label>Supporting Documents (optional)</label>
                    <input type="file" name="attachments[]" multiple>
                </div>
                <button type="submit" class="btn btn-primary">Submit Response</button>
            </form>
        </div>
    <?php endforeach ?>
<?php endif ?>

<?= $this->endSection() ?>

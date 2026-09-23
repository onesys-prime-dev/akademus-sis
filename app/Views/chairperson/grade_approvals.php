<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'grade-approvals';
$pageTitle     = 'Grade Approvals';
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
<style>
    .cls-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--color-border); }
    .cls-row:last-child { border-bottom: none; }
    .cls-meta { font-size: 12px; color: var(--color-text-secondary); }
    .cr-row { padding: 10px 0; border-bottom: 1px solid var(--color-border); }
    .cr-row:last-child { border-bottom: none; }
    .cr-values { font-size: 13px; margin: 4px 0; }
    .cr-reason { font-size: 12.5px; color: var(--color-text-secondary); font-style: italic; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Grade Approvals</h2></div>
</div>

<?php if ($deptName === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>You're not assigned to a department yet. Contact an administrator.</p>
        </div>
    </div>
<?php else: ?>

    <div class="panel" id="feedback" style="display:none;"></div>

    <div class="panel">
        <h2>Awaiting Your Approval</h2>
        <p class="panel-sub">Faculty has submitted grades for these classes. Review, then lock to make them official and visible to students.</p>

        <?php if (empty($classes)): ?>
            <div class="empty-state">
                <div class="material-symbols-outlined">inbox</div>
                <p>No submitted classes waiting on your approval.</p>
            </div>
        <?php endif ?>

        <?php foreach ($classes as $c): ?>
            <div class="cls-row">
                <div>
                    <strong><?= esc($c['subject_code']) ?></strong>
                    <div class="cls-meta"><?= esc($c['program']) ?> &middot; <?= esc($c['year_level']) ?> &middot; Section <?= esc($c['section']) ?> &middot; <?= esc($c['academic_year']) ?> &middot; <?= esc($c['student_count']) ?> students</div>
                </div>
                <a class="btn btn-primary" href="<?= site_url('chairperson/grade-approvals/class') ?>?<?= http_build_query([
                    'academic_year' => $c['academic_year'],
                    'program'       => $c['program'],
                    'year_level'    => $c['year_level'],
                    'section'       => $c['section'],
                    'subject_code'  => $c['subject_code'],
                ]) ?>">Review &amp; Lock</a>
            </div>
        <?php endforeach ?>
    </div>

    <div class="panel">
        <h2>Pending Correction Requests</h2>
        <p class="panel-sub">A faculty member is asking to change a grade that's already locked. Each request needs your approval or rejection.</p>

        <?php if (empty($changeRequests)): ?>
            <div class="empty-state">
                <div class="material-symbols-outlined">fact_check</div>
                <p>No pending correction requests.</p>
            </div>
        <?php endif ?>

        <?php foreach ($changeRequests as $r): ?>
            <div class="cr-row" data-request-id="<?= esc($r['request_id']) ?>">
                <strong><?= esc($r['student_username']) ?></strong> &middot; <?= esc($r['subject_code']) ?> &middot;
                <?= esc($r['program']) ?> <?= esc($r['year_level']) ?>-<?= esc($r['section']) ?> &middot; <?= esc($r['academic_year']) ?>
                <div class="cr-values"><?= esc($r['current_value']) ?> &rarr; <strong><?= esc($r['requested_value']) ?></strong> &middot; requested by <?= esc($r['requested_by_username']) ?></div>
                <div class="cr-reason">"<?= esc($r['reason']) ?>"</div>
                <div style="display:flex; gap:8px; margin-top:8px;">
                    <button type="button" class="btn btn-primary" onclick="reviewChange(<?= esc($r['request_id']) ?>, 'approve')">Approve</button>
                    <button type="button" class="btn btn-secondary" onclick="reviewChange(<?= esc($r['request_id']) ?>, 'reject')">Reject</button>
                </div>
            </div>
        <?php endforeach ?>
    </div>

<?php endif ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function showFeedback(message, isError) {
        const el = document.getElementById('feedback');
        el.style.display = 'block';
        el.style.color = isError ? '#a11414' : '#056945';
        el.textContent = message;
    }

    async function postJSON(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        return res.json();
    }

    async function reviewChange(requestId, action) {
        const notes = prompt(action === 'approve' ? 'Optional note for this approval:' : 'Reason for rejecting (optional):') || '';

        const url = action === 'approve'
            ? '<?= site_url('chairperson/grade-approvals/change-requests/approve') ?>'
            : '<?= site_url('chairperson/grade-approvals/change-requests/reject') ?>';

        const result = await postJSON(url, { request_id: requestId, notes });

        if (!result.ok) {
            showFeedback(result.error || 'Could not process the request.', true);
            return;
        }

        document.querySelector(`[data-request-id="${requestId}"]`)?.remove();
        showFeedback(`Request ${action === 'approve' ? 'approved' : 'rejected'}.`, false);
    }
</script>
<?= $this->endSection() ?>

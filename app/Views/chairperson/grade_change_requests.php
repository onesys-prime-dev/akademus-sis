<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'grade-change-requests';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/grade-approvals.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/grade-approvals.css') ?>">
<style>
    .req-card { border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 16px; margin-bottom: 16px; }
    .req-card h3 { margin: 0 0 4px; }
    .req-meta { font-size: 12px; color: var(--color-text-secondary); margin-bottom: 8px; }
    .req-change { display: flex; align-items: center; gap: 8px; font-weight: 700; margin-bottom: 8px; }
    .req-reason { font-size: 13px; color: var(--color-text-secondary); margin-bottom: 12px; }
    .req-actions { display: flex; gap: 8px; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Grade Correction Requests</h2><p class="ac-page-header__sub">Faculty responses awaiting your recommendation, on their way to the dean.</p></div>
</div>

<div id="feedback" class="ga-feedback" role="status" aria-live="polite" hidden></div>

<?php if ($requests === []): ?>
    <div class="ga-card ga-empty"><span class="material-symbols-outlined" aria-hidden="true">task_alt</span><div><h3>Nothing to review</h3><p>Correction requests faculty respond to will appear here.</p></div></div>
<?php else: ?>
    <?php foreach ($requests as $r): ?>
        <div class="req-card" data-request-id="<?= (int) $r['request_id'] ?>">
            <h3><?= esc($r['subject_code']) ?> &middot; <?= esc($r['student_username']) ?></h3>
            <div class="req-meta"><?= esc($r['program']) ?> &middot; <?= esc($r['year_level']) ?> &middot; Section <?= esc($r['section']) ?> &middot; <?= esc($r['academic_year']) ?> &middot; Requested by <?= esc($r['requested_by_username']) ?></div>
            <div class="req-change"><span><?= esc($r['current_value']) ?></span><span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span><span><?= esc($r['requested_value']) ?></span></div>
            <p class="req-reason"><?= esc($r['reason']) ?></p>
            <div class="req-actions">
                <button type="button" class="ga-button ga-button--reject" data-decision="reject">Reject</button>
                <button type="button" class="ga-button ga-button--primary" data-decision="recommend">Recommend</button>
            </div>
        </div>
    <?php endforeach ?>
<?php endif ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
    function showFeedback(message, isError) {
        const el = document.getElementById('feedback');
        el.hidden = false; el.className = 'ga-feedback' + (isError ? ' is-error' : ''); el.textContent = message;
    }
    document.querySelectorAll('[data-decision]').forEach(button => {
        button.addEventListener('click', async () => {
            const card = button.closest('[data-request-id]');
            const requestId = Number(card.dataset.requestId);
            const decision = button.dataset.decision;
            const confirmMsg = decision === 'recommend' ? 'Recommend this correction? It will move on to the dean.' : 'Reject this correction request?';
            if (!await acConfirm(confirmMsg)) return;
            const endpoint = decision === 'recommend' ? '<?= site_url('chairperson/grade-change-requests/recommend') ?>' : '<?= site_url('chairperson/grade-change-requests/reject') ?>';
            try {
                const res = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ request_id: requestId }) });
                const result = await res.json().catch(() => null);
                if (!res.ok || !result?.ok) throw new Error(result?.error || 'Could not save this decision.');
                card.remove();
                showFeedback(decision === 'recommend' ? 'Correction recommended and sent to the dean.' : 'Correction request rejected.', false);
            } catch (error) { showFeedback(error.message, true); }
        });
    });
</script>
<?= $this->endSection() ?>

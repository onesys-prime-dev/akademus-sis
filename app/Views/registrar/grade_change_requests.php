<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/grade-approvals.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/grade-approvals.css') ?>">
<style>
    .req-card { border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 16px; margin-bottom: 16px; }
    .req-card h3 { margin: 0 0 4px; }
    .req-meta { font-size: 12px; color: var(--color-text-secondary); margin-bottom: 8px; }
    .req-change { display: flex; align-items: center; gap: 8px; font-weight: 700; margin-bottom: 8px; }
    .req-reason { font-size: 13px; color: var(--color-text-secondary); margin-bottom: 12px; }
    .req-actions { display: flex; gap: 8px; }
    .issue-search { position: relative; max-width: 480px; margin-bottom: 24px; }
    .issue-search input { width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); }
    .issue-results { border: 1px solid var(--color-border); border-radius: var(--radius-sm); margin-top: 6px; max-height: 280px; overflow-y: auto; }
    .issue-result { padding: 10px 12px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); }
    .issue-result:last-child { border-bottom: none; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Grade Correction Requests</h2><p class="ac-page-header__sub">Open a new correction window against a locked grade, and give final approval once the dean has recommended.</p></div>
</div>

<div id="feedback" class="ga-feedback" role="status" aria-live="polite" hidden></div>

<div class="ga-card">
    <h3>Issue a Correction Form</h3>
    <p class="panel-sub">Search by student username or subject code to find a locked grade.</p>
    <div class="issue-search">
        <input type="text" id="issueSearchInput" placeholder="e.g. jdoe or ITE 102">
        <div class="issue-results" id="issueResults" hidden></div>
    </div>
</div>

<h3>Awaiting Final Approval</h3>
<?php if ($requests === []): ?>
    <div class="ga-card ga-empty"><span class="material-symbols-outlined" aria-hidden="true">task_alt</span><div><h3>Nothing to approve</h3><p>Correction requests the dean recommends will appear here.</p></div></div>
<?php else: ?>
    <?php foreach ($requests as $r): ?>
        <div class="req-card" data-request-id="<?= (int) $r['request_id'] ?>">
            <h3><?= esc($r['subject_code']) ?> &middot; <?= esc($r['student_username']) ?></h3>
            <div class="req-meta"><?= esc($r['program']) ?> &middot; <?= esc($r['year_level']) ?> &middot; Section <?= esc($r['section']) ?> &middot; <?= esc($r['academic_year']) ?> &middot; Requested by <?= esc($r['requested_by_username']) ?></div>
            <div class="req-change"><span><?= esc($r['current_value']) ?></span><span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span><span><?= esc($r['requested_value']) ?></span></div>
            <p class="req-reason"><?= esc($r['reason']) ?></p>
            <div class="req-actions">
                <button type="button" class="ga-button ga-button--primary" data-approve-final>Approve &amp; Apply</button>
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

    const searchInput = document.getElementById('issueSearchInput');
    const resultsBox = document.getElementById('issueResults');
    let searchTimer = null;

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const query = searchInput.value.trim();
        if (query.length < 2) { resultsBox.hidden = true; resultsBox.replaceChildren(); return; }
        searchTimer = setTimeout(async () => {
            const res = await fetch('<?= site_url('registrar/grade-change-requests/search') ?>?q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => null);
            resultsBox.replaceChildren();
            if (!data?.ok || !data.results.length) { resultsBox.hidden = true; return; }
            data.results.forEach(row => {
                const item = document.createElement('div');
                item.className = 'issue-result';
                const label = document.createElement('span');
                label.textContent = `${row.student_username} · ${row.subject_code} · ${row.grade_value} · ${row.academic_year}`;
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'ga-button ga-button--primary'; button.textContent = 'Issue form';
                button.addEventListener('click', () => issueForm(row.grade_id, button));
                item.append(label, button);
                resultsBox.append(item);
            });
            resultsBox.hidden = false;
        }, 300);
    });

    async function issueForm(gradeId, button) {
        if (!await acConfirm('Open a correction form against this grade? The faculty of record will be asked to respond.')) return;
        button.disabled = true;
        try {
            const res = await fetch('<?= site_url('registrar/grade-change-requests/issue') ?>', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ grade_id: gradeId }) });
            const result = await res.json().catch(() => null);
            if (!res.ok || !result?.ok) throw new Error(result?.error || 'Could not issue the form.');
            showFeedback('Correction form issued to the faculty of record.', false);
            resultsBox.hidden = true; searchInput.value = '';
        } catch (error) { showFeedback(error.message, true); button.disabled = false; }
    }

    document.querySelectorAll('[data-approve-final]').forEach(button => {
        button.addEventListener('click', async () => {
            const card = button.closest('[data-request-id]');
            const requestId = Number(card.dataset.requestId);
            if (!await acConfirm('Approve and apply this correction? The official grade will be updated immediately.')) return;
            try {
                const res = await fetch('<?= site_url('registrar/grade-change-requests/final-approve') ?>', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ request_id: requestId }) });
                const result = await res.json().catch(() => null);
                if (!res.ok || !result?.ok) throw new Error(result?.error || 'Could not approve this request.');
                card.remove();
                showFeedback('Correction approved and applied to the official record.', false);
            } catch (error) { showFeedback(error.message, true); }
        });
    });
</script>
<?= $this->endSection() ?>

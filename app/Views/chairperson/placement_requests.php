<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'placement-requests';
$pageTitle     = 'Shared Subject Requests';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('content') ?>

<div class="pr-card">
    <div class="pr-card__head">
        <h2>Requests I've Submitted</h2>
        <p>A request never gives you write access to another unit's offering — the owning unit decides the final class composition.</p>
    </div>

    <?php if ($mine === []): ?>
        <div class="pr-empty">You haven't submitted any shared-subject requests yet.</div>
    <?php else: ?>
        <table class="pr-table">
            <thead>
                <tr><th>Subject</th><th>Type</th><th>Status</th><th>Submitted</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($mine as $r): ?>
                    <tr>
                        <td>#<?= esc($r['subject_id']) ?></td>
                        <td><?= esc($r['request_type']) ?></td>
                        <td><span class="pr-status pr-status--<?= esc($r['status']) ?>"><?= esc($r['status']) ?></span></td>
                        <td><?= esc($r['created_at']) ?></td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                                <button type="button" class="pr-btn pr-btn--cancel" data-id="<?= (int) $r['id'] ?>">Cancel</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="pr-card">
    <div class="pr-card__head">
        <h2>Requests For Subjects I Manage</h2>
        <p>Approve, deny, or fulfill requests for subjects your unit owns. Fulfilling a placement attaches the requesting section to an offering — it never happens automatically.</p>
    </div>

    <?php if ($toReview === []): ?>
        <div class="pr-empty">Nothing pending review.</div>
    <?php else: ?>
        <table class="pr-table">
            <thead>
                <tr><th>Subject</th><th>Type</th><th>Requesting Unit</th><th>Section</th><th>Submitted</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($toReview as $r): ?>
                    <tr>
                        <td>#<?= esc($r['subject_id']) ?></td>
                        <td><?= esc($r['request_type']) ?></td>
                        <td>#<?= esc($r['requesting_sub_dept_id']) ?></td>
                        <td>#<?= esc($r['section_id']) ?></td>
                        <td><?= esc($r['created_at']) ?></td>
                        <td class="pr-actions">
                            <button type="button" class="pr-btn pr-btn--approve" data-id="<?= (int) $r['id'] ?>">Approve</button>
                            <button type="button" class="pr-btn pr-btn--deny" data-id="<?= (int) $r['id'] ?>">Deny</button>
                            <?php if ($r['request_type'] === 'placement'): ?>
                                <button type="button" class="pr-btn pr-btn--fulfill" data-id="<?= (int) $r['id'] ?>">Fulfill…</button>
                            <?php else: ?>
                                <button type="button" class="pr-btn pr-btn--fulfill" data-id="<?= (int) $r['id'] ?>">Mark Fulfilled</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
    .pr-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); margin-bottom: 20px; }
    .pr-card__head { padding: 20px 24px; border-bottom: 1px solid var(--color-border); }
    .pr-card__head h2 { margin: 0; font-size: 16px; font-weight: 800; }
    .pr-card__head p { margin: 6px 0 0; font-size: 12.5px; color: var(--color-text-secondary); max-width: 640px; }
    .pr-empty { padding: 24px; color: var(--color-text-secondary); font-size: 13px; }
    .pr-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .pr-table th, .pr-table td { text-align: left; padding: 10px 24px; border-bottom: 1px solid var(--color-border); }
    .pr-status { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: var(--radius-pill); background: var(--color-primary-soft); color: var(--color-primary); }
    .pr-btn { border: 1px solid var(--color-border); background: var(--color-surface); border-radius: var(--radius-md); padding: 4px 10px; font-size: 12px; cursor: pointer; margin-right: 6px; }
</style>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const prUrls = {
        cancel:  '<?= site_url('chairperson/placement-requests/cancel') ?>',
        approve: '<?= site_url('chairperson/placement-requests/approve') ?>',
        deny:    '<?= site_url('chairperson/placement-requests/deny') ?>',
        fulfill: '<?= site_url('chairperson/placement-requests/fulfill') ?>',
    };

    async function postJSON(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        return res.json();
    }

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.pr-btn');
        if (!btn) return;
        const id = parseInt(btn.dataset.id, 10);
        let url = null;
        const body = { id };

        if (btn.classList.contains('pr-btn--cancel')) url = prUrls.cancel;
        if (btn.classList.contains('pr-btn--approve')) url = prUrls.approve;
        if (btn.classList.contains('pr-btn--deny')) url = prUrls.deny;
        if (btn.classList.contains('pr-btn--fulfill')) {
            url = prUrls.fulfill;
            const offeringId = prompt('Target offering ID (leave blank to use the one already on the request):');
            if (offeringId) body.target_offering_id = parseInt(offeringId, 10);
        }
        if (!url) return;

        let data = await postJSON(url, body);

        if (!data.ok && data.requires_override) {
            const reason = prompt(`This would exceed the offering's capacity (${data.prospective_roster}/${data.capacity}). Enter a reason to override, or cancel:`);
            if (!reason) return;
            body.override_capacity = true;
            body.override_reason = reason;
            data = await postJSON(url, body);
        }

        if (!data.ok) {
            alert(data.error || 'Could not complete the action.');
            return;
        }

        window.location.reload();
    });
</script>
<?= $this->endSection() ?>

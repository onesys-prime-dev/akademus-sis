<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'placement-requests';
$pageTitle     = 'Shared Subject Requests';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

$typeLabels = [
    'placement'            => 'Placement',
    'additional_offering'  => 'Additional Offering',
    'conflict_report'      => 'Conflict Report',
];

$statusLabels = [
    'pending'   => 'Pending',
    'approved'  => 'Approved',
    'denied'    => 'Denied',
    'fulfilled' => 'Fulfilled',
    'cancelled' => 'Cancelled',
];

$subjectLabel = static function (array $r): string {
    return $r['subject_code'] !== null
        ? $r['subject_code'] . ' — ' . $r['subject_name']
        : 'Subject #' . $r['subject_id'];
};

$sectionLabel = static function (array $r): string {
    return $r['section_label'] !== null
        ? $r['section_program'] . ' · ' . $r['section_year_level'] . ' · Section ' . $r['section_label']
        : 'Section #' . $r['section_id'];
};
?>

<?= $this->section('styles') ?>
<style>
    .pr-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-xs); margin-bottom: 20px; }
    .pr-card__head { display: flex; align-items: flex-start; gap: 14px; padding: 20px 24px; border-bottom: 1px solid var(--color-border); }
    .pr-card__icon {
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        width: 40px; height: 40px; border-radius: var(--radius-md);
        background: var(--color-primary-soft); color: var(--color-primary);
    }
    .pr-card__icon .material-symbols-outlined { font-size: 20px; }
    .pr-card__head h2 { margin: 0; font-size: 15.5px; font-weight: 750; letter-spacing: -.01em; color: var(--color-text-primary); }
    .pr-card__head p { margin: 6px 0 0; font-size: 12.5px; color: var(--color-text-secondary); max-width: 640px; line-height: 1.5; }

    .pr-empty { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 40px 24px; color: var(--color-text-secondary); font-size: 13px; text-align: center; }
    .pr-empty .material-symbols-outlined { font-size: 34px; opacity: .4; }

    .pr-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .pr-table th, .pr-table td { text-align: left; padding: 12px 24px; border-bottom: 1px solid var(--color-border); vertical-align: top; }
    .pr-table tbody tr:last-child td { border-bottom: none; }
    .pr-table tbody tr:hover { background: var(--color-surface-secondary); }
    .pr-table th { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--color-text-tertiary); }

    .pr-subject { font-weight: 650; color: var(--color-text-primary); }
    .pr-section { color: var(--color-text-secondary); font-size: 12px; margin-top: 2px; }

    .pr-type { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 650; color: var(--color-text-secondary); }
    .pr-type .material-symbols-outlined { font-size: 15px; }

    .pr-status { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); white-space: nowrap; }
    .pr-status--pending { background: var(--color-warning-soft); color: var(--color-warning); }
    .pr-status--approved { background: var(--color-info-soft); color: var(--color-info); }
    .pr-status--fulfilled { background: var(--color-success-soft); color: var(--color-success); }
    .pr-status--denied { background: var(--color-danger-soft); color: var(--color-danger); }
    .pr-status--cancelled { background: var(--color-surface-sunken); color: var(--color-text-tertiary); }

    .pr-btn {
        border: 1px solid var(--color-border-control); background: var(--color-surface); color: var(--color-text-primary);
        border-radius: var(--radius-md); padding: 6px 12px; font-size: 11.5px; font-weight: 650; font-family: inherit;
        cursor: pointer; margin-right: 6px; transition: background-color var(--transition-fast), border-color var(--transition-fast), color var(--transition-fast);
    }
    .pr-btn:hover { border-color: var(--color-primary); background: var(--color-primary-soft); color: var(--color-primary); }
    .pr-btn--approve { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
    .pr-btn--approve:hover { background: var(--color-primary-hover); border-color: var(--color-primary-hover); color: #fff; }
    .pr-btn--fulfill { background: var(--color-success); border-color: var(--color-success); color: #fff; }
    .pr-btn--fulfill:hover { background: var(--color-success); border-color: var(--color-success); color: #fff; opacity: .9; }
    .pr-btn--deny, .pr-btn--cancel { color: var(--color-danger); }
    .pr-btn--deny:hover, .pr-btn--cancel:hover { border-color: var(--color-danger); background: var(--color-danger-soft); color: var(--color-danger); }
    .pr-actions { white-space: nowrap; }

    @media (max-width: 760px) {
        .pr-table, .pr-table thead, .pr-table tbody, .pr-table th, .pr-table td, .pr-table tr { display: block; }
        .pr-table thead { display: none; }
        .pr-table tr { padding: 14px 24px; border-bottom: 1px solid var(--color-border); }
        .pr-table td { padding: 3px 0; border-bottom: none; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h1 class="ac-page-header__title">Shared Subject Requests</h1>
        <p class="ac-page-header__sub">Track requests you've sent to other departments for subjects they own, and review requests sent to you for subjects your own department owns.</p>
    </div>
</div>

<div class="pr-card">
    <div class="pr-card__head">
        <span class="pr-card__icon"><span class="material-symbols-outlined" aria-hidden="true">outbox</span></span>
        <div>
            <h2>Requests I've Submitted</h2>
            <p>A request never gives you write access to another unit's offering — the owning unit decides the final class composition.</p>
        </div>
    </div>

    <?php if ($mine === []): ?>
        <div class="pr-empty">
            <span class="material-symbols-outlined" aria-hidden="true">inbox</span>
            You haven't submitted any shared-subject requests yet.
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="pr-table">
            <thead>
                <tr><th>Subject</th><th>Type</th><th>Status</th><th>Submitted</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($mine as $r): ?>
                    <tr>
                        <td data-label="Subject">
                            <div class="pr-subject"><?= esc($subjectLabel($r)) ?></div>
                            <div class="pr-section"><?= esc($sectionLabel($r)) ?></div>
                        </td>
                        <td data-label="Type"><span class="pr-type"><?= esc($typeLabels[$r['request_type']] ?? $r['request_type']) ?></span></td>
                        <td data-label="Status"><span class="pr-status pr-status--<?= esc($r['status'], 'attr') ?>"><?= esc($statusLabels[$r['status']] ?? $r['status']) ?></span></td>
                        <td data-label="Submitted"><?= esc(date('M j, Y g:i A', strtotime($r['created_at']))) ?></td>
                        <td data-label="" class="pr-actions">
                            <?php if ($r['status'] === 'pending'): ?>
                                <button type="button" class="pr-btn pr-btn--cancel" data-id="<?= (int) $r['id'] ?>">Cancel</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="pr-card">
    <div class="pr-card__head">
        <span class="pr-card__icon"><span class="material-symbols-outlined" aria-hidden="true">move_to_inbox</span></span>
        <div>
            <h2>Requests For Subjects I Manage</h2>
            <p>Approve, deny, or fulfill requests for subjects your unit owns. Fulfilling a placement attaches the requesting section to an offering — it never happens automatically.</p>
        </div>
    </div>

    <?php if ($toReview === []): ?>
        <div class="pr-empty">
            <span class="material-symbols-outlined" aria-hidden="true">task_alt</span>
            Nothing pending review.
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="pr-table">
            <thead>
                <tr><th>Subject</th><th>Type</th><th>Requesting Unit</th><th>Submitted</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($toReview as $r): ?>
                    <tr>
                        <td data-label="Subject">
                            <div class="pr-subject"><?= esc($subjectLabel($r)) ?></div>
                            <div class="pr-section"><?= esc($sectionLabel($r)) ?></div>
                        </td>
                        <td data-label="Type"><span class="pr-type"><?= esc($typeLabels[$r['request_type']] ?? $r['request_type']) ?></span></td>
                        <td data-label="Requesting Unit"><?= esc($r['requesting_dept_name'] ?? ('Unit #' . $r['requesting_sub_dept_id'])) ?></td>
                        <td data-label="Submitted"><?= esc(date('M j, Y g:i A', strtotime($r['created_at']))) ?></td>
                        <td data-label="" class="pr-actions">
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
        </div>
    <?php endif; ?>
</div>

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

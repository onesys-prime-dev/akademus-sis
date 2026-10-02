<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'shared-subject-coverage';
$pageTitle     = 'Shared Subject Coverage';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

$counts = ['fully_covered' => 0, 'partially_covered' => 0, 'not_covered' => 0, 'no_demand' => 0];
foreach ($coverage['subjects'] as $s) {
    $counts[$s['coverage']] = ($counts[$s['coverage']] ?? 0) + 1;
}
$openRequests = 0;
foreach ($coverage['subjects'] as $s) {
    foreach ($s['sections'] as $sec) {
        if ($sec['has_pending_placement'] || $sec['has_approved_placement'] || $sec['has_pending_additional'] || $sec['has_pending_conflict']) {
            $openRequests++;
        }
    }
}
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    .ssc-term-form { display: flex; gap: 8px; align-items: center; margin-bottom: 16px; }
    .ssc-term-form select { font-size: 13px; padding: 6px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-md); }

    .ssc-summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    .ssc-summary span { font-size: 12px; font-weight: 700; padding: 5px 12px; border-radius: var(--radius-pill); background: var(--color-surface-secondary); }

    .ssc-coverage { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); }
    .ssc-coverage--fully_covered { background: var(--color-success-soft); color: #166534; }
    .ssc-coverage--partially_covered { background: var(--color-warning-soft); color: var(--color-warning); }
    .ssc-coverage--not_covered { background: #fee2e2; color: #b91c1c; }

    .ssc-section-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--color-border); font-size: 12.5px; }
    .ssc-section-row:last-child { border-bottom: none; }

    .ssc-offering { border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 12px 14px; margin-top: 10px; }
    .ssc-offering__top { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; flex-wrap: wrap; }
    .ssc-meetings { font-size: 12.5px; color: var(--color-text-secondary); margin-top: 6px; }
    .ssc-conflict { color: #b91c1c; font-size: 12px; margin-top: 4px; }
    .ssc-no-conflict { color: #166534; font-size: 12px; margin-top: 4px; }

    .ssc-btn { border: 1px solid var(--color-border); background: var(--color-surface); border-radius: var(--radius-md); padding: 4px 10px; font-size: 12px; cursor: pointer; margin-right: 6px; margin-top: 6px; }
    .ssc-btn[disabled] { opacity: 0.5; cursor: default; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h1 class="ac-page-header__title">Shared Subject Coverage</h1>
        <p class="ac-page-header__sub">Subjects your students need from other academic units, and whether they're covered.</p>
    </div>
</div>

<form class="ssc-term-form" method="get">
    <label for="ssc-term" style="font-size:12.5px;color:var(--color-text-secondary);">Term</label>
    <select id="ssc-term" name="academic_year" onchange="this.form.submit()">
        <?php foreach ($terms as $t): ?>
            <option value="<?= esc($t) ?>" <?= $t === $selectedTerm ? 'selected' : '' ?>><?= esc($t) ?></option>
        <?php endforeach ?>
    </select>
</form>

<div id="feedback" class="flash" style="display:none;"></div>

<?php if ($selectedTerm === null): ?>
    <div class="panel"><div class="empty-state"><div class="material-symbols-outlined">calendar_month</div><p>No academic term is configured yet.</p></div></div>
<?php elseif (empty($coverage['subjects'])): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">task_alt</div>
            <p>All of your students are currently covered for externally provided subjects, or none are required this term.</p>
        </div>
    </div>
<?php else: ?>

    <div class="ssc-summary">
        <span>External subjects needed: <?= count($coverage['subjects']) ?></span>
        <span>Fully covered: <?= $counts['fully_covered'] ?></span>
        <span>Partially covered: <?= $counts['partially_covered'] ?></span>
        <span>Not covered: <?= $counts['not_covered'] ?></span>
        <span>Open requests: <?= $openRequests ?></span>
    </div>

    <?php foreach ($coverage['subjects'] as $s): ?>
        <div class="panel mc-card">
            <div class="mc-card__head">
                <div class="mc-card__title-row">
                    <h2><?= esc($s['subject_code']) ?> — <?= esc($s['subject_name']) ?></h2>
                    <span class="ssc-coverage ssc-coverage--<?= esc($s['coverage']) ?>"><?= esc(str_replace('_', ' ', ucfirst($s['coverage']))) ?></span>
                </div>
                <p>Provided by: <strong><?= esc($s['owner_name']) ?></strong></p>
            </div>

            <div style="padding:16px 24px;">
                <div class="ssd-stats" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:12px;">
                    <div class="stat-card"><div class="stat-value"><?= esc($s['my_expected']) ?></div><div class="stat-label">My Expected</div></div>
                    <div class="stat-card" title="Assignment and enrollment are currently the same state in this SIS."><div class="stat-value"><?= esc($s['my_assigned']) ?></div><div class="stat-label">My Enrolled / Assigned</div></div>
                    <div class="stat-card <?= $s['my_unassigned'] > 0 ? 'warning' : '' ?>"><div class="stat-value"><?= esc($s['my_unassigned']) ?></div><div class="stat-label">My Unassigned</div></div>
                </div>

                <details>
                    <summary style="font-size:12.5px;color:var(--color-text-secondary);cursor:pointer;">My sections (<?= count($s['sections']) ?>)</summary>
                    <div style="margin-top:6px;">
                        <?php foreach ($s['sections'] as $sec): ?>
                            <div class="ssc-section-row">
                                <span><?= esc($sec['label']) ?></span>
                                <span>Expected <?= esc($sec['expected_count']) ?> · Assigned <?= esc($sec['assigned_count']) ?> · <?= $sec['unassigned_count'] > 0 ? 'Unassigned ' . esc($sec['unassigned_count']) : 'Covered' ?></span>
                            </div>
                        <?php endforeach ?>
                    </div>
                </details>

                <div style="margin-top:14px;">
                    <div class="mc-meta" style="margin-bottom:6px;">Candidate Offerings</div>

                    <?php if (empty($s['offerings'])): ?>
                        <div class="empty-state" style="padding:12px 0;"><p>No offering exists for this subject yet this term.</p></div>
                    <?php endif ?>

                    <?php foreach ($s['offerings'] as $o): ?>
                        <div class="ssc-offering">
                            <div class="ssc-offering__top">
                                <span><strong><?= esc($o['label']) ?></strong></span>
                                <span class="mc-cap-pill"><?= $o['capacity'] !== null ? ($o['is_full'] ? 'Full' : esc($o['available_seats']) . ' available') : esc($o['roster_count']) . ' enrolled (unlimited)' ?></span>
                            </div>
                            <?php if (! empty($o['sections'])): ?>
                                <div class="mc-pills" style="margin-top:6px;"><?php foreach ($o['sections'] as $label): ?><span class="mc-pill"><?= esc($label) ?></span><?php endforeach ?></div>
                            <?php endif ?>
                            <div class="ssc-meetings">
                                <?php if ($o['is_scheduled']): ?>
                                    <?php foreach ($o['meetings'] as $m): ?>
                                        <div><?= esc($m['day_label']) ?> <?= esc($m['start']) ?>–<?= esc($m['end']) ?> · <?= esc($m['room']) ?><?= $m['faculty'] ? ' · ' . esc($m['faculty']) : '' ?></div>
                                    <?php endforeach ?>
                                <?php else: ?>
                                    <em>Not yet scheduled</em>
                                <?php endif ?>
                            </div>

                            <?php foreach ($s['sections'] as $sec): ?>
                                <?php $conflicts = $o['conflicts_by_section'][$sec['section_id']] ?? []; ?>
                                <?php if ($sec['unassigned_count'] === 0) continue; ?>
                                <div style="margin-top:8px;padding-top:8px;border-top:1px dashed var(--color-border);">
                                    <div style="font-size:12px;"><strong><?= esc($sec['label']) ?></strong> (<?= esc($sec['unassigned_count']) ?> unassigned)</div>
                                    <?php if (! empty($conflicts)): ?>
                                        <?php foreach ($conflicts as $c): ?><div class="ssc-conflict">Conflict: <?= esc($c) ?></div><?php endforeach ?>
                                    <?php else: ?>
                                        <div class="ssc-no-conflict">No schedule conflict</div>
                                    <?php endif ?>
                                    <?php if ($o['available_seats'] !== null && $o['available_seats'] < $sec['unassigned_count']): ?>
                                        <div class="ssc-conflict">Insufficient capacity for all <?= esc($sec['unassigned_count']) ?> students</div>
                                    <?php endif ?>

                                    <?php if ($sec['has_pending_placement'] || $sec['has_approved_placement']): ?>
                                        <button class="ssc-btn" disabled>Request Pending</button>
                                    <?php elseif (! empty($conflicts)): ?>
                                        <button class="ssc-btn" <?= $sec['has_pending_conflict'] ? 'disabled' : '' ?> onclick="submitRequest(<?= $s['subject_id'] ?>, <?= $sec['section_id'] ?>, 'conflict_report', <?= $o['offering_id'] ?>)">
                                            <?= $sec['has_pending_conflict'] ? 'Conflict Reported' : 'Report Conflict' ?>
                                        </button>
                                    <?php else: ?>
                                        <button class="ssc-btn" onclick="submitRequest(<?= $s['subject_id'] ?>, <?= $sec['section_id'] ?>, 'placement', <?= $o['offering_id'] ?>)">Request Placement</button>
                                    <?php endif ?>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endforeach ?>

                    <?php foreach ($s['sections'] as $sec): ?>
                        <?php if ($sec['unassigned_count'] === 0) continue; ?>
                        <?php if ($sec['has_pending_additional']): ?>
                            <button class="ssc-btn" disabled>Additional Offering Requested — <?= esc($sec['label']) ?></button>
                        <?php else: ?>
                            <button class="ssc-btn" onclick="submitRequest(<?= $s['subject_id'] ?>, <?= $sec['section_id'] ?>, 'additional_offering', null)">Request Additional Offering — <?= esc($sec['label']) ?></button>
                        <?php endif ?>
                    <?php endforeach ?>
                </div>

                <details style="margin-top:10px;">
                    <summary style="font-size:11.5px;color:var(--color-text-secondary);cursor:pointer;">⚠ Data notes</summary>
                    <ul style="font-size:11.5px;color:var(--color-text-secondary);margin:4px 0 0 16px;">
                        <li>Demand reflects students currently sectioned this term; it may not include retakers who've advanced past this subject's nominal term, and cannot yet exclude credited/transferee exemptions.</li>
                        <li>"Enrolled" and "Assigned" are the same underlying state today.</li>
                    </ul>
                </details>
            </div>
        </div>
    <?php endforeach ?>

<?php endif ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function showFeedback(message, isError) {
        const el = document.getElementById('feedback');
        el.style.display = 'block';
        el.className = 'flash ' + (isError ? 'error' : 'success');
        el.textContent = message;
    }

    async function submitRequest(subjectId, sectionId, requestType, targetOfferingId) {
        let notes = null;
        if (requestType === 'conflict_report' || requestType === 'additional_offering') {
            notes = prompt('Add a short note for the owning scheduler (optional):') || null;
        }

        const res = await fetch('<?= site_url('chairperson/placement-requests/create') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                subject_id: subjectId,
                section_id: sectionId,
                request_type: requestType,
                target_offering_id: targetOfferingId,
                notes: notes,
            }),
        });
        const data = await res.json();

        if (!data.ok) {
            showFeedback(data.error || 'Could not submit the request.', true);
            return;
        }

        showFeedback('Request submitted.', false);
        setTimeout(() => location.reload(), 600);
    }
</script>
<?= $this->endSection() ?>

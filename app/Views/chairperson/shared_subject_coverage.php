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
    .ssc-term-form { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
    .ssc-term-form label { font-size: 12.5px; font-weight: 650; color: var(--color-text-secondary); }
    .ssc-term-form select {
        font-size: 13px; font-family: inherit; padding: 8px 12px;
        border: 1px solid var(--color-border-control); border-radius: var(--radius-md);
        background: var(--color-surface); color: var(--color-text-primary);
    }
    .ssc-term-form select:focus { outline: none; border-color: var(--color-primary); box-shadow: var(--focus-ring); }

    /* Summary chip row */
    .ssc-summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 22px; }
    .ssc-chip {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 8px 14px; border-radius: var(--radius-pill);
        background: var(--color-surface); border: 1px solid var(--color-border);
        font-size: 12.5px; color: var(--color-text-secondary);
    }
    .ssc-chip strong { font-size: 13.5px; font-weight: 750; color: var(--color-text-primary); }
    .ssc-chip--success strong { color: var(--color-success); }
    .ssc-chip--warning strong { color: var(--color-warning); }
    .ssc-chip--danger strong { color: var(--color-danger); }
    .ssc-chip--info strong { color: var(--color-info); }

    /* Card header, same pattern as the Shared Subject Dashboard */
    .mc-card { padding: 0; }
    .mc-card__head { padding: 20px 24px; border-bottom: 1px solid var(--color-border); display: flex; align-items: flex-start; gap: 14px; }
    .mc-card__icon {
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        width: 40px; height: 40px; border-radius: var(--radius-md);
        background: var(--color-primary-soft); color: var(--color-primary);
    }
    .mc-card__icon .material-symbols-outlined { font-size: 20px; }
    .mc-card__head-body { min-width: 0; }
    .mc-card__title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .mc-card__title-row h2 { margin: 0; font-size: 15.5px; font-weight: 750; letter-spacing: -.01em; color: var(--color-text-primary); }
    .mc-card__head p { margin: 6px 0 0; font-size: 12.5px; color: var(--color-text-secondary); }
    .mc-card__head p strong { color: var(--color-text-primary); font-weight: 650; }

    .mc-card__body { padding: 20px 24px 24px; }

    .ssc-coverage { font-size: 11px; font-weight: 700; padding: 4px 11px; border-radius: var(--radius-pill); white-space: nowrap; }
    .ssc-coverage--fully_covered { background: var(--color-success-soft); color: var(--color-success); }
    .ssc-coverage--partially_covered { background: var(--color-warning-soft); color: var(--color-warning); }
    .ssc-coverage--not_covered { background: var(--color-danger-soft); color: var(--color-danger); }

    .ssd-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
    @media (max-width: 720px) { .ssd-stats { grid-template-columns: 1fr; } }
    .ssd-stats .stat-card { padding: 16px 18px; gap: 8px; box-shadow: none; }
    .ssd-stats .stat-icon { width: 32px; height: 32px; }
    .ssd-stats .stat-icon .material-symbols-outlined { font-size: 17px; }
    .ssd-stats .stat-value { font-size: 24px; }

    details > summary { font-size: 12.5px; font-weight: 650; color: var(--color-text-secondary); cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
    details > summary:hover { color: var(--color-primary); }
    details[open] > summary { color: var(--color-primary); margin-bottom: 6px; }

    .ssc-section-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 9px 2px; border-bottom: 1px solid var(--color-border); font-size: 12.5px; }
    .ssc-section-row:last-child { border-bottom: none; }
    .ssc-section-row span:first-child { font-weight: 650; color: var(--color-text-primary); }
    .ssc-section-row span:last-child { color: var(--color-text-secondary); text-align: right; }

    .ssc-section-label {
        font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
        color: var(--color-text-tertiary); margin: 20px 0 10px;
    }

    .mc-pills { display: flex; flex-wrap: wrap; gap: 8px; }
    .mc-pill {
        display: inline-flex; align-items: center; gap: 4px;
        background: var(--color-surface); border: 1px solid var(--color-border);
        border-radius: var(--radius-md); padding: 5px 12px;
        font-size: 12px; font-weight: 600; color: var(--color-text-primary);
    }
    .mc-cap-pill {
        font-size: 10.5px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill);
        background: var(--color-surface-sunken); color: var(--color-text-secondary); white-space: nowrap;
    }
    .mc-cap-pill--full { background: var(--color-danger-soft); color: var(--color-danger); }

    .ssc-offering { border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 14px 16px; margin-top: 12px; transition: border-color var(--transition-fast); }
    .ssc-offering:hover { border-color: var(--color-border-medium); }
    .ssc-offering__top { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; flex-wrap: wrap; }
    .ssc-offering__title { font-weight: 700; font-size: 13.5px; color: var(--color-text-primary); }
    .ssc-meetings { font-size: 12.5px; color: var(--color-text-secondary); margin-top: 8px; line-height: 1.6; }

    .ssc-need {
        margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--color-border);
    }
    .ssc-need__head { display: flex; align-items: center; gap: 8px; font-size: 12.5px; }
    .ssc-need__head strong { color: var(--color-text-primary); }
    .ssc-need__head span { font-size: 11px; color: var(--color-text-tertiary); }
    .ssc-status { display: flex; align-items: center; gap: 5px; font-size: 12px; margin-top: 6px; }
    .ssc-status .material-symbols-outlined { font-size: 16px; }
    .ssc-status--conflict { color: var(--color-danger); }
    .ssc-status--ok { color: var(--color-success); }

    .ssc-btn {
        display: inline-flex; align-items: center; gap: 6px;
        border: 1px solid var(--color-border-control); background: var(--color-surface);
        color: var(--color-text-primary); border-radius: var(--radius-md);
        padding: 7px 13px; font-size: 12px; font-weight: 650; font-family: inherit;
        cursor: pointer; margin: 8px 6px 0 0; transition: background-color var(--transition-fast), border-color var(--transition-fast);
    }
    .ssc-btn:hover:not([disabled]) { border-color: var(--color-primary); background: var(--color-primary-soft); color: var(--color-primary); }
    .ssc-btn[disabled] { opacity: .55; cursor: default; }
    .ssc-btn--primary { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
    .ssc-btn--primary:hover:not([disabled]) { background: var(--color-primary-hover); border-color: var(--color-primary-hover); color: #fff; }

    .ssd-note { font-size: 11.5px; color: var(--color-text-secondary); line-height: 1.65; }

    .mc-card__body > .empty-state { padding: 20px 0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h1 class="ac-page-header__title">Shared Subject Coverage</h1>
        <p class="ac-page-header__sub">For every subject your own students need that another department teaches: how many are covered by an existing offering, and where you can request placement, an extra offering, or report a schedule conflict.</p>
    </div>
</div>

<form class="ssc-term-form" method="get">
    <label for="ssc-term">Term</label>
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
        <span class="ssc-chip">External subjects needed <strong><?= count($coverage['subjects']) ?></strong></span>
        <span class="ssc-chip ssc-chip--success">Fully covered <strong><?= $counts['fully_covered'] ?></strong></span>
        <span class="ssc-chip ssc-chip--warning">Partially covered <strong><?= $counts['partially_covered'] ?></strong></span>
        <span class="ssc-chip ssc-chip--danger">Not covered <strong><?= $counts['not_covered'] ?></strong></span>
        <span class="ssc-chip ssc-chip--info">Open requests <strong><?= $openRequests ?></strong></span>
    </div>

    <?php foreach ($coverage['subjects'] as $s): ?>
        <div class="panel mc-card">
            <div class="mc-card__head">
                <span class="mc-card__icon"><span class="material-symbols-outlined" aria-hidden="true">subject</span></span>
                <div class="mc-card__head-body">
                    <div class="mc-card__title-row">
                        <h2><?= esc($s['subject_code']) ?> — <?= esc($s['subject_name']) ?></h2>
                        <span class="ssc-coverage ssc-coverage--<?= esc($s['coverage']) ?>"><?= esc(str_replace('_', ' ', ucfirst($s['coverage']))) ?></span>
                    </div>
                    <p>Provided by <strong><?= esc($s['owner_name']) ?></strong></p>
                </div>
            </div>

            <div class="mc-card__body">
                <div class="ssd-stats">
                    <div class="stat-card">
                        <span class="stat-icon"><span class="material-symbols-outlined" aria-hidden="true">groups</span></span>
                        <div class="stat-value"><?= esc($s['my_expected']) ?></div>
                        <div class="stat-label">My Expected</div>
                    </div>
                    <div class="stat-card" title="Assignment and enrollment are currently the same state in this SIS.">
                        <span class="stat-icon"><span class="material-symbols-outlined" aria-hidden="true">how_to_reg</span></span>
                        <div class="stat-value"><?= esc($s['my_assigned']) ?></div>
                        <div class="stat-label">My Enrolled / Assigned</div>
                    </div>
                    <div class="stat-card <?= $s['my_unassigned'] > 0 ? 'warning' : '' ?>">
                        <span class="stat-icon"><span class="material-symbols-outlined" aria-hidden="true">person_off</span></span>
                        <div class="stat-value"><?= esc($s['my_unassigned']) ?></div>
                        <div class="stat-label">My Unassigned</div>
                    </div>
                </div>

                <details>
                    <summary>My sections (<?= count($s['sections']) ?>)</summary>
                    <div>
                        <?php foreach ($s['sections'] as $sec): ?>
                            <div class="ssc-section-row">
                                <span><?= esc($sec['label']) ?></span>
                                <span>Expected <?= esc($sec['expected_count']) ?> · Assigned <?= esc($sec['assigned_count']) ?> · <?= $sec['unassigned_count'] > 0 ? 'Unassigned ' . esc($sec['unassigned_count']) : 'Covered' ?></span>
                            </div>
                        <?php endforeach ?>
                    </div>
                </details>

                <div class="ssc-section-label">Candidate Offerings</div>

                <?php if (empty($s['offerings'])): ?>
                    <div class="empty-state"><p>No offering exists for this subject yet this term.</p></div>
                <?php endif ?>

                <?php foreach ($s['offerings'] as $o): ?>
                    <div class="ssc-offering">
                        <div class="ssc-offering__top">
                            <span class="ssc-offering__title"><?= esc($o['label']) ?></span>
                            <span class="mc-cap-pill<?= $o['capacity'] !== null && $o['is_full'] ? ' mc-cap-pill--full' : '' ?>"><?= $o['capacity'] !== null ? ($o['is_full'] ? 'Full' : esc($o['available_seats']) . ' available') : esc($o['roster_count']) . ' enrolled (unlimited)' ?></span>
                        </div>
                        <?php if (! empty($o['sections'])): ?>
                            <div class="mc-pills" style="margin-top:8px;"><?php foreach ($o['sections'] as $label): ?><span class="mc-pill"><?= esc($label) ?></span><?php endforeach ?></div>
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
                            <div class="ssc-need">
                                <div class="ssc-need__head"><strong><?= esc($sec['label']) ?></strong><span>(<?= esc($sec['unassigned_count']) ?> unassigned)</span></div>
                                <?php if (! empty($conflicts)): ?>
                                    <?php foreach ($conflicts as $c): ?>
                                        <div class="ssc-status ssc-status--conflict"><span class="material-symbols-outlined" aria-hidden="true">error</span>Conflict: <?= esc($c) ?></div>
                                    <?php endforeach ?>
                                <?php else: ?>
                                    <div class="ssc-status ssc-status--ok"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span>No schedule conflict</div>
                                <?php endif ?>
                                <?php if ($o['available_seats'] !== null && $o['available_seats'] < $sec['unassigned_count']): ?>
                                    <div class="ssc-status ssc-status--conflict"><span class="material-symbols-outlined" aria-hidden="true">error</span>Insufficient capacity for all <?= esc($sec['unassigned_count']) ?> students</div>
                                <?php endif ?>

                                <?php if ($sec['has_pending_placement'] || $sec['has_approved_placement']): ?>
                                    <button class="ssc-btn" disabled>Request Pending</button>
                                <?php elseif (! empty($conflicts)): ?>
                                    <button class="ssc-btn<?= $sec['has_pending_conflict'] ? '' : ' ssc-btn--primary' ?>" <?= $sec['has_pending_conflict'] ? 'disabled' : '' ?> onclick="submitRequest(<?= $s['subject_id'] ?>, <?= $sec['section_id'] ?>, 'conflict_report', <?= $o['offering_id'] ?>)">
                                        <?= $sec['has_pending_conflict'] ? 'Conflict Reported' : 'Report Conflict' ?>
                                    </button>
                                <?php else: ?>
                                    <button class="ssc-btn ssc-btn--primary" onclick="submitRequest(<?= $s['subject_id'] ?>, <?= $sec['section_id'] ?>, 'placement', <?= $o['offering_id'] ?>)">Request Placement</button>
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

                <details style="margin-top:14px;">
                    <summary>&#9888; Data notes</summary>
                    <ul class="ssd-note" style="margin:0 0 0 16px;">
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

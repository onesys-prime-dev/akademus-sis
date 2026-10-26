<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'shared-subjects';
$pageTitle     = 'Shared Subject Dashboard';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    .ssd-toolbar { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; margin-bottom: 22px; }
    .ssd-term-form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .ssd-term-form label { font-size: 12.5px; font-weight: 650; color: var(--color-text-secondary); }
    .ssd-term-form select {
        font-size: 13px; font-family: inherit; padding: 8px 12px;
        border: 1px solid var(--color-border-control); border-radius: var(--radius-md);
        background: var(--color-surface); color: var(--color-text-primary);
    }
    .ssd-term-form select:focus { outline: none; border-color: var(--color-primary); box-shadow: var(--focus-ring); }
    .ssd-toggle { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: var(--color-text-secondary); cursor: pointer; }
    .ssd-toggle input { accent-color: var(--color-primary); width: 15px; height: 15px; }

    /* Card header, borrowed from the Merged Classes page's mc-card pattern
       plus a subject icon badge for visual identity. */
    .mc-card { padding: 0; }
    .mc-card__head { display: flex; align-items: flex-start; gap: 14px; padding: 20px 24px; border-bottom: 1px solid var(--color-border); }
    .mc-card__icon {
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        width: 40px; height: 40px; border-radius: var(--radius-md);
        background: var(--color-primary-soft); color: var(--color-primary);
    }
    .mc-card__icon .material-symbols-outlined { font-size: 20px; }
    .mc-card__title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .mc-card__title-row h2 { margin: 0; font-size: 15.5px; font-weight: 750; letter-spacing: -.01em; color: var(--color-text-primary); }

    .mc-card__body { padding: 20px 24px 24px; }

    .ssd-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
    .ssd-badge { font-size: 11px; font-weight: 700; padding: 4px 11px; border-radius: var(--radius-pill); white-space: nowrap; }
    .ssd-badge--warn { background: var(--color-warning-soft); color: var(--color-warning); }
    .ssd-badge--danger { background: var(--color-danger-soft); color: var(--color-danger); }
    .ssd-badge--info { background: var(--color-info-soft); color: var(--color-info); }

    .ssd-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
    @media (max-width: 720px) { .ssd-stats { grid-template-columns: 1fr; } }
    .ssd-stats .stat-card { padding: 16px 18px; gap: 8px; box-shadow: none; }
    .ssd-stats .stat-icon { width: 32px; height: 32px; }
    .ssd-stats .stat-icon .material-symbols-outlined { font-size: 17px; }
    .ssd-stats .stat-value { font-size: 24px; }

    details > summary { font-size: 12.5px; font-weight: 650; color: var(--color-text-secondary); cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
    details > summary:hover { color: var(--color-primary); }
    details[open] > summary { color: var(--color-primary); margin-bottom: 8px; }
    details + details { margin-top: 6px; }

    .mc-pills { display: flex; flex-wrap: wrap; gap: 8px; }
    .mc-pill {
        display: inline-flex; align-items: center; gap: 4px;
        background: var(--color-surface); border: 1px solid var(--color-border);
        border-radius: var(--radius-md); padding: 5px 12px;
        font-size: 12px; font-weight: 600; color: var(--color-text-primary);
    }

    .ssd-note { font-size: 11.5px; color: var(--color-text-secondary); line-height: 1.65; }

    .ssd-requests {
        display: flex; align-items: center; flex-wrap: wrap; gap: 4px;
        margin-top: 16px; padding: 12px 14px;
        background: var(--color-primary-soft); border-radius: var(--radius-md);
        font-size: 12.5px; color: var(--color-text-secondary);
    }
    .ssd-requests b { color: var(--color-text-primary); }
    .ssd-requests a { margin-left: auto; font-weight: 700; color: var(--color-primary); text-decoration: none; white-space: nowrap; }
    .ssd-requests a:hover { text-decoration: underline; }

    .ssd-offering {
        border: 1px solid var(--color-border); border-radius: var(--radius-md);
        padding: 14px 16px; margin-top: 12px;
        transition: border-color var(--transition-fast);
    }
    .ssd-offering:hover { border-color: var(--color-border-medium); }
    .ssd-offering__top { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; flex-wrap: wrap; }
    .ssd-offering__title { font-weight: 700; font-size: 13.5px; color: var(--color-text-primary); }
    .mc-cap-pill {
        font-size: 10.5px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill);
        background: var(--color-surface-sunken); color: var(--color-text-secondary); white-space: nowrap;
    }
    .mc-cap-pill--over { background: var(--color-danger-soft); color: var(--color-danger); }
    .ssd-meetings { font-size: 12.5px; color: var(--color-text-secondary); margin-top: 8px; line-height: 1.65; }
    .ssd-meeting-row { margin-bottom: 2px; }
    .ssd-offering a { font-size: 12.5px; font-weight: 700; color: var(--color-primary); text-decoration: none; }
    .ssd-offering a:hover { text-decoration: underline; }

    .mc-card__body > .empty-state { padding: 28px 0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h1 class="ac-page-header__title">Shared Subject Dashboard</h1>
        <p class="ac-page-header__sub">For every subject your department owns: how many students need it, whether it's scheduled yet, and any pending placement requests from other departments — in one place.</p>
    </div>
</div>

<div class="ssd-toolbar">
    <form class="ssd-term-form" method="get">
        <label for="ssd-term">Term</label>
        <select id="ssd-term" name="academic_year" onchange="this.form.submit()">
            <?php foreach ($terms as $t): ?>
                <option value="<?= esc($t) ?>" <?= $t === $selectedTerm ? 'selected' : '' ?>><?= esc($t) ?></option>
            <?php endforeach ?>
        </select>
        <label class="ssd-toggle">
            <input type="checkbox" name="show_inactive" value="1" <?= $includeInactive ? 'checked' : '' ?> onchange="this.form.submit()">
            Show subjects with no activity
        </label>
    </form>
</div>

<?php if ($selectedTerm === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">calendar_month</div>
            <p>No academic term is configured yet. An administrator needs to set one up.</p>
        </div>
    </div>
<?php elseif (empty($dashboard['subjects'])): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">inbox</div>
            <?php if ($dashboard['inactive_count'] > 0): ?>
                <p><?= $dashboard['inactive_count'] ?> subject<?= $dashboard['inactive_count'] === 1 ? '' : 's' ?> you manage had no demand, offerings, or requests this term.
                    <a href="?academic_year=<?= urlencode($selectedTerm) ?>&show_inactive=1">Show them anyway</a>.</p>
            <?php else: ?>
                <p>You don't currently manage any subjects, or none have activity for this term yet.</p>
            <?php endif ?>
        </div>
    </div>
<?php else: ?>

    <?php
    $attentionMeta = [
        'unassigned_demand'             => ['label' => 'Unassigned demand', 'class' => 'warn'],
        'pending_requests'               => ['label' => 'Pending requests', 'class' => 'info'],
        'approved_awaiting_fulfillment'  => ['label' => 'Approved, awaiting fulfillment', 'class' => 'info'],
        'over_capacity'                  => ['label' => 'Over capacity', 'class' => 'danger'],
        'needs_schedule'                 => ['label' => 'Needs schedule', 'class' => 'warn'],
        'no_faculty'                     => ['label' => 'No faculty assigned', 'class' => 'danger'],
    ];
    ?>

    <?php foreach ($dashboard['subjects'] as $s): ?>
        <?php $d = $s['demand']; ?>
        <div class="panel mc-card">
            <div class="mc-card__head">
                <span class="mc-card__icon"><span class="material-symbols-outlined" aria-hidden="true">subject</span></span>
                <div class="mc-card__title-row">
                    <h2><?= esc($s['subject_code']) ?> — <?= esc($s['subject_name']) ?></h2>
                </div>
            </div>

            <div class="mc-card__body">
                <?php if (! empty($s['attention'])): ?>
                    <div class="ssd-badges">
                        <?php foreach (array_unique($s['attention']) as $a): ?>
                            <?php $meta = $attentionMeta[$a] ?? ['label' => $a, 'class' => 'warn']; ?>
                            <span class="ssd-badge ssd-badge--<?= esc($meta['class'], 'attr') ?>"><?= esc($meta['label']) ?></span>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>

                <div class="ssd-stats">
                    <div class="stat-card">
                        <span class="stat-icon"><span class="material-symbols-outlined" aria-hidden="true">groups</span></span>
                        <div class="stat-value"><?= esc($d['expected_count'] ?? 0) ?></div>
                        <div class="stat-label">Expected Demand</div>
                    </div>
                    <div class="stat-card" title="Assignment and enrollment are currently the same state in this SIS.">
                        <span class="stat-icon"><span class="material-symbols-outlined" aria-hidden="true">how_to_reg</span></span>
                        <div class="stat-value"><?= esc($d['assigned_count'] ?? 0) ?></div>
                        <div class="stat-label">Enrolled / Assigned</div>
                    </div>
                    <div class="stat-card <?= ($d['unassigned_count'] ?? 0) > 0 ? 'warning' : '' ?>">
                        <span class="stat-icon"><span class="material-symbols-outlined" aria-hidden="true">person_off</span></span>
                        <div class="stat-value"><?= esc($d['unassigned_count'] ?? 0) ?></div>
                        <div class="stat-label">Unassigned</div>
                    </div>
                </div>

                <?php if (! empty($d['by_program'])): ?>
                    <details>
                        <summary>Program breakdown</summary>
                        <div class="mc-pills">
                            <?php foreach ($d['by_program'] as $bp): ?>
                                <span class="mc-pill"><?= esc($bp['program_name'] ?? 'Unknown') ?>: <?= esc($bp['expected_count']) ?></span>
                            <?php endforeach ?>
                        </div>
                    </details>
                <?php endif ?>

                <?php if (! empty($d['limitations'])): ?>
                    <details>
                        <summary>&#9888; Data notes</summary>
                        <ul class="ssd-note" style="margin:0 0 0 16px;">
                            <li>Demand reflects students currently sectioned this term; it may not include retakers who've advanced past this subject's nominal term, and cannot yet exclude credited/transferee exemptions.</li>
                            <li>"Enrolled" and "Assigned" are the same underlying state today — there is no separate "reserved but unconfirmed" status in this system yet.</li>
                        </ul>
                    </details>
                <?php endif ?>

                <?php if ($s['requests']['pending_total'] > 0 || $s['requests']['approved_not_fulfilled'] > 0): ?>
                    <div class="ssd-requests">
                        <?php $byType = $s['requests']['pending_by_type']; ?>
                        <?php if (! empty($byType['placement'])): ?><b><?= esc($byType['placement']) ?></b> placement request<?= $byType['placement'] === 1 ? '' : 's' ?> pending&nbsp;&middot;&nbsp;<?php endif ?>
                        <?php if (! empty($byType['additional_offering'])): ?><b><?= esc($byType['additional_offering']) ?></b> additional-offering request<?= $byType['additional_offering'] === 1 ? '' : 's' ?> pending&nbsp;&middot;&nbsp;<?php endif ?>
                        <?php if (! empty($byType['conflict_report'])): ?><b><?= esc($byType['conflict_report']) ?></b> conflict report<?= $byType['conflict_report'] === 1 ? '' : 's' ?> pending&nbsp;&middot;&nbsp;<?php endif ?>
                        <?php if ($s['requests']['approved_not_fulfilled'] > 0): ?><b><?= esc($s['requests']['approved_not_fulfilled']) ?></b> approved, awaiting fulfillment<?php endif ?>
                        <a href="<?= site_url('chairperson/placement-requests') ?>">Review requests →</a>
                    </div>
                <?php endif ?>

                <?php if (empty($s['offerings'])): ?>
                    <div class="empty-state">
                        <p>No offering exists for this subject yet this term.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($s['offerings'] as $o): ?>
                        <div class="ssd-offering">
                            <div class="ssd-offering__top">
                                <span class="ssd-offering__title"><?= esc($o['label']) ?></span>
                                <span class="mc-cap-pill<?= $o['capacity'] !== null && $o['over_capacity_by'] > 0 ? ' mc-cap-pill--over' : '' ?>">
                                    <?= $o['capacity'] !== null
                                        ? ($o['over_capacity_by'] > 0
                                            ? 'Over Capacity by ' . esc($o['over_capacity_by'])
                                            : esc($o['roster_count']) . ' / ' . esc($o['capacity']) . ' (' . esc($o['available_seats']) . ' available)')
                                        : esc($o['roster_count']) . ' enrolled (unlimited capacity)' ?>
                                </span>
                            </div>

                            <?php if (! empty($o['sections'])): ?>
                                <div class="mc-pills" style="margin-top:8px;">
                                    <?php foreach ($o['sections'] as $label): ?>
                                        <span class="mc-pill"><?= esc($label) ?></span>
                                    <?php endforeach ?>
                                </div>
                            <?php endif ?>

                            <div class="ssd-meetings">
                                <?php if ($o['is_scheduled']): ?>
                                    <?php foreach ($o['meetings'] as $m): ?>
                                        <div class="ssd-meeting-row">
                                            <?= esc($m['day_label']) ?> <?= esc($m['start']) ?>–<?= esc($m['end']) ?> · <?= esc($m['room']) ?>
                                            <?= $m['no_faculty'] ? ' · <em>no faculty assigned</em>' : ' · ' . esc($m['faculty']) ?>
                                        </div>
                                    <?php endforeach ?>
                                <?php else: ?>
                                    <em>Not yet scheduled</em>
                                <?php endif ?>
                            </div>

                            <div style="margin-top:10px;">
                                <a href="<?= site_url('chairperson/offerings') ?>">Manage offering →</a>
                            </div>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>
    <?php endforeach ?>

<?php endif ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    // Same styled floating-listbox every other portal page uses
    // (window.enhanceSelect, loaded globally in the layout) instead of the
    // browser's own unstyled popup. The native <select> stays the real
    // value holder, so the existing onchange="this.form.submit()" keeps
    // firing untouched — enhanceSelect's choose() dispatches a real
    // 'change' event on it rather than just updating a visible label.
    if (window.enhanceSelect) window.enhanceSelect(document.getElementById('ssd-term'));
</script>
<?= $this->endSection() ?>

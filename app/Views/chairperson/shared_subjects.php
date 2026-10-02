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
    .ssd-term-form { display: flex; gap: 8px; align-items: center; margin-bottom: 16px; }
    .ssd-term-form select { font-size: 13px; padding: 6px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-md); }

    .ssd-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin: 4px 0 12px; }
    @media (max-width: 720px) { .ssd-stats { grid-template-columns: 1fr; } }

    .ssd-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
    .ssd-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); background: var(--color-warning-soft); color: var(--color-warning); }

    .ssd-requests { font-size: 12.5px; color: var(--color-text-secondary); margin-bottom: 10px; }
    .ssd-requests b { color: var(--color-text-primary); }

    .ssd-offering { border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 12px 14px; margin-top: 10px; }
    .ssd-offering__top { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; flex-wrap: wrap; }
    .ssd-offering__title { font-weight: 700; font-size: 13.5px; }
    .ssd-meetings { font-size: 12.5px; color: var(--color-text-secondary); margin-top: 6px; }
    .ssd-meeting-row { margin-bottom: 2px; }

    .ssd-note { font-size: 11.5px; color: var(--color-text-secondary); margin-top: 8px; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h1 class="ac-page-header__title">Shared Subject Dashboard</h1>
        <p class="ac-page-header__sub">Demand, requests, and offerings for every subject your unit owns or manages — in one place.</p>
    </div>
</div>

<form class="ssd-term-form" method="get">
    <label for="ssd-term" style="font-size:12.5px;color:var(--color-text-secondary);">Term</label>
    <select id="ssd-term" name="academic_year" onchange="this.form.submit()">
        <?php foreach ($terms as $t): ?>
            <option value="<?= esc($t) ?>" <?= $t === $selectedTerm ? 'selected' : '' ?>><?= esc($t) ?></option>
        <?php endforeach ?>
    </select>
    <label style="font-size:12.5px;color:var(--color-text-secondary);display:flex;align-items:center;gap:4px;">
        <input type="checkbox" name="show_inactive" value="1" <?= $includeInactive ? 'checked' : '' ?> onchange="this.form.submit()">
        Show subjects with no activity
    </label>
</form>

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

    <?php foreach ($dashboard['subjects'] as $s): ?>
        <?php $d = $s['demand']; ?>
        <div class="panel mc-card">
            <div class="mc-card__head">
                <div class="mc-card__title-row">
                    <h2><?= esc($s['subject_code']) ?> — <?= esc($s['subject_name']) ?></h2>
                </div>
            </div>

            <div style="padding: 16px 24px;">
                <?php if (! empty($s['attention'])): ?>
                    <div class="ssd-badges">
                        <?php
                        $labels = [
                            'unassigned_demand' => 'Unassigned demand',
                            'pending_requests' => 'Pending requests',
                            'approved_awaiting_fulfillment' => 'Approved, awaiting fulfillment',
                            'over_capacity' => 'Over capacity',
                            'needs_schedule' => 'Needs schedule',
                            'no_faculty' => 'No faculty assigned',
                        ];
                        ?>
                        <?php foreach (array_unique($s['attention']) as $a): ?>
                            <span class="ssd-badge"><?= esc($labels[$a] ?? $a) ?></span>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>

                <div class="ssd-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?= esc($d['expected_count'] ?? 0) ?></div>
                        <div class="stat-label">Expected Demand</div>
                    </div>
                    <div class="stat-card" title="Assignment and enrollment are currently the same state in this SIS.">
                        <div class="stat-value"><?= esc($d['assigned_count'] ?? 0) ?></div>
                        <div class="stat-label">Enrolled / Assigned</div>
                    </div>
                    <div class="stat-card <?= ($d['unassigned_count'] ?? 0) > 0 ? 'warning' : '' ?>">
                        <div class="stat-value"><?= esc($d['unassigned_count'] ?? 0) ?></div>
                        <div class="stat-label">Unassigned</div>
                    </div>
                </div>

                <?php if (! empty($d['by_program'])): ?>
                    <details>
                        <summary style="font-size:12.5px;color:var(--color-text-secondary);cursor:pointer;">Program breakdown</summary>
                        <div style="margin-top:6px;">
                            <?php foreach ($d['by_program'] as $bp): ?>
                                <span class="mc-pill" style="margin:0 4px 4px 0;"><?= esc($bp['program_name'] ?? 'Unknown') ?>: <?= esc($bp['expected_count']) ?></span>
                            <?php endforeach ?>
                        </div>
                    </details>
                <?php endif ?>

                <?php if (! empty($d['limitations'])): ?>
                    <details style="margin-top:6px;">
                        <summary style="font-size:11.5px;color:var(--color-text-secondary);cursor:pointer;">⚠ Data notes</summary>
                        <ul class="ssd-note" style="margin:4px 0 0 16px;">
                            <li>Demand reflects students currently sectioned this term; it may not include retakers who've advanced past this subject's nominal term, and cannot yet exclude credited/transferee exemptions.</li>
                            <li>"Enrolled" and "Assigned" are the same underlying state today — there is no separate "reserved but unconfirmed" status in this system yet.</li>
                        </ul>
                    </details>
                <?php endif ?>

                <?php if ($s['requests']['pending_total'] > 0 || $s['requests']['approved_not_fulfilled'] > 0): ?>
                    <div class="ssd-requests" style="margin-top:12px;">
                        <?php $byType = $s['requests']['pending_by_type']; ?>
                        <?php if (! empty($byType['placement'])): ?><b><?= esc($byType['placement']) ?></b> placement request<?= $byType['placement'] === 1 ? '' : 's' ?> pending · <?php endif ?>
                        <?php if (! empty($byType['additional_offering'])): ?><b><?= esc($byType['additional_offering']) ?></b> additional-offering request<?= $byType['additional_offering'] === 1 ? '' : 's' ?> pending · <?php endif ?>
                        <?php if (! empty($byType['conflict_report'])): ?><b><?= esc($byType['conflict_report']) ?></b> conflict report<?= $byType['conflict_report'] === 1 ? '' : 's' ?> pending · <?php endif ?>
                        <?php if ($s['requests']['approved_not_fulfilled'] > 0): ?><b><?= esc($s['requests']['approved_not_fulfilled']) ?></b> approved, awaiting fulfillment<?php endif ?>
                        <a href="<?= site_url('chairperson/placement-requests') ?>" style="margin-left:8px;">Review requests →</a>
                    </div>
                <?php endif ?>

                <?php if (empty($s['offerings'])): ?>
                    <div class="empty-state" style="padding:16px 0;">
                        <p>No offering exists for this subject yet this term.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($s['offerings'] as $o): ?>
                        <div class="ssd-offering">
                            <div class="ssd-offering__top">
                                <span class="ssd-offering__title"><?= esc($o['label']) ?></span>
                                <span class="mc-cap-pill">
                                    <?= $o['capacity'] !== null
                                        ? ($o['over_capacity_by'] > 0
                                            ? 'Over Capacity by ' . esc($o['over_capacity_by'])
                                            : esc($o['roster_count']) . ' / ' . esc($o['capacity']) . ' (' . esc($o['available_seats']) . ' available)')
                                        : esc($o['roster_count']) . ' enrolled (unlimited capacity)' ?>
                                </span>
                            </div>

                            <?php if (! empty($o['sections'])): ?>
                                <div class="mc-pills" style="margin-top:6px;">
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

                            <div style="margin-top:8px;">
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

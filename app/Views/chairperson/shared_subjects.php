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
<link rel="stylesheet" href="<?= base_url('assets/css/pages/shared-subjects.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/shared-subjects.css') ?>">
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
        <div class="panel ssd-card">
            <div class="ssd-card__head">
                <span class="ssd-card__icon"><span class="material-symbols-outlined" aria-hidden="true">subject</span></span>
                <div class="ssd-card__title">
                    <strong><?= esc($s['subject_code']) ?></strong>
                    <span><?= esc($s['subject_name']) ?></span>
                </div>
            </div>

            <div class="ssd-card__body">
                <?php if (! empty($s['attention'])): ?>
                    <div class="ssd-badges">
                        <?php foreach (array_unique($s['attention']) as $a): ?>
                            <?php $meta = $attentionMeta[$a] ?? ['label' => $a, 'class' => 'warn']; ?>
                            <span class="ssd-badge ssd-badge--<?= esc($meta['class'], 'attr') ?>"><?= esc($meta['label']) ?></span>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>

                <div class="ssd-stats">
                    <div class="ssd-stat">
                        <div class="ssd-stat__value"><?= esc($d['expected_count'] ?? 0) ?></div>
                        <div class="ssd-stat__label">Expected Demand</div>
                    </div>
                    <div class="ssd-stat" title="Assignment and enrollment are currently the same state in this SIS.">
                        <div class="ssd-stat__value"><?= esc($d['assigned_count'] ?? 0) ?></div>
                        <div class="ssd-stat__label">Enrolled / Assigned</div>
                    </div>
                    <div class="ssd-stat<?= ($d['unassigned_count'] ?? 0) > 0 ? ' ssd-stat--warn' : '' ?>">
                        <div class="ssd-stat__value"><?= esc($d['unassigned_count'] ?? 0) ?></div>
                        <div class="ssd-stat__label">Unassigned</div>
                    </div>
                </div>

                <?php if (! empty($d['by_program'])): ?>
                    <div class="ssd-drawer-group">
                        <button type="button" class="ssd-drawer-trigger" aria-expanded="false">Program breakdown<span class="material-symbols-outlined" aria-hidden="true">expand_more</span></button>
                        <div class="ssd-drawer">
                            <div class="mc-pills">
                                <?php foreach ($d['by_program'] as $bp): ?>
                                    <span class="mc-pill"><?= esc($bp['program_name'] ?? 'Unknown') ?>: <?= esc($bp['expected_count']) ?></span>
                                <?php endforeach ?>
                            </div>
                        </div>
                    </div>
                <?php endif ?>

                <?php if (! empty($d['limitations'])): ?>
                    <div class="ssd-drawer-group">
                        <button type="button" class="ssd-drawer-trigger" aria-expanded="false">&#9888; Data notes<span class="material-symbols-outlined" aria-hidden="true">expand_more</span></button>
                        <div class="ssd-drawer">
                            <ul class="ssd-note">
                                <li>Demand reflects students currently sectioned this term; it may not include retakers who've advanced past this subject's nominal term, and cannot yet exclude credited/transferee exemptions.</li>
                                <li>"Enrolled" and "Assigned" are the same underlying state today — there is no separate "reserved but unconfirmed" status in this system yet.</li>
                            </ul>
                        </div>
                    </div>
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
                        <div class="ssd-empty-cta">
                            <a class="ac-btn ac-btn-sm ac-btn-secondary ssd-plot-btn" href="<?= site_url('chairperson/offerings') ?>">
                                Create offering<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($s['offerings'] as $o): ?>
                        <?php
                            $ctx = $o['plot_context'];
                            $plotUrl = ($ctx !== null && $ayLabel !== null && $semester !== null)
                                ? site_url('chairperson/dashboard') . '?' . http_build_query([
                                    'ay_label'   => $ayLabel,
                                    'semester'   => $semester,
                                    'program'    => $ctx['program'],
                                    'year_level' => $ctx['year_level'],
                                    'section'    => $ctx['section'],
                                ])
                                : null;
                        ?>
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
                                    <div class="ssd-unscheduled">
                                        <span class="ssd-unscheduled__label"><span class="ssd-unscheduled__dot" aria-hidden="true"></span>Not yet scheduled</span>
                                        <?php if ($plotUrl !== null): ?>
                                            <a class="ac-btn ac-btn-sm ac-btn-secondary ssd-plot-btn" href="<?= esc($plotUrl, 'attr') ?>">
                                                Plot it<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                                            </a>
                                        <?php endif ?>
                                    </div>
                                <?php endif ?>
                            </div>

                            <a class="ssd-offering__manage" href="<?= site_url('chairperson/offerings') ?>">Manage offering →</a>
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

    // Program breakdown / Data notes: a button+div pair instead of native
    // <details>/<summary>, so the open/close can actually animate (see
    // .ssd-drawer in shared-subjects.css) — native <details> just snaps
    // via display:none, which can't be transitioned.
    document.querySelectorAll('.ssd-drawer-trigger').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var open = btn.getAttribute('aria-expanded') !== 'true';
            btn.setAttribute('aria-expanded', String(open));
            btn.closest('.ssd-drawer-group').classList.toggle('is-open', open);
        });
    });
</script>
<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'overview';
$pageTitle     = 'Dashboard';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    /* ===== Welcome hero ===== */
    .dash-hero {
        background: var(--color-primary-hover);
        border-radius: var(--radius-xl);
        color: #fff;
        padding: 24px 28px;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-xs);
    }
    .dash-hero__inner { position: relative; z-index: 1; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 24px; }
    .dash-hero__main { max-width: 640px; }
    .dash-hero__badges { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 10px; }
    .dash-hero__badge {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill);
        background: rgba(16, 185, 129, .18); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, .3);
    }
    .dash-hero__badge .dot { width: 6px; height: 6px; border-radius: 50%; background: #34d399; }
    .dash-hero__term { font-size: 12px; color: rgba(255, 255, 255, .7); }
    .dash-hero__title { font-size: 22px; font-weight: 700; letter-spacing: -.02em; margin: 0 0 8px; color: #fff; }
    .dash-hero__sub { font-size: 13.5px; color: rgba(255, 255, 255, .78); line-height: 1.6; margin: 0; }

    .dash-hero__actions { display: flex; flex-direction: column; gap: 8px; flex: none; }
    .dash-hero__actions .btn { justify-content: center; white-space: nowrap; }
    .dash-hero__actions .btn-hero-primary { background: #fff; color: var(--color-primary-hover); box-shadow: var(--shadow-xs); }
    .dash-hero__actions .btn-hero-primary:hover { background: #eef2ff; }
    .dash-hero__actions .btn-hero-secondary { background: rgba(255, 255, 255, .12); color: #fff; border: 1px solid rgba(255, 255, 255, .25); }
    .dash-hero__actions .btn-hero-secondary:hover { background: rgba(255, 255, 255, .2); }

    /* ===== KPI cards ===== */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px; }
    .kpi-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 20px; box-shadow: var(--shadow-sm); transition: box-shadow var(--transition-fast); }
    .kpi-card:hover { box-shadow: var(--shadow-md); }
    .kpi-card__top { display: flex; align-items: flex-start; justify-content: flex-end; gap: 10px; min-height: 1px; }
    .kpi-flag { font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: var(--radius-pill); white-space: nowrap; }
    .kpi-flag--ok { background: var(--color-success-soft); color: #166534; }
    .kpi-flag--warn { background: var(--color-warning-soft); color: #92400e; }
    .kpi-flag--info { background: var(--color-primary-soft); color: var(--color-primary); }
    .kpi-value-row { display: flex; align-items: baseline; gap: 6px; margin-top: 14px; }
    .kpi-value { font-size: 28px; font-weight: 800; letter-spacing: -.02em; color: var(--color-text-primary); }
    .kpi-value-suffix { font-size: 13px; font-weight: 700; color: var(--color-text-tertiary); }
    .kpi-label { font-size: 12px; font-weight: 600; color: var(--color-text-secondary); margin-top: 4px; }
    .kpi-footer { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--color-border); font-size: 11.5px; }
    .kpi-footer span { color: var(--color-text-tertiary); }
    .kpi-footer a { color: var(--color-primary); font-weight: 700; text-decoration: none; }
    .kpi-footer a:hover { text-decoration: underline; }

    /* ===== Split layout ===== */
    .dash-split { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; align-items: start; }
    @media (max-width: 900px) { .dash-split { grid-template-columns: 1fr; } }

    .dash-panel { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: 20px; margin-bottom: 20px; }
    .dash-panel:last-child { margin-bottom: 0; }
    .dash-panel__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 16px; }
    .dash-panel__head h3 { margin: 0; font-size: 15px; font-weight: 800; color: var(--color-text-primary); }
    .dash-panel__head p { margin: 3px 0 0; font-size: 12px; color: var(--color-text-secondary); }

    .program-card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; }
    .program-card { display: flex; align-items: center; padding: 12px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface-secondary); }
    .program-card__name { font-size: 12.5px; font-weight: 700; color: var(--color-text-primary); line-height: 1.3; }

    .tool-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .tool-card { display: flex; flex-direction: column; gap: 6px; padding: 14px; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface-secondary); text-decoration: none; transition: border-color var(--transition-fast), background-color var(--transition-fast); }
    .tool-card:hover { border-color: var(--color-primary); background: var(--color-primary-soft); }
    .tool-card .material-symbols-outlined { color: var(--color-text-secondary); font-size: 22px; }
    .tool-card:hover .material-symbols-outlined { color: var(--color-primary); }
    .tool-card__title { font-size: 12.5px; font-weight: 700; color: var(--color-text-primary); }
    .tool-card__sub { font-size: 10.5px; color: var(--color-text-tertiary); }

    /* ===== Sections to Plot ===== */
    .gap-list { display: flex; flex-direction: column; gap: 8px; }
    .gap-row {
        display: flex; align-items: center; justify-content: space-between; gap: 14px;
        padding: 12px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-md);
        background: var(--color-surface-secondary);
    }
    .gap-row__where { min-width: 0; }
    .gap-row__combo { font-size: 12.5px; font-weight: 700; color: var(--color-text-primary); }
    .gap-row__combo .sep { margin: 0 4px; color: var(--color-text-tertiary); font-weight: 400; }
    .gap-row__subjects { margin-top: 2px; font-size: 11px; color: var(--color-text-tertiary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gap-row__actions { display: flex; align-items: center; gap: 10px; flex: none; }
    .gap-row__actions a { font-size: 12px; font-weight: 700; color: var(--color-primary); text-decoration: none; white-space: nowrap; }
    .gap-row__actions a:hover { text-decoration: underline; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Dashboard</h2>
        <p class="ac-page-header__sub">Department overview — scheduling progress, faculty, and pending approvals.</p>
    </div>
</div>

<?php if ($deptName === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>You're not assigned to a department yet. Contact an administrator.</p>
        </div>
    </div>
<?php elseif ($activeYear === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">event_busy</div>
            <p>No active academic year is set. An administrator needs to activate one under Admin &gt; Academic Years.</p>
        </div>
    </div>
<?php else: ?>

    <?php
        $curriculumPct = $stats['curriculum_slots'] > 0
            ? round($stats['curriculum_set'] / $stats['curriculum_slots'] * 100)
            : 0;
    ?>

    <section class="dash-hero">
        <div class="dash-hero__inner">
            <div class="dash-hero__main">
                <div class="dash-hero__badges">
                    <span class="dash-hero__badge"><span class="dot"></span> Term Active</span>
                    <span class="dash-hero__term"><?= esc($activeYear) ?></span>
                </div>
                <h1 class="dash-hero__title">Welcome back, <?= esc($user['name']) ?></h1>
                <p class="dash-hero__sub"><?= esc($deptName) ?> department overview — live tracking of class plotting and faculty teaching loads.</p>
            </div>

            <div class="dash-hero__actions">
                <a class="btn btn-hero-primary" href="<?= site_url('chairperson/dashboard') ?>">
                    <span class="material-symbols-outlined" style="font-size:18px;">calendar_month</span>
                    Go to Schedule Plotting
                </a>
                <a class="btn btn-hero-secondary" href="<?= site_url('chairperson/sections') ?>">
                    <span class="material-symbols-outlined" style="font-size:18px;">groups</span>
                    Manage Sections &amp; Roster
                </a>
            </div>
        </div>
    </section>

    <div class="dash-panel">
        <div class="dash-panel__head">
            <div>
                <h3>Sections to Plot</h3>
                <p>Your own programs' sections still missing subjects on the weekly schedule.</p>
            </div>
            <?php if ($plottingGaps !== []): ?>
                <span class="kpi-flag kpi-flag--warn"><?= count($plottingGaps) ?> <?= count($plottingGaps) === 1 ? 'section' : 'sections' ?></span>
            <?php else: ?>
                <span class="kpi-flag kpi-flag--ok">All Clear</span>
            <?php endif ?>
        </div>

        <?php if ($plottingGaps === []): ?>
            <div class="empty-state" style="padding:24px;">
                <p>Every section in your programs is fully plotted for this term.</p>
            </div>
        <?php else: ?>
            <div class="gap-list">
                <?php foreach ($plottingGaps as $gap): ?>
                    <div class="gap-row">
                        <div class="gap-row__where">
                            <div class="gap-row__combo">
                                <?= esc($gap['program']) ?><span class="sep">&middot;</span><?= esc($gap['year_level']) ?><span class="sep">&middot;</span>Section <?= esc($gap['section']) ?>
                            </div>
                            <div class="gap-row__subjects"><?= esc(implode(', ', $gap['missing_codes'])) ?></div>
                        </div>
                        <div class="gap-row__actions">
                            <span class="kpi-flag kpi-flag--warn"><?= (int) $gap['missing_count'] ?> to plot</span>
                            <a href="<?= esc($gap['url']) ?>">Plot now &rarr;</a>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-value-row"><span class="kpi-value"><?= esc($stats['plotted_classes']) ?></span></div>
            <div class="kpi-label">Classes plotted this term</div>
            <div class="kpi-footer">
                <span>Weekly plotting grid</span>
                <a href="<?= site_url('chairperson/dashboard') ?>">Plot now →</a>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-value-row"><span class="kpi-value"><?= esc($stats['faculty_count']) ?></span></div>
            <div class="kpi-label">Faculty in department</div>
            <div class="kpi-footer">
                <span>Active teaching staff</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__top">
                <?php if ($stats['pending_approvals'] > 0): ?>
                    <span class="kpi-flag kpi-flag--warn">Action Required</span>
                <?php else: ?>
                    <span class="kpi-flag kpi-flag--ok">All Clear</span>
                <?php endif ?>
            </div>
            <div class="kpi-value-row"><span class="kpi-value"><?= esc($stats['pending_approvals']) ?></span></div>
            <div class="kpi-label">Swap approvals pending</div>
            <div class="kpi-footer">
                <span>Reviewed on Schedule Plotting</span>
                <a href="<?= site_url('chairperson/dashboard') ?>">Review →</a>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__top">
                <span class="kpi-flag <?= $curriculumPct >= 100 ? 'kpi-flag--ok' : 'kpi-flag--info' ?>"><?= $curriculumPct ?>% Synced</span>
            </div>
            <div class="kpi-value-row"><span class="kpi-value"><?= esc($stats['curriculum_set']) ?></span><span class="kpi-value-suffix">/ <?= esc($stats['curriculum_slots']) ?></span></div>
            <div class="kpi-label">Curriculum terms published</div>
            <div class="kpi-footer">
                <span>Program &times; year level combos</span>
                <a href="<?= site_url('chairperson/curriculum') ?>">Curriculum →</a>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__top">
                <?php if ($stats['needs_plotting_count'] > 0): ?>
                    <span class="kpi-flag kpi-flag--warn">Action Required</span>
                <?php else: ?>
                    <span class="kpi-flag kpi-flag--ok">All Clear</span>
                <?php endif ?>
            </div>
            <div class="kpi-value-row"><span class="kpi-value"><?= esc($stats['needs_plotting_count']) ?></span></div>
            <div class="kpi-label">Classes needing your subjects plotted</div>
            <div class="kpi-footer">
                <span>Any department, live demand</span>
                <a href="<?= site_url('chairperson/offerings') ?>">Plot now →</a>
            </div>
        </div>
    </div>

    <div class="dash-split">
        <div>
            <div class="dash-panel">
                <div class="dash-panel__head">
                    <div>
                        <h3>Your Department</h3>
                        <p><?= esc($deptName) ?> &middot; Active term: <?= esc($activeYear) ?></p>
                    </div>
                </div>

                <?php if ($programs === []): ?>
                    <div class="empty-state" style="padding:24px;">
                        <p>No programs registered under this department yet.</p>
                    </div>
                <?php else: ?>
                    <div class="program-card-grid">
                        <?php foreach ($programs as $program): ?>
                            <div class="program-card">
                                <div class="program-card__name"><?= esc($program) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="dash-panel">
                <div class="dash-panel__head">
                    <div><h3>Quick Navigation</h3></div>
                </div>
                <div class="tool-grid">
                    <a class="tool-card" href="<?= site_url('chairperson/dashboard') ?>">
                        <span class="material-symbols-outlined">calendar_month</span>
                        <span class="tool-card__title">Schedule Plotter</span>
                        <span class="tool-card__sub">Weekly visual grid</span>
                    </a>
                    <a class="tool-card" href="<?= site_url('chairperson/sections') ?>">
                        <span class="material-symbols-outlined">groups</span>
                        <span class="tool-card__title">Sections &amp; Roster</span>
                        <span class="tool-card__sub">Student cohort list</span>
                    </a>
                    <a class="tool-card" href="<?= site_url('chairperson/offerings') ?>">
                        <span class="material-symbols-outlined">merge_type</span>
                        <span class="tool-card__title">Merged Classes</span>
                        <span class="tool-card__sub">Cross-section pool</span>
                    </a>
                    <a class="tool-card" href="<?= site_url('chairperson/curriculum') ?>">
                        <span class="material-symbols-outlined">menu_book</span>
                        <span class="tool-card__title">Curriculum</span>
                        <span class="tool-card__sub">Subject catalog</span>
                    </a>
                    <a class="tool-card" href="<?= site_url('chairperson/grade-approvals') ?>">
                        <span class="material-symbols-outlined">grade</span>
                        <span class="tool-card__title">Grade Approvals</span>
                        <span class="tool-card__sub">Lock &amp; review</span>
                    </a>
                    <a class="tool-card" href="<?= site_url('chairperson/profile') ?>">
                        <span class="material-symbols-outlined">person</span>
                        <span class="tool-card__title">Profile</span>
                        <span class="tool-card__sub">Account settings</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?= $this->endSection() ?>

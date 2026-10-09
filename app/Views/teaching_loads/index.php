<?= $this->extend('layouts/admin_console') ?>

<?php
/**
 * Teaching Loads — Faculty view (Akademus SIS)
 * Loads are generated live from the chairpersons' plotted schedules and kept
 * current via long-polling. Assignment is chairperson-directed while
 * plotting — faculty view their official assignments here, read-only.
 */
$initials      = strtoupper(mb_substr($user['name'], 0, 2));
$activeAdmin   = 'dashboard';
$pageTitle     = 'Teaching Loads';
$brandSub      = 'Faculty Portal';
$userName      = $user['name'];
$userRoleLabel = 'Faculty';
$navGroups     = [
    '' => [
        'dashboard'    => ['label' => 'Dashboard',    'icon' => 'dashboard',        'url' => 'faculty/dashboard'],
        'profile'      => ['label' => 'Profile',      'icon' => 'person',           'url' => 'faculty/profile'],
        'schedule'     => ['label' => 'Schedule',     'icon' => 'calendar_month',   'url' => 'faculty/schedule'],
        'availability' => ['label' => 'Availability', 'icon' => 'event_available',  'url' => 'faculty/availability'],
        'grading'      => ['label' => 'Grading',      'icon' => 'grade',            'url' => 'faculty/grading'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<style>
:root {
    /* Aliased onto the shared design-system tokens (public/assets/css/design-system/tokens.css). */
    --brand: var(--color-primary);
    --brand-soft: var(--color-primary-soft);
    --bg: var(--color-background);
    --border: var(--color-border);
    --ink: var(--color-text-primary);
    --muted: var(--color-text-secondary);
    --green: var(--color-success); --green-soft: var(--color-success-soft); --green-border: #bbf7d0;
    --red: var(--color-danger); --red-soft: var(--color-danger-soft); --red-border: var(--color-danger-border);
    --amber: var(--color-warning);
    --shadow-apple: var(--shadow-sm);
    --shadow-hover: var(--shadow-md);
}

.icon-btn { color: var(--muted); padding: 8px; border-radius: 50%; display: grid; place-items: center; transition: color .15s, background .15s; }
.icon-btn:hover { color: var(--ink); background: var(--color-surface-sunken); }

/* ===== Page head ===== */
.page-head { margin-bottom: 32px; display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; }
.page-head__title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.page-head h1 { font-size: 28px; font-weight: 700; letter-spacing: -.02em; margin-bottom: 8px; color: var(--ink); }
.page-head p { color: var(--muted); font-size: 13.5px; max-width: 640px; line-height: 1.6; }
.live-sync-badge {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 11px; font-weight: 700; padding: 4px 11px; border-radius: 999px;
    background: var(--green-soft); color: #166534; border: 1px solid var(--green-border);
}
.live-sync-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green); }

/* ===== Stats card ===== */
.stats-card {
    background: var(--color-surface); border-radius: var(--radius-xl); padding: 28px;
    box-shadow: var(--shadow-apple); border: 1px solid var(--border);
    margin-bottom: 24px;
}
.spec-row { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
.spec-row .lbl { font-size: 13px; font-weight: 600; white-space: nowrap; color: var(--ink); }
.spec-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.spec-chip { background: var(--brand-soft); color: var(--brand); padding: 6px 14px; border-radius: 999px; font-size: 11.5px; font-weight: 700; letter-spacing: .02em; border: 1px solid var(--color-info-soft); }
.load-meter { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
.load-meter-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
.load-meter-head .lbl { font-size: 13px; font-weight: 600; color: var(--ink); }
.load-meter-head .lbl b { font-size: 22px; font-weight: 800; color: var(--brand); margin: 0 2px; }
.load-meter-head .rem { font-size: 11.5px; color: var(--muted); font-weight: 500; }
.track { height: 10px; width: 100%; background: var(--color-surface-sunken); border-radius: 999px; overflow: hidden; padding: 2px; box-sizing: border-box; }
.fill { height: 100%; width: 0; background: linear-gradient(90deg, var(--brand), #3b82f6); border-radius: 999px; transition: width .5s ease-in-out, background .3s; }
.fill.warn { background: var(--amber); }
.fill.full { background: var(--red); }

.workload-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
.workload-stat {
    background: var(--color-surface-secondary); border: 1px solid var(--border); border-radius: var(--radius-md);
    padding: 12px 14px; display: flex; align-items: center; gap: 10px;
}
.workload-stat__icon {
    width: 32px; height: 32px; border-radius: var(--radius-sm); flex: none;
    display: flex; align-items: center; justify-content: center;
    background: var(--brand-soft); color: var(--brand);
}
.workload-stat__icon .material-symbols-outlined { font-size: 18px; }
.workload-stat__label { font-size: 11px; color: var(--muted); }
.workload-stat__value { font-size: 15px; font-weight: 800; color: var(--ink); }

/* ===== Weekly timetable preview ===== */
.timetable-panel {
    background: var(--color-surface); border-radius: var(--radius-xl); padding: 24px;
    box-shadow: var(--shadow-apple); border: 1px solid var(--border);
    margin-bottom: 24px;
}
.timetable-panel__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
.timetable-panel__head h2 { font-size: 15px; font-weight: 700; color: var(--ink); margin-bottom: 2px; }
.timetable-panel__head p { font-size: 12px; color: var(--muted); }
.timetable-panel__link {
    font-size: 12px; font-weight: 600; color: var(--brand); text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;
}
.timetable-panel__link:hover { text-decoration: underline; }
.tt-grid-wrap { overflow-x: auto; }
.tt-grid { display: grid; grid-template-columns: 56px repeat(7, minmax(96px, 1fr)); min-width: 720px; }
.tt-corner { grid-row: 1; grid-column: 1; }
.tt-day-head {
    grid-row: 1; text-align: center; font-size: 11px; font-weight: 700; color: var(--muted);
    padding: 0 0 10px; border-bottom: 1px solid var(--border); text-transform: uppercase; letter-spacing: .04em;
}
.tt-time { font-size: 10.5px; color: var(--muted); text-align: right; padding-right: 8px; transform: translateY(-6px); }
.tt-time-col { grid-row: 2; grid-column: 1; display: grid; }
.tt-day-col {
    grid-row: 2; position: relative; border-left: 1px solid var(--border);
    background-image: repeating-linear-gradient(to bottom, var(--color-surface-sunken) 0, var(--color-surface-sunken) 1px, transparent 1px, transparent 60px);
}
.tt-block {
    position: absolute; left: 3px; right: 3px; border-radius: var(--radius-sm);
    border-left: 3px solid; padding: 5px 7px; overflow: hidden;
    font-size: 10.5px; line-height: 1.3; box-shadow: var(--shadow-xs);
}
.tt-block__code { font-weight: 700; }
.tt-block__meta { opacity: .85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tt-block.acc-blue   { background: var(--color-info-soft);    border-left-color: #2563eb; color: #1e3a8a; }
.tt-block.acc-purple { background: #f1e6ff;                   border-left-color: #9333ea; color: #4c1d95; }
.tt-block.acc-red    { background: var(--red-soft);           border-left-color: #ef4444; color: #7f1d1d; }
.tt-block.acc-gold   { background: var(--color-warning-soft); border-left-color: #f59e0b; color: #78350f; }
.tt-block.acc-green  { background: var(--green-soft);         border-left-color: #16a34a; color: #14532d; }
.tt-block.acc-orange { background: #fff7ed;                   border-left-color: #f97316; color: #7c2d12; }
.timetable-panel__empty { padding: 32px 8px; text-align: center; font-size: 12.5px; color: var(--muted); }

/* ===== Panel: tabs + board ===== */
.panel {
    background: var(--color-surface); border-radius: var(--radius-xl); overflow: hidden;
    box-shadow: var(--shadow-apple); border: 1px solid var(--border);
    display: flex; flex-direction: column; height: 820px;
    margin-bottom: 24px;
}
.tab-row { position: relative; display: flex; gap: 8px; padding: 20px 24px 0; flex-wrap: wrap; background: var(--color-surface); }
/* top/height are now set directly by acInitSegmented (measured from the
   real button box), not hand-tuned insets here — those drifted out of
   sync with .panel's own rounded-corner clipping and cut the pill off. */
.tab-row .ac-segmented__indicator { background: var(--brand); border-radius: 999px; box-shadow: var(--shadow-xs); }
.tab {
    position: relative; z-index: 1; display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 18px; border-radius: 999px; font-size: 13px; font-weight: 500; color: var(--muted);
    transition: color .15s;
    /* Buttons carry a native, opaque background/border in every browser
       by default — never overridden here before because the old
       .tab.active rule set its own explicit background, which happened to
       paint over it. Once that moved to the sliding indicator (which sits
       BEHIND the button at z-index:0), the native chrome was left
       covering it almost entirely. */
    appearance: none; -webkit-appearance: none;
    border: none; background: none;
    font-family: inherit; cursor: pointer;
}
.tab:hover:not(:disabled):not(.active) { color: var(--ink); background: var(--color-surface-sunken); }
.tab.active { color: #fff; }
.tab:disabled { color: var(--color-text-tertiary); cursor: not-allowed; }
.tab-cnt { background: var(--color-surface-sunken); color: var(--muted); font-size: 11px; border-radius: 999px; padding: 2px 8px; font-weight: 600; }
.tab.active .tab-cnt { background: rgba(255,255,255,.2); color: #fff; }
.tab:disabled .tab-cnt { color: var(--color-text-tertiary); }

.filter-bar { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; padding: 16px 24px; border-bottom: 1px solid var(--border); background: var(--color-surface-secondary); }
.filter-bar .lbl { font-size: 13px; font-weight: 500; color: var(--muted); }
.chip-row { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; scrollbar-width: none; }
.chip-row::-webkit-scrollbar { display: none; }
.chip { background: var(--color-surface); color: var(--ink); border: 1px solid var(--border); box-shadow: var(--shadow-xs); padding: 6px 16px; border-radius: 999px; font-size: 12px; font-weight: 500; white-space: nowrap; transition: background .15s, color .15s; }
.chip:hover:not(.active) { background: var(--color-surface-sunken); }
.chip.active { background: var(--ink); border-color: var(--ink); color: #fff; }
.filter-bar .summary { margin-left: auto; font-size: 12px; font-weight: 500; color: var(--muted); }
.filter-select { background: var(--color-surface); color: var(--ink); border: 1px solid var(--border); box-shadow: var(--shadow-xs); padding: 6px 12px; border-radius: 999px; font-family: inherit; font-size: 12px; font-weight: 500; cursor: pointer; }
.filter-select:focus { outline: none; box-shadow: var(--focus-ring); }

.board-scroll { flex: 1; overflow-y: auto; background: var(--color-surface); }
.tile-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 18px; padding: 24px; }

/* ===== Load tiles ===== */
.tile { background: var(--color-surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px; display: flex; flex-direction: column; gap: 12px; box-shadow: var(--shadow-xs); transition: box-shadow .15s, transform .15s; }
.tile:hover { box-shadow: var(--shadow-hover); transform: translateY(-2px); }
.tile-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
.tile-code { font-size: 16px; font-weight: 700; letter-spacing: -.01em; color: var(--ink); }
.tile-chip { padding: 4px 10px; border-radius: 999px; font-size: 10.5px; font-weight: 600; white-space: nowrap; flex: none; }
.tile.acc-blue .tile-code { color: #2563eb; }   .tile.acc-blue .tile-chip { background: var(--color-info-soft); color: var(--color-info); }
.tile.acc-purple .tile-code { color: #9333ea; } .tile.acc-purple .tile-chip { background: #faf5ff; color: #7e22ce; }
.tile.acc-red .tile-code { color: #ef4444; }    .tile.acc-red .tile-chip { background: var(--red-soft); color: #b91c1c; }
.tile.acc-gold .tile-code { color: #f59e0b; }   .tile.acc-gold .tile-chip { background: var(--color-warning-soft); color: #b45309; }
.tile.acc-green .tile-code { color: var(--green); } .tile.acc-green .tile-chip { background: var(--green-soft); color: #15803d; }
.tile.acc-orange .tile-code { color: #f97316; } .tile.acc-orange .tile-chip { background: #fff7ed; color: #c2410c; }

.tile-title { font-size: 13px; font-weight: 600; line-height: 1.4; min-height: 2.6em; color: var(--ink); }
.tile-meta { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--muted); }
.tile-meta .material-symbols-outlined { font-size: 16px; color: var(--color-text-tertiary); }

.tile-btn { margin-top: 8px; border-radius: 999px; padding: 10px; font-size: 12.5px; font-weight: 500; text-align: center; display: flex; align-items: center; justify-content: center; gap: 6px; transition: background .15s, transform .1s; }
.tile-btn.claim { background: var(--brand); color: #fff; box-shadow: var(--shadow-xs); }
.tile-btn.claim:hover { background: var(--color-primary-hover); }
.tile-btn.claim:active { transform: scale(.96); }
.tile-btn.conflict { background: var(--red-soft); color: var(--red); border: 1px solid var(--red-border); cursor: not-allowed; }
.tile-btn.assigned { background: var(--green-soft); color: var(--green); border: 1px solid var(--green-border); cursor: default; }
.tile-btn.swap { background: var(--color-surface-sunken); color: var(--ink); }
.tile-btn.swap:hover { background: var(--color-border); }
.tile-btn.swap:disabled { color: var(--muted); cursor: not-allowed; }
.tile-btn.decline { background: var(--red-soft); color: var(--red); border: 1px solid var(--red-border); }
.tile-btn.decline:hover { background: #fee2e2; }
.tile-btn .material-symbols-outlined { font-size: 17px; }

.swap-status { padding: 4px 12px; border-radius: 999px; font-size: 10.5px; font-weight: 600; white-space: nowrap; flex: none; }
.swap-status.pending { background: var(--color-warning-soft); color: #b45309; }
.swap-status.accepted { background: var(--green-soft); color: var(--green); }
.swap-status.declined { background: var(--red-soft); color: var(--red); }
.swap-status.cancelled { background: var(--color-surface-sunken); color: var(--muted); }
.swap-who { font-size: 12px; color: var(--ink); font-weight: 600; }
.swap-status.awaiting { background: var(--color-info-soft); color: var(--color-info); }
.swap-status.approved { background: var(--green-soft); color: var(--green); }
.swap-status.rejected { background: var(--red-soft); color: var(--red); }

.tile.is-taken { opacity: .85; background: var(--color-surface-secondary); }
.taken-chip { padding: 4px 10px; border-radius: 999px; font-size: 10.5px; font-weight: 600; white-space: nowrap; flex: none; background: var(--color-surface-sunken); color: var(--muted); }

/* ===== Empty state ===== */
.empty-state { height: 100%; display: none; flex-direction: column; align-items: center; justify-content: center; padding: 32px; text-align: center; }
.empty-state.visible { display: flex; }
.empty-icon { width: 72px; height: 72px; border-radius: 50%; background: var(--color-surface-sunken); display: grid; place-items: center; color: var(--muted); margin-bottom: 20px; }
.empty-icon .material-symbols-outlined { font-size: 34px; }
.empty-state h3 { font-size: 17px; font-weight: 600; letter-spacing: -.01em; margin-bottom: 10px; color: var(--ink); }
.empty-state p { font-size: 13px; color: var(--muted); max-width: 380px; line-height: 1.6; }

/* ===== Confirm / swap / change-request modals ===== */
.modal-backdrop {
    position: fixed; inset: 0; background: rgba(15, 23, 42, .5);
    display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 70;
    opacity: 0; visibility: hidden; pointer-events: none;
    transition: opacity var(--motion-normal) var(--ease-standard), visibility 0s linear var(--motion-normal);
}
.modal-backdrop.open {
    opacity: 1; visibility: visible; pointer-events: auto;
    transition: opacity var(--motion-normal) var(--ease-standard), visibility 0s linear 0s;
}
.modal-card {
    background: var(--color-surface); border-radius: var(--radius-xl); border: 1px solid var(--border); box-shadow: var(--shadow-hover); width: 100%; max-width: 440px; padding: 28px;
    transform: scale(.97) translateY(8px); opacity: 0;
    transition: transform var(--motion-normal) var(--ease-enter), opacity var(--motion-normal) var(--ease-enter);
}
.modal-backdrop.open .modal-card { transform: scale(1) translateY(0); opacity: 1; }
.modal-card h3 { font-size: 17px; font-weight: 600; letter-spacing: -.01em; margin-bottom: 10px; color: var(--ink); }
.modal-card p { font-size: 13px; color: var(--muted); line-height: 1.6; margin-bottom: 28px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; }
.modal-btn { padding: 10px 20px; border-radius: var(--radius-md); font-size: 13px; font-weight: 700; transition: background .15s; }
.modal-btn.cancel { background: var(--color-surface-sunken); color: var(--ink); }
.modal-btn.cancel:hover { background: var(--color-border); }
.modal-btn.ok { background: var(--brand); color: #fff; box-shadow: var(--shadow-xs); }
.modal-btn.ok:hover { background: var(--color-primary-hover); }

/* ===== Toast ===== */
.toast { position: fixed; bottom: 24px; left: 50%; transform: translate(-50%,64px); opacity: 0; background: var(--ink); color: #fff; font-size: 13px; font-weight: 500; border-radius: 999px; padding: 12px 24px; box-shadow: var(--shadow-hover); transition: transform .2s, opacity .2s; z-index: 9000; pointer-events: none; }
.toast.show { transform: translate(-50%,0); opacity: 1; }
.toast.success { background: var(--green); }
.toast.error { background: var(--red); }

@media (max-width: 1200px) { .tile-grid { grid-template-columns: repeat(3, minmax(0,1fr)); } }
@media (max-width: 900px) { .tile-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
@media (max-width: 560px) { .tile-grid { grid-template-columns: 1fr; } }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-head">
    <div>
        <div class="page-head__title-row">
            <h1>Teaching Loads</h1>
        </div>
        <p>Your chairperson assigns subjects while plotting the schedule. This board updates in real time.</p>
    </div>
    <span class="live-sync-badge"><span class="dot"></span> Live Sync Enabled</span>
</div>

<!-- Stats card -->
<div class="stats-card">
    <div class="spec-row">
        <span class="lbl">Your specializations:</span>
        <div class="spec-chips">
            <?php foreach ($specializations as $sp): ?>
            <span class="spec-chip"><?= esc($sp) ?></span>
            <?php endforeach ?>
        </div>
    </div>
    <div class="load-meter">
        <div class="load-meter-head">
            <span class="lbl">Teaching Load: <b id="loadUnits">0</b> / <?= esc($max_units) ?> units</span>
            <span class="rem" id="loadRemaining"><?= esc($max_units) ?> units remaining</span>
        </div>
        <div class="track"><div class="fill" id="loadFill"></div></div>
    </div>
    <div class="workload-stats">
        <div class="workload-stat">
            <div class="workload-stat__icon"><span class="material-symbols-outlined">menu_book</span></div>
            <div>
                <div class="workload-stat__label">Assigned courses</div>
                <div class="workload-stat__value" id="statAssignedCourses">0</div>
            </div>
        </div>
        <div class="workload-stat">
            <div class="workload-stat__icon"><span class="material-symbols-outlined">calendar_month</span></div>
            <div>
                <div class="workload-stat__label">Weekly meetings</div>
                <div class="workload-stat__value" id="statWeeklyMeetings">0</div>
            </div>
        </div>
        <div class="workload-stat">
            <div class="workload-stat__icon"><span class="material-symbols-outlined">swap_horiz</span></div>
            <div>
                <div class="workload-stat__label">Open swap requests</div>
                <div class="workload-stat__value" id="statOpenSwaps">0</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs + board -->
<div class="panel">
    <div class="tab-row">
        <button type="button" class="tab active" data-tab="available">
            Unassigned <span class="tab-cnt" id="cntAvailable">0</span>
        </button>
        <button type="button" class="tab" data-tab="mine">
            My Assignments <span class="tab-cnt" id="cntMine">0</span>
        </button>
        <button type="button" class="tab" data-tab="swaps">
            Swap Requests <span class="tab-cnt" id="cntSwaps">0</span>
        </button>
        <button type="button" class="tab" data-tab="changes">
            Change Requests <span class="tab-cnt" id="cntChanges">0</span>
        </button>
    </div>

    <div class="filter-bar">
        <span class="lbl">Days:</span>
        <div class="chip-row" id="dayFilter"></div>
        <select class="filter-select" id="yearFilter"><option value="">All year levels</option></select>
        <select class="filter-select" id="sectionFilter"><option value="">All sections</option></select>
        <span class="summary" id="boardSummary"></span>
    </div>

    <div class="board-scroll">
        <div class="tile-grid" id="boardGrid"></div>
        <div class="empty-state" id="boardEmpty">
            <div class="empty-icon"><span class="material-symbols-outlined">calendar_add_on</span></div>
            <h3 id="emptyTitle"></h3>
            <p id="emptySub"></p>
        </div>
    </div>
</div>

<!-- Weekly timetable preview -->
<div class="timetable-panel">
    <div class="timetable-panel__head">
        <div>
            <h2>Weekly Timetable</h2>
            <p>Your assigned meetings for the active term, plotted by day and time.</p>
        </div>
        <a class="timetable-panel__link" href="<?= site_url('faculty/schedule') ?>">
            Full schedule <span class="material-symbols-outlined" style="font-size:14px;">arrow_forward</span>
        </a>
    </div>
    <div class="tt-grid-wrap">
        <div class="tt-grid" id="ttGrid"></div>
    </div>
    <div class="timetable-panel__empty" id="ttEmpty" style="display:none;">No meetings plotted yet — assigned subjects will appear here once your chairperson finishes plotting the schedule.</div>
</div>

<!-- Swap request modal -->
<div class="modal-backdrop" id="swapModal">
    <div class="modal-card">
        <h3 id="swapTitle"></h3>
        <p id="swapMessage"></p>
        <select class="filter-select" id="swapTarget" style="width:100%;margin-bottom:28px;padding:10px 14px;border-radius:var(--radius-md)"></select>
        <div class="modal-actions">
            <button type="button" class="modal-btn cancel" id="swapCancel">Cancel</button>
            <button type="button" class="modal-btn ok" id="swapSend">Send Request</button>
        </div>
    </div>
</div>

<!-- Request for change modal -->
<div class="modal-backdrop" id="changeRequestModal">
    <div class="modal-card">
        <h3 id="crTitle"></h3>
        <p id="crMessage"></p>
        <textarea id="crReason" rows="3" placeholder="Why can't you accept this assignment? (availability conflict, schedule conflict, etc.)"
            style="width:100%;margin-bottom:12px;padding:10px 14px;border-radius:var(--radius-md);border:1px solid var(--color-border);font-family:inherit;font-size:13px;resize:vertical;"></textarea>
        <input type="text" id="crProposed" placeholder="Proposed alternative (optional)"
            style="width:100%;margin-bottom:28px;padding:10px 14px;border-radius:var(--radius-md);border:1px solid var(--color-border);font-family:inherit;font-size:13px;">
        <div class="modal-actions">
            <button type="button" class="modal-btn cancel" id="crCancel">Cancel</button>
            <button type="button" class="modal-btn ok" id="crSend">Submit Request</button>
        </div>
    </div>
</div>

<!-- Claim confirmation -->
<div class="modal-backdrop" id="confirmModal">
    <div class="modal-card">
        <h3 id="confirmTitle"></h3>
        <p id="confirmMessage"></p>
        <div class="modal-actions">
            <button type="button" class="modal-btn cancel" id="confirmCancel">Cancel</button>
            <button type="button" class="modal-btn ok" id="confirmOk">Claim Subject</button>
        </div>
    </div>
</div>

<!-- No department assigned -->
<div class="modal-backdrop" id="noDeptModal">
    <div class="modal-card">
        <h3>No Department Assigned</h3>
        <p>Your account isn't linked to a department yet, so there are no teaching loads to show. Please contact your administrator to have a department assigned to your account.</p>
        <div class="modal-actions">
            <button type="button" class="modal-btn ok" id="noDeptOk">Got it</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const CONFIG = {
    stateUrl: <?= json_encode(site_url('faculty/loads/state')) ?>,
    swapRequestUrl: <?= json_encode(site_url('faculty/loads/swap/request')) ?>,
    swapRespondUrl: <?= json_encode(site_url('faculty/loads/swap/respond')) ?>,
    swapCancelUrl: <?= json_encode(site_url('faculty/loads/swap/cancel')) ?>,
    changeRequestStoreUrl: <?= json_encode(site_url('faculty/change-requests/store')) ?>,
    changeRequestCancelUrl: <?= json_encode(site_url('faculty/change-requests/cancel')) ?>,
    maxUnits: <?= (int) $max_units ?>,
    noDepartment: <?= json_encode($no_department) ?>,
};

const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

let LOADS = <?= json_encode($state['loads'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let TAKEN = <?= json_encode($state['taken'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let MINE  = <?= json_encode($state['mine'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let UNITS = <?= json_encode($state['units']) ?>;
let SWAPS = <?= json_encode($state['swaps'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let FACULTY = <?= json_encode($state['faculty'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let CHANGE_REQUESTS = <?= json_encode($state['change_requests'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

let activeTab = 'available';
let dayFilter = null;   // null = all days
let yearFilter = '';    // '' = all year levels
let sectionFilter = ''; // '' = all sections
let query = '';
let version = '';

// ---------- toast ----------
const toast = document.getElementById('toast');
let toastTimer;
function showToast(msg, kind) {
    toast.textContent = msg;
    toast.className = 'toast show' + (kind ? ' ' + kind : '');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3200);
}

// ---------- confirm dialog ----------
const confirmModal = document.getElementById('confirmModal');
let confirmResolve = null;

function confirmDialog(title, message, okLabel) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmOk').textContent = okLabel;
    confirmModal.classList.add('open');
    return new Promise(resolve => { confirmResolve = resolve; });
}

function settleConfirm(result) {
    if (confirmResolve === null) return;
    confirmModal.classList.remove('open');
    const resolve = confirmResolve;
    confirmResolve = null;
    resolve(result);
}

document.getElementById('confirmOk').addEventListener('click', () => settleConfirm(true));
document.getElementById('confirmCancel').addEventListener('click', () => settleConfirm(false));
confirmModal.addEventListener('click', e => { if (e.target === confirmModal) settleConfirm(false); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') settleConfirm(false); });

// ---------- rendering ----------
function metaRow(icon, text) {
    const row = document.createElement('div');
    row.className = 'tile-meta';
    const iconEl = document.createElement('span');
    iconEl.className = 'material-symbols-outlined';
    iconEl.textContent = icon;
    row.append(iconEl, document.createTextNode(text));
    return row;
}

function loadTile(l) {
    const tile = document.createElement('div');
    tile.className = 'tile acc-' + l.accent;

    const head = document.createElement('div');
    head.className = 'tile-head';
    const code = document.createElement('span');
    code.className = 'tile-code';
    code.textContent = l.code;
    const spec = document.createElement('span');
    spec.className = 'tile-chip';
    spec.textContent = l.spec;
    head.append(code, spec);

    const title = document.createElement('div');
    title.className = 'tile-title';
    title.textContent = l.title;

    tile.append(head, title,
        metaRow('calendar_month', l.days),
        metaRow('schedule', l.time),
        metaRow('location_on', `${l.room} · ${l.units} units`));

    const btn = document.createElement('button');
    btn.type = 'button';

    if (l.state === 'assigned') {
        btn.className = 'tile-btn assigned';
        btn.innerHTML = '<span class="material-symbols-outlined">check_circle</span> Assigned to you';
        btn.disabled = true;

        const swapBtn = document.createElement('button');
        swapBtn.type = 'button';
        if (l.swap_pending) {
            swapBtn.className = 'tile-btn swap';
            swapBtn.innerHTML = '<span class="material-symbols-outlined">hourglass_top</span> Swap pending';
            swapBtn.disabled = true;
        } else {
            swapBtn.className = 'tile-btn swap';
            swapBtn.innerHTML = '<span class="material-symbols-outlined">swap_horiz</span> Request Swap';
            swapBtn.addEventListener('click', () => openSwapModal(l));
        }

        const changeBtn = document.createElement('button');
        changeBtn.type = 'button';
        if (l.change_request_pending) {
            changeBtn.className = 'tile-btn swap';
            changeBtn.innerHTML = '<span class="material-symbols-outlined">hourglass_top</span> Change Requested';
            changeBtn.disabled = true;
        } else {
            changeBtn.className = 'tile-btn decline';
            changeBtn.innerHTML = '<span class="material-symbols-outlined">flag</span> Request Change';
            changeBtn.addEventListener('click', () => openChangeRequestModal(l));
        }

        tile.appendChild(btn);
        tile.appendChild(swapBtn);
        tile.appendChild(changeBtn);
        return tile;
    } else if (l.state === 'taken') {
        tile.classList.add('is-taken');
        // Replace the section chip with a "taken by" chip.
        spec.className = 'taken-chip';
        spec.textContent = 'Taken · ' + l.holder;

        if (l.swap_pending) {
            btn.className = 'tile-btn swap';
            btn.innerHTML = '<span class="material-symbols-outlined">hourglass_top</span> Swap in progress';
            btn.disabled = true;
        } else {
            btn.className = 'tile-btn swap';
            btn.innerHTML = '<span class="material-symbols-outlined">swap_horiz</span> Propose Swap';
            btn.addEventListener('click', () => openProposeModal(l));
        }
    } else if (l.state === 'conflict') {
        btn.className = 'tile-btn conflict';
        btn.textContent = 'Schedule Conflict';
        btn.disabled = true;
        btn.title = 'This load overlaps one of your assigned classes.';
    } else {
        btn.className = 'tile-btn swap';
        btn.innerHTML = '<span class="material-symbols-outlined">hourglass_empty</span> Awaiting Assignment';
        btn.disabled = true;
        btn.title = 'Your chairperson has not assigned this subject to anyone yet.';
    }

    tile.appendChild(btn);
    return tile;
}

const SWAP_STATUS = {
    pending:   { label: 'Pending',            cls: 'pending' },
    accepted:  { label: 'Awaiting approval',  cls: 'awaiting' },
    approved:  { label: 'Approved',           cls: 'approved' },
    declined:  { label: 'Declined',           cls: 'declined' },
    rejected:  { label: 'Rejected by chair',  cls: 'rejected' },
    cancelled: { label: 'Cancelled',          cls: 'cancelled' },
};

function swapTile(s) {
    const tile = document.createElement('div');
    tile.className = 'tile acc-' + (s.offered ? s.offered.accent : 'blue');

    const statusInfo = SWAP_STATUS[s.status] || SWAP_STATUS.cancelled;

    const head = document.createElement('div');
    head.className = 'tile-head';
    const code = document.createElement('span');
    code.className = 'tile-code';
    code.textContent = s.requested ? `${s.offered.code} ⇄ ${s.requested.code}` : s.offered.code;
    const status = document.createElement('span');
    status.className = 'swap-status ' + statusInfo.cls;
    status.textContent = statusInfo.label;
    head.append(code, status);

    const who = document.createElement('div');
    who.className = 'swap-who';
    if (s.direction === 'incoming') {
        who.textContent = s.requested
            ? `${s.requester} offers ${s.offered.code} for your ${s.requested.code}`
            : `${s.requester} wants to hand ${s.offered.code} to you`;
    } else {
        who.textContent = s.requested
            ? `You offered ${s.offered.code} for ${s.target}'s ${s.requested.code}`
            : `You offered ${s.offered.code} to ${s.target}`;
    }

    tile.append(head, who,
        metaRow('calendar_month', `${s.offered.code}: ${s.offered.days} · ${s.offered.time} · ${s.offered.room} · ${s.offered.spec}`));

    if (s.requested) {
        tile.append(metaRow('swap_horiz', `${s.requested.code}: ${s.requested.days} · ${s.requested.time} · ${s.requested.room} · ${s.requested.spec}`));
    }

    if (s.status === 'accepted') {
        tile.append(metaRow('schedule', 'Waiting for the chairperson to approve the transfer.'));
    }

    if (s.direction === 'incoming' && s.status === 'pending') {
        const accept = document.createElement('button');
        accept.type = 'button';
        accept.className = 'tile-btn claim';
        accept.textContent = 'Accept Swap';
        accept.addEventListener('click', () => respondSwap(s, 'accept'));

        const decline = document.createElement('button');
        decline.type = 'button';
        decline.className = 'tile-btn decline';
        decline.textContent = 'Decline';
        decline.addEventListener('click', () => respondSwap(s, 'decline'));

        tile.append(accept, decline);
    } else if (s.direction === 'outgoing' && (s.status === 'pending' || s.status === 'accepted')) {
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'tile-btn swap';
        cancel.textContent = 'Withdraw Request';
        cancel.addEventListener('click', () => cancelSwap(s));
        tile.appendChild(cancel);
    }

    return tile;
}

const CHANGE_STATUS = {
    pending:      { label: 'Pending',            cls: 'pending' },
    under_review: { label: 'Under Review',       cls: 'awaiting' },
    approved:     { label: 'Approved',           cls: 'approved' },
    rejected:     { label: 'Rejected',           cls: 'rejected' },
    cancelled:    { label: 'Cancelled',          cls: 'cancelled' },
};

function changeRequestTile(cr) {
    const tile = document.createElement('div');
    tile.className = 'tile';

    const statusInfo = CHANGE_STATUS[cr.status] || CHANGE_STATUS.cancelled;

    const head = document.createElement('div');
    head.className = 'tile-head';
    const code = document.createElement('span');
    code.className = 'tile-code';
    code.textContent = cr.subject;
    const status = document.createElement('span');
    status.className = 'swap-status ' + statusInfo.cls;
    status.textContent = statusInfo.label;
    head.append(code, status);

    const reason = document.createElement('div');
    reason.className = 'swap-who';
    reason.textContent = cr.reason;

    tile.append(head, reason);

    if (cr.proposed) {
        tile.append(metaRow('lightbulb', 'Proposed: ' + cr.proposed));
    }

    if (cr.status === 'pending' || cr.status === 'under_review') {
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'tile-btn swap';
        cancel.textContent = 'Withdraw Request';
        cancel.addEventListener('click', () => cancelChangeRequest(cr));
        tile.appendChild(cancel);
    }

    return tile;
}

function visibleLoads() {
    const source = activeTab === 'available' ? LOADS.concat(TAKEN) : MINE;
    const q = query.trim().toLowerCase();

    return source.filter(l => {
        if (dayFilter !== null && !l.day_list.includes(dayFilter)) return false;
        if (yearFilter !== '' && l.context.year_level !== yearFilter) return false;
        if (sectionFilter !== '' && l.context.section !== sectionFilter) return false;
        if (q === '') return true;
        return `${l.code} ${l.title} ${l.room} ${l.spec}`.toLowerCase().includes(q);
    });
}

function renderBoard() {
    const grid = document.getElementById('boardGrid');
    const empty = document.getElementById('boardEmpty');
    grid.innerHTML = '';

    document.getElementById('cntAvailable').textContent = LOADS.length;
    document.getElementById('cntMine').textContent = MINE.length;
    document.getElementById('cntSwaps').textContent =
        SWAPS.filter(s => s.direction === 'incoming' && s.status === 'pending').length;
    document.getElementById('cntChanges').textContent =
        CHANGE_REQUESTS.filter(cr => cr.status === 'pending' || cr.status === 'under_review').length;

    // The day/section filters only make sense for load boards.
    document.querySelector('.filter-bar').style.display = (activeTab === 'swaps' || activeTab === 'changes') ? 'none' : '';

    if (activeTab === 'swaps') {
        if (SWAPS.length === 0) {
            empty.classList.add('visible');
            document.getElementById('emptyTitle').textContent = 'No swap requests';
            document.getElementById('emptySub').textContent =
                'Offer one of your assignments to a colleague from the My Assignments tab, or wait for an incoming request.';
        } else {
            empty.classList.remove('visible');
            SWAPS.forEach(s => grid.appendChild(swapTile(s)));
        }
        updateLoadBar();
        return;
    }

    if (activeTab === 'changes') {
        if (CHANGE_REQUESTS.length === 0) {
            empty.classList.add('visible');
            document.getElementById('emptyTitle').textContent = 'No change requests';
            document.getElementById('emptySub').textContent =
                'Request a change from the My Assignments tab if you cannot accept an assignment.';
        } else {
            empty.classList.remove('visible');
            CHANGE_REQUESTS.forEach(cr => grid.appendChild(changeRequestTile(cr)));
        }
        updateLoadBar();
        return;
    }

    const items = visibleLoads();

    const totalUnits = items.reduce((sum, l) => sum + l.units, 0);
    document.getElementById('boardSummary').textContent =
        items.length === 0 ? '' : `${items.length} ${items.length === 1 ? 'load' : 'loads'} · ${totalUnits} units`;

    if (items.length === 0) {
        empty.classList.add('visible');
        if (activeTab === 'available') {
            document.getElementById('emptyTitle').textContent =
                (query || dayFilter !== null) ? 'No loads match your filters' : 'No teaching loads available';
            document.getElementById('emptySub').textContent =
                (query || dayFilter !== null) ? 'Try clearing the search or day filter.'
                    : 'Loads will appear here in real time once a chairperson plots the schedules.';
        } else {
            document.getElementById('emptyTitle').textContent = 'No assignments yet';
            document.getElementById('emptySub').textContent = 'Your chairperson hasn\'t assigned you any subjects yet — check back once schedule plotting starts.';
        }
    } else {
        empty.classList.remove('visible');
        items.forEach(l => grid.appendChild(loadTile(l)));
    }

    updateLoadBar();
}

function updateLoadBar() {
    const pct = Math.min(100, Math.round(UNITS / CONFIG.maxUnits * 100));
    const fill = document.getElementById('loadFill');
    fill.style.width = pct + '%';
    fill.className = 'fill' + (pct >= 100 ? ' full' : pct >= 75 ? ' warn' : '');
    document.getElementById('loadUnits').textContent = UNITS;
    document.getElementById('loadRemaining').textContent = `${Math.max(0, CONFIG.maxUnits - UNITS)} units remaining`;

    document.getElementById('statAssignedCourses').textContent = MINE.length;
    document.getElementById('statWeeklyMeetings').textContent =
        MINE.reduce((sum, l) => sum + (Array.isArray(l.day_list) ? l.day_list.length : 0), 0);
    document.getElementById('statOpenSwaps').textContent =
        SWAPS.filter(s => s.status === 'pending' || s.status === 'accepted').length;

    renderTimetable();
}

// ---------- weekly timetable preview ----------
const TT_START_MIN = 7 * 60;
const TT_END_MIN = 19 * 60;
const TT_PX_PER_HOUR = 60;

function ttMinutes(hhmm) {
    const [h, m] = hhmm.split(':').map(Number);
    return h * 60 + m;
}

function renderTimetable() {
    const grid = document.getElementById('ttGrid');
    const emptyEl = document.getElementById('ttEmpty');
    grid.innerHTML = '';

    const totalHours = (TT_END_MIN - TT_START_MIN) / 60;

    const corner = document.createElement('div');
    corner.className = 'tt-corner';
    grid.appendChild(corner);

    DAY_LABELS.forEach((label, i) => {
        const head = document.createElement('div');
        head.className = 'tt-day-head';
        head.style.gridColumn = String(i + 2);
        head.textContent = label;
        grid.appendChild(head);
    });

    const timeCol = document.createElement('div');
    timeCol.className = 'tt-time-col';
    timeCol.style.gridTemplateRows = `repeat(${totalHours}, ${TT_PX_PER_HOUR}px)`;
    for (let h = 0; h < totalHours; h++) {
        const hour24 = TT_START_MIN / 60 + h;
        const h12 = hour24 % 12 === 0 ? 12 : hour24 % 12;
        const t = document.createElement('div');
        t.className = 'tt-time';
        t.textContent = h12 + (hour24 < 12 ? ' AM' : ' PM');
        timeCol.appendChild(t);
    }
    grid.appendChild(timeCol);

    let hasAny = false;
    for (let day = 0; day < DAY_LABELS.length; day++) {
        const col = document.createElement('div');
        col.className = 'tt-day-col';
        col.style.gridColumn = String(day + 2);
        col.style.height = (totalHours * TT_PX_PER_HOUR) + 'px';

        MINE.forEach(l => {
            (l.meetings || []).forEach(mt => {
                if (mt.day !== day) return;
                const startMin = ttMinutes(mt.start);
                const endMin = ttMinutes(mt.end);
                if (endMin <= TT_START_MIN || startMin >= TT_END_MIN) return;
                hasAny = true;

                const block = document.createElement('div');
                block.className = 'tt-block acc-' + (l.accent || 'blue');
                block.style.top = Math.max(0, startMin - TT_START_MIN) + 'px';
                block.style.height = Math.max(20, endMin - startMin) + 'px';

                const code = document.createElement('div');
                code.className = 'tt-block__code';
                code.textContent = l.code;
                const meta = document.createElement('div');
                meta.className = 'tt-block__meta';
                meta.textContent = l.room || '';
                block.append(code, meta);

                col.appendChild(block);
            });
        });

        grid.appendChild(col);
    }

    grid.style.display = hasAny ? '' : 'none';
    emptyEl.style.display = hasAny ? 'none' : '';
}

// ---------- tabs / filters ----------
const tabRowIndicator = window.acInitSegmented
    ? window.acInitSegmented(document.querySelector('.tab-row'), { buttonSelector: '.tab' })
    : null;

document.querySelectorAll('.tab[data-tab]').forEach(tab =>
    tab.addEventListener('click', () => {
        activeTab = tab.dataset.tab;
        document.querySelectorAll('.tab[data-tab]').forEach(t =>
            t.classList.toggle('active', t.dataset.tab === activeTab));
        if (tabRowIndicator) tabRowIndicator.move(tab, true);
        renderBoard();
    }));

const dayFilterBox = document.getElementById('dayFilter');
['All', ...DAY_LABELS].forEach((label, i) => {
    const chip = document.createElement('button');
    chip.type = 'button';
    chip.className = 'chip' + (i === 0 ? ' active' : '');
    chip.textContent = label;
    chip.addEventListener('click', () => {
        dayFilter = i === 0 ? null : i - 1;
        [...dayFilterBox.children].forEach((c, j) => c.classList.toggle('active', j === i));
        renderBoard();
    });
    dayFilterBox.appendChild(chip);
});

// Board search reuses the standard topbar search field instead of a
// page-specific one, so the topbar stays identical across every page.
const acSearchInput = document.getElementById('acSearchInput');
if (acSearchInput) {
    acSearchInput.placeholder = 'Search subject, room, section...';
    acSearchInput.addEventListener('input', e => {
        query = e.target.value;
        renderBoard();
    });
}

// Year level / section dropdowns: options follow whatever contexts exist in
// the live data, so new sections appear automatically.
function rebuildContextFilters() {
    const all = LOADS.concat(TAKEN, MINE);

    const fill = (id, values, current, allLabel) => {
        const select = document.getElementById(id);
        select.innerHTML = '';
        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = allLabel;
        select.appendChild(allOption);
        values.forEach(v => {
            const option = document.createElement('option');
            option.value = v;
            option.textContent = v;
            select.appendChild(option);
        });
        select.value = values.includes(current) ? current : '';
        return select.value;
    };

    yearFilter = fill('yearFilter',
        [...new Set(all.map(l => l.context.year_level))].sort(), yearFilter, 'All year levels');
    sectionFilter = fill('sectionFilter',
        [...new Set(all.map(l => l.context.section))].sort(), sectionFilter, 'All sections');
}

document.getElementById('yearFilter').addEventListener('change', e => {
    yearFilter = e.target.value;
    renderBoard();
});
document.getElementById('sectionFilter').addEventListener('change', e => {
    sectionFilter = e.target.value;
    renderBoard();
});

// ---------- swaps ----------
const swapModal = document.getElementById('swapModal');
let swapMode = null; // { type: 'handover', load } | { type: 'exchange', load }

function openSwapModal(l) {
    // Handover: give one of my loads to a chosen colleague.
    if (FACULTY.length === 0) {
        showToast('No other faculty members in your department yet.', 'error');
        return;
    }
    swapMode = { type: 'handover', load: l };
    document.getElementById('swapTitle').textContent = 'Hand over ' + l.code;
    document.getElementById('swapMessage').textContent =
        `Offer ${l.code} — ${l.title} (${l.days}, ${l.time} · ${l.room} · ${l.units} units) to a colleague. ` +
        `They must accept, and the chairperson must approve, before it transfers.`;

    const select = document.getElementById('swapTarget');
    select.innerHTML = '';
    FACULTY.forEach(f => {
        const option = document.createElement('option');
        option.value = f.id;
        option.textContent = f.username;
        select.appendChild(option);
    });

    swapModal.classList.add('open');
}

function openProposeModal(l) {
    // Exchange: offer one of MY loads for a colleague's taken load.
    if (MINE.length === 0) {
        showToast('You need an assigned load of your own to offer in exchange — claim one first.', 'error');
        return;
    }
    const offerable = MINE.filter(m => !m.swap_pending);
    if (offerable.length === 0) {
        showToast('All your loads are already part of open swap requests.', 'error');
        return;
    }

    swapMode = { type: 'exchange', load: l };
    document.getElementById('swapTitle').textContent = `Propose swap for ${l.code}`;
    document.getElementById('swapMessage').textContent =
        `${l.code} — ${l.title} (${l.days}, ${l.time} · ${l.room}) is held by ${l.holder}. ` +
        `Choose which of your loads to offer in exchange. ${l.holder} must accept, ` +
        `and the chairperson must approve, before the loads trade places.`;

    const select = document.getElementById('swapTarget');
    select.innerHTML = '';
    offerable.forEach(m => {
        const option = document.createElement('option');
        option.value = m.assignment_id;
        option.textContent = `${m.code} — ${m.title} (${m.days}, ${m.time} · ${m.units} units)`;
        select.appendChild(option);
    });

    swapModal.classList.add('open');
}

function closeSwapModal() {
    swapMode = null;
    swapModal.classList.remove('open');
}

document.getElementById('swapCancel').addEventListener('click', closeSwapModal);
swapModal.addEventListener('click', e => { if (e.target === swapModal) closeSwapModal(); });

document.getElementById('swapSend').addEventListener('click', async () => {
    if (!swapMode) return;
    const mode = swapMode;
    const selected = document.getElementById('swapTarget');
    const value = Number(selected.value);
    const valueName = selected.selectedOptions[0].textContent;
    closeSwapModal();

    const body = mode.type === 'handover'
        ? { offered_assignment_id: mode.load.assignment_id, target_id: value }
        : { offered_assignment_id: value, requested_assignment_id: mode.load.assignment_id };

    try {
        const response = await fetch(CONFIG.swapRequestUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        const result = await response.json();

        if (response.ok && result.ok) {
            showToast(mode.type === 'handover'
                ? `Swap request for ${mode.load.code} sent to ${valueName}.`
                : `Swap proposed to ${mode.load.holder}: your load for their ${mode.load.code}.`, 'success');
        } else {
            showToast(result.error || 'Could not send the swap request.', 'error');
        }
    } catch (err) {
        showToast('Network error while sending the swap request.', 'error');
    } finally {
        refreshState();
    }
});

async function respondSwap(s, action) {
    const gain = s.offered;
    const give = s.requested;

    const confirmed = await confirmDialog(
        (action === 'accept' ? 'Accept' : 'Decline') + ' swap: ' + gain.code + '?',
        action === 'accept'
            ? `You receive ${gain.code} (${gain.days}, ${gain.time} · ${gain.room})` +
              (give ? ` and give ${give.code} to ${s.requester}.` : ` from ${s.requester}.`) +
              ` The chairperson still has to approve before anything transfers.`
            : `Decline ${s.requester}'s swap request for ${gain.code}?`,
        action === 'accept' ? 'Accept Swap' : 'Decline'
    );
    if (!confirmed) return;

    try {
        const response = await fetch(CONFIG.swapRespondUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ swap_id: s.id, action: action }),
        });
        const result = await response.json();

        if (response.ok && result.ok) {
            showToast(action === 'accept'
                ? `Swap accepted — now waiting for the chairperson's approval.`
                : `Swap request for ${gain.code} declined.`, 'success');
        } else {
            showToast(result.error || 'Could not respond to the swap.', 'error');
        }
    } catch (err) {
        showToast('Network error while responding to the swap.', 'error');
    } finally {
        refreshState();
    }
}

async function cancelSwap(s) {
    const confirmed = await confirmDialog(
        'Withdraw swap request?',
        `Withdraw your offer of ${s.offered.code}${s.requested ? ' for ' + s.requested.code : ''}? Everything stays as it is.`,
        'Withdraw'
    );
    if (!confirmed) return;

    try {
        const response = await fetch(CONFIG.swapCancelUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ swap_id: s.id }),
        });
        const result = await response.json();
        if (response.ok && result.ok) {
            showToast(`Swap request for ${s.offered.code} withdrawn.`, '');
        } else {
            showToast(result.error || 'Could not cancel the swap.', 'error');
        }
    } catch (err) {
        showToast('Network error while cancelling the swap.', 'error');
    } finally {
        refreshState();
    }
}

// ---------- change requests ----------
const changeRequestModal = document.getElementById('changeRequestModal');
let pendingChangeLoad = null;

// The reason/proposed-alternative fields are free text entered in a modal
// with no <form> tag (it's sent via fetch(), not a submit) — the global
// unsaved-changes tracker only auto-watches <form> elements, so this modal
// opts in explicitly instead of duplicating its own dirty-tracking.
if (window.UnsavedChanges) window.UnsavedChanges.trackContainer(changeRequestModal);

function openChangeRequestModal(l) {
    pendingChangeLoad = l;
    document.getElementById('crTitle').textContent = 'Request Change: ' + l.code;
    document.getElementById('crMessage').textContent =
        `${l.code} — ${l.title} · ${l.spec} · ${l.days}, ${l.time} · ${l.room}. This goes to your chairperson for review — it does not reassign the subject on its own.`;
    document.getElementById('crReason').value = '';
    document.getElementById('crProposed').value = '';
    changeRequestModal.classList.add('open');
}

// The unconditional close — used once the user has actually sent the
// request, or confirmed they want to discard it. Never call this directly
// from a Cancel/backdrop-click path; use closeChangeRequestModal() so an
// in-progress reason doesn't get silently discarded.
function closeChangeRequestModalRaw() {
    pendingChangeLoad = null;
    changeRequestModal.classList.remove('open');
}

function closeChangeRequestModal() {
    if (window.UnsavedChanges) {
        window.UnsavedChanges.guard(closeChangeRequestModalRaw);
    } else {
        closeChangeRequestModalRaw();
    }
}

document.getElementById('crCancel').addEventListener('click', closeChangeRequestModal);
changeRequestModal.addEventListener('click', e => { if (e.target === changeRequestModal) closeChangeRequestModal(); });

document.getElementById('crSend').addEventListener('click', async () => {
    if (!pendingChangeLoad) return;
    const load = pendingChangeLoad;
    const reason = document.getElementById('crReason').value.trim();
    const proposed = document.getElementById('crProposed').value.trim();

    if (reason.length < 5) {
        showToast('Please explain why you cannot accept this assignment.', 'error');
        return;
    }

    if (window.UnsavedChanges) window.UnsavedChanges.markClean();
    closeChangeRequestModalRaw();

    try {
        const response = await fetch(CONFIG.changeRequestStoreUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ assignment_id: load.assignment_id, reason: reason, proposed_alternative: proposed }),
        });
        const result = await response.json();

        if (response.ok && result.ok) {
            showToast(`Change request for ${load.code} submitted to your chairperson.`, 'success');
        } else {
            showToast(result.error || 'Could not submit the change request.', 'error');
        }
    } catch (err) {
        showToast('Network error while submitting the change request.', 'error');
    } finally {
        refreshState();
    }
});

async function cancelChangeRequest(cr) {
    const confirmed = await confirmDialog(
        'Withdraw change request?',
        `Withdraw your change request for ${cr.subject}? The assignment stays as it is.`,
        'Withdraw'
    );
    if (!confirmed) return;

    try {
        const response = await fetch(CONFIG.changeRequestCancelUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ request_id: cr.id }),
        });
        const result = await response.json();
        if (response.ok && result.ok) {
            showToast(`Change request for ${cr.subject} withdrawn.`, '');
        } else {
            showToast(result.error || 'Could not withdraw the request.', 'error');
        }
    } catch (err) {
        showToast('Network error while withdrawing the request.', 'error');
    } finally {
        refreshState();
    }
}

// Notifications are handled by the shared topbar bell (see
// layouts/admin_console.php) — it polls faculty/notifications on its own,
// so this page only needs to toast in-board events below.

// ---------- real-time (long-polling) ----------
function applyState(data) {
    const before = new Set(LOADS.map(l => l.key));
    const mineKeys = new Set((data.mine || []).map(l => l.key));

    // Toast swap events: new incoming requests and every status change.
    if (Array.isArray(data.swaps)) {
        const prev = new Map(SWAPS.map(s => [s.id, s]));
        data.swaps.forEach(s => {
            const old = prev.get(s.id);
            const code = s.offered ? s.offered.code : '';
            if (!old && s.direction === 'incoming' && s.status === 'pending') {
                showToast(`${s.requester} proposed a swap for ${code} — check Swap Requests.`, 'success');
            } else if (old && s.status !== old.status) {
                const messages = {
                    accepted: s.direction === 'outgoing'
                        ? `${s.target} accepted your swap for ${code} — awaiting chairperson approval.` : null,
                    approved: `Swap for ${code} approved by the chairperson — loads transferred.`,
                    declined: s.direction === 'outgoing' ? `${s.target} declined your swap request for ${code}.` : null,
                    rejected: `The chairperson rejected the swap for ${code}.`,
                    cancelled: s.direction === 'incoming' ? `The swap request for ${code} was withdrawn.` : null,
                };
                if (messages[s.status]) {
                    showToast(messages[s.status], s.status === 'approved' || s.status === 'accepted' ? 'success' : '');
                }
            }
        });
        SWAPS = data.swaps;
    }
    if (Array.isArray(data.faculty)) FACULTY = data.faculty;

    // Toast change-request decisions.
    if (Array.isArray(data.change_requests)) {
        const prevCr = new Map(CHANGE_REQUESTS.map(cr => [cr.id, cr]));
        data.change_requests.forEach(cr => {
            const old = prevCr.get(cr.id);
            if (old && cr.status !== old.status && (cr.status === 'approved' || cr.status === 'rejected')) {
                showToast(`Your change request for ${cr.subject} was ${cr.status}.`, cr.status === 'approved' ? 'success' : '');
            }
        });
        CHANGE_REQUESTS = data.change_requests;
    }

    LOADS = data.loads || [];
    TAKEN = data.taken || [];
    MINE = data.mine || [];
    UNITS = data.units || 0;
    rebuildContextFilters();

    // Loads that vanished without becoming ours were just assigned to a colleague.
    const after = new Set(LOADS.map(l => l.key));
    before.forEach(key => {
        if (!after.has(key) && !mineKeys.has(key)) {
            const code = key.split('|').pop();
            showToast(`${code} was just assigned to another faculty member.`, '');
        }
    });

    renderBoard();
}

async function refreshState() {
    try {
        const response = await fetch(CONFIG.stateUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) return;
        const data = await response.json();
        version = data.version || '';
        applyState(data);
    } catch (err) { /* next poll will retry */ }
}

async function longPollLoop() {
    for (;;) {
        try {
            const params = new URLSearchParams();
            if (version) params.set('version', version);

            const response = await fetch(`${CONFIG.stateUrl}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error('poll failed');

            const data = await response.json();
            version = data.version || '';
            applyState(data);
        } catch (err) {
            // Offline or server hiccup: back off briefly, then resume.
            await new Promise(resolve => setTimeout(resolve, 3000));
        }
    }
}

rebuildContextFilters();
renderBoard();
longPollLoop();

if (CONFIG.noDepartment) {
    const noDeptModal = document.getElementById('noDeptModal');
    noDeptModal.classList.add('open');
    document.getElementById('noDeptOk').addEventListener('click', () => {
        noDeptModal.classList.remove('open');
    });
}
</script>
<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?php
/**
 * Availability & Subject Preferences — Faculty view (Akademus SIS)
 * Same visual shell as Teaching Loads (sidebar/topbar/cards) so the faculty
 * side reads as one consistent app, matching the chairperson side's design
 * language (schedule_plotting.php / admin console).
 *
 * Redesigned against a supplied mockup, but only the parts backed by real
 * data/endpoints were built. Left out on purpose because nothing in the
 * schema or controller backs them: a draft/autosave status badge, a
 * chairperson note banner with submission deadline, split AM/PM shift
 * windows per day (the schema stores one start/end per day), a
 * "Preferred"/"Conditional" day-status (only available/unavailable/no
 * preference exist), drag-to-reorder subject ranking (preference is one of
 * three fixed levels, no order column), and the "Teaching Constraints"
 * panel (max consecutive hours, campus/room requirement — no such fields
 * exist). The per-day notes field DOES exist in the schema and was already
 * being saved by the controller with no UI to enter it, so it's wired in
 * here for the first time.
 */
$activeAdmin   = 'availability';
$pageTitle     = 'Availability & Preferences';
$brandSub      = 'Faculty Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
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

// Sort subjects for display only (preferred > willing > qualified > not set)
// — a visual grouping, not a persisted rank; there's no order column.
$levelRank = ['preferred' => 0, 'willing' => 1, 'qualified' => 2];
$sortedSubjects = $subjects;
usort($sortedSubjects, function ($a, $b) use ($preferencesById, $levelRank) {
    $ra = $levelRank[$preferencesById[$a['subject_id']] ?? ''] ?? 3;
    $rb = $levelRank[$preferencesById[$b['subject_id']] ?? ''] ?? 3;
    return $ra <=> $rb ?: strcmp($a['subject_code'], $b['subject_code']);
});
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
:root {
    --green: var(--color-success); --green-soft: var(--color-success-soft); --green-border: #bbf7d0;
    --red: var(--color-danger); --red-soft: var(--color-danger-soft); --red-border: #fecaca;
    --amber: var(--color-warning); --amber-soft: var(--color-warning-soft); --amber-border: #fde68a;
    --indigo: #4338ca; --indigo-soft: #e0e7ff; --indigo-border: #c7d2fe;
}

.container { max-width: 980px; margin: 0 auto; }

.page-head { margin-bottom: 20px; }
.page-head h1 { font-size: 24px; font-weight: 700; letter-spacing: -.02em; margin-bottom: 4px; color: var(--color-text-primary); }
.page-head p { color: var(--color-text-secondary); font-size: 13.5px; max-width: 640px; line-height: 1.55; }

.term-bar {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg);
    padding: 14px 20px; margin-bottom: 20px; box-shadow: var(--shadow-sm);
}
.term-bar label { font-size: 12px; font-weight: 600; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: .03em; }
.term-bar select {
    padding: 8px 32px 8px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-sm);
    font-family: inherit; font-size: 13.5px; font-weight: 500; color: var(--color-text-primary);
    background: var(--color-surface) url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%226%22%3E%3Cpath d=%22M1 1l4 4 4-4%22 stroke=%22%2386868b%22 stroke-width=%221.5%22 fill=%22none%22/%3E%3C/svg%3E') no-repeat right 12px center;
    appearance: none; cursor: pointer;
}
.term-bar select:hover { border-color: var(--color-primary); }
.term-pill { font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); }
.term-pill.is-active { color: var(--color-primary); background: var(--color-primary-soft); }
.term-pill.is-upcoming { color: #854d0e; background: var(--amber-soft); }
.term-pill.is-archived { color: var(--color-text-secondary); background: var(--color-surface-sunken); }
.term-bar .copy-group { display: flex; align-items: center; gap: 8px; margin-left: auto; flex-wrap: wrap; }
.term-bar .copy-group label { text-transform: none; letter-spacing: normal; font-weight: 500; color: var(--color-text-secondary); }
.term-bar .copy-group select { padding: 6px 28px 6px 12px; font-size: 12.5px; }
.term-bar .copy-btn {
    padding: 6px 12px; border-radius: var(--radius-sm); border: 1px solid var(--color-border);
    background: var(--color-surface); color: var(--color-primary); font-size: 12px; font-weight: 700;
    cursor: pointer; font-family: inherit; white-space: nowrap;
}
.term-bar .copy-btn:hover:not(:disabled) { border-color: var(--color-primary); background: var(--color-primary-soft); }
.term-bar .copy-btn:disabled { opacity: .5; cursor: not-allowed; }

/* ===== Panel header + quick metrics ===== */
.panel-head-row { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 16px; }
.panel-head-row h2 { margin: 0 0 4px; }
.metric-chips { display: flex; flex-wrap: wrap; gap: 10px; }
.metric-chip {
    display: flex; align-items: center; gap: 8px;
    background: var(--color-surface-secondary); border: 1px solid var(--color-border); border-radius: var(--radius-md);
    padding: 7px 12px;
}
.metric-chip .dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }
.metric-chip .lbl { display: block; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--color-text-tertiary); line-height: 1.3; }
.metric-chip .val { display: block; font-size: 12.5px; font-weight: 700; color: var(--color-text-primary); }

/* ===== Presets + legend ===== */
.tool-strip {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 14px; background: var(--color-surface-secondary); border: 1px solid var(--color-border);
    border-radius: var(--radius-md); margin-bottom: 16px;
}
.preset-group { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.preset-group .lbl { font-size: 12px; font-weight: 600; color: var(--color-text-secondary); }
.preset-btn {
    padding: 5px 11px; border-radius: var(--radius-sm); border: 1px solid var(--color-border);
    background: var(--color-surface); color: var(--color-text-primary); font-size: 11.5px; font-weight: 600;
    cursor: pointer; font-family: inherit; transition: border-color var(--motion-fast), color var(--motion-fast);
}
.preset-btn:hover { border-color: var(--color-primary); color: var(--color-primary); }
.preset-btn.clear { color: var(--color-text-secondary); border: none; background: none; }
.preset-btn.clear:hover { color: var(--red); }

.legend { display: flex; gap: 16px; flex-wrap: wrap; }
.legend span { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--color-text-secondary); }
.legend .dot { width: 8px; height: 8px; border-radius: 2px; }

/* ===== Availability day cards ===== */
.avail-row {
    display: flex; flex-wrap: wrap; align-items: center; gap: 12px;
    padding: 12px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-md); margin-bottom: 10px;
    transition: border-color var(--motion-fast), background-color var(--motion-fast);
}
.avail-row:last-of-type { margin-bottom: 0; }
.avail-day-group { display: flex; align-items: center; gap: 10px; min-width: 220px; }
.avail-day { width: 44px; padding: 8px 0; text-align: center; border-radius: var(--radius-sm); background: var(--color-surface-sunken); color: var(--color-text-primary); font-size: 11.5px; font-weight: 700; letter-spacing: .02em; }
.avail-day-select { display: flex; flex-direction: column; gap: 3px; }
.avail-status-text { font-size: 10.5px; font-weight: 600; color: var(--color-text-tertiary); }
.avail-row.is-available .avail-status-text { color: var(--green); }
.avail-row.is-unavailable .avail-status-text { color: var(--color-text-tertiary); }
.avail-row select.day-status {
    padding: 7px 28px 7px 11px; border: 1px solid var(--color-border); border-radius: var(--radius-sm);
    font-family: inherit; font-size: 12px; font-weight: 700; color: var(--color-text-primary); background-color: var(--color-surface);
    background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%226%22%3E%3Cpath d=%22M1 1l4 4 4-4%22 stroke=%22%2386868b%22 stroke-width=%221.5%22 fill=%22none%22/%3E%3C/svg%3E');
    background-repeat: no-repeat; background-position: right 10px center; appearance: none; cursor: pointer;
}
.avail-time-group { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; flex: 1; min-width: 220px; }
.avail-time-group input[type=time] {
    padding: 7px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm);
    font-family: inherit; font-size: 12.5px; color: var(--color-text-primary); background: var(--color-surface);
}
.avail-time-group span.to { font-size: 12px; color: var(--color-text-tertiary); }
.avail-time-group input:disabled { background: var(--color-surface-sunken); color: var(--color-text-tertiary); cursor: not-allowed; }
.avail-hrs-badge { font-size: 11px; font-weight: 700; padding: 4px 9px; border-radius: var(--radius-sm); background: var(--color-surface-sunken); color: var(--color-text-secondary); white-space: nowrap; }
.avail-copy-btn {
    display: flex; align-items: center; justify-content: center; width: 26px; height: 26px;
    border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-surface);
    color: var(--color-text-tertiary); cursor: pointer; flex: none;
}
.avail-copy-btn .material-symbols-outlined { font-size: 14px; }
.avail-copy-btn:hover { border-color: var(--color-primary); color: var(--color-primary); }
.avail-notes { flex: 1 1 100%; }
.avail-notes input {
    width: 100%; padding: 6px 10px; border: 1px solid transparent; border-radius: var(--radius-sm);
    font-family: inherit; font-size: 12px; color: var(--color-text-secondary); background: transparent;
}
.avail-notes input:hover, .avail-notes input:focus { background: var(--color-surface); border-color: var(--color-border); outline: none; }
.avail-notes input::placeholder { color: var(--color-text-tertiary); }
.avail-notes input:disabled { display: none; }

.avail-row.is-available { background: var(--green-soft); border-color: var(--green-border); }
.avail-row.is-available .avail-hrs-badge { background: #fff; color: var(--green); }
.avail-row.is-unavailable { background: var(--color-surface-sunken); border-style: dashed; opacity: .82; }

/* ===== Heatmap preview ===== */
.heatmap-wrap { overflow-x: auto; margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--color-border); }
.heatmap-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; }
.heatmap-head h3 { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--color-text-secondary); margin: 0; }
.heatmap-head p { font-size: 11.5px; color: var(--color-text-tertiary); margin: 2px 0 0; }
.heatmap { min-width: 640px; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 10px; }
.heatmap-row { display: grid; grid-template-columns: 48px 1fr; align-items: center; gap: 10px; padding: 6px 0; }
.heatmap-row .hm-day { font-size: 11.5px; font-weight: 700; color: var(--color-text-secondary); }
.heatmap-track { position: relative; height: 22px; background: var(--color-surface-sunken); border-radius: var(--radius-sm); overflow: hidden; }
.heatmap-block { position: absolute; top: 2px; bottom: 2px; border-radius: var(--radius-sm); background: var(--green-soft); border: 1px solid var(--green-border); color: var(--green); font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; overflow: hidden; white-space: nowrap; padding: 0 4px; }
.heatmap-empty { font-size: 11px; color: var(--color-text-tertiary); padding: 0 2px; }

/* ===== Subject preference metrics ===== */
.pref-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 16px; }
.pref-metric { border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 12px 14px; background: var(--color-surface-secondary); }
.pref-metric__head { display: flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
.pref-metric__head .dot { width: 7px; height: 7px; border-radius: 50%; }
.pref-metric__val { margin-top: 6px; font-size: 20px; font-weight: 800; color: var(--color-text-primary); }
.pref-metric__val small { font-size: 11px; font-weight: 600; color: var(--color-text-tertiary); }

/* ===== Filter/search toolbar ===== */
.pref-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 16px; }
.pref-search { position: relative; width: 240px; max-width: 100%; }
.pref-search input {
    width: 100%; padding: 7px 12px 7px 30px; border: 1px solid var(--color-border); border-radius: var(--radius-sm);
    font-family: inherit; font-size: 12.5px; color: var(--color-text-primary); background: var(--color-surface-secondary);
}
.pref-search .material-symbols-outlined { position: absolute; left: 8px; top: 50%; transform: translateY(-50%); font-size: 15px; color: var(--color-text-tertiary); }
.pref-tabs { display: flex; flex-wrap: wrap; gap: 6px; }
.pref-tab {
    padding: 6px 12px; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface);
    color: var(--color-text-secondary); font-size: 11.5px; font-weight: 700; cursor: pointer; font-family: inherit;
}
.pref-tab.active { background: var(--color-text-primary); color: #fff; border-color: var(--color-text-primary); }
.pref-tab[data-level="preferred"].active { background: var(--green); border-color: var(--green); }
.pref-tab[data-level="willing"].active { background: var(--amber); border-color: var(--amber); }
.pref-tab[data-level="qualified"].active { background: var(--indigo); border-color: var(--indigo); }

/* ===== Subject preference cards ===== */
.subj-row {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
    padding: 12px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-md); margin-bottom: 10px;
    transition: border-color var(--motion-fast);
}
.subj-row:last-of-type { margin-bottom: 0; }
.subj-row:hover { border-color: var(--color-primary-soft); }
.subj-row.is-hidden { display: none; }
.subj-main { display: flex; flex-direction: column; gap: 2px; max-width: 560px; }
.subj-code-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.subj-code { font-size: 13px; font-weight: 700; color: var(--color-text-primary); font-family: 'SFMono-Regular', ui-monospace, Menlo, monospace; }
.subj-units { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: var(--radius-sm); background: var(--color-info-soft); color: var(--color-info); text-transform: uppercase; }
.subj-title { font-size: 13px; font-weight: 600; color: var(--color-text-primary); }
.subj-desc { font-size: 11.5px; color: var(--color-text-tertiary); margin-top: 2px; }

.pref-pills { display: flex; align-items: center; gap: 4px; background: var(--color-surface-secondary); padding: 5px; border-radius: var(--radius-md); border: 1px solid var(--color-border); flex: none; }
.pref-pill {
    padding: 6px 12px; border-radius: var(--radius-sm); border: none; background: none;
    color: var(--color-text-secondary); font-size: 11.5px; font-weight: 700; cursor: pointer; font-family: inherit;
    transition: background-color var(--motion-fast), color var(--motion-fast);
}
.pref-pill:hover:not(.active) { background: var(--color-surface); }
.pref-pill[data-level="preferred"].active { background: var(--green); color: #fff; }
.pref-pill[data-level="willing"].active { background: var(--amber); color: #fff; }
.pref-pill[data-level="qualified"].active { background: var(--indigo); color: #fff; }
.pref-pill-reset {
    display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: none; background: none;
    color: var(--color-text-tertiary); cursor: pointer; border-radius: var(--radius-sm);
}
.pref-pill-reset:hover { color: var(--red); background: var(--red-soft); }
.pref-pill-reset .material-symbols-outlined { font-size: 15px; }

.save-row { margin-top: 18px; display: flex; justify-content: flex-end; gap: 10px; }

@media (max-width: 720px) {
    .avail-row { flex-direction: column; align-items: stretch; }
    .avail-day-group { min-width: 0; }
    .subj-row { flex-direction: column; align-items: stretch; }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="container">

    <div class="page-head">
        <h1>Set Your Availability</h1>
        <p>Tell your chairperson when you can teach and which subjects you'd like — this shows up while they're plotting the schedule.</p>
    </div>

    <?php
        // Only is_active is a real, stored fact. "Upcoming" is the term
        // AcademicYearModel::upcomingTerm() already treats as the one to
        // plan for; everything else that isn't active or upcoming is
        // effectively archived/completed — there's no separate "finished"
        // column, so this is derived, not fabricated.
        $termStatus = static function (array $t) use ($upcomingAyId) {
            if ($t['is_active']) return ['is-active', 'Active term'];
            if ($upcomingAyId !== null && (int) $t['ay_id'] === $upcomingAyId) return ['is-upcoming', 'Upcoming term'];
            return ['is-archived', 'Archived'];
        };
        $statusRank = ['is-active' => 0, 'is-upcoming' => 1, 'is-archived' => 2];
        // Active first, then upcoming, then archived (most recent archived
        // first) — instead of the raw label/semester order, which scattered
        // these across the list with no relationship to what matters here.
        usort($allTerms, function ($a, $b) use ($termStatus, $statusRank) {
            [$aClass] = $termStatus($a);
            [$bClass] = $termStatus($b);
            return $statusRank[$aClass] <=> $statusRank[$bClass] ?: (int) $b['ay_id'] <=> (int) $a['ay_id'];
        });
        $otherTerms = $term ? array_filter($allTerms, fn ($t) => (int) $t['ay_id'] !== (int) $term['ay_id']) : $allTerms;
    ?>

    <?php if (! empty($allTerms)): ?>
        <div class="term-bar">
            <label for="termSelect">Term</label>
            <select id="termSelect" onchange="if(this.value) window.location.href = '<?= site_url('faculty/availability') ?>?ay_id=' + this.value;">
                <?php foreach ($allTerms as $t): ?>
                    <?php [, $tLabel] = $termStatus($t); ?>
                    <option value="<?= (int) $t['ay_id'] ?>" <?= ($term && (int) $term['ay_id'] === (int) $t['ay_id']) ? 'selected' : '' ?>>
                        <?= esc(\App\Models\AcademicYearModel::format($t['label'], $t['semester'])) ?> (<?= esc($tLabel) ?>)
                    </option>
                <?php endforeach ?>
            </select>
            <?php if ($term): ?>
                <?php [$tClass, $tLabel] = $termStatus($term); ?>
                <span class="term-pill <?= $tClass ?>"><?= esc($tLabel) ?></span>
            <?php endif ?>

            <?php if ($term && ! empty($otherTerms)): ?>
                <div class="copy-group">
                    <label for="copyTermSelect">Copy from</label>
                    <select id="copyTermSelect">
                        <?php foreach ($otherTerms as $t): ?>
                            <option value="<?= (int) $t['ay_id'] ?>"><?= esc(\App\Models\AcademicYearModel::format($t['label'], $t['semester'])) ?></option>
                        <?php endforeach ?>
                    </select>
                    <button type="button" class="copy-btn" id="copyTermBtn">Copy Availability</button>
                </div>
            <?php endif ?>
        </div>
    <?php endif ?>

    <?php if (session()->has('message')): ?>
        <div class="flash success"><?= esc(session('message')) ?></div>
    <?php endif ?>

    <?php if (session()->has('errors')): ?>
        <div class="flash error">
            <?php foreach (session('errors') as $error): ?>
                <p style="margin:0;"><?= esc($error) ?></p>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if ($term === null): ?>
        <div class="notice">No academic term has been configured yet. Ask an admin to add one before you can submit your availability.</div>
    <?php else: ?>

        <div class="panel">
            <div class="panel-head-row">
                <div>
                    <h2>Weekly Availability</h2>
                    <p class="panel-sub" style="margin-bottom:0;">For each day, mark whether you're available, and if so, your time range. Leave a day as "No preference" if you'd rather not commit either way yet.</p>
                </div>
                <div class="metric-chips">
                    <div class="metric-chip">
                        <span class="dot" style="background:var(--green);"></span>
                        <div><span class="lbl">Total available</span><span class="val" id="metricHours">0.0 hrs</span></div>
                    </div>
                    <div class="metric-chip">
                        <span class="dot" style="background:var(--color-primary);"></span>
                        <div><span class="lbl">Active days</span><span class="val" id="metricDays">0 of 6</span></div>
                    </div>
                </div>
            </div>

            <div class="tool-strip">
                <div class="preset-group">
                    <span class="lbl">Quick presets:</span>
                    <button type="button" class="preset-btn" data-preset="weekdays">All Weekdays (8am–5pm)</button>
                    <button type="button" class="preset-btn" data-preset="morning">Morning (8am–12pm)</button>
                    <button type="button" class="preset-btn" data-preset="afternoon">Afternoon (1pm–5pm)</button>
                    <button type="button" class="preset-btn clear" data-preset="clear">Clear all</button>
                </div>
                <div class="legend">
                    <span><span class="dot" style="background:var(--green);"></span> Available</span>
                    <span><span class="dot" style="background:var(--color-text-tertiary);"></span> Not available</span>
                </div>
            </div>

            <form method="post" action="<?= site_url('faculty/availability/update') ?>" id="availabilityForm">
                <?= csrf_field() ?>
                <input type="hidden" name="ay_id" value="<?= (int) $term['ay_id'] ?>">

                <?php foreach ($dayLabels as $day => $label): ?>
                    <?php $row = $availabilityByDay[$day] ?? null; ?>
                    <?php $status = $row ? ($row['is_available'] ? 'available' : 'unavailable') : ''; ?>
                    <div class="avail-row <?= $status === 'available' ? 'is-available' : ($status === 'unavailable' ? 'is-unavailable' : '') ?>" id="row_<?= $day ?>" data-day="<?= $day ?>">
                        <div class="avail-day-group">
                            <div class="avail-day"><?= esc(strtoupper($label)) ?></div>
                            <div class="avail-day-select">
                                <select name="day_<?= $day ?>_status" class="day-status" data-day="<?= $day ?>" onchange="toggleTimes(<?= $day ?>)">
                                    <option value="" <?= $status === '' ? 'selected' : '' ?>>No preference</option>
                                    <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option>
                                    <option value="unavailable" <?= $status === 'unavailable' ? 'selected' : '' ?>>Not Available</option>
                                </select>
                                <span class="avail-status-text" id="statusText_<?= $day ?>"></span>
                            </div>
                        </div>
                        <div class="avail-time-group">
                            <input type="time" name="day_<?= $day ?>_start" id="start_<?= $day ?>" value="<?= esc($row['start_time'] ?? '') ?>" oninput="toggleTimes(<?= $day ?>)">
                            <span class="to">to</span>
                            <input type="time" name="day_<?= $day ?>_end" id="end_<?= $day ?>" value="<?= esc($row['end_time'] ?? '') ?>" oninput="toggleTimes(<?= $day ?>)">
                            <span class="avail-hrs-badge" id="hrs_<?= $day ?>">0.0 hrs</span>
                            <?php if ($day < 5): ?>
                                <button type="button" class="avail-copy-btn" data-day="<?= $day ?>" title="Copy this time range to all weekdays">
                                    <span class="material-symbols-outlined">content_copy</span>
                                </button>
                            <?php endif ?>
                        </div>
                        <div class="avail-notes">
                            <input type="text" name="day_<?= $day ?>_notes" id="notes_<?= $day ?>" maxlength="150" placeholder="Optional note for your chairperson (e.g. lab availability only)…" value="<?= esc($row['notes'] ?? '') ?>">
                        </div>
                    </div>
                <?php endforeach ?>

                <div class="heatmap-wrap">
                    <div class="heatmap-head">
                        <div>
                            <h3>Weekly Preview</h3>
                            <p>How your submitted hours line up across the week.</p>
                        </div>
                    </div>
                    <div class="heatmap" id="heatmap"></div>
                </div>

                <div class="save-row">
                    <button type="button" class="btn btn-secondary" id="discardBtn">Discard Changes</button>
                    <button type="submit" class="btn btn-primary">Save Availability</button>
                </div>
            </form>
        </div>

        <?php
            // Real counts/unit sums per level, computed from the actual
            // saved preferences — not the mockup's fabricated "18 unit
            // target" or deadline countdown, which have no backing field.
            $levelCounts = ['preferred' => 0, 'willing' => 0, 'qualified' => 0, '' => 0];
            $levelUnits  = ['preferred' => 0.0, 'willing' => 0.0, 'qualified' => 0.0];
            $subjectsById = [];
            foreach ($subjects as $s) {
                $subjectsById[$s['subject_id']] = $s;
                $lvl = $preferencesById[$s['subject_id']] ?? '';
                $levelCounts[$lvl]++;
                if ($lvl !== '') $levelUnits[$lvl] += (float) $s['units'];
            }
        ?>

        <div class="panel">
            <h2>Subject Preferences</h2>
            <p class="panel-sub">Let your chairperson know which subjects you'd like to teach this term.</p>

            <?php if (empty($subjects)): ?>
                <p class="panel-sub" style="margin-bottom:0;">No subjects found for your department yet.</p>
            <?php else: ?>
                <div class="pref-metrics">
                    <div class="pref-metric">
                        <div class="pref-metric__head" style="color:var(--green);"><span class="dot" style="background:var(--green);"></span> Preferred</div>
                        <div class="pref-metric__val" id="metricPreferred"><?= $levelCounts['preferred'] ?> <small>subjects · <?= rtrim(rtrim(number_format($levelUnits['preferred'], 1), '0'), '.') ?: '0' ?> units</small></div>
                    </div>
                    <div class="pref-metric">
                        <div class="pref-metric__head" style="color:#854d0e;"><span class="dot" style="background:var(--amber);"></span> Willing</div>
                        <div class="pref-metric__val" id="metricWilling"><?= $levelCounts['willing'] ?> <small>subjects · <?= rtrim(rtrim(number_format($levelUnits['willing'], 1), '0'), '.') ?: '0' ?> units</small></div>
                    </div>
                    <div class="pref-metric">
                        <div class="pref-metric__head" style="color:var(--indigo);"><span class="dot" style="background:var(--indigo);"></span> Qualified</div>
                        <div class="pref-metric__val" id="metricQualified"><?= $levelCounts['qualified'] ?> <small>subjects</small></div>
                    </div>
                    <div class="pref-metric">
                        <div class="pref-metric__head" style="color:var(--color-text-secondary);"><span class="dot" style="background:var(--color-text-tertiary);"></span> Not set</div>
                        <div class="pref-metric__val" id="metricUnset"><?= $levelCounts[''] ?> <small>of <?= count($subjects) ?> subjects</small></div>
                    </div>
                </div>

                <div class="pref-toolbar">
                    <div class="pref-search">
                        <span class="material-symbols-outlined">search</span>
                        <input type="text" id="subjSearch" placeholder="Filter by subject code or title…">
                    </div>
                    <div class="pref-tabs" id="prefTabs">
                        <button type="button" class="pref-tab active" data-level="">All (<?= count($subjects) ?>)</button>
                        <button type="button" class="pref-tab" data-level="preferred">Preferred (<?= $levelCounts['preferred'] ?>)</button>
                        <button type="button" class="pref-tab" data-level="willing">Willing (<?= $levelCounts['willing'] ?>)</button>
                        <button type="button" class="pref-tab" data-level="qualified">Qualified (<?= $levelCounts['qualified'] ?>)</button>
                        <button type="button" class="pref-tab" data-level="unset">Not set (<?= $levelCounts[''] ?>)</button>
                    </div>
                </div>

                <form method="post" action="<?= site_url('faculty/preferences/update') ?>" id="preferencesForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ay_id" value="<?= (int) $term['ay_id'] ?>">

                    <div id="subjList">
                        <?php foreach ($sortedSubjects as $subject): ?>
                            <?php $current = $preferencesById[$subject['subject_id']] ?? ''; ?>
                            <div class="subj-row" data-level="<?= esc($current) ?>" data-units="<?= (float) $subject['units'] ?>" data-search="<?= esc(strtolower($subject['subject_code'] . ' ' . $subject['subject_name'])) ?>">
                                <div class="subj-main">
                                    <div class="subj-code-row">
                                        <span class="subj-code"><?= esc($subject['subject_code']) ?></span>
                                        <span class="subj-units"><?= (float) $subject['units'] ?> Units<?= ! empty($subject['is_laboratory']) ? ' · Lab' : '' ?></span>
                                    </div>
                                    <span class="subj-title"><?= esc($subject['subject_name']) ?></span>
                                    <?php if (! empty($subject['description'])): ?>
                                        <span class="subj-desc"><?= esc($subject['description']) ?></span>
                                    <?php endif ?>
                                </div>
                                <div class="pref-pills" data-subject="<?= $subject['subject_id'] ?>">
                                    <input type="hidden" name="preference[<?= $subject['subject_id'] ?>]" class="pref-input" value="<?= esc($current) ?>">
                                    <?php foreach ($preferenceLevels as $level): ?>
                                        <button type="button" class="pref-pill <?= $current === $level ? 'active' : '' ?>" data-level="<?= $level ?>"><?= esc(ucfirst($level)) ?></button>
                                    <?php endforeach ?>
                                    <button type="button" class="pref-pill-reset" title="Reset to not set">
                                        <span class="material-symbols-outlined">close</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>

                    <p class="panel-sub" id="subjEmpty" style="display:none; text-align:center; margin:20px 0 0;">No subjects match your filter.</p>

                    <div class="save-row">
                        <button type="submit" class="btn btn-primary">Save Preferences</button>
                    </div>
                </form>
            <?php endif ?>
        </div>

    <?php endif ?>

</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    var DAY_LABELS = <?= json_encode(array_values($dayLabels)) ?>;

    function toMinutes(hhmm) {
        if (!hhmm) return null;
        var parts = hhmm.split(':');
        return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
    }

    function toggleTimes(day) {
        var status = document.querySelector('.day-status[data-day="' + day + '"]').value;
        var disabled = status !== 'available';
        var startEl = document.getElementById('start_' + day);
        var endEl = document.getElementById('end_' + day);
        var notesEl = document.getElementById('notes_' + day);
        startEl.disabled = disabled;
        endEl.disabled = disabled;
        notesEl.disabled = (status === '');
        if (disabled) {
            startEl.value = '';
            endEl.value = '';
        }
        var row = document.getElementById('row_' + day);
        row.classList.remove('is-available', 'is-unavailable');
        if (status === 'available') row.classList.add('is-available');
        else if (status === 'unavailable') row.classList.add('is-unavailable');

        var statusText = document.getElementById('statusText_' + day);
        statusText.textContent = status === 'available' ? 'Available' : status === 'unavailable' ? 'Not available' : 'No preference set';

        updateHours(day);
        updateMetrics();
        updateHeatmap();
    }

    function updateHours(day) {
        var start = toMinutes(document.getElementById('start_' + day).value);
        var end = toMinutes(document.getElementById('end_' + day).value);
        var hrs = (start !== null && end !== null && end > start) ? (end - start) / 60 : 0;
        document.getElementById('hrs_' + day).textContent = hrs.toFixed(1) + ' hrs';
        return hrs;
    }

    function updateMetrics() {
        var totalHrs = 0, activeDays = 0;
        document.querySelectorAll('.day-status').forEach(function (el) {
            var day = el.dataset.day;
            if (el.value === 'available') {
                totalHrs += updateHours(day);
                activeDays++;
            }
        });
        document.getElementById('metricHours').textContent = totalHrs.toFixed(1) + ' hrs';
        document.getElementById('metricDays').textContent = activeDays + ' of 6';
    }

    function fmt12(hhmm) {
        var parts = hhmm.split(':');
        var h = parseInt(parts[0], 10);
        var suffix = h >= 12 ? 'PM' : 'AM';
        var h12 = h % 12 === 0 ? 12 : h % 12;
        return h12 + ':' + parts[1] + ' ' + suffix;
    }

    function updateHeatmap() {
        var container = document.getElementById('heatmap');
        container.innerHTML = '';
        var dayMin = 7 * 60, dayMax = 19 * 60;

        document.querySelectorAll('.day-status').forEach(function (el) {
            var day = el.dataset.day;
            var row = document.createElement('div');
            row.className = 'heatmap-row';

            var label = document.createElement('div');
            label.className = 'hm-day';
            label.textContent = DAY_LABELS[day];
            row.appendChild(label);

            var track = document.createElement('div');
            track.className = 'heatmap-track';

            if (el.value === 'available') {
                var start = document.getElementById('start_' + day).value;
                var end = document.getElementById('end_' + day).value;
                var startMin = toMinutes(start), endMin = toMinutes(end);
                if (startMin !== null && endMin !== null && endMin > startMin) {
                    var block = document.createElement('div');
                    block.className = 'heatmap-block';
                    var left = Math.max(0, (startMin - dayMin) / (dayMax - dayMin) * 100);
                    var width = Math.min(100 - left, (endMin - startMin) / (dayMax - dayMin) * 100);
                    block.style.left = left + '%';
                    block.style.width = width + '%';
                    block.textContent = fmt12(start) + ' – ' + fmt12(end);
                    track.appendChild(block);
                }
            }

            row.appendChild(track);
            container.appendChild(row);
        });
    }

    document.querySelectorAll('.day-status').forEach(function (el) {
        toggleTimes(el.dataset.day);
    });

    // Snapshot of what the server actually rendered, so "Discard Changes"
    // can restore it exactly rather than just reloading the page.
    var originalState = {};
    for (var d0 = 0; d0 < 6; d0++) {
        originalState[d0] = {
            status: document.querySelector('.day-status[data-day="' + d0 + '"]').value,
            start: document.getElementById('start_' + d0).value,
            end: document.getElementById('end_' + d0).value,
            notes: document.getElementById('notes_' + d0).value,
        };
    }

    // Programmatic value assignment doesn't fire input/change events, so
    // anything that fills fields via JS (presets, per-row copy, copy from
    // another term) has to tell the global unsaved-changes tracker itself.
    function markFormDirty() {
        if (window.UnsavedChanges) window.UnsavedChanges.markDirty();
    }

    // ---------- quick presets ----------
    function applyPreset(day, status, start, end, notes) {
        var sel = document.querySelector('.day-status[data-day="' + day + '"]');
        sel.value = status;
        document.getElementById('start_' + day).value = start || '';
        document.getElementById('end_' + day).value = end || '';
        if (notes !== undefined) document.getElementById('notes_' + day).value = notes || '';
        toggleTimes(day);
    }

    document.querySelectorAll('.preset-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var preset = btn.dataset.preset;
            var weekdayIdx = [0, 1, 2, 3, 4]; // Mon–Fri
            if (preset === 'weekdays') {
                weekdayIdx.forEach(function (d) { applyPreset(d, 'available', '08:00', '17:00'); });
            } else if (preset === 'morning') {
                weekdayIdx.forEach(function (d) { applyPreset(d, 'available', '08:00', '12:00'); });
            } else if (preset === 'afternoon') {
                weekdayIdx.forEach(function (d) { applyPreset(d, 'available', '13:00', '17:00'); });
            } else if (preset === 'clear') {
                for (var d = 0; d < 6; d++) applyPreset(d, '', '', '');
            }
            markFormDirty();
        });
    });

    // ---------- per-row "copy to all weekdays" ----------
    document.querySelectorAll('.avail-copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var srcDay = btn.dataset.day;
            var status = document.querySelector('.day-status[data-day="' + srcDay + '"]').value;
            if (status !== 'available') return;
            var start = document.getElementById('start_' + srcDay).value;
            var end = document.getElementById('end_' + srcDay).value;
            if (!start || !end) return;
            [0, 1, 2, 3, 4].forEach(function (d) { applyPreset(d, 'available', start, end); });
            markFormDirty();
        });
    });

    // ---------- copy from another term ----------
    var copyTermBtn = document.getElementById('copyTermBtn');
    if (copyTermBtn) {
        copyTermBtn.addEventListener('click', function () {
            var ayId = document.getElementById('copyTermSelect').value;
            if (!ayId) return;
            copyTermBtn.disabled = true;
            copyTermBtn.textContent = 'Copying…';
            fetch('<?= site_url('faculty/availability/for-term') ?>/' + ayId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) return;
                    for (var d = 0; d < 6; d++) {
                        var day = data.days[d];
                        if (day) applyPreset(d, day.status, day.start, day.end, day.notes);
                        else applyPreset(d, '', '', '', '');
                    }
                    markFormDirty();
                })
                .finally(function () {
                    copyTermBtn.disabled = false;
                    copyTermBtn.textContent = 'Copy Availability';
                });
        });
    }

    // ---------- discard changes ----------
    var discardBtn = document.getElementById('discardBtn');
    if (discardBtn) {
        discardBtn.addEventListener('click', function () {
            for (var d = 0; d < 6; d++) {
                var s = originalState[d];
                applyPreset(d, s.status, s.start, s.end, s.notes);
            }
            if (window.UnsavedChanges) window.UnsavedChanges.markClean();
        });
    }

    // ---------- subject preference pills ----------
    var subjRows = document.querySelectorAll('#subjList .subj-row');

    function fmtUnits(n) {
        return (Math.round(n * 10) / 10).toString();
    }

    function recomputePrefMetrics() {
        var counts = { preferred: 0, willing: 0, qualified: 0, '': 0 };
        var units = { preferred: 0, willing: 0 };
        subjRows.forEach(function (row) {
            var level = row.dataset.level;
            counts[level] = (counts[level] || 0) + 1;
            if (level === 'preferred' || level === 'willing') units[level] += parseFloat(row.dataset.units) || 0;
        });

        var total = subjRows.length;
        setText('metricPreferred', counts.preferred + ' subjects · ' + fmtUnits(units.preferred) + ' units', counts.preferred);
        setText('metricWilling', counts.willing + ' subjects · ' + fmtUnits(units.willing) + ' units', counts.willing);
        setText('metricQualified', counts.qualified + ' subjects', counts.qualified);
        setText('metricUnset', counts[''] + ' of ' + total + ' subjects', counts['']);

        var tabsEl = document.getElementById('prefTabs');
        if (tabsEl) {
            tabsEl.querySelector('[data-level=""]').textContent = 'All (' + total + ')';
            tabsEl.querySelector('[data-level="preferred"]').textContent = 'Preferred (' + counts.preferred + ')';
            tabsEl.querySelector('[data-level="willing"]').textContent = 'Willing (' + counts.willing + ')';
            tabsEl.querySelector('[data-level="qualified"]').textContent = 'Qualified (' + counts.qualified + ')';
            tabsEl.querySelector('[data-level="unset"]').textContent = 'Not set (' + counts[''] + ')';
        }
    }

    function setText(id, small, big) {
        var el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = big + ' <small>' + small.replace(/^\d+\s/, '') + '</small>';
    }

    document.querySelectorAll('.pref-pills').forEach(function (group) {
        var input = group.querySelector('.pref-input');
        var pills = group.querySelectorAll('.pref-pill');
        var resetBtn = group.querySelector('.pref-pill-reset');
        var row = group.closest('.subj-row');

        function setLevel(level) {
            input.value = level;
            row.dataset.level = level;
            pills.forEach(function (p) { p.classList.toggle('active', p.dataset.level === level); });
            if (window.UnsavedChanges) window.UnsavedChanges.markDirty();
            recomputePrefMetrics();
            applySubjFilter();
        }

        pills.forEach(function (pill) {
            pill.addEventListener('click', function () {
                setLevel(pill.classList.contains('active') ? '' : pill.dataset.level);
            });
        });
        if (resetBtn) resetBtn.addEventListener('click', function () { setLevel(''); });
    });

    // ---------- filter tabs + search ----------
    var activeTab = '';
    var subjSearch = document.getElementById('subjSearch');
    var subjEmpty = document.getElementById('subjEmpty');

    function applySubjFilter() {
        var query = (subjSearch.value || '').trim().toLowerCase();
        var visible = 0;
        subjRows.forEach(function (row) {
            var level = row.dataset.level;
            var matchesTab = activeTab === '' || (activeTab === 'unset' ? level === '' : level === activeTab);
            var matchesSearch = query === '' || row.dataset.search.indexOf(query) !== -1;
            var show = matchesTab && matchesSearch;
            row.classList.toggle('is-hidden', !show);
            if (show) visible++;
        });
        subjEmpty.style.display = visible === 0 ? '' : 'none';
    }

    document.querySelectorAll('.pref-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.pref-tab').forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            activeTab = tab.dataset.level;
            applySubjFilter();
        });
    });
    if (subjSearch) subjSearch.addEventListener('input', applySubjFilter);
</script>
<?= $this->endSection() ?>

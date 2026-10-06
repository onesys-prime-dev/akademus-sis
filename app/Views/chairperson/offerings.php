<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'offerings';
$pageTitle     = 'Merged Classes';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    .mc-card { padding: 0; }
    .mc-schedule-form { display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap; align-items: center; }
    .mc-schedule-form select, .mc-schedule-form input { font-size: 12px; padding: 4px 6px; border: 1px solid var(--color-border); border-radius: var(--radius-md); }

    .mc-card__head { padding: 20px 24px; border-bottom: 1px solid var(--color-border); }
    .mc-card__title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .mc-card__title-row h2 { margin: 0; font-size: 16px; font-weight: 800; color: var(--color-text-primary); }
    .mc-card__head p { margin: 6px 0 0; font-size: 12.5px; color: var(--color-text-secondary); max-width: 640px; line-height: 1.5; }

    .mc-count { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); background: var(--color-primary-soft); color: var(--color-primary); }
    .mc-count--success { background: var(--color-success-soft); color: #166534; }
    .mc-count--warn { background: var(--color-warning-soft, #fef3c7); color: var(--color-warning, #92400e); }

    .mc-row { padding: 20px 24px; border-bottom: 1px solid var(--color-border); display: flex; flex-wrap: wrap; gap: 20px; align-items: center; justify-content: space-between; transition: background-color var(--transition-fast); }
    .mc-row:last-child { border-bottom: none; }
    .mc-row:hover { background: var(--color-surface-secondary); }
    .mc-row__main { flex: 1; min-width: 280px; }

    .mc-code { font-size: 15px; font-weight: 800; color: var(--color-text-primary); letter-spacing: -.01em; }
    .mc-meta { font-size: 11.5px; color: var(--color-text-tertiary); font-weight: 600; margin: 3px 0 10px; }

    .mc-sections { display: flex; flex-wrap: wrap; gap: 8px; }
    .mc-sec-check { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); cursor: pointer; font-size: 12.5px; font-weight: 600; color: var(--color-text-primary); transition: border-color var(--transition-fast), background-color var(--transition-fast); }
    .mc-sec-check:hover { border-color: var(--color-primary); background: var(--color-primary-soft); }
    .mc-sec-check input { accent-color: var(--color-primary); width: 15px; height: 15px; flex: none; }

    .mc-cap-pill { font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: var(--radius-pill); background: var(--color-surface-sunken); color: var(--color-text-secondary); white-space: nowrap; }

    .mc-row__actions { display: flex; align-items: center; gap: 10px; }
    .mc-row__actions input[type="number"] { width: 130px; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 13px; font-family: inherit; color: var(--color-text-primary); }
    .mc-row__actions input[type="number"]:focus { outline: none; border-color: var(--color-primary); box-shadow: var(--focus-ring); }

    .mc-merged { padding: 20px 24px; }
    .mc-merged-card { border: 1px solid var(--color-primary-soft); background: var(--color-surface); border-radius: var(--radius-lg); padding: 18px 20px; margin-bottom: 16px; }
    .mc-merged-card:last-child { margin-bottom: 0; }
    .mc-merged-card__top { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 12px; }
    .mc-merged-card__title { font-size: 14.5px; font-weight: 800; color: var(--color-text-primary); }

    .mc-active-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); background: var(--color-success-soft); color: #166534; }
    .mc-active-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--color-success); }

    .mc-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
    .mc-pill { display: inline-flex; align-items: center; gap: 4px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 5px 6px 5px 12px; font-size: 12px; font-weight: 600; color: var(--color-text-primary); }
    .mc-pill button { border: none; background: none; padding: 4px; border-radius: 50%; display: flex; cursor: pointer; color: var(--color-text-tertiary); }
    .mc-pill button:hover { background: var(--color-danger-soft); color: var(--color-danger); }

    .mc-progress-wrap { max-width: 360px; }
    .mc-progress-head { display: flex; justify-content: space-between; gap: 8px; font-size: 11.5px; font-weight: 700; margin-bottom: 5px; }
    .mc-progress-head span:first-child { color: var(--color-text-secondary); }
    .mc-progress-head span:last-child { color: var(--color-primary); }
    .mc-progress-head.is-over span:last-child { color: var(--color-danger); }
    .mc-track { height: 6px; border-radius: var(--radius-pill); background: var(--color-surface-sunken); overflow: hidden; }
    .mc-fill { height: 100%; background: var(--color-primary); border-radius: var(--radius-pill); }
    .mc-fill.is-over { background: var(--color-danger); }

    @media (max-width: 640px) {
        .mc-row { flex-direction: column; align-items: stretch; }
        .mc-row__actions { justify-content: space-between; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Merged Classes</h2>
        <p class="ac-page-header__sub">Combine the same subject across sections into one shared roster and capacity.</p>
    </div>
</div>

<?php if ($deptName === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>You're not assigned to a department yet. Contact an administrator.</p>
        </div>
    </div>
<?php else: ?>

    <div id="feedback" class="flash" style="display:none;"></div>

    <div class="panel mc-card">
        <div class="mc-card__head">
            <div class="mc-card__title-row">
                <h2>Needs Plotting</h2>
                <span class="mc-count <?= count($needsPlotting) ? 'mc-count--warn' : '' ?>"><?= count($needsPlotting) ?> Waiting</span>
            </div>
            <p>Classes from any department that require one of your subjects, with no offering yet — live demand, computed from current sectioning and enrollment. Pick one to create its offering and start plotting it with your own faculty.</p>
        </div>

        <?php if (empty($needsPlotting)): ?>
            <div class="empty-state">
                <div class="material-symbols-outlined">task_alt</div>
                <p>Nothing is waiting — every class that needs one of your subjects already has an offering.</p>
            </div>
        <?php else: ?>
            <div class="mc-row" style="flex-direction:column; align-items:stretch; gap:14px;">
                <div class="mc-schedule-form" style="gap:10px;">
                    <select id="needsPlottingSelect" style="flex:1; min-width:260px; padding:9px 12px; border:1px solid var(--color-border); border-radius:var(--radius-md); font-size:13px;">
                        <?php foreach ($needsPlotting as $i => $np): ?>
                            <option value="<?= $i ?>"><?= esc($np['subject_code']) ?> — <?= esc($np['section_label']) ?> (<?= esc($np['expected_count']) ?> student<?= $np['expected_count'] === 1 ? '' : 's' ?>)</option>
                        <?php endforeach ?>
                    </select>
                    <button type="button" class="btn btn-primary" onclick="plotDemand()">
                        <span class="material-symbols-outlined" style="font-size:18px;">add_task</span>
                        Create &amp; Plot
                    </button>
                </div>
            </div>
        <?php endif ?>
    </div>

    <div class="panel mc-card">
        <div class="mc-card__head">
            <div class="mc-card__title-row">
                <h2>Mergeable Subjects</h2>
                <span class="mc-count"><?= count($candidates) ?> Available</span>
            </div>
            <p>A subject plotted for two or more of your sections in the same term. Merging combines their rosters into one class with one shared capacity — neither section's other subjects are affected.</p>
        </div>

        <?php if (empty($candidates)): ?>
            <div class="empty-state">
                <div class="material-symbols-outlined">merge_type</div>
                <p>No subject is currently plotted for two or more of your sections in the same term.</p>
            </div>
        <?php else: ?>
            <?php foreach ($candidates as $i => $c): ?>
                <article class="mc-row">
                    <div class="mc-row__main">
                        <span class="mc-code"><?= esc($c['subject_code']) ?></span>
                        <div class="mc-meta"><?= esc($c['academic_year']) ?></div>
                        <div class="mc-sections">
                            <?php foreach ($c['sections'] as $s): ?>
                                <label class="mc-sec-check">
                                    <input type="checkbox" name="cand-<?= $i ?>" value="<?= esc($s['section_id']) ?>">
                                    <span><?= esc($s['program']) ?> &middot; <?= esc($s['year_level']) ?> &middot; Section <?= esc($s['section']) ?></span>
                                    <span class="mc-cap-pill"><?= $s['capacity'] !== null ? 'Cap ' . esc($s['capacity']) : 'Unlimited' ?></span>
                                </label>
                            <?php endforeach ?>
                        </div>
                    </div>
                    <div class="mc-row__actions">
                        <input type="number" min="1" placeholder="Cap (optional)" id="cap-<?= $i ?>">
                        <button type="button" class="btn btn-primary" onclick="mergeCandidate(<?= $i ?>, '<?= esc($c['academic_year'], 'js') ?>', '<?= esc($c['subject_code'], 'js') ?>')">
                            <span class="material-symbols-outlined" style="font-size:18px;">merge_type</span>
                            Merge Selected
                        </button>
                    </div>
                </article>
            <?php endforeach ?>
        <?php endif ?>
    </div>

    <div class="panel mc-card">
        <div class="mc-card__head">
            <div class="mc-card__title-row">
                <h2>Merged Classes</h2>
                <span class="mc-count mc-count--success"><?= count($offerings) ?> Active</span>
            </div>
            <p>Each card is one combined class instance. Removing a section returns its students to being counted against that section's own capacity — no one is dropped from the roster.</p>
        </div>

        <?php if (empty($offerings)): ?>
            <div class="empty-state">
                <div class="material-symbols-outlined">inbox</div>
                <p>No merged classes yet.</p>
            </div>
        <?php else: ?>
            <div class="mc-merged">
                <?php foreach ($offerings as $o): ?>
                    <?php
                        $overCapacity = $o['capacity'] !== null && $o['roster_count'] > $o['capacity'];
                        $pct = ($o['capacity'] !== null && $o['capacity'] > 0) ? round($o['roster_count'] / $o['capacity'] * 100) : null;
                    ?>
                    <div class="mc-merged-card">
                        <div class="mc-merged-card__top">
                            <span class="mc-merged-card__title"><?= esc($o['subject_code']) ?></span>
                            <span class="mc-active-badge"><span class="mc-active-dot"></span> Active Merged</span>
                            <span class="mc-meta" style="margin:0;"><?= esc($o['academic_year']) ?></span>
                            <?php if (! $o['can_manage']): ?>
                                <span class="mc-cap-pill" title="Only that department can change faculty, schedule, or section membership for this subject.">
                                    <?= $o['owner_dept_name'] ? 'Managed by ' . esc($o['owner_dept_name']) : 'Managed by another department' ?>
                                </span>
                            <?php endif ?>
                        </div>
                        <div class="mc-pills">
                            <?php foreach ($o['sections'] as $s): ?>
                                <span class="mc-pill">
                                    <?= esc($s['program']) ?> &middot; <?= esc($s['year_level']) ?> &middot; Sec. <?= esc($s['section']) ?>
                                    <?php if ($o['can_manage']): ?>
                                        <button type="button" title="Remove from merge" onclick="unmergeSection(<?= esc($o['offering_id']) ?>, <?= esc($s['section_id']) ?>, '<?= esc($s['section'], 'js') ?>')">
                                            <span class="material-symbols-outlined" style="font-size:15px;">close</span>
                                        </button>
                                    <?php endif ?>
                                </span>
                            <?php endforeach ?>
                        </div>
                        <?php if ($pct !== null): ?>
                            <div class="mc-progress-wrap">
                                <div class="mc-progress-head <?= $overCapacity ? 'is-over' : '' ?>">
                                    <span>Roster</span>
                                    <span><?= esc($o['roster_count']) ?> / <?= esc($o['capacity']) ?> (<?= $pct ?>%)<?= $overCapacity ? ' · over capacity' : '' ?></span>
                                </div>
                                <div class="mc-track"><div class="mc-fill <?= $overCapacity ? 'is-over' : '' ?>" style="width:<?= min(100, $pct) ?>%;"></div></div>
                            </div>
                        <?php else: ?>
                            <div class="mc-meta" style="margin-bottom:0;">Roster <?= esc($o['roster_count']) ?> (unlimited capacity)</div>
                        <?php endif ?>

                        <div class="mc-schedule">
                            <div class="mc-meta" style="margin:10px 0 6px;">Schedule — one class time for every attached section</div>
                            <?php if (empty($o['meetings'])): ?>
                                <div class="mc-meta">No meeting plotted yet.</div>
                            <?php else: ?>
                                <?php foreach ($o['meetings'] as $m): ?>
                                    <div class="mc-pill" style="margin-bottom:4px;">
                                        <?= esc(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][(int) $m['day']] ?? '?') ?>
                                        <?= esc(substr($m['start_time'], 0, 5)) ?>–<?= esc(substr($m['end_time'], 0, 5)) ?>
                                        · <?= esc($m['room']) ?>
                                        <?php if (! empty($m['prof'])): ?> · <?= esc($m['prof']) ?><?php endif ?>
                                    </div>
                                <?php endforeach ?>
                            <?php endif ?>

                            <?php if ($o['can_manage']): ?>
                                <form class="mc-schedule-form" onsubmit="return addOfferingMeeting(event, <?= (int) $o['offering_id'] ?>)">
                                    <select name="day" required>
                                        <option value="0">Mon</option><option value="1">Tue</option><option value="2">Wed</option>
                                        <option value="3">Thu</option><option value="4">Fri</option><option value="5">Sat</option>
                                    </select>
                                    <input type="time" name="start" required>
                                    <input type="time" name="end" required>
                                    <select name="room" required>
                                        <option value="">Room…</option>
                                        <?php foreach ($rooms as $r): ?>
                                            <option value="<?= esc($r['name']) ?>"><?= esc($r['name']) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary">Add Meeting</button>
                                </form>
                            <?php endif ?>
                        </div>

                        <?php if (! empty($o['components'])): ?>
                            <div class="mc-components" style="margin-top:14px; border-top:1px dashed var(--color-border); padding-top:12px;">
                                <div class="mc-meta" style="margin-bottom:8px;">
                                    Components
                                    <?php if ($o['completeness'] !== null): ?>
                                        <span class="mc-cap-pill" style="<?= $o['completeness']['complete'] ? '' : 'background:var(--color-danger-soft); color:var(--color-danger);' ?>">
                                            <?= $o['completeness']['complete'] ? 'Complete' : 'Incomplete' ?>
                                        </span>
                                    <?php endif ?>
                                </div>
                                <?php foreach ($o['components'] as $c): ?>
                                    <div class="mc-merged-card" style="background:var(--color-surface-sunken); margin-bottom:10px;">
                                        <div class="mc-merged-card__top">
                                            <span class="mc-merged-card__title" style="font-size:13px;"><?= esc($c['name'] ?: ('Component #' . $c['id'])) ?></span>
                                            <span class="mc-cap-pill"><?= $c['is_required'] ? 'Required' : 'Optional' ?> · <?= esc($c['roster_mode']) ?></span>
                                            <?php if ($c['required_hours'] !== null): ?><span class="mc-meta" style="margin:0;"><?= esc($c['required_hours']) ?>h/wk</span><?php endif ?>
                                            <?php if ($o['can_manage']): ?>
                                                <button type="button" class="ac-btn-outline" onclick="deactivateComponent(<?= (int) $c['id'] ?>)">Deactivate</button>
                                            <?php endif ?>
                                        </div>

                                        <?php if (empty($c['meetings'])): ?>
                                            <div class="mc-meta">No meeting plotted yet.</div>
                                        <?php else: ?>
                                            <?php foreach ($c['meetings'] as $m): ?>
                                                <div class="mc-pill" style="margin-bottom:4px;">
                                                    <?= esc(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][(int) $m['day']] ?? '?') ?>
                                                    <?= esc(substr($m['start_time'], 0, 5)) ?>–<?= esc(substr($m['end_time'], 0, 5)) ?>
                                                    · <?= esc($m['room']) ?>
                                                </div>
                                            <?php endforeach ?>
                                        <?php endif ?>

                                        <?php if ($o['can_manage']): ?>
                                            <form class="mc-schedule-form" onsubmit="return addComponentMeeting(event, <?= (int) $c['id'] ?>)">
                                                <select name="day" required>
                                                    <option value="0">Mon</option><option value="1">Tue</option><option value="2">Wed</option>
                                                    <option value="3">Thu</option><option value="4">Fri</option><option value="5">Sat</option>
                                                </select>
                                                <input type="time" name="start" required>
                                                <input type="time" name="end" required>
                                                <select name="room" required>
                                                    <option value="">Room…</option>
                                                    <?php foreach ($rooms as $r): ?>
                                                        <option value="<?= esc($r['name']) ?>"><?= esc($r['name']) ?></option>
                                                    <?php endforeach ?>
                                                </select>
                                                <button type="submit" class="btn btn-primary">Add Meeting</button>
                                            </form>
                                        <?php endif ?>

                                        <?php if ($c['roster_mode'] === 'explicit'): ?>
                                            <div class="mc-meta" style="margin:10px 0 4px;">Roster (<?= count($c['members']) ?> students) — enter a user ID to add/remove</div>
                                            <?php if ($o['can_manage']): ?>
                                                <form class="mc-schedule-form" onsubmit="return addComponentStudent(event, <?= (int) $c['id'] ?>)">
                                                    <input type="number" name="user_id" placeholder="User ID" required>
                                                    <button type="submit" class="btn btn-primary">Add Student</button>
                                                </form>
                                            <?php endif ?>
                                        <?php endif ?>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>

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

    async function postJSON(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        return res.json();
    }

    function reloadWithMessage(message) {
        sessionStorage.setItem('offeringsFeedback', message);
        location.reload();
    }

    const NEEDS_PLOTTING = <?= json_encode(array_values($needsPlotting)) ?>;

    async function plotDemand() {
        const select = document.getElementById('needsPlottingSelect');
        const item = NEEDS_PLOTTING[parseInt(select.value, 10)];
        if (!item) return;

        const result = await postJSON('<?= site_url('chairperson/offerings/plot-demand') ?>', {
            academic_year: item.academic_year,
            subject_code: item.subject_code,
            section_id: item.section_id,
        });

        if (!result.ok) {
            showFeedback(result.error || 'Could not create the offering.', true);
            return;
        }

        reloadWithMessage(`${item.subject_code} offering created for ${item.section_label} — plot its schedule below.`);
    }

    async function mergeCandidate(i, academicYear, subjectCode) {
        const checked = Array.from(document.querySelectorAll(`input[name="cand-${i}"]:checked`)).map(el => parseInt(el.value, 10));

        if (checked.length < 2) {
            showFeedback('Select at least two sections to merge.', true);
            return;
        }

        const capEl = document.getElementById('cap-' + i);

        const result = await postJSON('<?= site_url('chairperson/offerings/create') ?>', {
            academic_year: academicYear,
            subject_code: subjectCode,
            section_ids: checked,
            capacity: capEl.value,
        });

        if (!result.ok) {
            showFeedback(result.error || 'Could not create the merge.', true);
            return;
        }

        let msg = `${subjectCode} merged across ${checked.length} sections (roster ${result.roster_count}).`;
        if (result.over_capacity) {
            msg += ' Warning: the combined roster already exceeds the capacity you set.';
        }
        reloadWithMessage(msg);
    }

    async function unmergeSection(offeringId, sectionId, label) {
        if (!await acConfirm(`Remove Section ${label} from this merged class?`)) {
            return;
        }

        const result = await postJSON('<?= site_url('chairperson/offerings/unmerge') ?>', {
            offering_id: offeringId,
            section_id: sectionId,
        });

        if (!result.ok) {
            showFeedback(result.error || 'Could not unmerge.', true);
            return;
        }

        reloadWithMessage(`Section ${label} removed from the merge.`);
    }

    async function addOfferingMeeting(evt, offeringId) {
        evt.preventDefault();
        const form = evt.target;
        const existing = <?= json_encode(array_map(static fn ($o) => [
            'offering_id' => (int) $o['offering_id'],
            'meetings'    => array_map(static fn ($m) => [
                'day' => (int) $m['day'], 'start' => substr($m['start_time'], 0, 5), 'end' => substr($m['end_time'], 0, 5),
                'time_label' => $m['time_label'], 'room' => $m['room'], 'faculty_id' => null,
            ], $o['meetings']),
        ], $offerings)) ?>;
        const current = existing.find(o => o.offering_id === offeringId);
        const blocks = current ? current.meetings.slice() : [];

        const start = form.start.value, end = form.end.value;
        blocks.push({
            day: parseInt(form.day.value, 10),
            start, end,
            time_label: start + '–' + end,
            room: form.room.value,
            faculty_id: null,
        });

        const result = await postJSON('<?= site_url('chairperson/offerings/schedule/sync') ?>', {
            offering_id: offeringId,
            blocks: blocks,
        });

        if (!result.ok) {
            showFeedback(result.error || 'Could not save the schedule.', true);
            return false;
        }

        reloadWithMessage('Schedule saved for this offering.');
        return false;
    }

    const componentMeetingsById = <?php
        $componentMeetingsForJs = [];
        foreach ($offerings as $o) {
            foreach ($o['components'] as $c) {
                $componentMeetingsForJs[(int) $c['id']] = array_map(static fn ($m) => [
                    'day' => (int) $m['day'], 'start' => substr($m['start_time'], 0, 5), 'end' => substr($m['end_time'], 0, 5),
                    'time_label' => $m['time_label'], 'room' => $m['room'], 'faculty_id' => null,
                ], $c['meetings']);
            }
        }
        echo json_encode($componentMeetingsForJs);
    ?>;

    async function addComponentMeeting(evt, componentId) {
        evt.preventDefault();
        const form = evt.target;
        const start = form.start.value, end = form.end.value;

        const blocks = (componentMeetingsById[componentId] || []).slice();
        blocks.push({ day: parseInt(form.day.value, 10), start, end, time_label: start + '–' + end, room: form.room.value, faculty_id: null });

        const result = await postJSON('<?= site_url('chairperson/offerings/components/schedule/sync') ?>', {
            offering_component_id: componentId,
            blocks: blocks,
        });

        if (!result.ok) {
            showFeedback(result.error || 'Could not save the component schedule.', true);
            return false;
        }

        reloadWithMessage('Schedule saved for this component.');
        return false;
    }

    async function addComponentStudent(evt, componentId) {
        evt.preventDefault();
        const form = evt.target;
        const userId = parseInt(form.user_id.value, 10);

        const result = await postJSON('<?= site_url('chairperson/offerings/components/roster/add') ?>', {
            offering_component_id: componentId,
            user_id: userId,
        });

        if (!result.ok) {
            showFeedback(result.error || 'Could not add this student.', true);
            return false;
        }

        reloadWithMessage('Student added to the component roster.');
        return false;
    }

    async function deactivateComponent(componentId) {
        if (!await acConfirm('Deactivate this component? Its history is kept, but it stops counting toward current coverage.')) {
            return;
        }

        const result = await postJSON('<?= site_url('chairperson/offerings/components/deactivate') ?>', {
            offering_component_id: componentId,
        });

        if (!result.ok) {
            showFeedback(result.error || 'Could not deactivate this component.', true);
            return;
        }

        reloadWithMessage('Component deactivated.');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const msg = sessionStorage.getItem('offeringsFeedback');
        if (msg) {
            showFeedback(msg, false);
            sessionStorage.removeItem('offeringsFeedback');
        }
    });
</script>
<?= $this->endSection() ?>

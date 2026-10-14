<?= $this->extend('layouts/admin_console') ?>

<?php
/** Weekly availability and subject preferences retain separate save endpoints. */
$activeAdmin   = 'availability';
$pageTitle     = 'Availability & Preferences';
$brandSub      = 'Faculty Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = [
    '' => [
        'dashboard'    => ['label' => 'Dashboard',    'icon' => 'dashboard',        'url' => 'faculty/dashboard', 'help' => [
            'title' => 'Teaching Loads',
            'text'  => "Your assigned classes and units for the term, plus any loads still unassigned that you could pick up. Offer or request a swap with a colleague here — it only transfers once your chairperson approves it.",
        ]],
        'schedule'     => ['label' => 'Schedule',     'icon' => 'calendar_month',   'url' => 'faculty/schedule', 'help' => [
            'title' => 'My Schedule',
            'text'  => "Your weekly teaching schedule — days, times, rooms, and sections for everything you're currently assigned.",
        ]],
        'availability' => ['label' => 'Availability', 'icon' => 'event_available',  'url' => 'faculty/availability', 'help' => [
            'title' => 'Availability',
            'text'  => "Set the times you're available to teach — what your chairperson sees when assigning you to a class while plotting the schedule.",
        ]],
        'grading'      => ['label' => 'Grading',      'icon' => 'grade',            'url' => 'faculty/grading', 'help' => [
            'title' => 'Grading',
            'text'  => "Enter and submit grades for your classes. Once submitted, a class moves into the chair → dean → registrar approval chain and you can no longer edit it directly — use Correction Requests for that.",
        ]],
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
<link rel="stylesheet" href="<?= base_url('assets/css/pages/faculty-availability.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/faculty-availability.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="faculty-availability" id="facultyAvailability" data-copy-url="<?= site_url('faculty/availability/for-term') ?>" data-term-url="<?= site_url('faculty/availability') ?>">

    <div class="page-head">
        <h1>Set your availability</h1>
        <p>Choose when you can teach and which subjects you prefer.</p>
        <?php if ($term !== null): ?>
            <nav class="availability-sections" aria-label="Availability sections">
                <a href="#weeklyHeading">Weekly availability</a>
                <a href="#preferencesHeading">Subject preferences</a>
            </nav>
        <?php endif ?>
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
        // The system is new — archived (past) terms are just seed/test
        // clutter right now, not real history worth showing. Hide them from
        // the picker, EXCEPT the one currently being viewed (e.g. reached
        // via a direct ?ay_id= link) so the dropdown never silently
        // disagrees with what's actually on screen.
        $allTerms = array_values(array_filter($allTerms, function ($t) use ($termStatus, $term) {
            [$class] = $termStatus($t);

            return $class !== 'is-archived' || ($term && (int) $t['ay_id'] === (int) $term['ay_id']);
        }));
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
            <label for="termSelect">Current term</label>
            <select id="termSelect">
                <?php foreach ($allTerms as $t): ?>
                    <?php [, $optLabel] = $termStatus($t); ?>
                    <option value="<?= (int) $t['ay_id'] ?>" <?= ($term && (int) $term['ay_id'] === (int) $t['ay_id']) ? 'selected' : '' ?>>
                        <?= esc(\App\Models\AcademicYearModel::format($t['label'], $t['semester'])) ?> — <?= esc($optLabel) ?>
                    </option>
                <?php endforeach ?>
            </select>
            <?php if ($term): ?>
                <?php [$tClass, $tLabel] = $termStatus($term); ?>
                <span class="term-pill <?= $tClass ?>"><?= esc($tLabel) ?></span>
            <?php endif ?>

            <?php if ($term && ! empty($otherTerms)): ?>
                <div class="copy-group">
                    <label for="copyTermSelect">Copy from another term</label>
                    <select id="copyTermSelect">
                        <?php foreach ($otherTerms as $t): ?>
                            <?php [, $otLabel] = $termStatus($t); ?>
                            <option value="<?= (int) $t['ay_id'] ?>"><?= esc(\App\Models\AcademicYearModel::format($t['label'], $t['semester'])) ?> — <?= esc($otLabel) ?></option>
                        <?php endforeach ?>
                    </select>
                    <button type="button" class="copy-btn" id="copyTermBtn">Copy</button>
                </div>
            <?php endif ?>
            <p id="copyFeedback" class="copy-feedback" role="status" hidden></p>
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

        <div class="availability-section">
            <div class="panel-head-row">
                <div>
                    <h2 id="weeklyHeading">Weekly availability</h2>
                    <p class="panel-sub" style="margin-bottom:0;">Set your teaching hours for each day. Leave undecided days as “No preference”.</p>
                </div>
                <p class="availability-summary" aria-live="polite"><span id="metricDays">0 available days</span> &middot; <span id="metricHours">0 total hours</span></p>
            </div>

            <div class="tool-strip">
                <div class="preset-group">
                    <span class="lbl">Quick setup</span>
                    <button type="button" class="preset-btn" data-preset="weekdays">Weekdays 8–5</button>
                    <button type="button" class="preset-btn" data-preset="morning">Mornings 8–12</button>
                    <button type="button" class="preset-btn" data-preset="afternoon">Afternoons 1–5</button>
                    <button type="button" class="preset-btn clear" data-preset="clear">Clear schedule</button>
                </div>
            </div>

            <form method="post" action="<?= site_url('faculty/availability/update') ?>" id="availabilityForm" data-no-dirty-tracking aria-labelledby="weeklyHeading">
                <?= csrf_field() ?>
                <input type="hidden" name="ay_id" value="<?= (int) $term['ay_id'] ?>">

                <?php foreach ($dayLabels as $day => $label): ?>
                    <?php $row = $availabilityByDay[$day] ?? null; $fullDay = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$day]; ?>
                    <?php $status = $row ? ($row['is_available'] ? 'available' : 'unavailable') : ''; ?>
                    <div class="avail-row <?= $status === 'available' ? 'is-available' : ($status === 'unavailable' ? 'is-unavailable' : '') ?>" id="row_<?= $day ?>" data-day="<?= $day ?>">
                        <div class="avail-day-group">
                            <label class="avail-day" for="status_<?= $day ?>"><?= esc($fullDay) ?></label>
                            <div class="avail-day-select">
                                <select name="day_<?= $day ?>_status" class="day-status" id="status_<?= $day ?>" data-day="<?= $day ?>">
                                    <option value="" <?= $status === '' ? 'selected' : '' ?>>No preference</option>
                                    <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option>
                                    <option value="unavailable" <?= $status === 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
                                </select>
                            </div>
                        </div>
                        <div class="avail-time-group">
                            <input type="time" aria-label="<?= esc($fullDay) ?> start time" name="day_<?= $day ?>_start" id="start_<?= $day ?>" value="<?= esc($row['start_time'] ?? '') ?>">
                            <span class="to" aria-hidden="true">&rarr;</span>
                            <input type="time" aria-label="<?= esc($fullDay) ?> end time" name="day_<?= $day ?>_end" id="end_<?= $day ?>" value="<?= esc($row['end_time'] ?? '') ?>">
                            <span class="avail-hrs-badge" id="hrs_<?= $day ?>">0.0 hrs</span>
                            <?php if ($day < 5): ?>
                                <button type="button" class="avail-copy-btn" data-day="<?= $day ?>" title="Copy this time range to all weekdays" aria-label="Copy <?= esc($fullDay) ?> times to all weekdays">
                                    <span class="material-symbols-outlined">content_copy</span>
                                </button>
                            <?php endif ?>
                        </div>
                        <details class="avail-notes" <?= !empty($row['notes']) ? 'open' : '' ?>>
                            <summary><?= !empty($row['notes']) ? 'Note for chairperson' : 'Add note' ?></summary>
                            <input type="text" name="day_<?= $day ?>_notes" id="notes_<?= $day ?>" maxlength="150" aria-label="<?= esc($fullDay) ?> note for chairperson" placeholder="e.g. Available for laboratory classes only" value="<?= esc($row['notes'] ?? '') ?>">
                        </details>
                    </div>
                <?php endforeach ?>

                <div class="heatmap-wrap">
                    <div class="heatmap-head">
                        <div>
                            <h3>Weekly timeline</h3>
                            <p>A preview of the hours currently entered.</p>
                        </div>
                    </div>
                    <div class="heatmap" id="heatmap" role="img" aria-label="Weekly availability timeline"></div>
                </div>

                <div class="save-row">
                    <span class="save-status" id="availabilitySaveStatus" role="status">No unsaved changes</span>
                    <button type="button" class="btn btn-secondary" id="discardBtn" disabled>Discard</button>
                    <button type="submit" class="btn btn-primary" id="saveAvailability" disabled>Save availability</button>
                </div>
            </form>
        </div>

        <?php
            // Filter counts reflect the saved subject preferences.
            $levelCounts = ['preferred' => 0, 'willing' => 0, 'qualified' => 0, '' => 0];
            foreach ($subjects as $s) {
                $lvl = $preferencesById[$s['subject_id']] ?? '';
                $levelCounts[$lvl]++;
            }
        ?>

        <div class="availability-section">
            <h2 id="preferencesHeading">Subject preferences</h2>
            <p class="panel-sub">Choose the subjects you prefer, are willing to teach, or are qualified to teach.</p>

            <?php if (empty($subjects)): ?>
                <p class="panel-sub" style="margin-bottom:0;">No subjects found for your department yet.</p>
            <?php else: ?>
                <div class="pref-toolbar">
                    <div class="pref-search">
                        <span class="material-symbols-outlined">search</span>
                        <input type="search" id="subjSearch" aria-label="Search subjects by code or title" placeholder="Filter by subject code or title…">
                    </div>
                    <div class="pref-tabs" id="prefTabs" role="group" aria-label="Filter by preference">
                        <button type="button" class="pref-tab active" data-level="">All (<?= count($subjects) ?>)</button>
                        <button type="button" class="pref-tab" data-level="preferred">Preferred (<?= $levelCounts['preferred'] ?>)</button>
                        <button type="button" class="pref-tab" data-level="willing">Willing (<?= $levelCounts['willing'] ?>)</button>
                        <button type="button" class="pref-tab" data-level="qualified">Qualified (<?= $levelCounts['qualified'] ?>)</button>
                        <button type="button" class="pref-tab" data-level="unset">Not set (<?= $levelCounts[''] ?>)</button>
                    </div>
                </div>

                <form method="post" action="<?= site_url('faculty/preferences/update') ?>" id="preferencesForm" data-no-dirty-tracking aria-labelledby="preferencesHeading">
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
                                    <span id="subject_<?= (int) $subject['subject_id'] ?>" class="subj-title"><?= esc($subject['subject_name']) ?></span>
                                    <?php if (! empty($subject['description'])): ?>
                                        <span class="subj-desc"><?= esc($subject['description']) ?></span>
                                    <?php endif ?>
                                </div>
                                <div class="pref-pills" role="group" aria-labelledby="subject_<?= (int) $subject['subject_id'] ?>" data-subject="<?= $subject['subject_id'] ?>">
                                    <input type="hidden" name="preference[<?= $subject['subject_id'] ?>]" class="pref-input" value="<?= esc($current) ?>">
                                    <?php foreach ($preferenceLevels as $level): ?>
                                        <button type="button" class="pref-pill <?= $current === $level ? 'active' : '' ?>" data-level="<?= $level ?>" aria-pressed="<?= $current === $level ? 'true' : 'false' ?>"><?= esc(ucfirst($level)) ?></button>
                                    <?php endforeach ?>
                                    <button type="button" class="pref-pill-reset" title="Reset to not set" aria-label="Reset <?= esc($subject['subject_code']) ?> to not set">
                                        <span class="material-symbols-outlined">close</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>

                    <p class="panel-sub" id="subjEmpty" style="display:none; text-align:center; margin:20px 0 0;">No subjects match your filter.</p>

                    <div class="save-row">
                        <span class="save-status" id="preferencesSaveStatus" role="status">No unsaved changes</span>
                        <button type="button" class="btn btn-secondary" id="discardPreferences" disabled>Discard</button>
                        <button type="submit" class="btn btn-primary" id="savePreferences" disabled>Save preferences</button>
                    </div>
                </form>
            <?php endif ?>
        </div>

    <?php endif ?>

</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/faculty-availability.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/faculty-availability.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>
<?php
$activeAdmin = 'schedule';
$pageTitle   = 'My Schedule';
$brandSub    = 'Faculty Portal';
$userName    = $user['name'];
$userRoleLabel = $user['role'];
$navGroups = ['' => [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'dashboard', 'url' => 'faculty/dashboard', 'help' => [
        'title' => 'Teaching Loads',
        'text'  => "Your assigned classes and units for the term, plus any loads still unassigned that you could pick up. Offer or request a swap with a colleague here — it only transfers once your chairperson approves it.",
    ]],
    'schedule' => ['label' => 'Schedule', 'icon' => 'calendar_month', 'url' => 'faculty/schedule', 'help' => [
        'title' => 'My Schedule',
        'text'  => "Your weekly teaching schedule — days, times, rooms, and sections for everything you're currently assigned.",
    ]],
    'availability' => ['label' => 'Availability', 'icon' => 'event_available', 'url' => 'faculty/availability', 'help' => [
        'title' => 'Availability',
        'text'  => "Set the times you're available to teach — what your chairperson sees when assigning you to a class while plotting the schedule.",
    ]],
    'grading' => ['label' => 'Grading', 'icon' => 'grade', 'url' => 'faculty/grading', 'help' => [
        'title' => 'Grading',
        'text'  => "Enter and submit grades for your classes. Once submitted, a class moves into the chair → dean → registrar approval chain and you can no longer edit it directly — use Correction Requests for that.",
    ]],
]];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

$dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
// Same plotting-theme → accent mapping the student schedule uses (and
// TeachingLoads::ACCENTS plots with), so a class reads as the same color
// here as it does everywhere else it's shown — not an independently
// invented palette.
$accents = ['blue' => 'blue', 'purple' => 'purple', 'cyan' => 'blue', 'red' => 'red', 'green' => 'green', 'yellow' => 'gold'];
$scheduleData = array_map(static fn (array $m) => [
    'day' => (int) $m['day'], 'code' => $m['subject_code'], 'title' => $m['subject_title'],
    'start' => substr($m['start_time'], 0, 5), 'end' => substr($m['end_time'], 0, 5),
    'room' => $m['room'] ?? '', 'faculty' => $m['prof'] ?? '',
    'term' => $m['academic_year'], 'program' => $m['program'],
    'year' => $m['year_level'], 'section' => $m['section'],
    'accent' => $accents[$m['theme'] ?? 'blue'] ?? 'blue',
], $meetings);
?>
<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pages/student-schedule.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/student-schedule.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="ss-page" id="studentSchedule">
    <header class="ss-heading"><div><span class="ss-eyebrow">TEACHING SCHEDULE</span><h1>My Schedule</h1><p>Review your weekly teaching schedule, meeting times, rooms, and sections.</p></div><div class="ss-term" id="ssTermField" hidden><span>Academic term</span><label class="ss-sr-only" for="ssTerm">Academic term</label><select id="ssTerm"></select></div></header>
    <?php if ($meetings === []): ?>
        <section class="ss-card ss-empty"><span class="material-symbols-outlined" aria-hidden="true">calendar_today</span><h2>Your week is taking shape</h2><p><?= $activeYear ? 'No classes have been assigned to you yet. Your timetable will appear here once your chairperson plots your schedule.' : 'An active academic term needs to be configured before classes can be scheduled.' ?></p></section>
    <?php else: ?>
        <section class="ss-overview" id="ssOverview" aria-label="Weekly overview" hidden>
            <div class="ss-overview-intro"><div><span>Selected schedule</span><strong>Weekly overview</strong></div></div>
            <div class="ss-stat"><strong id="ssSubjectCount"></strong><span>Subjects</span></div><div class="ss-stat"><strong id="ssHours"></strong><span>Hours per week</span></div><div class="ss-stat"><strong id="ssDays"></strong><span>Class days</span></div>
        </section>
        <section class="ss-card ss-workspace" aria-labelledby="ssBoardTitle">
            <header class="ss-board-heading"><div><h2 id="ssBoardTitle">Weekly timetable</h2><p>Select a class to view its full schedule and details.</p></div><div class="ss-board-actions"><div class="ss-view-toggle" id="ssViewToggle" role="group" aria-label="Schedule view" hidden><button type="button" data-view="calendar" aria-pressed="true">Week</button><button type="button" data-view="agenda" aria-pressed="false">Agenda</button></div><button type="button" class="ss-button" id="ssPrint" title="Print the complete schedule for the selected term" hidden><span class="material-symbols-outlined" aria-hidden="true">print</span>Print</button></div></header>
            <div class="ss-toolbar" id="ssToolbar" hidden>
                <div class="ss-day-filters" id="ssDayFilters" role="group" aria-label="Filter by day"><button type="button" data-day="all" aria-pressed="true">All days</button><?php foreach ($dayNames as $i => $name): ?><button type="button" data-day="<?= $i ?>" aria-pressed="false"><?= esc(substr($name, 0, 3)) ?></button><?php endforeach ?></div>
                <div class="ss-subject-filter"><label class="ss-sr-only" for="ssSubject">Filter by subject</label><select id="ssSubject"><option value="all">All subjects</option></select></div>
            </div>
            <div class="ss-board-body" id="ssBoardBody">
            <div class="ss-calendar-scroll" id="ssCalendar" role="region" aria-label="Weekly class calendar" tabindex="0" hidden></div>
            <div class="ss-agenda" id="ssAgenda">
                <?php foreach ($dayNames as $day => $label): ?>
                    <?php $dayMeetings = array_values(array_filter($meetings, static fn ($m) => (int) $m['day'] === $day)); if ($dayMeetings === []) continue; ?>
                    <h3><?= esc($label) ?></h3>
                    <?php foreach ($dayMeetings as $m): ?><article class="ss-fallback"><strong><?= esc($m['subject_code']) ?> &middot; <?= esc($m['subject_title']) ?></strong><p><?= esc($m['time_label']) ?> &middot; <?= esc($m['room'] ?: 'Room to be assigned') ?> &middot; <?= esc($m['section']) ?></p><small><?= esc($m['academic_year']) ?> &middot; <?= esc($m['program']) ?> &middot; <?= esc($m['year_level']) ?></small></article><?php endforeach ?>
                <?php endforeach ?>
            </div>
            <div class="ss-empty" id="ssFilteredEmpty" hidden><span class="material-symbols-outlined" aria-hidden="true">event_available</span><h3>No classes in this view</h3><p>Choose another day or subject to see more of your week.</p><button type="button" class="ss-button" id="ssReset">Show all classes</button></div>
            </div>
            <footer class="ss-board-footer" id="ssFooter" hidden><span id="ssResultCount" role="status" aria-live="polite"></span><span>Select any class for full details</span></footer>
            <noscript><p class="ss-noscript">Enable JavaScript to use the interactive calendar and class details.</p></noscript>
        </section>
        <dialog class="ss-dialog" id="ssClassDialog" aria-labelledby="ssDetailTitle" aria-describedby="ssDetailSubtitle">
            <header class="ss-dialog-header"><span class="ss-eyebrow">CLASS DETAILS</span><button type="button" class="ss-icon-button" id="ssCloseDetails" aria-label="Close class details"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></header>
            <div class="ss-dialog-body"><span class="ss-detail-code" id="ssDetailCode"></span><h2 id="ssDetailTitle"></h2><p id="ssDetailSubtitle"></p>
                <div class="ss-selected-meeting"><span class="material-symbols-outlined" aria-hidden="true">event</span><div><strong id="ssDetailDay"></strong><span id="ssDetailTime"></span></div><span id="ssDetailDuration"></span></div>
                <dl class="ss-detail-facts" id="ssDetailFacts"></dl>
                <div class="ss-meetings-heading"><h3>Weekly meetings</h3><span id="ssDetailHours"></span></div><div class="ss-detail-meetings" id="ssDetailMeetings"></div>
                <p class="ss-detail-note">Meeting times follow your academic timetable.</p>
            </div>
            <footer class="ss-dialog-footer"><button type="button" class="ss-button ss-button--primary" id="ssShowSubject"><span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>Show this class in my schedule</button></footer>
        </dialog>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script type="application/json" id="ssConfig"><?= json_encode(['meetings' => $scheduleData, 'portalLabel' => 'Faculty Portal', 'showInstructor' => false], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= base_url('assets/js/student-schedule.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/student-schedule.js') ?>"></script>
<?= $this->endSection() ?>

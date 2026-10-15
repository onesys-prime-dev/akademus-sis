<?= $this->extend('layouts/admin_console') ?>
<?php
$activeAdmin = 'schedule';
$pageTitle = 'My Schedule';
$brandSub = 'Student Portal';
$userName = auth()->user()->username;
$userRoleLabel = 'Student';
$navGroups = [
    '' => [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'home', 'url' => 'student/dashboard', 'help' => [
            'title' => 'Student Dashboard',
            'text'  => "Your at-a-glance view: today's schedule, upcoming classes, and recent grade/tuition activity. Use the sidebar to get to any other part of your student portal.",
        ]],
        'tuition' => ['label' => 'Tuition & Fees', 'icon' => 'payments', 'url' => 'student/tuition', 'help' => [
            'title' => 'Tuition & Fees',
            'text'  => "Your current assessment, payment history, and balance. Upload proof of an off-site payment here for Finance to review, or see your installment schedule.",
        ]],
        'schedule' => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule', 'help' => [
            'title' => 'My Schedule',
            'text'  => "Your weekly class schedule for the current term — days, times, rooms, and instructors for everything you're enrolled in.",
        ]],
        'grades' => ['label' => 'My Grades', 'icon' => 'grade', 'url' => 'student/grades', 'help' => [
            'title' => 'My Grades',
            'text'  => "Your official grades, term by term. A grade only appears here once it's been locked by the registrar.",
        ]],
        'enlistment' => ['label' => 'Enlistment', 'icon' => 'playlist_add_check', 'url' => 'student/enlistment', 'help' => [
            'title' => 'Enlistment',
            'text'  => "Build your subject cart for next term and submit it for chair → dean → registrar approval. Only available while your department's enlistment window is open; otherwise you'll enroll directly once sections open.",
        ]],
        'documents' => ['label' => 'Documents',   'icon' => 'description',        'url' => 'student/documents', 'help' => [
            'title' => 'Documents',
            'text'  => "Request a Certificate of Registration (needs at least a downpayment) or Certificate of Enrollment (available once you're enrolled, balance or not), and track their status.",
        ]],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
$dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
// Same plotting-theme → accent mapping TeachingLoads::ACCENTS uses, so a
// class reads as the same color here as it does on the faculty's teaching
// load board — not an independently-invented palette.
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
    <header class="ss-heading"><div><span class="ss-eyebrow">ACADEMIC SCHEDULE</span><h1>My Schedule</h1><p>Review your weekly classes, meeting times, rooms, and instructors.</p></div><div class="ss-term" id="ssTermField" hidden><span>Academic term</span><label class="ss-sr-only" for="ssTerm">Academic term</label><select id="ssTerm"></select></div></header>
    <?php if (session()->has('message')): ?><div class="ss-notice" role="status"><?= esc(session('message')) ?></div><?php endif ?>
    <?php if (session()->has('errors')): ?><div class="ss-notice ss-notice--error" role="alert"><?php foreach ((array) session('errors') as $error): ?><p><?= esc($error) ?></p><?php endforeach ?></div><?php endif ?>
    <?php if ($meetings === []): ?>
        <section class="ss-card ss-empty"><span class="material-symbols-outlined" aria-hidden="true">calendar_today</span><h2>Your week is taking shape</h2><p>No class meetings are scheduled yet. Your timetable will appear here once your enrolled classes have a schedule.</p></section>
    <?php else: ?>
        <section class="ss-overview" id="ssOverview" aria-label="Weekly overview" hidden>
            <div class="ss-overview-intro"><div><span>Selected schedule</span><strong>Weekly overview</strong></div></div>
            <div class="ss-stat"><strong id="ssSubjectCount"></strong><span>Subjects</span></div><div class="ss-stat"><strong id="ssHours"></strong><span>Hours per week</span></div><div class="ss-stat"><strong id="ssDays"></strong><span>Class days</span></div>
        </section>
        <section class="ss-card ss-workspace" aria-labelledby="ssBoardTitle">
            <header class="ss-board-heading"><div><h2 id="ssBoardTitle">Weekly timetable</h2><p>Select a class to view its full schedule and details.</p></div><div class="ss-board-actions"><div class="ss-view-toggle" id="ssViewToggle" role="group" aria-label="Schedule view" hidden><button type="button" data-view="calendar" aria-pressed="true">Week</button><button type="button" data-view="agenda" aria-pressed="false">Agenda</button></div><button type="button" class="ss-button" id="ssPrint" title="Print the complete schedule for the selected term" hidden><span class="material-symbols-outlined" aria-hidden="true">print</span>Print</button></div></header>
            <div class="ss-toolbar" id="ssToolbar" hidden>
                <div class="ss-day-filters" id="ssDayFilters" role="group" aria-label="Filter by day"></div>
                <div class="ss-subject-filter"><label class="ss-sr-only" for="ssSubject">Filter by subject</label><select id="ssSubject"><option value="all">All subjects</option></select></div>
            </div>
            <div class="ss-calendar-scroll" id="ssCalendar" role="region" aria-label="Weekly class calendar" tabindex="0" hidden></div>
            <div class="ss-agenda" id="ssAgenda">
                <?php foreach ($dayNames as $day => $label): ?>
                    <?php $dayMeetings = array_values(array_filter($meetings, static fn ($m) => (int) $m['day'] === $day)); if ($dayMeetings === []) continue; ?>
                    <h3><?= esc($label) ?></h3>
                    <?php foreach ($dayMeetings as $m): ?><article class="ss-fallback"><strong><?= esc($m['subject_code']) ?> &middot; <?= esc($m['subject_title']) ?></strong><p><?= esc($m['time_label']) ?> &middot; <?= esc($m['room'] ?: 'Room to be assigned') ?> &middot; <?= esc($m['prof'] ?: 'Instructor to be assigned') ?></p><small><?= esc($m['academic_year']) ?> &middot; <?= esc($m['section']) ?></small></article><?php endforeach ?>
                <?php endforeach ?>
            </div>
            <div class="ss-empty" id="ssFilteredEmpty" hidden><span class="material-symbols-outlined" aria-hidden="true">event_available</span><h3>No classes in this view</h3><p>Choose another day or subject to see more of your week.</p><button type="button" class="ss-button" id="ssReset">Show all classes</button></div>
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
    <?php if (!empty($enrolled)): ?>
        <details class="ss-card ss-enrollments"><summary><span class="material-symbols-outlined" aria-hidden="true">school</span><span>My enrolled subjects<small>Enrollment information and subject management</small></span><span class="ss-enrollment-count"><?= count($enrolled) ?></span><span class="material-symbols-outlined" aria-hidden="true">expand_more</span></summary>
            <div class="ss-enrollment-body"><p>You can drop a subject in the active term until a grade is recorded for it.</p><div class="ss-table-scroll" role="region" aria-label="Enrolled subjects" tabindex="0"><table class="ss-table"><thead><tr><th scope="col">Subject</th><th scope="col">Section</th><th scope="col">Term</th><th scope="col"><span class="ss-sr-only">Actions</span></th></tr></thead><tbody>
            <?php foreach ($enrolled as $e): ?><tr><td><strong><?= esc($e['subject_code']) ?></strong></td><td><?= esc($e['program']) ?> &middot; <?= esc($e['year_level']) ?> &middot; <?= esc($e['section']) ?></td><td><?= esc($e['academic_year']) ?></td><td>
                <?php if ($e['can_drop']): ?><form method="post" action="<?= site_url('student/enrollments/drop') ?>" data-drop-code="<?= esc($e['subject_code'], 'attr') ?>"><?= csrf_field() ?><input type="hidden" name="enrollment_id" value="<?= (int) $e['enrollment_id'] ?>"><button type="submit" class="ss-button ss-button--drop">Drop subject</button></form>
                <?php elseif ((int) $e['grade_count'] > 0): ?><span class="ss-enrollment-status">Graded</span><?php else: ?><span class="ss-enrollment-status">Past term</span><?php endif ?>
            </td></tr><?php endforeach ?>
            </tbody></table></div></div>
        </details>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script type="application/json" id="ssConfig"><?= json_encode(['meetings' => $scheduleData], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= base_url('assets/js/student-schedule.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/student-schedule.js') ?>"></script>
<?= $this->endSection() ?>

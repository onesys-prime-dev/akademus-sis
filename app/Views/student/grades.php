<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'grades';
$pageTitle     = 'My Grades';
$brandSub      = 'Student Portal';
$userName      = auth()->user()->username;
$userRoleLabel = 'Student';
$navGroups     = [
    '' => [
        'dashboard' => ['label' => 'Dashboard',   'icon' => 'home',           'url' => 'student/dashboard', 'help' => [
            'title' => 'Student Dashboard',
            'text'  => "Your at-a-glance view: today's schedule, upcoming classes, and recent grade/tuition activity. Use the sidebar to get to any other part of your student portal.",
        ]],
        'tuition'   => ['label' => 'Tuition & Fees', 'icon' => 'payments',    'url' => 'student/tuition', 'help' => [
            'title' => 'Tuition & Fees',
            'text'  => "Your current assessment, payment history, and balance. Upload proof of an off-site payment here for Finance to review, or see your installment schedule.",
        ]],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule', 'help' => [
            'title' => 'My Schedule',
            'text'  => "Your weekly class schedule for the current term — days, times, rooms, and instructors for everything you're enrolled in.",
        ]],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades', 'help' => [
            'title' => 'My Grades',
            'text'  => "Your official grades, term by term. A grade only appears here once it's been locked by the registrar.",
        ]],
        'enlistment' => ['label' => 'Enlistment', 'icon' => 'playlist_add_check', 'url' => 'student/enlistment', 'help' => [
            'title' => 'Enlistment',
            'text'  => "Select your subjects for next term and submit them for approval. Only available while your department's enlistment window is open; otherwise you'll enroll directly once sections open.",
        ]],
        'documents' => ['label' => 'Documents',   'icon' => 'description',        'url' => 'student/documents', 'help' => [
            'title' => 'Documents',
            'text'  => "Request a Certificate of Registration (needs at least a downpayment) or Certificate of Enrollment (available once you're enrolled, balance or not), and track their status.",
        ]],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

// Newest term first, matching byYear's own ordering.
$termOrder = array_keys($byYear);
$years     = [];
foreach ($termOrder as $term) {
    $years[$terms[$term]['year']] = true;
}
$years = array_keys($years);

$gradesData = [];
foreach ($byYear as $term => $rows) {
    $gradesData[] = [
        'term'     => $term,
        'year'     => $terms[$term]['year'],
        'semester' => $terms[$term]['semester'],
        'gwa'      => $gwaByTerm[$term],
        'rows'     => array_map(static fn (array $g) => [
            'code'       => $g['subject_code'],
            'title'      => $g['subject_name'] ?? $g['subject_code'],
            'units'      => $g['units'],
            'schedule'   => $g['schedule_label'],
            'program'    => $g['program'],
            'year_level' => $g['year_level'],
            'section'    => $g['section'],
            'instructor' => $g['instructor'] ?: 'TBA',
            'grade'      => $g['grade_value'],
            'remarks'    => $g['remarks'],
            'is_passing' => $g['is_passing'],
        ], $rows),
    ];
}
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pages/student-grades.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/student-grades.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="sg-page" id="studentGrades">
    <header class="sg-hero">
        <div class="sg-hero__content">
            <div>
                <span class="sg-eyebrow">ACADEMIC RECORD</span>
                <h1>My Grades</h1>
                <p>Review your official grades, class details, and weighted average by term.</p>
            </div>
        </div>
        <span class="sg-official">Official student record</span>
    </header>

    <?php if (empty($byYear)): ?>
        <section class="sg-card sg-empty">
            <span class="material-symbols-outlined" aria-hidden="true">grade</span>
            <h2>No grades posted yet</h2>
            <p>Your official grades will appear here once your instructors submit them and your chairperson locks them in.</p>
        </section>
    <?php else: ?>
        <section class="sg-card sg-filters">
            <div class="sg-filters__intro">
                <div><strong>View academic term</strong><span>Choose a school year and semester.</span></div>
            </div>
            <div class="sg-filters__controls">
                <div class="sg-filter">
                    <label for="sgYear">School Year</label>
                    <select id="sgYear"></select>
                </div>
                <div class="sg-filter">
                    <label for="sgSemester">Semester</label>
                    <select id="sgSemester"></select>
                </div>
            </div>
        </section>

        <section class="sg-card sg-table-card">
            <header class="sg-table-head">
                <div>
                    <span class="sg-section-label">Selected term</span>
                    <h2 id="sgTermTitle">Official grades</h2>
                    <p>Locked grades submitted for your enrolled classes.</p>
                </div>
                <div class="sg-table-metrics" aria-live="polite">
                    <span><strong id="sgCourseCount">0</strong> courses</span>
                    <span><strong id="sgUnitCount">0</strong> units</span>
                </div>
            </header>
            <div class="sg-table-scroll" role="region" aria-label="Grades for the selected term" tabindex="0">
                <table class="sg-table">
                    <thead>
                        <tr>
                            <th scope="col">Course</th>
                            <th scope="col">Class Details</th>
                            <th scope="col">Enrollment</th>
                            <th scope="col">Units</th>
                            <th scope="col">Final Grade</th>
                            <th scope="col">Result</th>
                        </tr>
                    </thead>
                    <tbody id="sgTableBody"></tbody>
                </table>
            </div>
            <div class="sg-empty-term" id="sgEmptyTerm" hidden>
                <span class="material-symbols-outlined" aria-hidden="true">search_off</span>
                <p>No grades posted for this term.</p>
            </div>
        </section>

        <section class="sg-gwa" id="sgGwa">
            <div class="sg-gwa__copy">
                <span>General Weighted Average</span>
                <small id="sgGwaMeta">Based on posted numeric grades for this term</small>
            </div>
            <strong id="sgGwaValue">&mdash;</strong>
        </section>

        <?php $markings = [
            'I'      => 'Incomplete',
            'IP'     => 'In Progress',
            'FW'     => 'Unauthorized Withdrawal',
            'NC'     => 'No Credit',
            'W'      => 'Withdraw',
            'D'      => 'Dropped',
            'FA'     => 'Failure Due Absences',
            'Pass'   => 'Pass',
            'Failed' => 'Failed',
            'CE'     => 'Currently Enrolled',
        ]; ?>
        <details class="sg-card sg-legend">
            <summary>
                <span class="material-symbols-outlined" aria-hidden="true">menu_book</span>
                <span>Grade markings reference<small>What each code on this page means</small></span>
                <span class="material-symbols-outlined sg-legend__chevron" aria-hidden="true">expand_more</span>
            </summary>
            <div class="sg-legend-body">
                <dl>
                    <?php foreach ($markings as $code => $label): ?>
                        <div><dt><?= esc($code) ?></dt><dd><?= esc($label) ?></dd></div>
                    <?php endforeach ?>
                </dl>
            </div>
        </details>
    <?php endif ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script type="application/json" id="sgConfig"><?= json_encode(['terms' => $gradesData], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= base_url('assets/js/student-grades.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/student-grades.js') ?>"></script>
<?= $this->endSection() ?>

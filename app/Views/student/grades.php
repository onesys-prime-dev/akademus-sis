<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'grades';
$pageTitle     = 'My Grades';
$brandSub      = 'Student Portal';
$userName      = auth()->user()->username;
$userRoleLabel = 'Student';
$navGroups     = [
    '' => [
        'dashboard' => ['label' => 'Dashboard',   'icon' => 'home',           'url' => 'student/dashboard'],
        'profile'   => ['label' => 'Profile',     'icon' => 'person',         'url' => 'student/profile'],
        'tuition'   => ['label' => 'Tuition & Fees', 'icon' => 'payments',    'url' => 'student/tuition'],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule'],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades'],
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
    <header class="sg-heading">
        <div>
            <span class="sg-eyebrow">ACADEMIC RECORD</span>
            <h1>My Grades</h1>
            <p>View your official grades for a specific school year and semester.</p>
        </div>
    </header>

    <?php if (empty($byYear)): ?>
        <section class="sg-card sg-empty">
            <span class="material-symbols-outlined" aria-hidden="true">grade</span>
            <h2>No grades posted yet</h2>
            <p>Your official grades will appear here once your instructors submit them and your chairperson locks them in.</p>
        </section>
    <?php else: ?>
        <section class="sg-card sg-filters">
            <div class="sg-filter">
                <label for="sgYear">School Year</label>
                <select id="sgYear"></select>
            </div>
            <div class="sg-filter">
                <label for="sgSemester">Semester</label>
                <select id="sgSemester"></select>
            </div>
        </section>

        <section class="sg-card sg-table-card">
            <div class="sg-table-scroll" role="region" aria-label="Grades for the selected term" tabindex="0">
                <table class="sg-table">
                    <thead>
                        <tr>
                            <th scope="col">Course Code</th>
                            <th scope="col">Descriptive Title</th>
                            <th scope="col">Unit</th>
                            <th scope="col">Schedule</th>
                            <th scope="col">Instructor</th>
                            <th scope="col">Program</th>
                            <th scope="col">Yr.</th>
                            <th scope="col">Section</th>
                            <th scope="col">Final Grade</th>
                            <th scope="col">Final Grade Remarks</th>
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
            <span>General Weighted Average</span>
            <strong id="sgGwaValue">&mdash;</strong>
        </section>

        <section class="sg-card sg-legend">
            <h3>Student Markings</h3>
            <dl>
                <div><dt>I</dt><dd>Incomplete</dd></div>
                <div><dt>IP</dt><dd>In Progress</dd></div>
                <div><dt>FW</dt><dd>Unauthorized Withdrawal</dd></div>
                <div><dt>NC</dt><dd>No Credit</dd></div>
                <div><dt>W</dt><dd>Withdraw</dd></div>
                <div><dt>D</dt><dd>Dropped</dd></div>
                <div><dt>FA</dt><dd>Failure Due Absences</dd></div>
                <div><dt>Pass</dt><dd>Pass</dd></div>
                <div><dt>Failed</dt><dd>Failed</dd></div>
                <div><dt>CE</dt><dd>Currently Enrolled</dd></div>
            </dl>
        </section>
    <?php endif ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script type="application/json" id="sgConfig"><?= json_encode(['terms' => $gradesData], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= base_url('assets/js/student-grades.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/student-grades.js') ?>"></script>
<?= $this->endSection() ?>

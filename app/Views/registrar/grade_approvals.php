<?= $this->extend('layouts/admin_console') ?>

<?php
$this->setData(['pageTitle' => 'Grade Approvals'] + \App\Libraries\RegistrarPortal::shell('grade-approvals'));

/** Builds the review-link query string shared by every row below. */
$classLink = static fn (array $c): string => site_url('registrar/grade-approvals/class') . '?' . http_build_query(array_intersect_key($c, array_flip(['academic_year', 'program', 'year_level', 'section', 'subject_code'])));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/grade-approvals.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/grade-approvals.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="ga-page" id="gradeApprovals">
<header class="ga-heading"><div><span class="ga-eyebrow">ACADEMIC RECORDS</span><h1>Grade Approvals</h1><p>Final approval and official locking — institution-wide.</p></div></header>
<div id="gaFeedback" class="ga-feedback" role="status" aria-live="polite" hidden></div>
<section class="ga-card ga-queue-card">
    <div class="ga-tabs" role="tablist" aria-label="Approval queues" id="gaTabs" hidden>
        <button type="button" role="tab" id="gaSubmissionsTab" aria-controls="gaSubmissions" aria-selected="true" data-tab="gaSubmissions">Awaiting approval <span class="ga-count"><?= count($classes) ?></span></button>
        <button type="button" role="tab" id="gaApprovedTab" aria-controls="gaApproved" aria-selected="false" tabindex="-1" data-tab="gaApproved">Approved <span class="ga-count"><?= count($approvedClasses) ?></span></button>
        <button type="button" role="tab" id="gaLockedTab" aria-controls="gaLocked" aria-selected="false" tabindex="-1" data-tab="gaLocked">Locked <span class="ga-count"><?= count($lockedClasses) ?></span></button>
    </div>
    <section id="gaSubmissions" aria-labelledby="gaSubmissionsTitle" data-noun-singular="class" data-noun-plural="classes" data-count-suffix=" awaiting approval">
        <header class="ga-toolbar"><div><h2 id="gaSubmissionsTitle">Awaiting approval</h2><p>Noted by the dean. Open a class to approve and lock it.</p></div><label class="ga-search" hidden data-search-control><span class="material-symbols-outlined" aria-hidden="true">search</span><span class="ga-sr-only">Search classes</span><input type="search" data-search="gaSubmissions" placeholder="Search subject, section or term"></label></header>
        <?php if ($classes !== []): ?>
        <div class="ga-table-scroll"><table class="ga-table"><caption class="ga-sr-only">Classes awaiting approval</caption><thead><tr><th scope="col">Class</th><th scope="col">Academic term</th><th scope="col">Students</th><th scope="col">Status</th><th scope="col"><span class="ga-sr-only">Review</span></th></tr></thead><tbody>
        <?php foreach ($classes as $c): ?>
            <tr data-search-row><td data-label="Class" class="ga-class-cell"><strong><?= esc($c['subject_code']) ?></strong><span><?= esc($c['year_level']) ?> &middot; Section <?= esc($c['section']) ?></span><small><?= esc($c['program']) ?></small></td><td data-label="Academic term" class="ga-term-cell"><?= esc($c['academic_year']) ?></td><td data-label="Students" class="ga-number"><?= (int) $c['student_count'] ?></td><td data-label="Status"><span class="ga-status">Noted</span></td><td class="ga-row-action"><a class="ga-button ga-button--primary" href="<?= esc($classLink($c), 'attr') ?>">Review grades<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a></td></tr>
        <?php endforeach ?>
        </tbody></table></div>
        <?php else: ?><div class="ga-empty"><span class="material-symbols-outlined" aria-hidden="true">task_alt</span><div><h3>Nothing awaiting approval</h3><p>Classes the dean notes will appear here.</p></div></div><?php endif ?>
        <div class="ga-empty" data-no-results hidden><span class="material-symbols-outlined" aria-hidden="true">search_off</span><div><h3>No matching classes</h3><p>Try another subject, section, or academic term.</p></div></div>
        <footer class="ga-footer"><span data-result-count role="status" aria-live="polite"><?= count($classes) ?> <?= count($classes) === 1 ? 'class' : 'classes' ?> awaiting approval</span></footer>
    </section>
    <section id="gaApproved" aria-labelledby="gaApprovedTitle" hidden data-noun-singular="class" data-noun-plural="classes" data-count-suffix=" approved">
        <header class="ga-toolbar"><div><h2 id="gaApprovedTitle">Approved — ready to lock</h2><p>Open a class to lock it and make its grades official.</p></div><label class="ga-search" hidden data-search-control><span class="material-symbols-outlined" aria-hidden="true">search</span><span class="ga-sr-only">Search classes</span><input type="search" data-search="gaApproved" placeholder="Search subject, section or term"></label></header>
        <?php if ($approvedClasses !== []): ?>
        <div class="ga-table-scroll"><table class="ga-table"><caption class="ga-sr-only">Approved classes</caption><thead><tr><th scope="col">Class</th><th scope="col">Academic term</th><th scope="col">Students</th><th scope="col">Status</th><th scope="col"><span class="ga-sr-only">Review</span></th></tr></thead><tbody>
        <?php foreach ($approvedClasses as $c): ?>
            <tr data-search-row><td data-label="Class" class="ga-class-cell"><strong><?= esc($c['subject_code']) ?></strong><span><?= esc($c['year_level']) ?> &middot; Section <?= esc($c['section']) ?></span><small><?= esc($c['program']) ?></small></td><td data-label="Academic term" class="ga-term-cell"><?= esc($c['academic_year']) ?></td><td data-label="Students" class="ga-number"><?= (int) $c['student_count'] ?></td><td data-label="Status"><span class="ga-status">Approved</span></td><td class="ga-row-action"><a class="ga-button ga-button--primary" href="<?= esc($classLink($c), 'attr') ?>">Lock grades<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a></td></tr>
        <?php endforeach ?>
        </tbody></table></div>
        <?php else: ?><div class="ga-empty"><span class="material-symbols-outlined" aria-hidden="true">task_alt</span><div><h3>Nothing approved yet</h3><p>Classes you approve will appear here until locked.</p></div></div><?php endif ?>
        <div class="ga-empty" data-no-results hidden><span class="material-symbols-outlined" aria-hidden="true">search_off</span><div><h3>No matching classes</h3><p>Try another subject, section, or academic term.</p></div></div>
        <footer class="ga-footer"><span data-result-count role="status" aria-live="polite"><?= count($approvedClasses) ?> <?= count($approvedClasses) === 1 ? 'class' : 'classes' ?> approved</span></footer>
    </section>
    <section id="gaLocked" aria-labelledby="gaLockedTitle" hidden data-noun-singular="class" data-noun-plural="classes" data-count-suffix=" locked">
        <header class="ga-toolbar"><div><h2 id="gaLockedTitle">Locked classes</h2><p>Official and visible to students.</p></div><label class="ga-search" hidden data-search-control><span class="material-symbols-outlined" aria-hidden="true">search</span><span class="ga-sr-only">Search locked classes</span><input type="search" data-search="gaLocked" placeholder="Search subject, section or term"></label></header>
        <?php if ($lockedClasses !== []): ?>
        <div class="ga-table-scroll"><table class="ga-table"><caption class="ga-sr-only">Locked classes</caption><thead><tr><th scope="col">Class</th><th scope="col">Academic term</th><th scope="col">Students</th><th scope="col">Status</th><th scope="col"><span class="ga-sr-only">View</span></th></tr></thead><tbody>
        <?php foreach ($lockedClasses as $c): ?>
            <tr data-search-row><td data-label="Class" class="ga-class-cell"><strong><?= esc($c['subject_code']) ?></strong><span><?= esc($c['year_level']) ?> &middot; Section <?= esc($c['section']) ?></span><small><?= esc($c['program']) ?></small></td><td data-label="Academic term" class="ga-term-cell"><?= esc($c['academic_year']) ?></td><td data-label="Students" class="ga-number"><?= (int) $c['student_count'] ?></td><td data-label="Status"><span class="ga-status locked">Official</span></td><td class="ga-row-action"><a class="ga-button" href="<?= esc($classLink($c), 'attr') ?>">View grades<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a></td></tr>
        <?php endforeach ?>
        </tbody></table></div>
        <?php else: ?><div class="ga-empty"><span class="material-symbols-outlined" aria-hidden="true">lock_open</span><div><h3>Nothing locked yet</h3><p>Classes you lock will stay listed here for reference.</p></div></div><?php endif ?>
        <div class="ga-empty" data-no-results hidden><span class="material-symbols-outlined" aria-hidden="true">search_off</span><div><h3>No matching classes</h3><p>Try another subject, section, or academic term.</p></div></div>
        <footer class="ga-footer"><span data-result-count role="status" aria-live="polite"><?= count($lockedClasses) ?> <?= count($lockedClasses) === 1 ? 'class' : 'classes' ?> locked</span></footer>
    </section>
</section>
<aside class="ga-policy"><span class="material-symbols-outlined" aria-hidden="true">lock_outline</span><p><strong>Review before locking.</strong> Locked grades are official and visible to students. Later changes require an approved correction request.</p></aside>
<noscript><p class="ga-policy">Enable JavaScript to filter queues.</p></noscript>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script type="application/json" id="gaConfig"><?= json_encode(['endpoint' => site_url('registrar/grade-approvals')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= base_url('assets/js/grade-approvals.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/grade-approvals.js') ?>"></script>
<?= $this->endSection() ?>

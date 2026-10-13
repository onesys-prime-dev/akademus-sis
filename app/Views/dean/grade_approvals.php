<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/grade-approvals.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/grade-approvals.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="ga-page" id="gradeApprovals">
<header class="ga-heading"><div><span class="ga-eyebrow">ACADEMIC RECORDS</span><h1>Grade Approvals</h1><p>Note classes the chairperson has recommended, on their way to the registrar.</p></div><?php if ($collegeName !== null): ?><span class="ga-department"><small>College</small><strong><?= esc($collegeName) ?></strong></span><?php endif ?></header>
<?php if ($collegeName === null): ?>
<section class="ga-card ga-empty"><span class="material-symbols-outlined" aria-hidden="true">domain_disabled</span><div><h3>No college assigned</h3><p>Contact an administrator to assign a college to your account.</p></div></section>
<?php else: ?>
<div id="gaFeedback" class="ga-feedback" role="status" aria-live="polite" hidden></div>
<section class="ga-card ga-queue-card">
    <div class="ga-tabs" role="tablist" aria-label="Approval queues" id="gaTabs" hidden><button type="button" role="tab" id="gaSubmissionsTab" aria-controls="gaSubmissions" aria-selected="true" data-tab="gaSubmissions">To review <span class="ga-count"><?= count($classes) ?></span></button><button type="button" role="tab" id="gaLockedTab" aria-controls="gaLocked" aria-selected="false" tabindex="-1" data-tab="gaLocked">Noted <span class="ga-count"><?= count($notedClasses) ?></span></button></div>
    <section id="gaSubmissions" aria-labelledby="gaSubmissionsTitle" data-noun-singular="class" data-noun-plural="classes" data-count-suffix=" awaiting your note">
        <header class="ga-toolbar"><div><h2 id="gaSubmissionsTitle">Awaiting your note</h2><p>Open a class to check its grades before noting it.</p></div><label class="ga-search" hidden data-search-control><span class="material-symbols-outlined" aria-hidden="true">search</span><span class="ga-sr-only">Search classes</span><input type="search" data-search="gaSubmissions" placeholder="Search subject, section or term"></label></header>
        <?php if ($classes !== []): ?>
        <div class="ga-table-scroll"><table class="ga-table"><caption class="ga-sr-only">Classes awaiting your note</caption><thead><tr><th scope="col">Class</th><th scope="col">Academic term</th><th scope="col">Students</th><th scope="col">Status</th><th scope="col"><span class="ga-sr-only">Review</span></th></tr></thead><tbody>
        <?php foreach ($classes as $c): ?>
            <tr data-search-row><td data-label="Class" class="ga-class-cell"><strong><?= esc($c['subject_code']) ?></strong><span><?= esc($c['year_level']) ?> &middot; Section <?= esc($c['section']) ?></span><small><?= esc($c['program']) ?></small></td><td data-label="Academic term" class="ga-term-cell"><?= esc($c['academic_year']) ?></td><td data-label="Students" class="ga-number"><?= (int) $c['student_count'] ?></td><td data-label="Status"><span class="ga-status">Recommended</span></td><td class="ga-row-action"><a class="ga-button ga-button--primary" aria-label="Review <?= esc($c['subject_code'], 'attr') ?> section <?= esc($c['section'], 'attr') ?>" href="<?= esc(site_url('dean/grade-approvals/class') . '?' . http_build_query(array_intersect_key($c, array_flip(['academic_year','program','year_level','section','subject_code']))), 'attr') ?>">Review grades<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a></td></tr>
        <?php endforeach ?>
        </tbody></table></div>
        <?php else: ?><div class="ga-empty"><span class="material-symbols-outlined" aria-hidden="true">task_alt</span><div><h3>Nothing awaiting your note</h3><p>Classes the chairperson recommends will appear here.</p></div></div><?php endif ?>
        <div class="ga-empty" data-no-results hidden><span class="material-symbols-outlined" aria-hidden="true">search_off</span><div><h3>No matching classes</h3><p>Try another subject, section, or academic term.</p></div></div>
        <footer class="ga-footer"><span data-result-count role="status" aria-live="polite"><?= count($classes) ?> <?= count($classes) === 1 ? 'class' : 'classes' ?> awaiting your note</span><span>Noted classes move on to the registrar.</span></footer>
    </section>
    <section id="gaLocked" aria-labelledby="gaLockedTitle" hidden data-noun-singular="class" data-noun-plural="classes" data-count-suffix=" noted">
        <header class="ga-toolbar"><div><h2 id="gaLockedTitle">Noted classes</h2><p>Sent on to the registrar. Open one to check what was recorded.</p></div><label class="ga-search" hidden data-search-control><span class="material-symbols-outlined" aria-hidden="true">search</span><span class="ga-sr-only">Search noted classes</span><input type="search" data-search="gaLocked" placeholder="Search subject, section or term"></label></header>
        <?php if ($notedClasses !== []): ?>
        <div class="ga-table-scroll"><table class="ga-table"><caption class="ga-sr-only">Noted classes</caption><thead><tr><th scope="col">Class</th><th scope="col">Academic term</th><th scope="col">Students</th><th scope="col">Status</th><th scope="col"><span class="ga-sr-only">View</span></th></tr></thead><tbody>
        <?php foreach ($notedClasses as $c): ?>
            <tr data-search-row><td data-label="Class" class="ga-class-cell"><strong><?= esc($c['subject_code']) ?></strong><span><?= esc($c['year_level']) ?> &middot; Section <?= esc($c['section']) ?></span><small><?= esc($c['program']) ?></small></td><td data-label="Academic term" class="ga-term-cell"><?= esc($c['academic_year']) ?></td><td data-label="Students" class="ga-number"><?= (int) $c['student_count'] ?></td><td data-label="Status"><span class="ga-status locked">Noted</span></td><td class="ga-row-action"><a class="ga-button" aria-label="View <?= esc($c['subject_code'], 'attr') ?> section <?= esc($c['section'], 'attr') ?>" href="<?= esc(site_url('dean/grade-approvals/class') . '?' . http_build_query(array_intersect_key($c, array_flip(['academic_year','program','year_level','section','subject_code']))), 'attr') ?>">View grades<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a></td></tr>
        <?php endforeach ?>
        </tbody></table></div>
        <?php else: ?><div class="ga-empty"><span class="material-symbols-outlined" aria-hidden="true">lock_open</span><div><h3>Nothing noted yet</h3><p>Classes you note will stay listed here while they move on to the registrar.</p></div></div><?php endif ?>
        <div class="ga-empty" data-no-results hidden><span class="material-symbols-outlined" aria-hidden="true">search_off</span><div><h3>No matching classes</h3><p>Try another subject, section, or academic term.</p></div></div>
        <footer class="ga-footer"><span data-result-count role="status" aria-live="polite"><?= count($notedClasses) ?> <?= count($notedClasses) === 1 ? 'class' : 'classes' ?> noted</span></footer>
    </section>
</section>
<aside class="ga-policy"><span class="material-symbols-outlined" aria-hidden="true">lock_outline</span><p><strong>Review before noting.</strong> Noting sends a class on to the registrar for final approval and locking.</p></aside>
<noscript><p class="ga-policy">Enable JavaScript to filter queues.</p></noscript>
<?php endif ?>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script type="application/json" id="gaConfig"><?= json_encode(['endpoint' => site_url('dean/grade-approvals')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= base_url('assets/js/grade-approvals.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/grade-approvals.js') ?>"></script>
<?= $this->endSection() ?>

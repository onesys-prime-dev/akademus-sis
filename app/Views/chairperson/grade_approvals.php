<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'grade-approvals';
$pageTitle     = 'Grade Approvals';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/grade-approvals.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/grade-approvals.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="ga-page" id="gradeApprovals">
<header class="ga-heading"><div><span class="ga-eyebrow">ACADEMIC RECORDS</span><h1>Grade Approvals</h1><p>Review faculty submissions and decide requests to change official grades.</p></div><?php if ($deptName !== null): ?><span class="ga-department"><small>Department</small><strong><?= esc($deptName) ?></strong></span><?php endif ?></header>
<?php if ($deptName === null): ?>
<section class="ga-card ga-empty"><span class="material-symbols-outlined" aria-hidden="true">domain_disabled</span><div><h3>No department assigned</h3><p>Contact an administrator to assign a department to your account.</p></div></section>
<?php else: ?>
<div id="gaFeedback" class="ga-feedback" role="status" aria-live="polite" hidden></div>
<section class="ga-card ga-queue-card">
    <div class="ga-tabs" role="tablist" aria-label="Approval queues" id="gaTabs" hidden><button type="button" role="tab" id="gaSubmissionsTab" aria-controls="gaSubmissions" aria-selected="true" data-tab="gaSubmissions">To review <span class="ga-count"><?= count($classes) ?></span></button><button type="button" role="tab" id="gaLockedTab" aria-controls="gaLocked" aria-selected="false" tabindex="-1" data-tab="gaLocked">Official <span class="ga-count"><?= count($lockedClasses) ?></span></button><button type="button" role="tab" id="gaCorrectionsTab" aria-controls="gaCorrections" aria-selected="false" tabindex="-1" data-tab="gaCorrections">Corrections <span class="ga-count" id="gaCorrectionCount"><?= count($changeRequests) ?></span></button></div>
    <section id="gaSubmissions" aria-labelledby="gaSubmissionsTitle" data-noun-singular="class" data-noun-plural="classes" data-count-suffix=" awaiting review">
        <header class="ga-toolbar"><div><h2 id="gaSubmissionsTitle">Awaiting review</h2><p>Open a class to check its grades before making them official.</p></div><label class="ga-search" hidden data-search-control><span class="material-symbols-outlined" aria-hidden="true">search</span><span class="ga-sr-only">Search grade submissions</span><input type="search" data-search="gaSubmissions" placeholder="Search subject, section or term"></label></header>
        <?php if ($classes !== []): ?>
        <div class="ga-table-scroll"><table class="ga-table"><caption class="ga-sr-only">Submitted classes awaiting review</caption><thead><tr><th scope="col">Class</th><th scope="col">Academic term</th><th scope="col">Students</th><th scope="col">Status</th><th scope="col"><span class="ga-sr-only">Review</span></th></tr></thead><tbody>
        <?php foreach ($classes as $c): ?>
            <tr data-search-row><td data-label="Class" class="ga-class-cell"><strong><?= esc($c['subject_code']) ?></strong><span><?= esc($c['year_level']) ?> &middot; Section <?= esc($c['section']) ?></span><small><?= esc($c['program']) ?></small></td><td data-label="Academic term" class="ga-term-cell"><?= esc($c['academic_year']) ?></td><td data-label="Students" class="ga-number"><?= (int) $c['student_count'] ?></td><td data-label="Status"><span class="ga-status">Submitted</span></td><td class="ga-row-action"><a class="ga-button ga-button--primary" aria-label="Review <?= esc($c['subject_code'], 'attr') ?> section <?= esc($c['section'], 'attr') ?>" href="<?= esc(site_url('chairperson/grade-approvals/class') . '?' . http_build_query(array_intersect_key($c, array_flip(['academic_year','program','year_level','section','subject_code']))), 'attr') ?>">Review grades<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a></td></tr>
        <?php endforeach ?>
        </tbody></table></div>
        <?php else: ?><div class="ga-empty"><span class="material-symbols-outlined" aria-hidden="true">task_alt</span><div><h3>No submissions pending</h3><p>New submissions will appear here when faculty sends grades for review.</p></div></div><?php endif ?>
        <div class="ga-empty" data-no-results hidden><span class="material-symbols-outlined" aria-hidden="true">search_off</span><div><h3>No matching classes</h3><p>Try another subject, section, or academic term.</p></div></div>
        <footer class="ga-footer"><span data-result-count role="status" aria-live="polite"><?= count($classes) ?> <?= count($classes) === 1 ? 'class' : 'classes' ?> awaiting review</span><span>Grades become official after locking.</span></footer>
    </section>
    <section id="gaLocked" aria-labelledby="gaLockedTitle" hidden data-noun-singular="class" data-noun-plural="classes" data-count-suffix=" locked">
        <header class="ga-toolbar"><div><h2 id="gaLockedTitle">Locked classes</h2><p>Already official. Open one to check what was recorded.</p></div><label class="ga-search" hidden data-search-control><span class="material-symbols-outlined" aria-hidden="true">search</span><span class="ga-sr-only">Search locked classes</span><input type="search" data-search="gaLocked" placeholder="Search subject, section or term"></label></header>
        <?php if ($lockedClasses !== []): ?>
        <div class="ga-table-scroll"><table class="ga-table"><caption class="ga-sr-only">Locked classes</caption><thead><tr><th scope="col">Class</th><th scope="col">Academic term</th><th scope="col">Students</th><th scope="col">Status</th><th scope="col"><span class="ga-sr-only">View</span></th></tr></thead><tbody>
        <?php foreach ($lockedClasses as $c): ?>
            <tr data-search-row><td data-label="Class" class="ga-class-cell"><strong><?= esc($c['subject_code']) ?></strong><span><?= esc($c['year_level']) ?> &middot; Section <?= esc($c['section']) ?></span><small><?= esc($c['program']) ?></small></td><td data-label="Academic term" class="ga-term-cell"><?= esc($c['academic_year']) ?></td><td data-label="Students" class="ga-number"><?= (int) $c['student_count'] ?></td><td data-label="Status"><span class="ga-status locked">Official</span></td><td class="ga-row-action"><a class="ga-button" aria-label="View <?= esc($c['subject_code'], 'attr') ?> section <?= esc($c['section'], 'attr') ?>" href="<?= esc(site_url('chairperson/grade-approvals/class') . '?' . http_build_query(array_intersect_key($c, array_flip(['academic_year','program','year_level','section','subject_code']))), 'attr') ?>">View grades<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a></td></tr>
        <?php endforeach ?>
        </tbody></table></div>
        <?php else: ?><div class="ga-empty"><span class="material-symbols-outlined" aria-hidden="true">lock_open</span><div><h3>Nothing locked yet</h3><p>Classes you lock will stay listed here for reference.</p></div></div><?php endif ?>
        <div class="ga-empty" data-no-results hidden><span class="material-symbols-outlined" aria-hidden="true">search_off</span><div><h3>No matching classes</h3><p>Try another subject, section, or academic term.</p></div></div>
        <footer class="ga-footer"><span data-result-count role="status" aria-live="polite"><?= count($lockedClasses) ?> <?= count($lockedClasses) === 1 ? 'class' : 'classes' ?> locked</span><span>Contact the student's registrar for corrections.</span></footer>
    </section>
    <section id="gaCorrections" aria-labelledby="gaCorrectionsTitle" data-noun-singular="request" data-noun-plural="requests" data-count-suffix=" pending">
        <header class="ga-toolbar"><div><h2 id="gaCorrectionsTitle">Correction requests</h2><p>Review proposed changes to grades that are already locked.</p></div><label class="ga-search" hidden data-search-control><span class="material-symbols-outlined" aria-hidden="true">search</span><span class="ga-sr-only">Search correction requests</span><input type="search" data-search="gaCorrections" placeholder="Search student, subject or term"></label></header>
        <div id="gaRequestList">
        <?php foreach ($changeRequests as $r): ?>
            <article class="ga-request" data-request-id="<?= (int) $r['request_id'] ?>" data-search-row>
                <div><div class="ga-request-title"><strong data-student><?= esc($r['student_username']) ?></strong><span data-subject><?= esc($r['subject_code']) ?></span></div><p class="ga-request-meta" data-context><?= esc($r['program']) ?> &middot; <?= esc($r['year_level']) ?> &middot; Section <?= esc($r['section']) ?><br><?= esc($r['academic_year']) ?> &middot; Requested by <?= esc($r['requested_by_username']) ?></p><div class="ga-change"><div>Current <strong data-current><?= esc($r['current_value']) ?></strong></div><span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span><div>Requested <strong data-requested><?= esc($r['requested_value']) ?></strong></div></div><p class="ga-reason" data-reason><?= esc($r['reason']) ?></p></div>
                <div class="ga-request-actions"><button type="button" class="ga-button ga-button--reject" data-decision="reject">Reject</button><button type="button" class="ga-button ga-button--primary" data-decision="approve">Approve change</button></div>
            </article>
        <?php endforeach ?>
        </div>
        <div class="ga-empty" id="gaCorrectionsEmpty" <?= $changeRequests !== [] ? 'hidden' : '' ?>><span class="material-symbols-outlined" aria-hidden="true">task_alt</span><div><h3>No corrections to review</h3><p>Requests to change locked grades will appear here.</p></div></div>
        <div class="ga-empty" data-no-results hidden><span class="material-symbols-outlined" aria-hidden="true">search_off</span><div><h3>No matching requests</h3><p>Try another student, subject, or academic term.</p></div></div>
        <footer class="ga-footer"><span data-result-count role="status" aria-live="polite"><?= count($changeRequests) ?> <?= count($changeRequests) === 1 ? 'request' : 'requests' ?> pending</span><span>Each decision is recorded.</span></footer>
    </section>
</section>
<aside class="ga-policy"><span class="material-symbols-outlined" aria-hidden="true">lock_outline</span><p><strong>Review before locking.</strong> Locked grades are official and visible to students. Later changes require an approved correction request.</p></aside>
<dialog class="ga-dialog" id="gaDecisionDialog" aria-labelledby="gaDecisionTitle" aria-describedby="gaDecisionDescription">
    <form id="gaDecisionForm"><header class="ga-dialog-header"><h2 id="gaDecisionTitle">Review correction</h2><p id="gaDecisionDescription"></p></header><div class="ga-dialog-body"><div class="ga-feedback is-error" id="gaDecisionError" role="alert" hidden></div><div class="ga-dialog-summary" id="gaDecisionSummary"></div><div class="ga-field"><label for="gaNotes">Decision note <span>Optional</span></label><textarea id="gaNotes" rows="3" placeholder="Add context for this decision"></textarea></div></div><footer class="ga-dialog-footer"><button type="button" class="ga-button" id="gaCancelDecision">Cancel</button><button type="submit" class="ga-button ga-button--primary" id="gaConfirmDecision">Confirm</button></footer></form>
</dialog>
<noscript><p class="ga-policy">Enable JavaScript to filter queues and review correction requests.</p></noscript>
<?php endif ?>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script type="application/json" id="gaConfig"><?= json_encode(['endpoint' => site_url('chairperson/grade-approvals/change-requests')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= base_url('assets/js/grade-approvals.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/grade-approvals.js') ?>"></script>
<?= $this->endSection() ?>

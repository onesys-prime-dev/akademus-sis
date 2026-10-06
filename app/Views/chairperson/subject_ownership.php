<?= $this->extend('layouts/admin_console') ?>
<?php
$activeAdmin = 'subject-ownership';
$pageTitle = 'Subject Ownership';
$brandSub = 'Chairperson Portal';
$userName = $user['name'];
$userRoleLabel = $user['role'];
$navGroups = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
$catalog = array_map(static fn ($s) => [
    'id' => (int) $s['subject_id'], 'code' => $s['subject_code'], 'title' => $s['subject_name'],
    'units' => (float) $s['units'], 'duration' => (float) $s['contact_hours'],
    'is_lab' => (bool) $s['is_laboratory'], 'active' => (bool) $s['is_active'],
    'department' => (int) $s['sub_department_id'],
], $subjects);
?>
<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/subject-ownership.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/subject-ownership.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="so-page">
    <header class="so-heading">
        <div><div class="so-eyebrow">ACADEMIC PLANNING / SUBJECTS</div><h2>Subject Ownership<span class="so-heading-dot">.</span></h2><p>Your department’s subjects, organized in one place.</p></div>
        <?php if ($hasAnyDept): ?><button type="button" class="so-button so-button--primary" data-open-add><span class="material-symbols-outlined" aria-hidden="true">add</span>Add subject</button><?php endif ?>
    </header>
    <div id="soFeedback" class="so-feedback" role="status" aria-live="polite" hidden></div>
    <?php if (!$hasAnyDept): ?>
        <section class="so-card so-empty"><span class="material-symbols-outlined" aria-hidden="true">domain_disabled</span><h3>No department assigned</h3><p>Contact an administrator to assign a department to your account.</p></section>
    <?php else: ?>
        <section class="so-overview so-card" aria-label="Catalog overview">
            <div class="so-department"><span class="so-department-icon material-symbols-outlined" aria-hidden="true">account_balance</span><div><div class="so-eyebrow">YOUR DEPARTMENT<?= count($deptNames) > 1 ? 'S' : '' ?></div><h3><?= count($deptNames) === 1 ? esc(reset($deptNames)) : count($deptNames) . ' departments' ?></h3><p>Owned here. Shared across programs.</p></div></div>
            <div class="so-stat"><span>Total subjects</span><strong id="soTotal"><?= count($catalog) ?></strong></div>
            <div class="so-stat"><span><i class="so-dot so-dot--green"></i>Active</span><strong id="soActive"><?= count(array_filter($catalog, static fn ($s) => $s['active'])) ?></strong></div>
            <div class="so-stat"><span><i class="so-dot"></i>Inactive</span><strong id="soInactive"><?= count(array_filter($catalog, static fn ($s) => !$s['active'])) ?></strong></div>
        </section>
        <section class="so-card so-courses" aria-labelledby="soCoursesTitle">
            <header class="so-catalog-heading">
                <div><div class="so-title-line"><h3 id="soCoursesTitle">Course distribution</h3></div><p>Expand a course to explore its subjects by year and semester.</p></div>
                <?php if (count($deptNames) > 1): ?><div class="so-filter"><label for="soCourseDept">Department</label><select id="soCourseDept"><?php foreach ($deptNames as $id => $name): ?><option value="<?= (int) $id ?>"><?= esc($name) ?></option><?php endforeach ?></select></div><?php endif ?>
            </header>
            <div class="so-course-groups" id="soCourseGroups"></div>
            <p class="so-empty-inline" id="soCourseEmpty" hidden>This department has no active courses yet. Add one through the Curriculum Builder.</p>
        </section>
        <section class="so-card so-catalog" aria-labelledby="soCatalogTitle">
            <header class="so-catalog-heading"><div><div class="so-title-line"><h3 id="soCatalogTitle">Subject catalog</h3><span class="so-count" id="soCatalogCount"><?= count($catalog) ?></span></div><p>Manage the subjects your department contributes to the shared catalog.</p></div><a class="so-link" href="<?= site_url('chairperson/curriculum') ?>">Curriculum Builder<span class="material-symbols-outlined" aria-hidden="true">arrow_outward</span></a></header>
            <div class="so-toolbar">
                <div class="so-search"><span class="material-symbols-outlined" aria-hidden="true">search</span><label class="so-sr-only" for="soSearch">Search subjects by code or title</label><input type="search" id="soSearch" placeholder="Search by subject code or title…" autocomplete="off"><kbd>/</kbd></div>
                <div class="so-filter"><label for="soType">Class type</label><select id="soType"><option value="all">All types</option><option value="lecture">Lecture</option><option value="lab">Laboratory</option></select></div>
                <?php if (count($deptNames) > 1): ?><div class="so-filter"><label for="soDepartment">Department</label><select id="soDepartment"><option value="all">All departments</option><?php foreach ($deptNames as $id => $name): ?><option value="<?= (int) $id ?>"><?= esc($name) ?></option><?php endforeach ?></select></div><?php endif ?>
            </div>
            <div class="so-list-controls"><nav class="so-status-tabs" aria-label="Filter subjects by status"><button type="button" class="is-active" data-status="all" aria-pressed="true">All subjects <span id="soAllTab"><?= count($catalog) ?></span></button><button type="button" data-status="active" aria-pressed="false">Active</button><button type="button" data-status="inactive" aria-pressed="false">Inactive</button></nav><div class="so-sort"><label for="soSort">Sort by</label><select id="soSort"><option value="code">Subject code</option><option value="title">Subject title</option></select></div></div>
            <div class="so-table-wrap" id="soTableWrap"><table class="so-table"><caption class="so-sr-only">Subjects owned by your departments</caption><thead><tr><th scope="col">Subject</th><th scope="col">Units</th><th scope="col">Class type</th><th scope="col">Meeting length</th><th scope="col">Status</th><th scope="col" class="so-actions-heading">Actions</th></tr></thead><tbody id="soRows"></tbody></table></div>
            <div class="so-empty" id="soEmpty" hidden><span class="material-symbols-outlined" aria-hidden="true">search_off</span><h3 id="soEmptyTitle">No matching subjects</h3><p id="soEmptyText">Try a different search or adjust your filters.</p><button type="button" class="so-button" id="soClearFilters">Clear filters</button><button type="button" class="so-button so-button--primary" id="soEmptyAdd" data-open-add hidden>Add your first subject</button></div>
            <footer class="so-pagination"><p id="soRange" role="status" aria-live="polite"></p><div><button type="button" class="so-page-button" id="soPrevious" aria-label="Previous page"><span class="material-symbols-outlined" aria-hidden="true">chevron_left</span></button><span id="soPageNumber"></span><button type="button" class="so-page-button" id="soNext" aria-label="Next page"><span class="material-symbols-outlined" aria-hidden="true">chevron_right</span></button></div></footer>
            <noscript><p class="so-notice">Enable JavaScript to browse and manage your subject catalog.</p></noscript>
        </section>
        <aside class="so-notice"><span class="material-symbols-outlined" aria-hidden="true">info</span><p><strong>A shared catalog, without duplicates.</strong> Reuse existing subjects through the Curriculum Builder. Inactive subjects are hidden from new assignments; subjects on live schedules cannot be deactivated.</p></aside>

        <dialog class="so-dialog" id="soEditor" aria-labelledby="soEditorTitle" aria-describedby="soEditorDescription">
            <form id="soForm">
                <header class="so-dialog-header"><div><div class="so-eyebrow">SUBJECT DETAILS</div><h3 id="soEditorTitle">Add a subject</h3><p id="soEditorDescription">Create a subject for your department’s shared catalog.</p></div><button type="button" class="so-icon-button" data-close-editor aria-label="Close subject editor"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></header>
                <div class="so-dialog-body">
                    <div class="so-feedback is-error" id="soFormError" role="alert" hidden></div>
                    <div class="so-field" id="soOwnerField"><label for="soOwner">Owning department</label><select id="soOwner" required><?php foreach ($deptNames as $id => $name): ?><option value="<?= (int) $id ?>"><?= esc($name) ?></option><?php endforeach ?></select></div>
                    <div class="so-field"><label for="soCode">Subject code <span>Required</span></label><input id="soCode" type="text" placeholder="e.g. ITE 101" maxlength="20" minlength="2" required aria-describedby="soCodeHelp"><small id="soCodeHelp">Use a unique code, 2–20 characters. Letters are capitalized automatically.</small></div>
                    <div class="so-field"><label for="soTitle">Subject title <span>Required</span></label><input id="soTitle" type="text" placeholder="e.g. Introduction to Computing" maxlength="200" required></div>
                    <div class="so-form-grid"><div class="so-field"><label for="soUnits">Units</label><input id="soUnits" type="number" min="0.5" max="6" step="any" value="3" required><small>Between 0.5 and 6 units.</small></div><div class="so-field"><label for="soDuration">Meeting length</label><select id="soDuration" required><?php foreach ($durations as $d): ?><option value="<?= (float) $d ?>" <?= (float) $d === 1.5 ? 'selected' : '' ?>><?= (float) $d ?> <?= (float) $d === 1.0 ? 'hour' : 'hours' ?></option><?php endforeach ?></select><small>Duration of each meeting.</small></div></div>
                    <fieldset class="so-type-options"><legend>Class type</legend><label><input type="radio" name="class_type" value="lecture" checked><span class="material-symbols-outlined" aria-hidden="true">menu_book</span><span><strong>Lecture</strong><small>Classroom instruction</small></span></label><label><input type="radio" name="class_type" value="lab"><span class="material-symbols-outlined" aria-hidden="true">science</span><span><strong>Laboratory</strong><small>Practical instruction</small></span></label></fieldset>
                    <p class="so-editor-hint" id="soEditorHint"><span class="material-symbols-outlined" aria-hidden="true">info</span>Already in the catalog? Reuse it through the Curriculum Builder.</p>
                </div>
                <footer class="so-dialog-footer"><button type="button" class="so-button" data-close-editor>Cancel</button><button type="submit" class="so-button so-button--primary" id="soSave">Add subject</button></footer>
            </form>
        </dialog>
        <dialog class="so-dialog so-dialog--small" id="soStatusDialog" aria-labelledby="soStatusTitle" aria-describedby="soStatusDescription"><div class="so-dialog-header"><div><div class="so-eyebrow">CATALOG AVAILABILITY</div><h3 id="soStatusTitle">Deactivate subject?</h3></div><button type="button" class="so-icon-button" id="soCloseStatus" aria-label="Close status dialog"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></div><div class="so-dialog-body"><div class="so-feedback is-error" id="soStatusError" role="alert" hidden></div><strong id="soStatusSubject"></strong><p id="soStatusDescription"></p></div><footer class="so-dialog-footer"><button type="button" class="so-button" id="soCancelStatus">Cancel</button><button type="button" class="so-button so-button--danger" id="soConfirmStatus">Deactivate subject</button></footer></dialog>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<?php if ($hasAnyDept): ?>
<script type="application/json" id="soConfig"><?= json_encode(['subjects' => $catalog, 'departments' => (object) $deptNames, 'courses' => (object) $courses, 'subjectCourses' => (object) $subjectCourses, 'endpoint' => site_url('chairperson/subject-ownership')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= base_url('assets/js/subject-ownership.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/subject-ownership.js') ?>"></script>
<?php endif ?>
<?= $this->endSection() ?>

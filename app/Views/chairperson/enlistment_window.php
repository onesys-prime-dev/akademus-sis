<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'enlistment-window';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Enlistment Window</h2><p class="ac-page-header__sub">Open or close pre-registration for the upcoming term, while the active term is still running.</p></div>
</div>

<?php if ($ay === null): ?>
    <div class="ac-empty-block">No upcoming academic term exists yet. Create one under Admin &rarr; Academic Years before opening enlistment for it.</div>
<?php else: ?>
    <?php if (count($terms) > 1): ?>
        <form method="get" action="<?= site_url('chairperson/enlistment-window') ?>" style="margin-bottom:16px;">
            <label style="font-size:11.5px;">Term
                <select name="ay_id" class="ac-input" onchange="this.form.submit()">
                    <?php foreach ($terms as $t): ?>
                        <option value="<?= (int) $t['ay_id'] ?>" <?= (int) $ay['ay_id'] === (int) $t['ay_id'] ? 'selected' : '' ?>><?= esc($t['label']) ?> &middot; <?= esc($t['semester']) ?> Semester</option>
                    <?php endforeach ?>
                </select>
            </label>
        </form>
    <?php endif ?>

    <div class="ac-card" style="max-width:420px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span class="ac-dept-name"><?= esc($ay['label']) ?> &middot; <?= esc($ay['semester']) ?> <span style="font-weight:400; color:var(--color-text-secondary);">(upcoming)</span></span>
            <span class="ac-pill <?= $isOpen ? 'ac-pill--success' : 'ac-pill--danger' ?>">Enlistment <?= $isOpen ? 'OPEN' : 'CLOSED' ?></span>
        </div>

        <form method="post" action="<?= site_url('chairperson/enlistment-window/update') ?>" style="display:grid; gap:8px; margin-top:14px;">
            <?= csrf_field() ?>
            <input type="hidden" name="ay_id" value="<?= (int) $ay['ay_id'] ?>">
            <label style="font-size:11.5px;">Opens<input type="datetime-local" name="enlistment_opens_at" class="ac-input" value="<?= $ay['enlistment_opens_at'] ? esc(str_replace(' ', 'T', $ay['enlistment_opens_at'])) : '' ?>"></label>
            <label style="font-size:11.5px;">Closes<input type="datetime-local" name="enlistment_closes_at" class="ac-input" value="<?= $ay['enlistment_closes_at'] ? esc(str_replace(' ', 'T', $ay['enlistment_closes_at'])) : '' ?>"></label>
            <button type="submit" class="ac-btn ac-btn-primary ac-btn-block">Save Window</button>
        </form>
    </div>

    <div class="ac-card" style="max-width:720px; margin-top:20px;">
        <h3 class="ac-dept-name" style="margin-bottom:4px;">Offered Subjects</h3>
        <p style="font-size:12.5px; color:var(--color-text-secondary); margin-top:0;">
            Every curriculum subject for <strong><?= esc($ay['semester']) ?> Semester, <?= esc($ay['label']) ?></strong> on the program/year/section you pick is pre-checked &mdash; uncheck anything you're <strong>not</strong> offering, then save.
        </p>

        <?php if ($programs === []): ?>
            <div class="ac-empty-block">You have no program assigned for scheduling.</div>
        <?php else: ?>
            <form method="get" action="<?= site_url('chairperson/enlistment-window') ?>" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-top:12px;">
                <input type="hidden" name="ay_id" value="<?= (int) $ay['ay_id'] ?>">
                <?php if (count($programs) > 1): ?>
                    <label style="font-size:11.5px;">Program
                        <select name="program_id" class="ac-input" onchange="this.form.submit()">
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= (int) $p['program_id'] ?>" <?= $selectedProgram === (int) $p['program_id'] ? 'selected' : '' ?>><?= esc($p['program_name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </label>
                <?php else: ?>
                    <input type="hidden" name="program_id" value="<?= (int) $programs[0]['program_id'] ?>">
                <?php endif ?>

                <label style="font-size:11.5px;">Year Level
                    <select name="year_level" class="ac-input" onchange="this.form.submit()">
                        <option value="">Select&hellip;</option>
                        <?php foreach ($yearLevels as $level => $label): ?>
                            <option value="<?= $level ?>" <?= $selectedYear === $level ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>

                <label style="font-size:11.5px;">Section
                    <select name="section" class="ac-input" style="width:160px;" onchange="this.form.submit()">
                        <option value="">Select&hellip;</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?= esc($s) ?>" <?= $selectedSection === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>

                <button type="submit" class="ac-btn ac-btn-secondary">Load Subjects</button>
            </form>

            <?php if ($selectedYear >= 1 && $selectedSection !== ''): ?>
                <?php if ($subjects === []): ?>
                    <div class="ac-empty-block" style="margin-top:16px;">No curriculum subjects found for this program/year/semester.</div>
                <?php else: ?>
                    <form method="post" action="<?= site_url('chairperson/enlistment-window/save-offerings') ?>" style="margin-top:16px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ay_id" value="<?= (int) $ay['ay_id'] ?>">
                        <input type="hidden" name="program_id" value="<?= $selectedProgram ?>">
                        <input type="hidden" name="year_level" value="<?= $selectedYear ?>">
                        <input type="hidden" name="section" value="<?= esc($selectedSection) ?>">

                        <table class="ac-table" style="width:100%;">
                            <thead>
                                <tr><th>Offer</th><th>Code</th><th>Title</th><th>Units</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $s): ?>
                                    <tr>
                                        <td data-label="Offer"><input type="checkbox" name="subjects[]" value="<?= esc($s['code']) ?>" checked></td>
                                        <td data-label="Code"><?= esc($s['code']) ?></td>
                                        <td data-label="Title"><?= esc($s['title']) ?></td>
                                        <td data-label="Units"><?= esc((string) $s['units']) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>

                        <button type="submit" class="ac-btn ac-btn-primary" style="margin-top:12px;">Save Offerings</button>
                    </form>
                <?php endif ?>
            <?php endif ?>
        <?php endif ?>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

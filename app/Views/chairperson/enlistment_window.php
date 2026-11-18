<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'enlistment-window';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

/**
 * A human-readable companion to the OPEN/CLOSED pill, read off the same
 * raw enlistment_opens_at/enlistment_closes_at fields AcademicYearModel::
 * isEnlistmentOpen() checks — never recomputed independently, so the pill
 * and this line can't disagree.
 */
if (! function_exists('ew_status_line')) :
function ew_status_line(?array $ay, bool $isOpen): array
{
    if ($ay === null) {
        return ['schedule', 'No window scheduled yet.'];
    }

    $opensAt  = $ay['enlistment_opens_at'] ?? null;
    $closesAt = $ay['enlistment_closes_at'] ?? null;
    $now      = time();

    if ($isOpen) {
        return $closesAt !== null
            ? ['hourglass_top', 'Open now — closes ' . ew_relative_time(strtotime($closesAt), $now) . '.']
            : ['hourglass_top', 'Open now — no closing date set.'];
    }

    if ($opensAt !== null && strtotime($opensAt) > $now) {
        return ['event_upcoming', 'Opens ' . ew_relative_time(strtotime($opensAt), $now) . '.'];
    }

    if ($closesAt !== null && strtotime($closesAt) <= $now) {
        return ['event_busy', 'Closed ' . ew_relative_time(strtotime($closesAt), $now) . '.'];
    }

    return ['schedule', 'No window scheduled yet.'];
}

function ew_relative_time(int $target, int $now): string
{
    $diff   = abs($target - $now);
    $future = $target > $now;

    $unit = match (true) {
        $diff < 3600       => [round($diff / 60), 'minute'],
        $diff < 86400      => [round($diff / 3600), 'hour'],
        default            => [round($diff / 86400), 'day'],
    };
    [$count, $label] = $unit;
    $count = max(1, (int) $count);
    $text  = $count . ' ' . $label . ($count === 1 ? '' : 's');

    return $future ? 'in ' . $text : $text . ' ago';
}
endif;
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/enlistment-window.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/enlistment-window.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Enlistment Window</h2><p class="ac-page-header__sub">Open or close pre-registration for the upcoming term, while the active term is still running.</p></div>
</div>

<?php if ($ay === null): ?>
    <div class="ac-empty-block">No upcoming academic term exists yet. Create one under Admin &rarr; Academic Years before opening enlistment for it.</div>
<?php else: ?>
    <div class="ew-section">
        <?php if (count($terms) > 1): ?>
            <form method="get" action="<?= site_url('chairperson/enlistment-window') ?>" class="ew-term-field">
                <label for="ewTermSelect">Term</label>
                <select id="ewTermSelect" name="ay_id" onchange="this.form.submit()">
                    <?php foreach ($terms as $t): ?>
                        <option value="<?= (int) $t['ay_id'] ?>" <?= (int) $ay['ay_id'] === (int) $t['ay_id'] ? 'selected' : '' ?>><?= esc($t['label']) ?> &middot; <?= esc($t['semester']) ?> Semester</option>
                    <?php endforeach ?>
                </select>
            </form>
        <?php endif ?>

        <?php [$statusIcon, $statusText] = ew_status_line($ay, $isOpen); ?>
        <div class="ew-card">
            <div class="ew-card-head">
                <strong><?= esc($ay['label']) ?> &middot; <?= esc($ay['semester']) ?> <span class="ew-upcoming">(upcoming)</span></strong>
                <span class="ew-pill <?= $isOpen ? 'ew-pill--open' : 'ew-pill--closed' ?>">Enlistment <?= $isOpen ? 'OPEN' : 'CLOSED' ?></span>
            </div>

            <div class="ew-status-line">
                <span class="material-symbols-outlined" aria-hidden="true"><?= esc($statusIcon) ?></span>
                <span><?= esc($statusText) ?></span>
            </div>

            <form method="post" action="<?= site_url('chairperson/enlistment-window/update') ?>" class="ew-card-body">
                <?= csrf_field() ?>
                <input type="hidden" name="ay_id" value="<?= (int) $ay['ay_id'] ?>">
                <div class="ew-datefield">
                    <label for="ewOpensAt">Opens</label>
                    <div class="ew-datefield-control">
                        <span class="material-symbols-outlined" aria-hidden="true">event_available</span>
                        <input id="ewOpensAt" type="datetime-local" name="enlistment_opens_at" value="<?= $ay['enlistment_opens_at'] ? esc(str_replace(' ', 'T', $ay['enlistment_opens_at'])) : '' ?>">
                    </div>
                </div>
                <div class="ew-datefield">
                    <label for="ewClosesAt">Closes</label>
                    <div class="ew-datefield-control">
                        <span class="material-symbols-outlined" aria-hidden="true">event_busy</span>
                        <input id="ewClosesAt" type="datetime-local" name="enlistment_closes_at" value="<?= $ay['enlistment_closes_at'] ? esc(str_replace(' ', 'T', $ay['enlistment_closes_at'])) : '' ?>">
                    </div>
                </div>
                <button type="submit" class="ac-btn ac-btn-primary ew-save">Save Window</button>
            </form>
        </div>
    </div>
    <script>(function () { var t = document.getElementById('ewTermSelect'); if (t && window.enhanceSelect) window.enhanceSelect(t); })();</script>

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

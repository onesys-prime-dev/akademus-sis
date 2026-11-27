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
<link rel="stylesheet" href="<?= base_url('assets/css/components/datetime-picker.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/components/datetime-picker.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Enlistment Window</h2><p class="ac-page-header__sub">Open or close pre-registration for the upcoming term, while the active term is still running.</p></div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<?php if (session()->has('errors')): ?>
    <div class="ac-alert" style="background:var(--color-danger-soft); border-color:var(--color-danger-border); margin-bottom:16px;">
        <span class="material-symbols-outlined" style="color:var(--color-danger);" aria-hidden="true">error</span>
        <div class="ac-alert__text" style="color:var(--color-danger);">
            <?php foreach ((array) session('errors') as $error): ?>
                <p><?= esc($error) ?></p>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<?php if ($ay === null): ?>
    <div class="ac-empty-block">No upcoming academic term exists yet. Create one under Admin &rarr; Academic Years before opening enlistment for it.</div>
<?php else: ?>
    <div class="ew-layout">
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

            <?php if ($windowMode === 'registrar_only'): ?>
                <p class="ew-readonly-note">Enlistment windows are controlled by the Registrar.</p>
            <?php else: ?>
                <?php $outerSet = $ay['enlistment_opens_at'] !== null && $ay['enlistment_closes_at'] !== null; ?>
                <div class="ew-program-windows">
                    <?php if (! $outerSet): ?>
                        <p class="ew-readonly-note">The Registrar hasn't opened enlistment for this term yet — your program's window can't be set until they do.</p>
                    <?php else: ?>
                        <p class="ew-readonly-note">Registrar window: <?= esc(date('M j, Y g:i A', strtotime($ay['enlistment_opens_at']))) ?> &ndash; <?= esc(date('M j, Y g:i A', strtotime($ay['enlistment_closes_at']))) ?></p>
                    <?php endif ?>

                    <?php foreach ($programs as $p): ?>
                        <?php $pw = $programWindows[(int) $p['program_id']] ?? null; ?>
                        <form method="post" action="<?= site_url('chairperson/enlistment-window/update-program-window') ?>" class="ew-card-body ew-program-window">
                            <?= csrf_field() ?>
                            <input type="hidden" name="ay_id" value="<?= (int) $ay['ay_id'] ?>">
                            <input type="hidden" name="program_id" value="<?= (int) $p['program_id'] ?>">
                            <div class="ew-program-window__name"><?= esc($p['program_name']) ?></div>
                            <div class="ew-datefield">
                                <label for="ewOpensAt<?= (int) $p['program_id'] ?>">Opens</label>
                                <div class="ew-datefield-control">
                                    <span class="material-symbols-outlined" aria-hidden="true">event_available</span>
                                    <input id="ewOpensAt<?= (int) $p['program_id'] ?>" type="datetime-local" name="enlistment_opens_at" value="<?= $pw && $pw['enlistment_opens_at'] ? esc(str_replace(' ', 'T', $pw['enlistment_opens_at'])) : '' ?>" <?= $outerSet ? '' : 'disabled' ?>>
                                </div>
                            </div>
                            <div class="ew-datefield">
                                <label for="ewClosesAt<?= (int) $p['program_id'] ?>">Closes</label>
                                <div class="ew-datefield-control">
                                    <span class="material-symbols-outlined" aria-hidden="true">event_busy</span>
                                    <input id="ewClosesAt<?= (int) $p['program_id'] ?>" type="datetime-local" name="enlistment_closes_at" value="<?= $pw && $pw['enlistment_closes_at'] ? esc(str_replace(' ', 'T', $pw['enlistment_closes_at'])) : '' ?>" <?= $outerSet ? '' : 'disabled' ?>>
                                </div>
                            </div>
                            <button type="submit" class="ac-btn ac-btn-primary ew-save" <?= $outerSet ? '' : 'disabled' ?>>Save Window</button>
                        </form>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
        <script>(function () { var t = document.getElementById('ewTermSelect'); if (t && window.enhanceSelect) window.enhanceSelect(t); })();</script>
        </div>

        <div class="ac-card ew-offerings">
        <h3 class="ac-dept-name ew-offerings__title">Offered Subjects</h3>
        <p class="ew-offerings__hint">
            Every curriculum subject for <strong><?= esc($ay['semester']) ?> Semester, <?= esc($ay['label']) ?></strong> on the program/year/section you pick is pre-checked &mdash; uncheck anything you're <strong>not</strong> offering, then save.
        </p>

        <?php if ($programs === []): ?>
            <div class="ac-empty-block">You have no program assigned for scheduling.</div>
        <?php else: ?>
            <form method="get" action="<?= site_url('chairperson/enlistment-window') ?>" class="ew-offerings__filters">
                <input type="hidden" name="ay_id" value="<?= (int) $ay['ay_id'] ?>">
                <?php if (count($programs) > 1): ?>
                    <div class="ew-offerings__field">
                        <label for="ewProgramSelect">Program</label>
                        <select id="ewProgramSelect" name="program_id" class="ac-input" onchange="this.form.submit()">
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= (int) $p['program_id'] ?>" <?= $selectedProgram === (int) $p['program_id'] ? 'selected' : '' ?>><?= esc($p['program_name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="program_id" value="<?= (int) $programs[0]['program_id'] ?>">
                <?php endif ?>

                <div class="ew-offerings__field">
                    <label for="ewYearLevelSelect">Year Level</label>
                    <select id="ewYearLevelSelect" name="year_level" class="ac-input" onchange="this.form.submit()">
                        <option value="">Select&hellip;</option>
                        <?php foreach ($yearLevels as $level => $label): ?>
                            <option value="<?= $level ?>" <?= $selectedYear === $level ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="ew-offerings__field ew-offerings__section-field">
                    <label for="ewSectionSelect">Section</label>
                    <select id="ewSectionSelect" name="section" class="ac-input" onchange="this.form.submit()">
                        <option value="">Select&hellip;</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?= esc($s) ?>" <?= $selectedSection === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>

                <script>(function () {
                    ['ewProgramSelect', 'ewYearLevelSelect', 'ewSectionSelect'].forEach(function (id) {
                        var el = document.getElementById(id);
                        if (el && window.enhanceSelect) window.enhanceSelect(el);
                    });
                })();</script>

                <button type="submit" class="ac-btn ac-btn-secondary">Load Subjects</button>
            </form>

            <?php if ($selectedYear >= 1 && $selectedSection !== ''): ?>
                <?php if ($subjects === []): ?>
                    <div class="ac-empty-block ew-offerings__empty">No curriculum subjects found for this program/year/semester.</div>
                <?php else: ?>
                    <form method="post" action="<?= site_url('chairperson/enlistment-window/save-offerings') ?>" class="ew-offerings__table-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ay_id" value="<?= (int) $ay['ay_id'] ?>">
                        <input type="hidden" name="program_id" value="<?= $selectedProgram ?>">
                        <input type="hidden" name="year_level" value="<?= $selectedYear ?>">
                        <input type="hidden" name="section" value="<?= esc($selectedSection) ?>">

                        <div class="ew-offerings__table-scroll">
                            <table class="ac-table ew-offerings__table">
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
                        </div>

                        <button type="submit" class="ac-btn ac-btn-primary ew-offerings__save">Save Offerings</button>
                    </form>
                <?php endif ?>
            <?php else: ?>
                <div class="ew-offerings__placeholder">
                    <span class="material-symbols-outlined" aria-hidden="true">checklist</span>
                    <p>Pick a program, year level, and section above, then <strong>Load Subjects</strong> to choose what's offered this term.</p>
                </div>
            <?php endif ?>
        <?php endif ?>
        </div>
    </div>

    <div class="ac-card ew-overrides">
        <h3 class="ac-dept-name ew-overrides__title">Student Overrides</h3>
        <p class="ew-overrides__hint">Let one student enlist for <strong><?= esc($ay['semester']) ?> Semester, <?= esc($ay['label']) ?></strong> regardless of the window above &mdash; for an irregular student who needs to enlist outside the normal schedule.</p>

        <form method="get" action="<?= site_url('chairperson/enlistment-window') ?>" class="ew-overrides__search">
            <input type="hidden" name="ay_id" value="<?= (int) $ay['ay_id'] ?>">
            <input type="search" name="override_q" value="<?= esc($overrideQuery, 'attr') ?>" placeholder="Search by name or student number&hellip;">
            <button type="submit" class="ac-btn ac-btn-secondary">Search</button>
        </form>

        <?php if ($overrideQuery !== ''): ?>
            <?php if ($overrideCandidates === []): ?>
                <div class="ac-empty-block">No active student matches "<?= esc($overrideQuery) ?>" in your programs.</div>
            <?php else: ?>
                <ul class="ew-overrides__candidates">
                    <?php foreach ($overrideCandidates as $c): ?>
                        <li>
                            <span><?= esc($c['last_name']) ?>, <?= esc($c['first_name']) ?> &middot; <?= esc($c['student_number']) ?> &middot; <?= esc($c['program_name'] ?? '') ?></span>
                            <button type="button" class="ac-btn ac-btn-primary" data-grant-window-override="<?= (int) $c['student_id'] ?>">Unlock</button>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        <?php endif ?>

        <h4 class="ew-overrides__subhead">Active overrides this term</h4>
        <?php if ($activeOverrides === []): ?>
            <div class="ac-empty-block">No student has an active override for this term.</div>
        <?php else: ?>
            <ul class="ew-overrides__active">
                <?php foreach ($activeOverrides as $o): ?>
                    <?php
                        $grantedByName = trim(($o['granted_by_last_name'] ?? '') . ' ' . ($o['granted_by_first_name'] ?? ''));
                        $grantedByWho  = $grantedByName !== '' ? $grantedByName : ucfirst($o['granted_by_role']);
                    ?>
                    <li>
                        <span>
                            <?= esc($o['last_name']) ?>, <?= esc($o['first_name']) ?> &middot; <?= esc($o['student_number']) ?> &middot; "<?= esc($o['reason']) ?>"
                            <br><small>Granted by <?= esc($grantedByWho) ?> &middot; <?= esc(date('M j, Y g:i A', strtotime($o['created_at']))) ?></small>
                        </span>
                        <button type="button" class="ac-btn ac-btn-secondary" data-revoke-window-override="<?= (int) $o['id'] ?>">Revoke</button>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/datetime-picker.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/datetime-picker.js') ?>"></script>
<script>
    document.querySelectorAll('.ew-datefield input[type="datetime-local"]').forEach(function (input) {
        if (window.enhanceDateTimeField) window.enhanceDateTimeField(input);
    });
</script>
<?php if ($ay !== null): ?>
<script type="application/json" id="ewConfig"><?= json_encode([
    'grantUrl'  => site_url('chairperson/enlistment-window/grant-override'),
    'revokeUrl' => site_url('chairperson/enlistment-window/revoke-override'),
    'ayId'      => (int) $ay['ay_id'],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= base_url('assets/js/enlistment-window.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/enlistment-window.js') ?>"></script>
<?php endif ?>
<?= $this->endSection() ?>

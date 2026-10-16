<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'student-progress';
$pageTitle     = 'Student Progress';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

$currentParams = [
    'program_id' => $programId !== 0 ? $programId : null,
    'view'       => $view !== 'all' ? $view : null,
    'q'          => $q !== '' ? $q : null,
];
$baseQuery = static function (array $overrides) use ($currentParams): string {
    $params = array_filter(array_merge($currentParams, $overrides), static fn ($v) => $v !== null && $v !== '');

    return site_url('chairperson/student-progress') . ($params !== [] ? '?' . http_build_query($params) : '');
};

?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/student-progress.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/student-progress.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="sp-page" id="studentProgress">
    <header class="sp-heading">
        <div>
            <span class="sp-eyebrow">ACADEMIC MONITORING</span>
            <h1>Student Progress</h1>
            <p>Curriculum completion, deficiencies, and graduation candidates for your authorized programs.</p>
        </div>
    </header>

    <?php if ($noUnits): ?>
        <section class="sp-card sp-empty"><span class="material-symbols-outlined" aria-hidden="true">domain_disabled</span><div><h3>No department assigned</h3><p>Contact an administrator to assign a department to your account.</p></div></section>
    <?php else: ?>
        <section class="sp-card sp-filters">
            <?php if (count($programs) > 1): ?>
            <div class="sp-filter">
                <label for="spProgram">Program</label>
                <select id="spProgram" onchange="location.href=this.value">
                    <option value="<?= esc($baseQuery(['program_id' => null])) ?>" <?= $programId === 0 ? 'selected' : '' ?>>All programs</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?= esc($baseQuery(['program_id' => $p['program_id']])) ?>" <?= $programId === $p['program_id'] ? 'selected' : '' ?>><?= esc($p['program_code']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <?php endif ?>
            <form class="sp-search" method="get" action="<?= site_url('chairperson/student-progress') ?>">
                <?php if ($programId !== 0): ?><input type="hidden" name="program_id" value="<?= $programId ?>"><?php endif ?>
                <?php if ($view !== 'all'): ?><input type="hidden" name="view" value="<?= esc($view, 'attr') ?>"><?php endif ?>
                <span class="material-symbols-outlined" aria-hidden="true">search</span>
                <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Search by name or student number…">
            </form>
        </section>

        <nav class="sp-tabs" aria-label="Filter students">
            <a class="<?= $view === 'all' ? 'is-active' : '' ?>" href="<?= esc($baseQuery(['view' => null])) ?>">All students</a>
            <a class="<?= $view === 'candidates' ? 'is-active' : '' ?>" href="<?= esc($baseQuery(['view' => 'candidates'])) ?>">Graduation candidates <span class="sp-tab-count"><?= $candidateCount ?></span></a>
            <a class="<?= $view === 'deficient' ? 'is-active' : '' ?>" href="<?= esc($baseQuery(['view' => 'deficient'])) ?>">Needs attention <span class="sp-tab-count"><?= $deficientCount ?></span></a>
        </nav>

        <section class="sp-card sp-table-card">
            <?php if ($students === []): ?>
                <div class="sp-empty-inline">
                    <span class="material-symbols-outlined" aria-hidden="true">search_off</span>
                    <h3>No matching students</h3>
                    <p><?= $view === 'candidates' ? 'No student in this scope is ready to graduate yet.' : ($view === 'deficient' ? 'No student in this scope currently has a deficiency.' : 'Try another search or program.') ?></p>
                </div>
            <?php else: ?>
                <div class="sp-table-scroll">
                    <table class="sp-table">
                        <thead>
                            <tr>
                                <th scope="col">Student</th>
                                <th scope="col">Program</th>
                                <th scope="col">Year &amp; Section</th>
                                <th scope="col">Curriculum</th>
                                <th scope="col">Progress</th>
                                <th scope="col">Remaining</th>
                                <th scope="col">Status</th>
                                <th scope="col"><span class="sp-sr-only">Details</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td><strong><?= esc($s['last_name'] . ', ' . $s['first_name']) ?></strong><small><?= esc($s['student_number']) ?></small></td>
                                    <td><?= esc($s['program_code'] ?? '—') ?></td>
                                    <td><?= $s['year_level'] !== null ? esc($s['year_level'] . ' · ' . $s['section']) : '—' ?></td>
                                    <td><?= esc($s['curriculum_code'] ?? '—') ?></td>
                                    <td class="sp-progress-cell">
                                        <?php if ($s['completion_pct'] !== null): ?>
                                            <div class="sp-progress-bar"><span style="width: <?= (float) $s['completion_pct'] ?>%"></span></div>
                                            <small><?= esc(rtrim(rtrim(number_format((float) $s['completion_pct'], 1), '0'), '.')) ?>%</small>
                                        <?php else: ?>
                                            <small>No curriculum assigned</small>
                                        <?php endif ?>
                                    </td>
                                    <td><?= (int) $s['remaining_count'] ?> subject<?= $s['remaining_count'] === 1 ? '' : 's' ?><?php if ($s['in_progress_count'] > 0): ?><small><?= (int) $s['in_progress_count'] ?> in progress</small><?php endif ?></td>
                                    <td>
                                        <?php if ($s['is_candidate']): ?>
                                            <span class="sp-badge sp-badge--candidate">Graduation candidate</span>
                                        <?php elseif ($s['deficiency_count'] > 0): ?>
                                            <span class="sp-badge sp-badge--deficient"><?= (int) $s['deficiency_count'] ?> deficienc<?= $s['deficiency_count'] === 1 ? 'y' : 'ies' ?></span>
                                        <?php else: ?>
                                            <span class="sp-badge sp-badge--ontrack">On track</span>
                                        <?php endif ?>
                                    </td>
                                    <td><button type="button" class="sp-view-btn" data-student-id="<?= (int) $s['student_id'] ?>">View<span class="material-symbols-outlined" aria-hidden="true">chevron_right</span></button></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </section>

        <p class="sp-note">Progress is calculated against each student's assigned curriculum. A student with no curriculum assigned cannot show a completion percentage — assign one from Student Records (Registrar) or Curriculum Builder first.</p>

        <dialog class="sp-dialog" id="spDetailDialog" aria-labelledby="spDetailName">
            <header class="sp-dialog-header">
                <div><span class="sp-eyebrow" id="spDetailMeta"></span><h2 id="spDetailName"></h2></div>
                <button type="button" class="sp-icon-button" id="spCloseDetail" aria-label="Close"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
            </header>
            <div class="sp-dialog-body" id="spDetailBody"></div>
        </dialog>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script type="application/json" id="spConfig"><?= json_encode([
    'detailUrl' => site_url('chairperson/student-progress/detail'),
    'grantUrl'  => site_url('chairperson/student-progress/grant-override'),
    'revokeUrl' => site_url('chairperson/student-progress/revoke-override'),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= base_url('assets/js/student-progress.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/student-progress.js') ?>"></script>
<?= $this->endSection() ?>

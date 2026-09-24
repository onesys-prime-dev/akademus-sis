<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'grade-approvals';
$pageTitle     = $context['subject_code'] . ' · ' . $context['program'] . ' ' . $context['year_level'] . '-' . $context['section'];
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = [
    '' => [
        'overview'        => ['label' => 'Dashboard',        'icon' => 'home',           'url' => 'chairperson/overview'],
        'profile'         => ['label' => 'Profile',           'icon' => 'person',          'url' => 'chairperson/profile'],
        'schedule'        => ['label' => 'Schedule Plotting', 'icon' => 'calendar_month',  'url' => 'chairperson/dashboard'],
        'sections'        => ['label' => 'Sections',          'icon' => 'groups',          'url' => 'chairperson/sections'],
        'offerings'       => ['label' => 'Merged Classes',    'icon' => 'merge_type',      'url' => 'chairperson/offerings'],
        'grade-approvals' => ['label' => 'Grade Approvals',   'icon' => 'grade',           'url' => 'chairperson/grade-approvals'],
        'curriculum'      => ['label' => 'Curriculum',        'icon' => 'menu_book',       'url' => 'chairperson/curriculum'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    .status-pill { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); }
    .status-pill.submitted { background: #dff8fb; color: #164e63; }
    .status-pill.locked { background: var(--color-success-soft); color: #14532d; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title"><?= esc($context['subject_code']) ?></h2>
        <p class="ac-page-header__sub"><?= esc($context['program']) ?> <?= esc($context['year_level']) ?>-<?= esc($context['section']) ?></p>
    </div>
</div>

<div id="feedback" class="flash" style="display:none;"></div>

<div class="panel">
    <?php if ($offering !== null): ?>
        <p class="panel-sub">This is a merged class combining multiple sections' rosters (<?= count($roster) ?> students).</p>
    <?php endif ?>

    <?php if (empty($roster)): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">group_off</div>
            <p>No students are enrolled in this class.</p>
        </div>
    <?php else: ?>
        <table class="grade-table">
            <thead>
                <tr><th>Student</th><th>Grade</th><th>Remarks</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php $allSubmitted = true; ?>
                <?php foreach ($roster as $row): ?>
                    <?php $g = $row['grade']; if ($g === null || $g['status'] !== 'submitted') { $allSubmitted = false; } ?>
                    <tr>
                        <td><?= esc($row['username']) ?></td>
                        <td><?= $g !== null ? esc($g['grade_value']) : '—' ?></td>
                        <td><?= $g !== null && $g['remarks'] !== null ? esc($g['remarks']) : '—' ?></td>
                        <td><?= $g !== null ? '<span class="status-pill ' . esc($g['status']) . '">' . esc(ucfirst($g['status'])) . '</span>' : '—' ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>

        <div style="margin-top:16px;">
            <?php if ($allSubmitted): ?>
                <button type="button" class="btn btn-primary" onclick="lockClass()">Lock All &amp; Make Official</button>
            <?php else: ?>
                <p class="panel-sub">Not every student has a submitted grade yet — nothing to lock.</p>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const CONTEXT = <?= json_encode($context) ?>;

    function showFeedback(message, isError) {
        const el = document.getElementById('feedback');
        el.style.display = 'block';
        el.className = 'flash ' + (isError ? 'error' : 'success');
        el.textContent = message;
    }

    async function lockClass() {
        if (!await acConfirm('Lock these grades? Once locked, changes require a formal correction request.')) return;

        const res = await fetch('<?= site_url('chairperson/grade-approvals/lock') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(CONTEXT),
        });
        const result = await res.json();

        if (!result.ok) {
            showFeedback(result.error || 'Could not lock.', true);
            return;
        }

        showFeedback('Grades locked and now official.', false);
        setTimeout(() => { window.location.href = '<?= site_url('chairperson/grade-approvals') ?>'; }, 800);
    }
</script>
<?= $this->endSection() ?>

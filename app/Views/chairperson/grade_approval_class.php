<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'grade-approvals';
$pageTitle     = $context['subject_code'] . ' · ' . $context['program'] . ' ' . $context['year_level'] . '-' . $context['section'];
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

<div class="ga-page">
<a class="ga-back" href="<?= site_url('chairperson/grade-approvals') ?>"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Grade approvals</a>
<div class="ga-heading">
    <div>
        <h1><?= esc($context['subject_code']) ?></h1>
        <p><?= esc($context['program']) ?> &middot; <?= esc($context['year_level']) ?> &middot; Section <?= esc($context['section']) ?></p>
    </div>
</div>

<div id="feedback" class="ga-feedback" role="status" aria-live="polite" hidden></div>

<div class="ga-card">
<div class="ga-review-meta"><strong><?= esc($context['academic_year']) ?></strong><span><?= count($roster) ?> students</span><span>Grade review</span></div>
    <?php if ($offering !== null): ?>
        <p class="ga-policy">This is a merged class combining multiple sections' rosters (<?= count($roster) ?> students).</p>
    <?php endif ?>

    <?php if (empty($roster)): ?>
        <div class="ga-empty">
            <div class="material-symbols-outlined">group_off</div>
            <p>No students are enrolled in this class.</p>
        </div>
    <?php else: ?>
        <div class="ga-table-scroll"><table class="ga-table">
            <thead>
                <tr><th scope="col">Student</th><th scope="col">Grade</th><th scope="col">Remarks</th><th scope="col">Status</th></tr>
            </thead>
            <tbody>
                <?php $allSubmitted = true; ?>
                <?php foreach ($roster as $row): ?>
                    <?php $g = $row['grade']; if ($g === null || $g['status'] !== 'submitted') { $allSubmitted = false; } ?>
                    <tr>
                        <td><?= esc($row['username']) ?></td>
                        <td><?= $g !== null ? esc($g['grade_value']) : '—' ?></td>
                        <td><?= $g !== null && $g['remarks'] !== null ? esc($g['remarks']) : '—' ?></td>
                        <td><?= $g !== null ? '<span class="ga-status ' . esc($g['status']) . '">' . esc(ucfirst($g['status'])) . '</span>' : '—' ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table></div>

        <div class="ga-lock-footer">
            <?php if ($allSubmitted): ?>
                <p>Confirm that every grade is correct. Locking makes these grades official and visible to students.</p><button type="button" class="ga-button ga-button--primary" id="gaLockButton" onclick="lockClass()"><span class="material-symbols-outlined" aria-hidden="true">lock</span>Lock grades</button>
            <?php else: ?>
                <p class="panel-sub">Not every student has a submitted grade yet — nothing to lock.</p>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const CONTEXT = <?= json_encode($context, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function showFeedback(message, isError) {
        const el = document.getElementById('feedback');
        el.hidden = false;
        el.className = 'ga-feedback' + (isError ? ' is-error' : '');
        el.textContent = message;
    }

    let lockBusy = false;
    async function lockClass() {
        if (lockBusy) return;
        lockBusy = true;
        const button = document.getElementById('gaLockButton');
        const label = button.innerHTML;
        try {
            if (!await acConfirm('Lock these grades? Once locked, changes require a formal correction request.')) return;
            button.disabled = true; button.textContent = 'Locking grades...';
            const res = await fetch('<?= site_url('chairperson/grade-approvals/lock') ?>', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(CONTEXT),
            });
            const result = await res.json().catch(() => null);
            if (!res.ok || !result?.ok) throw new Error(result?.error || 'Could not lock grades. Please try again.');
            showFeedback('Grades locked and now official.', false);
            button.textContent = 'Grades locked';
            setTimeout(() => { window.location.href = '<?= site_url('chairperson/grade-approvals') ?>'; }, 800);
        } catch (error) { showFeedback(error.message, true); }
        finally {
            if (button.textContent !== 'Grades locked') { lockBusy = false; button.disabled = false; button.innerHTML = label; }
        }
    }
</script>
<?= $this->endSection() ?>

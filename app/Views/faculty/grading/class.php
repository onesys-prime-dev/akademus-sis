<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'grading';
$pageTitle     = $context['subject_code'] . ' · ' . $context['program'] . ' ' . $context['year_level'] . '-' . $context['section'];
$brandSub      = 'Faculty Portal';
$userName      = $user['name'];
$userRoleLabel = 'Faculty';
$navGroups     = [
    '' => [
        'dashboard'    => ['label' => 'Dashboard',    'icon' => 'dashboard',        'url' => 'faculty/dashboard'],
        'profile'      => ['label' => 'Profile',      'icon' => 'person',           'url' => 'faculty/profile'],
        'schedule'     => ['label' => 'Schedule',     'icon' => 'calendar_month',   'url' => 'faculty/schedule'],
        'availability' => ['label' => 'Availability', 'icon' => 'event_available',  'url' => 'faculty/availability'],
        'grading'      => ['label' => 'Grading',      'icon' => 'grade',            'url' => 'faculty/grading'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    table.grade-table input[type=text] { width: 90px; padding: 6px 8px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); }
    table.grade-table input[type=text].remarks { width: 160px; }
    .status-pill { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); }
    .status-pill.locked { background: var(--color-success-soft); color: #14532d; }
    .status-pill.submitted { background: #dff8fb; color: #164e63; }
    .status-pill.draft { background: var(--color-warning-soft); color: #713f12; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title"><?= esc($context['subject_code']) ?></h2>
        <p class="ac-page-header__sub"><?= esc($context['program']) ?> <?= esc($context['year_level']) ?>-<?= esc($context['section']) ?></p>
    </div>
</div>

<div class="panel" id="feedback" style="display:none;"></div>

<div class="panel">
    <?php if ($offering !== null): ?>
        <p class="panel-sub">This class is merged with other sections into one shared roster (<?= count($roster) ?> students).</p>
    <?php endif ?>

    <?php if (empty($roster)): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">group_off</div>
            <p>No students are enrolled in this class yet.</p>
        </div>
    <?php else: ?>
        <table class="grade-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Grade</th>
                    <th>Remarks</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roster as $row): ?>
                    <?php $g = $row['grade']; $locked = $g !== null && $g['status'] === 'locked'; ?>
                    <tr data-enrollment-id="<?= esc($row['enrollment_id']) ?>">
                        <td><?= esc($row['username']) ?></td>
                        <td>
                            <input type="text" class="grade-input" value="<?= $g !== null ? esc($g['grade_value']) : '' ?>" <?= $locked ? 'disabled' : '' ?>>
                        </td>
                        <td>
                            <input type="text" class="remarks-input" value="<?= $g !== null ? esc($g['remarks']) : '' ?>" <?= $locked ? 'disabled' : '' ?>>
                        </td>
                        <td>
                            <?php if ($g !== null): ?>
                                <span class="status-pill <?= esc($g['status']) ?>"><?= esc(ucfirst($g['status'])) ?></span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php if ($locked): ?>
                                <button type="button" class="btn btn-secondary" onclick="openRequestChange(<?= esc($g['grade_id']) ?>, '<?= esc($g['grade_value'], 'js') ?>')">Request Change</button>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>

        <div style="display:flex; gap:10px; margin-top:16px;">
            <button type="button" class="btn btn-secondary" onclick="saveDraft()">Save Draft</button>
            <button type="button" class="btn btn-primary" onclick="submitClass()">Submit for Approval</button>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const CONTEXT = <?= json_encode($context) ?>;

    async function postJSON(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        return res.json();
    }

    function showFeedback(message, isError) {
        const el = document.getElementById('feedback');
        el.style.display = 'block';
        el.style.color = isError ? '#a11414' : '#056945';
        el.textContent = message;
    }

    function collectGrades() {
        return Array.from(document.querySelectorAll('tr[data-enrollment-id]')).map((row) => ({
            enrollment_id: parseInt(row.dataset.enrollmentId, 10),
            value: row.querySelector('.grade-input').value.trim(),
            remarks: row.querySelector('.remarks-input').value.trim(),
        }));
    }

    async function saveDraft() {
        const result = await postJSON('<?= site_url('faculty/grading/save') ?>', { ...CONTEXT, grades: collectGrades() });
        if (!result.ok) {
            showFeedback((result.errors || [result.error]).join(' '), true);
            return;
        }
        showFeedback('Grades saved.', false);
    }

    async function submitClass() {
        if (!confirm('Submit this class\'s grades for chairperson approval? You can still edit until it is locked.')) return;

        const saveResult = await postJSON('<?= site_url('faculty/grading/save') ?>', { ...CONTEXT, grades: collectGrades() });
        if (!saveResult.ok) {
            showFeedback((saveResult.errors || [saveResult.error]).join(' '), true);
            return;
        }

        const result = await postJSON('<?= site_url('faculty/grading/submit') ?>', CONTEXT);
        if (!result.ok) {
            showFeedback(result.error || 'Could not submit.', true);
            return;
        }

        showFeedback('Submitted for chairperson approval.', false);
        setTimeout(() => location.reload(), 800);
    }

    async function openRequestChange(gradeId, currentValue) {
        const newValue = prompt(`Current value: ${currentValue}\nEnter the corrected value:`);
        if (!newValue) return;
        const reason = prompt('Reason for this correction (required):');
        if (!reason) return;

        const result = await postJSON('<?= site_url('faculty/grading/request-change') ?>', {
            grade_id: gradeId,
            requested_value: newValue.trim(),
            reason: reason.trim(),
        });

        if (!result.ok) {
            showFeedback(result.error || 'Could not file the request.', true);
            return;
        }

        showFeedback('Change request submitted to the chairperson.', false);
    }
</script>
<?= $this->endSection() ?>

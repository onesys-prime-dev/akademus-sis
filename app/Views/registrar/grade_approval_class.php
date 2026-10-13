<?= $this->extend('layouts/admin_console') ?>

<?php
$pageTitle = $context['subject_code'] . ' · ' . $context['program'] . ' ' . $context['year_level'] . '-' . $context['section'];
$this->setData(['pageTitle' => $pageTitle] + \App\Libraries\RegistrarPortal::shell('grade-approvals'));

$studentRecords = array_map(static function (array $row): array {
    $grade = $row['grade'];

    return [
        'username' => $row['username'],
        'grade'    => $grade === null ? null : [
            'value'   => $grade['grade_value'],
            'remarks' => $grade['remarks'],
            'status'  => $grade['status'],
        ],
    ];
}, $roster);
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/grade-approvals.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/grade-approvals.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ga-page">
<a class="ga-back" href="<?= site_url('registrar/grade-approvals') ?>"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Grade approvals</a>
<div class="ga-heading">
    <div>
        <span class="ga-eyebrow">GRADE REVIEW</span>
        <h1><?= esc($context['subject_code']) ?></h1>
        <p><?= esc($context['program']) ?> &middot; <?= esc($context['year_level']) ?> &middot; Section <?= esc($context['section']) ?></p>
    </div>
</div>

<div id="feedback" class="ga-feedback" role="status" aria-live="polite" hidden></div>

<div class="ga-card ga-review-card">
<div class="ga-review-meta"><div><span>Academic term</span><strong><?= esc($context['academic_year']) ?></strong></div><div><span>Class roster</span><strong><?= count($roster) ?> <?= count($roster) === 1 ? 'student' : 'students' ?></strong></div><div><span>Workflow</span><strong>Grade review</strong></div></div>
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
                <tr><th scope="col">Student</th><th scope="col">Grade</th><th scope="col">Remarks</th><th scope="col">Status</th><th scope="col"><span class="ga-sr-only">Student record</span></th></tr>
            </thead>
            <tbody>
                <?php $allDeanNoted = true; $allRegistrarApproved = true; $allLocked = true; ?>
                <?php foreach ($roster as $recordIndex => $row): ?>
                    <?php
                        $g = $row['grade'];
                        if ($g === null || $g['status'] !== 'dean_noted') { $allDeanNoted = false; }
                        if ($g === null || $g['status'] !== 'registrar_approved') { $allRegistrarApproved = false; }
                        if ($g === null || $g['status'] !== 'locked') { $allLocked = false; }
                    ?>
                    <tr>
                        <td data-label="Student"><button type="button" class="ga-student-button" data-student-record="<?= $recordIndex ?>"><?= esc($row['username']) ?><span>View grade record</span></button></td>
                        <td data-label="Grade" class="ga-grade-value"><?= $g !== null ? esc($g['grade_value']) : '—' ?></td>
                        <td data-label="Remarks"><?= $g !== null && $g['remarks'] !== null ? esc($g['remarks']) : '—' ?></td>
                        <td data-label="Status">
                            <?= $g !== null ? '<span class="ga-status ' . esc($g['status']) . '">' . esc(ucfirst($g['status'])) . '</span>' : '—' ?>
                        </td>
                        <td class="ga-row-action"><button type="button" class="ga-button ga-record-button" data-student-record="<?= $recordIndex ?>">View record<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></button></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table></div>

        <div class="ga-lock-footer">
            <?php if ($allDeanNoted): ?>
                <p>Confirm that every grade is correct. Approving sends this class on for the final lock.</p><button type="button" class="ga-button ga-button--primary" id="gaApproveButton" onclick="approveClass()"><span class="material-symbols-outlined" aria-hidden="true">task_alt</span>Approve grades</button>
            <?php elseif ($allRegistrarApproved): ?>
                <p>Confirm that every grade is correct. Locking makes these grades official and visible to students.</p><button type="button" class="ga-button ga-button--primary" id="gaLockButton" onclick="lockClass()"><span class="material-symbols-outlined" aria-hidden="true">lock</span>Lock grades</button>
            <?php elseif ($allLocked): ?>
                <p class="ga-official-note"><span class="material-symbols-outlined" aria-hidden="true">verified</span>All grades in this class are locked, official, and visible to students.</p>
            <?php else: ?>
                <p class="panel-sub">Not every student has a dean-noted grade yet — nothing to approve or lock.</p>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>

<dialog class="ga-dialog ga-record-dialog" id="gaStudentDialog" aria-labelledby="gaStudentDialogTitle">
    <header class="ga-record-header"><div><span class="ga-eyebrow">STUDENT GRADE RECORD</span><h2 id="gaStudentDialogTitle"></h2><p><?= esc($context['subject_code']) ?> &middot; <?= esc($context['academic_year']) ?></p></div><button type="button" class="ga-record-close" id="gaCloseStudentDialog" aria-label="Close student record"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></header>
    <div class="ga-record-body">
        <section class="ga-record-summary" aria-label="Current grade"><div><span>Grade</span><strong id="gaRecordGrade"></strong></div><div><span>Remarks</span><strong id="gaRecordRemarks"></strong></div><div><span>Status</span><strong id="gaRecordStatus"></strong></div></section>
    </div>
    <footer class="ga-dialog-footer"><button type="button" class="ga-button" id="gaCloseStudentDialogFooter">Close</button></footer>
</dialog>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const CONTEXT = <?= json_encode($context, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const STUDENT_RECORDS = <?= json_encode($studentRecords, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const studentDialog = document.getElementById('gaStudentDialog');
    let studentDialogReturnFocus = null;
    const titleCase = value => value ? value.charAt(0).toUpperCase() + value.slice(1) : 'Not available';

    function openStudentRecord(index, trigger) {
        const record = STUDENT_RECORDS[index];
        if (!record) return;
        studentDialogReturnFocus = trigger;
        document.getElementById('gaStudentDialogTitle').textContent = record.username;
        document.getElementById('gaRecordGrade').textContent = record.grade?.value ?? 'Not submitted';
        document.getElementById('gaRecordRemarks').textContent = record.grade?.remarks || 'No remarks';
        document.getElementById('gaRecordStatus').textContent = titleCase(record.grade?.status);
        studentDialog.showModal();
        document.getElementById('gaCloseStudentDialog').focus();
    }

    document.querySelectorAll('[data-student-record]').forEach(button => button.addEventListener('click', () => openStudentRecord(Number(button.dataset.studentRecord), button)));
    document.getElementById('gaCloseStudentDialog').addEventListener('click', () => studentDialog.close());
    document.getElementById('gaCloseStudentDialogFooter').addEventListener('click', () => studentDialog.close());
    studentDialog.addEventListener('click', event => {
        if (event.target !== studentDialog) return;
        const bounds = studentDialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) studentDialog.close();
    });
    studentDialog.addEventListener('close', () => studentDialogReturnFocus?.isConnected && studentDialogReturnFocus.focus());

    function showFeedback(message, isError) {
        const el = document.getElementById('feedback');
        el.hidden = false;
        el.className = 'ga-feedback' + (isError ? ' is-error' : '');
        el.textContent = message;
    }

    let busy = false;
    async function runTransition(buttonId, confirmMessage, endpoint, busyLabel, successLabel, feedbackMessage, redirectTo) {
        if (busy) return;
        busy = true;
        const button = document.getElementById(buttonId);
        const label = button.innerHTML;
        try {
            if (!await acConfirm(confirmMessage)) return;
            button.disabled = true; button.textContent = busyLabel;
            const res = await fetch(endpoint, {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(CONTEXT),
            });
            const result = await res.json().catch(() => null);
            if (!res.ok || !result?.ok) throw new Error(result?.error || 'Could not complete this action. Please try again.');
            showFeedback(feedbackMessage, false);
            button.textContent = successLabel;
            setTimeout(() => { window.location.href = redirectTo; }, 800);
        } catch (error) { showFeedback(error.message, true); }
        finally {
            if (button.textContent !== successLabel) { busy = false; button.disabled = false; button.innerHTML = label; }
        }
    }

    function approveClass() {
        runTransition('gaApproveButton', 'Approve these grades? They will move on for the final lock.', '<?= site_url('registrar/grade-approvals/approve') ?>', 'Approving grades...', 'Grades approved', 'Grades approved.', '<?= site_url('registrar/grade-approvals') ?>');
    }

    function lockClass() {
        runTransition('gaLockButton', 'Lock these grades? Once locked, changes require a formal correction request.', '<?= site_url('registrar/grade-approvals/lock') ?>', 'Locking grades...', 'Grades locked', 'Grades locked and now official.', '<?= site_url('registrar/grade-approvals') ?>');
    }
</script>
<?= $this->endSection() ?>

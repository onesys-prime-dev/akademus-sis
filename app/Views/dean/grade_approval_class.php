<?= $this->extend('layouts/admin_console') ?>

<?php
$pageTitle = $context['subject_code'] . ' · ' . $context['program'] . ' ' . $context['year_level'] . '-' . $context['section'];
$this->setData(['pageTitle' => $pageTitle] + \App\Libraries\DeanPortal::shell('grade-approvals'));

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
<a class="ga-back" href="<?= site_url('dean/grade-approvals') ?>"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Grade approvals</a>
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
                <?php $allRecommended = true; $allNotedOrBeyond = true; ?>
                <?php foreach ($roster as $recordIndex => $row): ?>
                    <?php
                        $g = $row['grade'];
                        if ($g === null || $g['status'] !== 'chair_recommended') { $allRecommended = false; }
                        if ($g === null || in_array($g['status'], ['draft', 'submitted', 'chair_recommended'], true)) { $allNotedOrBeyond = false; }
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
            <?php if ($allRecommended): ?>
                <p>Confirm that every grade is correct. Noting sends this class on to the registrar for final approval.</p><button type="button" class="ga-button ga-button--primary" id="gaLockButton" onclick="noteClass()"><span class="material-symbols-outlined" aria-hidden="true">task_alt</span>Note grades</button>
            <?php elseif ($allNotedOrBeyond): ?>
                <p class="ga-official-note"><span class="material-symbols-outlined" aria-hidden="true">verified</span>This class has already been noted and is further along the approval chain.</p>
            <?php else: ?>
                <p class="panel-sub">Not every student has a chair-recommended grade yet — nothing to note.</p>
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

    let lockBusy = false;
    async function noteClass() {
        if (lockBusy) return;
        lockBusy = true;
        const button = document.getElementById('gaLockButton');
        const label = button.innerHTML;
        try {
            if (!await acConfirm('Note these grades? They will move on to the registrar for final approval.')) return;
            button.disabled = true; button.textContent = 'Noting grades...';
            const res = await fetch('<?= site_url('dean/grade-approvals/note') ?>', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(CONTEXT),
            });
            const result = await res.json().catch(() => null);
            if (!res.ok || !result?.ok) throw new Error(result?.error || 'Could not note grades. Please try again.');
            showFeedback('Grades noted and sent to the registrar.', false);
            button.textContent = 'Grades noted';
            setTimeout(() => { window.location.href = '<?= site_url('dean/grade-approvals') ?>'; }, 800);
        } catch (error) { showFeedback(error.message, true); }
        finally {
            if (button.textContent !== 'Grades noted') { lockBusy = false; button.disabled = false; button.innerHTML = label; }
        }
    }
</script>
<?= $this->endSection() ?>

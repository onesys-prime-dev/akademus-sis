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
        'grading-change-requests' => ['label' => 'Correction Requests', 'icon' => 'history_edu', 'url' => 'faculty/grading/change-requests'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pages/grade-approvals.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/grade-approvals.css') ?>">
<style>
    table.grade-table input[type=text] { width: 90px; padding: 6px 8px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); }
    table.grade-table input[type=text].remarks { width: 160px; }
    .status-pill { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); }
    .status-pill.locked { background: var(--color-success-soft); color: #14532d; }
    .status-pill.submitted,
    .status-pill.chair_recommended,
    .status-pill.dean_noted,
    .status-pill.registrar_approved { background: #dff8fb; color: #164e63; }
    .status-pill.draft { background: var(--color-warning-soft); color: #713f12; }
    .status-pill.pending { background: var(--color-warning-soft); color: #713f12; }
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
        <?php
            // Once a grade leaves 'draft' (submitted, chair_recommended,
            // dean_noted, registrar_approved, or locked) it's out of the
            // faculty's hands — submitClass() submits the whole roster in
            // one bulk update, so any one row past draft means the whole
            // class already left draft together.
            $classSubmitted = false;
            foreach ($roster as $row) {
                if ($row['grade'] !== null && $row['grade']['status'] !== 'draft') {
                    $classSubmitted = true;
                    break;
                }
            }
        ?>
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
                    <?php
                        $g         = $row['grade'];
                        $editable  = $g === null || $g['status'] === 'draft';
                        $isLocked  = $g !== null && $g['status'] === 'locked';
                        $history   = $row['change_requests'];
                        $pending   = null;
                        foreach ($history as $h) { if ($h['status'] === 'pending') { $pending = $h; break; } }
                    ?>
                    <tr data-enrollment-id="<?= esc($row['enrollment_id']) ?>">
                        <td><?= esc($row['username']) ?></td>
                        <td>
                            <input type="text" class="grade-input" aria-label="Grade for <?= esc($row['username']) ?>" value="<?= $g !== null ? esc($g['grade_value']) : '' ?>" <?= $editable ? '' : 'disabled' ?>>
                        </td>
                        <td>
                            <input type="text" class="remarks-input" aria-label="Remarks for <?= esc($row['username']) ?>" value="<?= $g !== null ? esc($g['remarks']) : '' ?>" <?= $editable ? '' : 'disabled' ?>>
                        </td>
                        <td>
                            <?php if ($pending !== null): ?>
                                <span class="status-pill pending">Change requested</span>
                            <?php elseif ($g !== null): ?>
                                <span class="status-pill <?= esc($g['status']) ?>"><?= esc(ucfirst(str_replace('_', ' ', $g['status']))) ?></span>
                            <?php endif ?>
                            <?php if ($history !== []): ?>
                                <details class="ga-history">
                                    <summary>History</summary>
                                    <?php foreach ($history as $h): ?>
                                        <div class="ga-history-item">
                                            <strong><?= esc($h['current_value']) ?></strong> &rarr; <strong><?= esc($h['requested_value']) ?></strong>
                                            <span class="ga-status <?= esc($h['status']) ?>"><?= esc(ucfirst($h['status'])) ?></span>
                                            <div><?= esc($h['reason']) ?></div>
                                        </div>
                                    <?php endforeach ?>
                                </details>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php if ($isLocked): ?>
                                <button type="button" class="btn btn-secondary" <?= $pending !== null ? 'disabled title="A change request is already pending for this grade."' : '' ?> onclick="openRequestChange(<?= esc($g['grade_id']) ?>, '<?= esc($row['username'], 'js') ?>', '<?= esc($g['grade_value'], 'js') ?>', this)"><?= $pending !== null ? 'Request pending' : 'Request Change' ?></button>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>

        <?php if ($classSubmitted): ?>
            <p class="panel-sub" style="margin-top:16px;">This class has already been submitted and is no longer editable here. A grade only becomes correctable again after it's locked — use "Request Change" above once it is.</p>
        <?php else: ?>
            <div style="display:flex; gap:10px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="saveDraft()">Save Draft</button>
                <button type="button" class="btn btn-primary" onclick="submitClass()">Submit for Approval</button>
            </div>
        <?php endif ?>
    <?php endif ?>
</div>

<dialog class="ga-dialog" id="gaRequestDialog" aria-labelledby="gaReqTitle" aria-describedby="gaReqDesc">
    <form id="gaRequestForm">
        <header class="ga-dialog-header">
            <h2 id="gaReqTitle">Request a grade correction</h2>
            <p id="gaReqDesc">This goes to your chairperson for approval. The official grade stays as-is until they decide.</p>
        </header>
        <div class="ga-dialog-body">
            <div class="ga-feedback is-error" id="gaReqError" role="alert" hidden></div>
            <div class="ga-dialog-summary">
                <strong id="gaReqStudent"></strong>
                <div class="ga-change">
                    <div>Current <strong id="gaReqCurrent"></strong></div>
                    <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                    <div>Corrected <strong id="gaReqPreview">—</strong></div>
                </div>
            </div>
            <div class="ga-field">
                <label for="gaReqValue">Corrected value</label>
                <input type="text" id="gaReqValue" required autocomplete="off">
            </div>
            <div class="ga-field">
                <label for="gaReqReason">Reason <span>Required</span></label>
                <textarea id="gaReqReason" rows="3" required placeholder="Why does this grade need to change?"></textarea>
            </div>
        </div>
        <footer class="ga-dialog-footer">
            <button type="button" class="ga-button" id="gaReqCancel">Cancel</button>
            <button type="submit" class="ga-button ga-button--primary" id="gaReqSubmit">Send request</button>
        </footer>
    </form>
</dialog>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const CONTEXT = <?= json_encode($context, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

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
        if (!await acConfirm('Submit this class\'s grades for chairperson approval? You can still edit until it is locked.')) return;

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

    const reqDialog  = document.getElementById('gaRequestDialog');
    const reqForm    = document.getElementById('gaRequestForm');
    const reqValue   = document.getElementById('gaReqValue');
    const reqReason  = document.getElementById('gaReqReason');
    const reqPreview = document.getElementById('gaReqPreview');
    const reqError   = document.getElementById('gaReqError');
    let reqContext   = null;
    let reqBusy      = false;
    let reqReturnFocus = null;

    function openRequestChange(gradeId, studentName, currentValue, triggerButton) {
        reqContext = { gradeId, row: triggerButton.closest('tr') };
        reqReturnFocus = triggerButton;
        document.getElementById('gaReqStudent').textContent = studentName;
        document.getElementById('gaReqCurrent').textContent = currentValue;
        reqPreview.textContent = '—';
        reqValue.value = '';
        reqReason.value = '';
        reqError.hidden = true;
        reqDialog.showModal();
        reqValue.focus();
    }

    reqValue.addEventListener('input', () => { reqPreview.textContent = reqValue.value.trim() || '—'; });
    document.getElementById('gaReqCancel').addEventListener('click', () => { if (!reqBusy) reqDialog.close(); });
    reqDialog.addEventListener('cancel', event => { if (reqBusy) event.preventDefault(); });
    reqDialog.addEventListener('close', () => { if (reqReturnFocus?.isConnected) reqReturnFocus.focus(); });

    reqForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (reqBusy || !reqContext) return;

        const newValue = reqValue.value.trim();
        const reason = reqReason.value.trim();

        if (!newValue || !reason) {
            reqError.textContent = 'Both the corrected value and a reason are required.';
            reqError.hidden = false;
            return;
        }

        reqBusy = true;
        const submitBtn = document.getElementById('gaReqSubmit');
        const label = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending…';

        const result = await postJSON('<?= site_url('faculty/grading/request-change') ?>', {
            grade_id: reqContext.gradeId,
            requested_value: newValue,
            reason: reason,
        });

        submitBtn.disabled = false;
        submitBtn.textContent = label;
        reqBusy = false;

        if (!result.ok) {
            reqError.textContent = result.error || 'Could not file the request.';
            reqError.hidden = false;
            return;
        }

        reqDialog.close();
        showFeedback('Change request submitted to the chairperson.', false);
        setTimeout(() => location.reload(), 700);
    });
</script>
<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'tuition';
$pageTitle     = 'Tuition & Fees';
$brandSub      = 'Student Portal';
$userName      = auth()->user()->username;
$userRoleLabel = 'Student';
$navGroups     = [
    '' => [
        'dashboard' => ['label' => 'Dashboard',   'icon' => 'home',           'url' => 'student/dashboard', 'help' => [
            'title' => 'Student Dashboard',
            'text'  => "Your at-a-glance view: today's schedule, upcoming classes, and recent grade/tuition activity. Use the sidebar to get to any other part of your student portal.",
        ]],
        'tuition'   => ['label' => 'Tuition & Fees', 'icon' => 'payments',    'url' => 'student/tuition', 'help' => [
            'title' => 'Tuition & Fees',
            'text'  => "Your current assessment, payment history, and balance. Upload proof of an off-site payment here for Finance to review, or see your installment schedule.",
        ]],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule', 'help' => [
            'title' => 'My Schedule',
            'text'  => "Your weekly class schedule for the current term — days, times, rooms, and instructors for everything you're enrolled in.",
        ]],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades', 'help' => [
            'title' => 'My Grades',
            'text'  => "Your official grades, term by term. A grade only appears here once it's been locked by the registrar.",
        ]],
        'enlistment' => ['label' => 'Enlistment', 'icon' => 'playlist_add_check', 'url' => 'student/enlistment', 'help' => [
            'title' => 'Enlistment',
            'text'  => "Select your subjects for next term and submit them for approval. Only available while your department's enlistment window is open; otherwise you'll enroll directly once sections open.",
        ]],
        'documents' => ['label' => 'Documents',   'icon' => 'description',        'url' => 'student/documents', 'help' => [
            'title' => 'Documents',
            'text'  => "Request a Certificate of Registration (needs at least a downpayment) or Certificate of Enrollment (available once you're enrolled, balance or not), and track their status.",
        ]],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

$current = $assessments[0] ?? null;

// Tuition-first waterfall for the two-bucket Balances split: payments apply
// to the tuition line before the itemized/added fees. "Added fees" here
// covers BOTH the curriculum-driven itemized lines (Miscellaneous/Other/
// Additional/Course, from assessment_items) AND one-off Finance-charged
// fees (student_additional_fees, e.g. a campus event fee) — the latter is
// what total_amount's own additional_fees_amount reflects, so it has to be
// folded in here too or this two-bucket split would stop summing to the
// real total/balance the moment Finance charges one.
$addedFeesTotal = (float) ($current['additional_fees_amount'] ?? 0);
foreach ($itemsByCategory as $category => $rows) {
    foreach ($rows as $row) {
        $addedFeesTotal += (float) $row['amount'];
    }
}

$tuitionAmount   = (float) ($current['tuition_amount'] ?? 0);
$amountPaid      = (float) ($current['amount_paid'] ?? 0);
$tuitionPaid     = min($tuitionAmount, $amountPaid);
$addedFeesPaid   = min($addedFeesTotal, max(0, $amountPaid - $tuitionAmount));
$tuitionBalance  = max(0, $tuitionAmount - $tuitionPaid);
$addedFeesBalance = max(0, $addedFeesTotal - $addedFeesPaid);
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pages/student-tuition.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/student-tuition.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="tf-page">
<header class="tf-heading">
    <div>
        <span class="tf-eyebrow">STUDENT FINANCES</span>
        <h1>Tuition &amp; Fees</h1>
        <p>Review your assessment, payment schedule, and account activity.</p>
    </div>
    <?php if ($current !== null): ?>
        <span class="tf-term"><span>Current term</span><?= esc($current['academic_year']) ?></span>
    <?php endif ?>
</header>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<?php if (session()->has('errors')): ?>
    <div class="ac-notice" style="margin-bottom:16px; border-color:var(--color-danger,#dc2626);">
        <?php foreach ((array) session('errors') as $error): ?>
            <span class="ac-notice__text"><?= esc($error) ?></span><br>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?php if ($current === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">payments</div>
            <p>No tuition assessment yet. This appears once you're enrolled and your program's rates are set.</p>
        </div>
    </div>
<?php elseif ($current['status'] === 'unassessed'): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">payments</div>
            <p>Your program doesn't have tuition rates set for <?= esc($current['academic_year']) ?> yet. Contact the Registrar's Office.</p>
        </div>
    </div>
<?php else: ?>

<?php
$totalAssessment = (float) $current['total_amount'];
$paidPercent = $totalAssessment > 0 ? min(100, max(0, $amountPaid / $totalAssessment * 100)) : 0;
$statusLabel = ['paid' => 'Paid in full', 'partial' => 'Partially paid'][$current['status']] ?? 'Unpaid';
$statusClass = ['paid' => 'paid', 'partial' => 'partial'][$current['status']] ?? 'unpaid';
?>
<?php if (! empty($current['is_estimated'])): ?>
    <div class="ac-notice" style="margin-bottom:16px; background:var(--color-info-soft, #eff6ff);">
        <span class="material-symbols-outlined" aria-hidden="true" style="color:var(--color-info, #1d4ed8);">info</span>
        <span class="ac-notice__text">This is a <strong>preliminary estimate</strong> based on your curriculum — you're not enrolled in any classes yet this term, so these figures aren't final. They'll update automatically once you're enrolled.</span>
    </div>
<?php endif ?>
<section class="tf-summary" aria-label="Account overview">
    <div class="tf-balance">
        <div class="tf-balance-top"><span>Amount due</span><span class="tf-status tf-status--<?= $statusClass ?>"><?= esc($statusLabel) ?></span></div>
        <strong class="tf-amount"><span>₱</span><?= number_format((float) $current['balance'], 2) ?></strong>
        <p>Outstanding balance for <?= esc($current['academic_year']) ?></p>
        <a class="tf-action" href="#payment-proof">Submit payment proof<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
    </div>
    <div class="tf-overview">
        <div class="tf-overview-label">Account summary</div>
        <div class="tf-metrics"><div><span>Total assessment</span><strong>₱<?= number_format($totalAssessment, 2) ?></strong></div><div><span>Payments received</span><strong class="tf-paid">₱<?= number_format($amountPaid, 2) ?></strong></div></div>
        <div class="tf-progress-label"><span>Payment progress</span><strong><?= number_format($paidPercent, 0) ?>%</strong></div>
        <progress class="tf-progress" value="<?= $paidPercent ?>" max="100" aria-label="Assessment paid"><?= number_format($paidPercent, 0) ?>%</progress>
        <dl class="tf-breakdown"><div><dt>Tuition</dt><dd>₱<?= number_format($tuitionBalance, 2) ?> remaining</dd></div><div><dt>Other fees</dt><dd>₱<?= number_format($addedFeesBalance, 2) ?> remaining</dd></div></dl>
    </div>
</section>
<div class="tf-layout">

<section class="panel tf-assessment" aria-labelledby="assessment-title">
    <header class="tf-section-heading"><div><h2 id="assessment-title">Assessment breakdown</h2><p>An itemized statement of charges for this term.</p></div></header>
    <div class="tf-column-labels"><span>Fee description</span><span>Amount · PHP</span></div>
    <div class="tf-tuition-line"><div><strong>Tuition fee</strong><span>Academic tuition for this term</span></div><strong>₱<?= number_format($tuitionAmount, 2) ?></strong></div>
    <?php if ($additionalFees !== []): ?>
        <?php $additionalFeesTotal = array_sum(array_column($additionalFees, 'amount')); ?>
        <details class="tf-fee-group" open>
            <summary><span class="material-symbols-outlined tf-chevron" aria-hidden="true">chevron_right</span><span class="tf-category"><strong>Added fees</strong><small><?= count($additionalFees) ?> <?= count($additionalFees) === 1 ? 'fee item' : 'fee items' ?></small></span><strong>₱<?= number_format($additionalFeesTotal, 2) ?></strong></summary>
            <dl class="tf-fee-items"><?php foreach ($additionalFees as $fee): ?><div><dt><?= esc($fee['label']) ?></dt><dd>₱<?= number_format((float) $fee['amount'], 2) ?></dd></div><?php endforeach ?></dl>
        </details>
    <?php endif ?>
    <?php foreach (['miscellaneous' => 'Miscellaneous fees', 'other' => 'Other fees', 'additional' => 'Additional fees', 'course' => 'Course fees'] as $catKey => $catLabel): ?>
        <?php if (empty($itemsByCategory[$catKey])) continue; ?>
        <?php $catTotal = array_sum(array_column($itemsByCategory[$catKey], 'amount')); ?>
        <details class="tf-fee-group" <?= $catKey === 'miscellaneous' ? 'open' : '' ?>>
            <summary><span class="material-symbols-outlined tf-chevron" aria-hidden="true">chevron_right</span><span class="tf-category"><strong><?= esc($catLabel) ?></strong><small><?= count($itemsByCategory[$catKey]) ?> <?= count($itemsByCategory[$catKey]) === 1 ? 'fee item' : 'fee items' ?></small></span><strong>₱<?= number_format($catTotal, 2) ?></strong></summary>
            <dl class="tf-fee-items"><?php foreach ($itemsByCategory[$catKey] as $item): ?><div><dt><?= esc($item['label']) ?></dt><dd>₱<?= number_format((float) $item['amount'], 2) ?></dd></div><?php endforeach ?></dl>
        </details>
    <?php endforeach ?>
    <div class="tf-assessment-total"><span>Total assessment</span><strong>₱<?= number_format($totalAssessment, 2) ?></strong></div>
    <p class="tf-note"><span class="material-symbols-outlined" aria-hidden="true">info</span>Assessment amounts are before recorded payments.</p>
</section>
<div class="tf-side">

<?php if (!empty($schedule)): ?>
<div class="panel tf-schedule">
    <header class="tf-section-heading"><div><h2>Payment schedule</h2><p>Installment amounts and their current status.</p></div></header>
    <div class="tf-table-scroll" role="region" aria-label="Payment details" tabindex="0"><table class="grade-table">
        <thead>
            <tr><th>Installment</th><th>Due date</th><th class="tf-money">Amount</th><th class="tf-money">Remaining</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php foreach ($schedule as $row): ?>
                <?php
                $installmentAmount = (float) $row['amount'];
                $installmentBalance = (float) $row['balance'];
                $installmentStatus = $installmentBalance <= 0 ? 'Paid' : ($installmentBalance < $installmentAmount ? 'Partial' : 'Due');
                $installmentClass = strtolower($installmentStatus);
                ?>
                <tr>
                    <td data-label="Installment"><strong><?= esc($row['label']) ?></strong></td>
                    <td data-label="Due date"><?= $row['due_date'] ? esc(date('M j, Y', strtotime($row['due_date']))) : 'Not set' ?></td>
                    <td data-label="Amount" class="tf-money">&#8369;<?= number_format($installmentAmount, 2) ?></td>
                    <td data-label="Remaining" class="tf-money"><strong>&#8369;<?= number_format($installmentBalance, 2) ?></strong></td>
                    <td data-label="Status"><span class="tf-installment-status tf-installment-status--<?= $installmentClass ?>"><?= $installmentStatus ?></span></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table></div>
</div>
<?php endif ?>

<section class="panel tf-upload" id="payment-proof" tabindex="-1">
    <header class="tf-section-heading"><div><h2>Submit payment proof</h2><p>Send a receipt after completing a payment.</p></div></header>
    <form method="post" action="<?= site_url('student/tuition/proof/upload') ?>" enctype="multipart/form-data" class="tf-upload-form" id="tfUploadForm">
        <?= csrf_field() ?>
        <?php if (!empty($schedule)): ?>
        <div class="ac-field" style="margin:0;">
            <label for="tf-schedule">Payment for</label>
            <select id="tf-schedule" name="schedule_id" class="ac-input">
                <option value="">General Payment</option>
                <?php foreach ($schedule as $row): ?>
                    <?php if ((float) $row['balance'] <= 0) continue; ?>
                    <option value="<?= $row['schedule_id'] ?>"><?= esc($row['label']) ?> (<?= number_format((float) $row['balance'], 2) ?> due)</option>
                <?php endforeach ?>
            </select>
        </div>
        <?php endif ?>
        <div class="ac-field" style="margin:0;">
            <label for="tf-remarks">Payment reference or remarks</label>
            <textarea id="tf-remarks" name="remarks" class="ac-input" rows="2" required placeholder="e.g. Bank transfer ref. 12345"></textarea>
        </div>
        <div class="ac-field" style="margin:0;">
            <label for="tf-proof">Receipt or proof of payment</label>
            <div class="tf-dropzone" id="tfDropzone">
                <div class="tf-dropzone__prompt" id="tfDropzonePrompt">
                    <div class="tf-dropzone__cta">
                        <span class="material-symbols-outlined" aria-hidden="true">upload_file</span>
                        <span>Click to browse, or drag a file here</span>
                    </div>
                    <input id="tf-proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" aria-describedby="tf-file-help" class="tf-file-overlay">
                    <small id="tf-file-help">JPG, PNG, or PDF · Maximum 5 MB</small>
                </div>
                <div class="tf-file-preview" id="tfFilePreview" hidden>
                    <div class="tf-file-preview__thumb" id="tfFilePreviewThumb"><span class="material-symbols-outlined" aria-hidden="true">description</span></div>
                    <div class="tf-file-preview__meta">
                        <strong id="tfFilePreviewName"></strong>
                        <span id="tfFilePreviewSize"></span>
                    </div>
                    <button type="button" class="tf-file-preview__action" id="tfFileView">View</button>
                    <button type="button" class="tf-file-preview__action" id="tfFileReplace">Replace</button>
                    <button type="button" class="tf-file-preview__action tf-file-preview__action--danger" id="tfFileRemove">Remove</button>
                </div>
                <div class="tf-upload-progress" id="tfUploadProgress" hidden>
                    <div class="tf-upload-progress__row">
                        <span id="tfUploadProgressLabel">Uploading…</span>
                        <span id="tfUploadProgressPercent">0%</span>
                    </div>
                    <div class="tf-upload-progress__bar"><div class="tf-upload-progress__fill" id="tfUploadProgressFill"></div></div>
                    <p class="tf-upload-progress__error" id="tfUploadError" hidden></p>
                    <button type="button" class="ac-btn ac-btn-secondary" id="tfUploadRetry" hidden>Retry upload</button>
                </div>
            </div>
        </div>
        <button type="submit" class="ac-btn ac-btn-primary" id="tfUploadSubmit"><span class="material-symbols-outlined" aria-hidden="true">upload</span><span id="tfUploadSubmitLabel">Submit for review</span></button>
        <p class="tf-form-help">Your submission will be reviewed by the cashier before it is reflected in your balance.</p>
    </form>

    <div class="ac-modal" id="tfConfirmUploadModal">
        <div class="ac-modal__panel">
            <div class="ac-modal__header">
                <div>
                    <div class="ac-modal__title">Confirm submission</div>
                    <div class="ac-modal__subtitle">Review the details before sending this to the cashier.</div>
                </div>
                <button type="button" onclick="acCloseModal('tfConfirmUploadModal')" class="ac-modal__close">
                    <span class="material-symbols-outlined" aria-hidden="true">close</span>
                </button>
            </div>
            <div class="ac-modal__body">
                <dl class="tf-confirm-facts">
                    <div><dt>Payment for</dt><dd id="tfConfirmSchedule"></dd></div>
                    <div><dt>Reference / remarks</dt><dd id="tfConfirmRemarks"></dd></div>
                    <div><dt>Attached file</dt><dd id="tfConfirmFile"></dd></div>
                </dl>
                <p class="tf-form-help">Your submission will be reviewed by the cashier before it is reflected in your balance.</p>
                <div class="ac-modal__actions">
                    <button type="button" class="ac-btn ac-btn-secondary" onclick="acCloseModal('tfConfirmUploadModal')">Cancel</button>
                    <button type="button" class="ac-btn ac-btn-primary" id="tfConfirmUploadBtn"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span>Confirm &amp; submit</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        if (window.enhanceSelect) window.enhanceSelect(document.getElementById('tf-schedule'));

        // The browser's native fragment-jump "scroll into view" walks past
        // the app shell's internal scroll container (.ac-content) and ends
        // up scrolling the outer document instead, which drags the fixed
        // sidebar/topbar shell out of view along with it on mobile. Scroll
        // the target into view ourselves, confined to .ac-content, and
        // focus it without letting the browser take a second, uncontrolled
        // scroll pass.
        // Same fix for a direct/refresh load that already carries the hash —
        // the browser's native jump-on-load has the identical problem.
        if (window.location.hash === '#payment-proof') {
            window.addEventListener('load', function () {
                var target = document.getElementById('payment-proof');
                var scroller = document.querySelector('.ac-content');
                if (target && scroller) {
                    var top = target.getBoundingClientRect().top - scroller.getBoundingClientRect().top + scroller.scrollTop;
                    scroller.scrollTop = top;
                }
                document.scrollingElement.scrollTop = 0;
            });
        }

        document.querySelectorAll('a[href="#payment-proof"]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                var target = document.getElementById('payment-proof');
                var scroller = document.querySelector('.ac-content');
                if (!target || !scroller) return;
                e.preventDefault();
                var top = target.getBoundingClientRect().top - scroller.getBoundingClientRect().top + scroller.scrollTop;
                scroller.scrollTo({ top: top, behavior: 'smooth' });
                if (target.focus) target.focus({ preventScroll: true });
                document.scrollingElement.scrollTop = 0;
                history.replaceState(null, '', '#payment-proof');
            });
        });

        var form         = document.getElementById('tfUploadForm');
        var dropzone      = document.getElementById('tfDropzone');
        var fileInput     = document.getElementById('tf-proof');
        var promptEl      = document.getElementById('tfDropzonePrompt');
        var preview       = document.getElementById('tfFilePreview');
        var previewThumb  = document.getElementById('tfFilePreviewThumb');
        var previewName   = document.getElementById('tfFilePreviewName');
        var previewSize   = document.getElementById('tfFilePreviewSize');
        var viewBtn       = document.getElementById('tfFileView');
        var removeBtn     = document.getElementById('tfFileRemove');
        var replaceBtn    = document.getElementById('tfFileReplace');
        var currentFile   = null;
        var progress      = document.getElementById('tfUploadProgress');
        var progressFill  = document.getElementById('tfUploadProgressFill');
        var progressPct   = document.getElementById('tfUploadProgressPercent');
        var progressLabel = document.getElementById('tfUploadProgressLabel');
        var errorMsg      = document.getElementById('tfUploadError');
        var retryBtn      = document.getElementById('tfUploadRetry');
        var submitBtn     = document.getElementById('tfUploadSubmit');
        var submitLabel   = document.getElementById('tfUploadSubmitLabel');

        var MAX_BYTES = 5 * 1024 * 1024;
        var ALLOWED_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

        function formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function showFile(file) {
            if (file.size > MAX_BYTES) {
                resetSelection();
                window.alert('That file is too large — maximum is 5 MB.');
                return;
            }
            if (ALLOWED_TYPES.indexOf(file.type) === -1) {
                resetSelection();
                window.alert('Only JPG, PNG, or PDF files are accepted.');
                return;
            }
            currentFile = file;
            promptEl.hidden = true;
            preview.hidden = false;
            previewName.textContent = file.name;
            previewSize.textContent = formatSize(file.size) + ' · ' + (file.type || 'file');
            previewThumb.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">description</span>';
            if (file.type.indexOf('image/') === 0) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    previewThumb.innerHTML = '<img src="' + e.target.result + '" alt="">';
                };
                reader.readAsDataURL(file);
            }
        }

        function resetSelection() {
            currentFile = null;
            fileInput.value = '';
            promptEl.hidden = false;
            preview.hidden = true;
        }

        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files[0]) showFile(fileInput.files[0]);
        });

        viewBtn.addEventListener('click', function () {
            if (!currentFile) return;
            var url = URL.createObjectURL(currentFile);
            tfOpenFile(url, currentFile.name, { downloadName: currentFile.name });
        });
        removeBtn.addEventListener('click', resetSelection);
        replaceBtn.addEventListener('click', function () { fileInput.click(); });

        ['dragenter', 'dragover'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });
        dropzone.addEventListener('drop', function (e) {
            var file = e.dataTransfer.files && e.dataTransfer.files[0];
            if (!file) return;
            fileInput.files = e.dataTransfer.files;
            showFile(file);
        });

        var lastFormData = null;

        function startUpload() {
            progress.hidden = false;
            errorMsg.hidden = true;
            retryBtn.hidden = true;
            progressLabel.textContent = 'Uploading…';
            progressFill.style.width = '0%';
            progressPct.textContent = '0%';
            submitBtn.disabled = true;
            submitBtn.classList.add('is-loading');
            submitLabel.textContent = 'Uploading…';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', form.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.upload.addEventListener('progress', function (e) {
                if (!e.lengthComputable) return;
                var pct = Math.round((e.loaded / e.total) * 100);
                progressFill.style.width = pct + '%';
                progressPct.textContent = pct + '%';
            });

            xhr.addEventListener('load', function () {
                if (xhr.status >= 200 && xhr.status < 400) {
                    progressLabel.textContent = 'Done — redirecting…';
                    progressFill.style.width = '100%';
                    progressPct.textContent = '100%';
                    window.location.href = xhr.responseURL || form.action;
                } else {
                    failUpload('The server rejected this upload. Please try again.');
                }
            });

            xhr.addEventListener('error', function () {
                failUpload('Upload failed — check your connection and try again.');
            });

            xhr.send(lastFormData);
        }

        function failUpload(message) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('is-loading');
            submitLabel.textContent = 'Submit for review';
            progressLabel.textContent = 'Upload failed';
            errorMsg.textContent = message;
            errorMsg.hidden = false;
            retryBtn.hidden = false;
        }

        retryBtn.addEventListener('click', function () {
            if (lastFormData) startUpload();
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!form.reportValidity()) return;
            if (!currentFile) {
                window.alert('Please attach a receipt or proof of payment.');
                return;
            }

            var scheduleSelect = document.getElementById('tf-schedule');
            var scheduleLabel = scheduleSelect
                ? (scheduleSelect.options[scheduleSelect.selectedIndex].textContent || 'General Payment')
                : 'General Payment';
            document.getElementById('tfConfirmSchedule').textContent = scheduleLabel;
            document.getElementById('tfConfirmRemarks').textContent = document.getElementById('tf-remarks').value;
            document.getElementById('tfConfirmFile').textContent = currentFile.name + ' (' + formatSize(currentFile.size) + ')';

            acOpenModal('tfConfirmUploadModal');
        });

        document.getElementById('tfConfirmUploadBtn').addEventListener('click', function () {
            acCloseModal('tfConfirmUploadModal');
            lastFormData = new FormData(form);
            startUpload();
        });
    })();
    </script>
</section>
</div>
</div>

<?php if (!empty($proofs)): ?>
<div class="panel">
    <header class="tf-section-heading"><div><h2>Submitted proofs</h2><p>Receipts you've sent in for review.</p></div></header>
    <div class="tf-table-scroll" role="region" aria-label="Payment details" tabindex="0"><table class="grade-table">
        <thead>
            <tr><th>File</th><th>Covers</th><th>Uploaded</th><th>Status</th><th>Cashier Remarks</th></tr>
        </thead>
        <tbody>
            <?php foreach ($proofs as $p): ?>
                <tr>
                    <td data-label="File"><button type="button" class="tf-receipt-link" onclick="tfOpenFile('<?= site_url('student/tuition/proof/' . $p['proof_id']) ?>', '<?= esc($p['original_filename'], 'js') ?>')" title="<?= esc($p['original_filename'], 'attr') ?>"><?= esc($p['original_filename']) ?></button></td>
                    <td data-label="Covers"><?= ! empty($p['schedule_label']) ? esc($p['schedule_label']) : '<span class="tf-muted">General Payment</span>' ?></td>
                    <td data-label="Uploaded"><?= esc(date('M j, Y', strtotime($p['created_at']))) ?></td>
                    <td data-label="Status">
                        <?php if ($p['status'] === 'approved'): ?>
                            <span class="pass-pill pass">Approved</span>
                        <?php elseif ($p['status'] === 'rejected'): ?>
                            <span class="pass-pill fail">Rejected</span>
                        <?php else: ?>
                            <span class="pass-pill">Pending</span>
                        <?php endif ?>
                    </td>
                    <td data-label="Cashier Remarks"><?= $p['cashier_remarks'] !== null ? esc($p['cashier_remarks']) : '—' ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table></div>
</div>
<?php endif ?>
<?php endif ?>

<div class="panel">
    <header class="tf-section-heading"><div><h2>Payment history</h2><p>Payments posted to your student account.</p></div></header>
    <?php if (empty($payments)): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">receipt_long</div>
            <p>No payments recorded yet.</p>
        </div>
    <?php else: ?>
        <div class="tf-table-scroll" role="region" aria-label="Payment details" tabindex="0"><table class="grade-table tf-history-table">
            <thead>
                <tr><th>Date</th><th>Covers</th><th class="tf-money">Amount</th><th>Method</th><th>Reference</th><th>Receipt</th></tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td data-label="Date"><?= esc(date('M j, Y', strtotime($p['payment_date']))) ?></td>
                        <td data-label="Covers"><?= ! empty($p['schedule_label']) ? esc($p['schedule_label']) : '<span class="tf-muted">General Payment</span>' ?></td>
                        <td data-label="Amount" class="tf-money">&#8369;<?= number_format((float) $p['amount'], 2) ?></td>
                        <td data-label="Method"><?= esc(ucfirst(str_replace('_', ' ', $p['method']))) ?></td>
                        <td data-label="Reference"><?= $p['reference_no'] !== null ? esc($p['reference_no']) : '—' ?></td>
                        <td data-label="Receipt">
                            <?php if (! empty($p['or_number'])): ?>
                                <button type="button" class="tf-receipt-link" onclick="tfOpenFile('<?= site_url('student/tuition/receipt/' . $p['receipt_id']) ?>', 'Official Receipt — <?= esc($p['or_number'], 'js') ?>')"><?= esc($p['or_number']) ?></button>
                            <?php else: ?>
                                —
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table></div>
    <?php endif ?>
</div>

<?php if (count($assessments) > 1): ?>
<div class="panel">
    <h2>Previous Terms</h2>
    <div class="tf-table-scroll" role="region" aria-label="Payment details" tabindex="0"><table class="grade-table">
        <thead>
            <tr><th>Academic Year</th><th class="tf-money">Total</th><th class="tf-money">Paid</th><th class="tf-money">Balance</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($assessments, 1) as $a): ?>
                <tr>
                    <td data-label="Academic Year"><?= esc($a['academic_year']) ?></td>
                    <td data-label="Total" class="tf-money">&#8369;<?= number_format((float) $a['total_amount'], 2) ?></td>
                    <td data-label="Paid" class="tf-money">&#8369;<?= number_format((float) $a['amount_paid'], 2) ?></td>
                    <td data-label="Balance" class="tf-money">&#8369;<?= number_format((float) $a['balance'], 2) ?></td>
                    <td data-label="Status"><?= esc(ucfirst($a['status'])) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table></div>
</div>
<?php endif ?>

</div>

<div class="ac-modal tf-receipt-modal" id="tfFileModal">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title" id="tfFileModalTitle">Document preview</div>
            </div>
            <button type="button" onclick="acCloseModal('tfFileModal')" class="ac-modal__close">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <div class="ac-modal__body">
            <iframe id="tfFileFrame" class="tf-receipt-frame" title="Document preview"></iframe>
            <div class="ac-modal__actions">
                <a id="tfFileDownload" href="#" class="ac-btn ac-btn-secondary"><span class="material-symbols-outlined" aria-hidden="true">download</span>Download</a>
                <button type="button" class="ac-btn ac-btn-primary" onclick="tfPrintFile()"><span class="material-symbols-outlined" aria-hidden="true">print</span>Print</button>
            </div>
        </div>
    </div>
</div>

<script>
var tfFileModalBlobUrl = null;

// opts.downloadName set => url is a local blob: preview (a file picked but
// not uploaded yet), so the download link must use the `download` attribute
// instead of a '?download=1' query string, which only a real server route
// understands.
function tfOpenFile(url, title, opts) {
    opts = opts || {};
    var frame = document.getElementById('tfFileFrame');
    var download = document.getElementById('tfFileDownload');
    var titleEl = document.getElementById('tfFileModalTitle');
    titleEl.textContent = title;
    titleEl.title = title;
    frame.src = url;
    if (opts.downloadName) {
        download.href = url;
        download.setAttribute('download', opts.downloadName);
        tfFileModalBlobUrl = url;
    } else {
        download.href = url + (url.indexOf('?') === -1 ? '?' : '&') + 'download=1';
        download.removeAttribute('download');
        tfFileModalBlobUrl = null;
    }
    acOpenModal('tfFileModal');
}
function tfPrintFile() {
    var frame = document.getElementById('tfFileFrame');
    if (frame && frame.contentWindow) {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    }
}
(function () {
    var modal = document.getElementById('tfFileModal');
    new MutationObserver(function () {
        if (!modal.classList.contains('is-open')) {
            document.getElementById('tfFileFrame').src = '';
            if (tfFileModalBlobUrl) {
                URL.revokeObjectURL(tfFileModalBlobUrl);
                tfFileModalBlobUrl = null;
            }
        }
    }).observe(modal, { attributes: true, attributeFilter: ['class'] });
})();

// Animates the fee-breakdown <details> drawers: native <details> snaps
// open/closed with no transition, so the toggle is intercepted here and the
// height is animated manually, then the `open` attribute is only flipped
// once the collapse animation finishes (so content doesn't vanish mid-slide).
(function () {
    document.querySelectorAll('.tf-fee-group').forEach(function (details) {
        var content = details.querySelector('.tf-fee-items');
        var summary = details.querySelector('summary');
        if (!content || !summary) return;

        summary.addEventListener('click', function (e) {
            e.preventDefault();
            if (details.classList.contains('tf-animating')) return;
            details.classList.add('tf-animating');

            if (details.open) {
                content.style.height = content.scrollHeight + 'px';
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        content.style.height = '0px';
                    });
                });
                content.addEventListener('transitionend', function handler(ev) {
                    if (ev.propertyName !== 'height') return;
                    content.removeEventListener('transitionend', handler);
                    details.open = false;
                    content.style.height = '';
                    details.classList.remove('tf-animating');
                }, { once: true });
            } else {
                details.open = true;
                var target = content.scrollHeight;
                content.style.height = '0px';
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        content.style.height = target + 'px';
                    });
                });
                content.addEventListener('transitionend', function handler(ev) {
                    if (ev.propertyName !== 'height') return;
                    content.removeEventListener('transitionend', handler);
                    content.style.height = '';
                    details.classList.remove('tf-animating');
                }, { once: true });
            }
        });
    });
})();
</script>
<?= $this->endSection() ?>

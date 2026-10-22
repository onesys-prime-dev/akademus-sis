<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<?php
    $s = $student;
    $primary = $assessments[0] ?? null;
    $pendingProofs = array_filter($proofs, static fn (array $p) => $p['status'] === 'pending');

    // What's actually saved right now for each academic year (every
    // schedule row except the separately-tracked "Additional Fees" line)
    // — so the plan picker starts pointed at reality instead of always
    // defaulting to "Full Payment" regardless of what's saved.
    $currentCountByYear = [];
    foreach ($assessments as $a) {
        $rows  = $schedules[(int) $a['assessment_id']] ?? [];
        $count = count(array_filter($rows, static fn (array $r) => $r['label'] !== 'Additional Fees'));
        if ($count > 0) {
            $currentCountByYear[$a['academic_year']] = $count;
        }
    }
?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/cashier') ?>" class="ac-back-link"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Back to Cashier</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;"><?= esc(trim($s['first_name'] . ' ' . $s['last_name'])) ?></h2>
        <p class="ac-page-header__sub">
            <?= esc($s['student_number']) ?> &middot; <?= esc($s['program_name'] ?? 'No program set') ?>
            <?php if ($primary !== null): ?>
                &middot; <span class="ac-status-dot ac-status-dot--<?= $primary['status'] === 'paid' ? 'active' : 'pending' ?>"><?= esc(ucfirst($primary['status'])) ?></span>
            <?php endif ?>
        </p>
    </div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<?php if ($primary === null): ?>
    <div class="ac-empty-block"><p>No assessment exists yet for this student.</p></div>
<?php else: ?>

<div class="ac-stat-grid" style="margin-bottom:24px;">
    <div class="ac-metric-card">
        <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">receipt_long</span><span><?= esc($primary['academic_year']) ?> — Total</span></div>
        <div class="ac-metric-card__value">₱<?= number_format((float) $primary['total_amount'], 2) ?></div>
    </div>
    <div class="ac-metric-card">
        <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px; color:var(--color-success);" aria-hidden="true">check_circle</span><span>Paid</span></div>
        <div class="ac-metric-card__value" style="color:var(--color-success);">₱<?= number_format((float) $primary['amount_paid'], 2) ?></div>
    </div>
    <div class="ac-metric-card">
        <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px; color:var(--color-warning);" aria-hidden="true">account_balance_wallet</span><span>Balance</span></div>
        <div class="ac-metric-card__value" style="color:<?= (float) $primary['balance'] > 0 ? 'var(--color-warning)' : 'var(--color-success)' ?>;">₱<?= number_format((float) $primary['balance'], 2) ?></div>
    </div>
</div>

<div class="ac-tabs">
    <div class="ac-tabs__links">
        <a href="#" class="ac-tabs__link is-active" data-tab="plan" onclick="return csTab(event,'plan')">Payment Plan</a>
        <a href="#" class="ac-tabs__link" data-tab="proofs" onclick="return csTab(event,'proofs')">Payment Proofs<?= $pendingProofs !== [] ? ' <span class="ac-pill ac-pill--warning">' . count($pendingProofs) . '</span>' : '' ?></a>
        <a href="#" class="ac-tabs__link" data-tab="record" onclick="return csTab(event,'record')">Record Payment</a>
        <a href="#" class="ac-tabs__link" data-tab="change" onclick="return csTab(event,'change')">Change Plan</a>
        <a href="#" class="ac-tabs__link" data-tab="history" onclick="return csTab(event,'history')">History</a>
    </div>
</div>

<!-- Payment Plan -->
<div class="cs-panel" id="cs-plan">
    <?php foreach ($assessments as $a): ?>
        <?php $schedule = $schedules[(int) $a['assessment_id']] ?? []; ?>
        <?php if ($schedule === []) continue; ?>
        <div class="ac-dtable cs-plantable" style="margin-bottom:20px;">
            <div style="padding:14px 20px; border-bottom:1px solid var(--color-border); font-size:12.5px; font-weight:700; color:var(--color-text-secondary);"><?= esc($a['academic_year']) ?></div>
            <div class="ac-dtable__head">
                <div>Installment</div><div>Due Date</div><div>Amount</div><div>Paid</div><div>Remaining</div><div>Status</div>
            </div>
            <?php foreach ($schedule as $row): ?>
                <?php
                    $installmentAmount  = (float) $row['amount'];
                    $installmentBalance = (float) $row['balance'];
                    $installmentPaid    = (float) $row['paid'];
                    $installmentStatus  = $installmentBalance <= 0 ? 'Paid' : ($installmentPaid > 0 ? 'Partial' : 'Due');
                    $statusPill = match ($installmentStatus) {
                        'Paid'    => 'ac-pill--success',
                        'Partial' => 'ac-pill--warning',
                        default   => 'ac-pill--neutral',
                    };
                ?>
                <div class="ac-dtable__row">
                    <div data-label="Installment"><strong><?= esc($row['label']) ?></strong></div>
                    <div data-label="Due Date"><?= $row['due_date'] ? esc(date('M j, Y', strtotime($row['due_date']))) : ($row['label'] === 'Downpayment' ? 'Due on enrollment' : '—') ?></div>
                    <div data-label="Amount">₱<?= number_format($installmentAmount, 2) ?></div>
                    <div data-label="Paid">₱<?= number_format($installmentPaid, 2) ?></div>
                    <div data-label="Remaining">₱<?= number_format($installmentBalance, 2) ?></div>
                    <div data-label="Status"><span class="ac-pill <?= $statusPill ?>"><?= esc($installmentStatus) ?></span></div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endforeach ?>
</div>

<!-- Payment Proofs -->
<div class="cs-panel" id="cs-proofs" hidden>
    <?php if ($proofs === []): ?>
        <div class="ac-empty-block"><p>No payment proofs submitted yet.</p></div>
    <?php else: ?>
    <div class="ac-dtable cs-prooftable">
        <div class="ac-dtable__head">
            <div>Uploaded</div><div>Amount &amp; Installment</div><div>File</div><div>Reference</div><div>Status</div><div>Actions</div>
        </div>
        <?php foreach ($proofs as $p): ?>
            <?php
                $amount    = $p['schedule_id'] !== null ? (float) $p['schedule_amount'] : (float) $p['assessment_balance'];
                $planLabel = $p['schedule_id'] !== null ? $p['schedule_label'] : 'General Payment (full balance)';
                $statusPill = match ($p['status']) {
                    'approved' => 'ac-pill--success',
                    'rejected' => 'ac-pill--danger',
                    default    => 'ac-pill--warning',
                };
            ?>
            <div class="ac-dtable__row">
                <div data-label="Uploaded"><?= esc(date('M j, Y g:i A', strtotime($p['created_at']))) ?></div>
                <div data-label="Amount & Installment">
                    ₱<?= number_format($amount, 2) ?>
                    <br><span class="ac-pill ac-pill--neutral"><?= esc($planLabel) ?></span>
                </div>
                <div data-label="File">
                    <button type="button" class="ac-btn-outline" onclick="acOpenFileModal('<?= site_url('student/tuition/proof/' . $p['proof_id']) ?>', '<?= esc($p['original_filename'], 'js') ?>')" title="<?= esc($p['original_filename'], 'attr') ?>">
                        <span class="material-symbols-outlined" aria-hidden="true" style="font-size:16px; vertical-align:-3px;">visibility</span> View
                    </button>
                </div>
                <div data-label="Reference"><?= esc($p['remarks'] ?: '—') ?></div>
                <div data-label="Status">
                    <span class="ac-pill <?= $statusPill ?>"><?= esc(ucfirst($p['status'])) ?></span>
                    <?php if ($p['status'] === 'rejected' && $p['cashier_remarks']): ?>
                        <div class="ac-muted" style="font-size:11.5px; margin-top:4px;"><?= esc($p['cashier_remarks']) ?></div>
                    <?php endif ?>
                </div>
                <div data-label="Actions">
                    <?php if ($p['status'] === 'pending'): ?>
                        <div class="cs-proof-actions" id="csActions<?= (int) $p['proof_id'] ?>">
                            <div class="cs-proof-actions__default" style="display:flex; gap:8px;">
                                <form method="post" action="<?= site_url('finance/payment-proofs/' . $p['proof_id'] . '/approve') ?>" data-confirm="Approve this proof and record ₱<?= number_format($amount, 2) ?> as the <?= esc($planLabel, 'attr') ?> payment?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ac-btn ac-btn-primary">Approve</button>
                                </form>
                                <button type="button" class="ac-btn-outline" onclick="csToggleReject(<?= (int) $p['proof_id'] ?>, true)">Reject</button>
                            </div>
                            <form method="post" action="<?= site_url('finance/payment-proofs/' . $p['proof_id'] . '/reject') ?>" class="cs-proof-actions__reject" style="display:none; gap:6px;" data-confirm="Reject this payment proof? The student will see your reason.">
                                <?= csrf_field() ?>
                                <input type="text" name="cashier_remarks" class="ac-input" placeholder="Reason (required)" required minlength="3" style="min-width:140px;">
                                <button type="submit" class="ac-btn ac-btn-primary">Confirm</button>
                                <button type="button" class="ac-btn-outline" onclick="csToggleReject(<?= (int) $p['proof_id'] ?>, false)">Cancel</button>
                            </form>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<!-- Record Payment -->
<div class="cs-panel" id="cs-record" hidden>
    <div class="ac-card">
        <h3 style="margin-top:0;">Record a Payment</h3>
        <form method="post" action="<?= site_url('finance/cashier/student/' . $s['student_id'] . '/payment') ?>" class="ac-form-grid">
            <?= csrf_field() ?>
            <div class="ac-field" style="margin:0;">
                <label>Academic Year</label>
                <select name="academic_year" class="ac-input" required>
                    <?php foreach ($assessments as $a): ?>
                        <option value="<?= esc($a['academic_year']) ?>"><?= esc($a['academic_year']) ?> (Bal: <?= number_format((float) $a['balance'], 2) ?>)</option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Amount</label>
                <input type="number" name="amount" class="ac-input" step="0.01" min="0.01" required>
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Method</label>
                <select name="method" class="ac-input" required>
                    <?php foreach ($methods as $m): ?>
                        <option value="<?= esc($m) ?>"><?= esc(ucfirst(str_replace('_', ' ', $m))) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Reference No.</label>
                <input type="text" name="reference_no" class="ac-input" placeholder="Optional">
            </div>
            <button type="submit" class="ac-btn ac-btn-primary" data-confirm="Record this payment and issue an Official Receipt?">Record &amp; Issue OR</button>
        </form>
    </div>
</div>

<!-- Change Plan -->
<div class="cs-panel" id="cs-change" hidden>
    <div class="ac-card">
        <h3 style="margin-top:0;">Change Installment Plan</h3>
        <form method="post" action="<?= site_url('finance/cashier/student/' . $s['student_id'] . '/installments') ?>" id="installmentForm" class="ac-form-grid">
            <?= csrf_field() ?>
            <div class="ac-field" style="margin:0;">
                <label>Academic Year</label>
                <select name="academic_year" id="installmentYear" class="ac-input" required>
                    <?php foreach ($assessments as $a): ?>
                        <option value="<?= esc($a['academic_year']) ?>"><?= esc($a['academic_year']) ?> (Total: <?= number_format((float) $a['total_amount'], 2) ?>)</option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Installment Plan</label>
                <select name="installment_count" id="installmentCount" class="ac-input" required>
                    <option value="1">Full Payment</option>
                    <option value="2">2 Payments</option>
                    <option value="3">3 Payments</option>
                    <option value="4">4 Payments</option>
                    <option value="5">5 Payments</option>
                </select>
            </div>
            <button type="submit" class="ac-btn ac-btn-primary" id="installmentSubmit" data-confirm="Rebuild this student's installment schedule with the selected plan?">Update Plan</button>
        </form>

        <div id="installmentPreview" style="margin-top:16px;">
            <p style="font-size:11px; font-weight:700; color:var(--color-text-secondary); text-transform:uppercase; letter-spacing:.04em; margin:0 0 8px;">Preview — what this plan will generate</p>
            <div id="installmentPreviewBody" class="ac-muted" style="font-size:13px;">Loading…</div>
        </div>
    </div>
</div>

<!-- History -->
<div class="cs-panel" id="cs-history" hidden>
    <h3 style="margin-top:0;">Assessment History</h3>
    <div class="ac-dtable cs-assesstable" style="margin-bottom:24px;">
        <div class="ac-dtable__head">
            <div>Academic Year</div><div>Total</div><div>Discounts</div><div>Paid</div><div>Balance</div><div>Status</div>
        </div>
        <?php foreach ($assessments as $a): ?>
            <div class="ac-dtable__row">
                <div data-label="Academic Year"><?= esc($a['academic_year']) ?></div>
                <div data-label="Total"><?= number_format((float) $a['total_amount'], 2) ?></div>
                <div data-label="Discounts"><?= ((float) ($a['discount_amount'] ?? 0)) > 0 ? '−' . number_format((float) $a['discount_amount'], 2) : '—' ?></div>
                <div data-label="Paid"><?= number_format((float) $a['amount_paid'], 2) ?></div>
                <div data-label="Balance"><?= number_format((float) $a['balance'], 2) ?></div>
                <div data-label="Status"><span class="ac-status <?= $a['status'] === 'paid' ? 'locked' : '' ?>"><?= esc(ucfirst($a['status'])) ?></span></div>
            </div>
        <?php endforeach ?>
    </div>

    <h3>Payment History</h3>
    <?php if ($payments === []): ?>
        <div class="ac-empty-block"><p>No payments recorded yet.</p></div>
    <?php else: ?>
    <div class="ac-dtable cs-paymenttable">
        <div class="ac-dtable__head">
            <div>Date</div><div>Amount</div><div>Method</div><div>Reference</div><div>Receipt</div>
        </div>
        <?php foreach ($payments as $p): ?>
            <div class="ac-dtable__row">
                <div data-label="Date"><?= esc(date('M j, Y g:i A', strtotime($p['payment_date']))) ?></div>
                <div data-label="Amount"><?= number_format((float) $p['amount'], 2) ?></div>
                <div data-label="Method"><?= esc(ucfirst(str_replace('_', ' ', $p['method']))) ?></div>
                <div data-label="Reference"><?= $p['reference_no'] !== null ? esc($p['reference_no']) : '—' ?></div>
                <div data-label="Receipt">
                    <?php if (! empty($p['or_number'])): ?>
                        <a href="<?= site_url('student/tuition/receipt/' . $p['receipt_id']) ?>" target="_blank"><?= esc($p['or_number']) ?></a>
                        <?php if ($p['receipt_status'] === 'voided'): ?><span class="ac-muted">(voided)</span><?php endif ?>
                    <?php else: ?>
                        —
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<script>
function csTab(e, name) {
    e.preventDefault();
    document.querySelectorAll('.cs-panel').forEach(function (p) { p.hidden = true; });
    document.querySelectorAll('.ac-tabs__link').forEach(function (l) { l.classList.remove('is-active'); });
    document.getElementById('cs-' + name).hidden = false;
    document.querySelector('.ac-tabs__link[data-tab="' + name + '"]').classList.add('is-active');
    return false;
}

// Reject starts collapsed to a single button — the reason field only
// appears once the reviewer actually commits to rejecting, instead of a
// permanent text box on every pending row competing for space with
// Approve.
function csToggleReject(proofId, show) {
    var wrap = document.getElementById('csActions' + proofId);
    wrap.querySelector('.cs-proof-actions__default').style.display = show ? 'none' : 'flex';
    var form = wrap.querySelector('.cs-proof-actions__reject');
    form.style.display = show ? 'flex' : 'none';
    if (show) form.querySelector('input[name="cashier_remarks"]').focus();
}

(function () {
    var yearEl   = document.getElementById('installmentYear');
    var countEl  = document.getElementById('installmentCount');
    var body     = document.getElementById('installmentPreviewBody');
    var submit   = document.getElementById('installmentSubmit');
    var previewUrl = <?= json_encode(site_url('finance/cashier/student/' . $s['student_id'] . '/installments/preview')) ?>;
    var currentCountByYear = <?= json_encode($currentCountByYear) ?>;
    var fetchToken = 0;
    var loaded = false;

    // Start the picker pointed at what's actually saved for the chosen
    // year, not whatever option happens to be first in the <select> — a
    // year with no plan at all yet (nothing in currentCountByYear) keeps
    // whatever's already selected.
    function syncToCurrentPlan() {
        var current = currentCountByYear[yearEl.value];
        if (current) countEl.value = String(current);
    }

    function loadPreview() {
        var year = yearEl.value, count = countEl.value;
        if (!year || !count) return;

        var ticket = ++fetchToken;
        body.textContent = 'Loading…';
        body.className = 'ac-muted';

        fetch(previewUrl + '?academic_year=' + encodeURIComponent(year) + '&installment_count=' + encodeURIComponent(count), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (ticket !== fetchToken) return; // a newer request superseded this one

                if (!data.ok) {
                    body.textContent = data.error || 'Could not generate a preview.';
                    body.className = 'ac-muted';
                    submit.dataset.confirm = 'Rebuild this student\'s installment schedule with the selected plan?';
                    return;
                }

                var table = document.createElement('table');
                table.className = 'ac-table';
                table.innerHTML = '<thead><tr><th>Installment</th><th>Due Date</th><th>Amount</th></tr></thead>';
                var tbody = document.createElement('tbody');

                data.rows.forEach(function (row) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td>' + row.label + '</td><td>' + (row.due_date || '—') + '</td><td>' + row.amount + '</td>';
                    tbody.appendChild(tr);
                });

                table.appendChild(tbody);
                body.className = '';
                body.replaceChildren(table);

                var summary = data.rows.map(function (row) { return row.label + ' ' + row.amount + (row.due_date ? ' (' + row.due_date + ')' : ''); }).join('; ');
                submit.dataset.confirm = 'Rebuild this student\'s installment schedule as follows: ' + summary + '?';
            })
            .catch(function () {
                if (ticket !== fetchToken) return;
                body.textContent = 'Could not load a preview — you can still save, but double-check the result afterward.';
                body.className = 'ac-muted';
            });
    }

    if (yearEl && countEl) {
        yearEl.addEventListener('change', function () { syncToCurrentPlan(); loadPreview(); });
        countEl.addEventListener('change', loadPreview);

        // The Change Plan tab starts hidden — no point fetching a preview
        // nobody can see yet. Load it lazily the first time that tab is
        // actually opened.
        document.querySelector('.ac-tabs__link[data-tab="change"]').addEventListener('click', function () {
            if (loaded) return;
            loaded = true;
            syncToCurrentPlan();
            loadPreview();
        });
    }
})();
</script>

<?php endif ?>

<?= $this->endSection() ?>

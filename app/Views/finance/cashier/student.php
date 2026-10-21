<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<?php $s = $student; ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/cashier') ?>" style="font-size:12.5px; color:var(--color-text-secondary);">&larr; Back to Cashier</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;"><?= esc(trim($s['first_name'] . ' ' . $s['last_name'])) ?></h2>
        <p class="ac-page-header__sub"><?= esc($s['student_number']) ?> &middot; <?= esc($s['program_name'] ?? 'No program set') ?></p>
    </div>
</div>

<h3>Assessments</h3>
<div class="ac-table-card" style="margin-bottom:20px;">
    <table class="ac-table">
        <thead><tr><th>Academic Year</th><th>Total</th><th>Discounts</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
        <tbody>
            <?php if ($assessments === []): ?>
                <tr><td colspan="6" class="ac-muted">No assessments yet.</td></tr>
            <?php endif ?>
            <?php foreach ($assessments as $a): ?>
                <tr>
                    <td><?= esc($a['academic_year']) ?></td>
                    <td><?= number_format((float) $a['total_amount'], 2) ?></td>
                    <td><?= ((float) ($a['discount_amount'] ?? 0)) > 0 ? '−' . number_format((float) $a['discount_amount'], 2) : '—' ?></td>
                    <td><?= number_format((float) $a['amount_paid'], 2) ?></td>
                    <td><?= number_format((float) $a['balance'], 2) ?></td>
                    <td><span class="ac-status <?= $a['status'] === 'paid' ? 'locked' : '' ?>"><?= esc(ucfirst($a['status'])) ?></span></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<?php $pendingProofs = array_filter($proofs, static fn (array $p) => $p['status'] === 'pending'); ?>
<?php if ($proofs !== []): ?>
<h3>Payment Proofs<?= $pendingProofs !== [] ? ' <span class="ac-pill ac-pill--warning" style="vertical-align:middle;">' . count($pendingProofs) . ' pending</span>' : '' ?></h3>
<div class="ac-table-card" style="margin-bottom:20px;">
    <table class="ac-table">
        <thead><tr><th>Uploaded</th><th>Amount &amp; Installment</th><th>File</th><th>Reference</th><th>Status</th><th style="width:300px;"></th></tr></thead>
        <tbody>
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
                <tr>
                    <td><?= esc(date('M j, Y g:i A', strtotime($p['created_at']))) ?></td>
                    <td>
                        ₱<?= number_format($amount, 2) ?>
                        <br><span class="ac-pill ac-pill--neutral"><?= esc($planLabel) ?></span>
                    </td>
                    <td>
                        <button type="button" class="ac-btn-outline" onclick="acOpenFileModal('<?= site_url('student/tuition/proof/' . $p['proof_id']) ?>', '<?= esc($p['original_filename'], 'js') ?>')" title="<?= esc($p['original_filename'], 'attr') ?>">
                            <span class="material-symbols-outlined" aria-hidden="true" style="font-size:16px; vertical-align:-3px;">visibility</span> View
                        </button>
                    </td>
                    <td><?= esc($p['remarks'] ?: '—') ?></td>
                    <td>
                        <span class="ac-pill <?= $statusPill ?>"><?= esc(ucfirst($p['status'])) ?></span>
                        <?php if ($p['status'] === 'rejected' && $p['cashier_remarks']): ?>
                            <div class="ac-muted" style="font-size:11.5px; margin-top:4px;"><?= esc($p['cashier_remarks']) ?></div>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if ($p['status'] === 'pending'): ?>
                            <div style="display:flex; gap:8px;">
                                <form method="post" action="<?= site_url('finance/payment-proofs/' . $p['proof_id'] . '/approve') ?>" data-confirm="Approve this proof and record ₱<?= number_format($amount, 2) ?> as the <?= esc($planLabel, 'attr') ?> payment?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ac-btn ac-btn-primary">Approve</button>
                                </form>
                                <form method="post" action="<?= site_url('finance/payment-proofs/' . $p['proof_id'] . '/reject') ?>" style="display:flex; gap:6px;" data-confirm="Reject this payment proof? The student will see your reason.">
                                    <?= csrf_field() ?>
                                    <input type="text" name="cashier_remarks" class="ac-input" placeholder="Reason (required)" required minlength="3" style="min-width:120px;">
                                    <button type="submit" class="ac-btn-outline">Reject</button>
                                </form>
                            </div>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>

<?php if ($assessments !== []): ?>
<div class="ac-card" style="margin-bottom:20px;">
    <h3>Record a Payment</h3>
    <form method="post" action="<?= site_url('finance/cashier/student/' . $s['student_id'] . '/payment') ?>" style="display:grid; grid-template-columns:1.5fr 1fr 1fr 1fr auto; gap:10px; align-items:end;">
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

<div class="ac-card" style="margin-bottom:20px;">
    <h3>Change Installment Plan</h3>
    <form method="post" action="<?= site_url('finance/cashier/student/' . $s['student_id'] . '/installments') ?>" id="installmentForm" style="display:grid; grid-template-columns:1.5fr 1.5fr auto; gap:10px; align-items:end;">
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

<script>
(function () {
    var yearEl   = document.getElementById('installmentYear');
    var countEl  = document.getElementById('installmentCount');
    var body     = document.getElementById('installmentPreviewBody');
    var submit   = document.getElementById('installmentSubmit');
    var previewUrl = <?= json_encode(site_url('finance/cashier/student/' . $s['student_id'] . '/installments/preview')) ?>;
    var fetchToken = 0;

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

    yearEl.addEventListener('change', loadPreview);
    countEl.addEventListener('change', loadPreview);
    loadPreview();
})();
</script>
<?php endif ?>

<h3>Payment History</h3>
<div class="ac-table-card">
    <table class="ac-table">
        <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Receipt</th></tr></thead>
        <tbody>
            <?php if ($payments === []): ?>
                <tr><td colspan="5" class="ac-muted">No payments recorded yet.</td></tr>
            <?php endif ?>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= esc(date('M j, Y g:i A', strtotime($p['payment_date']))) ?></td>
                    <td><?= number_format((float) $p['amount'], 2) ?></td>
                    <td><?= esc(ucfirst(str_replace('_', ' ', $p['method']))) ?></td>
                    <td><?= $p['reference_no'] !== null ? esc($p['reference_no']) : '—' ?></td>
                    <td>
                        <?php if (! empty($p['or_number'])): ?>
                            <a href="<?= site_url('student/tuition/receipt/' . $p['receipt_id']) ?>" target="_blank"><?= esc($p['or_number']) ?></a>
                            <?php if ($p['receipt_status'] === 'voided'): ?><span class="ac-muted">(voided)</span><?php endif ?>
                        <?php else: ?>
                            —
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Payment Proofs</h2>
        <p class="ac-page-header__sub">Pending proof-of-payment uploads awaiting review. Approving writes the payment to the student's ledger; rejecting requires a reason shown to the student.</p>
    </div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<?php if (empty($proofs)): ?>
    <div class="ac-empty-block">
        <p>No proofs pending review.</p>
    </div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Uploaded</th>
                    <th>File</th>
                    <th>Remarks</th>
                    <th style="width:320px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($proofs as $proof): ?>
                    <tr>
                        <td><?= esc($proof['first_name'] . ' ' . $proof['last_name']) ?> <span class="ac-muted">(<?= esc($proof['student_number']) ?>)</span></td>
                        <td><?= esc(date('M j, Y g:i A', strtotime($proof['created_at']))) ?></td>
                        <td><a href="<?= site_url('student/tuition/proof/' . $proof['proof_id']) ?>" target="_blank"><?= esc($proof['original_filename']) ?></a></td>
                        <td><?= esc($proof['remarks']) ?></td>
                        <td>
                            <div style="display:flex; gap:8px;">
                                <form method="post" action="<?= site_url($basePath . '/payment-proofs/' . $proof['proof_id'] . '/approve') ?>" data-confirm="Approve this proof and record the payment?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ac-btn ac-btn-primary">Approve</button>
                                </form>
                                <form method="post" action="<?= site_url($basePath . '/payment-proofs/' . $proof['proof_id'] . '/reject') ?>" style="display:flex; gap:6px;">
                                    <?= csrf_field() ?>
                                    <input type="text" name="cashier_remarks" class="ac-input" placeholder="Reason (required)" required minlength="3" style="min-width:150px;">
                                    <button type="submit" class="ac-btn-outline">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

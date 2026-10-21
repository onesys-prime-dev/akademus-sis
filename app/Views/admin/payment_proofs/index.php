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
    <div class="ac-dtable ac-pftable">

        <div class="ac-dtable__head">
            <div>Student</div>
            <div>Amount &amp; Installment</div>
            <div>File</div>
            <div>Uploaded</div>
            <div>Reference</div>
            <div style="text-align:right;">Actions</div>
        </div>

        <?php foreach ($proofs as $proof): ?>
            <?php
                $fullName  = trim($proof['first_name'] . ' ' . $proof['last_name']);
                $initials  = strtoupper(substr($proof['first_name'], 0, 1) . substr($proof['last_name'], 0, 1));
                $photoUrl  = avatar_photo_url((int) $proof['user_id'], $proof['photo_mime'] ?? null);
                $amount    = $proof['schedule_id'] !== null ? (float) $proof['schedule_amount'] : (float) $proof['assessment_balance'];
                $planLabel = $proof['schedule_id'] !== null ? $proof['schedule_label'] : 'General Payment (full balance)';
                $recordUrl = $basePath === 'finance'
                    ? site_url('finance/cashier/student/' . $proof['student_id'])
                    : site_url($basePath . '/students/' . $proof['student_id']);
            ?>
            <div class="ac-dtable__row ac-pftable__row" data-href="<?= $recordUrl ?>" title="View <?= esc($fullName, 'attr') ?>'s record" tabindex="0" role="link">

                <div class="ac-user-cell" data-label="Student">
                    <div class="ac-avatar" style="<?= $photoUrl === null ? 'background:' . esc(avatar_color($fullName), 'attr') . ';color:#fff;' : '' ?>" aria-hidden="true">
                        <?php if ($photoUrl !== null): ?>
                            <img src="<?= esc($photoUrl) ?>" alt="">
                        <?php else: ?>
                            <?= esc($initials) ?>
                        <?php endif ?>
                    </div>
                    <div>
                        <p class="ac-user-cell__name" style="color:var(--color-primary);"><?= esc($fullName) ?></p>
                        <p class="ac-user-cell__id"><?= esc($proof['student_number']) ?></p>
                    </div>
                </div>

                <div data-label="Amount & Installment">
                    <p class="ac-cell-bold" style="margin:0;">₱<?= number_format($amount, 2) ?></p>
                    <span class="ac-pill ac-pill--neutral" style="margin-top:4px; display:inline-block;"><?= esc($planLabel) ?></span>
                </div>

                <div class="ac-cell-muted" data-label="File">
                    <button type="button" class="ac-btn-outline" onclick="acOpenFileModal('<?= site_url('student/tuition/proof/' . $proof['proof_id']) ?>', '<?= esc($proof['original_filename'], 'js') ?>')" title="<?= esc($proof['original_filename'], 'attr') ?>">
                        <span class="material-symbols-outlined" aria-hidden="true" style="font-size:16px; vertical-align:-3px;">visibility</span> View
                    </button>
                </div>

                <div class="ac-cell-muted" data-label="Uploaded"><?= esc(date('M j, Y g:i A', strtotime($proof['created_at']))) ?></div>

                <div class="ac-cell-muted" data-label="Reference"><?= esc($proof['remarks'] ?: '—') ?></div>

                <div class="ac-actions-cell" data-label="Actions">
                    <form method="post" action="<?= site_url($basePath . '/payment-proofs/' . $proof['proof_id'] . '/approve') ?>" data-confirm="Approve this proof and record ₱<?= number_format($amount, 2) ?> as the <?= esc($planLabel, 'attr') ?> payment?">
                        <?= csrf_field() ?>
                        <button type="submit" class="ac-btn ac-btn-primary">Approve</button>
                    </form>
                    <form method="post" action="<?= site_url($basePath . '/payment-proofs/' . $proof['proof_id'] . '/reject') ?>" style="display:flex; gap:6px;" data-confirm="Reject this payment proof? The student will see your reason.">
                        <?= csrf_field() ?>
                        <input type="text" name="cashier_remarks" class="ac-input" placeholder="Reason (required)" required minlength="3" style="min-width:140px;">
                        <button type="submit" class="ac-btn-outline">Reject</button>
                    </form>
                </div>

            </div>
        <?php endforeach ?>

    </div>
<?php endif ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    // Clicking anywhere on a payment-proof row opens that student's record,
    // except on the row's own interactive controls (View/Approve/Reject,
    // the reason field) — those keep their normal behavior instead of also
    // triggering a navigation underneath them.
    document.querySelectorAll('.ac-pftable__row').forEach(function (row) {
        function go(e) {
            if (e.target.closest('a, button, input, form')) return;
            window.location.href = row.dataset.href;
        }
        row.style.cursor = 'pointer';
        row.addEventListener('click', go);
        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); window.location.href = row.dataset.href; }
        });
    });
</script>
<?= $this->endSection() ?>

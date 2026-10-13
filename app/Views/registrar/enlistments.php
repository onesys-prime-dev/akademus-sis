<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Enlistment Approvals</h2><p class="ac-page-header__sub">Dean-noted carts awaiting final approval. Approving converts each item into a real enrollment.</p></div>
</div>

<?php if (session('errors')): ?>
    <div class="ac-alert" style="background:var(--color-danger-soft); margin-bottom:16px;">
        <div class="ac-alert__text" style="color:var(--color-danger);">
            <?php foreach (session('errors') as $error): ?><p><?= esc($error) ?></p><?php endforeach ?>
        </div>
    </div>
<?php endif ?>
<?php if (session('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;"><span class="ac-notice__text"><?= esc(session('message')) ?></span></div>
<?php endif ?>

<?php if ($requests === []): ?>
    <div class="ac-empty-block">Nothing awaiting final approval.</div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead><tr><th>Student</th><th>Student #</th><th>Program</th><th>Academic Year</th><th>Dean-Noted</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?= esc($r['username']) ?></td>
                        <td><?= esc($r['student_number']) ?></td>
                        <td><?= esc($r['program_name'] ?? '—') ?></td>
                        <td><?= esc($r['academic_year']) ?></td>
                        <td><?= esc($r['dean_noted_at']) ?></td>
                        <td>
                            <form method="post" action="<?= site_url('registrar/enlistments/approve') ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="ac-btn-outline" data-confirm="Approve and enroll this student into every cart item?">Approve &amp; Enroll</button>
                            </form>
                            <form method="post" action="<?= site_url('registrar/enlistments/return') ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <input type="text" name="notes" placeholder="Reason (required)" style="width:160px; font-size:11.5px;" required>
                                <button type="submit" class="ac-btn-outline">Return</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

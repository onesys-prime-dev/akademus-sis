<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<?php $s = $student; ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/additional-fees') ?>" style="font-size:12.5px; color:var(--color-text-secondary);">&larr; Back to Additional Fees</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;"><?= esc(trim($s['first_name'] . ' ' . $s['last_name'])) ?></h2>
        <p class="ac-page-header__sub"><?= esc($s['student_number']) ?> &middot; <?= esc($s['program_name'] ?? 'No program set') ?></p>
    </div>
</div>

<div class="ac-card" style="margin-bottom:20px;">
    <h3>Add a Fee</h3>
    <form method="post" action="<?= site_url('finance/additional-fees/student/' . $s['student_id'] . '/grant') ?>" style="display:grid; grid-template-columns:1.5fr 2fr 1fr 2fr auto; gap:10px; align-items:end;">
        <?= csrf_field() ?>
        <div class="ac-field" style="margin:0;">
            <label>Academic Year</label>
            <input type="text" name="academic_year" class="ac-input" value="<?= esc($activeTerm ?? '') ?>" placeholder="AY 2026 - 2027 · 1st Semester" required>
        </div>
        <div class="ac-field" style="margin:0;">
            <label>Fee Label</label>
            <input type="text" name="label" class="ac-input" placeholder="e.g. University Week T-Shirt" required maxlength="100">
        </div>
        <div class="ac-field" style="margin:0;">
            <label>Amount</label>
            <input type="number" name="amount" class="ac-input" step="0.01" min="0.01" required>
        </div>
        <div class="ac-field" style="margin:0;">
            <label>Remarks</label>
            <input type="text" name="remarks" class="ac-input" placeholder="Optional">
        </div>
        <button type="submit" class="ac-btn ac-btn-primary">Add</button>
    </form>
</div>

<h3>Fee History</h3>
<div class="ac-table-card">
    <table class="ac-table">
        <thead><tr><th>Fee</th><th>Amount</th><th>Academic Year</th><th>Status</th><th>Added</th><th></th></tr></thead>
        <tbody>
            <?php if ($fees === []): ?>
                <tr><td colspan="6" class="ac-muted">No additional fees charged yet.</td></tr>
            <?php endif ?>
            <?php foreach ($fees as $f): ?>
                <tr>
                    <td><?= esc($f['label']) ?><?php if ($f['remarks']): ?><br><small class="ac-muted"><?= esc($f['remarks']) ?></small><?php endif ?></td>
                    <td>₱<?= number_format((float) $f['amount'], 2) ?></td>
                    <td><?= esc($f['academic_year']) ?></td>
                    <td><span class="ac-status <?= $f['status'] === 'active' ? 'locked' : '' ?>"><?= esc(ucfirst($f['status'])) ?></span></td>
                    <td><?= esc(date('M j, Y', strtotime($f['granted_at']))) ?></td>
                    <td>
                        <?php if ($f['status'] === 'active'): ?>
                            <form method="post" action="<?= site_url('finance/additional-fees/fee/' . $f['id'] . '/revoke') ?>" onsubmit="return confirm('Revoke this fee? It will stop being applied on future recomputes.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="ac-btn-outline">Revoke</button>
                            </form>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>

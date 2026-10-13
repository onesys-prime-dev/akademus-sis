<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<?php $s = $student; ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/discounts') ?>" style="font-size:12.5px; color:var(--color-text-secondary);">&larr; Back to Discounts</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;"><?= esc(trim($s['first_name'] . ' ' . $s['last_name'])) ?></h2>
        <p class="ac-page-header__sub"><?= esc($s['student_number']) ?> &middot; <?= esc($s['program_name'] ?? 'No program set') ?></p>
    </div>
</div>

<div class="ac-card" style="margin-bottom:20px;">
    <h3>Grant a Discount</h3>
    <?php if ($discountTypes === []): ?>
        <p class="panel-sub">No active discount types — add one from the Discounts catalog first.</p>
    <?php else: ?>
        <form method="post" action="<?= site_url('finance/discounts/student/' . $s['student_id'] . '/grant') ?>" style="display:grid; grid-template-columns:2fr 1fr 1fr 2fr auto; gap:10px; align-items:end;">
            <?= csrf_field() ?>
            <div class="ac-field" style="margin:0;">
                <label>Discount Type</label>
                <select name="discount_type_id" class="ac-input" required>
                    <?php foreach ($discountTypes as $d): ?>
                        <option value="<?= (int) $d['id'] ?>"><?= esc($d['name']) ?> (<?= $d['value_type'] === 'percentage' ? esc($d['default_value']) . '%' : '₱' . number_format((float) $d['default_value'], 2) ?>)</option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Academic Year</label>
                <input type="text" name="academic_year" class="ac-input" placeholder="Blank = every term">
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Override Value</label>
                <input type="number" name="override_value" class="ac-input" step="0.01" min="0.01" placeholder="Optional">
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Remarks</label>
                <input type="text" name="remarks" class="ac-input" placeholder="Optional">
            </div>
            <button type="submit" class="ac-btn ac-btn-primary">Grant</button>
        </form>
    <?php endif ?>
</div>

<h3>Grant History</h3>
<div class="ac-table-card">
    <table class="ac-table">
        <thead><tr><th>Discount</th><th>Value</th><th>Academic Year</th><th>Status</th><th>Granted</th><th></th></tr></thead>
        <tbody>
            <?php if ($grants === []): ?>
                <tr><td colspan="6" class="ac-muted">No discounts granted yet.</td></tr>
            <?php endif ?>
            <?php foreach ($grants as $g): ?>
                <tr>
                    <td><?= esc($g['name']) ?></td>
                    <td><?= $g['override_value'] !== null ? (esc($g['override_value']) . ($g['value_type'] === 'percentage' ? '%' : '')) : esc($g['default_value']) . ($g['value_type'] === 'percentage' ? '%' : '') ?></td>
                    <td><?= $g['academic_year'] !== null ? esc($g['academic_year']) : 'Every term' ?></td>
                    <td><span class="ac-status <?= $g['status'] === 'active' ? 'locked' : '' ?>"><?= esc(ucfirst($g['status'])) ?></span></td>
                    <td><?= esc(date('M j, Y', strtotime($g['granted_at']))) ?></td>
                    <td>
                        <?php if ($g['status'] === 'active'): ?>
                            <form method="post" action="<?= site_url('finance/discounts/grant/' . $g['id'] . '/revoke') ?>" onsubmit="return confirm('Revoke this discount? It will stop being applied on future recomputes.');">
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

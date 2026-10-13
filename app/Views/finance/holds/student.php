<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<?php $s = $student; ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/holds') ?>" style="font-size:12.5px; color:var(--color-text-secondary);">&larr; Back to Holds</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;"><?= esc(trim($s['first_name'] . ' ' . $s['last_name'])) ?></h2>
        <p class="ac-page-header__sub"><?= esc($s['student_number']) ?> &middot; <?= esc($s['program_name'] ?? 'No program set') ?></p>
    </div>
</div>

<?php $hasActive = false; foreach ($holds as $h) { if ($h['status'] === 'active') { $hasActive = true; break; } } ?>

<?php if (! $hasActive): ?>
<div class="ac-card" style="margin-bottom:20px;">
    <h3>Place a Hold</h3>
    <form method="post" action="<?= site_url('finance/holds/student/' . $s['student_id'] . '/place') ?>" style="display:grid; grid-template-columns:1fr 2fr auto; gap:10px; align-items:end;">
        <?= csrf_field() ?>
        <div class="ac-field" style="margin:0;">
            <label>Type</label>
            <select name="hold_type" class="ac-input" required>
                <option value="delinquent">Delinquent</option>
                <option value="unpaid_balance">Unpaid Balance</option>
                <option value="manual">Manual</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="ac-field" style="margin:0;">
            <label>Reason</label>
            <input type="text" name="reason" class="ac-input" required minlength="5">
        </div>
        <button type="submit" class="ac-btn ac-btn-primary" data-confirm="Place this hold? It will block enrollment, dropping classes, and document release until released.">Place Hold</button>
    </form>
</div>
<?php endif ?>

<h3>Hold History</h3>
<div class="ac-table-card">
    <table class="ac-table">
        <thead><tr><th>Type</th><th>Reason</th><th>Status</th><th>Placed</th><th>Released</th><th></th></tr></thead>
        <tbody>
            <?php if ($holds === []): ?>
                <tr><td colspan="6" class="ac-muted">No holds on record.</td></tr>
            <?php endif ?>
            <?php foreach ($holds as $h): ?>
                <tr>
                    <td><?= esc(ucfirst(str_replace('_', ' ', $h['hold_type']))) ?></td>
                    <td><?= esc($h['reason']) ?></td>
                    <td><span class="ac-status <?= $h['status'] === 'active' ? '' : 'locked' ?>"><?= esc(ucfirst($h['status'])) ?></span></td>
                    <td><?= esc(date('M j, Y', strtotime($h['placed_at']))) ?></td>
                    <td><?= $h['released_at'] !== null ? esc(date('M j, Y', strtotime($h['released_at']))) : '—' ?></td>
                    <td>
                        <?php if ($h['status'] === 'active'): ?>
                            <form method="post" action="<?= site_url('finance/holds/' . $h['id'] . '/release') ?>" onsubmit="return confirm('Release this hold?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="ac-btn-outline">Release</button>
                            </form>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>

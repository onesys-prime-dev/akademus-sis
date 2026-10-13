<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<?php $c = $clearanceCase; ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('registrar/clearance') ?>" style="font-size:12.5px; color:var(--color-text-secondary);">&larr; Back to Clearance Queue</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;"><?= esc($c['username']) ?></h2>
        <p class="ac-page-header__sub"><?= esc($c['student_number']) ?> &middot; <?= esc($c['academic_year']) ?> &middot; <span class="ac-pill <?= $c['status'] === 'cleared' ? 'ac-pill--success' : ($c['status'] === 'denied' ? 'ac-pill--danger' : '') ?>"><?= esc(ucfirst($c['status'])) ?></span></p>
    </div>
    <?php if ($c['status'] === 'pending'): ?>
        <form method="post" action="<?= site_url('registrar/clearance/' . $c['id'] . '/finalize') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="ac-btn ac-btn-primary" <?= $fullyCleared ? '' : 'disabled' ?> data-confirm="Finalize graduation for this student? Their status will be set to Graduated.">Finalize Graduation</button>
        </form>
    <?php endif ?>
</div>

<?php if (! $fullyCleared && $c['status'] === 'pending'): ?>
    <div class="ac-alert-card">
        <div class="ac-alert-card__icon"><span class="material-symbols-outlined">pending_actions</span></div>
        <div>
            <h4 class="ac-alert-card__title">Not Yet Fully Cleared</h4>
            <p class="ac-alert-card__text">Every item below must be cleared before graduation can be finalized.</p>
        </div>
    </div>
<?php endif ?>

<div class="ac-table-card" style="margin-top:16px;">
    <table class="ac-table">
        <thead><tr><th>Office</th><th>Status</th><th>Remarks</th><th>Cleared</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= esc($item['requirement_name']) ?></td>
                    <td><span class="ac-pill <?= $item['status'] === 'cleared' ? 'ac-pill--success' : ($item['status'] === 'denied' ? 'ac-pill--danger' : '') ?>"><?= esc(ucfirst($item['status'])) ?></span></td>
                    <td><?= esc($item['remarks'] ?? '—') ?></td>
                    <td><?= $item['cleared_at'] ? esc(date('M j, Y', strtotime($item['cleared_at']))) : '—' ?></td>
                    <td>
                        <?php if ($item['status'] === 'pending' && in_array($item['office_role'], $authorizedOffices, true)): ?>
                            <form method="post" action="<?= site_url('registrar/clearance/' . $item['clearance_id'] . '/item/' . $item['id'] . '/clear') ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="text" name="remarks" placeholder="Remarks (optional)" style="width:140px; font-size:11.5px;">
                                <button type="submit" class="ac-btn-outline">Clear</button>
                            </form>
                            <form method="post" action="<?= site_url('registrar/clearance/' . $item['clearance_id'] . '/item/' . $item['id'] . '/deny') ?>" style="display:inline;" onsubmit="return confirm('Deny this item? A reason is required.');">
                                <?= csrf_field() ?>
                                <input type="text" name="remarks" placeholder="Reason (required)" style="width:140px; font-size:11.5px;" required>
                                <button type="submit" class="ac-btn-outline">Deny</button>
                            </form>
                        <?php elseif ($item['status'] === 'pending'): ?>
                            <span class="ac-muted" style="font-size:11.5px;">Owned by another office</span>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>

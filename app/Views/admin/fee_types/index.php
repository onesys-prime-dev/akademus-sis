<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Fee Types</h2>
        <p class="ac-page-header__sub">The dynamic fee catalog. Add a fee here once and it becomes available to price per program on the <a href="<?= site_url($basePath . '/tuition-rates') ?>">Tuition Rates</a> page — no code change needed for a new fee.</p>
    </div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<div class="ac-table-card" style="margin-bottom:20px;">
    <div style="padding:20px 24px;">
        <h4 style="margin:0 0 12px; font-size:14px;">Add a Fee Type</h4>
        <form method="post" action="<?= site_url($basePath . '/fee-types/store') ?>" style="display:grid; grid-template-columns:1fr 1fr 2fr auto auto; gap:10px; align-items:end;">
            <?= csrf_field() ?>
            <div class="ac-field" style="margin:0;">
                <label>Category</label>
                <select name="category" class="ac-input" required>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= esc($c) ?>" style="text-transform:capitalize;"><?= esc(ucfirst($c)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Code</label>
                <input type="text" name="code" class="ac-input" placeholder="LIBRARY" required maxlength="30">
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Name</label>
                <input type="text" name="name" class="ac-input" placeholder="Library" required maxlength="100">
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Sort Order</label>
                <input type="number" name="sort_order" class="ac-input" value="0" style="width:90px;">
            </div>
            <button type="submit" class="ac-btn ac-btn-primary">Add</button>
        </form>
    </div>
</div>

<?php if (empty($feeTypes)): ?>
    <div class="ac-empty-block">
        <p>No fee types yet.</p>
    </div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feeTypes as $ft): ?>
                    <tr>
                        <td style="text-transform:capitalize;"><?= esc($ft['category']) ?></td>
                        <td><code><?= esc($ft['code']) ?></code></td>
                        <td>
                            <form method="post" action="<?= site_url($basePath . '/fee-types/' . $ft['fee_type_id'] . '/update') ?>" style="display:flex; gap:8px; align-items:center;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="sort_order" value="<?= (int) $ft['sort_order'] ?>">
                                <select name="category" class="ac-input" style="width:auto;">
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= esc($c) ?>" <?= $c === $ft['category'] ? 'selected' : '' ?>><?= esc(ucfirst($c)) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <input type="text" name="name" class="ac-input" value="<?= esc($ft['name'], 'attr') ?>" style="width:auto;">
                                <button type="submit" class="ac-btn-outline">Save</button>
                            </form>
                        </td>
                        <td>
                            <span class="ac-status-dot <?= $ft['is_active'] ? 'ac-status-dot--active' : 'ac-status-dot--inactive' ?>">
                                <?= $ft['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="ac-actions-cell">
                            <form method="post" action="<?= site_url($basePath . '/fee-types/' . $ft['fee_type_id'] . '/toggle-active') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="ac-btn-outline" data-confirm="<?= $ft['is_active'] ? 'Deactivate' : 'Activate' ?> this fee type?">
                                    <?= $ft['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

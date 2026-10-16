<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Finance Settings</h2>
        <p class="ac-page-header__sub">Policies that control what students can do online while they still owe a balance.</p>
    </div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<div class="ac-card" style="max-width:560px;">
    <form method="post" action="<?= site_url('finance/settings/update') ?>">
        <?= csrf_field() ?>

        <div class="ac-field">
            <label for="allow_enlistment_with_balance">Allow online enlistment with an outstanding balance</label>
            <select id="allow_enlistment_with_balance" name="allow_enlistment_with_balance" class="ac-input">
                <option value="1" <?= $allowEnlistmentWithBalance ? 'selected' : '' ?>>Allow — students may enlist online regardless of balance</option>
                <option value="0" <?= ! $allowEnlistmentWithBalance ? 'selected' : '' ?>>Block — students with a balance must contact the registrar/finance office</option>
            </select>
        </div>

        <div style="margin-top:8px;">
            <button type="submit" class="ac-btn ac-btn-primary">Save Settings</button>
        </div>
    </form>
</div>

<?= $this->endSection() ?>

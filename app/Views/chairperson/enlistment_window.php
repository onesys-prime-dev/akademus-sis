<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'enlistment-window';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Enlistment Window</h2><p class="ac-page-header__sub">Open or close online enlistment for the active term.</p></div>
</div>

<?php if ($ay === null): ?>
    <div class="ac-empty-block">No active academic term is set. Contact the registrar.</div>
<?php else: ?>
    <div class="ac-card" style="max-width:420px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span class="ac-dept-name"><?= esc($ay['label']) ?> &middot; <?= esc($ay['semester']) ?></span>
            <span class="ac-pill <?= $isOpen ? 'ac-pill--success' : 'ac-pill--danger' ?>">Enlistment <?= $isOpen ? 'OPEN' : 'CLOSED' ?></span>
        </div>

        <form method="post" action="<?= site_url('chairperson/enlistment-window/update') ?>" style="display:grid; gap:8px; margin-top:14px;">
            <?= csrf_field() ?>
            <label style="font-size:11.5px;">Opens<input type="datetime-local" name="enlistment_opens_at" class="ac-input" value="<?= $ay['enlistment_opens_at'] ? esc(str_replace(' ', 'T', $ay['enlistment_opens_at'])) : '' ?>"></label>
            <label style="font-size:11.5px;">Closes<input type="datetime-local" name="enlistment_closes_at" class="ac-input" value="<?= $ay['enlistment_closes_at'] ? esc(str_replace(' ', 'T', $ay['enlistment_closes_at'])) : '' ?>"></label>
            <button type="submit" class="ac-btn ac-btn-primary ac-btn-block">Save Window</button>
        </form>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

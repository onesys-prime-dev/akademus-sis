<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Dashboard</h2>
        <p class="ac-page-header__sub"><?= esc($collegeName ?? 'Your college') ?> — grade approvals at a glance.</p>
    </div>
    <a href="<?= site_url('dean/grade-approvals') ?>" class="ac-btn ac-btn-primary">Open Grade Approvals</a>
</div>

<?php if ($collegeName === null): ?>
    <div class="ac-alert-card">
        <div class="ac-alert-card__icon"><span class="material-symbols-outlined">domain_disabled</span></div>
        <div>
            <h4 class="ac-alert-card__title">No college assigned</h4>
            <p class="ac-alert-card__text">Contact an administrator to assign a college to your account.</p>
        </div>
    </div>
<?php else: ?>

<div class="ac-bento">
    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon ac-bento-card__icon--warning"><span class="material-symbols-outlined icon-fill">pending_actions</span></div>
        </div>
        <p class="ac-bento-card__label">Awaiting Your Note</p>
        <h3 class="ac-bento-card__value"><?= esc($awaitingNoteCount) ?></h3>
    </div>

    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon ac-bento-card__icon--success"><span class="material-symbols-outlined icon-fill">task_alt</span></div>
        </div>
        <p class="ac-bento-card__label">Noted — With Registrar</p>
        <h3 class="ac-bento-card__value"><?= esc($notedCount) ?></h3>
    </div>
</div>

<?php endif ?>

<?= $this->endSection() ?>

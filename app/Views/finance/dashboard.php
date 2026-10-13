<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Dashboard</h2>
        <p class="ac-page-header__sub">Collections, pending proofs, and outstanding balances at a glance.</p>
    </div>
    <a href="<?= site_url('finance/cashier') ?>" class="ac-btn ac-btn-primary">Open Cashier</a>
</div>

<div class="ac-bento">
    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon ac-bento-card__icon--success"><span class="material-symbols-outlined icon-fill">payments</span></div>
        </div>
        <p class="ac-bento-card__label">Today's Collections</p>
        <h3 class="ac-bento-card__value">₱<?= number_format($todayCollections, 2) ?></h3>
    </div>

    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon ac-bento-card__icon--warning"><span class="material-symbols-outlined icon-fill">pending_actions</span></div>
        </div>
        <p class="ac-bento-card__label">Pending Payment Proofs</p>
        <h3 class="ac-bento-card__value"><?= esc($pendingProofs) ?></h3>
    </div>

    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon ac-bento-card__icon--warning"><span class="material-symbols-outlined icon-fill">account_balance_wallet</span></div>
        </div>
        <p class="ac-bento-card__label">Students With a Balance</p>
        <h3 class="ac-bento-card__value"><?= esc($highBalanceCount) ?></h3>
    </div>
</div>

<?= $this->endSection() ?>

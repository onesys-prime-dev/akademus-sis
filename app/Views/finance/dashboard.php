<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Welcome back, <?= esc($userName) ?></h2>
        <p class="ac-page-header__sub">Collections, pending proofs, and outstanding balances at a glance — <?= esc(date('l, F j, Y')) ?>.</p>
    </div>
    <a href="<?= site_url('finance/cashier') ?>" class="ac-btn ac-btn-primary">
        <span class="material-symbols-outlined" aria-hidden="true">point_of_sale</span>
        Open Cashier
    </a>
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
            <div class="ac-bento-card__icon ac-bento-card__icon--primary"><span class="material-symbols-outlined icon-fill">calendar_month</span></div>
        </div>
        <p class="ac-bento-card__label">This Month's Collections</p>
        <h3 class="ac-bento-card__value">₱<?= number_format($monthCollections, 2) ?></h3>
    </div>

    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon<?= $pendingProofs > 0 ? ' ac-bento-card__icon--warning' : ' ac-bento-card__icon--primary' ?>"><span class="material-symbols-outlined icon-fill">pending_actions</span></div>
            <?php if ($pendingProofs > 0): ?>
                <span class="ac-pill ac-pill--warning">Action required</span>
            <?php else: ?>
                <span class="ac-pill ac-pill--success">All clear</span>
            <?php endif ?>
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

<div class="ac-dash-grid">

    <section class="ac-table-card" aria-labelledby="finRecentTitle" style="margin-top:0;">
        <div style="padding:16px 20px; border-bottom:1px solid var(--color-border); display:flex; align-items:center; justify-content:space-between;">
            <div>
                <h2 id="finRecentTitle" style="font-size:15px; margin:0;">Recent Payments</h2>
                <p class="ac-muted" style="font-size:12px; margin:2px 0 0;">The last <?= count($recentPayments) ?> payments recorded across all students.</p>
            </div>
            <a href="<?= site_url('finance/cashier') ?>" style="font-size:12.5px; color:var(--color-primary); text-decoration:none; white-space:nowrap;">Open Cashier →</a>
        </div>

        <?php if ($recentPayments === []): ?>
            <div class="ac-empty-block" style="border:none; margin:0;"><p>No payments recorded yet.</p></div>
        <?php else: ?>
            <table class="ac-table">
                <thead><tr><th>Student</th><th>Amount</th><th>Method</th><th>Date</th><th>Receipt</th></tr></thead>
                <tbody>
                    <?php foreach ($recentPayments as $p): ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('finance/cashier/student/' . $p['student_id']) ?>" style="color:var(--color-primary); text-decoration:none; font-weight:600;"><?= esc(trim($p['first_name'] . ' ' . $p['last_name'])) ?></a>
                                <div class="ac-muted" style="font-size:11.5px;"><?= esc($p['student_number']) ?></div>
                            </td>
                            <td>₱<?= number_format((float) $p['amount'], 2) ?></td>
                            <td><?= esc(ucfirst(str_replace('_', ' ', $p['method']))) ?></td>
                            <td><?= esc(date('M j, Y g:i A', strtotime($p['payment_date']))) ?></td>
                            <td>
                                <?php if (! empty($p['or_number'])): ?>
                                    <a href="<?= site_url('student/tuition/receipt/' . $p['receipt_id']) ?>" target="_blank"><?= esc($p['or_number']) ?></a>
                                <?php else: ?>
                                    —
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </section>

    <aside>
        <?php $attentionCount = ($pendingProofs > 0 ? 1 : 0) + ($pendingClearance > 0 ? 1 : 0); ?>
        <section class="ac-table-card" aria-labelledby="finAttentionTitle" style="margin-top:0;">
            <div style="padding:16px 20px; border-bottom:1px solid var(--color-border); display:flex; align-items:center; justify-content:space-between;">
                <h2 id="finAttentionTitle" style="font-size:15px; margin:0;">Attention Needed</h2>
                <span class="ac-pill <?= $attentionCount === 0 ? 'ac-pill--success' : 'ac-pill--warning' ?>"><?= $attentionCount === 0 ? 'All clear' : $attentionCount . ' item' . ($attentionCount === 1 ? '' : 's') ?></span>
            </div>

            <div style="padding:16px;">
                <?php if ($attentionCount === 0): ?>
                    <div class="ac-attention-empty">
                        <span class="material-symbols-outlined" aria-hidden="true">verified</span>
                        <strong>You're all caught up</strong>
                        <p style="margin:0; font-size:12.5px;">No payment proofs or clearance items need your review right now.</p>
                    </div>
                <?php else: ?>
                    <div class="ac-task-list">
                        <?php if ($pendingProofs > 0): ?>
                            <a class="ac-task-row ac-task-row--warning" href="<?= site_url('finance/payment-proofs') ?>">
                                <span class="ac-task-row__icon"><span class="material-symbols-outlined" aria-hidden="true">receipt_long</span></span>
                                <span class="ac-task-row__body">
                                    <p class="ac-task-row__title"><?= $pendingProofs ?> payment proof<?= $pendingProofs === 1 ? '' : 's' ?> pending</p>
                                    <p class="ac-task-row__sub">Students waiting on your approval</p>
                                </span>
                                <span class="material-symbols-outlined ac-task-row__arrow" aria-hidden="true">chevron_right</span>
                            </a>
                        <?php endif ?>

                        <?php if ($pendingClearance > 0): ?>
                            <a class="ac-task-row ac-task-row--warning" href="<?= site_url('finance/clearance') ?>">
                                <span class="ac-task-row__icon"><span class="material-symbols-outlined" aria-hidden="true">checklist</span></span>
                                <span class="ac-task-row__body">
                                    <p class="ac-task-row__title"><?= $pendingClearance ?> clearance item<?= $pendingClearance === 1 ? '' : 's' ?> pending</p>
                                    <p class="ac-task-row__sub">Graduating students awaiting your sign-off</p>
                                </span>
                                <span class="material-symbols-outlined ac-task-row__arrow" aria-hidden="true">chevron_right</span>
                            </a>
                        <?php endif ?>
                    </div>
                <?php endif ?>
            </div>
        </section>
    </aside>

</div>

<?= $this->endSection() ?>

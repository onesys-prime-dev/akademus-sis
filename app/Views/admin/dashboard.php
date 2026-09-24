<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Dashboard</h2>
        <p class="ac-page-header__sub">Overview of institutional metrics and pending actions.</p>
    </div>
</div>

<?php if ($stats['unassigned'] > 0): ?>
    <div class="ac-alert-card" id="deptAlert">
        <div class="ac-alert-card__icon">
            <span class="material-symbols-outlined icon-fill">warning</span>
        </div>
        <div>
            <h4 class="ac-alert-card__title">Action Required: Unassigned Colleges</h4>
            <p class="ac-alert-card__text">
                <?= esc($stats['unassigned']) ?> college<?= $stats['unassigned'] > 1 ? 's are' : ' is' ?> currently lacking a designated chairperson.
            </p>
            <div class="ac-alert-card__actions">
                <a href="<?= site_url('admin/departments') ?>" class="ac-btn-outline">Review Assignments</a>
                <button type="button" onclick="document.getElementById('deptAlert').remove()" class="ac-btn-outline ac-btn-outline--ghost">Dismiss</button>
            </div>
        </div>
    </div>
<?php endif ?>

<div class="ac-bento">

    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon ac-bento-card__icon--primary"><span class="material-symbols-outlined icon-fill">hub</span></div>
            <span class="ac-pill ac-pill--success"><?= esc($stats['chaired']) ?>/<?= esc($stats['departments']) ?> chaired</span>
        </div>
        <p class="ac-bento-card__label">Total Colleges</p>
        <h3 class="ac-bento-card__value"><?= esc($stats['departments']) ?></h3>
    </div>

    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon ac-bento-card__icon--tertiary"><span class="material-symbols-outlined icon-fill">library_books</span></div>
            <span class="ac-pill ac-pill--neutral">Active</span>
        </div>
        <p class="ac-bento-card__label">Active Programs</p>
        <h3 class="ac-bento-card__value"><?= esc($stats['programs']) ?></h3>
    </div>

    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon ac-bento-card__icon--primary"><span class="material-symbols-outlined icon-fill">person_4</span></div>
            <?php if ($stats['faculty_invited'] > 0): ?>
                <span class="ac-pill ac-pill--warning"><?= esc($stats['faculty_invited']) ?> invited</span>
            <?php endif ?>
        </div>
        <p class="ac-bento-card__label">Faculty Members</p>
        <h3 class="ac-bento-card__value"><?= esc($stats['faculty']) ?></h3>
    </div>

    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon ac-bento-card__icon--warning"><span class="material-symbols-outlined icon-fill">school</span></div>
            <span class="ac-pill ac-pill--neutral">Enrolled</span>
        </div>
        <p class="ac-bento-card__label">Total Students</p>
        <h3 class="ac-bento-card__value"><?= esc($stats['students']) ?></h3>
    </div>

</div>

<div class="ac-dash-grid">

    <div class="ac-panel">
        <div class="ac-panel__head">
            <h3>Recent Institutional Changes</h3>
        </div>
        <div>
            <?php if (empty($activity)): ?>
                <div class="ac-empty-panel"><strong>No recent changes</strong><p>Updates to colleges, programs, and accounts will appear here.</p></div>
            <?php else: ?>
                <?php foreach ($activity as $item): ?>
                    <div class="ac-activity-row">
                        <div class="ac-activity-row__left">
                            <div class="ac-activity-icon">
                                <span class="material-symbols-outlined"><?= esc($item['icon']) ?></span>
                            </div>
                            <div>
                                <p class="ac-activity-title"><?= esc($item['title']) ?></p>
                                <p class="ac-activity-detail"><?= esc($item['detail']) ?></p>
                            </div>
                        </div>
                        <span class="ac-activity-time"><?= esc(time_ago($item['time'])) ?></span>
                    </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>

    <div class="ac-side-stack">

        <div class="ac-panel ac-panel--padded">
            <h3 class="ac-panel__title">System Setup</h3>
            <div class="ac-progress">
                <div class="ac-progress__fill" style="width: <?= esc($setupPercent) ?>%"></div>
            </div>
            <p class="ac-panel__note">
                <?= esc($setupPercent) ?>% complete.
                <?= $setupPercent < 100
                    ? 'Finish assigning chairpersons to unlock full reporting.'
                    : 'All colleges have a chairperson assigned.' ?>
            </p>
            <?php if ($setupPercent < 100): ?>
                <a href="<?= site_url('admin/departments') ?>" class="ac-btn-outline" style="display:block; text-align:center;">Continue Setup</a>
            <?php endif ?>
        </div>

        <div class="ac-spotlight spring-hover">
            <img src="<?= base_url('assets/images/background/auth-image.png') ?>" alt="Campus">
            <div class="ac-spotlight__overlay">
                <div>
                    <p class="ac-spotlight__label">Active Term</p>
                    <p class="ac-spotlight__title">
                        <?= $stats['active_ay_formatted'] ? esc($stats['active_ay_formatted']) : 'No active term set' ?>
                    </p>
                </div>
            </div>
        </div>

    </div>

</div>

<?= $this->endSection() ?>

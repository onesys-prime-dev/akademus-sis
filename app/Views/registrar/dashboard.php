<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<?php helper('admin'); ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Dashboard</h2>
        <p class="ac-page-header__sub">Student records and enrollment at a glance.</p>
    </div>
    <a href="<?= site_url('registrar/students') ?>" class="ac-btn ac-btn-primary">Open Student Records</a>
</div>

<?php if ($stats['no_curriculum'] > 0): ?>
    <div class="ac-alert-card">
        <div class="ac-alert-card__icon"><span class="material-symbols-outlined">assignment_late</span></div>
        <div>
            <h4 class="ac-alert-card__title">Action Required: Students Without a Curriculum</h4>
            <p class="ac-alert-card__text">
                <?= esc($stats['no_curriculum']) ?> active <?= $stats['no_curriculum'] === 1 ? 'student has' : 'students have' ?> no program or curriculum version yet,
                so prerequisites and curriculum checks cannot be applied to <?= $stats['no_curriculum'] === 1 ? 'them' : 'them' ?>.
                Open the student's record and assign a curriculum.
            </p>
        </div>
    </div>
<?php endif ?>

<div class="ac-bento">

    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon ac-bento-card__icon--warning"><span class="material-symbols-outlined icon-fill">school</span></div>
            <span class="ac-pill ac-pill--success"><?= esc($stats['by_status']['active']) ?> active</span>
        </div>
        <p class="ac-bento-card__label">Total Students</p>
        <h3 class="ac-bento-card__value"><?= esc($stats['students']) ?></h3>
    </div>

    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon ac-bento-card__icon--primary"><span class="material-symbols-outlined icon-fill">how_to_reg</span></div>
        </div>
        <p class="ac-bento-card__label">Active Enrollments</p>
        <h3 class="ac-bento-card__value"><?= esc($stats['active_enrollments']) ?></h3>
    </div>

    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon ac-bento-card__icon--tertiary"><span class="material-symbols-outlined icon-fill">pause_circle</span></div>
        </div>
        <p class="ac-bento-card__label">Inactive / Dropped</p>
        <h3 class="ac-bento-card__value"><?= esc($stats['by_status']['inactive'] + $stats['by_status']['dropped']) ?></h3>
    </div>

    <div class="ac-bento-card spring-hover">
        <div class="ac-bento-card__top">
            <div class="ac-bento-card__icon ac-bento-card__icon--primary"><span class="material-symbols-outlined icon-fill">workspace_premium</span></div>
        </div>
        <p class="ac-bento-card__label">Graduated</p>
        <h3 class="ac-bento-card__value"><?= esc($stats['by_status']['graduated']) ?></h3>
    </div>

</div>

<div class="ac-dash-grid">

    <div class="ac-panel">
        <div class="ac-panel__head"><h3>Recent Enrollment Changes</h3></div>
        <div>
            <?php if (empty($enrollmentChanges)): ?>
                <div class="ac-empty-panel"><strong>No changes yet</strong><p>Drops and re-enrollments will appear here.</p></div>
            <?php else: ?>
                <?php foreach ($enrollmentChanges as $c): ?>
                    <div class="ac-activity-row">
                        <div class="ac-activity-row__left">
                            <div class="ac-activity-icon"><span class="material-symbols-outlined">swap_horiz</span></div>
                            <div>
                                <p class="ac-activity-title">
                                    <a href="<?= site_url('registrar/students/' . (int) $c['student_id']) ?>"><?= esc(trim($c['first_name'] . ' ' . $c['last_name'])) ?></a>
                                    &middot; <?= esc($c['subject_code']) ?>
                                </p>
                                <p class="ac-activity-detail"><?= esc(($c['from_status'] ?? 'new') . ' → ' . $c['to_status']) ?><?= $c['reason'] ? ' — ' . esc($c['reason']) : '' ?></p>
                            </div>
                        </div>
                        <span class="ac-activity-time"><?= esc(time_ago($c['created_at'])) ?></span>
                    </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>

    <div class="ac-panel">
        <div class="ac-panel__head"><h3>Recent Status Changes</h3></div>
        <div>
            <?php if (empty($statusChanges)): ?>
                <div class="ac-empty-panel"><strong>No changes yet</strong><p>Changes to a student's academic status will appear here.</p></div>
            <?php else: ?>
                <?php foreach ($statusChanges as $c): ?>
                    <div class="ac-activity-row">
                        <div class="ac-activity-row__left">
                            <div class="ac-activity-icon"><span class="material-symbols-outlined">manage_accounts</span></div>
                            <div>
                                <p class="ac-activity-title">
                                    <a href="<?= site_url('registrar/students/' . (int) $c['student_id']) ?>"><?= esc(trim($c['first_name'] . ' ' . $c['last_name'])) ?></a>
                                    &middot; <?= esc($c['student_number']) ?>
                                </p>
                                <p class="ac-activity-detail"><?= esc(($c['from_status'] ?? '—') . ' → ' . $c['to_status']) ?><?= $c['reason'] ? ' — ' . esc($c['reason']) : '' ?></p>
                            </div>
                        </div>
                        <span class="ac-activity-time"><?= esc(time_ago($c['created_at'])) ?></span>
                    </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>

</div>

<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'enlistment';
$pageTitle     = 'Enlistment';
$brandSub      = 'Student Portal';
$userName      = auth()->user()->username;
$userRoleLabel = 'Student';
$navGroups     = [
    '' => [
        'dashboard' => ['label' => 'Dashboard',   'icon' => 'home',           'url' => 'student/dashboard'],
        'profile'   => ['label' => 'Profile',     'icon' => 'person',         'url' => 'student/profile'],
        'tuition'   => ['label' => 'Tuition & Fees', 'icon' => 'payments',    'url' => 'student/tuition'],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule'],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades'],
        'enlistment' => ['label' => 'Enlistment', 'icon' => 'playlist_add_check', 'url' => 'student/enlistment'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

$STEPS = ['draft' => 1, 'submitted' => 2, 'chair_recommended' => 2, 'dean_noted' => 3, 'enrolled' => 4, 'conversion_failed' => 4];
$status = $enlistment['status'] ?? null;
$itemIds = array_map(static fn ($i) => $i['section_id'] . '|' . $i['subject_code'], $items);
?>

<?= $this->section('content') ?>
<style>
    .en-pill { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); }
    .en-pill.draft { background: var(--color-warning-soft); color: #713f12; }
    .en-pill.submitted, .en-pill.chair_recommended, .en-pill.dean_noted { background: #dff8fb; color: #164e63; }
    .en-pill.enrolled { background: var(--color-success-soft); color: #14532d; }
    .en-pill.conversion_failed { background: var(--color-danger-soft); color: #7f1d1d; }
    .en-steps { display:flex; gap:8px; margin:14px 0 20px; flex-wrap:wrap; }
    .en-step { flex:1; min-width:120px; padding:10px 12px; border-radius:var(--radius-md); border:1px solid var(--color-border); font-size:12px; font-weight:600; color:var(--color-text-tertiary); }
    .en-step.is-active { border-color:#60a5fa; background:#eff6ff; color:#1d4ed8; }
    .en-step.is-done { border-color:#86efac; background:var(--color-success-soft); color:#14532d; }
</style>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Online Enlistment</h2><p class="ac-page-header__sub"><?= esc($activeTerm ?? 'No active term') ?></p></div>
    <?php if ($status !== null): ?><span class="en-pill <?= esc($status) ?>"><?= esc(str_replace('_', ' ', strtoupper($status))) ?></span><?php endif ?>
</div>

<?php if (session('errors')): ?>
    <div class="ac-alert" style="background:var(--color-danger-soft); border-color:var(--color-danger-border);">
        <div class="ac-alert__text" style="color:var(--color-danger);">
            <?php foreach (session('errors') as $error): ?><p><?= esc($error) ?></p><?php endforeach ?>
        </div>
    </div>
<?php endif ?>
<?php if (session('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;"><span class="ac-notice__text"><?= esc(session('message')) ?></span></div>
<?php endif ?>

<?php if ($activeTerm === null): ?>
    <div class="ac-empty-block">No active academic term is set. Contact the registrar.</div>
<?php else: ?>

    <div class="en-steps">
        <?php foreach (['Info' => 1, 'Course Selection' => 2, 'Chair/Dean Review' => 3, 'Enrolled' => 4] as $label => $n): ?>
            <div class="en-step <?= ($STEPS[$status] ?? 1) === $n ? 'is-active' : (($STEPS[$status] ?? 1) > $n ? 'is-done' : '') ?>"><?= esc($label) ?></div>
        <?php endforeach ?>
    </div>

    <?php if (($enlistment['return_notes'] ?? null) && $status === 'draft'): ?>
        <div class="ac-alert" style="background:var(--color-warning-soft); margin-bottom:16px;">
            <div class="ac-alert__text">Returned by <?= esc($enlistment['returned_by_role']) ?>: <?= esc($enlistment['return_notes']) ?></div>
        </div>
    <?php endif ?>

    <!-- Step 1: Info -->
    <div class="ac-card" style="margin-bottom:16px;">
        <div class="ac-dept-name">1. Confirm Your Information</div>
        <p style="font-size:12.5px; color:var(--color-text-secondary);">Make sure your contact details are up to date before enlisting. <a href="<?= site_url('student/profile') ?>">Edit profile &rarr;</a></p>
    </div>

    <?php if ($status === 'draft'): ?>
        <?php if (! $windowOpen): ?>
            <div class="ac-empty-block">Online enlistment is currently closed. Check back when the window opens.</div>
        <?php else: ?>
            <!-- Step 2: Course Selection -->
            <div class="ac-card" style="margin-bottom:16px;">
                <div class="ac-dept-name">2. Course Selection</div>
                <p style="font-size:12.5px; color:var(--color-text-secondary);">Units: <?= esc((string) $unitsUsed) ?> / <?= esc((string) $cap) ?></p>

                <?php if ($options === []): ?>
                    <div class="ac-empty-block">No course offerings found for your program yet.</div>
                <?php else: ?>
                    <table class="ac-table">
                        <thead><tr><th>Subject</th><th>Title</th><th>Year/Section</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($options as $opt): ?>
                            <?php $inCart = in_array($opt['section_id'] . '|' . $opt['subject_code'], $itemIds, true); ?>
                            <tr>
                                <td><?= esc($opt['subject_code']) ?></td>
                                <td><?= esc($opt['subject_title']) ?></td>
                                <td><?= esc($opt['year_level']) ?> &middot; <?= esc($opt['section']) ?></td>
                                <td>
                                    <?php if ($inCart): ?>
                                        <span class="ac-pill ac-pill--success">In cart</span>
                                    <?php else: ?>
                                        <form method="post" action="<?= site_url('student/enlistment/add-item') ?>" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="enlistment_id" value="<?= (int) $enlistment['id'] ?>">
                                            <input type="hidden" name="section_id" value="<?= (int) $opt['section_id'] ?>">
                                            <input type="hidden" name="subject_code" value="<?= esc($opt['subject_code'], 'attr') ?>">
                                            <button type="submit" class="ac-btn-outline">Add</button>
                                        </form>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                <?php endif ?>
            </div>

            <!-- Step 3: Review & Submit -->
            <div class="ac-card">
                <div class="ac-dept-name">3. Review &amp; Submit</div>
                <?php if ($items === []): ?>
                    <div class="ac-empty-block">Your cart is empty.</div>
                <?php else: ?>
                    <table class="ac-table">
                        <thead><tr><th>Subject</th><th>Year/Section</th><th>Units</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= esc($item['subject_code']) ?></td>
                                <td><?= esc($item['year_level']) ?> &middot; <?= esc($item['section']) ?></td>
                                <td><?= esc($item['units']) ?></td>
                                <td>
                                    <form method="post" action="<?= site_url('student/enlistment/remove-item') ?>" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="enlistment_id" value="<?= (int) $enlistment['id'] ?>">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                        <button type="submit" class="ac-btn-outline">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>

                    <form method="post" action="<?= site_url('student/enlistment/submit') ?>" style="margin-top:14px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="enlistment_id" value="<?= (int) $enlistment['id'] ?>">
                        <button type="submit" class="ac-btn ac-btn-primary">Submit for Review</button>
                    </form>
                <?php endif ?>
            </div>
        <?php endif ?>
    <?php elseif (in_array($status, ['submitted', 'chair_recommended', 'dean_noted'], true)): ?>
        <!-- Step 4: Status Tracker -->
        <div class="ac-card">
            <div class="ac-dept-name">Status Tracker</div>
            <p style="font-size:12.5px; color:var(--color-text-secondary);">Your cart is being reviewed. You cannot edit it while it's in review.</p>
            <table class="ac-table">
                <thead><tr><th>Subject</th><th>Year/Section</th><th>Units</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr><td><?= esc($item['subject_code']) ?></td><td><?= esc($item['year_level']) ?> &middot; <?= esc($item['section']) ?></td><td><?= esc($item['units']) ?></td></tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($status === 'enrolled'): ?>
        <div class="ac-card">
            <div class="ac-dept-name">Enrolled</div>
            <p style="font-size:12.5px; color:var(--color-text-secondary);">Your approved subjects are now on <a href="<?= site_url('student/schedule') ?>">My Schedule</a>, and your tuition assessment is on <a href="<?= site_url('student/tuition') ?>">Tuition &amp; Fees</a>.</p>
        </div>
    <?php elseif ($status === 'conversion_failed'): ?>
        <div class="ac-empty-block">Your enlistment was approved but could not be converted to enrollments. Check your notifications for details, or contact the registrar.</div>
    <?php endif ?>

<?php endif ?>

<?= $this->endSection() ?>

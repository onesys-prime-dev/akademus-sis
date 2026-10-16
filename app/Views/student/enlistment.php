<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'enlistment';
$pageTitle     = 'Enlistment';
$brandSub      = 'Student Portal';
$userName      = auth()->user()->username;
$userRoleLabel = 'Student';
$navGroups     = [
    '' => [
        'dashboard' => ['label' => 'Dashboard',   'icon' => 'home',           'url' => 'student/dashboard', 'help' => [
            'title' => 'Student Dashboard',
            'text'  => "Your at-a-glance view: today's schedule, upcoming classes, and recent grade/tuition activity. Use the sidebar to get to any other part of your student portal.",
        ]],
        'tuition'   => ['label' => 'Tuition & Fees', 'icon' => 'payments',    'url' => 'student/tuition', 'help' => [
            'title' => 'Tuition & Fees',
            'text'  => "Your current assessment, payment history, and balance. Upload proof of an off-site payment here for Finance to review, or see your installment schedule.",
        ]],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule', 'help' => [
            'title' => 'My Schedule',
            'text'  => "Your weekly class schedule for the current term — days, times, rooms, and instructors for everything you're enrolled in.",
        ]],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades', 'help' => [
            'title' => 'My Grades',
            'text'  => "Your official grades, term by term. A grade only appears here once it's been locked by the registrar.",
        ]],
        'enlistment' => ['label' => 'Enlistment', 'icon' => 'playlist_add_check', 'url' => 'student/enlistment', 'help' => [
            'title' => 'Enlistment',
            'text'  => "Build your subject cart for next term and submit it for chair → dean → registrar approval. Only available while your department's enlistment window is open; otherwise you'll enroll directly once sections open.",
        ]],
        'documents' => ['label' => 'Documents',   'icon' => 'description',        'url' => 'student/documents', 'help' => [
            'title' => 'Documents',
            'text'  => "Request a Certificate of Registration (needs at least a downpayment) or Certificate of Enrollment (available once you're enrolled, balance or not), and track their status.",
        ]],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

$STEPS = ['draft' => 1, 'submitted' => 2, 'chair_recommended' => 2, 'dean_noted' => 3, 'enrolled' => 4, 'conversion_failed' => 4];
$status = $enlistment['status'] ?? null;
$itemIds = array_map(static fn ($i) => $i['section_id'] . '|' . $i['subject_code'], $items);
$DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/student-enlistment.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/student-enlistment.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

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
            <div class="en-splash">
                <span class="material-symbols-outlined en-splash__icon" aria-hidden="true">event_busy</span>
                <h3>Enlistment is not yet open</h3>
                <p>Online enlistment hasn't started for this term yet. Check back once the window opens.</p>
            </div>
        <?php elseif (! $eligible): ?>
            <div class="en-splash en-splash--blocked">
                <span class="material-symbols-outlined en-splash__icon" aria-hidden="true">block</span>
                <h3>Unable to enlist online</h3>
                <p class="en-splash__warning">You have an outstanding balance of &#8369;<?= number_format($balance, 2) ?>.</p>
                <p>Please settle your balance with the Finance office, or contact the registrar, before enlisting online.</p>
            </div>
        <?php else: ?>
            <!-- Step 2: Course Selection -->
            <div class="ac-card" style="margin-bottom:16px;">
                <div class="ac-dept-name">2. Course Selection</div>
                <p class="en-units-note">Subjects your curriculum says you still need. Units: <?= esc((string) $unitsUsed) ?> / <?= esc((string) $cap) ?></p>

                <?php if ($dueSubjects === []): ?>
                    <div class="ac-empty-block">Nothing left to enlist in right now — either you're all caught up, or no curriculum is assigned to your record yet.</div>
                <?php else: ?>
                    <?php foreach ($dueSubjects as $subj): ?>
                        <?php
                            $subjectInCartIds = array_filter($itemIds, static fn (string $id): bool => str_ends_with($id, '|' . $subj['code']));
                            $subjectInCart    = $subjectInCartIds !== [];
                        ?>
                        <div class="en-subject">
                            <div class="en-subject__head">
                                <div>
                                    <span class="en-subject__code"><?= esc($subj['code']) ?></span>
                                    <span class="en-subject__title"><?= esc($subj['title']) ?></span>
                                    <span class="en-subject__units"><?= esc((string) $subj['units']) ?> units</span>
                                </div>
                                <div class="en-subject__badges">
                                    <?php if ($subj['has_lecture']): ?><span class="en-check" aria-label="Has lecture">L</span><?php endif ?>
                                    <?php if ($subj['has_lab']): ?><span class="en-check en-check--lab" aria-label="Has laboratory">Lab</span><?php endif ?>
                                    <?php if (in_array($subj['status'], ['failed', 'unresolved'], true)): ?>
                                        <span class="en-badge en-badge--retake">Retake</span>
                                    <?php endif ?>
                                    <?php if (! empty($subj['override_granted'])): ?>
                                        <span class="en-badge en-badge--waived">Requirement Waived</span>
                                    <?php endif ?>
                                </div>
                            </div>
                            <?php if (! empty($subj['corequisites'])): ?>
                                <p class="en-coreq-note">Pairs with: <?= esc(implode(', ', $subj['corequisites'])) ?> — must be added to your cart together.</p>
                            <?php endif ?>

                            <?php if ($subj['offerings'] === []): ?>
                                <div class="en-muted" style="padding:10px 0;">Not yet offered this term.</div>
                            <?php else: ?>
                                <div class="en-table-scroll">
                                <table class="ac-table en-class-table">
                                    <thead>
                                        <tr>
                                            <th>Section</th>
                                            <th>Day &amp; Time</th><th>Room</th><th>Instructor</th>
                                            <th>Slots</th><th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($subj['offerings'] as $off): ?>
                                        <?php $rowInCart = in_array($off['section_id'] . '|' . $subj['code'], $itemIds, true); ?>
                                        <tr>
                                            <td><?= esc($off['section_label']) ?></td>
                                            <td>
                                                <?php if ($off['meetings'] === []): ?><span class="en-muted">TBA</span><?php endif ?>
                                                <?php foreach ($off['meetings'] as $m): ?>
                                                    <div class="en-meeting"><?= esc($DAY_NAMES[$m['day']] ?? '?') ?> <?= esc($m['start']) ?>&ndash;<?= esc($m['end']) ?></div>
                                                <?php endforeach ?>
                                            </td>
                                            <td><?php foreach ($off['meetings'] as $m): ?><div class="en-meeting"><?= esc($m['room'] ?: 'Room to be assigned') ?></div><?php endforeach ?></td>
                                            <td><?php foreach ($off['meetings'] as $m): ?><div class="en-meeting"><?= esc($m['prof'] ?: 'Instructor to be assigned') ?></div><?php endforeach ?></td>
                                            <td>
                                                <?php if ($off['capacity'] === null): ?>
                                                    <span class="ac-pill ac-pill--neutral">Unlimited</span>
                                                <?php else: ?>
                                                    <span class="ac-pill <?= $off['available'] <= 0 ? 'en-slots--full' : '' ?>"><?= esc((string) max(0, $off['available'])) ?> / <?= esc((string) $off['capacity']) ?></span>
                                                <?php endif ?>
                                            </td>
                                            <td>
                                                <?php if ($rowInCart): ?>
                                                    <span class="ac-pill ac-pill--success">In cart</span>
                                                <?php elseif ($subjectInCart): ?>
                                                    <span class="en-muted">&mdash;</span>
                                                <?php else: ?>
                                                    <form method="post" action="<?= site_url('student/enlistment/add-item') ?>" style="display:inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="enlistment_id" value="<?= (int) $enlistment['id'] ?>">
                                                        <input type="hidden" name="section_id" value="<?= (int) $off['section_id'] ?>">
                                                        <input type="hidden" name="subject_code" value="<?= esc($subj['code'], 'attr') ?>">
                                                        <button type="submit" class="ac-btn-outline">Add</button>
                                                    </form>
                                                <?php endif ?>
                                            </td>
                                        </tr>
                                    <?php endforeach ?>
                                    </tbody>
                                </table>
                                </div>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
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
            <form method="post" action="<?= site_url('student/enlistment/send-reminder') ?>" style="margin-top:12px;">
                <?= csrf_field() ?>
                <input type="hidden" name="enlistment_id" value="<?= (int) $enlistment['id'] ?>">
                <button type="submit" class="ac-btn-outline">Send Reminder</button>
            </form>
        </div>
    <?php elseif ($status === 'enrolled'): ?>
        <div class="ac-card">
            <div class="ac-dept-name">Enrolled</div>
            <p style="font-size:12.5px; color:var(--color-text-secondary);">Your approved subjects are now on <a href="<?= site_url('student/schedule') ?>">My Schedule</a>, and your tuition assessment is on <a href="<?= site_url('student/tuition') ?>">Tuition &amp; Fees</a>.</p>
            <a href="<?= site_url('student/enlistment/print-form') ?>" target="_blank" class="ac-btn ac-btn-primary" style="margin-top:10px;">Print Student Form</a>
        </div>
    <?php elseif ($status === 'conversion_failed'): ?>
        <div class="ac-empty-block">Your enlistment was approved but could not be converted to enrollments. Check your notifications for details, or contact the registrar.</div>
    <?php endif ?>

<?php endif ?>

<?= $this->endSection() ?>

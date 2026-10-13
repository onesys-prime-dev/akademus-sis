<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'dashboard';
$pageTitle     = 'Dashboard';
$brandSub      = 'Student Portal';
$userName      = $account->username;
$userRoleLabel = $section !== null ? "{$section['program']} · {$section['year_level']}" : 'Student';
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
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/student-dashboard.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/student-dashboard.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$timeLabel = static function (string $time): string { [$h, $m] = array_map('intval', explode(':', $time)); return ($h % 12 ?: 12) . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . ($h < 12 ? ' AM' : ' PM'); };
$next = $week['next'];
$tomorrow = $now->modify('+1 day');
$tomorrowMeetings = array_values(array_filter($week['meetings'], static fn ($m) => (int) $m['day'] === (int) $tomorrow->format('N') - 1));
$hasAssessment = $assessment !== null && $assessment['status'] !== 'unassessed';
?>
<div class="sd-page">
    <header class="sd-heading"><div><span class="sd-eyebrow">STUDENT HOME</span><h1>Welcome back, <?= esc($account->username) ?>.</h1><p>Your classes, account, and academic progress for today.</p></div><div class="sd-date"><strong><?= esc($now->format('l, F j')) ?></strong><a href="<?= site_url('student/dashboard') ?>" aria-label="Refresh dashboard">Updated <?= esc($now->format('g:i A')) ?> PHT</a></div></header>
    <div class="sd-term"><div><span>Current term</span><strong><?= esc($activeTerm ?? 'No active academic term') ?></strong></div><div><span>Program and section</span><strong><?= $section !== null ? esc($section['program'] . ' · ' . $section['year_level'] . ' · Section ' . $section['section']) : 'No section assigned' ?></strong></div></div>
    <?php if ($section === null): ?><div class="sd-card sd-empty" style="margin-bottom:24px;"><span class="material-symbols-outlined" aria-hidden="true">domain_disabled</span><div><h3>Your section is not assigned yet</h3><p>Contact an administrator to complete your section assignment.</p></div></div><?php endif ?>
    <div class="sd-main">
        <div class="sd-stack">
            <section class="sd-card sd-next" aria-labelledby="sdNextTitle">
                <?php $isOngoing = $next !== null && $next['ongoing']; ?>
                <div class="sd-next-top"><span id="sdNextTitle"><?php if ($isOngoing): ?><span class="sd-live-dot" aria-hidden="true"></span><?php endif ?><?= $isOngoing ? 'In progress now' : 'Next class' ?></span><a href="<?= site_url('student/schedule') ?>">View schedule</a></div>
                <?php if ($next !== null): ?>
                    <h2><?= esc($next['subject_code']) ?></h2><p class="sd-next-title"><?= esc($next['subject_title']) ?></p>
                    <div class="sd-next-meta"><span><span class="material-symbols-outlined" aria-hidden="true">schedule</span><?= $next['starts_at']->format('Y-m-d') === $now->format('Y-m-d') ? 'Today' : esc($next['starts_at']->format('D, M j')) ?> &middot; <?= esc($timeLabel($next['start_time']) . ' – ' . $timeLabel($next['end_time'])) ?></span><span><span class="material-symbols-outlined" aria-hidden="true">location_on</span><?= esc($next['room'] ?: 'Room to be assigned') ?></span><span><span class="material-symbols-outlined" aria-hidden="true">person</span><?= esc($next['prof'] ?: 'Instructor to be assigned') ?></span></div>
                <?php else: ?><h2>A new week starts here.</h2><p class="sd-next-title"><?= $activeTerm === null ? 'Your timetable will appear when an academic term is active.' : 'Your classes will appear once your enrollments have scheduled meetings.' ?></p><?php endif ?>
                <a class="sd-next-link" href="<?= site_url('student/schedule') ?>">Open class schedule<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
            </section>
            <section class="sd-card" aria-labelledby="sdAgendaTitle">
                <header class="sd-section-header sd-agenda-header"><div><h2 id="sdAgendaTitle">Class schedule</h2><p>Today and tomorrow at a glance.</p></div><div class="sd-days" id="sdDayToggle" role="group" aria-label="Choose schedule day" hidden><button type="button" data-day-panel="sdToday" aria-controls="sdToday" aria-pressed="true">Today</button><button type="button" data-day-panel="sdTomorrow" aria-controls="sdTomorrow" aria-pressed="false">Tomorrow</button></div></header>
                <?php foreach ([['sdToday', $now, $week['today']], ['sdTomorrow', $tomorrow, $tomorrowMeetings]] as [$panelId, $date, $dayMeetings]): ?>
                <div id="<?= $panelId ?>" aria-label="<?= esc($date->format('l, F j'), 'attr') ?>"><p class="sd-agenda-date"><?= esc($date->format('l, F j')) ?> &middot; <?= count($dayMeetings) ?> <?= count($dayMeetings) === 1 ? 'class' : 'classes' ?></p>
                    <?php if ($dayMeetings === []): ?><div class="sd-empty"><span class="material-symbols-outlined" aria-hidden="true">event_available</span><div><h3><?= $week['meetings'] !== [] ? 'No classes scheduled' : 'No meetings to show yet' ?></h3><p><?= $week['meetings'] !== [] ? 'You have a clear timetable for this day. Check your full schedule to plan ahead.' : 'Scheduled meetings for your enrolled classes will appear here.' ?></p></div></div>
                    <?php else: ?>
                        <?php foreach ($dayMeetings as $meeting): $state = $meeting['state'] ?? 'Upcoming'; ?>
                        <a class="sd-meeting" href="<?= site_url('student/schedule') ?>"><span class="sd-meeting-time"><?= esc($timeLabel($meeting['start_time'])) ?><small><?= esc($timeLabel($meeting['end_time'])) ?></small></span><span class="sd-meeting-subject"><strong><?= esc($meeting['subject_code']) ?></strong><span><?= esc($meeting['subject_title']) ?></span><small><?= esc($meeting['room'] ?: 'Room to be assigned') ?> &middot; <?= esc($meeting['prof'] ?: 'Instructor to be assigned') ?></small></span><span class="sd-state <?= $state === 'In progress' ? 'sd-state--live' : ($state === 'Finished' ? 'sd-state--finished' : '') ?>"><?= esc($state) ?></span></a>
                        <?php endforeach ?>
                    <?php endif ?>
                </div>
                <?php endforeach ?>
                <footer class="sd-card-footer"><a class="sd-link" href="<?= site_url('student/schedule') ?>">View full week<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a></footer>
            </section>
        </div>
        <div class="sd-stack">
            <section class="sd-card" aria-label="Active term overview">
                <div class="sd-stats">
                    <div class="sd-stat"><div><span>Enrolled subjects</span><strong><?= (int) $stats['enrolled_subjects'] ?></strong></div></div>
                    <div class="sd-stat"><div><span>Weekly hours</span><strong><?= esc($week['hours']) ?></strong></div></div>
                    <div class="sd-stat"><div><span>Class days</span><strong><?= (int) $week['days'] ?></strong></div></div>
                </div>
                <p class="sd-stats-caption">Your enrollments in the active term</p>
            </section>
            <section class="sd-card" aria-labelledby="sdTuitionTitle"><header class="sd-section-header"><div><h2 id="sdTuitionTitle">Tuition &amp; fees</h2><p>Current term assessment</p></div></header>
                <?php if ($hasAssessment): ?>
                    <div class="sd-balance"><span class="sd-balance-value"><span>₱</span><?= number_format((float) $assessment['balance'], 2) ?></span><span class="sd-balance-status <?= $assessment['status'] === 'paid' ? 'is-paid' : '' ?>"><?= $assessment['status'] === 'paid' ? 'Paid in full' : 'Outstanding balance' ?></span>
                        <?php if ($nextPayment !== null): $dueDate = $nextPayment['due_date'] ? new DateTimeImmutable($nextPayment['due_date'], new DateTimeZone('Asia/Manila')) : null; $overdue = $dueDate !== null && $dueDate->format('Y-m-d') < $now->format('Y-m-d'); ?>
                        <div class="sd-payment <?= $overdue ? 'is-overdue' : '' ?>"><span><?= $overdue ? 'Past-due installment' : 'Next unpaid installment' ?></span><strong><?= esc($nextPayment['label']) ?> &middot; ₱<?= number_format((float) $nextPayment['balance'], 2) ?></strong><p><?= $dueDate ? 'Due ' . esc($dueDate->format('M j, Y')) : 'Due date not set' ?></p></div>
                        <?php endif ?>
                    </div>
                <?php else: ?><div class="sd-empty"><span class="material-symbols-outlined" aria-hidden="true">receipt_long</span><div><h3>Assessment not available</h3><p>Your balance will appear once tuition is assessed for the active term.</p></div></div><?php endif ?>
                <footer class="sd-card-footer"><a class="sd-link" href="<?= site_url('student/tuition') ?>">View assessment &amp; payments<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a></footer>
            </section>
            <section class="sd-card" aria-labelledby="sdGradesTitle"><header class="sd-section-header"><div><h2 id="sdGradesTitle">Published grades</h2><p><?= count($publishedGrades) ?> official <?= count($publishedGrades) === 1 ? 'grade' : 'grades' ?> in this term</p></div></header>
                <?php if ($publishedGrades === []): ?><div class="sd-empty"><span class="material-symbols-outlined" aria-hidden="true">fact_check</span><div><h3>No published grades yet</h3><p>Your grades will appear after they are reviewed and made official.</p></div></div><?php else: ?>
                    <?php foreach (array_slice($publishedGrades, 0, 3) as $grade): $passState = $grade['is_passing'] === null ? '' : ($grade['is_passing'] ? 'is-pass' : 'is-fail'); ?><div class="sd-grade"><div><strong><?= esc($grade['subject_code']) ?></strong><small><?= esc($grade['remarks'] ?: 'Official grade') ?></small></div><span class="sd-grade-value <?= $passState ?>"><?= esc($grade['grade_value']) ?></span></div><?php endforeach ?>
                <?php endif ?>
                <footer class="sd-card-footer"><a class="sd-link" href="<?= site_url('student/grades') ?>">View all grades<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a></footer>
            </section>
        </div>
    </div>
    <nav class="sd-shortcuts" aria-label="Student shortcuts">
        <a class="sd-shortcut" href="<?= site_url('student/profile') ?>"><span class="sd-shortcut-icon sd-shortcut-icon--blue material-symbols-outlined" aria-hidden="true">person_outline</span><span><strong>My profile</strong><small>Personal and account details</small></span><span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
        <a class="sd-shortcut" href="<?= site_url('student/schedule') ?>"><span class="sd-shortcut-icon sd-shortcut-icon--violet material-symbols-outlined" aria-hidden="true">calendar_month</span><span><strong>My schedule</strong><small>Class details and printable timetable</small></span><span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
        <a class="sd-shortcut" href="<?= site_url('student/tuition') ?>#payment-proof"><span class="sd-shortcut-icon sd-shortcut-icon--amber material-symbols-outlined" aria-hidden="true">upload_file</span><span><strong>Payment proof</strong><small>Submit your receipt for review</small></span><span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
    </nav>
    <p class="sd-note">Class times follow your recurring academic timetable. All times are in Philippine Standard Time.</p>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(() => {
    const toggle = document.getElementById('sdDayToggle');
    if (!toggle) return;
    toggle.hidden = false;
    document.getElementById('sdTomorrow').hidden = true;
    toggle.addEventListener('click', event => {
        const button = event.target.closest('[data-day-panel]');
        if (!button) return;
        toggle.querySelectorAll('button').forEach(item => {
            const selected = item === button;
            item.setAttribute('aria-pressed', String(selected));
            document.getElementById(item.dataset.dayPanel).hidden = !selected;
        });
    });
})();
</script>
<?= $this->endSection() ?>

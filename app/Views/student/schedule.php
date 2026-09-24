<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'schedule';
$pageTitle     = 'My Schedule';
$brandSub      = 'Student Portal';
$userName      = auth()->user()->username;
$userRoleLabel = 'Student';
$navGroups     = [
    '' => [
        'dashboard' => ['label' => 'Dashboard',   'icon' => 'home',           'url' => 'student/dashboard'],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule'],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades'],
        'profile'   => ['label' => 'Profile',     'icon' => 'person',         'url' => 'student/profile'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$days  = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$byDay = array_fill(0, 6, []);

foreach ($meetings as $meeting) {
    $byDay[(int) $meeting['day']][] = $meeting;
}

foreach ($byDay as &$dayMeetings) {
    usort($dayMeetings, static fn (array $a, array $b) => strcmp($a['start_time'], $b['start_time']));
}
unset($dayMeetings);

$colors = [
    'blue'   => ['bg' => '#e0edff', 'border' => '#0040A6', 'text' => '#00296b'],
    'purple' => ['bg' => '#f1e6ff', 'border' => '#7c3aed', 'text' => '#4c1d95'],
    'cyan'   => ['bg' => '#dff8fb', 'border' => '#0891b2', 'text' => '#164e63'],
    'red'    => ['bg' => '#fee2e2', 'border' => '#dc2626', 'text' => '#7f1d1d'],
    'green'  => ['bg' => '#dcfce7', 'border' => '#16a34a', 'text' => '#14532d'],
    'yellow' => ['bg' => '#fef9c3', 'border' => '#ca8a04', 'text' => '#713f12'],
];

$gridStartMin = 7 * 60;
$gridEndMin   = 19 * 60;
$rowMinutes   = 30;
$totalRows    = (int) (($gridEndMin - $gridStartMin) / $rowMinutes);

$toMinutes = static function (string $hhmm) {
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    return $h * 60 + $m;
};
?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">My Schedule</h2></div>
</div>

<div class="panel">
    <?php if ($meetings === []): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">event_busy</div>
            <p>You're not enrolled in any classes yet.</p>
        </div>
    <?php else: ?>

        <div class="view-toggle">
            <button type="button" class="active" data-view="list">
                <span class="material-symbols-outlined">view_list</span> List
            </button>
            <button type="button" data-view="calendar">
                <span class="material-symbols-outlined">calendar_month</span> Calendar
            </button>
        </div>

        <div class="schedule-view active" data-view="list">
            <?php foreach ($byDay as $dayIndex => $dayMeetings): ?>
                <?php if ($dayMeetings === []) continue; ?>
                <div class="day-heading"><?= esc($days[$dayIndex]) ?></div>
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Subject</th>
                            <th>Room</th>
                            <th>Faculty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dayMeetings as $m): ?>
                            <tr>
                                <td><?= esc($m['time_label']) ?></td>
                                <td><?= esc($m['subject_code']) ?> &mdash; <?= esc($m['subject_title']) ?></td>
                                <td><?= esc($m['room']) ?></td>
                                <td><?= esc($m['prof'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            <?php endforeach ?>
        </div>

        <div class="schedule-view" data-view="calendar">
            <div class="grid-wrap">
                <div class="grid">
                    <div class="grid-head">
                        <div class="grid-corner"></div>
                        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $label): ?>
                            <div><?= esc($label) ?></div>
                        <?php endforeach ?>
                    </div>

                    <div class="time-col">
                        <?php for ($m = $gridStartMin; $m < $gridEndMin; $m += 60): ?>
                            <div class="time-slot"><?php
                                $h12 = $m % 720 === 0 ? 12 : intdiv($m, 60) % 12;
                                echo $h12 . ($m < 720 ? ' AM' : ' PM');
                            ?></div>
                        <?php endfor ?>
                    </div>

                    <?php for ($day = 0; $day < 6; $day++): ?>
                        <div class="day-col" style="height:<?= $totalRows * 32 ?>px;">
                            <?php for ($r = 0; $r < $totalRows / 2; $r++): ?>
                                <div class="hour-line"></div>
                            <?php endfor ?>

                            <?php foreach ($blocks as $b): ?>
                                <?php if ($b['day'] !== $day) continue; ?>
                                <?php
                                    $startMin = $toMinutes($b['start']);
                                    $endMin   = $toMinutes($b['end']);
                                    $top      = ($startMin - $gridStartMin) / $rowMinutes * 32;
                                    $height   = max(32, ($endMin - $startMin) / $rowMinutes * 32);
                                    $c        = $colors[$b['color']] ?? $colors['blue'];
                                ?>
                                <div class="block" style="top:<?= $top ?>px; height:<?= $height ?>px; background:<?= $c['bg'] ?>; border-color:<?= $c['border'] ?>; color:<?= $c['text'] ?>;">
                                    <div class="b-code"><?= esc($b['code']) ?></div>
                                    <?php if ($height > 46): ?>
                                        <div class="b-title"><?= esc($b['title']) ?></div>
                                    <?php endif ?>
                                    <div class="b-meta"><?= esc($b['time_label']) ?> &middot; <?= esc($b['room']) ?></div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endfor ?>
                </div>
            </div>
        </div>

    <?php endif ?>
</div>

<?= $this->endSection() ?>

<?php if (! empty($enrolled)): ?>
<div class="panel" style="margin-top:16px;">
    <h3 style="margin:0 0 4px; font-size:15px;">My Enrolled Subjects</h3>
    <p style="font-size:12px; color:var(--color-text-secondary); margin:0 0 12px;">You can drop a subject in the active term until a grade is recorded for it.</p>
    <table class="schedule-table">
        <thead><tr><th>Subject</th><th>Section</th><th>Term</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($enrolled as $e): ?>
            <tr>
                <td><b><?= esc($e['subject_code']) ?></b></td>
                <td><?= esc($e['program']) ?> &middot; <?= esc($e['year_level']) ?> &middot; <?= esc($e['section']) ?></td>
                <td><?= esc($e['academic_year']) ?></td>
                <td style="text-align:right;">
                    <?php if ($e['can_drop']): ?>
                        <form method="post" action="<?= site_url('student/enrollments/drop') ?>" onsubmit="return confirm('Drop <?= esc($e['subject_code'], 'js') ?>? You can re-enroll later if a seat is available.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="enrollment_id" value="<?= (int) $e['enrollment_id'] ?>">
                            <button type="submit" class="ac-btn ac-btn-secondary">Drop</button>
                        </form>
                    <?php elseif ((int) $e['grade_count'] > 0): ?>
                        <span style="font-size:12px; color:var(--color-text-secondary);">Graded</span>
                    <?php else: ?>
                        <span style="font-size:12px; color:var(--color-text-secondary);">Past term</span>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        const toggle = document.querySelector('.view-toggle');
        if (!toggle) return;
        if (window.acInitSegmented) window.acInitSegmented(toggle);
        toggle.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-view]');
            if (!btn) return;
            const view = btn.dataset.view;

            toggle.querySelectorAll('button').forEach((b) => b.classList.toggle('active', b === btn));
            document.querySelectorAll('.schedule-view').forEach((el) => {
                el.classList.toggle('active', el.dataset.view === view);
            });
        });
    })();
</script>
<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?php
/**
 * Schedule — Faculty view (Akademus SIS)
 * Read-only weekly grid of this faculty member's own plotted classes for
 * the active term — visually modeled on the chairperson's plotting grid,
 * minus any editing controls.
 */
$activeAdmin   = 'schedule';
$pageTitle     = 'My Schedule';
$brandSub      = 'Faculty Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = [
    '' => [
        'dashboard'    => ['label' => 'Dashboard',    'icon' => 'dashboard',        'url' => 'faculty/dashboard'],
        'profile'      => ['label' => 'Profile',      'icon' => 'person',           'url' => 'faculty/profile'],
        'schedule'     => ['label' => 'Schedule',     'icon' => 'calendar_month',   'url' => 'faculty/schedule'],
        'availability' => ['label' => 'Availability', 'icon' => 'event_available',  'url' => 'faculty/availability'],
        'grading'      => ['label' => 'Grading',      'icon' => 'grade',            'url' => 'faculty/grading'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

$gridStartMin = 7 * 60;
$gridEndMin   = 19 * 60;
$rowMinutes   = 30;
$totalRows    = (int) (($gridEndMin - $gridStartMin) / $rowMinutes);

$toMinutes = static function (string $hhmm) {
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    return $h * 60 + $m;
};

$colors = [
    'blue'   => ['bg' => '#e0edff', 'border' => '#0040A6', 'text' => '#00296b'],
    'purple' => ['bg' => '#f1e6ff', 'border' => '#7c3aed', 'text' => '#4c1d95'],
    'cyan'   => ['bg' => '#dff8fb', 'border' => '#0891b2', 'text' => '#164e63'],
    'red'    => ['bg' => '#fee2e2', 'border' => '#dc2626', 'text' => '#7f1d1d'],
    'green'  => ['bg' => '#dcfce7', 'border' => '#16a34a', 'text' => '#14532d'],
    'yellow' => ['bg' => '#fef9c3', 'border' => '#ca8a04', 'text' => '#713f12'],
];
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    .stats-strip { display: flex; gap: 24px; padding: 16px 4px; border-bottom: 1px solid var(--color-border); margin-bottom: 16px; }
    .stat { display: flex; flex-direction: column; }
    .stat .num { font-size: 22px; font-weight: 700; color: var(--color-text-primary); }
    .stat .lbl { font-size: 12px; color: var(--color-text-secondary); }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">My Schedule</h2></div>
</div>

<div class="panel">
    <?php if (empty($blocks)): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">event_busy</div>
            <h3 style="font-size:15px; font-weight:700; color:var(--color-text-primary); margin-bottom:4px;">No classes plotted yet</h3>
            <p><?= $activeYear ? 'Your chairperson hasn\'t assigned you any classes for the active term yet.' : 'Ask an admin to set an active academic term.' ?></p>
        </div>
    <?php else: ?>
        <p class="panel-sub" style="margin-bottom:16px;"><?= $activeYear ? esc($activeYear) . ' · read-only — your chairperson plots this' : 'No active term configured yet' ?></p>

        <div class="stats-strip">
            <div class="stat"><span class="num"><?= count($blocks) ?></span><span class="lbl">Weekly meetings</span></div>
            <div class="stat"><span class="num"><?= (int) $unitsTaught ?></span><span class="lbl">Subjects</span></div>
        </div>

        <div class="grid-wrap">
            <div class="grid">
                <div class="grid-head">
                    <div class="grid-corner"></div>
                    <?php foreach ($dayLabels as $label): ?>
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
                                <?php if ($height > 62): ?>
                                    <div class="b-meta"><?= esc($b['section']) ?> &middot; <?= esc(str_replace(' Year', ' Yr', $b['year_level'])) ?></div>
                                <?php endif ?>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endfor ?>
            </div>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>

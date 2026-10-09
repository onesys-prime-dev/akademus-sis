<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin = 'schedule'; $pageTitle = 'My Schedule'; $brandSub = 'Faculty Portal';
$userName = $user['name']; $userRoleLabel = $user['role'];
$navGroups = ['' => [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'dashboard', 'url' => 'faculty/dashboard'],
    'profile' => ['label' => 'Profile', 'icon' => 'person', 'url' => 'faculty/profile'],
    'schedule' => ['label' => 'Schedule', 'icon' => 'calendar_month', 'url' => 'faculty/schedule'],
    'availability' => ['label' => 'Availability', 'icon' => 'event_available', 'url' => 'faculty/availability'],
    'grading' => ['label' => 'Grading', 'icon' => 'grade', 'url' => 'faculty/grading'],
]];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

$gridStartMin = 7 * 60; $gridEndMin = 19 * 60; $rowMinutes = 30;
$totalRows = (int) (($gridEndMin - $gridStartMin) / $rowMinutes);
$toMinutes = static function (string $hhmm): int {
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    return $h * 60 + $m;
};
$blockAttrs = static function (array $b, string $dayName, array $c): string {
    $d = [
        'code' => $b['code'], 'title' => $b['title'], 'time' => $b['time_label'], 'room' => $b['room'],
        'section' => $b['section'], 'year' => $b['year_level'], 'day' => $dayName, 'accent' => $c['border'],
    ];
    $out = '';
    foreach ($d as $k => $v) { $out .= ' data-' . $k . '="' . esc((string) $v, 'attr') . '"'; }
    return $out;
};
$colors = [
    'blue' => ['bg' => '#edf4ff', 'border' => '#3973c6', 'text' => '#183f77'],
    'purple' => ['bg' => '#f4f0fb', 'border' => '#7658a8', 'text' => '#4d3873'],
    'cyan' => ['bg' => '#eaf7f8', 'border' => '#318795', 'text' => '#245c66'],
    'red' => ['bg' => '#fceff0', 'border' => '#c65760', 'text' => '#7a3037'],
    'green' => ['bg' => '#edf7f0', 'border' => '#3f8a58', 'text' => '#285c39'],
    'yellow' => ['bg' => '#fbf4e8', 'border' => '#b47a2f', 'text' => '#714b1d'],
];
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    .schedule-page { display: grid; gap: 24px; }
    .schedule-page__head { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; }
    .schedule-page__title { margin: 0 0 5px; color: var(--color-text-primary); font-size: 28px; font-weight: 650; letter-spacing: -.025em; line-height: 1.2; }
    .schedule-page__sub { margin: 0; color: var(--color-text-secondary); font-size: 13.5px; }
    .schedule-readonly { display: inline-flex; align-items: center; gap: 7px; color: var(--color-text-secondary); font-size: 12px; font-weight: 600; white-space: nowrap; }
    .schedule-readonly .material-symbols-outlined { font-size: 16px; }
    .schedule-metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 180px)); gap: 12px; }
    .schedule-metric { padding: 16px 18px; border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface); }
    .schedule-metric__value { display: block; color: var(--color-text-primary); font-size: 24px; font-weight: 650; letter-spacing: -.025em; line-height: 1; font-variant-numeric: tabular-nums; }
    .schedule-metric__label { display: block; margin-top: 7px; color: var(--color-text-secondary); font-size: 12px; font-weight: 500; }
    .schedule-calendar { overflow: hidden; border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface); }
    .schedule-calendar__bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 20px; border-bottom: 1px solid var(--color-border); }
    .schedule-calendar__title { margin: 0; color: var(--color-text-primary); font-size: 14px; font-weight: 650; }
    .schedule-calendar__hint { margin: 3px 0 0; color: var(--color-text-tertiary); font-size: 12px; }
    .schedule-key { display: flex; align-items: center; gap: 7px; color: var(--color-text-tertiary); font-size: 11.5px; white-space: nowrap; }
    .schedule-key::before { content: ''; width: 8px; height: 8px; border-radius: 2px; background: var(--color-primary); }
    .schedule-calendar .grid-wrap { padding: 0; }
    .schedule-calendar .grid { min-width: 900px; border: 0; border-radius: 0; }
    .schedule-calendar .grid-head > div { padding: 11px 8px; border-color: var(--color-border); background: var(--color-surface-secondary); color: var(--color-text-secondary); font-size: 10.5px; font-weight: 650; letter-spacing: .045em; }
    .schedule-calendar .time-col { border-color: var(--color-border); background: var(--color-surface-secondary); }
    .schedule-calendar .time-slot { padding: 2px 10px 0 4px; border-color: var(--color-border); color: var(--color-text-tertiary); font-size: 10px; font-weight: 500; }
    .schedule-calendar .day-col { border-color: var(--color-border); background: var(--color-surface); }
    .schedule-calendar .day-col .hour-line { border-color: var(--color-border); }
    .schedule-calendar .day-col .hour-line:nth-child(odd) { background: rgba(29, 36, 51, .014); }
    .schedule-calendar .block { left: 4px; right: 4px; padding: 7px 8px; border: 1px solid rgba(29, 36, 51, .06); border-left: 3px solid; border-radius: var(--radius-sm); box-shadow: none; transition: filter var(--transition-fast), transform var(--transition-fast); }
    .schedule-calendar .block:hover { filter: saturate(1.08); transform: translateY(-1px); z-index: 3; }
    .schedule-calendar .block .b-code { font-size: 10.5px; font-weight: 700; }
    .schedule-calendar .block .b-title { margin-top: 2px; font-size: 10px; font-weight: 500; line-height: 1.25; }
    .schedule-calendar .block .b-meta { margin-top: 3px; font-size: 9.5px; line-height: 1.25; opacity: .78; }
    .schedule-agenda { display: none; overflow: hidden; border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface); }
    .schedule-agenda__day { padding: 10px 16px; border-top: 1px solid var(--color-border); border-bottom: 1px solid var(--color-border); background: var(--color-surface-secondary); color: var(--color-text-secondary); font-size: 11px; font-weight: 650; text-transform: uppercase; letter-spacing: .045em; }
    .schedule-agenda__day:first-child { border-top: 0; }
    .schedule-agenda__item { display: grid; grid-template-columns: 88px 1fr; gap: 14px; padding: 15px 16px; border-bottom: 1px solid var(--color-border); }
    .schedule-agenda__item:last-child { border-bottom: 0; }
    .schedule-agenda__time { color: var(--color-text-secondary); font-size: 11.5px; font-weight: 600; line-height: 1.45; }
    .schedule-agenda__class { min-width: 0; padding-left: 11px; border-left: 3px solid var(--class-color); }
    .schedule-agenda__code { color: var(--color-text-primary); font-size: 13px; font-weight: 650; }
    .schedule-agenda__name { margin-top: 2px; color: var(--color-text-secondary); font-size: 12px; }
    .schedule-agenda__meta { margin-top: 5px; color: var(--color-text-tertiary); font-size: 11.5px; }
    .schedule-empty { border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface); }
    .schedule-empty h3 { margin: 0 0 5px; color: var(--color-text-primary); font-size: 15px; font-weight: 650; }
    .schedule-empty p { margin: 0 auto; max-width: 48ch; }
.schedule-calendar .block, .schedule-agenda__item { cursor: pointer; }
    .schedule-calendar .block:focus-visible, .schedule-agenda__item:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; }
    .schedule-agenda__item:active { background: var(--color-surface-secondary); }
    .cls-modal { position: fixed; inset: 0; z-index: 1000; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 20, 32, .5); }
    .cls-modal.is-open { display: flex; animation: clsFade .16s ease-out; }
    .cls-modal__card { width: 100%; max-width: 420px; overflow: hidden; border-radius: var(--radius-lg); background: var(--color-surface); box-shadow: 0 20px 50px rgba(0, 0, 0, .28); animation: clsPop .22s cubic-bezier(.2, 1.3, .4, 1); }
    .cls-modal__head { position: relative; padding: 20px 52px 16px 22px; border-top: 5px solid var(--accent); }
    .cls-modal__code { color: var(--accent); font-size: 12px; font-weight: 700; letter-spacing: .05em; }
    .cls-modal__title { margin: 4px 0 0; color: var(--color-text-primary); font-size: 19px; font-weight: 650; line-height: 1.3; }
    .cls-modal__close { position: absolute; top: 14px; right: 14px; display: grid; place-items: center; width: 32px; height: 32px; border: 0; border-radius: 50%; background: transparent; color: var(--color-text-secondary); cursor: pointer; }
    .cls-modal__close:hover { background: var(--color-surface-secondary); }
    .cls-modal__body { display: grid; gap: 14px; padding: 4px 22px 22px; }
    .cls-modal__row { display: flex; align-items: center; gap: 13px; }
    .cls-modal__ico { display: grid; place-items: center; flex: none; width: 36px; height: 36px; border-radius: 10px; background: color-mix(in srgb, var(--accent) 14%, transparent); color: var(--accent); }
    .cls-modal__ico .material-symbols-outlined { font-size: 20px; }
    .cls-modal__lbl { display: block; color: var(--color-text-tertiary); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
    .cls-modal__val { display: block; color: var(--color-text-primary); font-size: 14px; font-weight: 550; }
    @keyframes clsFade { from { opacity: 0; } }
    @keyframes clsPop { from { opacity: 0; transform: translateY(12px) scale(.94); } }
    @media (prefers-reduced-motion: reduce) { .cls-modal.is-open, .cls-modal__card { animation: none; } }
    @media (max-width: 760px) {
        .schedule-page { gap: 18px; }
        .schedule-page__head { align-items: flex-start; flex-direction: column; gap: 10px; }
        .schedule-page__title { font-size: 24px; }
        .schedule-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .schedule-calendar { display: none; }
        .schedule-agenda { display: block; }
    }
    @media (max-width: 420px) { .schedule-agenda__item { grid-template-columns: 74px 1fr; gap: 10px; } }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="schedule-page">
    <header class="schedule-page__head">
        <div>
            <h2 class="schedule-page__title">My Schedule</h2>
            <p class="schedule-page__sub"><?= $activeYear ? esc($activeYear) : 'No active academic term' ?></p>
        </div>
        <?php if (! empty($blocks)): ?>
            <span class="schedule-readonly"><span class="material-symbols-outlined" aria-hidden="true">lock</span>Managed by your chairperson</span>
        <?php endif ?>
    </header>

    <?php if (empty($blocks)): ?>
        <div class="empty-state schedule-empty">
            <span class="material-symbols-outlined" aria-hidden="true">event_busy</span>
            <h3>No classes scheduled</h3>
            <p><?= $activeYear ? 'Your teaching schedule will appear here after your chairperson assigns classes.' : 'An active academic term needs to be configured before classes can be scheduled.' ?></p>
        </div>
    <?php else: ?>
        <div class="schedule-metrics" aria-label="Schedule summary">
            <div class="schedule-metric"><span class="schedule-metric__value"><?= count($blocks) ?></span><span class="schedule-metric__label">Weekly meetings</span></div>
            <div class="schedule-metric"><span class="schedule-metric__value"><?= (int) $unitsTaught ?></span><span class="schedule-metric__label">Subjects assigned</span></div>
        </div>

        <section class="schedule-calendar" aria-labelledby="weeklyCalendarTitle">
            <div class="schedule-calendar__bar">
                <div><h3 class="schedule-calendar__title" id="weeklyCalendarTitle">Weekly calendar</h3><p class="schedule-calendar__hint">7:00 AM to 7:00 PM</p></div>
                <span class="schedule-key">Scheduled class</span>
            </div>
            <div class="grid-wrap" tabindex="0" aria-label="Weekly schedule. Scroll horizontally to see all days.">
                <div class="grid">
                    <div class="grid-head"><div class="grid-corner"></div><?php foreach ($dayLabels as $label): ?><div><?= esc($label) ?></div><?php endforeach ?></div>
                    <div class="time-col">
                        <?php for ($m = $gridStartMin; $m < $gridEndMin; $m += 60): ?>
                            <div class="time-slot"><?php $h12 = $m % 720 === 0 ? 12 : intdiv($m, 60) % 12; echo $h12 . ($m < 720 ? ' AM' : ' PM'); ?></div>
                        <?php endfor ?>
                    </div>
                    <?php for ($day = 0; $day < count($dayLabels); $day++): ?>
                        <div class="day-col" style="height:<?= $totalRows * 32 ?>px;">
                            <?php for ($r = 0; $r < $totalRows / 2; $r++): ?><div class="hour-line"></div><?php endfor ?>
                            <?php foreach ($blocks as $b): ?>
                                <?php if ($b['day'] !== $day) continue; ?>
                                <?php
                                    $startMin = $toMinutes($b['start']); $endMin = $toMinutes($b['end']);
                                    $top = ($startMin - $gridStartMin) / $rowMinutes * 32;
                                    $height = max(32, ($endMin - $startMin) / $rowMinutes * 32);
                                    $c = $colors[$b['color']] ?? $colors['blue'];
                                ?>
                                <div class="block" role="button" tabindex="0"<?= $blockAttrs($b, $dayLabels[$day], $c) ?> style="top:<?= $top ?>px; height:<?= $height ?>px; background:<?= $c['bg'] ?>; border-left-color:<?= $c['border'] ?>; color:<?= $c['text'] ?>;" title="<?= esc($b['code'] . ' - ' . $b['title'] . ', ' . $b['time_label'] . ', ' . $b['room']) ?>">
                                    <div class="b-code"><?= esc($b['code']) ?></div>
                                    <?php if ($height > 46): ?><div class="b-title"><?= esc($b['title']) ?></div><?php endif ?>
                                    <div class="b-meta"><?= esc($b['time_label']) ?> &middot; <?= esc($b['room']) ?></div>
                                    <?php if ($height > 62): ?><div class="b-meta"><?= esc($b['section']) ?> &middot; <?= esc(str_replace(' Year', ' Yr', $b['year_level'])) ?></div><?php endif ?>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endfor ?>
                </div>
            </div>
        </section>

        <section class="schedule-agenda" aria-label="Weekly schedule list">
            <?php for ($day = 0; $day < count($dayLabels); $day++): ?>
                <?php $dayBlocks = array_values(array_filter($blocks, static fn ($b) => $b['day'] === $day)); ?>
                <?php if (empty($dayBlocks)) continue; ?>
                <div class="schedule-agenda__day"><?= esc($dayLabels[$day]) ?></div>
                <?php foreach ($dayBlocks as $b): ?>
                    <?php $c = $colors[$b['color']] ?? $colors['blue']; ?>
                    <article class="schedule-agenda__item" role="button" tabindex="0"<?= $blockAttrs($b, $dayLabels[$day], $c) ?>>
                        <div class="schedule-agenda__time"><?= esc($b['time_label']) ?></div>
                        <div class="schedule-agenda__class" style="--class-color:<?= $c['border'] ?>">
                            <div class="schedule-agenda__code"><?= esc($b['code']) ?></div>
                            <div class="schedule-agenda__name"><?= esc($b['title']) ?></div>
                            <div class="schedule-agenda__meta"><?= esc($b['room']) ?> &middot; <?= esc($b['section']) ?> &middot; <?= esc($b['year_level']) ?></div>
                        </div>
                    </article>
                <?php endforeach ?>
            <?php endfor ?>
        </section>
    <?php endif ?>
</div>

<div class="cls-modal" id="clsModal" role="dialog" aria-modal="true" aria-labelledby="clsTitle" hidden>
    <div class="cls-modal__card">
        <div class="cls-modal__head">
            <div class="cls-modal__code" id="clsCode"></div>
            <h3 class="cls-modal__title" id="clsTitle"></h3>
            <button type="button" class="cls-modal__close" id="clsClose" aria-label="Close"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
        </div>
        <div class="cls-modal__body">
            <div class="cls-modal__row"><span class="cls-modal__ico"><span class="material-symbols-outlined" aria-hidden="true">event</span></span><div><span class="cls-modal__lbl">Day</span><span class="cls-modal__val" id="clsDay"></span></div></div>
            <div class="cls-modal__row"><span class="cls-modal__ico"><span class="material-symbols-outlined" aria-hidden="true">schedule</span></span><div><span class="cls-modal__lbl">Time</span><span class="cls-modal__val" id="clsTime"></span></div></div>
            <div class="cls-modal__row"><span class="cls-modal__ico"><span class="material-symbols-outlined" aria-hidden="true">meeting_room</span></span><div><span class="cls-modal__lbl">Room</span><span class="cls-modal__val" id="clsRoom"></span></div></div>
            <div class="cls-modal__row"><span class="cls-modal__ico"><span class="material-symbols-outlined" aria-hidden="true">groups</span></span><div><span class="cls-modal__lbl">Section</span><span class="cls-modal__val" id="clsSection"></span></div></div>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('clsModal');
    if (!modal) return;
    var lastFocus = null;
    var set = function (id, v) { document.getElementById(id).textContent = v || '—'; };
    function open(el) {
        var d = el.dataset;
        set('clsCode', d.code); set('clsTitle', d.title); set('clsDay', d.day);
        set('clsTime', d.time); set('clsRoom', d.room);
        set('clsSection', [d.section, d.year].filter(Boolean).join(' · '));
        modal.style.setProperty('--accent', d.accent);
        lastFocus = el; modal.hidden = false; modal.classList.add('is-open');
        document.getElementById('clsClose').focus();
    }
    function close() {
        modal.classList.remove('is-open'); modal.hidden = true;
        if (lastFocus) lastFocus.focus();
    }
    document.addEventListener('click', function (e) {
        var el = e.target.closest('.schedule-calendar .block, .schedule-agenda__item');
        if (el) return open(el);
        if (e.target === modal || e.target.closest('#clsClose')) close();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) return close();
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('.schedule-calendar .block, .schedule-agenda__item')) {
            e.preventDefault(); open(e.target);
        }
    });
})();
</script>
<?= $this->endSection() ?>

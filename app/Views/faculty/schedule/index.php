<?php
/**
 * Schedule — Faculty view (Akademus SIS)
 * Read-only weekly grid of this faculty member's own plotted classes for
 * the active term — visually modeled on the chairperson's plotting grid,
 * minus any editing controls.
 */
$initials = strtoupper(mb_substr($user['name'], 0, 2));

// Grid window: 7:00–19:00, one row per 30 minutes (matches the chairperson
// plotting grid's granularity), so blocks line up cleanly regardless of
// exact start/end minute.
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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Schedule · Akademus SIS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?= base_url('assets/css/design-system/tokens.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/tokens.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/design-system/base.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/base.css') ?>">
<style>
:root{
    /* Aliased onto the shared design-system tokens (public/assets/css/design-system/tokens.css). */
    --brand:var(--color-primary);
    --brand-soft:var(--color-primary-soft);
    --bg:var(--color-background);
    --border:var(--color-border);
    --ink:var(--color-text-primary);
    --muted:var(--color-text-secondary);
    --red:var(--color-danger); --red-soft:var(--color-danger-soft);
    --shadow-apple:var(--shadow-sm);
}
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%}
body{
    font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    background:var(--bg);color:var(--ink);
    display:flex;padding:12px;gap:12px;overflow:hidden;
    -webkit-font-smoothing:antialiased;
}
button{font-family:inherit;cursor:pointer;border:none;background:none}
.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24;vertical-align:middle}
.fill-icon{font-variation-settings:'FILL' 1}
.glass{background:rgba(255,255,255,.75);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.4)}

/* ===== Sidebar ===== */
.side-drawer{width:64px;flex:0 0 auto;display:flex;flex-direction:column;align-items:center;padding:20px 0;border-radius:20px;box-shadow:var(--shadow-apple);transition:width .16s ease;overflow:hidden;z-index:50}
.side-drawer:hover{width:200px;align-items:stretch}
.side-drawer .brand{width:40px;height:40px;border-radius:10px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.08);color:var(--brand);display:flex;align-items:center;justify-content:center;margin:0 auto 24px;flex:0 0 auto}
.side-drawer:hover .brand{margin-left:12px;margin-right:auto}
.side-drawer nav{display:flex;flex-direction:column;gap:8px;width:100%;align-items:center;flex:1}
.side-drawer:hover nav{align-items:stretch;padding:0 8px}
.drawer-link{display:flex;align-items:center;gap:12px;width:44px;height:44px;border-radius:12px;color:var(--muted);text-decoration:none;flex:0 0 auto;justify-content:center;transition:background .15s,color .15s;white-space:nowrap;overflow:hidden}
.side-drawer:hover .drawer-link{width:100%;justify-content:flex-start;padding:0 12px}
.drawer-link .drawer-label{font-size:.82rem;font-weight:600;display:none}
.side-drawer:hover .drawer-label{display:inline}
.drawer-link:hover{color:var(--ink);background:rgba(0,0,0,.05)}
.drawer-link.active{color:var(--brand);background:var(--brand-soft)}
.drawer-link.active .material-symbols-outlined{font-variation-settings:'FILL' 1}
.drawer-link--logout{margin-top:auto}
.drawer-link--logout:hover{color:var(--red);background:var(--red-soft)}

/* ===== Content shell ===== */
.content{flex:1;min-width:0;display:flex;flex-direction:column;gap:12px;height:100%;overflow:hidden}
.topbar{border-radius:20px;display:flex;justify-content:space-between;align-items:center;gap:24px;padding:16px 32px;box-shadow:var(--shadow-apple)}
.topbar-title{font-size:1.15rem;font-weight:600;letter-spacing:-.01em;white-space:nowrap}
.topbar-sub{font-size:.76rem;color:var(--muted);margin-top:2px}
.scroll-area{flex:1;overflow-y:auto;border-radius:20px;background:#fff;box-shadow:var(--shadow-apple)}

/* Grid */
.grid-wrap{padding:24px}
.grid{
    display:grid;
    grid-template-columns:64px repeat(6, 1fr);
    border:1px solid var(--border);border-radius:14px;overflow:hidden;
}
.grid-head{
    display:contents;
}
.grid-head > div{
    background:#fafafa;border-bottom:1px solid var(--border);
    padding:10px 8px;font-size:.74rem;font-weight:700;color:var(--muted);
    text-align:center;text-transform:uppercase;letter-spacing:.03em;
    position:sticky;top:0;z-index:2;
}
.grid-corner{background:#fafafa}
.time-col{
    display:flex;flex-direction:column;border-right:1px solid var(--border);background:#fafafa;
}
.time-slot{
    height:32px;font-size:.68rem;color:var(--muted);text-align:right;padding:0 8px;
    border-bottom:1px solid rgba(0,0,0,.04);display:flex;align-items:flex-start;justify-content:flex-end;
}
.day-col{position:relative;border-right:1px solid var(--border)}
.day-col:last-child{border-right:none}
.day-col .hour-line{height:32px;border-bottom:1px solid rgba(0,0,0,.04)}
.day-col .hour-line:nth-child(odd){background:rgba(0,0,0,.012)}

.block{
    position:absolute;left:4px;right:4px;border-radius:10px;
    border-left:4px solid;padding:6px 8px;overflow:hidden;
    box-shadow:0 1px 3px rgba(0,0,0,.08);
}
.block .b-code{font-size:.76rem;font-weight:700;line-height:1.2}
.block .b-title{font-size:.68rem;line-height:1.25;margin-top:1px;opacity:.85}
.block .b-meta{font-size:.64rem;margin-top:3px;opacity:.75}

.empty-state{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    padding:64px 24px;color:var(--muted);text-align:center;
}
.empty-state .material-symbols-outlined{font-size:40px;margin-bottom:10px;opacity:.5}
.empty-state h3{font-size:.94rem;font-weight:700;color:var(--ink);margin-bottom:4px}
.empty-state p{font-size:.8rem;max-width:340px}

.stats-strip{display:flex;gap:24px;padding:16px 24px;border-bottom:1px solid var(--border)}
.stat{display:flex;flex-direction:column}
.stat .num{font-size:1.3rem;font-weight:700}
.stat .lbl{font-size:.72rem;color:var(--muted)}

@media (max-width:900px){
    .grid-wrap{padding:16px}
}

@media (max-width:640px){
    body{padding:6px;gap:6px}
    .side-drawer{width:56px}
    .topbar{padding:12px 16px}
    .grid-wrap{padding:12px;overflow-x:auto}
    .grid{min-width:760px}
    .stats-strip{padding:12px 16px;gap:16px}
}
</style>
</head>

<body>

<!-- Sidebar -->
<aside class="side-drawer glass">
    <div class="brand"><span class="material-symbols-outlined fill-icon">school</span></div>
    <nav>
        <a class="drawer-link" href="<?= site_url('faculty/dashboard') ?>" title="Dashboard">
            <span class="material-symbols-outlined">dashboard</span>
            <span class="drawer-label">Dashboard</span>
        </a>
        <a class="drawer-link" href="<?= site_url('faculty/profile') ?>" title="Profile">
            <span class="material-symbols-outlined">person</span>
            <span class="drawer-label">Profile</span>
        </a>
        <a class="drawer-link active" href="<?= site_url('faculty/schedule') ?>" title="Schedule">
            <span class="material-symbols-outlined">calendar_month</span>
            <span class="drawer-label">Schedule</span>
        </a>
        <a class="drawer-link" href="<?= site_url('faculty/availability') ?>" title="Availability">
            <span class="material-symbols-outlined">event_available</span>
            <span class="drawer-label">Availability</span>
        </a>
    </nav>
    <a class="drawer-link drawer-link--logout" href="<?= site_url('logout') ?>" title="Logout">
        <span class="material-symbols-outlined">logout</span>
        <span class="drawer-label">Logout</span>
    </a>
</aside>

<!-- Content -->
<div class="content">

    <header class="topbar glass">
        <div>
            <div class="topbar-title">My Schedule</div>
            <div class="topbar-sub"><?= $activeYear ? esc($activeYear) . ' · read-only — your chairperson plots this' : 'No active term configured yet' ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:50%;background:var(--brand);color:#fff;display:grid;place-items:center;font-size:.7rem;font-weight:700;"><?= esc($initials) ?></div>
            <div>
                <div style="font-size:.82rem;font-weight:600;line-height:1.2;"><?= esc($user['name']) ?></div>
                <div style="font-size:.7rem;color:var(--muted);line-height:1.2;"><?= esc($user['role']) ?></div>
            </div>
        </div>
    </header>

    <main class="scroll-area">
        <?php if (empty($blocks)): ?>
            <div class="empty-state">
                <span class="material-symbols-outlined">event_busy</span>
                <h3>No classes plotted yet</h3>
                <p><?= $activeYear ? 'Your chairperson hasn\'t assigned you any classes for the active term yet.' : 'Ask an admin to set an active academic term.' ?></p>
            </div>
        <?php else: ?>
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
                            <div class="time-slot" style="height:64px;"><?php
                                $h12 = $m % 720 === 0 ? 12 : intdiv($m, 60) % 12;
                                echo $h12 . ($m < 720 ? ' AM' : ' PM');
                            ?></div>
                        <?php endfor ?>
                    </div>

                    <?php for ($day = 0; $day < 6; $day++): ?>
                        <div class="day-col" style="height:<?= $totalRows * 32 ?>px;">
                            <?php for ($r = 0; $r < $totalRows / 2; $r++): ?>
                                <div class="hour-line" style="height:64px;"></div>
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
    </main>
</div>

</body>
</html>

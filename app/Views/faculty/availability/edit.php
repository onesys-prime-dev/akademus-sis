<?php
/**
 * Availability & Subject Preferences — Faculty view (Akademus SIS)
 * Same visual shell as Teaching Loads (sidebar/topbar/cards) so the faculty
 * side reads as one consistent app, matching the chairperson side's design
 * language (schedule_plotting.php / admin console).
 */
$initials = strtoupper(mb_substr($user['name'], 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Availability &amp; Preferences · Akademus SIS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<style>
:root{
    --brand:#0040A6;
    --brand-soft:rgba(0,64,166,.1);
    --bg:#f5f5f7;
    --border:rgba(0,0,0,.08);
    --ink:#1d1d1f;
    --muted:#86868b;
    --green:#16a34a; --green-soft:#f0fdf4; --green-border:#bbf7d0;
    --red:#ef4444; --red-soft:#fef2f2; --red-border:#fecaca;
    --amber:#f59e0b; --amber-soft:#fef9c3; --amber-border:#fde68a;
    --indigo:#4338ca; --indigo-soft:#e0e7ff; --indigo-border:#c7d2fe;
    --shadow-apple:0 4px 24px rgba(0,0,0,.04);
    --shadow-hover:0 8px 32px rgba(0,0,0,.08);
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
button:focus-visible,input:focus-visible,select:focus-visible{outline:2px solid rgba(0,64,166,.4);outline-offset:2px}

.material-symbols-outlined{
    font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24;
    vertical-align:middle;
}
.fill-icon{font-variation-settings:'FILL' 1}

.glass{
    background:rgba(255,255,255,.75);
    backdrop-filter:blur(24px);
    -webkit-backdrop-filter:blur(24px);
    border:1px solid rgba(255,255,255,.4);
}

/* ===== Sidebar: icon rail that expands to show labels on hover, and
   retracts the instant the pointer leaves — no click, no persisted state
   (matches the chairperson side's Schedule Plotting drawer). ===== */
.side-drawer{
    width:64px;flex:0 0 auto;
    display:flex;flex-direction:column;align-items:center;
    padding:20px 0;border-radius:20px;
    box-shadow:var(--shadow-apple);
    transition:width .16s ease;overflow:hidden;z-index:50;
}
.side-drawer:hover{width:200px;align-items:stretch}

.side-drawer .brand{
    width:40px;height:40px;border-radius:10px;background:#fff;
    box-shadow:0 1px 2px rgba(0,0,0,.08);color:var(--brand);
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 24px;flex:0 0 auto;
}
.side-drawer:hover .brand{margin-left:12px;margin-right:auto}

.side-drawer nav{display:flex;flex-direction:column;gap:8px;width:100%;align-items:center;flex:1}
.side-drawer:hover nav{align-items:stretch;padding:0 8px}

.drawer-link{
    display:flex;align-items:center;gap:12px;
    width:44px;height:44px;border-radius:12px;
    color:var(--muted);text-decoration:none;flex:0 0 auto;
    justify-content:center;transition:background .15s,color .15s;
    white-space:nowrap;overflow:hidden;
}
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

.topbar{
    border-radius:20px;
    display:flex;justify-content:space-between;align-items:center;
    gap:24px;padding:16px 32px;box-shadow:var(--shadow-apple);
}
.topbar-title{font-size:1.15rem;font-weight:600;letter-spacing:-.01em;white-space:nowrap}

.scroll-area{flex:1;overflow-y:auto;border-radius:20px}
.container{max-width:880px;margin:0 auto;padding:32px}

.page-head{margin-bottom:24px}
.page-head h1{font-size:1.5rem;font-weight:700;letter-spacing:-.02em;margin-bottom:4px}
.page-head p{color:var(--muted);font-size:.88rem}

.msg-ok, .msg-err{
    display:flex;align-items:center;gap:10px;
    padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:.86rem;font-weight:500;
}
.msg-ok{color:var(--green);background:var(--green-soft);border:1px solid var(--green-border)}
.msg-err{color:var(--red);background:var(--red-soft);border:1px solid var(--red-border)}
.msg-err p{margin:0}

.notice{
    background:var(--indigo-soft);color:var(--indigo);
    padding:16px 20px;border-radius:14px;font-size:.86rem;
}

/* Term selector */
.term-bar{
    display:flex;align-items:center;gap:12px;flex-wrap:wrap;
    background:#fff;border:1px solid var(--border);border-radius:16px;
    padding:14px 20px;margin-bottom:24px;box-shadow:var(--shadow-apple);
}
.term-bar label{font-size:.78rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em}
.term-bar select{
    padding:8px 32px 8px 14px;border:1px solid var(--border);border-radius:10px;
    font-family:inherit;font-size:.86rem;font-weight:500;color:var(--ink);
    background:#fff url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%226%22%3E%3Cpath d=%22M1 1l4 4 4-4%22 stroke=%22%2386868b%22 stroke-width=%221.5%22 fill=%22none%22/%3E%3C/svg%3E') no-repeat right 12px center;
    appearance:none;cursor:pointer;
}
.term-bar select:hover{border-color:var(--brand)}
.term-pill{
    font-size:.72rem;font-weight:700;color:var(--brand);background:var(--brand-soft);
    padding:3px 10px;border-radius:999px;
}

/* Cards */
.card{
    background:#fff;border:1px solid var(--border);border-radius:20px;
    padding:24px;margin-bottom:24px;box-shadow:var(--shadow-apple);
}
.card h2{font-size:1.02rem;font-weight:700;letter-spacing:-.01em;margin-bottom:4px}
.card .card-sub{color:var(--muted);font-size:.82rem;margin-bottom:20px}

/* Availability rows */
.avail-row{
    display:grid;grid-template-columns:88px 150px 1fr 1fr;gap:12px;align-items:center;
    padding:12px 4px;border-bottom:1px solid var(--border);
}
.avail-row:last-of-type{border-bottom:none}
.avail-day{font-size:.86rem;font-weight:700}
.avail-row select, .avail-row input[type=time]{
    padding:8px 12px;border:1px solid var(--border);border-radius:10px;
    font-family:inherit;font-size:.82rem;color:var(--ink);background:#fff;
    width:100%;
}
.avail-row select{
    background:#fff url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%226%22%3E%3Cpath d=%22M1 1l4 4 4-4%22 stroke=%22%2386868b%22 stroke-width=%221.5%22 fill=%22none%22/%3E%3C/svg%3E') no-repeat right 12px center;
    appearance:none;padding-right:30px;cursor:pointer;
}
.avail-row select:focus, .avail-row input:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(0,64,166,.12)}
.avail-row.is-available{background:var(--green-soft);border-radius:12px;border-bottom-color:transparent}
.avail-row.is-unavailable{background:var(--red-soft);border-radius:12px;border-bottom-color:transparent}
.avail-row input:disabled{background:rgba(0,0,0,.03);color:var(--muted);cursor:not-allowed}

/* Subject preference rows */
.subj-row{
    display:grid;grid-template-columns:110px 1fr 170px;gap:12px;align-items:center;
    padding:12px 4px;border-bottom:1px solid var(--border);
}
.subj-row:last-of-type{border-bottom:none}
.subj-code{font-size:.84rem;font-weight:700;color:var(--brand)}
.subj-title{font-size:.84rem;color:var(--ink)}
.subj-row select{
    padding:8px 30px 8px 12px;border:1px solid var(--border);border-radius:10px;
    font-family:inherit;font-size:.82rem;font-weight:600;
    background:#fff url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%226%22%3E%3Cpath d=%22M1 1l4 4 4-4%22 stroke=%22%2386868b%22 stroke-width=%221.5%22 fill=%22none%22/%3E%3C/svg%3E') no-repeat right 12px center;
    appearance:none;cursor:pointer;width:100%;
}
.subj-row select.lvl-preferred{color:var(--green);background-color:var(--green-soft);border-color:var(--green-border)}
.subj-row select.lvl-willing{color:#854d0e;background-color:var(--amber-soft);border-color:var(--amber-border)}
.subj-row select.lvl-qualified{color:var(--indigo);background-color:var(--indigo-soft);border-color:var(--indigo-border)}

.save-row{margin-top:20px;display:flex;justify-content:flex-end}
.btn-primary{
    background:var(--brand);color:#fff;padding:11px 22px;border-radius:12px;
    font-size:.86rem;font-weight:600;transition:background .15s,transform .1s;
}
.btn-primary:hover{background:#003080}
.btn-primary:active{transform:scale(.97)}

.legend{display:flex;gap:16px;flex-wrap:wrap;margin-top:4px;margin-bottom:16px}
.legend span{display:inline-flex;align-items:center;gap:6px;font-size:.76rem;color:var(--muted)}
.legend .dot{width:8px;height:8px;border-radius:50%}
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
        <a class="drawer-link" href="<?= site_url('faculty/schedule') ?>" title="Schedule">
            <span class="material-symbols-outlined">calendar_month</span>
            <span class="drawer-label">Schedule</span>
        </a>
        <a class="drawer-link active" href="<?= site_url('faculty/availability') ?>" title="Availability">
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
        <div style="display:flex;align-items:center;gap:16px">
            <div class="topbar-title">Availability &amp; Preferences</div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <div class="avatar" style="width:32px;height:32px;border-radius:50%;background:var(--brand);color:#fff;display:grid;place-items:center;font-size:.7rem;font-weight:700;"><?= esc($initials) ?></div>
            <div>
                <div style="font-size:.82rem;font-weight:600;line-height:1.2;"><?= esc($user['name']) ?></div>
                <div style="font-size:.7rem;color:var(--muted);line-height:1.2;"><?= esc($user['role']) ?></div>
            </div>
        </div>
    </header>

    <main class="scroll-area">
        <div class="container">

            <div class="page-head">
                <h1>Set Your Availability</h1>
                <p>Tell your chairperson when you can teach and which subjects you'd like — this shows up while they're plotting the schedule.</p>
            </div>

            <?php if (! empty($allTerms)): ?>
                <div class="term-bar">
                    <label for="termSelect">Term</label>
                    <select id="termSelect" onchange="if(this.value) window.location.href = '<?= site_url('faculty/availability') ?>?ay_id=' + this.value;">
                        <?php foreach ($allTerms as $t): ?>
                            <option value="<?= (int) $t['ay_id'] ?>" <?= ($term && (int) $term['ay_id'] === (int) $t['ay_id']) ? 'selected' : '' ?>>
                                <?= esc(\App\Models\AcademicYearModel::format($t['label'], $t['semester'])) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($term && $term['is_active']): ?>
                        <span class="term-pill">Active term</span>
                    <?php endif ?>
                </div>
            <?php endif ?>

            <?php if (session()->has('message')): ?>
                <div class="msg-ok"><span class="material-symbols-outlined" style="font-size:18px;">check_circle</span> <?= esc(session('message')) ?></div>
            <?php endif ?>

            <?php if (session()->has('errors')): ?>
                <div class="msg-err">
                    <span class="material-symbols-outlined" style="font-size:18px;">error</span>
                    <div>
                        <?php foreach (session('errors') as $error): ?>
                            <p><?= esc($error) ?></p>
                        <?php endforeach ?>
                    </div>
                </div>
            <?php endif ?>

            <?php if ($term === null): ?>
                <div class="notice">No academic term has been configured yet. Ask an admin to add one before you can submit your availability.</div>
            <?php else: ?>

                <div class="card">
                    <h2>Weekly Availability</h2>
                    <p class="card-sub">For each day, mark whether you're available, and if so, your time range. Leave a day as "No preference" if you'd rather not commit either way yet.</p>

                    <form method="post" action="<?= site_url('faculty/availability/update') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ay_id" value="<?= (int) $term['ay_id'] ?>">

                        <?php foreach ($dayLabels as $day => $label): ?>
                            <?php $row = $availabilityByDay[$day] ?? null; ?>
                            <?php $status = $row ? ($row['is_available'] ? 'available' : 'unavailable') : ''; ?>
                            <div class="avail-row <?= $status === 'available' ? 'is-available' : ($status === 'unavailable' ? 'is-unavailable' : '') ?>" id="row_<?= $day ?>">
                                <div class="avail-day"><?= esc($label) ?></div>
                                <select name="day_<?= $day ?>_status" class="day-status" data-day="<?= $day ?>" onchange="toggleTimes(<?= $day ?>)">
                                    <option value="" <?= $status === '' ? 'selected' : '' ?>>No preference</option>
                                    <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option>
                                    <option value="unavailable" <?= $status === 'unavailable' ? 'selected' : '' ?>>Not Available</option>
                                </select>
                                <input type="time" name="day_<?= $day ?>_start" id="start_<?= $day ?>" value="<?= esc($row['start_time'] ?? '') ?>">
                                <input type="time" name="day_<?= $day ?>_end" id="end_<?= $day ?>" value="<?= esc($row['end_time'] ?? '') ?>">
                            </div>
                        <?php endforeach ?>

                        <div class="save-row">
                            <button type="submit" class="btn-primary">Save Availability</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h2>Subject Preferences</h2>
                    <p class="card-sub">Let your chairperson know which subjects you'd like to teach this term.</p>

                    <?php if (empty($subjects)): ?>
                        <p class="card-sub" style="margin-bottom:0;">No subjects found for your department yet.</p>
                    <?php else: ?>
                        <div class="legend">
                            <span><span class="dot" style="background:var(--green);"></span> Preferred</span>
                            <span><span class="dot" style="background:var(--amber);"></span> Willing</span>
                            <span><span class="dot" style="background:var(--indigo);"></span> Qualified</span>
                        </div>

                        <form method="post" action="<?= site_url('faculty/preferences/update') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="ay_id" value="<?= (int) $term['ay_id'] ?>">

                            <?php foreach ($subjects as $subject): ?>
                                <?php $current = $preferencesById[$subject['subject_id']] ?? ''; ?>
                                <div class="subj-row">
                                    <span class="subj-code"><?= esc($subject['subject_code']) ?></span>
                                    <span class="subj-title"><?= esc($subject['subject_name']) ?></span>
                                    <select name="preference[<?= $subject['subject_id'] ?>]" class="<?= $current !== '' ? 'lvl-' . $current : '' ?>" onchange="this.className = this.value ? 'lvl-' + this.value : ''">
                                        <option value="" <?= $current === '' ? 'selected' : '' ?>>Not set</option>
                                        <?php foreach ($preferenceLevels as $level): ?>
                                            <option value="<?= $level ?>" <?= $current === $level ? 'selected' : '' ?>><?= esc(ucfirst($level)) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                </div>
                            <?php endforeach ?>

                            <div class="save-row">
                                <button type="submit" class="btn-primary">Save Preferences</button>
                            </div>
                        </form>
                    <?php endif ?>
                </div>

            <?php endif ?>

        </div>
    </main>
</div>

<script>
    function toggleTimes(day) {
        var status = document.querySelector('.day-status[data-day="' + day + '"]').value;
        var disabled = status !== 'available';
        document.getElementById('start_' + day).disabled = disabled;
        document.getElementById('end_' + day).disabled = disabled;
        if (disabled) {
            document.getElementById('start_' + day).value = '';
            document.getElementById('end_' + day).value = '';
        }
        var row = document.getElementById('row_' + day);
        row.classList.remove('is-available', 'is-unavailable');
        if (status === 'available') row.classList.add('is-available');
        else if (status === 'unavailable') row.classList.add('is-unavailable');
    }
    document.querySelectorAll('.day-status').forEach(function (el) {
        toggleTimes(el.dataset.day);
    });
</script>

</body>
</html>

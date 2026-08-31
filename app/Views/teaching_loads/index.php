<?php
/**
 * Teaching Loads — Faculty view (Akademus SIS)
 * Loads are generated live from the chairpersons' plotted schedules and kept
 * current via long-polling. Assignment is chairperson-directed while
 * plotting — faculty view their official assignments here, read-only.
 * Pure CSS — no framework.
 */
$initials = strtoupper(mb_substr($user['name'], 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Available Teaching Loads · Akademus SIS</title>
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
    --amber:#f59e0b;
    --shadow-apple:0 4px 24px rgba(0,0,0,.04);
    --shadow-hover:0 8px 32px rgba(0,0,0,.08);
}
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%}
body{
    font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    background:var(--bg);color:var(--ink);
    display:flex;overflow:hidden;
    -webkit-font-smoothing:antialiased;
}
button{font-family:inherit;cursor:pointer;border:none;background:none}
button:focus-visible,input:focus-visible{outline:2px solid rgba(0,64,166,.4);outline-offset:2px}

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

/* ===== Sidebar ===== */
.sidenav{
    position:fixed;left:0;top:0;height:100vh;width:260px;
    border-right:1px solid var(--border);
    display:flex;flex-direction:column;padding:24px 0;z-index:50;
}
.brand-row{display:flex;align-items:center;gap:16px;padding:0 24px;margin-bottom:40px}
.brand-icon{
    width:40px;height:40px;border-radius:16px;background:var(--brand-soft);
    display:grid;place-items:center;color:var(--brand);
}
.brand-icon .material-symbols-outlined{font-size:24px}
.brand-name{font-weight:600;font-size:1.05rem;letter-spacing:-.01em}
.brand-sub{color:var(--muted);font-size:.72rem;font-weight:500;letter-spacing:.02em}

.nav-list{list-style:none;display:flex;flex-direction:column;gap:8px;padding:0 16px;flex:1}
.nav-link{
    display:flex;align-items:center;gap:12px;
    padding:12px 16px;border-radius:12px;
    font-size:.86rem;font-weight:500;color:var(--ink);text-decoration:none;
    transition:background .15s,transform .1s;
}
.nav-link:hover{background:rgba(0,0,0,.05)}
.nav-link:active{transform:scale(.97)}
.nav-link.active{background:var(--brand);color:#fff;box-shadow:0 1px 2px rgba(0,0,0,.1)}
.nav-link .material-symbols-outlined{font-size:20px}

.profile-card{
    margin:auto 16px 16px;display:flex;align-items:center;gap:12px;
    padding:12px 16px;border-radius:16px;
    background:rgba(255,255,255,.5);border:1px solid rgba(0,0,0,.05);
    box-shadow:0 1px 2px rgba(0,0,0,.05);
}
.avatar{
    width:36px;height:36px;border-radius:50%;background:var(--brand);color:#fff;
    display:grid;place-items:center;font-size:.72rem;font-weight:700;flex:none;
}
.profile-name{font-size:.84rem;font-weight:600}
.profile-role{font-size:.72rem;color:var(--muted)}

/* ===== Content shell ===== */
.content{flex:1;display:flex;flex-direction:column;margin-left:260px;height:100vh;overflow:hidden}

.topbar{
    position:sticky;top:0;z-index:40;
    display:flex;justify-content:space-between;align-items:center;
    gap:24px;padding:16px 32px;border-bottom:1px solid var(--border);
}
.topbar-title{font-size:1.15rem;font-weight:600;letter-spacing:-.01em;white-space:nowrap}
.topbar-right{display:flex;align-items:center;gap:16px;flex:1;justify-content:flex-end}

.search-box{position:relative;width:100%;max-width:420px}
.search-box .material-symbols-outlined{
    position:absolute;left:16px;top:50%;transform:translateY(-50%);
    color:var(--muted);font-size:20px;transition:color .15s;pointer-events:none;
}
.search-box input{
    width:100%;padding:10px 16px 10px 44px;
    background:rgba(0,0,0,.05);border:1px solid transparent;border-radius:999px;
    font-family:inherit;font-size:.84rem;color:var(--ink);
    transition:background .15s,box-shadow .15s;
}
.search-box input::placeholder{color:var(--muted)}
.search-box input:focus{outline:none;background:#fff;box-shadow:0 0 0 2px rgba(0,64,166,.3)}
.search-box:focus-within .material-symbols-outlined{color:var(--brand)}

.icon-btn{
    color:var(--muted);padding:8px;border-radius:50%;
    display:grid;place-items:center;transition:color .15s,background .15s;
}
.icon-btn:hover{color:var(--ink);background:rgba(0,0,0,.05)}

.menu-btn{display:none;color:var(--ink);padding:8px;border-radius:50%}
.menu-btn:hover{background:rgba(0,0,0,.05)}

/* ===== Main area ===== */
.scroll-area{flex:1;overflow-y:auto;padding:40px}
.container{max-width:1200px;margin:0 auto}

.page-head{margin-bottom:40px}
.page-head h1{font-size:2.3rem;font-weight:700;letter-spacing:-.02em;margin-bottom:12px}
.page-head p{color:var(--muted);font-size:.95rem;max-width:640px;line-height:1.6}

/* ===== Stats card ===== */
.stats-card{
    background:#fff;border-radius:24px;padding:32px;
    box-shadow:var(--shadow-apple);border:1px solid var(--border);
    margin-bottom:40px;
}
.spec-row{display:flex;align-items:center;gap:24px;margin-bottom:32px;flex-wrap:wrap}
.spec-row .lbl{font-size:.86rem;font-weight:600;white-space:nowrap}
.spec-chips{display:flex;flex-wrap:wrap;gap:8px}
.spec-chip{
    background:var(--brand-soft);color:var(--brand);
    padding:6px 16px;border-radius:999px;
    font-size:.74rem;font-weight:500;letter-spacing:.02em;
}
.load-meter{display:flex;flex-direction:column;gap:12px}
.load-meter-head{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap}
.load-meter-head .lbl{font-size:.86rem;font-weight:600}
.load-meter-head .rem{font-size:.74rem;color:var(--muted);font-weight:500}
.track{height:8px;width:100%;background:rgba(0,0,0,.05);border-radius:999px;overflow:hidden}
.fill{height:100%;width:0;background:var(--brand);border-radius:999px;transition:width .5s ease-in-out,background .3s}
.fill.warn{background:var(--amber)}
.fill.full{background:var(--red)}

/* ===== Panel: tabs + board ===== */
.panel{
    background:#fff;border-radius:24px;overflow:hidden;
    box-shadow:var(--shadow-apple);border:1px solid var(--border);
    display:flex;flex-direction:column;height:640px;
}
.tab-row{display:flex;gap:8px;padding:24px 24px 0;flex-wrap:wrap;background:#fff}
.tab{
    display:inline-flex;align-items:center;gap:8px;
    padding:10px 20px;border-radius:999px;
    font-size:.86rem;font-weight:500;color:var(--muted);
    transition:background .15s,color .15s;
}
.tab:hover:not(:disabled):not(.active){color:var(--ink);background:rgba(0,0,0,.05)}
.tab.active{background:var(--brand);color:#fff;box-shadow:0 1px 2px rgba(0,0,0,.1)}
.tab:disabled{color:rgba(134,134,139,.5);cursor:not-allowed}
.tab-cnt{
    background:rgba(0,0,0,.05);color:var(--muted);
    font-size:.7rem;border-radius:999px;padding:2px 8px;font-weight:600;
}
.tab.active .tab-cnt{background:rgba(255,255,255,.2);color:#fff}
.tab:disabled .tab-cnt{color:rgba(134,134,139,.5)}

.filter-bar{
    display:flex;align-items:center;gap:16px;flex-wrap:wrap;
    padding:20px 32px;border-bottom:1px solid var(--border);
    background:rgba(255,255,255,.5);
}
.filter-bar .lbl{font-size:.84rem;font-weight:500;color:var(--muted)}
.chip-row{display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;scrollbar-width:none}
.chip-row::-webkit-scrollbar{display:none}
.chip{
    background:#fff;color:var(--ink);
    border:1px solid var(--border);box-shadow:0 1px 2px rgba(0,0,0,.05);
    padding:6px 16px;border-radius:999px;
    font-size:.72rem;font-weight:500;white-space:nowrap;
    transition:background .15s,color .15s;
}
.chip:hover:not(.active){background:rgba(0,0,0,.05)}
.chip.active{background:var(--ink);border-color:var(--ink);color:#fff}
.filter-bar .summary{margin-left:auto;font-size:.74rem;font-weight:500;color:var(--muted)}
.filter-select{
    background:#fff;color:var(--ink);
    border:1px solid var(--border);box-shadow:0 1px 2px rgba(0,0,0,.05);
    padding:6px 12px;border-radius:999px;
    font-family:inherit;font-size:.72rem;font-weight:500;cursor:pointer;
}
.filter-select:focus{outline:none;box-shadow:0 0 0 2px rgba(0,64,166,.3)}

.board-scroll{flex:1;overflow-y:auto;background:#fff}
.tile-grid{
    display:grid;grid-template-columns:repeat(4,minmax(0,1fr));
    gap:20px;padding:32px;
}

/* ===== Load tiles ===== */
.tile{
    background:#fff;border:1px solid var(--border);border-radius:16px;
    padding:20px;display:flex;flex-direction:column;gap:12px;
    box-shadow:0 1px 2px rgba(0,0,0,.05);
    transition:box-shadow .15s,transform .15s;
}
.tile:hover{box-shadow:var(--shadow-hover);transform:translateY(-2px)}
.tile-head{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
.tile-code{font-size:1.1rem;font-weight:700;letter-spacing:-.01em}
.tile-chip{
    padding:4px 10px;border-radius:999px;
    font-size:.62rem;font-weight:600;white-space:nowrap;flex:none;
}
.tile.acc-blue .tile-code{color:#2563eb}   .tile.acc-blue .tile-chip{background:#eff6ff;color:#1d4ed8}
.tile.acc-purple .tile-code{color:#9333ea} .tile.acc-purple .tile-chip{background:#faf5ff;color:#7e22ce}
.tile.acc-red .tile-code{color:#ef4444}    .tile.acc-red .tile-chip{background:#fef2f2;color:#b91c1c}
.tile.acc-gold .tile-code{color:#f59e0b}   .tile.acc-gold .tile-chip{background:#fffbeb;color:#b45309}
.tile.acc-green .tile-code{color:#16a34a}  .tile.acc-green .tile-chip{background:#f0fdf4;color:#15803d}
.tile.acc-orange .tile-code{color:#f97316} .tile.acc-orange .tile-chip{background:#fff7ed;color:#c2410c}

.tile-title{font-size:.86rem;font-weight:600;line-height:1.4;min-height:2.6em}
.tile-meta{display:flex;align-items:center;gap:8px;font-size:.74rem;color:var(--muted)}
.tile-meta .material-symbols-outlined{font-size:16px;color:rgba(134,134,139,.7)}

.tile-btn{
    margin-top:8px;border-radius:999px;padding:10px;
    font-size:.82rem;font-weight:500;text-align:center;
    display:flex;align-items:center;justify-content:center;gap:6px;
    transition:background .15s,transform .1s;
}
.tile-btn.claim{background:var(--brand);color:#fff;box-shadow:0 1px 2px rgba(0,0,0,.1)}
.tile-btn.claim:hover{background:#00368d}
.tile-btn.claim:active{transform:scale(.96)}
.tile-btn.conflict{background:var(--red-soft);color:var(--red);border:1px solid var(--red-border);cursor:not-allowed}
.tile-btn.assigned{background:var(--green-soft);color:var(--green);border:1px solid var(--green-border);cursor:default}
.tile-btn.swap{background:rgba(0,0,0,.05);color:var(--ink)}
.tile-btn.swap:hover{background:rgba(0,0,0,.1)}
.tile-btn.swap:disabled{color:var(--muted);cursor:not-allowed}
.tile-btn.decline{background:var(--red-soft);color:var(--red);border:1px solid var(--red-border)}
.tile-btn.decline:hover{background:#fee2e2}
.tile-btn .material-symbols-outlined{font-size:17px}

.swap-status{
    padding:4px 12px;border-radius:999px;font-size:.62rem;font-weight:600;white-space:nowrap;flex:none;
}
.swap-status.pending{background:#fffbeb;color:#b45309}
.swap-status.accepted{background:var(--green-soft);color:var(--green)}
.swap-status.declined{background:var(--red-soft);color:var(--red)}
.swap-status.cancelled{background:rgba(0,0,0,.05);color:var(--muted)}
.swap-who{font-size:.74rem;color:var(--ink);font-weight:600}
.swap-status.awaiting{background:#eff6ff;color:#1d4ed8}
.swap-status.approved{background:var(--green-soft);color:var(--green)}
.swap-status.rejected{background:var(--red-soft);color:var(--red)}

.tile.is-taken{opacity:.85;background:#fafafa}
.taken-chip{
    padding:4px 10px;border-radius:999px;font-size:.62rem;font-weight:600;white-space:nowrap;flex:none;
    background:rgba(0,0,0,.06);color:var(--muted);
}

/* ===== Empty state ===== */
.empty-state{
    height:100%;display:none;flex-direction:column;align-items:center;justify-content:center;
    padding:32px;text-align:center;
}
.empty-state.visible{display:flex}
.empty-icon{
    width:80px;height:80px;border-radius:50%;background:rgba(0,0,0,.05);
    display:grid;place-items:center;color:var(--muted);
    box-shadow:inset 0 2px 4px rgba(0,0,0,.05);margin-bottom:24px;
}
.empty-icon .material-symbols-outlined{font-size:38px}
.empty-state h3{font-size:1.15rem;font-weight:600;letter-spacing:-.01em;margin-bottom:12px}
.empty-state p{font-size:.84rem;color:var(--muted);max-width:380px;line-height:1.6}

/* ===== Confirm modal ===== */
.modal-backdrop{
    position:fixed;inset:0;background:rgba(0,0,0,.3);
    backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);
    display:none;align-items:center;justify-content:center;padding:16px;z-index:60;
}
.modal-backdrop.open{display:flex}
.modal-card{
    background:#fff;border-radius:24px;border:1px solid var(--border);
    box-shadow:var(--shadow-hover);width:100%;max-width:440px;padding:32px;
}
.modal-card h3{font-size:1.15rem;font-weight:600;letter-spacing:-.01em;margin-bottom:12px}
.modal-card p{font-size:.84rem;color:var(--muted);line-height:1.6;margin-bottom:32px}
.modal-actions{display:flex;justify-content:flex-end;gap:12px}
.modal-btn{
    padding:10px 20px;border-radius:999px;font-size:.84rem;font-weight:500;
    transition:background .15s;
}
.modal-btn.cancel{background:rgba(0,0,0,.05);color:var(--ink)}
.modal-btn.cancel:hover{background:rgba(0,0,0,.1)}
.modal-btn.ok{background:var(--brand);color:#fff;box-shadow:0 1px 2px rgba(0,0,0,.1)}
.modal-btn.ok:hover{background:#00368d}

/* ===== Toast ===== */
.toast{
    position:fixed;bottom:24px;left:50%;
    transform:translate(-50%,64px);opacity:0;
    background:var(--ink);color:#fff;
    font-size:.82rem;font-weight:500;border-radius:999px;padding:12px 24px;
    box-shadow:var(--shadow-hover);
    transition:transform .2s,opacity .2s;z-index:70;pointer-events:none;
}
.toast.show{transform:translate(-50%,0);opacity:1}
.toast.success{background:var(--green)}
.toast.error{background:#dc2626}

/* ===== Responsive ===== */
@media (max-width:1200px){.tile-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:900px){.tile-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.topbar-title{display:none}}
@media (max-width:768px){
    .sidenav{display:none}
    .content{margin-left:0}
    .menu-btn{display:grid}
    .scroll-area{padding:24px 16px}
    .search-box{max-width:none}
}
@media (max-width:560px){.tile-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<!-- Sidebar -->
<nav class="sidenav glass">
    <div class="brand-row">
        <div class="brand-icon"><span class="material-symbols-outlined fill-icon">school</span></div>
        <div>
            <div class="brand-name">Faculty Portal</div>
            <div class="brand-sub">IT Department</div>
        </div>
    </div>
    <ul class="nav-list">
        <li><a class="nav-link active" href="#">
            <span class="material-symbols-outlined">dashboard</span> Dashboard</a></li>
        <li><a class="nav-link" href="#" title="Coming soon">
            <span class="material-symbols-outlined">person</span> Profile</a></li>
        <li><a class="nav-link" href="#" title="Coming soon">
            <span class="material-symbols-outlined">calendar_month</span> Schedule</a></li>
        <li><a class="nav-link" href="<?= site_url('faculty/availability') ?>">
            <span class="material-symbols-outlined">event_available</span> Availability</a></li>
        <li><a class="nav-link" href="<?= site_url('logout') ?>">
            <span class="material-symbols-outlined">logout</span> Logout</a></li>
    </ul>
    <div class="profile-card">
        <div class="avatar"><?= esc($initials) ?></div>
        <div>
            <div class="profile-name"><?= esc($user['name']) ?></div>
            <div class="profile-role">Faculty</div>
        </div>
    </div>
</nav>

<!-- Content -->
<div class="content">

    <header class="topbar glass">
        <div style="display:flex;align-items:center;gap:16px">
            <button class="menu-btn" type="button" aria-label="Menu">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <div class="topbar-title">Teaching Load Management</div>
        </div>
        <div class="topbar-right">
            <div class="search-box">
                <span class="material-symbols-outlined">search</span>
                <input id="searchInput" placeholder="Search subject, room, section..." type="text"/>
            </div>
            <div class="notif-wrap" id="notifWrap" style="position:relative;">
                <button class="icon-btn" type="button" id="notifBtn" title="Notifications">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <span id="notifBadge" style="display:none;position:absolute;top:2px;right:2px;background:var(--red,#ef4444);color:#fff;font-size:10px;font-weight:700;border-radius:999px;min-width:16px;height:16px;line-height:16px;text-align:center;padding:0 3px;"></span>
                <div id="notifPanel" style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:320px;max-height:400px;overflow-y:auto;background:#fff;border-radius:16px;box-shadow:var(--shadow-hover);border:1px solid var(--border);z-index:60;">
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--border);">
                        <strong style="font-size:.86rem;">Notifications</strong>
                        <button type="button" id="notifMarkAll" style="font-size:.72rem;color:var(--brand);background:none;border:none;cursor:pointer;">Mark all read</button>
                    </div>
                    <div id="notifList"></div>
                </div>
            </div>
        </div>
    </header>

    <main class="scroll-area">
        <div class="container">

            <div class="page-head">
                <h1>Teaching Loads</h1>
                <p>Your chairperson assigns subjects while plotting the schedule. This board updates in real time.</p>
            </div>

            <!-- Stats card -->
            <div class="stats-card">
                <div class="spec-row">
                    <span class="lbl">Your specializations:</span>
                    <div class="spec-chips">
                        <?php foreach ($specializations as $sp): ?>
                        <span class="spec-chip"><?= esc($sp) ?></span>
                        <?php endforeach ?>
                    </div>
                </div>
                <div class="load-meter">
                    <div class="load-meter-head">
                        <span class="lbl" id="loadLabel">Teaching Load: 0 / <?= esc($max_units) ?> units</span>
                        <span class="rem" id="loadRemaining"><?= esc($max_units) ?> units remaining</span>
                    </div>
                    <div class="track"><div class="fill" id="loadFill"></div></div>
                </div>
            </div>

            <!-- Tabs + board -->
            <div class="panel">
                <div class="tab-row">
                    <button type="button" class="tab active" data-tab="available">
                        Unassigned <span class="tab-cnt" id="cntAvailable">0</span>
                    </button>
                    <button type="button" class="tab" data-tab="mine">
                        My Assignments <span class="tab-cnt" id="cntMine">0</span>
                    </button>
                    <button type="button" class="tab" data-tab="swaps">
                        Swap Requests <span class="tab-cnt" id="cntSwaps">0</span>
                    </button>
                    <button type="button" class="tab" data-tab="changes">
                        Change Requests <span class="tab-cnt" id="cntChanges">0</span>
                    </button>
                </div>

                <div class="filter-bar">
                    <span class="lbl">Days:</span>
                    <div class="chip-row" id="dayFilter"></div>
                    <select class="filter-select" id="yearFilter"><option value="">All year levels</option></select>
                    <select class="filter-select" id="sectionFilter"><option value="">All sections</option></select>
                    <span class="summary" id="boardSummary"></span>
                </div>

                <div class="board-scroll">
                    <div class="tile-grid" id="boardGrid"></div>
                    <div class="empty-state" id="boardEmpty">
                        <div class="empty-icon"><span class="material-symbols-outlined">calendar_add_on</span></div>
                        <h3 id="emptyTitle"></h3>
                        <p id="emptySub"></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Swap request modal -->
<div class="modal-backdrop" id="swapModal">
    <div class="modal-card">
        <h3 id="swapTitle"></h3>
        <p id="swapMessage"></p>
        <select class="filter-select" id="swapTarget" style="width:100%;margin-bottom:32px;padding:10px 14px;border-radius:12px"></select>
        <div class="modal-actions">
            <button type="button" class="modal-btn cancel" id="swapCancel">Cancel</button>
            <button type="button" class="modal-btn ok" id="swapSend">Send Request</button>
        </div>
    </div>
</div>

<!-- Request for change modal -->
<div class="modal-backdrop" id="changeRequestModal">
    <div class="modal-card">
        <h3 id="crTitle"></h3>
        <p id="crMessage"></p>
        <textarea id="crReason" rows="3" placeholder="Why can't you accept this assignment? (availability conflict, schedule conflict, etc.)"
            style="width:100%;margin-bottom:12px;padding:10px 14px;border-radius:12px;border:1px solid var(--border);font-family:inherit;font-size:.86rem;resize:vertical;"></textarea>
        <input type="text" id="crProposed" placeholder="Proposed alternative (optional)"
            style="width:100%;margin-bottom:32px;padding:10px 14px;border-radius:12px;border:1px solid var(--border);font-family:inherit;font-size:.86rem;">
        <div class="modal-actions">
            <button type="button" class="modal-btn cancel" id="crCancel">Cancel</button>
            <button type="button" class="modal-btn ok" id="crSend">Submit Request</button>
        </div>
    </div>
</div>

<!-- Claim confirmation -->
<div class="modal-backdrop" id="confirmModal">
    <div class="modal-card">
        <h3 id="confirmTitle"></h3>
        <p id="confirmMessage"></p>
        <div class="modal-actions">
            <button type="button" class="modal-btn cancel" id="confirmCancel">Cancel</button>
            <button type="button" class="modal-btn ok" id="confirmOk">Claim Subject</button>
        </div>
    </div>
</div>

<!-- No department assigned -->
<div class="modal-backdrop" id="noDeptModal">
    <div class="modal-card">
        <h3>No Department Assigned</h3>
        <p>Your account isn't linked to a department yet, so there are no teaching loads to show. Please contact your administrator to have a department assigned to your account.</p>
        <div class="modal-actions">
            <button type="button" class="modal-btn ok" id="noDeptOk">Got it</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const CONFIG = {
    stateUrl: <?= json_encode(site_url('faculty/loads/state')) ?>,
    swapRequestUrl: <?= json_encode(site_url('faculty/loads/swap/request')) ?>,
    swapRespondUrl: <?= json_encode(site_url('faculty/loads/swap/respond')) ?>,
    swapCancelUrl: <?= json_encode(site_url('faculty/loads/swap/cancel')) ?>,
    changeRequestStoreUrl: <?= json_encode(site_url('faculty/change-requests/store')) ?>,
    changeRequestCancelUrl: <?= json_encode(site_url('faculty/change-requests/cancel')) ?>,
    notificationsUrl: <?= json_encode(site_url('faculty/notifications')) ?>,
    notificationsMarkReadUrl: <?= json_encode(site_url('faculty/notifications/mark-read')) ?>,
    notificationsMarkAllReadUrl: <?= json_encode(site_url('faculty/notifications/mark-all-read')) ?>,
    maxUnits: <?= (int) $max_units ?>,
    noDepartment: <?= json_encode($no_department) ?>,
};

const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

let LOADS = <?= json_encode($state['loads'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let TAKEN = <?= json_encode($state['taken'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let MINE  = <?= json_encode($state['mine'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let UNITS = <?= json_encode($state['units']) ?>;
let SWAPS = <?= json_encode($state['swaps'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let FACULTY = <?= json_encode($state['faculty'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let CHANGE_REQUESTS = <?= json_encode($state['change_requests'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let UNREAD = <?= json_encode($state['unread_notifications']) ?>;

let activeTab = 'available';
let dayFilter = null;   // null = all days
let yearFilter = '';    // '' = all year levels
let sectionFilter = ''; // '' = all sections
let query = '';
let version = '';

// ---------- toast ----------
const toast = document.getElementById('toast');
let toastTimer;
function showToast(msg, kind) {
    toast.textContent = msg;
    toast.className = 'toast show' + (kind ? ' ' + kind : '');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3200);
}

// ---------- confirm dialog ----------
const confirmModal = document.getElementById('confirmModal');
let confirmResolve = null;

function confirmDialog(title, message, okLabel) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmOk').textContent = okLabel;
    confirmModal.classList.add('open');
    return new Promise(resolve => { confirmResolve = resolve; });
}

function settleConfirm(result) {
    if (confirmResolve === null) return;
    confirmModal.classList.remove('open');
    const resolve = confirmResolve;
    confirmResolve = null;
    resolve(result);
}

document.getElementById('confirmOk').addEventListener('click', () => settleConfirm(true));
document.getElementById('confirmCancel').addEventListener('click', () => settleConfirm(false));
confirmModal.addEventListener('click', e => { if (e.target === confirmModal) settleConfirm(false); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') settleConfirm(false); });

// ---------- rendering ----------
function metaRow(icon, text) {
    const row = document.createElement('div');
    row.className = 'tile-meta';
    const iconEl = document.createElement('span');
    iconEl.className = 'material-symbols-outlined';
    iconEl.textContent = icon;
    row.append(iconEl, document.createTextNode(text));
    return row;
}

function loadTile(l) {
    const tile = document.createElement('div');
    tile.className = 'tile acc-' + l.accent;

    const head = document.createElement('div');
    head.className = 'tile-head';
    const code = document.createElement('span');
    code.className = 'tile-code';
    code.textContent = l.code;
    const spec = document.createElement('span');
    spec.className = 'tile-chip';
    spec.textContent = l.spec;
    head.append(code, spec);

    const title = document.createElement('div');
    title.className = 'tile-title';
    title.textContent = l.title;

    tile.append(head, title,
        metaRow('calendar_month', l.days),
        metaRow('schedule', l.time),
        metaRow('location_on', `${l.room} · ${l.units} units`));

    const btn = document.createElement('button');
    btn.type = 'button';

    if (l.state === 'assigned') {
        btn.className = 'tile-btn assigned';
        btn.innerHTML = '<span class="material-symbols-outlined">check_circle</span> Assigned to you';
        btn.disabled = true;

        const swapBtn = document.createElement('button');
        swapBtn.type = 'button';
        if (l.swap_pending) {
            swapBtn.className = 'tile-btn swap';
            swapBtn.innerHTML = '<span class="material-symbols-outlined">hourglass_top</span> Swap pending';
            swapBtn.disabled = true;
        } else {
            swapBtn.className = 'tile-btn swap';
            swapBtn.innerHTML = '<span class="material-symbols-outlined">swap_horiz</span> Request Swap';
            swapBtn.addEventListener('click', () => openSwapModal(l));
        }

        const changeBtn = document.createElement('button');
        changeBtn.type = 'button';
        if (l.change_request_pending) {
            changeBtn.className = 'tile-btn swap';
            changeBtn.innerHTML = '<span class="material-symbols-outlined">hourglass_top</span> Change Requested';
            changeBtn.disabled = true;
        } else {
            changeBtn.className = 'tile-btn decline';
            changeBtn.innerHTML = '<span class="material-symbols-outlined">flag</span> Request Change';
            changeBtn.addEventListener('click', () => openChangeRequestModal(l));
        }

        tile.appendChild(btn);
        tile.appendChild(swapBtn);
        tile.appendChild(changeBtn);
        return tile;
    } else if (l.state === 'taken') {
        tile.classList.add('is-taken');
        // Replace the section chip with a "taken by" chip.
        spec.className = 'taken-chip';
        spec.textContent = 'Taken · ' + l.holder;

        if (l.swap_pending) {
            btn.className = 'tile-btn swap';
            btn.innerHTML = '<span class="material-symbols-outlined">hourglass_top</span> Swap in progress';
            btn.disabled = true;
        } else {
            btn.className = 'tile-btn swap';
            btn.innerHTML = '<span class="material-symbols-outlined">swap_horiz</span> Propose Swap';
            btn.addEventListener('click', () => openProposeModal(l));
        }
    } else if (l.state === 'conflict') {
        btn.className = 'tile-btn conflict';
        btn.textContent = 'Schedule Conflict';
        btn.disabled = true;
        btn.title = 'This load overlaps one of your assigned classes.';
    } else {
        btn.className = 'tile-btn swap';
        btn.innerHTML = '<span class="material-symbols-outlined">hourglass_empty</span> Awaiting Assignment';
        btn.disabled = true;
        btn.title = 'Your chairperson has not assigned this subject to anyone yet.';
    }

    tile.appendChild(btn);
    return tile;
}

const SWAP_STATUS = {
    pending:   { label: 'Pending',            cls: 'pending' },
    accepted:  { label: 'Awaiting approval',  cls: 'awaiting' },
    approved:  { label: 'Approved',           cls: 'approved' },
    declined:  { label: 'Declined',           cls: 'declined' },
    rejected:  { label: 'Rejected by chair',  cls: 'rejected' },
    cancelled: { label: 'Cancelled',          cls: 'cancelled' },
};

function swapTile(s) {
    const tile = document.createElement('div');
    tile.className = 'tile acc-' + (s.offered ? s.offered.accent : 'blue');

    const statusInfo = SWAP_STATUS[s.status] || SWAP_STATUS.cancelled;

    const head = document.createElement('div');
    head.className = 'tile-head';
    const code = document.createElement('span');
    code.className = 'tile-code';
    code.textContent = s.requested ? `${s.offered.code} ⇄ ${s.requested.code}` : s.offered.code;
    const status = document.createElement('span');
    status.className = 'swap-status ' + statusInfo.cls;
    status.textContent = statusInfo.label;
    head.append(code, status);

    const who = document.createElement('div');
    who.className = 'swap-who';
    if (s.direction === 'incoming') {
        who.textContent = s.requested
            ? `${s.requester} offers ${s.offered.code} for your ${s.requested.code}`
            : `${s.requester} wants to hand ${s.offered.code} to you`;
    } else {
        who.textContent = s.requested
            ? `You offered ${s.offered.code} for ${s.target}'s ${s.requested.code}`
            : `You offered ${s.offered.code} to ${s.target}`;
    }

    tile.append(head, who,
        metaRow('calendar_month', `${s.offered.code}: ${s.offered.days} · ${s.offered.time} · ${s.offered.room} · ${s.offered.spec}`));

    if (s.requested) {
        tile.append(metaRow('swap_horiz', `${s.requested.code}: ${s.requested.days} · ${s.requested.time} · ${s.requested.room} · ${s.requested.spec}`));
    }

    if (s.status === 'accepted') {
        tile.append(metaRow('schedule', 'Waiting for the chairperson to approve the transfer.'));
    }

    if (s.direction === 'incoming' && s.status === 'pending') {
        const accept = document.createElement('button');
        accept.type = 'button';
        accept.className = 'tile-btn claim';
        accept.textContent = 'Accept Swap';
        accept.addEventListener('click', () => respondSwap(s, 'accept'));

        const decline = document.createElement('button');
        decline.type = 'button';
        decline.className = 'tile-btn decline';
        decline.textContent = 'Decline';
        decline.addEventListener('click', () => respondSwap(s, 'decline'));

        tile.append(accept, decline);
    } else if (s.direction === 'outgoing' && (s.status === 'pending' || s.status === 'accepted')) {
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'tile-btn swap';
        cancel.textContent = 'Withdraw Request';
        cancel.addEventListener('click', () => cancelSwap(s));
        tile.appendChild(cancel);
    }

    return tile;
}

const CHANGE_STATUS = {
    pending:      { label: 'Pending',            cls: 'pending' },
    under_review: { label: 'Under Review',       cls: 'awaiting' },
    approved:     { label: 'Approved',           cls: 'approved' },
    rejected:     { label: 'Rejected',           cls: 'rejected' },
    cancelled:    { label: 'Cancelled',          cls: 'cancelled' },
};

function changeRequestTile(cr) {
    const tile = document.createElement('div');
    tile.className = 'tile';

    const statusInfo = CHANGE_STATUS[cr.status] || CHANGE_STATUS.cancelled;

    const head = document.createElement('div');
    head.className = 'tile-head';
    const code = document.createElement('span');
    code.className = 'tile-code';
    code.textContent = cr.subject;
    const status = document.createElement('span');
    status.className = 'swap-status ' + statusInfo.cls;
    status.textContent = statusInfo.label;
    head.append(code, status);

    const reason = document.createElement('div');
    reason.className = 'swap-who';
    reason.textContent = cr.reason;

    tile.append(head, reason);

    if (cr.proposed) {
        tile.append(metaRow('lightbulb', 'Proposed: ' + cr.proposed));
    }

    if (cr.status === 'pending' || cr.status === 'under_review') {
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'tile-btn swap';
        cancel.textContent = 'Withdraw Request';
        cancel.addEventListener('click', () => cancelChangeRequest(cr));
        tile.appendChild(cancel);
    }

    return tile;
}

function visibleLoads() {
    const source = activeTab === 'available' ? LOADS.concat(TAKEN) : MINE;
    const q = query.trim().toLowerCase();

    return source.filter(l => {
        if (dayFilter !== null && !l.day_list.includes(dayFilter)) return false;
        if (yearFilter !== '' && l.context.year_level !== yearFilter) return false;
        if (sectionFilter !== '' && l.context.section !== sectionFilter) return false;
        if (q === '') return true;
        return `${l.code} ${l.title} ${l.room} ${l.spec}`.toLowerCase().includes(q);
    });
}

function renderBoard() {
    const grid = document.getElementById('boardGrid');
    const empty = document.getElementById('boardEmpty');
    grid.innerHTML = '';

    document.getElementById('cntAvailable').textContent = LOADS.length;
    document.getElementById('cntMine').textContent = MINE.length;
    document.getElementById('cntSwaps').textContent =
        SWAPS.filter(s => s.direction === 'incoming' && s.status === 'pending').length;
    document.getElementById('cntChanges').textContent =
        CHANGE_REQUESTS.filter(cr => cr.status === 'pending' || cr.status === 'under_review').length;

    // The day/section filters only make sense for load boards.
    document.querySelector('.filter-bar').style.display = (activeTab === 'swaps' || activeTab === 'changes') ? 'none' : '';

    if (activeTab === 'swaps') {
        if (SWAPS.length === 0) {
            empty.classList.add('visible');
            document.getElementById('emptyTitle').textContent = 'No swap requests';
            document.getElementById('emptySub').textContent =
                'Offer one of your assignments to a colleague from the My Assignments tab, or wait for an incoming request.';
        } else {
            empty.classList.remove('visible');
            SWAPS.forEach(s => grid.appendChild(swapTile(s)));
        }
        updateLoadBar();
        return;
    }

    if (activeTab === 'changes') {
        if (CHANGE_REQUESTS.length === 0) {
            empty.classList.add('visible');
            document.getElementById('emptyTitle').textContent = 'No change requests';
            document.getElementById('emptySub').textContent =
                'Request a change from the My Assignments tab if you cannot accept an assignment.';
        } else {
            empty.classList.remove('visible');
            CHANGE_REQUESTS.forEach(cr => grid.appendChild(changeRequestTile(cr)));
        }
        updateLoadBar();
        return;
    }

    const items = visibleLoads();

    const totalUnits = items.reduce((sum, l) => sum + l.units, 0);
    document.getElementById('boardSummary').textContent =
        items.length === 0 ? '' : `${items.length} ${items.length === 1 ? 'load' : 'loads'} · ${totalUnits} units`;

    if (items.length === 0) {
        empty.classList.add('visible');
        if (activeTab === 'available') {
            document.getElementById('emptyTitle').textContent =
                (query || dayFilter !== null) ? 'No loads match your filters' : 'No teaching loads available';
            document.getElementById('emptySub').textContent =
                (query || dayFilter !== null) ? 'Try clearing the search or day filter.'
                    : 'Loads will appear here in real time once a chairperson plots the schedules.';
        } else {
            document.getElementById('emptyTitle').textContent = 'No assignments yet';
            document.getElementById('emptySub').textContent = 'Your chairperson hasn\'t assigned you any subjects yet — check back once schedule plotting starts.';
        }
    } else {
        empty.classList.remove('visible');
        items.forEach(l => grid.appendChild(loadTile(l)));
    }

    updateLoadBar();
}

function updateLoadBar() {
    const pct = Math.min(100, Math.round(UNITS / CONFIG.maxUnits * 100));
    const fill = document.getElementById('loadFill');
    fill.style.width = pct + '%';
    fill.className = 'fill' + (pct >= 100 ? ' full' : pct >= 75 ? ' warn' : '');
    document.getElementById('loadLabel').textContent = `Teaching Load: ${UNITS} / ${CONFIG.maxUnits} units`;
    document.getElementById('loadRemaining').textContent = `${Math.max(0, CONFIG.maxUnits - UNITS)} units remaining`;
}

// ---------- tabs / filters ----------
document.querySelectorAll('.tab[data-tab]').forEach(tab =>
    tab.addEventListener('click', () => {
        activeTab = tab.dataset.tab;
        document.querySelectorAll('.tab[data-tab]').forEach(t =>
            t.classList.toggle('active', t.dataset.tab === activeTab));
        renderBoard();
    }));

const dayFilterBox = document.getElementById('dayFilter');
['All', ...DAY_LABELS].forEach((label, i) => {
    const chip = document.createElement('button');
    chip.type = 'button';
    chip.className = 'chip' + (i === 0 ? ' active' : '');
    chip.textContent = label;
    chip.addEventListener('click', () => {
        dayFilter = i === 0 ? null : i - 1;
        [...dayFilterBox.children].forEach((c, j) => c.classList.toggle('active', j === i));
        renderBoard();
    });
    dayFilterBox.appendChild(chip);
});

document.getElementById('searchInput').addEventListener('input', e => {
    query = e.target.value;
    renderBoard();
});

// Year level / section dropdowns: options follow whatever contexts exist in
// the live data, so new sections appear automatically.
function rebuildContextFilters() {
    const all = LOADS.concat(TAKEN, MINE);

    const fill = (id, values, current, allLabel) => {
        const select = document.getElementById(id);
        select.innerHTML = '';
        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = allLabel;
        select.appendChild(allOption);
        values.forEach(v => {
            const option = document.createElement('option');
            option.value = v;
            option.textContent = v;
            select.appendChild(option);
        });
        select.value = values.includes(current) ? current : '';
        return select.value;
    };

    yearFilter = fill('yearFilter',
        [...new Set(all.map(l => l.context.year_level))].sort(), yearFilter, 'All year levels');
    sectionFilter = fill('sectionFilter',
        [...new Set(all.map(l => l.context.section))].sort(), sectionFilter, 'All sections');
}

document.getElementById('yearFilter').addEventListener('change', e => {
    yearFilter = e.target.value;
    renderBoard();
});
document.getElementById('sectionFilter').addEventListener('change', e => {
    sectionFilter = e.target.value;
    renderBoard();
});

// ---------- swaps ----------
const swapModal = document.getElementById('swapModal');
let swapMode = null; // { type: 'handover', load } | { type: 'exchange', load }

function openSwapModal(l) {
    // Handover: give one of my loads to a chosen colleague.
    if (FACULTY.length === 0) {
        showToast('No other faculty members in your department yet.', 'error');
        return;
    }
    swapMode = { type: 'handover', load: l };
    document.getElementById('swapTitle').textContent = 'Hand over ' + l.code;
    document.getElementById('swapMessage').textContent =
        `Offer ${l.code} — ${l.title} (${l.days}, ${l.time} · ${l.room} · ${l.units} units) to a colleague. ` +
        `They must accept, and the chairperson must approve, before it transfers.`;

    const select = document.getElementById('swapTarget');
    select.innerHTML = '';
    FACULTY.forEach(f => {
        const option = document.createElement('option');
        option.value = f.id;
        option.textContent = f.username;
        select.appendChild(option);
    });

    swapModal.classList.add('open');
}

function openProposeModal(l) {
    // Exchange: offer one of MY loads for a colleague's taken load.
    if (MINE.length === 0) {
        showToast('You need an assigned load of your own to offer in exchange — claim one first.', 'error');
        return;
    }
    const offerable = MINE.filter(m => !m.swap_pending);
    if (offerable.length === 0) {
        showToast('All your loads are already part of open swap requests.', 'error');
        return;
    }

    swapMode = { type: 'exchange', load: l };
    document.getElementById('swapTitle').textContent = `Propose swap for ${l.code}`;
    document.getElementById('swapMessage').textContent =
        `${l.code} — ${l.title} (${l.days}, ${l.time} · ${l.room}) is held by ${l.holder}. ` +
        `Choose which of your loads to offer in exchange. ${l.holder} must accept, ` +
        `and the chairperson must approve, before the loads trade places.`;

    const select = document.getElementById('swapTarget');
    select.innerHTML = '';
    offerable.forEach(m => {
        const option = document.createElement('option');
        option.value = m.assignment_id;
        option.textContent = `${m.code} — ${m.title} (${m.days}, ${m.time} · ${m.units} units)`;
        select.appendChild(option);
    });

    swapModal.classList.add('open');
}

function closeSwapModal() {
    swapMode = null;
    swapModal.classList.remove('open');
}

document.getElementById('swapCancel').addEventListener('click', closeSwapModal);
swapModal.addEventListener('click', e => { if (e.target === swapModal) closeSwapModal(); });

document.getElementById('swapSend').addEventListener('click', async () => {
    if (!swapMode) return;
    const mode = swapMode;
    const selected = document.getElementById('swapTarget');
    const value = Number(selected.value);
    const valueName = selected.selectedOptions[0].textContent;
    closeSwapModal();

    const body = mode.type === 'handover'
        ? { offered_assignment_id: mode.load.assignment_id, target_id: value }
        : { offered_assignment_id: value, requested_assignment_id: mode.load.assignment_id };

    try {
        const response = await fetch(CONFIG.swapRequestUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        const result = await response.json();

        if (response.ok && result.ok) {
            showToast(mode.type === 'handover'
                ? `Swap request for ${mode.load.code} sent to ${valueName}.`
                : `Swap proposed to ${mode.load.holder}: your load for their ${mode.load.code}.`, 'success');
        } else {
            showToast(result.error || 'Could not send the swap request.', 'error');
        }
    } catch (err) {
        showToast('Network error while sending the swap request.', 'error');
    } finally {
        refreshState();
    }
});

async function respondSwap(s, action) {
    const gain = s.offered;
    const give = s.requested;

    const confirmed = await confirmDialog(
        (action === 'accept' ? 'Accept' : 'Decline') + ' swap: ' + gain.code + '?',
        action === 'accept'
            ? `You receive ${gain.code} (${gain.days}, ${gain.time} · ${gain.room})` +
              (give ? ` and give ${give.code} to ${s.requester}.` : ` from ${s.requester}.`) +
              ` The chairperson still has to approve before anything transfers.`
            : `Decline ${s.requester}'s swap request for ${gain.code}?`,
        action === 'accept' ? 'Accept Swap' : 'Decline'
    );
    if (!confirmed) return;

    try {
        const response = await fetch(CONFIG.swapRespondUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ swap_id: s.id, action: action }),
        });
        const result = await response.json();

        if (response.ok && result.ok) {
            showToast(action === 'accept'
                ? `Swap accepted — now waiting for the chairperson's approval.`
                : `Swap request for ${gain.code} declined.`, 'success');
        } else {
            showToast(result.error || 'Could not respond to the swap.', 'error');
        }
    } catch (err) {
        showToast('Network error while responding to the swap.', 'error');
    } finally {
        refreshState();
    }
}

async function cancelSwap(s) {
    const confirmed = await confirmDialog(
        'Withdraw swap request?',
        `Withdraw your offer of ${s.offered.code}${s.requested ? ' for ' + s.requested.code : ''}? Everything stays as it is.`,
        'Withdraw'
    );
    if (!confirmed) return;

    try {
        const response = await fetch(CONFIG.swapCancelUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ swap_id: s.id }),
        });
        const result = await response.json();
        if (response.ok && result.ok) {
            showToast(`Swap request for ${s.offered.code} withdrawn.`, '');
        } else {
            showToast(result.error || 'Could not cancel the swap.', 'error');
        }
    } catch (err) {
        showToast('Network error while cancelling the swap.', 'error');
    } finally {
        refreshState();
    }
}

// ---------- change requests ----------
const changeRequestModal = document.getElementById('changeRequestModal');
let pendingChangeLoad = null;

function openChangeRequestModal(l) {
    pendingChangeLoad = l;
    document.getElementById('crTitle').textContent = 'Request Change: ' + l.code;
    document.getElementById('crMessage').textContent =
        `${l.code} — ${l.title} · ${l.spec} · ${l.days}, ${l.time} · ${l.room}. This goes to your chairperson for review — it does not reassign the subject on its own.`;
    document.getElementById('crReason').value = '';
    document.getElementById('crProposed').value = '';
    changeRequestModal.classList.add('open');
}

function closeChangeRequestModal() {
    pendingChangeLoad = null;
    changeRequestModal.classList.remove('open');
}

document.getElementById('crCancel').addEventListener('click', closeChangeRequestModal);
changeRequestModal.addEventListener('click', e => { if (e.target === changeRequestModal) closeChangeRequestModal(); });

document.getElementById('crSend').addEventListener('click', async () => {
    if (!pendingChangeLoad) return;
    const load = pendingChangeLoad;
    const reason = document.getElementById('crReason').value.trim();
    const proposed = document.getElementById('crProposed').value.trim();

    if (reason.length < 5) {
        showToast('Please explain why you cannot accept this assignment.', 'error');
        return;
    }

    closeChangeRequestModal();

    try {
        const response = await fetch(CONFIG.changeRequestStoreUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ assignment_id: load.assignment_id, reason: reason, proposed_alternative: proposed }),
        });
        const result = await response.json();

        if (response.ok && result.ok) {
            showToast(`Change request for ${load.code} submitted to your chairperson.`, 'success');
        } else {
            showToast(result.error || 'Could not submit the change request.', 'error');
        }
    } catch (err) {
        showToast('Network error while submitting the change request.', 'error');
    } finally {
        refreshState();
    }
});

async function cancelChangeRequest(cr) {
    const confirmed = await confirmDialog(
        'Withdraw change request?',
        `Withdraw your change request for ${cr.subject}? The assignment stays as it is.`,
        'Withdraw'
    );
    if (!confirmed) return;

    try {
        const response = await fetch(CONFIG.changeRequestCancelUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ request_id: cr.id }),
        });
        const result = await response.json();
        if (response.ok && result.ok) {
            showToast(`Change request for ${cr.subject} withdrawn.`, '');
        } else {
            showToast(result.error || 'Could not withdraw the request.', 'error');
        }
    } catch (err) {
        showToast('Network error while withdrawing the request.', 'error');
    } finally {
        refreshState();
    }
}

// ---------- notifications ----------
function updateNotifBadge() {
    const badge = document.getElementById('notifBadge');
    if (UNREAD > 0) {
        badge.textContent = UNREAD > 99 ? '99+' : UNREAD;
        badge.style.display = 'block';
    } else {
        badge.style.display = 'none';
    }
}
updateNotifBadge();

async function loadNotifications() {
    const list = document.getElementById('notifList');
    list.innerHTML = '<p style="padding:16px;font-size:.8rem;color:var(--muted);">Loading…</p>';

    try {
        const response = await fetch(CONFIG.notificationsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await response.json();

        if (!data.notifications || data.notifications.length === 0) {
            list.innerHTML = '<p style="padding:16px;font-size:.8rem;color:var(--muted);">No notifications yet.</p>';
            return;
        }

        list.innerHTML = '';
        data.notifications.forEach(n => {
            const item = document.createElement('div');
            item.style.cssText = 'padding:12px 16px;border-bottom:1px solid var(--border);cursor:pointer;font-size:.8rem;'
                + (n.is_read == 0 ? 'background:rgba(0,64,166,.04);font-weight:600;' : '');
            item.textContent = n.message;
            item.addEventListener('click', async () => {
                await fetch(CONFIG.notificationsMarkReadUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ id: n.id }),
                });
                if (n.link) window.location.href = n.link;
            });
            list.appendChild(item);
        });
    } catch (err) {
        list.innerHTML = '<p style="padding:16px;font-size:.8rem;color:var(--muted);">Could not load notifications.</p>';
    }
}

const notifPanel = document.getElementById('notifPanel');
document.getElementById('notifBtn').addEventListener('click', () => {
    const open = notifPanel.style.display === 'block';
    notifPanel.style.display = open ? 'none' : 'block';
    if (!open) loadNotifications();
});
document.addEventListener('click', e => {
    if (!document.getElementById('notifWrap').contains(e.target)) notifPanel.style.display = 'none';
});
document.getElementById('notifMarkAll').addEventListener('click', async (e) => {
    e.stopPropagation();
    await fetch(CONFIG.notificationsMarkAllReadUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    UNREAD = 0;
    updateNotifBadge();
    loadNotifications();
});

// ---------- real-time (long-polling) ----------
function applyState(data) {
    const before = new Set(LOADS.map(l => l.key));
    const mineKeys = new Set((data.mine || []).map(l => l.key));

    // Toast swap events: new incoming requests and every status change.
    if (Array.isArray(data.swaps)) {
        const prev = new Map(SWAPS.map(s => [s.id, s]));
        data.swaps.forEach(s => {
            const old = prev.get(s.id);
            const code = s.offered ? s.offered.code : '';
            if (!old && s.direction === 'incoming' && s.status === 'pending') {
                showToast(`${s.requester} proposed a swap for ${code} — check Swap Requests.`, 'success');
            } else if (old && s.status !== old.status) {
                const messages = {
                    accepted: s.direction === 'outgoing'
                        ? `${s.target} accepted your swap for ${code} — awaiting chairperson approval.` : null,
                    approved: `Swap for ${code} approved by the chairperson — loads transferred.`,
                    declined: s.direction === 'outgoing' ? `${s.target} declined your swap request for ${code}.` : null,
                    rejected: `The chairperson rejected the swap for ${code}.`,
                    cancelled: s.direction === 'incoming' ? `The swap request for ${code} was withdrawn.` : null,
                };
                if (messages[s.status]) {
                    showToast(messages[s.status], s.status === 'approved' || s.status === 'accepted' ? 'success' : '');
                }
            }
        });
        SWAPS = data.swaps;
    }
    if (Array.isArray(data.faculty)) FACULTY = data.faculty;

    // Toast change-request decisions.
    if (Array.isArray(data.change_requests)) {
        const prevCr = new Map(CHANGE_REQUESTS.map(cr => [cr.id, cr]));
        data.change_requests.forEach(cr => {
            const old = prevCr.get(cr.id);
            if (old && cr.status !== old.status && (cr.status === 'approved' || cr.status === 'rejected')) {
                showToast(`Your change request for ${cr.subject} was ${cr.status}.`, cr.status === 'approved' ? 'success' : '');
            }
        });
        CHANGE_REQUESTS = data.change_requests;
    }

    if (typeof data.unread_notifications === 'number') {
        UNREAD = data.unread_notifications;
        updateNotifBadge();
    }

    LOADS = data.loads || [];
    TAKEN = data.taken || [];
    MINE = data.mine || [];
    UNITS = data.units || 0;
    rebuildContextFilters();

    // Loads that vanished without becoming ours were just assigned to a colleague.
    const after = new Set(LOADS.map(l => l.key));
    before.forEach(key => {
        if (!after.has(key) && !mineKeys.has(key)) {
            const code = key.split('|').pop();
            showToast(`${code} was just assigned to another faculty member.`, '');
        }
    });

    renderBoard();
}

async function refreshState() {
    try {
        const response = await fetch(CONFIG.stateUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) return;
        const data = await response.json();
        version = data.version || '';
        applyState(data);
    } catch (err) { /* next poll will retry */ }
}

async function longPollLoop() {
    for (;;) {
        try {
            const params = new URLSearchParams();
            if (version) params.set('version', version);

            const response = await fetch(`${CONFIG.stateUrl}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error('poll failed');

            const data = await response.json();
            version = data.version || '';
            applyState(data);
        } catch (err) {
            // Offline or server hiccup: back off briefly, then resume.
            await new Promise(resolve => setTimeout(resolve, 3000));
        }
    }
}

rebuildContextFilters();
renderBoard();
longPollLoop();

if (CONFIG.noDepartment) {
    const noDeptModal = document.getElementById('noDeptModal');
    noDeptModal.classList.add('open');
    document.getElementById('noDeptOk').addEventListener('click', () => {
        noDeptModal.classList.remove('open');
    });
}
</script>
</body>
</html>

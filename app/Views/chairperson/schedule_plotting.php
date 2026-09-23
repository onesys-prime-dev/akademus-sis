<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'schedule';
$pageTitle     = 'Schedule Plotting';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = [
    '' => [
        'overview'        => ['label' => 'Dashboard',        'icon' => 'home',           'url' => 'chairperson/overview'],
        'profile'         => ['label' => 'Profile',           'icon' => 'person',          'url' => 'chairperson/profile'],
        'schedule'        => ['label' => 'Schedule Plotting', 'icon' => 'calendar_month',  'url' => 'chairperson/dashboard'],
        'sections'        => ['label' => 'Sections',          'icon' => 'groups',          'url' => 'chairperson/sections'],
        'offerings'       => ['label' => 'Merged Classes',    'icon' => 'merge_type',      'url' => 'chairperson/offerings'],
        'grade-approvals' => ['label' => 'Grade Approvals',   'icon' => 'grade',           'url' => 'chairperson/grade-approvals'],
        'curriculum'      => ['label' => 'Curriculum',        'icon' => 'menu_book',       'url' => 'chairperson/curriculum'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('headerActions') ?>
<div class="right">
    <div class="sync-status" id="syncStatus">
        <span class="sync-dot"></span>
        <span id="syncText">All changes saved</span>
    </div>
    <button type="button" class="btn btn-ghost" id="approvalsBtn">
        Swap Approvals <span class="approval-badge" id="approvalBadge">0</span>
    </button>
    <button type="button" class="btn btn-ghost" id="changeRequestsBtn">
        Change Requests <span class="approval-badge" id="changeRequestBadge">0</span>
    </button>
    <button type="button" class="btn btn-ghost" id="roomOverviewBtn">Room Overview</button>
    <button type="button" class="btn btn-ghost" id="facultyPanelBtn">Faculty</button>
    <div class="user-menu" id="notifWrap" style="position:relative;">
        <button type="button" class="user-trigger" id="notifBtn" title="Notifications" style="padding:8px;">
            <span class="material-symbols-outlined">notifications</span>
            <span class="approval-badge zero" id="notifBadge" style="margin-left:2px;">0</span>
        </button>
        <div class="user-dropdown" id="notifPanel" style="min-width:320px; max-height:400px; overflow-y:auto; padding:0;">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid var(--outline-variant);">
                <strong style="font-size:13px;">Notifications</strong>
                <button type="button" id="notifMarkAll" style="font-size:11.5px;color:var(--primary-container);background:none;border:none;cursor:pointer;font-weight:700;">Mark all read</button>
            </div>
            <div id="notifList"></div>
        </div>
    </div>
    <div class="user-menu" id="userMenu">
        <button type="button" class="user-trigger" id="userMenuBtn">
            <div class="avatar-sm"><?= esc(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
            <div class="user-text">
                <div class="name"><?= esc($user['name']) ?></div>
                <div class="role"><?= esc($user['role']) ?></div>
            </div>
            <span class="material-symbols-outlined chevron">expand_more</span>
        </button>
        <div class="user-dropdown">
            <a href="<?= site_url('chairperson/profile') ?>"><span class="material-symbols-outlined" style="font-size:18px;">settings</span> Settings</a>
            <hr>
            <a href="<?= site_url('logout') ?>" class="danger"><span class="material-symbols-outlined" style="font-size:18px;">logout</span> Logout</a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<style>
        /* This page ships its own notification bell + avatar menu in
           headerActions (id-coupled to its own JS below) — hide the shared
           layout's generic ones here instead of duplicating both. */
        .ac-notif, .ac-user-menu { display: none; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            /* Apple-glass inspired palette — core surfaces/text/primary aliased
               onto the shared design-system tokens (public/assets/css/design-system/tokens.css);
               the extra secondary/tertiary accents below are specific to this
               page's scheduling grid and have no shared equivalent yet. */
            --primary:            var(--color-primary-hover);
            --primary-dark:       #001849;
            --primary-container:  var(--color-primary);
            --on-primary:         #ffffff;
            --on-primary-container: #dae1ff;
            --primary-fixed:      var(--color-primary-soft);

            --warning:            var(--color-warning);
            --warning-dark:       #d99800;
            --secondary:          #7f5700;
            --secondary-container: #ffb204;

            --tertiary:           #832700;
            --tertiary-container: #ffdbcf;

            --error:              var(--color-danger);
            --error-container:    #ffdad6;
            --on-error-container: #93000a;

            --success:            var(--color-success);
            --success-container:  var(--color-success-soft);

            --surface:            var(--color-background);
            --surface-dim:        #eef0f3;
            --surface-container-lowest: var(--color-surface);
            --surface-container-low:    var(--color-surface-secondary);
            --surface-container:        #eeeef1;
            --surface-container-high:   var(--color-surface-sunken);

            --on-surface:         var(--color-text-primary);
            --on-surface-variant: var(--color-text-secondary);
            --outline:            var(--color-text-tertiary);
            --outline-variant:    var(--color-border);

            --radius-sm: var(--radius-md);
            --radius-md: var(--radius-lg);
            --radius-lg: var(--radius-xl);
        }

        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: var(--surface);
            color: var(--on-surface);
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            font-size: 20px;
            line-height: 1;
            vertical-align: middle;
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--outline-variant); border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--outline); }

        /* ===== headerActions controls (sync status, approvals, notif bell,
           legacy avatar menu) — relocated into the shared ac-topbar's
           headerActions slot; the old .topbar/.side-drawer chrome that used
           to hold them is gone. .right lays them out in a row the way
           .topbar .right used to. .user-menu/.user-trigger/.user-dropdown/
           .avatar-sm still style the page's own notification bell and its
           legacy avatar dropdown (kept only because this page's script
           below unconditionally references #userMenu — see notifBtn click
           handler — so removing that markup would throw and break the
           notification bell). ===== */
        .right { display: flex; align-items: center; gap: 16px; }

        .user-menu { position: relative; }

        .user-trigger {
            display: flex;
            align-items: center;
            gap: 10px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 8px 4px 4px;
            border-radius: var(--radius-sm);
            font-family: inherit;
            transition: background .15s;
        }

        .user-trigger:hover { background: var(--surface-container-high); }

        .avatar-sm {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--primary-container);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
            flex: 0 0 auto;
        }

        .user-trigger .user-text { text-align: left; }
        .user-trigger .name { font-weight: 700; font-size: 13px; color: var(--on-surface); }
        .user-trigger .role { font-size: 11.5px; color: var(--on-surface-variant); }
        .user-trigger .chevron { font-size: 18px; color: var(--on-surface-variant); transition: transform .15s; }
        .user-menu.open .chevron { transform: rotate(180deg); }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 190px;
            background: var(--surface-container-lowest);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-md);
            padding: 6px;
            display: none;
            z-index: 50;
        }

        .user-menu.open .user-dropdown { display: block; }

        .user-dropdown a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: 8px;
            color: var(--on-surface);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .user-dropdown a:hover { background: var(--surface-container-high); }
        .user-dropdown a.danger { color: var(--error); }
        .user-dropdown a.danger:hover { background: #fde8e8; }
        .user-dropdown hr { border: none; border-top: 1px solid var(--outline-variant); margin: 4px 0; }

        .btn {
            border: none;
            border-radius: var(--radius-sm);
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: box-shadow .15s, background .15s;
        }

        .btn-primary { background: var(--primary-container); color: #fff; box-shadow: var(--shadow-sm); }
        .btn-primary:hover { background: var(--primary); box-shadow: var(--shadow-md); }
        .btn-primary:disabled { background: var(--primary-fixed); color: var(--primary); box-shadow: none; cursor: default; }
        .btn-ghost { background: var(--surface-container-lowest); color: var(--primary-container); border: 1px solid var(--outline-variant); }
        .btn-ghost:hover { background: var(--surface-container-low); border-color: var(--primary-container); }
        .approval-badge {
            display: inline-block;
            background: var(--error);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            border-radius: 999px;
            min-width: 17px;
            padding: 2px 5px;
            text-align: center;
            margin-left: 3px;
        }

        .approval-badge.zero { background: var(--outline-variant); }

        .approvals-list {
            max-height: 340px;
            overflow-y: auto;
            margin-bottom: 14px;
        }

        .approval-item {
            border: 1px solid var(--outline-variant);
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 8px;
        }

        .approval-item .ap-who { font-size: 12px; font-weight: 600; margin-bottom: 4px; }
        .approval-item .ap-loads { font-size: 12px; color: var(--on-surface-variant); margin-bottom: 8px; }
        .approval-item .ap-actions { display: flex; gap: 8px; justify-content: flex-end; }

        .approvals-empty {
            padding: 24px 12px;
            text-align: center;
            font-size: 12.5px;
            color: var(--on-surface-variant);
        }

        .btn-danger { background: var(--error); color: #fff; }
        .btn-danger:hover { background: #b91c1c; }

        /* Confirmation modal stacks above every other modal */
        .modal-backdrop.confirm-top { z-index: 70; }
        .confirm-modal { width: 400px; }

        /* Google-Docs-style sync indicator */
        .sync-status {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            color: var(--on-surface-variant);
        }

        .sync-status .sync-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--success);
        }

        .sync-status.saving .sync-dot { background: var(--warning); }
        .sync-status.error .sync-dot { background: var(--error); }
        .sync-status.error { color: var(--error); }

        /* ===== Filters ===== */
        .filters {
            display: flex;
            gap: 12px;
            padding: 14px 24px;
            background: var(--surface-container-lowest);
            flex-wrap: wrap;
        }

        .filters label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--on-surface-variant);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: .02em;
        }

        .filters select {
            padding: 7px 10px;
            border: 1px solid var(--outline-variant);
            border-radius: var(--radius-sm);
            background: var(--surface-container-low);
            min-width: 160px;
            font-family: inherit;
            color: var(--on-surface);
        }

        .filters select:focus { outline: 2px solid var(--primary-container); outline-offset: 1px; }

        .readonly-banner {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: var(--tertiary-container);
            border-bottom: 1px solid var(--outline-variant);
            color: var(--warning);
            font-size: 13px;
        }

        .readonly-banner .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--warning);
            flex-shrink: 0;
        }

        /* ===== Layout ===== */
        .layout {
            display: grid;
            grid-template-columns: 280px 1fr 300px;
            gap: 16px;
            padding: 16px 24px;
            align-items: start;
        }

        .panel {
            background: var(--surface-container-lowest);
            border: 1px solid var(--outline-variant);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: 14px;
        }

        .panel h2 { font-size: 14px; font-weight: 700; margin-bottom: 10px; color: var(--on-surface); }

        /* ===== Subjects rail ===== */
        .subject {
            border: 1px solid var(--outline-variant);
            border-radius: var(--radius-sm);
            padding: 10px 12px 10px 14px;
            margin-bottom: 8px;
            background: var(--surface-container-lowest);
            cursor: grab;
            user-select: none;
            position: relative;
            overflow: hidden;
            transition: box-shadow .15s, transform .15s, opacity .15s;
        }

        .subject::before {
            content: "";
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background: var(--outline);
        }

        .subject:active { cursor: grabbing; }
        .subject:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }
        .subject.dragging { opacity: .4; }

        .subject .row1 {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
        }

        .subject .code {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            background: var(--surface-container-low);
        }

        .subject .units { color: var(--on-surface-variant); font-weight: 500; }
        .subject .title { font-weight: 700; margin: 5px 0 2px; color: var(--on-surface); }
        .subject .meta { font-size: 12px; color: var(--on-surface-variant); }

        /* A subject leaves the rail once it reaches its max meetings */
        .subject.is-plotted { display: none; }

        .subject .plot-count {
            font-size: 10px;
            color: var(--success);
            font-weight: 600;
            margin-top: 2px;
        }

        .subject.blue::before   { background: #3b82f6; } .subject.blue .code   { color: #3b82f6; }
        .subject.purple::before { background: #8b5cf6; } .subject.purple .code { color: #8b5cf6; }
        .subject.cyan::before   { background: #06b6d4; } .subject.cyan .code   { color: #06b6d4; }
        .subject.red::before    { background: #ef4444; } .subject.red .code    { color: #ef4444; }
        .subject.green::before  { background: #22c55e; } .subject.green .code  { color: #22c55e; }
        .subject.yellow::before { background: #eab308; } .subject.yellow .code { color: #eab308; }

        .plotted-units {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--outline-variant);
            font-size: 13px;
            display: flex;
            justify-content: space-between;
        }

        .plotted-units b { font-size: 15px; }

        .rail-hint { font-size: 11px; color: var(--outline); margin-bottom: 10px; }

        .rail-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .rail-head h2 { margin-bottom: 0; }

        .rail-btn {
            border: 1px dashed #93c5fd;
            background: #eff6ff;
            color: var(--primary);
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            white-space: nowrap;
        }

        .rail-btn:hover { background: #dbeafe; }


        /* Lab subjects: Saturday-only badge */
        .lab-badge {
            display: inline-block;
            background: var(--warning);
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 5px;
            margin-left: 4px;
            vertical-align: 1px;
        }

        /* ===== Weekly grid ===== */
        .schedule .panel-scroll { overflow-x: auto; }

        .grid-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .grid-toolbar h2 { margin-bottom: 0; }

        .class-count {
            font-size: 11px;
            font-weight: 400;
            color: var(--on-surface-variant);
            margin-left: 6px;
        }

        .room-legend {
            display: flex;
            gap: 12px;
            font-size: 11px;
            color: var(--on-surface-variant);
            flex-wrap: wrap;
        }

        .room-legend span { display: flex; align-items: center; gap: 5px; }

        .room-legend i {
            width: 10px;
            height: 10px;
            border-radius: 3px;
        }

        .room-legend .lg-green  { background: var(--success); }
        .room-legend .lg-yellow { background: var(--warning); }
        .room-legend .lg-red    { background: var(--error); }
        .room-legend .lg-grey   { background: var(--outline); }

        .grid {
            display: grid;
            grid-template-columns: 70px repeat(<?= count($week) ?>, minmax(120px, 1fr));
            min-width: 800px;
        }

        .grid .head {
            text-align: center;
            padding: 10px 4px;
            border-bottom: 1px solid var(--outline-variant);
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--on-surface-variant);
            background: var(--surface-container-lowest);
        }

        .grid .time {
            font-size: 11px;
            color: var(--on-surface-variant);
            text-align: right;
            padding: 2px 8px 0 0;
            border-right: 1px solid var(--outline-variant);
        }

        .day-col {
            position: relative;
            border-right: 1px solid var(--surface-dim);
        }

        .slot { height: 28px; border-bottom: 1px solid var(--surface-dim); }

        .block {
            position: absolute;
            left: 3px;
            right: 3px;
            border-radius: var(--radius-sm);
            border-left: 4px solid;
            box-shadow: var(--shadow-sm);
            padding: 4px 18px 4px 6px;
            font-size: 11px;
            overflow: hidden;
            transition: box-shadow .15s;
        }

        .block:hover { box-shadow: var(--shadow-md); }

        .block .code  { font-weight: 800; }
        .block .title { font-weight: 600; }
        .block .meta  { color: var(--on-surface-variant); }
        .block .meta.prof { color: var(--on-surface); font-weight: 600; }

        /* Keep narrow (lane-sharing) blocks readable: one line + ellipsis,
           full details in the hover tooltip. */
        .block .code, .block .title, .block .meta, .block .section-tag {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .block .remove {
            position: absolute;
            top: 2px;
            right: 4px;
            border: none;
            background: none;
            color: var(--on-surface-variant);
            font-size: 13px;
            line-height: 1;
            cursor: pointer;
            padding: 2px;
        }

        .block .remove:hover { color: #ef4444; }

        /* Own blocks always sit above ghosts so their controls stay clickable */
        .block { z-index: 2; }

        /* Ghost blocks: classes plotted for other sections */
        .block.ghost {
            background: var(--surface-dim);
            border-left-color: var(--outline);
            border: 1px dashed var(--outline-variant);
            border-left: 4px solid var(--outline);
            color: var(--on-surface-variant);
            padding-right: 6px;
            z-index: 1;
        }

        .block.ghost .code, .block.ghost .title { color: var(--on-surface-variant); }
        .block.ghost .meta { color: var(--outline); }

        .block.ghost .section-tag {
            display: inline-block;
            background: var(--outline-variant);
            color: #4b5563;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 999px;
            margin-bottom: 2px;
        }

        .block.blue   { background: #dbeafe; border-left-color: #3b82f6; }
        .block.green  { background: #dcfce7; border-left-color: #22c55e; }
        .block.purple { background: #ede9fe; border-left-color: #8b5cf6; }
        .block.yellow { background: #fef9c3; border-left-color: #eab308; }
        .block.red    { background: #fee2e2; border-left-color: #ef4444; }
        .block.cyan   { background: #cffafe; border-left-color: #06b6d4; }

        /* Drop preview ghost */
        .drop-preview {
            position: absolute;
            left: 3px;
            right: 3px;
            border-radius: 6px;
            border: 2px dashed var(--primary-container);
            background: rgba(37, 99, 235, .08);
            pointer-events: none;
            z-index: 5;
        }

        .drop-preview.invalid {
            border-color: #ef4444;
            background: rgba(239, 68, 68, .08);
        }

        /* ===== AI assistant rail ===== */
        .context-card {
            border: 1px solid var(--outline-variant);
            background: var(--surface-container-lowest);
            border-left: 4px solid var(--primary-container);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-sm);
            padding: 10px 12px;
            margin-bottom: 10px;
        }

        .context-card .context-title {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--primary-container);
            font-weight: 700;
            margin-bottom: 4px;
        }

        .context-card .context-line { font-size: 12px; color: var(--on-surface); }

        .legend {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            font-size: 11px;
            color: var(--on-surface-variant);
            margin-bottom: 10px;
        }

        .legend-swatch {
            flex: 0 0 auto;
            width: 14px;
            height: 14px;
            margin-top: 1px;
            border: 1px dashed var(--outline-variant);
            border-left: 3px solid var(--outline);
            background: var(--surface-dim);
            border-radius: 3px;
        }

        .conflict {
            border: 1px solid var(--outline-variant);
            background: var(--surface-container-lowest);
            border-left: 4px solid var(--error);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-sm);
            padding: 10px 12px;
            margin-bottom: 10px;
        }

        .conflict .tag {
            display: inline-block;
            background: var(--error);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            margin-bottom: 6px;
        }

        .conflict .when { font-size: 12px; color: var(--on-surface-variant); margin-bottom: 6px; }

        .conflict .class-card {
            background: var(--surface-container-low);
            border: 1px solid var(--outline-variant);
            border-radius: 6px;
            padding: 6px 8px;
            margin-bottom: 6px;
            font-size: 12px;
        }

        .conflict .class-card .code { font-weight: 700; }
        .conflict .class-card .meta { color: var(--on-surface-variant); }

        .suggestion {
            border: 1px solid var(--outline-variant);
            background: var(--surface-container-lowest);
            border-left: 4px solid var(--warning);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-sm);
            padding: 10px 12px;
        }

        .suggestion .impact {
            display: inline-block;
            background: var(--warning);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            margin-bottom: 6px;
        }

        .suggestion .title { font-weight: 700; margin-bottom: 4px; }
        .suggestion .body { font-size: 12px; color: var(--on-surface-variant); margin-bottom: 6px; }
        .suggestion a { font-size: 12px; color: var(--primary-container); font-weight: 600; }

        /* ===== Room assignment modal ===== */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, .5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 50;
        }

        .modal-backdrop.open { display: flex; }

        .modal {
            background: #fff;
            border-radius: 12px;
            width: 440px;
            max-width: calc(100vw - 32px);
            padding: 18px 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,.25);
        }

        .modal h3 { font-size: 16px; margin-bottom: 4px; }

        .modal .sub { font-size: 12px; color: var(--on-surface-variant); margin-bottom: 14px; }

        .modal .modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .expand-btn {
            border: 1px solid var(--outline-variant);
            background: #fff;
            color: #374151;
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 12px;
            font-family: inherit;
            cursor: pointer;
            white-space: nowrap;
        }

        .expand-btn:hover { border-color: var(--primary-container); color: var(--primary-container); }

        .room-layout { display: flex; gap: 14px; }

        /* Floor sidebar: only shown in the expanded modal */
        .floor-rail { display: none; }

        .room-scroll {
            flex: 1;
            min-width: 0;
            max-height: 300px;
            overflow-y: auto;
            padding-right: 2px;
        }

        .floor-heading {
            grid-column: 1 / -1;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--on-surface-variant);
            padding-top: 4px;
        }

        .floor-heading.recommended { color: var(--primary-container); }

        .lab-room-badge {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
            padding: 2px 6px;
            border-radius: 999px;
            background: rgba(0,64,166,.1);
            color: var(--primary-container);
            margin-left: 6px;
        }

        .room-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
        }

        /* ----- Expanded (large-print) mode ----- */
        .room-modal.expanded {
            width: min(940px, 94vw);
        }

        .room-modal.expanded .room-layout { margin-top: 4px; }

        .room-modal.expanded .floor-rail {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 0 0 150px;
        }

        .floor-btn {
            text-align: left;
            border: 1px solid var(--outline-variant);
            background: #fff;
            color: #374151;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            font-family: inherit;
            cursor: pointer;
        }

        .floor-btn:hover { border-color: #93c5fd; }

        .floor-btn.active {
            background: var(--primary-container);
            border-color: var(--primary-container);
            color: #fff;
            font-weight: 600;
        }

        .room-modal.expanded .room-scroll { max-height: min(56vh, 520px); }

        .room-modal.expanded .room-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .room-modal.expanded .room-card { padding: 16px 14px; }
        .room-modal.expanded .room-card .room-name { font-size: 17px; }
        .room-modal.expanded .room-card .room-status { font-size: 14px; margin-top: 5px; }
        .room-modal.expanded .room-card .room-dot { width: 12px; height: 12px; }
        .room-modal.expanded .floor-heading { font-size: 13px; }

        .room-card {
            border-radius: 8px;
            padding: 10px;
            cursor: pointer;
            text-align: left;
            font-family: inherit;
        }

        .room-card .room-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .room-card .room-name { font-weight: 700; font-size: 13px; }

        .room-card .room-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
        }

        .room-card .room-status { font-size: 11px; font-weight: 600; margin-top: 2px; }

        .room-card:hover:not(:disabled) { box-shadow: 0 2px 8px rgba(0,0,0,.12); }

        /* Room status: green available / yellow high utilization /
           red near full / grey occupied (disabled) */
        .room-card.green  { border: 1px solid #86efac; background: #f0fdf4; }
        .room-card.green .room-status { color: var(--success); }
        .room-card.green .room-dot { background: var(--success); }

        .room-card.yellow { border: 1px solid #fcd34d; background: #fffbeb; }
        .room-card.yellow .room-status { color: var(--warning); }
        .room-card.yellow .room-dot { background: var(--warning); }

        .room-card.red    { border: 1px solid #fca5a5; background: #fef2f2; }
        .room-card.red .room-status { color: var(--error); }
        .room-card.red .room-dot { background: var(--error); }

        .room-card:disabled {
            border: 1px solid var(--outline-variant);
            background: var(--surface-dim);
            cursor: not-allowed;
        }

        .room-card:disabled .room-name { color: var(--outline); }
        .room-card:disabled .room-status { color: var(--outline); }
        .room-card:disabled .room-dot { background: var(--outline); }

        /* Faculty assignment modal */
        .fc-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        @media (max-width: 640px) {
            .fc-grid { grid-template-columns: 1fr; }
        }
        .faculty-card {
            width: 100%;
            text-align: left;
            border: 1px solid var(--outline-variant);
            border-radius: 10px;
            overflow: hidden;
            background: var(--surface);
            display: flex;
            flex-direction: column;
        }
        .faculty-card:hover:not(.fc-disabled) { box-shadow: 0 2px 8px rgba(0,0,0,.12); }
        .faculty-card.fc-disabled { opacity: .6; }
        .faculty-card .fc-band {
            padding: 12px 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border-bottom: 1px solid var(--outline-variant);
        }
        .faculty-card .fc-band.preferred { background: #f0fdf4; }
        .faculty-card .fc-band.mismatch { background: #fefce8; }
        .faculty-card .fc-band.unavailable,
        .faculty-card .fc-band.conflict { background: #fef2f2; }
        .faculty-card .fc-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: var(--primary-container); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 14px; flex: 0 0 auto;
        }
        .faculty-card .fc-head-text { min-width: 0; }
        .faculty-card .fc-name { font-weight: 700; font-size: 13px; }
        .faculty-card .fc-name .fc-emp { font-weight: 400; color: var(--on-surface-variant); }
        .faculty-card .fc-body { padding: 10px 14px 12px; display: flex; flex-direction: column; flex: 1; }
        .faculty-card .fc-meta { font-size: 11px; color: var(--on-surface-variant); margin-top: 4px; }
        .faculty-card .fc-actions { display: flex; gap: 8px; margin-top: 10px; }
        .faculty-card .fc-btn {
            flex: 1; padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;
            cursor: pointer; text-align: center; border: 1px solid var(--outline-variant); background: var(--surface);
        }
        .faculty-card .fc-btn:hover { background: var(--surface-container-high); }
        .faculty-card .fc-btn.fc-btn-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
        .faculty-card .fc-btn.fc-btn-primary:hover { filter: brightness(1.08); }
        .faculty-card .fc-btn:disabled { cursor: not-allowed; opacity: .5; }
        .faculty-card .fc-tag {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 999px; margin-top: 4px;
        }
        .faculty-card .fc-tag.preferred { background: #dcfce7; color: #166534; }
        .faculty-card .fc-tag.willing { background: #fef9c3; color: #854d0e; }
        .faculty-card .fc-tag.qualified { background: #e0e7ff; color: #3730a3; }
        .faculty-card .fc-tag.conflict { background: #fee2e2; color: #991b1b; }
        .faculty-card .fc-tag.unavailable { background: #fee2e2; color: #991b1b; }
        .faculty-card .fc-tag.mismatch { background: #fef9c3; color: #854d0e; }
        .fc-avail-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .fc-avail-table th, .fc-avail-table td { text-align: left; padding: 3px 6px; border-bottom: 1px solid var(--outline-variant); }
        .fc-avail-table th { color: var(--on-surface-variant); font-weight: 600; }

        /* Soft warnings shown inside the modal (non-blocking) */
        .modal-warning {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 9px 11px;
            font-size: 12px;
            color: #92400e;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .modal-warning::before { content: "⚠"; }

        .modal .note { font-size: 11px; color: var(--outline); margin-bottom: 12px; }

        .modal .actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* ===== Room overview modal ===== */
        .overview-modal { width: 480px; }

        .ov-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--on-surface-variant);
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .day-chips {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .day-chip {
            padding: 6px 12px;
            border-radius: 999px;
            border: 1px solid var(--outline-variant);
            background: #fff;
            color: #374151;
            font-size: 12px;
            font-family: inherit;
            cursor: pointer;
        }

        .day-chip:hover { border-color: #93c5fd; }

        .day-chip.active {
            background: var(--primary-container);
            border-color: var(--primary-container);
            color: #fff;
            font-weight: 600;
        }

        .overview-rooms {
            grid-template-columns: repeat(3, 1fr);
            margin-bottom: 14px;
            max-height: 250px;
            overflow-y: auto;
        }

        .room-card.selected {
            outline: 2px solid var(--primary-container);
            outline-offset: 1px;
        }

        .overview-list {
            max-height: 340px;
            overflow-y: auto;
            border: 1px solid var(--outline-variant);
            border-radius: 8px;
            margin-bottom: 14px;
        }

        .overview-row {
            display: flex;
            gap: 12px;
            padding: 9px 12px;
            border-bottom: 1px solid var(--surface-dim);
            align-items: flex-start;
        }

        .overview-row:last-child { border-bottom: none; }

        .overview-row .ov-time {
            flex: 0 0 132px;
            font-size: 12px;
            font-weight: 700;
            color: var(--on-surface);
        }

        .overview-row .ov-details { min-width: 0; font-size: 12px; }
        .overview-row .ov-details .code { font-weight: 700; }
        .overview-row .ov-details .meta { color: var(--on-surface-variant); }

        .overview-row .ov-section {
            margin-left: auto;
            flex: 0 0 auto;
            background: #eff6ff;
            color: var(--primary-container);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            white-space: nowrap;
        }

        .overview-row.free {
            background: #f0fdf4;
        }

        .overview-row.free .ov-time { color: var(--success); }
        .overview-row.free .ov-details { color: var(--success); font-weight: 600; }

        .overview-empty {
            padding: 24px 12px;
            text-align: center;
            font-size: 12.5px;
            color: var(--on-surface-variant);
        }

        /* ===== Toast ===== */
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(80px);
            background: var(--on-surface);
            color: #fff;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 13px;
            opacity: 0;
            transition: transform .25s, opacity .25s;
            z-index: 60;
            pointer-events: none;
        }

        .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .toast.error { background: var(--error); }
        .toast.success { background: var(--success); }

        /* ===== Filters bar & readonly banner: these used to sit inside a
           .header-card/.app-main wrapper that paired with the old
           side-drawer/topbar shell (both now removed — the shared layout's
           .ac-sidebar/.ac-topbar/.ac-content replace them). The
           border/radius/shadow they used to borrow from that wrapper are
           added directly here so they still read as cards. ===== */
        .filters { border: 1px solid var(--outline-variant); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 16px; }
        .readonly-banner { border: 1px solid var(--outline-variant); border-radius: var(--radius-md); margin-bottom: 16px; }

        /* One scroll container per panel body, not one per child — a child
           per element made every subject card its own tiny scroll box,
           clipping wrapped two-line titles. */
        .panel-scroll > *:last-child { margin-bottom: 0; }
    </style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

    <form class="filters" method="get" action="<?= site_url('chairperson/dashboard') ?>" id="contextForm">
        <div>
            <label>Academic Year</label>
            <select name="ay_label" class="context-select">
                <?php foreach ($filters['academic_year_labels'] as $label): ?>
                    <option <?= $label === $selected['ay_label'] ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Semester</label>
            <select name="semester" class="context-select">
                <?php foreach ($filters['semester'] as $option): ?>
                    <option <?= $option === $selected['semester'] ? 'selected' : '' ?>><?= esc($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        $filterLabels = [
            'program'    => 'Program',
            'year_level' => 'Year Level',
            'section'    => 'Section',
        ];
        ?>
        <?php foreach ($filterLabels as $name => $label): ?>
            <div>
                <label><?= esc($label) ?></label>
                <select name="<?= esc($name, 'attr') ?>" class="context-select">
                    <?php foreach ($filters[$name] as $option): ?>
                        <option <?= $option === $selected[$name] ? 'selected' : '' ?>><?= esc($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>
    </form>

    <?php if (! $is_active_year): ?>
        <div class="readonly-banner">
            <span class="dot"></span>
            <span><b><?= esc($selected['academic_year']) ?></b> is not the active term — this view is read-only. Switch Admin &gt; Academic Years to make it active, or select the active term above to make changes.</span>
        </div>
    <?php endif ?>

    <main class="layout">

        <!-- Left rail: curriculum subjects (draggable) -->
        <aside class="panel">
            <div class="rail-head">
                <h2>Curriculum Subjects</h2>
                <a class="rail-btn" style="display:inline-block; text-decoration:none;" href="<?= site_url('chairperson/curriculum') . '?' . http_build_query($selected) ?>">
                    <?= $preset_codes === null ? 'Create Curriculum' : 'Edit Curriculum' ?>
                </a>
            </div>
            <div class="rail-hint">
                <?php if ($preset_codes === null): ?>
                    No curriculum set for <?= esc($selected['year_level']) ?> yet — showing the full
                    subject catalog. Create one so each year level loads its own subjects.
                <?php else: ?>
                    <?= esc($selected['year_level']) ?> curriculum &middot; <?= count($subjects) ?> subjects
                    (all sections). Drag a subject onto the grid to plot it.
                <?php endif; ?>
            </div>

            <div class="panel-scroll">
                <?php foreach ($subjects as $subject): ?>
                    <div class="subject <?= esc($subject['color'], 'attr') ?>"
                         draggable="<?= $is_active_year ? 'true' : 'false' ?>"
                         data-code="<?= esc($subject['code'], 'attr') ?>"
                         <?= $is_active_year ? '' : 'style="opacity:.5; cursor:not-allowed;" title="Read-only — not the active term"' ?>>
                        <div class="row1">
                            <span>
                                <span class="code"><?= esc($subject['code']) ?></span>
                                <?php if ($subject['is_lab']): ?><span class="lab-badge">LABORATORY</span><?php endif; ?>
                            </span>
                            <span class="units"><?= esc($subject['units']) ?></span>
                        </div>
                        <div class="title"><?= esc($subject['title']) ?></div>
                        <div class="meta"><?= esc($subject['hours']) ?></div>
                        <div class="plot-count"></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="plotted-units">
                <span>Plotted Units</span>
                <b id="plottedUnits">0</b>
            </div>
        </aside>

        <!-- Center: weekly schedule grid -->
        <section class="panel schedule">
            <div class="grid-toolbar">
                <h2>Weekly Plotting Grid
                    <span class="class-count" id="classCount"></span>
                </h2>
                <div class="room-legend">
                    <span><i class="lg-green"></i> Available</span>
                    <span><i class="lg-yellow"></i> High utilization</span>
                    <span><i class="lg-red"></i> Near full</span>
                    <span><i class="lg-grey"></i> Occupied</span>
                </div>
            </div>
            <div class="panel-scroll">
                <div class="grid">
                    <div class="head"></div>
                    <?php foreach ($week as $day): ?>
                        <div class="head"><?= esc($day['day']) ?></div>
                    <?php endforeach; ?>

                    <!-- Time labels column -->
                    <div id="timeCol"></div>

                    <!-- Day columns (blocks are rendered by JS) -->
                    <?php foreach ($week as $dayIndex => $day): ?>
                        <div class="day-col" data-day="<?= $dayIndex ?>"></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Right rail: AI scheduling assistant -->
        <aside class="panel">
            <h2>AI Scheduling Assistant</h2>

            <div class="panel-scroll">
                <div class="context-card">
                    <div class="context-title">Plotting for</div>
                    <div class="context-line"><?= esc($selected['section']) ?> &middot; <?= esc($selected['year_level']) ?></div>
                    <div class="context-line"><?= esc($selected['program']) ?></div>
                    <div class="context-line"><?= esc($selected['academic_year']) ?></div>
                </div>

                <div class="legend">
                    <span class="legend-swatch"></span>
                    Grey blocks belong to other sections. Their rooms and professors
                    are unavailable during those times.
                </div>

                <div class="suggestion">
                    <span class="impact"><?= esc($suggestion['impact']) ?></span>
                    <div class="title"><?= esc($suggestion['title']) ?></div>
                    <div class="body"><?= esc($suggestion['body']) ?></div>
                    <a href="#"><?= esc($suggestion['alt']) ?></a>
                </div>
            </div>
        </aside>

    </main>

    <!-- Room assignment modal -->
    <div class="modal-backdrop" id="roomModal" role="dialog" aria-modal="true" aria-label="Assign a room">
        <div class="modal room-modal" id="roomModalBox">
            <div class="modal-head">
                <h3>Assign a room</h3>
                <button type="button" class="expand-btn" id="roomModalExpand">&#x26F6; Expand</button>
            </div>
            <div class="sub" id="roomModalSub"></div>
            <div id="roomModalWarnings"></div>

            <div class="room-layout">
                <div class="floor-rail" id="floorRail"></div>
                <div class="room-scroll">
                    <div class="room-grid" id="roomGrid"></div>
                </div>
            </div>

            <div class="note">Occupied rooms are disabled. Room availability reflects the schedule in real time.</div>
            <div class="actions">
                <button type="button" class="btn btn-ghost" id="roomModalCancel">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Faculty assignment modal -->
    <div class="modal-backdrop" id="facultyModal" role="dialog" aria-modal="true" aria-label="Assign faculty">
        <div class="modal room-modal" style="width:800px; max-width:94vw;">
            <div class="modal-head">
                <h3>Assign faculty</h3>
            </div>
            <div class="sub" id="facultyModalSub"></div>
            <div id="facultyModalWarnings"></div>

            <div style="position:relative; margin:10px 0 12px;">
                <input type="text" id="facultyModalSearch" placeholder="Search faculty by name…"
                    style="width:100%; padding:9px 12px; border:1px solid var(--outline-variant); border-radius:8px; font-size:13px; background:var(--surface);">
            </div>

            <div class="room-scroll" style="max-height:52vh;">
                <div id="facultyModalList" class="fc-grid"></div>
            </div>

            <div class="note"><span style="color:#166534; font-weight:700;">Preferred</span> &middot; <span style="color:#854d0e; font-weight:700;">Available but not preferred</span> &middot; <span style="color:#991b1b; font-weight:700;">Unavailable</span> (overridable, with confirmation) or already teaching another class at this time (not overridable). You can also plot the subject without assigning faculty yet.</div>
            <div class="actions">
                <button type="button" class="btn btn-ghost" id="facultyModalSkip">Skip — Assign Later</button>
                <button type="button" class="btn btn-ghost" id="facultyModalCancel">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Swap approvals modal -->
    <div class="modal-backdrop" id="approvalsModal" role="dialog" aria-modal="true" aria-label="Swap Approvals">
        <div class="modal">
            <h3>Swap Approvals</h3>
            <div class="sub">Faculty-agreed swaps in your department, waiting for your decision.
                Loads only transfer once you approve.</div>
            <div class="approvals-list" id="approvalsList"></div>
            <div class="actions">
                <button type="button" class="btn btn-ghost" id="approvalsClose">Close</button>
            </div>
        </div>
    </div>

    <!-- Change requests modal -->
    <div class="modal-backdrop" id="changeRequestsModal" role="dialog" aria-modal="true" aria-label="Change Requests">
        <div class="modal">
            <h3>Change Requests</h3>
            <div class="sub">Faculty members who can't accept an official assignment, waiting for your review.
                Deciding does not reassign the subject — replot it yourself via the grid.</div>
            <div class="approvals-list" id="changeRequestsList"></div>
            <div class="actions">
                <button type="button" class="btn btn-ghost" id="changeRequestsClose">Close</button>
            </div>
        </div>
    </div>


    <!-- Room overview modal -->
    <div class="modal-backdrop" id="overviewModal" role="dialog" aria-modal="true" aria-label="Room Overview">
        <div class="modal overview-modal">
            <h3>Room Overview</h3>
            <div class="sub">Every class plotted in a room for a day — all programs, year levels
                and sections in <?= esc($selected['academic_year']) ?>, live.</div>

            <div class="ov-label">Day</div>
            <div class="day-chips" id="overviewDays"></div>

            <div class="ov-label">Room</div>
            <div class="room-grid overview-rooms" id="overviewRooms"></div>

            <div class="overview-list" id="overviewList"></div>

            <div class="actions">
                <button type="button" class="btn btn-ghost" id="overviewClose">Close</button>
            </div>
        </div>
    </div>

    <!-- Faculty context panel modal -->
    <div class="modal-backdrop" id="facultyPanelModal" role="dialog" aria-modal="true" aria-label="Department Faculty">
        <div class="modal overview-modal" style="width:560px;">
            <h3>Department Faculty</h3>
            <div class="sub" id="facultyPanelSub">Availability &amp; preferences for the upcoming term, current load for the active term.</div>
            <div id="facultyPanelList" style="max-height:60vh; overflow-y:auto;"></div>
            <div class="actions">
                <button type="button" class="btn btn-ghost" id="facultyPanelClose">Close</button>
            </div>
        </div>
    </div>

    <!-- Conflict warning modal -->
    <div class="modal-backdrop" id="conflictModal" role="dialog" aria-modal="true" aria-label="Cannot plot here">
        <div class="modal">
            <h3>Cannot plot here</h3>
            <div class="sub" id="conflictMessage"></div>
            <div class="conflict">
                <span class="tag" id="conflictTag"></span>
                <div class="when" id="conflictWhen"></div>
                <div id="conflictCards"></div>
            </div>
            <div class="actions">
                <button type="button" class="btn btn-primary" id="conflictOk">Got it</button>
            </div>
        </div>
    </div>

    <!-- Generic confirmation modal (stacks above the other modals) -->
    <div class="modal-backdrop confirm-top" id="confirmModal" role="dialog" aria-modal="true" aria-label="Confirm">
        <div class="modal confirm-modal">
            <h3 id="confirmTitle"></h3>
            <div class="sub" id="confirmMessage"></div>
            <div class="actions">
                <button type="button" class="btn btn-ghost" id="confirmCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmOk">Confirm</button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <!-- On-page error log: catches JS errors and [schedule] diagnostics without needing DevTools -->
    <button type="button" id="debugToggle" style="position:fixed; bottom:14px; left:14px; z-index:999;
        background:#1f2937; color:#fff; border:none; border-radius:20px; padding:8px 14px;
        font-size:12px; font-weight:700; cursor:pointer; display:none; align-items:center; gap:6px; box-shadow:0 4px 12px rgba(0,0,0,.25);">
        ⚠ Errors <span id="debugCount">0</span>
    </button>
    <div id="debugPanel" style="position:fixed; bottom:56px; left:14px; z-index:999; width:min(520px, 92vw);
        max-height:50vh; overflow-y:auto; background:#111827; color:#e5e7eb; border-radius:10px;
        padding:12px; font:11px/1.5 ui-monospace,monospace; box-shadow:0 8px 24px rgba(0,0,0,.4); display:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <b style="color:#fff; font-family:inherit;">Error log</b>
            <button type="button" id="debugClear" style="background:none; border:1px solid #4b5563; color:#e5e7eb; border-radius:6px; padding:2px 8px; cursor:pointer; font-size:11px;">Clear</button>
        </div>
        <div id="debugList"></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        // ---------- user menu (avatar dropdown) ----------
        (function () {
            const menu = document.getElementById('userMenu');
            const btn  = document.getElementById('userMenuBtn');
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                menu.classList.toggle('open');
            });
            document.addEventListener('click', () => menu.classList.remove('open'));
        })();

        // ---------- notifications ----------
        (function () {
            const NOTIF_URLS = {
                list:         <?= json_encode(site_url('chairperson/notifications')) ?>,
                markRead:     <?= json_encode(site_url('chairperson/notifications/mark-read')) ?>,
                markAllRead:  <?= json_encode(site_url('chairperson/notifications/mark-all-read')) ?>,
            };

            window.UNREAD = <?= json_encode((int) $unread_notifications) ?>;

            function updateNotifBadge() {
                const badge = document.getElementById('notifBadge');
                badge.textContent = window.UNREAD > 99 ? '99+' : window.UNREAD;
                badge.classList.toggle('zero', window.UNREAD === 0);
            }
            window.updateNotifBadge = updateNotifBadge;
            updateNotifBadge();

            async function loadNotifications() {
                const list = document.getElementById('notifList');
                list.innerHTML = '<p style="padding:14px;font-size:12px;color:var(--on-surface-variant);">Loading…</p>';

                try {
                    const response = await fetch(NOTIF_URLS.list, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await response.json();

                    if (!data.notifications || data.notifications.length === 0) {
                        list.innerHTML = '<p style="padding:14px;font-size:12px;color:var(--on-surface-variant);">No notifications yet.</p>';
                        return;
                    }

                    list.innerHTML = '';
                    data.notifications.forEach(n => {
                        const item = document.createElement('div');
                        item.style.cssText = 'padding:10px 14px;border-bottom:1px solid var(--outline-variant);cursor:pointer;font-size:12.5px;'
                            + (n.is_read == 0 ? 'background:rgba(0,64,166,.05);font-weight:600;' : '');
                        item.textContent = n.message;
                        item.addEventListener('click', async () => {
                            await fetch(NOTIF_URLS.markRead, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                body: JSON.stringify({ id: n.id }),
                            });
                            if (n.link) window.location.href = n.link;
                        });
                        list.appendChild(item);
                    });
                } catch (err) {
                    list.innerHTML = '<p style="padding:14px;font-size:12px;color:var(--on-surface-variant);">Could not load notifications.</p>';
                }
            }

            const wrap = document.getElementById('notifWrap');
            const btn  = document.getElementById('notifBtn');
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const opening = ! wrap.classList.contains('open');
                document.getElementById('userMenu').classList.remove('open');
                wrap.classList.toggle('open');
                if (opening) loadNotifications();
            });
            document.addEventListener('click', () => wrap.classList.remove('open'));

            document.getElementById('notifMarkAll').addEventListener('click', async (e) => {
                e.stopPropagation();
                await fetch(NOTIF_URLS.markAllRead, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                window.UNREAD = 0;
                updateNotifBadge();
                loadNotifications();
            });
        })();

        // ---------- generic modal accessibility: Tab-trap for whichever of
        // the 8 modals above is currently open. Escape is deliberately left
        // alone here — it already has a more specific handler further down
        // (search "confirmResolve") that correctly unwinds the confirm-modal
        // stack, and duplicating that logic here risked racing it. ----------
        (function () {
            function topOpenModal() {
                const open = Array.prototype.slice.call(document.querySelectorAll('.modal-backdrop.open'));
                if (!open.length) return null;
                // .confirm-top stacks above the others, so prefer it if present.
                return open.find(m => m.classList.contains('confirm-top')) || open[open.length - 1];
            }

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Tab') return;

                const modal = topOpenModal();
                if (!modal) return;

                const focusables = Array.prototype.slice.call(
                    modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
                ).filter(n => n.offsetParent !== null);
                if (!focusables.length) return;
                const first = focusables[0];
                const last  = focusables[focusables.length - 1];

                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            });
        })();

        // ---------- on-page error log ----------
        (function () {
            const list  = document.getElementById('debugList');
            const panel = document.getElementById('debugPanel');
            const toggle = document.getElementById('debugToggle');
            const countEl = document.getElementById('debugCount');
            let count = 0;

            function logEntry(kind, message) {
                count++;
                countEl.textContent = count;
                toggle.style.display = 'flex';

                const time = new Date().toLocaleTimeString();
                const row = document.createElement('div');
                row.style.cssText = 'border-bottom:1px solid #374151; padding:6px 0; white-space:pre-wrap; word-break:break-word;';
                row.innerHTML = '<span style="color:#f87171; font-weight:700;">[' + kind + ' ' + time + ']</span> ';
                row.appendChild(document.createTextNode(message));
                list.prepend(row);
            }

            toggle.addEventListener('click', () => {
                panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            });
            document.getElementById('debugClear').addEventListener('click', () => {
                list.innerHTML = '';
                count = 0;
                countEl.textContent = '0';
                toggle.style.display = 'none';
                panel.style.display = 'none';
            });

            window.addEventListener('error', (e) => {
                logEntry('JS error', `${e.message} — ${e.filename}:${e.lineno}:${e.colno}`);
            });
            window.addEventListener('unhandledrejection', (e) => {
                logEntry('unhandled promise', String(e.reason && e.reason.stack || e.reason));
            });

            const origError = console.error.bind(console);
            console.error = function (...args) {
                origError(...args);
                logEntry('console.error', args.map(a => {
                    try { return typeof a === 'string' ? a : JSON.stringify(a); }
                    catch (_) { return String(a); }
                }).join(' '));
            };
        })();
    </script>

    <script>
        function toMinutes(hhmm) {
            const [h, m] = hhmm.split(':').map(Number);
            return h * 60 + m;
        }

        const CONFIG = {
            rowHeight: 28,
            gridStartMin: toMinutes(<?= json_encode($grid_start) ?>),
            gridEndMin: toMinutes(<?= json_encode($grid_end) ?>),
            syncUrl: <?= json_encode(site_url('chairperson/schedule/sync')) ?>,
            stateUrl: <?= json_encode(site_url('chairperson/schedule/state')) ?>,
            facultyPanelUrl: <?= json_encode(site_url('chairperson/faculty-panel')) ?>,
            facultyOverrideUrl: <?= json_encode(site_url('chairperson/schedule/faculty-override')) ?>,
        };
        CONFIG.totalRows = (CONFIG.gridEndMin - CONFIG.gridStartMin) / 30;

        const SUBJECTS = <?= json_encode($subjects, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const ROOMS    = <?= json_encode($rooms, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const WEEK     = <?= json_encode($week, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const CONTEXT  = <?= json_encode($selected, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let OTHERS     = <?= json_encode(array_values($other_blocks), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let blocks     = <?= json_encode(array_values($blocks), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let ASSIGNMENTS = <?= json_encode(array_values($assignments), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let APPROVALS   = <?= json_encode(array_values($swap_approvals), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let CHANGE_REQUESTS = <?= json_encode(array_values($change_requests), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        // Autosave state: every change syncs to the server immediately.
        let saveTimer = null;   // debounce handle
        let saving = false;     // a sync request is in flight
        let saveQueued = false; // a change happened while saving
        let stateVersion = '';  // long-poll change signature

        // ---------- time helpers ----------
        function rowToHHMM(row) {
            const mins = CONFIG.gridStartMin + row * 30;
            return String(Math.floor(mins / 60)).padStart(2, '0') + ':' + String(mins % 60).padStart(2, '0');
        }

        function rowTo12h(row) {
            return minutesTo12h(CONFIG.gridStartMin + row * 30);
        }

        function minutesTo12h(mins) {
            let h = Math.floor(mins / 60);
            const m = mins % 60;
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return h + ':' + String(m).padStart(2, '0') + ' ' + ampm;
        }

        function blockStartRow(b) { return (toMinutes(b.start) - CONFIG.gridStartMin) / 30; }
        function blockEndRow(b)   { return (toMinutes(b.end)   - CONFIG.gridStartMin) / 30; }

        function rangesOverlap(aStart, aEnd, bStart, bEnd) {
            return aStart < bEnd && bStart < aEnd;
        }

        // ---------- grid construction ----------
        const timeCol = document.getElementById('timeCol');
        for (let row = 0; row < CONFIG.totalRows; row++) {
            const cell = document.createElement('div');
            cell.className = 'time slot';
            const mins = CONFIG.gridStartMin + row * 30;
            if (mins % 60 === 0) cell.textContent = rowTo12h(row);
            timeCol.appendChild(cell);
        }

        document.querySelectorAll('.day-col').forEach(col => {
            for (let row = 0; row < CONFIG.totalRows; row++) {
                const slot = document.createElement('div');
                slot.className = 'slot';
                col.appendChild(slot);
            }
        });

        // ---------- confirmation dialog ----------
        const confirmModal = document.getElementById('confirmModal');
        let confirmResolve = null;

        function confirmDialog({ title, message, confirmLabel = 'Confirm', danger = false }) {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmMessage').textContent = message;

            const okBtn = document.getElementById('confirmOk');
            okBtn.textContent = confirmLabel;
            okBtn.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');

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

        // ---------- rendering ----------
        // Assign overlapping blocks to side-by-side lanes (interval
        // partitioning), so no block ever hides behind another.
        function assignLanes(items) {
            const sorted = items.slice().sort((a, b) => a.startRow - b.startRow || a.endRow - b.endRow);

            const clusters = [];
            let cluster = [];
            let clusterEnd = -1;

            sorted.forEach(item => {
                if (cluster.length && item.startRow >= clusterEnd) {
                    clusters.push(cluster);
                    cluster = [];
                    clusterEnd = -1;
                }
                cluster.push(item);
                clusterEnd = Math.max(clusterEnd, item.endRow);
            });
            if (cluster.length) clusters.push(cluster);

            clusters.forEach(group => {
                const laneEnds = [];
                group.forEach(item => {
                    let lane = laneEnds.findIndex(end => end <= item.startRow);
                    if (lane === -1) { lane = laneEnds.length; laneEnds.push(0); }
                    laneEnds[lane] = item.endRow;
                    item.lane = lane;
                });
                group.forEach(item => { item.lanes = laneEnds.length; });
            });
        }

        function positionBlock(el, item) {
            el.style.top = (item.startRow * CONFIG.rowHeight) + 'px';
            el.style.height = ((item.endRow - item.startRow) * CONFIG.rowHeight - 4) + 'px';
            el.style.left = `calc(${(item.lane / item.lanes) * 100}% + 3px)`;
            el.style.width = `calc(${100 / item.lanes}% - 6px)`;
            el.style.right = 'auto';
        }

        function shortYear(yearLevel) {
            return (yearLevel || '').replace(' Year', ' Yr');
        }

        function ghostEl(b) {
            const el = document.createElement('div');
            el.className = 'block ghost';
            el.title = `${b.code} · ${b.section}${b.year_level ? ' · ' + b.year_level : ''}`
                + ` · ${b.room}${b.prof ? ' · ' + b.prof : ''} · ${b.time_label}`;

            const tag = document.createElement('div');
            tag.className = 'section-tag';
            tag.textContent = b.section + (b.year_level ? ' · ' + shortYear(b.year_level) : '');

            const code = document.createElement('div');
            code.className = 'code';
            code.textContent = b.code;

            const meta = document.createElement('div');
            meta.className = 'meta';
            meta.textContent = b.room;

            el.append(tag, code, meta);
            return el;
        }

        function ownEl(b, index) {
            const el = document.createElement('div');
            el.className = 'block ' + b.theme;
            el.title = `${b.code} · ${b.title} · ${b.time_label} · ${b.room}${b.prof ? ' · ' + b.prof : ''}`;

            const code = document.createElement('div');
            code.className = 'code';
            code.textContent = b.code;

            const title = document.createElement('div');
            title.className = 'title';
            title.textContent = b.title;

            const time = document.createElement('div');
            time.className = 'meta';
            time.textContent = b.time_label;

            const room = document.createElement('div');
            room.className = 'meta';
            room.textContent = b.room;

            let prof = null;
            if (b.prof) {
                prof = document.createElement('div');
                prof.className = 'meta prof';
                prof.style.cursor = 'pointer';
                prof.style.textDecoration = 'underline dotted';
                prof.title = 'Click to assign a different faculty member to this meeting';
                prof.textContent = '👤 ' + b.prof;
                prof.addEventListener('click', ev => {
                    ev.stopPropagation();
                    // Reopens the picker for just this one meeting — the
                    // subject's other meetings keep whoever they already
                    // have, only this block's assignment changes.
                    reassignBlockFaculty(index);
                });
            }

            const remove = document.createElement('button');
            remove.className = 'remove';
            remove.type = 'button';
            remove.title = 'Remove from schedule';
            remove.textContent = '×';
            remove.addEventListener('click', async () => {
                const confirmed = await confirmDialog({
                    title: 'Remove class',
                    message: `Remove ${b.code} — ${b.title} (${WEEK[b.day].day}, ${b.time_label}, ${b.room}) from this section's schedule?`,
                    confirmLabel: 'Remove',
                    danger: true,
                });
                if (!confirmed) return;

                blocks.splice(index, 1);
                renderBlocks();
                scheduleSave();
                showToast('Removed ' + b.code + ' from the schedule.', '');
            });

            el.append(code, title, time, room);
            if (prof) el.appendChild(prof);
            el.appendChild(remove);
            return el;
        }

        // Cached once — the six day columns never get replaced, only their
        // block children do, so re-querying the DOM every render is wasted
        // work and (if the selector ever mismatches) a silent failure point.
        const DAY_COLS = new Map();
        document.querySelectorAll('.day-col').forEach(col => {
            DAY_COLS.set(Number(col.dataset.day), col);
        });

        if (DAY_COLS.size === 0) {
            console.error('[schedule] no .day-col elements found in the DOM — the weekly grid did not render.');
        }

        function renderBlocks() {
            try {
                document.querySelectorAll('.day-col .block').forEach(el => el.remove());

                let placed = 0;

                for (const [day, col] of DAY_COLS) {
                    const items = [];

                    blocks.forEach((b, i) => {
                        if (Number(b.day) !== day) return;
                        items.push({ startRow: blockStartRow(b), endRow: blockEndRow(b), el: ownEl(b, i) });
                    });

                    OTHERS.forEach(b => {
                        if (Number(b.day) !== day) return;
                        items.push({ startRow: blockStartRow(b), endRow: blockEndRow(b), el: ghostEl(b) });
                    });

                    assignLanes(items);

                    items.forEach(item => {
                        positionBlock(item.el, item);
                        col.appendChild(item.el);
                        placed++;
                    });
                }

                if (blocks.length + OTHERS.length > 0 && placed === 0) {
                    console.error('[schedule] blocks exist in memory but none were placed on the grid.', { blocks, OTHERS, days: [...DAY_COLS.keys()] });
                }
            } catch (err) {
                console.error('[schedule] renderBlocks failed:', err);
                showToast('Something went wrong drawing the schedule — check the console and reload.', 'error');
            }

            updateRail();
            renderRoomOverview(); // keep the overview live with every change
        }

        /** A subject can meet at most this many times a week (matches the server-side cap). */
        const MAX_MEETINGS = 3;

        function round1(n) { return Math.round(n * 10) / 10; }

        function updateRail() {
            let units = 0;

            SUBJECTS.forEach(s => {
                const subjectBlocks = blocks.filter(b => b.code === s.code);
                const count = subjectBlocks.length;
                const card = document.querySelector(`.subject[data-code="${s.code}"]`);

                if (count > 0) units += parseFloat(s.units) || 0;

                // Actual scheduled hours toward this subject's own weekly
                // target (units x hrs/unit) — not a fixed "out of 3
                // meetings" count. E.g. a 3-hour-per-meeting subject only
                // needs 1 meeting; a 1.5-hour subject needs 2.
                const weeklyHours = parseFloat(s.weekly_hours) || 0;

                let subjectPlotted = 0;
                subjectBlocks.forEach(b => {
                    const duration = (toMinutes(b.end) - toMinutes(b.start)) / 60;
                    const room = Math.max(0, weeklyHours - subjectPlotted);
                    subjectPlotted += Math.min(duration, room);
                });

                if (card) {
                    card.classList.toggle('is-plotted', weeklyHours > 0 && subjectPlotted >= weeklyHours);
                    const counter = card.querySelector('.plot-count');
                    if (counter) {
                        counter.textContent = count > 0
                            ? `✓ Plotted ${round1(subjectPlotted)}/${round1(weeklyHours)} Hours`
                            : '';
                    }
                }
            });

            document.getElementById('plottedUnits').textContent = units;
            document.getElementById('classCount').textContent =
                blocks.length === 0 ? 'No classes plotted yet' : `${blocks.length} ${blocks.length === 1 ? 'class' : 'classes'} plotted for this section`;
        }

        function setSync(state, text) {
            const box = document.getElementById('syncStatus');
            box.className = 'sync-status' + (state === 'saved' ? '' : ' ' + state);
            document.getElementById('syncText').textContent = text;
        }

        // ---------- drag & drop ----------
        let dragged = null;
        const preview = document.createElement('div');
        preview.className = 'drop-preview';

        document.querySelectorAll('.subject').forEach(card => {
            card.addEventListener('dragstart', e => {
                dragged = SUBJECTS.find(s => s.code === card.dataset.code) || null;
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'copy';
                e.dataTransfer.setData('text/plain', card.dataset.code);
            });
            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                preview.remove();
                dragged = null;
            });
        });

        function subjectSlots(subject) {
            return Math.max(1, Math.round((subject.duration || 1.5) * 2));
        }

        function dropRange(col, clientY, subject) {
            const slots = subjectSlots(subject);
            const rect = col.getBoundingClientRect();
            let startRow = Math.floor((clientY - rect.top) / CONFIG.rowHeight);
            startRow = Math.max(0, Math.min(startRow, CONFIG.totalRows - slots));
            return { startRow, endRow: startRow + slots };
        }

        // Hard conflict: this section's grid already has a class in the range.
        function sectionConflict(day, startRow, endRow) {
            return blocks.find(b => b.day === day && rangesOverlap(startRow, endRow, blockStartRow(b), blockEndRow(b)));
        }

        // Hard conflict: the professor already teaches another class
        // (this section or any other) in the range.
        function facultyConflict(prof, day, startRow, endRow) {
            if (!prof) return null;
            return blocks.concat(OTHERS).find(b =>
                b.prof === prof && b.day === day && rangesOverlap(startRow, endRow, blockStartRow(b), blockEndRow(b)));
        }

        // Rooms are physical: occupancy counts every section's blocks.
        // grey = occupied at that exact time; otherwise availability degrades
        // with how many classes the room already hosts that day.
        function roomStatus(room, day, startRow, endRow) {
            const all = blocks.concat(OTHERS);

            const occupied = all.some(b =>
                b.room === room && b.day === day && rangesOverlap(startRow, endRow, blockStartRow(b), blockEndRow(b)));
            if (occupied) return 'grey';

            const load = all.filter(b => b.room === room && b.day === day).length;
            if (load >= 3) return 'red';
            if (load >= 2) return 'yellow';
            return 'green';
        }

        const ROOM_STATUS_LABEL = {
            green:  'Available',
            yellow: 'High utilization',
            red:    'Near full',
            grey:   'Occupied',
        };

        document.querySelectorAll('.day-col').forEach(col => {
            col.addEventListener('dragover', e => {
                if (!dragged) return;
                e.preventDefault();

                const day = Number(col.dataset.day);
                const { startRow, endRow } = dropRange(col, e.clientY, dragged);

                preview.style.top = (startRow * CONFIG.rowHeight) + 'px';
                preview.style.height = ((endRow - startRow) * CONFIG.rowHeight - 4) + 'px';
                preview.classList.toggle('invalid',
                    !!sectionConflict(day, startRow, endRow)
                    || !!facultyConflict(dragged.prof, day, startRow, endRow));
                if (preview.parentElement !== col) col.appendChild(preview);
            });

            col.addEventListener('dragleave', e => {
                if (e.target === col && preview.parentElement === col) preview.remove();
            });

            col.addEventListener('drop', e => {
                if (!dragged) return;
                e.preventDefault();
                preview.remove();

                const subject = dragged;
                const day = Number(col.dataset.day);
                const { startRow, endRow } = dropRange(col, e.clientY, subject);

                const attempted = { subject, day, startRow, endRow };

                // Hard rule: the section cannot attend two classes at once,
                // no matter the room.
                const clash = sectionConflict(day, startRow, endRow);
                if (clash) {
                    showConflictModal({
                        tag: 'Time Overlap',
                        message: `${CONTEXT.section} already has a class during this time. A section cannot attend two classes at once — pick a different time.`,
                        attempted,
                        existing: clash,
                    });
                    return;
                }

                // Hard rule: the professor cannot teach two classes at once,
                // even in different rooms or for a different section.
                const profClash = facultyConflict(subject.prof, day, startRow, endRow);
                if (profClash) {
                    const where = profClash.section && profClash.section !== CONTEXT.section
                        ? ` for section ${profClash.section}` : '';
                    showConflictModal({
                        tag: 'Faculty Conflict',
                        message: `${subject.prof} is already teaching at this time${where}. A professor cannot be in two rooms at once — pick a different time or reassign the subject.`,
                        attempted,
                        existing: profClash,
                    });
                    return;
                }

                // Soft warning: plotting on a day the section doesn't attend
                // yet means an extra campus day. Doesn't block the drop.
                const warnings = [];
                const attendedDays = new Set(blocks.map(b => b.day));
                if (attendedDays.size > 0 && !attendedDays.has(day)) {
                    warnings.push(`Adding ${subject.code} on ${WEEK[day].day} may require students to attend campus on an extra day. You can still continue.`);
                }

                openRoomModal(subject, day, startRow, endRow, warnings);
            });
        });

        // ---------- swap approvals ----------
        const approvalsModal = document.getElementById('approvalsModal');

        function renderApprovals() {
            const badge = document.getElementById('approvalBadge');
            badge.textContent = APPROVALS.length;
            badge.classList.toggle('zero', APPROVALS.length === 0);

            const list = document.getElementById('approvalsList');
            list.innerHTML = '';

            if (APPROVALS.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'approvals-empty';
                empty.textContent = 'No swaps waiting for approval.';
                list.appendChild(empty);
                return;
            }

            APPROVALS.forEach(a => {
                const item = document.createElement('div');
                item.className = 'approval-item';

                const who = document.createElement('div');
                who.className = 'ap-who';
                who.textContent = a.requested
                    ? `${a.requester} ⇄ ${a.target} (exchange)`
                    : `${a.requester} → ${a.target} (handover)`;

                const loads = document.createElement('div');
                loads.className = 'ap-loads';
                loads.textContent = a.requested
                    ? `${a.requester} gives ${a.offered} · receives ${a.requested}`
                    : `${a.requester} hands over ${a.offered}`;

                const actions = document.createElement('div');
                actions.className = 'ap-actions';

                const reject = document.createElement('button');
                reject.type = 'button';
                reject.className = 'btn btn-ghost';
                reject.textContent = 'Reject';
                reject.addEventListener('click', () => decideSwap(a, false));

                const approve = document.createElement('button');
                approve.type = 'button';
                approve.className = 'btn btn-primary';
                approve.textContent = 'Approve';
                approve.addEventListener('click', () => decideSwap(a, true));

                actions.append(reject, approve);
                item.append(who, loads, actions);
                list.appendChild(item);
            });
        }

        async function decideSwap(a, approve) {
            const confirmed = await confirmDialog({
                title: (approve ? 'Approve' : 'Reject') + ' swap',
                message: (a.requested
                    ? `${a.requester} gives ${a.offered} and receives ${a.requested} from ${a.target}.`
                    : `${a.requester} hands ${a.offered} over to ${a.target}.`)
                    + (approve ? ' Approving transfers the load(s) immediately.' : ' Rejecting keeps everything as it is.'),
                confirmLabel: approve ? 'Approve Swap' : 'Reject Swap',
                danger: !approve,
            });
            if (!confirmed) return;

            try {
                const url = approve
                    ? <?= json_encode(site_url('chairperson/swaps/approve')) ?>
                    : <?= json_encode(site_url('chairperson/swaps/reject')) ?>;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ swap_id: a.id }),
                });
                const result = await response.json();

                if (response.ok && result.ok) {
                    APPROVALS = APPROVALS.filter(x => x.id !== a.id);
                    renderApprovals();
                    showToast(approve ? 'Swap approved — loads transferred.' : 'Swap rejected.', approve ? 'success' : '');
                } else {
                    showToast(result.error || 'Could not decide the swap.', 'error');
                }
            } catch (err) {
                showToast('Network error while deciding the swap.', 'error');
            }
        }

        document.getElementById('approvalsBtn').addEventListener('click', () => {
            renderApprovals();
            approvalsModal.classList.add('open');
        });

        // ===== Change requests =====
        const changeRequestsModal = document.getElementById('changeRequestsModal');

        function renderChangeRequests() {
            const badge = document.getElementById('changeRequestBadge');
            badge.textContent = CHANGE_REQUESTS.length;
            badge.classList.toggle('zero', CHANGE_REQUESTS.length === 0);

            const list = document.getElementById('changeRequestsList');
            list.innerHTML = '';

            if (CHANGE_REQUESTS.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'approvals-empty';
                empty.textContent = 'No change requests waiting for review.';
                list.appendChild(empty);
                return;
            }

            CHANGE_REQUESTS.forEach(cr => {
                const item = document.createElement('div');
                item.className = 'approval-item';

                const who = document.createElement('div');
                who.className = 'ap-who';
                who.textContent = `${cr.faculty} — ${cr.subject}`;

                const reason = document.createElement('div');
                reason.className = 'ap-loads';
                reason.textContent = cr.reason + (cr.proposed ? ` (proposed: ${cr.proposed})` : '');

                const actions = document.createElement('div');
                actions.className = 'ap-actions';

                const reject = document.createElement('button');
                reject.type = 'button';
                reject.className = 'btn btn-ghost';
                reject.textContent = 'Reject';
                reject.addEventListener('click', () => decideChangeRequest(cr, false));

                const approve = document.createElement('button');
                approve.type = 'button';
                approve.className = 'btn btn-primary';
                approve.textContent = 'Approve';
                approve.addEventListener('click', () => decideChangeRequest(cr, true));

                actions.append(reject, approve);
                item.append(who, reason, actions);
                list.appendChild(item);
            });
        }

        async function decideChangeRequest(cr, approve) {
            const confirmed = await confirmDialog({
                title: (approve ? 'Approve' : 'Reject') + ' change request',
                message: `${cr.faculty}'s request for ${cr.subject}: "${cr.reason}".`
                    + (approve ? ' Approving does not reassign the subject — you\'ll need to replot it with a different faculty member.' : ' Rejecting keeps the assignment as it is.'),
                confirmLabel: approve ? 'Approve' : 'Reject',
                danger: !approve,
            });
            if (!confirmed) return;

            try {
                const url = approve
                    ? <?= json_encode(site_url('chairperson/change-requests/approve')) ?>
                    : <?= json_encode(site_url('chairperson/change-requests/reject')) ?>;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ request_id: cr.id }),
                });
                const result = await response.json();

                if (response.ok && result.ok) {
                    CHANGE_REQUESTS = CHANGE_REQUESTS.filter(x => x.id !== cr.id);
                    renderChangeRequests();
                    showToast(approve ? 'Change request approved.' : 'Change request rejected.', approve ? 'success' : '');
                } else {
                    showToast(result.error || 'Could not decide the change request.', 'error');
                }
            } catch (err) {
                showToast('Network error while deciding the change request.', 'error');
            }
        }

        document.getElementById('changeRequestsBtn').addEventListener('click', () => {
            renderChangeRequests();
            changeRequestsModal.classList.add('open');
        });
        document.getElementById('changeRequestsClose').addEventListener('click', () => changeRequestsModal.classList.remove('open'));
        changeRequestsModal.addEventListener('click', e => { if (e.target === changeRequestsModal) changeRequestsModal.classList.remove('open'); });
        document.getElementById('approvalsClose').addEventListener('click', () => approvalsModal.classList.remove('open'));
        approvalsModal.addEventListener('click', e => { if (e.target === approvalsModal) approvalsModal.classList.remove('open'); });

        // ---------- room overview modal ----------
        const overviewModal = document.getElementById('overviewModal');
        const overviewState = { day: 0, room: ROOMS[0].name };

        function overviewRoomLoad(room, day) {
            return blocks.concat(OTHERS).filter(b => b.room === room && b.day === day).length;
        }

        function renderOverviewControls() {
            const daysBox = document.getElementById('overviewDays');
            daysBox.innerHTML = '';
            WEEK.forEach((d, i) => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'day-chip' + (i === overviewState.day ? ' active' : '');
                chip.textContent = d.day;
                chip.addEventListener('click', () => {
                    overviewState.day = i;
                    renderRoomOverview();
                });
                daysBox.appendChild(chip);
            });

            const roomsBox = document.getElementById('overviewRooms');
            roomsBox.innerHTML = '';

            FLOORS.forEach(floor => {
                const heading = document.createElement('div');
                heading.className = 'floor-heading';
                heading.textContent = floor;
                roomsBox.appendChild(heading);

                ROOMS.filter(r => r.floor === floor).forEach(room => {
                    const load = overviewRoomLoad(room.name, overviewState.day);
                    const state = load >= 3 ? 'red' : load >= 2 ? 'yellow' : 'green';

                    const card = document.createElement('button');
                    card.type = 'button';
                    card.className = 'room-card ' + state + (room.name === overviewState.room ? ' selected' : '');

                    const head = document.createElement('div');
                    head.className = 'room-head';
                    const name = document.createElement('div');
                    name.className = 'room-name';
                    name.textContent = room.name;
                    const dot = document.createElement('span');
                    dot.className = 'room-dot';
                    head.append(name, dot);

                    const status = document.createElement('div');
                    status.className = 'room-status';
                    status.textContent = load === 0 ? 'Free all day' : `${load} ${load === 1 ? 'class' : 'classes'}`;

                    card.append(head, status);
                    card.addEventListener('click', () => {
                        overviewState.room = room.name;
                        renderRoomOverview();
                    });
                    roomsBox.appendChild(card);
                });
            });
        }

        function overviewSectionLabel(b) {
            const section = b.section || CONTEXT.section;
            const yearLevel = b.year_level || CONTEXT.year_level;
            return `${section} · ${shortYear(yearLevel)}`;
        }

        function overviewClassRow(b) {
            const row = document.createElement('div');
            row.className = 'overview-row';

            const time = document.createElement('div');
            time.className = 'ov-time';
            time.textContent = `${rowTo12h(blockStartRow(b))} - ${rowTo12h(blockEndRow(b))}`;

            const details = document.createElement('div');
            details.className = 'ov-details';
            const code = document.createElement('span');
            code.className = 'code';
            code.textContent = b.code;
            details.appendChild(code);
            details.appendChild(document.createTextNode(' — ' + b.title));
            const meta = document.createElement('div');
            meta.className = 'meta';
            const program = b.program || CONTEXT.program;
            meta.textContent = (b.prof ? b.prof + ' · ' : '') + program;
            details.appendChild(meta);

            const section = document.createElement('span');
            section.className = 'ov-section';
            section.textContent = overviewSectionLabel(b);

            row.append(time, details, section);
            return row;
        }

        function overviewFreeRow(startRow, endRow) {
            const row = document.createElement('div');
            row.className = 'overview-row free';

            const time = document.createElement('div');
            time.className = 'ov-time';
            time.textContent = `${rowTo12h(startRow)} - ${rowTo12h(endRow)}`;

            const details = document.createElement('div');
            details.className = 'ov-details';
            const hours = (endRow - startRow) / 2;
            details.textContent = `Free · ${hours} ${hours === 1 ? 'hour' : 'hours'}`;

            row.append(time, details);
            return row;
        }

        function renderRoomOverview() {
            if (!overviewModal.classList.contains('open')) return;

            renderOverviewControls();

            const room = overviewState.room;
            const day = overviewState.day;
            const list = document.getElementById('overviewList');
            list.innerHTML = '';

            const items = blocks.concat(OTHERS)
                .filter(b => b.room === room && b.day === day)
                .sort((a, b) => blockStartRow(a) - blockStartRow(b));

            if (items.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'overview-empty';
                empty.textContent = `${room} is free all day on ${WEEK[day].day} — no classes plotted.`;
                list.appendChild(empty);
                return;
            }

            // Interleave the occupied slots with the free gaps between them.
            let cursor = 0;
            items.forEach(b => {
                const startRow = blockStartRow(b);
                const endRow = blockEndRow(b);
                if (startRow > cursor) list.appendChild(overviewFreeRow(cursor, startRow));
                list.appendChild(overviewClassRow(b));
                cursor = Math.max(cursor, endRow);
            });
            if (cursor < CONFIG.totalRows) list.appendChild(overviewFreeRow(cursor, CONFIG.totalRows));
        }

        document.getElementById('roomOverviewBtn').addEventListener('click', () => {
            overviewModal.classList.add('open');
            renderRoomOverview();
        });

        document.getElementById('overviewClose').addEventListener('click', () => overviewModal.classList.remove('open'));
        overviewModal.addEventListener('click', e => { if (e.target === overviewModal) overviewModal.classList.remove('open'); });

        // ===== Faculty context panel =====
        const facultyPanelModal = document.getElementById('facultyPanelModal');

        function facultyStatusChip(status) {
            if (!status) return '';
            const label = status === 'full_time' ? 'Full-Time' : 'Part-Time';
            return `<span style="font-size:11px; padding:2px 8px; border-radius:999px; background:#eef2ff; color:#4338ca; margin-left:6px;">${label}</span>`;
        }

        function facultyAvailabilitySummary(rows) {
            if (!rows || !rows.length) return '<span style="color:var(--outline);">No availability submitted yet</span>';
            const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            return rows.map(r => {
                if (!r.is_available || r.is_available == 0) {
                    return `${days[r.day_of_week]}: Not available`;
                }
                return `${days[r.day_of_week]}: ${(r.start_time || '').slice(0, 5)}–${(r.end_time || '').slice(0, 5)}`;
            }).join(' &middot; ');
        }

        function facultyPreferenceChips(prefs) {
            if (!prefs || !prefs.length) return '<span style="color:var(--outline);">No preferences submitted</span>';
            const colors = { preferred: '#dcfce7', willing: '#fef9c3', qualified: '#e0e7ff' };
            const textColors = { preferred: '#166534', willing: '#854d0e', qualified: '#3730a3' };
            return prefs.map(p =>
                `<span title="${p.title}" style="font-size:11px; padding:2px 8px; border-radius:999px; background:${colors[p.level] || '#eee'}; color:${textColors[p.level] || '#333'}; margin-right:4px;">${p.code} &middot; ${p.level}</span>`
            ).join('');
        }

        async function loadFacultyPanel() {
            const list = document.getElementById('facultyPanelList');
            list.innerHTML = '<p class="sub">Loading&hellip;</p>';

            try {
                const response = await fetch(CONFIG.facultyPanelUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
                const data = await response.json();

                document.getElementById('facultyPanelSub').textContent = data.upcoming_term
                    ? `Availability & preferences for ${data.upcoming_term}. Current load for ${data.active_year || 'the active term'}.`
                    : 'No upcoming term configured yet — faculty cannot submit availability until an admin adds one.';

                if (!data.faculty.length) {
                    list.innerHTML = '<p class="sub">No faculty members in your department yet.</p>';
                    return;
                }

                list.innerHTML = data.faculty.map(f => `
                    <div style="border:1px solid var(--outline-variant); border-radius:10px; padding:12px 14px; margin-bottom:10px;">
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <strong>${f.name}</strong>
                            <span>${facultyStatusChip(f.employment_status)}</span>
                        </div>
                        <div style="font-size:12px; color:var(--on-surface-variant); margin-top:6px;">
                            <strong>Load:</strong> ${f.units} units &middot; ${f.weekly_hours} hrs/week &middot; ${f.subjects.length} subject(s)
                        </div>
                        <div style="font-size:12px; margin-top:6px;">
                            <strong>Availability:</strong> ${facultyAvailabilitySummary(f.availability)}
                        </div>
                        <div style="font-size:12px; margin-top:6px;">
                            <strong>Preferences:</strong> ${facultyPreferenceChips(f.preferences)}
                        </div>
                    </div>
                `).join('');
            } catch (e) {
                list.innerHTML = '<p class="sub">Could not load faculty data.</p>';
            }
        }

        document.getElementById('facultyPanelBtn').addEventListener('click', () => {
            facultyPanelModal.classList.add('open');
            loadFacultyPanel();
        });

        document.getElementById('facultyPanelClose').addEventListener('click', () => facultyPanelModal.classList.remove('open'));
        facultyPanelModal.addEventListener('click', e => { if (e.target === facultyPanelModal) facultyPanelModal.classList.remove('open'); });

        // ---------- conflict warning modal ----------
        const conflictModal = document.getElementById('conflictModal');

        function conflictCard(label, code, title, metaText) {
            const card = document.createElement('div');
            card.className = 'class-card';

            const head = document.createElement('div');
            const tag = document.createElement('span');
            tag.className = 'meta';
            tag.textContent = label + ' ';
            const codeEl = document.createElement('span');
            codeEl.className = 'code';
            codeEl.textContent = code;
            head.append(tag, codeEl, document.createTextNode(' — ' + title));

            const meta = document.createElement('div');
            meta.className = 'meta';
            meta.textContent = metaText;

            card.append(head, meta);
            return card;
        }

        /**
         * opts: { tag, message, attempted: {subject, day, startRow, endRow},
         *         existing: block|null }
         */
        function showConflictModal(opts) {
            document.getElementById('conflictTag').textContent = opts.tag;
            document.getElementById('conflictMessage').textContent = opts.message;

            const a = opts.attempted;
            document.getElementById('conflictWhen').textContent =
                `${WEEK[a.day].day}, ${rowTo12h(a.startRow)} - ${rowTo12h(a.endRow)}`;

            const cards = document.getElementById('conflictCards');
            cards.innerHTML = '';

            cards.appendChild(conflictCard(
                'You are plotting:',
                a.subject.code,
                a.subject.title,
                (a.subject.prof ? a.subject.prof + ' · ' : '') + CONTEXT.section
            ));

            if (opts.existing) {
                const e = opts.existing;
                cards.appendChild(conflictCard(
                    'Already scheduled:',
                    e.code,
                    e.title,
                    `${e.room}${e.prof ? ' · ' + e.prof : ''} · ${e.section || CONTEXT.section} · ${e.time_label}`
                ));
            }

            conflictModal.classList.add('open');
        }

        document.getElementById('conflictOk').addEventListener('click', () => conflictModal.classList.remove('open'));
        conflictModal.addEventListener('click', e => { if (e.target === conflictModal) conflictModal.classList.remove('open'); });

        // ---------- room assignment modal ----------
        const modal = document.getElementById('roomModal');
        const roomGrid = document.getElementById('roomGrid');

        let modalArgs = null; // remembered so polling can refresh room statuses live

        const FLOORS = [...new Set(ROOMS.map(r => r.floor))];
        let floorFilter = 'all';
        let roomModalExpanded = localStorage.getItem('roomModalExpanded') === '1';

        function applyRoomModalSize() {
            document.getElementById('roomModalBox').classList.toggle('expanded', roomModalExpanded);
            document.getElementById('roomModalExpand').innerHTML =
                roomModalExpanded ? '&#x26F6; Collapse' : '&#x26F6; Expand';
        }

        document.getElementById('roomModalExpand').addEventListener('click', () => {
            roomModalExpanded = !roomModalExpanded;
            localStorage.setItem('roomModalExpanded', roomModalExpanded ? '1' : '0');
            applyRoomModalSize();
            renderFloorRail();
        });

        function renderFloorRail() {
            const rail = document.getElementById('floorRail');
            rail.innerHTML = '';

            ['all', ...FLOORS].forEach(floor => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'floor-btn' + (floor === floorFilter ? ' active' : '');
                btn.textContent = floor === 'all' ? 'All floors' : floor;
                btn.addEventListener('click', () => {
                    floorFilter = floor;
                    renderFloorRail();
                    renderRoomGrid();
                });
                rail.appendChild(btn);
            });
        }

        function roomCard(room, subject, day, startRow, endRow) {
            const state = roomStatus(room.name, day, startRow, endRow);
            const occupied = state === 'grey';

            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'room-card ' + state;
            card.disabled = occupied;

            const head = document.createElement('div');
            head.className = 'room-head';

            const name = document.createElement('div');
            name.className = 'room-name';
            name.textContent = room.name;
            if (room.is_laboratory) {
                const badge = document.createElement('span');
                badge.className = 'lab-room-badge';
                badge.textContent = 'Laboratory';
                name.appendChild(badge);
            }

            const dot = document.createElement('span');
            dot.className = 'room-dot';

            head.append(name, dot);

            const status = document.createElement('div');
            status.className = 'room-status';
            status.textContent = ROOM_STATUS_LABEL[state] + (room.capacity ? ` · Capacity: ${room.capacity}` : '');

            card.append(head, status);

            if (!occupied) {
                card.addEventListener('click', () => selectRoom(subject, day, startRow, endRow, room));
            }

            return card;
        }

        async function selectRoom(subject, day, startRow, endRow, room) {
            // Soft rule: a laboratory subject in a non-lab room is allowed,
            // but must be explicitly confirmed — never silently plotted.
            if (subject.is_lab && !room.is_laboratory) {
                const confirmed = await confirmDialog({
                    title: 'Laboratory Room Recommended',
                    message: `${subject.code} — ${subject.title} is marked as a laboratory subject, but ${room.name} is not classified as a laboratory. Are you sure you want to continue?`,
                    confirmLabel: 'Continue Anyway',
                    danger: false,
                });
                if (!confirmed) return; // stay in the room picker
            }

            closeRoomModal();

            // A subject is taught by one faculty member across all its
            // meetings (enforced server-side in sync() too) — so once one
            // meeting has an assigned faculty, every later meeting of the
            // same subject in this section carries that assignment over
            // automatically instead of asking again.
            const existing = blocks.find(b => b.code === subject.code && b.faculty_id);

            if (existing) {
                pendingPlot = { subject, day, startRow, endRow, roomName: room.name };
                finalizePlot(existing.faculty_id, existing.prof);
                return;
            }

            openFacultyModal(subject, day, startRow, endRow, room.name);
        }

        function renderRoomGrid() {
            if (!modalArgs) return;
            const [subject, day, startRow, endRow] = modalArgs;

            roomGrid.innerHTML = '';

            const inFloor = r => floorFilter === 'all' || floorFilter === r.floor;

            if (subject.is_lab) {
                // Laboratory subject: lab rooms recommended first, then
                // everything else — grouping takes priority over floor
                // layout so the best options are never buried below.
                const labRooms = ROOMS.filter(r => r.is_laboratory && inFloor(r));
                const otherRooms = ROOMS.filter(r => !r.is_laboratory && inFloor(r));

                if (labRooms.length) {
                    const heading = document.createElement('div');
                    heading.className = 'floor-heading recommended';
                    heading.textContent = '⭐ Recommended — Laboratory Rooms';
                    roomGrid.appendChild(heading);
                    labRooms.forEach(room => roomGrid.appendChild(roomCard(room, subject, day, startRow, endRow)));
                }

                if (otherRooms.length) {
                    const heading = document.createElement('div');
                    heading.className = 'floor-heading';
                    heading.textContent = 'Other Rooms';
                    roomGrid.appendChild(heading);
                    otherRooms.forEach(room => roomGrid.appendChild(roomCard(room, subject, day, startRow, endRow)));
                }

                return;
            }

            // Lecture subject: unchanged floor-grouped layout. Lab rooms can
            // still appear (still usable), just not promoted.
            FLOORS.forEach(floor => {
                if (floorFilter !== 'all' && floorFilter !== floor) return;

                const heading = document.createElement('div');
                heading.className = 'floor-heading';
                heading.textContent = floor;
                roomGrid.appendChild(heading);

                ROOMS.filter(r => r.floor === floor)
                    .forEach(room => roomGrid.appendChild(roomCard(room, subject, day, startRow, endRow)));
            });
        }

        function openRoomModal(subject, day, startRow, endRow, warnings = []) {
            modalArgs = [subject, day, startRow, endRow, warnings];
            document.getElementById('roomModalSub').textContent =
                `${subject.code} - ${subject.title} · ${WEEK[day].day}, ${rowTo12h(startRow)} - ${rowTo12h(endRow)}`;

            const warningsBox = document.getElementById('roomModalWarnings');
            warningsBox.innerHTML = '';
            warnings.forEach(text => {
                const warning = document.createElement('div');
                warning.className = 'modal-warning';
                warning.append(document.createTextNode(' ' + text));
                warningsBox.appendChild(warning);
            });

            applyRoomModalSize();
            renderFloorRail();
            renderRoomGrid();

            modal.classList.add('open');
        }

        function closeRoomModal() {
            modalArgs = null;
            modal.classList.remove('open');
        }

        document.getElementById('roomModalCancel').addEventListener('click', closeRoomModal);
        modal.addEventListener('click', e => { if (e.target === modal) closeRoomModal(); });
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            // The confirm dialog stacks on top: Esc answers it (as "cancel")
            // without closing the modal underneath.
            if (confirmResolve !== null) {
                settleConfirm(false);
                return;
            }
            closeRoomModal();
            document.getElementById('conflictModal').classList.remove('open');
            overviewModal.classList.remove('open');
            approvalsModal.classList.remove('open');
            changeRequestsModal.classList.remove('open');
            closeFacultyModal();
        });

        // ---------- faculty assignment modal ----------
        const facultyModal = document.getElementById('facultyModal');
        let pendingPlot = null;

        function timeOverlap(aStart, aEnd, bStart, bEnd) {
            return aStart < bEnd && bStart < aEnd;
        }

        function finalizePlot(facultyId, facultyName) {
            if (!pendingPlot) return;
            const { subject, day, startRow, endRow, roomName, reassignIndex } = pendingPlot;

            if (reassignIndex != null && blocks[reassignIndex]) {
                // Reassigning one existing meeting in place — its day/time/
                // room don't change, only who's teaching it.
                blocks[reassignIndex].prof = facultyName || undefined;
                blocks[reassignIndex].faculty_id = facultyId || null;

                closeFacultyModal();
                renderBlocks();
                scheduleSave();
                showToast(
                    facultyName
                        ? `${subject.code} (${WEEK[day].day}, ${blocks[reassignIndex].time_label}) reassigned to ${facultyName}.`
                        : `${subject.code} (${WEEK[day].day}, ${blocks[reassignIndex].time_label}) unassigned.`,
                    'success'
                );
                return;
            }

            blocks.push({
                day: day,
                code: subject.code,
                title: subject.title,
                prof: facultyName || undefined,
                faculty_id: facultyId || null,
                time_label: `${rowTo12h(startRow)} - ${rowTo12h(endRow)}`,
                room: roomName,
                theme: subject.color,
                start: rowToHHMM(startRow),
                end: rowToHHMM(endRow),
            });

            closeFacultyModal();
            renderBlocks();
            scheduleSave();
            showToast(
                facultyName
                    ? `${subject.code} plotted in ${roomName}, assigned to ${facultyName}.`
                    : `${subject.code} plotted in ${roomName}.`,
                'success'
            );
        }

        // Reopens the faculty picker for ONE already-plotted meeting, to
        // override its carried-over assignment without touching the
        // subject's other meetings. Clicking a candidate updates this block
        // in place (see the reassignIndex branch in finalizePlot above).
        function reassignBlockFaculty(index) {
            const b = blocks[index];
            if (!b) return;

            openFacultyModal(
                { code: b.code, title: b.title, color: b.theme, units: null },
                b.day,
                blockStartRow(b),
                blockEndRow(b),
                b.room,
                index
            );
        }

        // Faculty status for one proposed (day, start, end) slot, relative
        // to their marked availability/preferences and existing meetings:
        //   'conflict'    — already teaching something else at this time (hard block, cannot override)
        //   'unavailable' — marked not available (or no availability on record for that day) — overridable, but warns first
        //   'mismatch'    — available, but not their preferred subject/level
        //   'preferred'   — available and this subject is one of their preferences
        function classifyFacultySlot(f, subject, day, start, end, isReassign) {
            const conflictMeeting = (f.meetings || []).find(m => {
                if (m.day !== day || !timeOverlap(start, end, m.start, m.end)) return false;
                // When reassigning an already-saved meeting, its own saved
                // record (for whoever currently teaches it) exactly matches
                // this slot — that's not a real conflict, it's the meeting
                // being edited.
                if (isReassign && m.start === start && m.end === end) return false;
                return true;
            });
            if (conflictMeeting) {
                return { status: 'conflict', meeting: conflictMeeting };
            }

            const dayAvail = (f.availability || []).find(a => a.day_of_week === day);
            const availStart = dayAvail && dayAvail.start_time ? dayAvail.start_time.slice(0, 5) : null;
            const availEnd = dayAvail && dayAvail.end_time ? dayAvail.end_time.slice(0, 5) : null;
            const withinWindow = dayAvail && dayAvail.is_available
                && (!availStart || availStart <= start)
                && (!availEnd || availEnd >= end);

            if (!dayAvail || !dayAvail.is_available || !withinWindow) {
                return { status: 'unavailable', dayAvail };
            }

            const pref = (f.preferences || []).find(p => p.code === subject.code);

            return pref && pref.level === 'preferred'
                ? { status: 'preferred', pref }
                : { status: 'mismatch', pref };
        }

        const SLOT_STATUS_META = {
            conflict:    { label: 'Schedule conflict' },
            unavailable: { label: 'Availability conflict' },
            mismatch:    { label: 'Not a preferred subject' },
            preferred:   { label: 'Preferred' },
        };

        function facultyAvailabilityTable(f) {
            const rows = WEEK.map((w, dayIdx) => {
                const a = (f.availability || []).find(x => x.day_of_week === dayIdx);
                const window = a && a.is_available
                    ? `${minutesTo12h(toMinutes(a.start_time || '00:00'))} – ${minutesTo12h(toMinutes(a.end_time || '00:00'))}`
                    : null;
                return `<tr>
                    <td>${w.day}</td>
                    <td>${a ? (a.is_available ? (window || 'Available') : 'Unavailable') : '<span class="sub">Not submitted</span>'}</td>
                </tr>`;
            }).join('');

            return `<table class="fc-avail-table"><thead><tr><th>Day</th><th>Availability</th></tr></thead><tbody>${rows}</tbody></table>`;
        }

        async function recordFacultyOverride(facultyId, conflictType, reason) {
            const { subject, day, startRow, endRow } = pendingPlot;
            try {
                await fetch(CONFIG.facultyOverrideUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({
                        faculty_id: facultyId,
                        academic_year: CONTEXT.academic_year,
                        program: CONTEXT.program,
                        year_level: CONTEXT.year_level,
                        section: CONTEXT.section,
                        subject_code: subject.code,
                        day: day,
                        start: rowToHHMM(startRow),
                        end: rowToHHMM(endRow),
                        conflict_type: conflictType,
                        reason: reason || '',
                    }),
                });
            } catch (e) {
                // Best-effort audit trail — never blocks the actual plot.
            }
        }

        async function openFacultyModal(subject, day, startRow, endRow, roomName, reassignIndex) {
            pendingPlot = { subject, day, startRow, endRow, roomName, reassignIndex: reassignIndex ?? null };

            document.getElementById('facultyModalSub').textContent =
                `${subject.code} - ${subject.title} · ${WEEK[day].day}, ${rowTo12h(startRow)} - ${rowTo12h(endRow)} · ${roomName}`;
            document.getElementById('facultyModalWarnings').innerHTML = '';

            const list = document.getElementById('facultyModalList');
            list.innerHTML = '<p class="sub">Loading faculty…</p>';
            facultyModal.classList.add('open');

            let data;
            try {
                const url = CONFIG.facultyPanelUrl + '?for_academic_year=' + encodeURIComponent(CONTEXT.academic_year);
                const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
                data = await response.json();
            } catch (e) {
                list.innerHTML = '<p class="sub">Could not load faculty. You can still skip and assign later.</p>';
                return;
            }

            const start = rowToHHMM(startRow);
            const end = rowToHHMM(endRow);
            const statusRank = { preferred: 0, mismatch: 1, unavailable: 2, conflict: 3 };

            const candidates = (data.faculty || []).map(f => {
                const slot = classifyFacultySlot(f, subject, day, start, end, pendingPlot.reassignIndex != null);
                const overCap = data.max_units && (f.units + (parseFloat(subject.units) || 0) > data.max_units);
                return { f, slot, overCap };
            }).sort((a, b) => {
                const r = statusRank[a.slot.status] - statusRank[b.slot.status];
                return r !== 0 ? r : a.f.name.localeCompare(b.f.name);
            });

            if (!candidates.length) {
                list.innerHTML = '<p class="sub">No faculty members in your department yet. You can still skip and assign later.</p>';
                return;
            }

            function renderCards(filterText) {
                list.innerHTML = '';
                const needle = (filterText || '').trim().toLowerCase();

                candidates
                    .filter(({ f }) => !needle || f.name.toLowerCase().includes(needle))
                    .forEach(({ f, slot, overCap }) => {
                        const meta = SLOT_STATUS_META[slot.status];
                        const card = document.createElement('div');
                        card.className = 'faculty-card' + (slot.status === 'conflict' ? ' fc-disabled' : '');

                        let tags = `<span class="fc-tag ${slot.status}">${meta.label}</span>`;
                        if (overCap) tags += ' <span class="fc-tag conflict">over unit cap</span>';

                        const empLabel = f.employment_status === 'full_time' ? 'Full-Time' : f.employment_status === 'part_time' ? 'Part-Time' : '';
                        const initials = f.name.split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();

                        const dayAvail = (f.availability || []).find(a => a.day_of_week === day);
                        const todayWindow = dayAvail
                            ? (dayAvail.is_available
                                ? `${minutesTo12h(toMinutes(dayAvail.start_time || '00:00'))} – ${minutesTo12h(toMinutes(dayAvail.end_time || '00:00'))}`
                                : 'Not available')
                            : 'Not submitted';

                        card.innerHTML = `
                            <div class="fc-band ${slot.status}">
                                <div class="fc-avatar">${initials}</div>
                                <div class="fc-head-text">
                                    <div class="fc-name">${f.name}${empLabel ? ` <span class="fc-emp">· ${empLabel}</span>` : ''}</div>
                                    <div>${tags}</div>
                                </div>
                            </div>
                            <div class="fc-body">
                                <div class="fc-meta">${f.units} units · ${f.weekly_hours} hrs/week · ${f.subjects.length} subject(s) currently</div>
                                <div class="fc-meta"><strong>${WEEK[day].day} availability:</strong> ${todayWindow}</div>
                                <div class="fc-meta" style="margin-top:2px;"><strong>Preferences:</strong> ${facultyPreferenceChips(f.preferences)}</div>
                                ${slot.status === 'conflict' ? `<div class="fc-meta" style="color:#991b1b;">Already teaching ${slot.meeting.code || 'another class'} ${WEEK[day].day} ${rowTo12h(Math.round(toMinutes(slot.meeting.start) / 30))}–${rowTo12h(Math.round(toMinutes(slot.meeting.end) / 30))}.</div>` : ''}
                                <div class="fc-avail-panel" style="display:none; margin-top:6px;"></div>
                                <div class="fc-actions">
                                    <button type="button" class="fc-btn fc-schedule">View Schedule</button>
                                    <button type="button" class="fc-btn fc-btn-primary fc-assign" ${slot.status === 'conflict' ? 'disabled' : ''}>Assign</button>
                                </div>
                            </div>
                        `;

                        const scheduleBtn = card.querySelector('.fc-schedule');
                        const panel = card.querySelector('.fc-avail-panel');
                        scheduleBtn.addEventListener('click', () => {
                            const open = panel.style.display !== 'none';
                            panel.style.display = open ? 'none' : 'block';
                            scheduleBtn.textContent = open ? 'View Schedule' : 'Hide Schedule';
                            if (!open && !panel.dataset.rendered) {
                                panel.innerHTML = facultyAvailabilityTable(f);
                                panel.dataset.rendered = '1';
                            }
                        });

                        const assignBtn = card.querySelector('.fc-assign');
                        if (slot.status === 'conflict') {
                            // Hard block: two classes cannot occupy the same
                            // faculty at the same time — not overridable.
                        } else if (slot.status === 'unavailable') {
                            assignBtn.addEventListener('click', async () => {
                                const proceed = await confirmDialog({
                                    title: 'Faculty Availability Conflict',
                                    message: `${f.name} is marked unavailable for ${WEEK[day].day} ${rowTo12h(startRow)}–${rowTo12h(endRow)}. `
                                        + 'Choose "Cancel" to pick a different schedule or faculty member, or "Proceed Anyway" to assign them regardless (this will be recorded).',
                                    confirmLabel: 'Proceed Anyway',
                                    danger: true,
                                });
                                if (!proceed) return;
                                const reason = window.prompt('Optional: reason for overriding this availability conflict', '') || '';
                                await recordFacultyOverride(f.id, 'unavailable', reason);
                                finalizePlot(f.id, f.name);
                            });
                        } else {
                            assignBtn.addEventListener('click', () => finalizePlot(f.id, f.name));
                        }

                        list.appendChild(card);
                    });
            }

            renderCards('');
            const searchInput = document.getElementById('facultyModalSearch');
            searchInput.value = '';
            searchInput.oninput = () => renderCards(searchInput.value);
        }

        function closeFacultyModal() {
            pendingPlot = null;
            facultyModal.classList.remove('open');
        }

        document.getElementById('facultyModalSkip').addEventListener('click', () => finalizePlot(null, null));
        document.getElementById('facultyModalCancel').addEventListener('click', closeFacultyModal);
        facultyModal.addEventListener('click', e => { if (e.target === facultyModal) closeFacultyModal(); });

        // ---------- autosave (insta-plot) ----------
        function scheduleSave() {
            setSync('saving', 'Saving…');
            clearTimeout(saveTimer);
            saveTimer = setTimeout(doSave, 250);
        }

        async function doSave() {
            if (saving) { saveQueued = true; return; }
            saving = true;

            try {
                const response = await fetch(CONFIG.syncUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ context: CONTEXT, blocks: blocks }),
                });
                const result = await response.json();

                if (response.ok && result.ok) {
                    setSync('saved', 'All changes saved');
                } else {
                    // Server rejected the change (e.g. a conflict won the
                    // race): fall back to the server's truth.
                    showToast(result.error || 'Change rejected by the server.', 'error');
                    await reloadFromServer();
                    setSync('saved', 'All changes saved');
                }
            } catch (err) {
                setSync('error', 'Offline — retrying…');
                setTimeout(scheduleSave, 3000);
            } finally {
                saving = false;
                if (saveQueued) {
                    saveQueued = false;
                    doSave();
                }
            }
        }

        async function reloadFromServer() {
            const query = new URLSearchParams(CONTEXT).toString();
            const response = await fetch(`${CONFIG.stateUrl}?${query}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) return;
            const data = await response.json();
            stateVersion = data.version || '';
            blocks = data.blocks || [];
            OTHERS = data.others || [];
            if (Array.isArray(data.assignments)) ASSIGNMENTS = data.assignments;
            renderBlocks();
        }

        // Filters switch context instantly — everything is already saved.
        document.querySelectorAll('#contextForm .context-select').forEach(sel =>
            sel.addEventListener('change', () => sel.form.submit()));

        // Only unsent changes can be lost now: warn if a save is in flight.
        window.addEventListener('beforeunload', e => {
            if (saving || saveTimer && document.getElementById('syncStatus').classList.contains('saving')) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // ---------- real-time sync (long-polling) ----------
        // The server holds each request open until the schedule changes, so
        // edits from other tabs/users appear in under a second.
        async function longPollLoop() {
            for (;;) {
                try {
                    const params = new URLSearchParams(CONTEXT);
                    if (stateVersion) params.set('version', stateVersion);

                    const response = await fetch(`${CONFIG.stateUrl}?${params.toString()}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!response.ok) throw new Error('poll failed');

                    const data = await response.json();
                    stateVersion = data.version || '';

                    // Notify the chairperson the moment a faculty member
                    // claims one of the plotted loads — or swaps it away.
                    if (Array.isArray(data.assignments)) {
                        const known = new Map(ASSIGNMENTS.map(a => [a.key, a.username]));
                        data.assignments.forEach(a => {
                            const label = `${a.code} (${a.section} · ${shortYear(a.year_level)})`;
                            if (!known.has(a.key)) {
                                showToast(`${label} was assigned to ${a.username}.`, 'success');
                            } else if (known.get(a.key) !== a.username) {
                                showToast(`${label} was reassigned from ${known.get(a.key)} to ${a.username}.`, 'success');
                            }
                        });
                        ASSIGNMENTS = data.assignments;
                    }

                    // Approvals queue: toast newly arrived requests.
                    if (Array.isArray(data.swap_approvals)) {
                        const knownIds = new Set(APPROVALS.map(a => a.id));
                        data.swap_approvals.forEach(a => {
                            if (!knownIds.has(a.id)) {
                                showToast(`Swap awaiting your approval: ${a.requester} ${a.requested ? '⇄' : '→'} ${a.target} (${a.offered}).`, 'success');
                            }
                        });
                        APPROVALS = data.swap_approvals;
                        renderApprovals();
                    }

                    // Change requests queue: toast newly arrived requests.
                    if (Array.isArray(data.change_requests)) {
                        const knownCrIds = new Set(CHANGE_REQUESTS.map(cr => cr.id));
                        data.change_requests.forEach(cr => {
                            if (!knownCrIds.has(cr.id)) {
                                showToast(`Change request from ${cr.faculty} for ${cr.subject}.`, 'success');
                            }
                        });
                        CHANGE_REQUESTS = data.change_requests;
                        renderChangeRequests();
                    }

                    if (typeof data.unread_notifications === 'number') {
                        window.UNREAD = data.unread_notifications;
                        if (window.updateNotifBadge) window.updateNotifBadge();
                    }

                    let changed = false;

                    if (Array.isArray(data.others) && JSON.stringify(data.others) !== JSON.stringify(OTHERS)) {
                        OTHERS = data.others;
                        changed = true;
                    }

                    // Adopt the server's copy of our own board only while no
                    // local change is being saved, so we never clobber a
                    // just-made edit.
                    if (Array.isArray(data.blocks) && !saving && !saveQueued
                        && !document.getElementById('syncStatus').classList.contains('saving')
                        && JSON.stringify(data.blocks) !== JSON.stringify(blocks)) {
                        blocks = data.blocks;
                        changed = true;
                    }

                    if (changed) {
                        renderBlocks();
                        // Refresh room availability if the modal is open.
                        if (modalArgs) openRoomModal(...modalArgs);
                    }
                } catch (err) {
                    // Offline or server hiccup: back off briefly, then resume.
                    await new Promise(resolve => setTimeout(resolve, 3000));
                }
            }
        }
        longPollLoop();

        // ---------- toast ----------
        let toastTimer = null;
        function showToast(message, kind) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast show' + (kind ? ' ' + kind : '');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
        }

        renderBlocks();
        renderApprovals();
        renderChangeRequests();
    </script>
<?= $this->endSection() ?>

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

<?= $this->section('styles') ?>
<style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        /* Scoped to .ac-main so this page can use scheduling-specific color
           aliases without changing the shared sidebar, topbar, or other
           portal pages. */
        .ac-main {
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
            --outline-variant:    var(--color-border);        }

        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: var(--surface);
            color: var(--on-surface);
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
        }

        /* Scoped to .ac-content — a bare .material-symbols-outlined rule here
           would directly set font-size on every icon with that class on the
           page, including the shared sidebar/topbar's nav icons (which
           normally just inherit ~13px from .ac-nav-item), making them
           render larger only on this page. */
        .ac-content .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            font-size: 20px;
            line-height: 1;
            vertical-align: middle;
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--outline-variant); border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--outline); }

        /* Page-specific plotting controls live with the page title. Keeping
           them out of the shared topbar makes navigation consistent with
           every other Chairperson Portal screen. */
        .plotting-page-head { align-items: center; }
        .plotting-page-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
        .plotting-page-actions__buttons { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
        .plotting-page-actions .btn { display: inline-flex; align-items: center; gap: 6px; min-height: 36px; padding: 8px 11px; font-size: 11.5px; }
        .plotting-page-actions .btn .material-symbols-outlined { font-size: 17px; }

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
            min-height: 34px;
            padding: 6px 10px;
            border: 1px solid #d9e4dd;
            border-radius: 999px;
            background: #f2fbf5;
            font-size: 11px;
            font-weight: 600;
            color: var(--on-surface-variant);
        }

        .sync-status .sync-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--success);
        }

        .sync-status.saving { border-color: #f2ddb4; background: #fff9ed; }
        .sync-status.saving .sync-dot { background: var(--warning); }
        .sync-status.error { border-color: #f2c9c5; background: #fff5f4; color: var(--error); }
        .sync-status.error .sync-dot { background: var(--error); }

        @media (max-width: 1280px) {
            .plotting-page-head { align-items: flex-start; }
            .plotting-page-actions { width: 100%; justify-content: space-between; }
        }
        @media (max-width: 680px) {
            .plotting-page-actions { align-items: stretch; flex-direction: column; }
            .plotting-page-actions__buttons { display: grid; grid-template-columns: 1fr 1fr; }
            .plotting-page-actions .btn { justify-content: center; min-height: 42px; }
            .sync-status { align-self: flex-start; }
        }

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

        /* The shared layout caps .ac-content at 1280px so normal text-heavy
           pages stay readable — but that wastes space here and forces the
           3-column plotting grid to scroll sooner than it needs to. This
           page gets the full width instead. */
        .ac-content { max-width: none; }

        /* ===== Layout ===== */
        .layout {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr) 300px;
            gap: 16px;
            padding: 16px 24px;
            align-items: start;
        }

        /* Grid items default to min-width:auto, so without this the middle
           column refuses to shrink below the plotting grid's 800px
           min-width and drags the whole page into horizontal overflow
           instead of scrolling inside its own .panel-scroll. */
        .layout > .panel { min-width: 0; }

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

        /* ===== Premium finish ===== */
        .grid .head {
            font-size: 10.5px;
            letter-spacing: .09em;
            padding: 12px 4px;
            background: linear-gradient(180deg, var(--surface-container-lowest), var(--surface-container-low));
        }
        .grid .time { font-size: 10.5px; font-weight: 500; letter-spacing: .02em; color: var(--outline); }
        .day-col { border-right: 1px solid rgba(15, 23, 42, .05); }
        .slot { border-bottom: 1px solid rgba(15, 23, 42, .07); }
        .slot:nth-child(odd) { border-bottom-style: dotted; border-bottom-color: rgba(15, 23, 42, .045); }

        .block {
            --bg: #eef2ff; --accent: #6366f1;
            border-radius: 10px;
            border: 1px solid rgba(15, 23, 42, .06);
            border-left: 3px solid var(--accent);
            background: linear-gradient(155deg, var(--bg) 0%, color-mix(in srgb, var(--bg) 55%, #fff) 100%);
            box-shadow: 0 1px 2px rgba(15, 23, 42, .06), 0 4px 10px -6px rgba(15, 23, 42, .18);
            padding: 6px 20px 5px 8px;
            transition: transform .16s ease, box-shadow .16s ease;
        }
        .block:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(15, 23, 42, .08), 0 10px 20px -8px rgba(15, 23, 42, .28); z-index: 4; }
        .block.blue   { --bg: #dbeafe; --accent: #3b82f6; }
        .block.green  { --bg: #dcfce7; --accent: #16a34a; }
        .block.purple { --bg: #ede9fe; --accent: #7c3aed; }
        .block.yellow { --bg: #fef3c7; --accent: #d97706; }
        .block.red    { --bg: #ffe4e6; --accent: #e11d48; }
        .block.cyan   { --bg: #cffafe; --accent: #0891b2; }

        .block .code  { font-size: 10px; font-weight: 800; letter-spacing: .06em; color: color-mix(in srgb, var(--accent) 80%, #000); }
        .block .title {
            font-size: 11.5px; font-weight: 650; line-height: 1.25; color: #0f172a;
            white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        }
        .block .meta { font-size: 10.5px; color: #475569; }
        .block .meta.prof { color: #0f172a; }
        .block.short .title { -webkit-line-clamp: 1; }
        .block.short .meta:not(.prof):nth-of-type(n+4) { display: none; }

        .block .remove {
            opacity: 0; width: 18px; height: 18px; top: 4px; right: 4px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 13px;
            background: rgba(255, 255, 255, .85); transition: opacity .15s, background .15s, color .15s;
        }
        .block:hover .remove, .block .remove:focus-visible { opacity: 1; }
        .block .remove:hover { background: #fee2e2; color: #dc2626; }

        /* Other sections: a quiet hatched backdrop, details on hover */
        .block.ghost {
            background: repeating-linear-gradient(135deg, #f4f5f8 0 6px, #eceef3 6px 12px);
            border: 1px solid rgba(15, 23, 42, .07);
            border-left: 3px solid #b6bcc9;
            box-shadow: none;
            opacity: .92;
            padding: 5px 6px;
        }
        .block.ghost:hover { opacity: 1; }
        .block.ghost .code { color: #64748b; }
        .block.ghost.compact .meta, .block.ghost.compact .section-tag { display: none; }
        .block.ghost.compact .code { font-size: 9.5px; }

        /* ===== AI assistant ===== */
        .ai-title { display: flex; align-items: center; gap: 8px; }
        .ai-spark {
            width: 24px; height: 24px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-size: 12px;
            box-shadow: 0 4px 10px -3px rgba(99, 102, 241, .55);
        }
        .ai-head { padding: 8px 10px; border-radius: 10px; background: var(--surface-container-low); }
        .ai-status { font-weight: 600; }
        .suggestion {
            border: 1px solid rgba(15, 23, 42, .07);
            border-left: 1px solid rgba(15, 23, 42, .07);
            border-radius: 14px;
            padding: 12px 14px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 8px 18px -12px rgba(15, 23, 42, .3);
            transition: transform .16s ease, box-shadow .16s ease;
        }
        .suggestion:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(15, 23, 42, .06), 0 14px 24px -14px rgba(15, 23, 42, .38); }
        .suggestion .impact { background: rgba(99, 102, 241, .1); color: #4f46e5; letter-spacing: .02em; padding: 3px 10px; }
        .suggestion .impact.good { background: rgba(99, 102, 241, .1); color: #4f46e5; }
        .suggestion .impact.warn { background: #fee2e2; color: #b91c1c; }
        .suggestion .title { font-size: 13px; }
        .suggestion .body { font-size: 12px; margin-bottom: 8px; }
        .ai-fit { display: flex; align-items: center; gap: 8px; margin: 0 0 8px; font-size: 11px; color: var(--on-surface-variant); }
        .ai-fit .bar { flex: 1; height: 4px; border-radius: 999px; background: var(--surface-container-high); overflow: hidden; }
        .ai-fit .bar i { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #6366f1, #22c55e); }
        .suggestion .ai-reasons { list-style: none; padding: 0; }
        .suggestion .ai-reasons li { position: relative; padding-left: 14px; margin-bottom: 2px; }
        .suggestion .ai-reasons li::before { content: "✓"; position: absolute; left: 0; color: #16a34a; font-weight: 700; }
        .suggestion .ai-apply { background: linear-gradient(135deg, #4f46e5, #7c3aed); border-radius: 8px; padding: 6px 14px; box-shadow: 0 6px 14px -6px rgba(79, 70, 229, .6); }
        .ai-fill { border-radius: 8px; font-weight: 700; }
        .ai-fill[hidden] { display: none; }
        .ai-section {
            display: flex; align-items: center; justify-content: space-between; margin: 14px 2px 8px;
            font-size: 10.5px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; color: var(--on-surface-variant);
        }
        .ai-section .count {
            min-width: 18px; text-align: center; padding: 1px 6px; border-radius: 999px;
            background: rgba(99, 102, 241, .12); color: #4f46e5; letter-spacing: 0;
        }
        .ai-prof { display: flex; align-items: center; gap: 8px; margin: 0 0 8px; font-size: 12px; font-weight: 600; color: #0f172a; }
        .ai-prof .avatar {
            width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0ea5e9, #6366f1); color: #fff; font-size: 11px; font-weight: 700;
        }
        .ai-prof .level { font-size: 10px; font-weight: 700; padding: 1px 8px; border-radius: 999px; text-transform: capitalize; background: #e2e8f0; color: #475569; }
        .ai-prof .level.preferred { background: #dcfce7; color: #166534; }
        .ai-prof .level.willing { background: #fef9c3; color: #854d0e; }
        .ai-prof .level.qualified { background: #dbeafe; color: #1e40af; }
        .ai-prof .level.pending { background: #fee2e2; color: #991b1b; text-transform: none; }
        .ai-prof-select { width: 100%; margin: 0 0 8px; padding: 6px 8px; border: 1px solid var(--outline-variant); border-radius: 8px; font-size: 12px; background: var(--surface-container-lowest); }
        .ai-note { margin: 0 0 8px; padding: 6px 10px; border-radius: 8px; background: #fef3c7; color: #92400e; font-size: 11.5px; }
        .ai-now { margin: 0 0 8px; font-size: 11.5px; color: var(--on-surface-variant); }
        .ai-done { text-align: center; padding: 22px 12px; color: var(--on-surface-variant); font-size: 12.5px; }
        .ai-done .material-symbols-outlined { display: block; margin: 0 auto 6px; font-size: 34px; color: #16a34a; }

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

        .ai-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin: 10px 0 8px; }
        .ai-status { font-size: 12px; color: var(--on-surface-variant); }
        .ai-fill { font-size: 12px; padding: 4px 10px; }
        .suggestion { margin-bottom: 10px; }
        .suggestion .impact.good { background: var(--success, #16a34a); }
        .suggestion .impact.warn { background: var(--error, #dc2626); }
        .suggestion .ai-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .suggestion .ai-apply { background: var(--primary-container); color: #fff; border: 0; border-radius: 6px; padding: 4px 12px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .suggestion .ai-apply:hover { filter: brightness(1.1); }
        .suggestion .ai-alt-toggle { background: none; border: 0; padding: 0; font-size: 12px; color: var(--primary-container); font-weight: 600; cursor: pointer; text-decoration: underline; }
        .suggestion .ai-reasons { margin: 0 0 8px; padding-left: 16px; font-size: 11.5px; color: var(--on-surface-variant); }
        .suggestion .ai-alts { margin-top: 8px; border-top: 1px dashed var(--outline-variant); padding-top: 6px; }
        .suggestion .ai-alt { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 12px; padding: 3px 0; }
        .suggestion .ai-alt button { background: none; border: 1px solid var(--outline-variant); border-radius: 6px; font-size: 11px; padding: 2px 8px; cursor: pointer; }

        /* ===== Room assignment modal ===== */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity var(--motion-normal, 220ms) var(--ease-standard, ease), visibility 0s linear var(--motion-normal, 220ms);
        }

        .modal-backdrop.open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transition: opacity var(--motion-normal, 220ms) var(--ease-standard, ease), visibility 0s linear 0s;
        }

        .modal {
            background: var(--surface-container-lowest);
            border-radius: var(--radius-lg);
            width: 440px;
            max-width: calc(100vw - 32px);
            padding: 24px;
            box-shadow: var(--shadow-lg);
            transform: scale(.97) translateY(8px);
            opacity: 0;
            transition: transform var(--motion-normal, 220ms) var(--ease-enter, ease-out), opacity var(--motion-normal, 220ms) var(--ease-enter, ease-out);
        }

        .modal-backdrop.open .modal {
            transform: scale(1) translateY(0);
            opacity: 1;
        }

        .modal h3 { font-size: 17px; font-weight: 800; letter-spacing: -.01em; color: var(--on-surface); margin-bottom: 4px; }

        .modal .sub { font-size: 12.5px; color: var(--on-surface-variant); margin-bottom: 16px; line-height: 1.5; }

        .modal .modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--outline-variant);
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

        /* Faculty assignment modal: one comparison list with fixed context and actions. */
        .faculty-picker {
            width: min(900px, calc(100vw - 32px));
            height: min(760px, calc(100dvh - 32px));
            max-height: calc(100dvh - 32px);
            padding: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border-radius: 10px;
            border: 1px solid rgba(15, 62, 153, .12);
            box-shadow: 0 24px 70px rgba(15, 23, 42, .22), 0 2px 8px rgba(15, 62, 153, .08);
        }
        .faculty-picker__head { flex: 0 0 auto; padding: 22px 24px 18px; border-bottom: 1px solid #dbe5f5; background: linear-gradient(135deg, #ffffff 0%, #f4f7ff 100%); }
        .faculty-picker__title-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
        .faculty-picker__head h3 { margin: 0; font-size: 19px; font-weight: 700; }
        .faculty-picker__close { width: 36px; height: 36px; border: 0; border-radius: 6px; background: transparent; color: var(--on-surface-variant); cursor: pointer; }
        .faculty-picker__close:hover { background: var(--surface-container-high); color: var(--on-surface); }
        .faculty-picker__close:focus-visible, .faculty-filter:focus-visible, .faculty-card .fc-btn:focus-visible { outline: 2px solid var(--primary-container); outline-offset: 2px; }
        .faculty-picker__context { display: grid; grid-template-columns: auto 1fr; gap: 2px 12px; margin-top: 14px; }
        .faculty-picker__code { grid-row: 1 / 3; align-self: center; padding: 6px 9px; border-radius: 6px; background: #e8efff; color: #0b46b5; font-size: 12px; font-weight: 750; letter-spacing: .015em; }
        .faculty-picker__subject { font-size: 14px; font-weight: 600; color: var(--on-surface); overflow-wrap: anywhere; }
        .faculty-picker__meeting { font-size: 12px; color: var(--on-surface-variant); }
        .faculty-picker__toolbar { flex: 0 0 auto; padding: 14px 24px 0; background: #fff; }
        .faculty-picker__search-wrap { position: relative; }
        .faculty-picker__search-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--outline); font-size: 18px; pointer-events: none; }
        .faculty-picker__search { width: 100%; min-height: 40px; padding: 9px 12px 9px 38px; border: 1px solid #ccd8ec; border-radius: 7px; font: inherit; font-size: 13px; background: #f8faff; transition: border-color .15s, box-shadow .15s, background .15s; }
        .faculty-picker__search:focus { outline: none; border-color: var(--primary-container); background: #fff; box-shadow: 0 0 0 3px rgba(0, 63, 163, .12); }
        .faculty-picker__filters { display: flex; gap: 18px; margin-top: 10px; border-bottom: 1px solid var(--outline-variant); overflow-x: auto; }
        .faculty-filter { min-height: 38px; padding: 8px 0; border: 0; border-bottom: 2px solid transparent; background: transparent; color: var(--on-surface-variant); font: inherit; font-size: 12px; cursor: pointer; white-space: nowrap; }
        .faculty-filter:hover { color: var(--on-surface); }
        .faculty-filter.active { color: #073f9c; border-bottom-color: #0b57d0; font-weight: 700; }
        .faculty-picker__results { min-height: 0; flex: 1 1 auto; overflow-y: auto; padding: 0 24px 10px; scrollbar-gutter: stable; background: linear-gradient(#fff, #fbfcff); }
        .faculty-picker__summary { position: sticky; top: 0; z-index: 2; margin: 0; padding: 10px 0 8px; background: var(--surface-container-lowest); color: var(--on-surface-variant); font-size: 11.5px; }
        .fc-grid { display: flex; flex-direction: column; gap: 6px; }
        .faculty-card {
            display: grid; grid-template-columns: minmax(220px, 1.1fr) minmax(300px, 1.5fr) auto;
            align-items: center; gap: 20px; padding: 15px 12px; border: 1px solid transparent; border-radius: 8px;
            background: #fff; transition: background-color 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
        }
        .faculty-card:hover { background: #fff; border-color: #d5dfef; box-shadow: 0 5px 18px rgba(30, 64, 125, .08); }
        .faculty-card.fc-recommended-row { background: linear-gradient(100deg, #f3f7ff 0%, #ffffff 76%); border-color: #cbdcff; box-shadow: inset 3px 0 0 #0b57d0; }
        .faculty-card.fc-tone-unavailable { background: #fffafa; border-color: #f8dddd; }
        .faculty-card.fc-tone-conflict { background: #fafbfc; border-color: #e3e7ed; }
        .faculty-card.fc-disabled { color: var(--on-surface-variant); }
        .fc-identity { display: flex; align-items: flex-start; gap: 12px; min-width: 0; }
        .faculty-card .fc-avatar { width: 36px; height: 36px; border-radius: 50%; background: #e6edff; color: #1246a3; border: 1px solid #d3dfff; display: flex; align-items: center; justify-content: center; font-weight: 750; font-size: 12px; flex: 0 0 auto; }
        .faculty-card.fc-tone-preferred .fc-avatar, .faculty-card.fc-tone-qualified .fc-avatar { background: #e7f7ef; color: #087443; border-color: #c9ead9; }
        .faculty-card.fc-tone-willing .fc-avatar { background: #fff4dc; color: #915400; border-color: #f5deb0; }
        .faculty-card.fc-tone-unavailable .fc-avatar { background: #feecec; color: #b42318; border-color: #f5d0d0; }
        .faculty-card.fc-tone-conflict .fc-avatar { background: #edf0f4; color: #586474; border-color: #dce1e8; }
        .faculty-card .fc-head-text { min-width: 0; }
        .faculty-card .fc-name { font-weight: 650; font-size: 13px; overflow-wrap: anywhere; }
        .faculty-card .fc-emp { display: block; margin-top: 2px; font-size: 11.5px; color: var(--on-surface-variant); }
        .fc-status { display: flex; align-items: flex-start; gap: 7px; margin-top: 7px; font-size: 11.5px; line-height: 1.35; }
        .fc-status::before { content: ''; width: 7px; height: 7px; margin-top: 4px; border-radius: 50%; background: var(--outline); flex: none; }
        .fc-status.preferred::before, .fc-status.qualified::before { background: var(--success); }
        .fc-status.willing::before, .fc-status.mismatch::before { background: var(--warning); }
        .fc-status.unavailable::before, .fc-status.conflict::before { background: var(--error); }
        .fc-status strong { display: block; color: var(--on-surface); font-weight: 650; }
        .fc-status.preferred strong, .fc-status.qualified strong { color: #087443; }
        .fc-status.willing strong, .fc-status.mismatch strong { color: #915400; }
        .fc-status.unavailable strong, .fc-status.conflict strong { color: #b42318; }
        .fc-status span { display: block; color: var(--on-surface-variant); }
        .fc-recommended { display: inline-block; margin-left: 7px; padding: 2px 6px; border-radius: 999px; background: #dfe9ff; color: #0947ad; font-size: 9px; font-weight: 750; text-transform: uppercase; letter-spacing: .045em; vertical-align: 1px; }
        .fc-facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; min-width: 0; }
        .fc-fact dt { margin: 0 0 3px; color: var(--on-surface-variant); font-size: 10.5px; font-weight: 500; }
        .fc-fact dd { margin: 0; color: var(--on-surface); font-size: 12px; line-height: 1.35; overflow-wrap: anywhere; }
        .fc-fact dd.is-conflict { color: var(--error); }
        .fc-load-track { height: 3px; margin-top: 6px; background: var(--surface-container-high); overflow: hidden; }
        .fc-load-fill { height: 100%; background: linear-gradient(90deg, #0b57d0, #4f7fe5); }
        .fc-load-fill.is-over { background: var(--error); }
        .faculty-card .fc-actions { display: flex; flex-direction: column; gap: 7px; width: 118px; }
        .faculty-card .fc-btn { min-height: 34px; padding: 7px 10px; border-radius: 5px; font: inherit; font-size: 11.5px; font-weight: 600; cursor: pointer; text-align: center; border: 1px solid var(--outline-variant); background: var(--surface); color: var(--on-surface); }
        .faculty-card .fc-btn:hover:not(:disabled) { border-color: var(--primary-container); color: var(--primary-container); }
        .faculty-card .fc-btn.fc-btn-primary { background: linear-gradient(135deg, #0b57d0, #073f9c); color: #fff; border-color: #0b4db8; box-shadow: 0 3px 8px rgba(11, 87, 208, .18); }
        .faculty-card .fc-btn.fc-btn-primary:hover:not(:disabled) { background: linear-gradient(135deg, #1464e2, #0b46ae); color: #fff; box-shadow: 0 5px 12px rgba(11, 87, 208, .24); }
        .faculty-card .fc-btn.fc-btn-warning { background: #fff6f5; color: #b42318; border-color: #f2c9c5; }
        .faculty-card .fc-btn:disabled { cursor: not-allowed; color: var(--outline); background: var(--surface-container-low); }
        .fc-avail-panel { grid-column: 1 / -1; padding: 12px 0 0 48px; }
        .fc-avail-table { width: min(100%, 520px); border-collapse: collapse; font-size: 11.5px; }
        .fc-avail-table th, .fc-avail-table td { text-align: left; padding: 7px 8px; border-bottom: 1px solid var(--outline-variant); }
        .fc-avail-table th { color: var(--on-surface-variant); font-weight: 600; }
        .faculty-picker__state { display: grid; place-items: center; min-height: 220px; padding: 32px; text-align: center; color: var(--on-surface-variant); }
        .faculty-picker__state strong { display: block; color: var(--on-surface); font-size: 14px; margin-bottom: 5px; }
        .faculty-picker__retry { margin-top: 14px; }
        .fc-skeleton { display: grid; grid-template-columns: 1fr 1.5fr 118px; gap: 20px; padding: 20px 0; border-bottom: 1px solid var(--outline-variant); }
        .fc-skeleton span { display: block; height: 12px; border-radius: 3px; background: var(--surface-container-high); animation: fcPulse 1s ease-in-out infinite alternate; }
        @keyframes fcPulse { to { opacity: .45; } }
        .faculty-picker__legend { flex: 0 0 auto; margin: 0; padding: 10px 24px; border-top: 1px solid #e5eaf2; color: #66531b; background: #fffaf0; font-size: 11px; line-height: 1.4; }
        .faculty-picker__footer { flex: 0 0 auto; display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 14px 24px; border-top: 1px solid #dfe6f0; background: #f8faff; }
        .faculty-picker__footer .actions { margin: 0; }
        .confirm-reason { margin-top: 14px; }
        .confirm-reason label { display: block; margin-bottom: 6px; color: var(--on-surface); font-size: 12px; font-weight: 600; }
        .confirm-reason textarea { width: 100%; min-height: 72px; resize: vertical; padding: 9px 10px; border: 1px solid var(--outline-variant); border-radius: 6px; font: inherit; font-size: 12px; }
        @media (max-width: 760px), (min-resolution: 1.2dppx) and (max-width: 1100px) {
            .faculty-picker { width: min(720px, calc(100vw - 24px)); height: min(820px, calc(100dvh - 24px)); }
            .faculty-card { grid-template-columns: 1fr auto; gap: 14px; }
            .fc-facts { grid-column: 1 / -1; grid-row: 2; }
            .faculty-card .fc-actions { grid-column: 2; grid-row: 1; }
            .fc-avail-panel { grid-row: 3; padding-left: 48px; }
        }
        @media (max-width: 560px) {
            .faculty-picker { width: 100vw; height: 100dvh; max-width: none; max-height: none; border-radius: 0; }
            .faculty-picker__head, .faculty-picker__toolbar, .faculty-picker__results, .faculty-picker__legend, .faculty-picker__footer { padding-left: 16px; padding-right: 16px; }
            .faculty-picker__context { grid-template-columns: 1fr; }
            .faculty-picker__code { grid-row: auto; }
            .faculty-card { grid-template-columns: 1fr; gap: 12px; padding: 18px 0; }
            .fc-facts { grid-column: 1; grid-row: auto; grid-template-columns: 1fr 1fr; }
            .fc-fact:last-child { grid-column: 1 / -1; }
            .faculty-card .fc-actions { grid-column: 1; grid-row: auto; width: 100%; flex-direction: row; }
            .faculty-card .fc-btn { flex: 1; min-height: 44px; }
            .fc-avail-panel { grid-row: auto; padding-left: 0; }
            .faculty-picker__footer { align-items: stretch; flex-direction: column-reverse; }
            .faculty-picker__footer .actions { display: flex; }
            .faculty-picker__footer .btn { min-height: 44px; }
        }
        @media (prefers-reduced-motion: reduce) { .fc-skeleton span { animation: none; } }

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
        .overview-modal { width: min(1120px, calc(100vw - 32px)); height: min(820px, calc(100dvh - 32px)); max-height: calc(100dvh - 32px); display: flex; flex-direction: column; padding: 0; overflow: hidden; border: 1px solid rgba(15, 62, 153, .12); box-shadow: 0 24px 70px rgba(15, 23, 42, .22), 0 2px 8px rgba(15, 62, 153, .08); }

        .ov-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 22px 24px;
            border-bottom: 1px solid var(--outline-variant);
            background: linear-gradient(135deg, #fff 0%, #f4f7ff 100%);
            flex: 0 0 auto;
        }

        .ov-head__title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .ov-head h3 { font-size: 18px; font-weight: 800; margin: 0; }

        .ov-summary-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
            background: var(--success-container);
            color: #14532d;
        }

        .ov-summary-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--success); }

        .ov-head .sub { margin: 6px 0 0; font-size: 12.5px; }

        .ov-body { flex: 1; min-height: 0; display: flex; flex-direction: column; padding: 18px 24px 0; background: #fbfcff; }

        .ov-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--on-surface-variant);
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: .04em;
        }

        .ov-day-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; margin-bottom: 14px; }
        .day-chips {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 0;
        }

        .day-chip {
            padding: 7px 14px;
            border-radius: 999px;
            border: 1px solid var(--outline-variant);
            background: #fff;
            color: #374151;
            font-size: 12.5px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background .15s, border-color .15s, color .15s;
        }

        .day-chip:hover { border-color: #93c5fd; }

        .day-chip.active {
            background: linear-gradient(135deg, #0b57d0, #073f9c);
            border-color: #0b4db8;
            color: #fff;
            box-shadow: 0 3px 8px rgba(11, 87, 208, .2);
        }

        .day-chip:focus-visible, .ov-filter:focus-visible, .ov-search:focus-visible, .overview-rooms .room-card:focus-visible { outline: 2px solid #0b57d0; outline-offset: 2px; }

        .ov-metrics { display: grid; grid-template-columns: repeat(3, minmax(90px, 1fr)); gap: 8px; min-width: 360px; }
        .ov-metric { padding: 8px 11px; border: 1px solid #dce4f0; border-radius: 8px; background: #fff; }
        .ov-metric__value { display: block; font-size: 16px; line-height: 1.1; font-weight: 750; color: var(--on-surface); }
        .ov-metric__label { display: block; margin-top: 3px; color: var(--on-surface-variant); font-size: 10.5px; }
        .ov-metric.free .ov-metric__value { color: #087443; }
        .ov-metric.busy .ov-metric__value { color: #b42318; }

        .ov-workspace { flex: 1; min-height: 0; display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(310px, .8fr); gap: 16px; }
        .ov-room-browser, .ov-schedule-pane { min-height: 0; border: 1px solid #dce4f0; border-radius: 10px 10px 0 0; background: #fff; overflow: hidden; }
        .ov-room-browser { display: flex; flex-direction: column; }
        .ov-room-toolbar { flex: 0 0 auto; padding: 14px 16px 12px; border-bottom: 1px solid #e4eaf3; background: #fff; }
        .ov-room-toolbar__top { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .ov-search-wrap { position: relative; width: min(280px, 100%); }
        .ov-search-wrap .material-symbols-outlined { position: absolute; top: 50%; left: 10px; transform: translateY(-50%); color: #778397; font-size: 17px; pointer-events: none; }
        .ov-search { width: 100%; min-height: 36px; padding: 8px 10px 8px 34px; border: 1px solid #ccd8ec; border-radius: 7px; background: #f8faff; font: inherit; font-size: 12px; }
        .ov-filters { display: flex; gap: 5px; margin-top: 10px; overflow-x: auto; }
        .ov-filter { min-height: 30px; padding: 5px 10px; border: 1px solid #d7dfeb; border-radius: 999px; background: #fff; color: var(--on-surface-variant); font: inherit; font-size: 11px; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .ov-filter.active { border-color: #bfd1f4; background: #eaf1ff; color: #0b46a3; }
        .ov-room-scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 14px 16px 20px; }
        .ov-schedule-pane { display: flex; flex-direction: column; }
        .ov-schedule-head { flex: 0 0 auto; padding: 15px 16px 13px; border-bottom: 1px solid #e4eaf3; background: linear-gradient(135deg, #f6f9ff, #fff); }
        .ov-schedule-head .ov-label { margin-bottom: 4px; }
        .ov-schedule-title { font-size: 15px; font-weight: 750; color: var(--on-surface); }
        .ov-schedule-meta { margin-top: 3px; font-size: 11px; color: var(--on-surface-variant); }

        .ov-legend { display: flex; gap: 12px; flex-wrap: wrap; font-size: 10.5px; color: var(--on-surface-variant); }
        .ov-legend span { display: inline-flex; align-items: center; gap: 5px; }
        .ov-legend i { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .ov-legend .lg-green { background: var(--success); }
        .ov-legend .lg-yellow { background: var(--warning); }
        .ov-legend .lg-red { background: var(--error); }

        .overview-rooms { grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); margin-bottom: 18px; }

        .ov-floor-group { margin-bottom: 18px; }
        .ov-floor-group:last-child { margin-bottom: 0; }

        .floor-heading.ov-floor-heading {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            padding: 0 0 8px;
        }

        .floor-heading.ov-floor-heading .count { font-weight: 500; color: var(--outline); text-transform: none; letter-spacing: 0; }

        .room-card.selected {
            outline: none;
            border-color: #6e99e8;
            box-shadow: inset 3px 0 0 #0b57d0, 0 4px 12px rgba(11, 87, 208, .12);
        }

        /* Overview-only room card additions (type/capacity badges + timeline
           bar) — scoped under .overview-rooms so the simpler room-picker
           modal (roomModal/facultyModal, which shares the base .room-card
           class) is unaffected. */
        .overview-rooms .room-card {
            align-items: stretch;
            text-align: left;
            padding: 12px;
            border-radius: 8px;
            transition: transform .15s, box-shadow .15s, border-color .15s;
        }
        .overview-rooms .room-card:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(30, 64, 125, .1); }

        .ov-card-top { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 2px; }

        .ov-type-badge {
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
            padding: 2px 7px;
            border-radius: 5px;
            background: var(--surface-container-high);
            color: var(--on-surface-variant);
        }

        .ov-type-badge.is-lab { background: rgba(0,64,166,.1); color: var(--primary-container); }

        .ov-capacity { font-size: 10.5px; font-weight: 600; color: var(--on-surface-variant); white-space: nowrap; }

        .ov-timeline { margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,.06); }
        .ov-timeline-track { position: relative; height: 7px; border-radius: 999px; background: var(--success-container); overflow: hidden; }
        .ov-timeline-seg { position: absolute; top: 0; bottom: 0; background: var(--warning); }
        .ov-timeline-seg.is-red { background: var(--error); }

        .overview-list { flex: 1; min-height: 0; overflow-y: auto; }

        .overview-row {
            display: grid;
            grid-template-columns: 96px minmax(0, 1fr);
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--surface-dim);
            align-items: flex-start;
        }

        .overview-row:last-child { border-bottom: none; }

        .overview-row .ov-time {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--on-surface);
        }

        .overview-row .ov-details { min-width: 0; font-size: 12px; }
        .overview-row .ov-details .code { font-weight: 700; }
        .overview-row .ov-details .meta { color: var(--on-surface-variant); }

        .overview-row .ov-section {
            grid-column: 2;
            justify-self: start;
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
            display: grid;
            place-items: center;
            min-height: 180px;
            padding: 24px 18px;
            text-align: center;
            font-size: 12.5px;
            color: var(--on-surface-variant);
        }
        .overview-empty strong { display: block; margin-bottom: 4px; color: var(--on-surface); font-size: 13px; }
        .ov-no-results { padding: 42px 16px; text-align: center; color: var(--on-surface-variant); font-size: 12px; }
        .ov-footer { flex: 0 0 auto; display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 13px 24px; border-top: 1px solid #dfe6f0; background: #f8faff; }
        .ov-footer__note { color: var(--on-surface-variant); font-size: 11px; }

        @media (max-width: 850px) {
            .overview-modal { width: min(760px, calc(100vw - 24px)); height: min(900px, calc(100dvh - 24px)); }
            .ov-day-row { align-items: stretch; flex-direction: column; }
            .ov-metrics { min-width: 0; }
            .ov-workspace { display: flex; flex-direction: column; overflow-y: auto; }
            .ov-room-browser { flex: 0 0 auto; overflow: visible; }
            .ov-room-scroll { overflow: visible; max-height: 460px; }
            .ov-schedule-pane { flex: 0 0 360px; }
        }
        @media (max-width: 560px) {
            .overview-modal { width: 100vw; height: 100dvh; max-height: none; max-width: none; border-radius: 0; }
            .ov-head, .ov-body, .ov-footer { padding-left: 16px; padding-right: 16px; }
            .ov-head__title-row { align-items: flex-start; }
            .ov-summary-badge { width: 100%; }
            .ov-metrics { grid-template-columns: repeat(3, 1fr); }
            .ov-room-toolbar__top { align-items: flex-start; flex-direction: column; }
            .ov-search-wrap { width: 100%; }
            .overview-rooms { grid-template-columns: 1fr; }
            .ov-footer__note { display: none; }
            .ov-footer .btn { width: 100%; min-height: 44px; }
        }

        /* ===== Department faculty directory ===== */
        .faculty-directory { width: min(900px, calc(100vw - 32px)); height: min(760px, calc(100dvh - 32px)); max-height: calc(100dvh - 32px); display: flex; flex-direction: column; padding: 0; overflow: hidden; border: 1px solid rgba(15, 62, 153, .12); box-shadow: 0 24px 70px rgba(15, 23, 42, .22), 0 2px 8px rgba(15, 62, 153, .08); }
        .fd-head { flex: 0 0 auto; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding: 22px 24px 18px; border-bottom: 1px solid #dbe5f5; background: linear-gradient(135deg, #fff 0%, #f4f7ff 100%); }
        .fd-title-row { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
        .fd-head h3 { margin: 0; font-size: 19px; font-weight: 750; }
        .fd-count { display: inline-flex; align-items: center; padding: 3px 8px; border-radius: 999px; background: #e8efff; color: #0b46a3; font-size: 10.5px; font-weight: 700; }
        .fd-head .sub { margin: 6px 0 0; }
        .fd-close { width: 36px; height: 36px; border: 0; border-radius: 6px; background: transparent; color: var(--on-surface-variant); cursor: pointer; }
        .fd-close:hover { background: #e9eef7; color: var(--on-surface); }
        .fd-close:focus-visible, .fd-filter:focus-visible, .fd-search:focus-visible, .fd-details-btn:focus-visible { outline: 2px solid #0b57d0; outline-offset: 2px; }
        .fd-toolbar { flex: 0 0 auto; padding: 14px 24px 0; background: #fff; }
        .fd-search-wrap { position: relative; }
        .fd-search-wrap .material-symbols-outlined { position: absolute; top: 50%; left: 11px; transform: translateY(-50%); color: #778397; font-size: 18px; pointer-events: none; }
        .fd-search { width: 100%; min-height: 40px; padding: 9px 12px 9px 38px; border: 1px solid #ccd8ec; border-radius: 7px; background: #f8faff; font: inherit; font-size: 13px; }
        .fd-search:focus { outline: none; border-color: #0b57d0; background: #fff; box-shadow: 0 0 0 3px rgba(0, 63, 163, .12); }
        .fd-filters { display: flex; gap: 18px; margin-top: 9px; border-bottom: 1px solid #e1e7f0; overflow-x: auto; }
        .fd-filter { min-height: 38px; padding: 8px 0; border: 0; border-bottom: 2px solid transparent; background: transparent; color: var(--on-surface-variant); font: inherit; font-size: 12px; cursor: pointer; white-space: nowrap; }
        .fd-filter.active { color: #073f9c; border-bottom-color: #0b57d0; font-weight: 700; }
        .fd-metrics { flex: 0 0 auto; display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 12px 24px; background: #fbfcff; }
        .fd-metric { padding: 9px 11px; border: 1px solid #dce4f0; border-radius: 8px; background: #fff; }
        .fd-metric strong { display: block; color: var(--on-surface); font-size: 16px; line-height: 1.1; }
        .fd-metric span { display: block; margin-top: 3px; color: var(--on-surface-variant); font-size: 10.5px; }
        .fd-metric.is-ready strong { color: #087443; }
        .fd-list-wrap { min-height: 0; flex: 1 1 auto; overflow-y: auto; padding: 0 24px 14px; background: linear-gradient(#fbfcff, #fff); scrollbar-gutter: stable; }
        .fd-summary { position: sticky; top: 0; z-index: 2; margin: 0; padding: 8px 0; background: #fbfcff; color: var(--on-surface-variant); font-size: 11.5px; }
        .fd-list { display: flex; flex-direction: column; gap: 8px; }
        .fd-card { padding: 15px 16px; border: 1px solid #dce4f0; border-radius: 9px; background: #fff; transition: border-color .15s, box-shadow .15s; }
        .fd-card:hover { border-color: #c6d4e8; box-shadow: 0 5px 16px rgba(30, 64, 125, .07); }
        .fd-card.is-ready { box-shadow: inset 3px 0 0 #16a269; }
        .fd-card.needs-setup { box-shadow: inset 3px 0 0 #d18a12; }
        .fd-card-main { display: grid; grid-template-columns: minmax(210px, 1.1fr) repeat(3, minmax(125px, .8fr)) auto; gap: 18px; align-items: center; }
        .fd-person { display: flex; align-items: center; gap: 11px; min-width: 0; }
        .fd-avatar { width: 38px; height: 38px; flex: none; display: grid; place-items: center; border: 1px solid #d3dfff; border-radius: 50%; background: #e8efff; color: #1246a3; font-size: 12px; font-weight: 750; }
        .fd-name { font-size: 13px; font-weight: 700; color: var(--on-surface); overflow-wrap: anywhere; }
        .fd-employment { display: inline-block; margin-top: 4px; padding: 2px 6px; border-radius: 999px; background: #f0f3f8; color: var(--on-surface-variant); font-size: 9.5px; font-weight: 650; }
        .fd-fact-label { display: block; margin-bottom: 3px; color: var(--on-surface-variant); font-size: 10.5px; }
        .fd-fact-value { display: block; color: var(--on-surface); font-size: 11.5px; line-height: 1.35; }
        .fd-fact-value.is-ready { color: #087443; font-weight: 650; }
        .fd-fact-value.needs-setup { color: #9a5c00; font-weight: 650; }
        .fd-load-track { height: 3px; margin-top: 6px; background: #e9edf3; overflow: hidden; }
        .fd-load-fill { height: 100%; background: linear-gradient(90deg, #0b57d0, #4f7fe5); }
        .fd-load-fill.is-full { background: linear-gradient(90deg, #d18a12, #e8aa36); }
        .fd-details-btn { min-height: 32px; padding: 6px 9px; border: 1px solid #d3dce9; border-radius: 6px; background: #fff; color: #0b46a3; font: inherit; font-size: 11px; font-weight: 650; cursor: pointer; white-space: nowrap; }
        .fd-details-btn:hover { border-color: #9eb9e8; background: #f6f9ff; }
        .fd-details { margin-top: 14px; padding: 13px 0 0 49px; border-top: 1px solid #e7ebf1; }
        .fd-detail-group + .fd-detail-group { margin-top: 11px; }
        .fd-detail-title { margin-bottom: 6px; color: var(--on-surface-variant); font-size: 10.5px; font-weight: 650; text-transform: uppercase; letter-spacing: .035em; }
        .fd-day-list, .fd-pref-list { display: flex; gap: 5px; flex-wrap: wrap; }
        .fd-day, .fd-pref { display: inline-flex; align-items: center; padding: 4px 7px; border-radius: 5px; font-size: 10.5px; }
        .fd-day { border: 1px solid #d7e9df; background: #f1fbf5; color: #087443; }
        .fd-day.is-unavailable { border-color: #e2e5ea; background: #f5f6f8; color: #667085; }
        .fd-pref.preferred { background: #e7f7ef; color: #087443; }
        .fd-pref.willing { background: #fff4dc; color: #915400; }
        .fd-pref.qualified { background: #e9edff; color: #4338ca; }
        .fd-state { display: grid; place-items: center; min-height: 220px; padding: 30px; text-align: center; color: var(--on-surface-variant); font-size: 12px; }
        .fd-state strong { display: block; margin-bottom: 5px; color: var(--on-surface); font-size: 14px; }
        .fd-skeleton { height: 86px; border: 1px solid #e2e7ef; border-radius: 9px; background: linear-gradient(90deg, #f3f5f8 25%, #fafbfc 50%, #f3f5f8 75%); background-size: 200% 100%; animation: fdPulse 1.2s infinite linear; }
        @keyframes fdPulse { to { background-position: -200% 0; } }
        .fd-footer { flex: 0 0 auto; display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 13px 24px; border-top: 1px solid #dfe6f0; background: #f8faff; }
        .fd-footer span { color: var(--on-surface-variant); font-size: 11px; }

        @media (max-width: 820px) {
            .faculty-directory { width: min(720px, calc(100vw - 24px)); }
            .fd-card-main { grid-template-columns: minmax(210px, 1fr) repeat(2, minmax(120px, .7fr)) auto; }
            .fd-card-main .fd-fact:nth-of-type(3) { grid-column: 2; }
        }
        @media (max-width: 600px) {
            .faculty-directory { width: 100vw; height: 100dvh; max-width: none; max-height: none; border-radius: 0; }
            .fd-head, .fd-toolbar, .fd-metrics, .fd-list-wrap, .fd-footer { padding-left: 16px; padding-right: 16px; }
            .fd-metrics { grid-template-columns: repeat(3, 1fr); }
            .fd-card-main { grid-template-columns: 1fr 1fr; gap: 13px; }
            .fd-person { grid-column: 1 / -1; }
            .fd-card-main .fd-fact:nth-of-type(3) { grid-column: auto; }
            .fd-details-btn { grid-column: 1 / -1; min-height: 42px; }
            .fd-details { padding-left: 0; }
            .fd-footer span { display: none; }
            .fd-footer .btn { width: 100%; min-height: 44px; }
        }
        @media (prefers-reduced-motion: reduce) { .fd-skeleton { animation: none; } }

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

    <div class="ac-page-header plotting-page-head">
        <div>
            <h2 class="ac-page-header__title">Schedule Plotting</h2>
            <p class="ac-page-header__sub">Drag subjects onto the weekly grid to plot rooms, faculty, and meeting times.</p>
        </div>
        <div class="plotting-page-actions" aria-label="Schedule plotting tools">
            <div class="sync-status" id="syncStatus" role="status" aria-live="polite">
                <span class="sync-dot" aria-hidden="true"></span>
                <span id="syncText">All changes saved</span>
            </div>
            <div class="plotting-page-actions__buttons">
                <button type="button" class="btn btn-ghost" id="approvalsBtn">
                    <span class="material-symbols-outlined" aria-hidden="true">swap_horiz</span>
                    <span>Swap Approvals</span><span class="approval-badge" id="approvalBadge">0</span>
                </button>
                <button type="button" class="btn btn-ghost" id="changeRequestsBtn">
                    <span class="material-symbols-outlined" aria-hidden="true">edit_note</span>
                    <span>Change Requests</span><span class="approval-badge" id="changeRequestBadge">0</span>
                </button>
                <button type="button" class="btn btn-ghost" id="roomOverviewBtn">
                    <span class="material-symbols-outlined" aria-hidden="true">meeting_room</span><span>Room Overview</span>
                </button>
                <button type="button" class="btn btn-ghost" id="facultyPanelBtn">
                    <span class="material-symbols-outlined" aria-hidden="true">groups</span><span>Faculty</span>
                </button>
            </div>
        </div>
    </div>

    <form class="filters" method="get" action="<?= site_url('chairperson/dashboard') ?>" id="contextForm" data-no-dirty-tracking>
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
            <h2 class="ai-title"><span class="ai-spark" aria-hidden="true">✦</span> AI Scheduling Assistant</h2>

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

                <div class="ai-head">
                    <span class="ai-status" id="aiStatus">Analyzing the schedule…</span>
                    <button type="button" class="btn btn-ghost ai-fill" id="aiFillAll" hidden>Plot everything</button>
                </div>
                <div id="aiSuggestions"></div>
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
    <div class="modal-backdrop" id="facultyModal" role="dialog" aria-modal="true" aria-labelledby="facultyModalTitle" aria-describedby="facultyModalMeeting">
        <div class="modal faculty-picker">
            <header class="faculty-picker__head">
                <div class="faculty-picker__title-row">
                    <h3 id="facultyModalTitle">Assign faculty</h3>
                    <button type="button" class="faculty-picker__close" id="facultyModalClose" aria-label="Close faculty assignment">
                        <span class="material-symbols-outlined" aria-hidden="true">close</span>
                    </button>
                </div>
                <div class="faculty-picker__context">
                    <span class="faculty-picker__code" id="facultyModalCode"></span>
                    <span class="faculty-picker__subject" id="facultyModalSubject"></span>
                    <span class="faculty-picker__meeting" id="facultyModalMeeting"></span>
                </div>
            </header>
            <div id="facultyModalWarnings"></div>

            <div class="faculty-picker__toolbar">
                <label class="sr-only" for="facultyModalSearch">Search faculty by name</label>
                <div class="faculty-picker__search-wrap">
                    <span class="material-symbols-outlined faculty-picker__search-icon" aria-hidden="true">search</span>
                    <input class="faculty-picker__search" type="search" id="facultyModalSearch" placeholder="Search faculty by name…" autocomplete="off">
                </div>
                <div class="faculty-picker__filters" id="facultyModalFilters" role="group" aria-label="Filter faculty candidates">
                    <button type="button" class="faculty-filter active" data-filter="all" aria-pressed="true">All</button>
                    <button type="button" class="faculty-filter" data-filter="available" aria-pressed="false">Available</button>
                    <button type="button" class="faculty-filter" data-filter="preferred" aria-pressed="false">Preferred</button>
                    <button type="button" class="faculty-filter" data-filter="qualified" aria-pressed="false">Qualified</button>
                    <button type="button" class="faculty-filter" data-filter="conflicts" aria-pressed="false">Conflicts</button>
                </div>
            </div>
            <div class="faculty-picker__results" id="facultyModalResults">
                <p class="faculty-picker__summary" id="facultyModalSummary" aria-live="polite"></p>
                <div id="facultyModalList" class="fc-grid" role="list"></div>
            </div>
            <p class="faculty-picker__legend">Availability conflicts may be overridden with confirmation. Schedule conflicts cannot be overridden.</p>
            <footer class="faculty-picker__footer">
                <button type="button" class="btn btn-ghost" id="facultyModalSkip">Assign later</button>
                <div class="actions">
                    <button type="button" class="btn btn-ghost" id="facultyModalCancel">Cancel</button>
                </div>
            </footer>
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
    <div class="modal-backdrop" id="overviewModal" role="dialog" aria-modal="true" aria-labelledby="overviewTitle" aria-describedby="overviewDescription">
        <div class="modal overview-modal">
            <div class="ov-head">
                <div>
                    <div class="ov-head__title-row">
                        <h3 id="overviewTitle">Room Overview</h3>
                        <span class="ov-summary-badge" id="overviewSummary"><span class="dot"></span></span>
                    </div>
                    <div class="sub" id="overviewDescription">Every class plotted in a room for a day — all programs, year levels
                        and sections in <?= esc($selected['academic_year']) ?>, live.</div>
                </div>
                <button type="button" class="expand-btn" id="overviewClose" aria-label="Close room overview">&#x2715;</button>
            </div>

            <div class="ov-body">
                <div class="ov-day-row">
                    <div>
                        <div class="ov-label">View day</div>
                        <div class="day-chips" id="overviewDays" role="group" aria-label="Select day"></div>
                    </div>
                    <div class="ov-metrics" aria-live="polite">
                        <div class="ov-metric free"><span class="ov-metric__value" id="overviewMetricFree">0</span><span class="ov-metric__label">Free rooms</span></div>
                        <div class="ov-metric"><span class="ov-metric__value" id="overviewMetricUse">0</span><span class="ov-metric__label">In use</span></div>
                        <div class="ov-metric busy"><span class="ov-metric__value" id="overviewMetricBusy">0</span><span class="ov-metric__label">Busy rooms</span></div>
                    </div>
                </div>

                <div class="ov-workspace">
                    <section class="ov-room-browser" aria-labelledby="overviewRoomsLabel">
                        <div class="ov-room-toolbar">
                            <div class="ov-room-toolbar__top">
                                <div>
                                    <div class="ov-label" id="overviewRoomsLabel">Rooms</div>
                                    <div class="ov-legend">
                                        <span><i class="lg-green"></i> Free</span>
                                        <span><i class="lg-yellow"></i> In use</span>
                                        <span><i class="lg-red"></i> Busy</span>
                                    </div>
                                </div>
                                <label class="ov-search-wrap">
                                    <span class="sr-only">Search rooms</span>
                                    <span class="material-symbols-outlined" aria-hidden="true">search</span>
                                    <input class="ov-search" type="search" id="overviewRoomSearch" placeholder="Search rooms…" autocomplete="off">
                                </label>
                            </div>
                            <div class="ov-filters" id="overviewRoomFilters" role="group" aria-label="Filter rooms">
                                <button type="button" class="ov-filter active" data-filter="all" aria-pressed="true">All rooms</button>
                                <button type="button" class="ov-filter" data-filter="free" aria-pressed="false">Free</button>
                                <button type="button" class="ov-filter" data-filter="in-use" aria-pressed="false">In use</button>
                                <button type="button" class="ov-filter" data-filter="labs" aria-pressed="false">Laboratories</button>
                            </div>
                        </div>
                        <div class="ov-room-scroll" id="overviewRoomsWrap"></div>
                    </section>

                    <aside class="ov-schedule-pane" aria-labelledby="overviewListLabel">
                        <div class="ov-schedule-head">
                            <div class="ov-label">Selected room</div>
                            <div class="ov-schedule-title" id="overviewListLabel">Schedule</div>
                            <div class="ov-schedule-meta" id="overviewListMeta"></div>
                        </div>
                        <div class="overview-list" id="overviewList"></div>
                    </aside>
                </div>
            </div>

            <div class="ov-footer">
                <span class="ov-footer__note">Updates automatically from the plotting grid.</span>
                <button type="button" class="btn btn-ghost" id="overviewCloseBottom">Close</button>
            </div>
        </div>
    </div>

    <!-- Faculty context panel modal -->
    <div class="modal-backdrop" id="facultyPanelModal" role="dialog" aria-modal="true" aria-labelledby="facultyPanelTitle" aria-describedby="facultyPanelSub">
        <div class="modal faculty-directory">
            <header class="fd-head">
                <div>
                    <div class="fd-title-row">
                        <h3 id="facultyPanelTitle">Department Faculty</h3>
                        <span class="fd-count" id="facultyPanelCount">Loading…</span>
                    </div>
                    <div class="sub" id="facultyPanelSub">Availability, subject preferences, and current teaching load.</div>
                </div>
                <button type="button" class="fd-close" id="facultyPanelClose" aria-label="Close department faculty">
                    <span class="material-symbols-outlined" aria-hidden="true">close</span>
                </button>
            </header>
            <div class="fd-toolbar">
                <label class="sr-only" for="facultyPanelSearch">Search faculty by name</label>
                <div class="fd-search-wrap">
                    <span class="material-symbols-outlined" aria-hidden="true">search</span>
                    <input class="fd-search" type="search" id="facultyPanelSearch" placeholder="Search faculty by name…" autocomplete="off">
                </div>
                <div class="fd-filters" id="facultyPanelFilters" role="group" aria-label="Filter faculty setup status">
                    <button type="button" class="fd-filter active" data-filter="all" aria-pressed="true">All faculty</button>
                    <button type="button" class="fd-filter" data-filter="ready" aria-pressed="false">Setup complete</button>
                    <button type="button" class="fd-filter" data-filter="needs-setup" aria-pressed="false">Needs setup</button>
                </div>
            </div>
            <div class="fd-metrics" aria-live="polite">
                <div class="fd-metric"><strong id="facultyMetricTotal">—</strong><span>Total faculty</span></div>
                <div class="fd-metric is-ready"><strong id="facultyMetricAvailable">—</strong><span>Availability set</span></div>
                <div class="fd-metric"><strong id="facultyMetricPreferences">—</strong><span>Preferences set</span></div>
            </div>
            <div class="fd-list-wrap" id="facultyPanelResults" aria-busy="true">
                <p class="fd-summary" id="facultyPanelSummary" aria-live="polite">Loading faculty…</p>
                <div class="fd-list" id="facultyPanelList" role="list"></div>
            </div>
            <footer class="fd-footer">
                <span>Availability and preferences reflect the upcoming term.</span>
                <button type="button" class="btn btn-ghost" id="facultyPanelCloseBottom">Close</button>
            </footer>
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
            <div class="confirm-reason" id="confirmReasonWrap" hidden>
                <label for="confirmReason">Reason for override <span class="sub">(optional)</span></label>
                <textarea id="confirmReason" maxlength="250" placeholder="Add context for the audit record"></textarea>
            </div>
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
        // The notification bell and avatar/logout menu are now handled by
        // the shared layout (layouts/admin_console.php), same as every
        // other page — this page no longer ships its own duplicate copies.

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
            suggestUrl: <?= json_encode(site_url('chairperson/schedule/suggest')) ?>,
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
        let confirmLastFocus = null;

        function confirmDialog({ title, message, confirmLabel = 'Confirm', danger = false, reason = false }) {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmMessage').textContent = message;
            const reasonWrap = document.getElementById('confirmReasonWrap');
            const reasonInput = document.getElementById('confirmReason');
            reasonWrap.hidden = !reason;
            reasonInput.value = '';

            const okBtn = document.getElementById('confirmOk');
            okBtn.textContent = confirmLabel;
            okBtn.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');

            confirmLastFocus = document.activeElement;
            confirmModal.classList.add('open');
            requestAnimationFrame(() => (reason ? reasonInput : document.getElementById('confirmCancel')).focus());
            return new Promise(resolve => { confirmResolve = resolve; });
        }

        function settleConfirm(result) {
            if (confirmResolve === null) return;
            confirmModal.classList.remove('open');
            const resolve = confirmResolve;
            confirmResolve = null;
            if (confirmLastFocus && confirmLastFocus.isConnected) confirmLastFocus.focus();
            confirmLastFocus = null;
            resolve(result && !document.getElementById('confirmReasonWrap').hidden
                ? { confirmed: true, reason: document.getElementById('confirmReason').value.trim() }
                : result);
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

        // Lay out one day column. Your own classes are laned only against
        // each other; other sections' classes (ghosts) are laned separately
        // and squeezed into a slim strip beside them, so a busy timetable
        // elsewhere never crushes what you are actually plotting. Where a
        // time cluster holds only one kind, it uses the full column width.
        function layoutColumn(items) {
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
                const own = group.filter(i => i.own);
                const ghosts = group.filter(i => !i.own);
                const split = own.length > 0 && ghosts.length > 0;

                assignLanes(own);
                assignLanes(ghosts);

                own.forEach(i => { i.zone = split ? { x: 0, w: 0.66 } : { x: 0, w: 1 }; });
                ghosts.forEach(i => { i.zone = split ? { x: 0.66, w: 0.34 } : { x: 0, w: 1 }; });
            });
        }

        function positionBlock(el, item) {
            const zone = item.zone || { x: 0, w: 1 };
            const laneW = zone.w / item.lanes;

            el.style.top = (item.startRow * CONFIG.rowHeight) + 'px';
            el.style.height = ((item.endRow - item.startRow) * CONFIG.rowHeight - 4) + 'px';
            el.style.left = `calc(${(zone.x + item.lane * laneW) * 100}% + 3px)`;
            el.style.width = `calc(${laneW * 100}% - 6px)`;
            el.style.right = 'auto';

            if (!item.own && (zone.w < 1 || item.lanes > 1)) el.classList.add('compact');
            if ((item.endRow - item.startRow) <= 2) el.classList.add('short');
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
                        items.push({ own: true, startRow: blockStartRow(b), endRow: blockEndRow(b), el: ownEl(b, i) });
                    });

                    OTHERS.forEach(b => {
                        if (Number(b.day) !== day) return;
                        items.push({ own: false, startRow: blockStartRow(b), endRow: blockEndRow(b), el: ghostEl(b) });
                    });

                    layoutColumn(items);

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
            queueSuggestions();
        }

        // ---------- AI scheduling assistant ----------
        // The server ranks conflict-free placements for whatever is still
        // unplotted; applying one just adds a block and autosaves, so the
        // normal sync validation still has the last word.
        let aiTimer = null;
        let aiPlan = { suggestions: [], unplaceable: [] };
        let aiRequest = 0;

        function queueSuggestions() {
            clearTimeout(aiTimer);
            aiTimer = setTimeout(loadSuggestions, 700);
        }

        async function loadSuggestions() {
            // Wait for any pending save so the server sees the current grid.
            if (saving || saveQueued || document.getElementById('syncStatus').classList.contains('saving')) {
                queueSuggestions();
                return;
            }

            const ticket = ++aiRequest;
            const status = document.getElementById('aiStatus');

            try {
                const response = await fetch(`${CONFIG.suggestUrl}?${new URLSearchParams(CONTEXT).toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();

                if (ticket !== aiRequest) return; // a newer request superseded this one
                if (!response.ok || !data.ok) throw new Error('bad response');

                aiPlan = data;
                renderSuggestions();
            } catch (err) {
                status.textContent = 'The assistant could not analyze this schedule right now.';
                document.getElementById('aiFillAll').hidden = true;
                document.getElementById('aiSuggestions').replaceChildren();
            }
        }

        function aiEl(tag, className, text) {
            const el = document.createElement(tag);
            if (className) el.className = className;
            if (text !== undefined) el.textContent = text;
            return el;
        }

        const IMPACT_LABEL = { high: 'High impact', medium: 'Medium impact', low: 'Small gain' };

        function aiSection(box, label, count) {
            const head = aiEl('div', 'ai-section');
            head.append(aiEl('span', '', label), aiEl('span', 'count', String(count)));
            box.appendChild(head);
        }

        function aiFitRow(score) {
            const fit = Math.max(20, Math.min(99, Math.round((score - 40) / 80 * 100)));
            const row = aiEl('div', 'ai-fit');
            const bar = aiEl('span', 'bar');
            const fill = document.createElement('i');
            fill.style.width = fit + '%';
            bar.appendChild(fill);
            row.append(aiEl('span', '', 'Fit'), bar, aiEl('span', '', fit + '%'));
            return row;
        }

        function aiReasons(list) {
            const ul = aiEl('ul', 'ai-reasons');
            list.forEach(r => ul.appendChild(aiEl('li', '', r)));
            return ul;
        }

        function aiFacultyChip(f) {
            const chip = aiEl('div', 'ai-prof');
            chip.appendChild(aiEl('span', 'avatar', (f.name || '?').charAt(0).toUpperCase()));
            const text = aiEl('span', 'text', f.name);
            chip.appendChild(text);

            if (f.level) chip.appendChild(aiEl('span', 'level ' + f.level, f.level));

            if (f.setup_pending) {
                const pending = aiEl('span', 'level pending', 'Setup pending');
                pending.title = 'This professor has not finished setting their availability and/or subjects yet.';
                chip.appendChild(pending);
            }

            return chip;
        }

        /** Buttons + collapsible alternatives shared by every card type. */
        function aiActions(card, applyLabel, onApply, alternatives, describe, onUse) {
            const actions = aiEl('div', 'ai-actions');

            if (onApply) {
                const apply = aiEl('button', 'ai-apply', applyLabel);
                apply.type = 'button';
                apply.addEventListener('click', onApply);
                actions.appendChild(apply);
            }

            card.appendChild(actions);

            if (!alternatives || alternatives.length === 0) return;

            const toggle = aiEl('button', 'ai-alt-toggle', `View alternative options (${alternatives.length})`);
            toggle.type = 'button';
            const alts = aiEl('div', 'ai-alts');
            alts.hidden = true;

            alternatives.forEach(a => {
                const row = aiEl('div', 'ai-alt');
                row.appendChild(aiEl('span', '', describe(a)));
                const use = aiEl('button', '', 'Use');
                use.type = 'button';
                use.addEventListener('click', () => onUse(a));
                row.appendChild(use);
                alts.appendChild(row);
            });

            toggle.addEventListener('click', () => { alts.hidden = !alts.hidden; });
            actions.appendChild(toggle);
            card.appendChild(alts);
        }

        function improvementCard(item) {
            const card = aiEl('div', 'suggestion');
            card.appendChild(aiEl('span', 'impact ' + (item.impact === 'high' ? 'warn' : 'good'), `${IMPACT_LABEL[item.impact] || 'Improvement'} · ${item.code}`));

            if (item.type === 'assign_faculty') {
                if (!item.faculty) {
                    card.appendChild(aiEl('div', 'body', item.summary));
                    return card;
                }

                card.appendChild(aiEl('div', 'title', item.summary));
                card.appendChild(aiFacultyChip(item.faculty));
                card.appendChild(aiReasons(item.faculty.reasons));
                aiActions(card, 'Assign', () => applyAssignFaculty(item, item.faculty),
                    item.alternatives, f => `${f.name}${f.level ? ' · ' + f.level : ''}`, f => applyAssignFaculty(item, f));
                return card;
            }

            // move
            if (item.best) {
                card.appendChild(aiEl('div', 'title', `Move ${item.title} to ${item.best.day_name} ${item.best.time_label}`));
                card.appendChild(aiEl('div', 'body', item.summary));
                card.appendChild(aiEl('div', 'ai-now', 'Now: ' + item.block.label));
                card.appendChild(aiFitRow(item.best.score));
                card.appendChild(aiReasons(item.best.reasons));
                aiActions(card, 'Move it', () => applyMove(item, item.best),
                    item.alternatives, a => `${a.day_name} ${a.time_label} · ${a.room}`, a => applyMove(item, a));
            } else {
                card.appendChild(aiEl('div', 'title', item.title));
                card.appendChild(aiEl('div', 'body', item.summary));
                card.appendChild(aiEl('div', 'ai-now', 'Now: ' + item.block.label));
            }

            return card;
        }

        function placementCard(s) {
            const card = aiEl('div', 'suggestion');
            card.appendChild(aiEl('span', 'impact good', `${s.code} · meeting ${s.meeting} of ${s.of}`));
            card.appendChild(aiEl('div', 'title', `${s.title}: ${s.best.day_name} ${s.best.time_label}`));
            card.appendChild(aiEl('div', 'body', s.best.room));

            if (s.faculty) {
                const chipHolder = aiEl('div', 'ai-prof-holder');
                chipHolder.appendChild(aiFacultyChip(s.faculty));
                card.appendChild(chipHolder);

                // Any professor who could teach this plan, including those
                // who have not set their availability/subjects yet.
                const options = s.faculty_options || [];

                if (options.length > 1) {
                    const select = document.createElement('select');
                    select.className = 'ai-prof-select';
                    select.title = 'Choose a different professor for this subject';

                    options.forEach(o => {
                        const opt = document.createElement('option');
                        opt.value = String(o.id);
                        opt.textContent = o.name + (o.level ? ` · ${o.level}` : '') + (o.setup_pending ? ' · setup pending' : '');
                        if (o.id === s.faculty_id) opt.selected = true;
                        select.appendChild(opt);
                    });

                    select.addEventListener('change', () => {
                        const chosen = options.find(o => String(o.id) === select.value);
                        if (!chosen) return;

                        // Every meeting of this subject shares one professor.
                        (aiPlan.suggestions || []).filter(x => x.code === s.code).forEach(x => {
                            x.prof = chosen.name;
                            x.faculty_id = chosen.id;
                            x.faculty = chosen;
                        });

                        chipHolder.replaceChildren(aiFacultyChip(chosen));
                    });

                    card.appendChild(select);
                }
            } else if (s.faculty_note) {
                card.appendChild(aiEl('div', 'ai-note', s.faculty_note));
            }

            card.appendChild(aiFitRow(s.best.score));
            card.appendChild(aiReasons(s.best.reasons.concat(s.faculty ? s.faculty.reasons.slice(0, 2) : [])));
            aiActions(card, 'Plot this', () => applySuggestion(s, s.best),
                s.alternatives, a => `${a.day_name} ${a.time_label} · ${a.room}`, a => applySuggestion(s, a));

            return card;
        }

        function renderSuggestions() {
            const box = document.getElementById('aiSuggestions');
            const status = document.getElementById('aiStatus');
            const fillAll = document.getElementById('aiFillAll');
            box.replaceChildren();

            const plan = aiPlan.suggestions || [];
            const stuck = aiPlan.unplaceable || [];
            const fixes = aiPlan.improvements || [];

            if (SUBJECTS.length === 0) {
                status.textContent = 'No curriculum subjects for this term yet.';
                fillAll.hidden = true;
                return;
            }

            fillAll.hidden = plan.length === 0;

            if (plan.length === 0 && stuck.length === 0 && fixes.length === 0) {
                status.textContent = 'Schedule complete';

                const done = aiEl('div', 'ai-done');
                done.append(aiEl('span', 'material-symbols-outlined', 'task_alt'),
                    aiEl('div', '', 'Every subject is plotted, well placed and conflict-free. Drag a class to fine-tune, or switch sections to keep going.'));
                box.appendChild(done);
                return;
            }

            const parts = [];
            if (fixes.length) parts.push(`${fixes.length} improvement${fixes.length === 1 ? '' : 's'}`);
            if (plan.length) parts.push(`${plan.length} placement${plan.length === 1 ? '' : 's'}`);
            const size = aiPlan.headcount > 0 ? ` · ${aiPlan.headcount}${aiPlan.headcount_known ? '' : ' (est.)'} students` : '';
            status.textContent = (parts.join(' · ') || 'Needs attention') + size;

            if (fixes.length) {
                aiSection(box, 'Improve what is plotted', fixes.length);
                fixes.forEach(item => box.appendChild(improvementCard(item)));
            }

            if (plan.length || stuck.length) {
                aiSection(box, 'Still to plot', plan.length + stuck.length);
            }

            plan.forEach(s => box.appendChild(placementCard(s)));

            stuck.forEach(u => {
                const card = aiEl('div', 'suggestion');
                card.appendChild(aiEl('span', 'impact warn', `${u.code} · cannot place`));
                card.appendChild(aiEl('div', 'body', u.reason));
                box.appendChild(card);
            });
        }

        function suggestionBlock(s, slot) {
            const subject = SUBJECTS.find(x => x.code === s.code);

            return {
                day: slot.day,
                code: s.code,
                title: s.title,
                prof: s.prof || undefined,
                faculty_id: s.faculty_id || null,
                time_label: slot.time_label,
                room: slot.room,
                theme: subject ? subject.color : s.theme,
                start: slot.start,
                end: slot.end,
            };
        }

        function applySuggestion(s, slot) {
            blocks.push(suggestionBlock(s, slot));
            renderBlocks();
            scheduleSave();
            showToast(`${s.code} plotted on ${slot.day_name}, ${slot.time_label} (${slot.room}).`, 'success');
        }

        // Move one existing meeting. The block is found by what it looked
        // like when the suggestion was made; if the grid has changed since,
        // nothing is touched and the suggestions are recomputed.
        function applyMove(item, slot) {
            const b = blocks.find(x => x.code === item.code && Number(x.day) === item.block.day
                && x.start === item.block.start && x.room === item.block.room);

            if (!b) {
                showToast('The schedule changed — refreshing suggestions.', 'error');
                loadSuggestions();
                return;
            }

            b.day = slot.day;
            b.start = slot.start;
            b.end = slot.end;
            b.time_label = slot.time_label;
            b.room = slot.room;

            renderBlocks();
            scheduleSave();
            showToast(`${item.code} moved to ${slot.day_name}, ${slot.time_label} (${slot.room}).`, 'success');
        }

        function applyAssignFaculty(item, faculty) {
            const targets = blocks.filter(x => x.code === item.code);

            if (targets.length === 0) {
                loadSuggestions();
                return;
            }

            targets.forEach(x => {
                x.prof = faculty.name;
                x.faculty_id = faculty.id;
            });

            renderBlocks();
            scheduleSave();
            showToast(`${faculty.name} assigned to ${item.code}.`, 'success');
        }

        document.getElementById('aiFillAll').addEventListener('click', async () => {
            const plan = aiPlan.suggestions || [];

            if (plan.length === 0) return;

            const ok = await confirmDialog({
                title: 'Plot everything?',
                message: `Add ${plan.length} suggested class meeting${plan.length === 1 ? '' : 's'} to this section's grid. You can still move or remove any of them afterwards.`,
                confirmLabel: 'Plot everything',
            });

            if (!ok) return;

            plan.forEach(s => blocks.push(suggestionBlock(s, s.best)));
            renderBlocks();
            scheduleSave();
            showToast(`${plan.length} class meeting${plan.length === 1 ? '' : 's'} plotted.`, 'success');
        });

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
        const overviewState = { day: 0, room: ROOMS[0]?.name || '', query: '', filter: 'all' };
        let overviewLastFocus = null;

        function overviewRoomLoad(room, day) {
            return blocks.concat(OTHERS).filter(b => b.room === room && b.day === day).length;
        }

        function overviewRoomBlocks(room, day) {
            return blocks.concat(OTHERS)
                .filter(b => b.room === room && b.day === day)
                .sort((a, b) => blockStartRow(a) - blockStartRow(b));
        }

        function overviewRoomHours(room, day) {
            return overviewRoomBlocks(room, day).reduce((total, block) => total + ((blockEndRow(block) - blockStartRow(block)) / 2), 0);
        }

        function overviewTimeline(room, day, state) {
            const track = document.createElement('div');
            track.className = 'ov-timeline-track';
            overviewRoomBlocks(room, day).forEach(b => {
                const seg = document.createElement('div');
                seg.className = 'ov-timeline-seg' + (state === 'red' ? ' is-red' : '');
                const left = (blockStartRow(b) / CONFIG.totalRows) * 100;
                const width = ((blockEndRow(b) - blockStartRow(b)) / CONFIG.totalRows) * 100;
                seg.style.left = left + '%';
                seg.style.width = width + '%';
                seg.title = `${b.code}: ${rowTo12h(blockStartRow(b))} - ${rowTo12h(blockEndRow(b))}`;
                track.appendChild(seg);
            });
            return track;
        }

        function renderOverviewSummary() {
            const day = overviewState.day;
            const free = ROOMS.filter(r => overviewRoomLoad(r.name, day) === 0).length;
            const inUse = ROOMS.filter(r => overviewRoomLoad(r.name, day) > 0).length;
            const busy = ROOMS.filter(r => overviewRoomLoad(r.name, day) >= 3).length;
            const pct = ROOMS.length ? Math.round((free / ROOMS.length) * 100) : 0;
            const badge = document.getElementById('overviewSummary');
            badge.innerHTML = '';
            const dot = document.createElement('span');
            dot.className = 'dot';
            badge.appendChild(dot);
            badge.appendChild(document.createTextNode(`${pct}% free on ${WEEK[day].day}`));
            document.getElementById('overviewMetricFree').textContent = free;
            document.getElementById('overviewMetricUse').textContent = inUse;
            document.getElementById('overviewMetricBusy').textContent = busy;
        }

        function renderOverviewControls() {
            const daysBox = document.getElementById('overviewDays');
            daysBox.innerHTML = '';
            WEEK.forEach((d, i) => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'day-chip' + (i === overviewState.day ? ' active' : '');
                chip.textContent = d.day;
                chip.setAttribute('aria-pressed', String(i === overviewState.day));
                chip.addEventListener('click', () => {
                    overviewState.day = i;
                    renderRoomOverview();
                });
                daysBox.appendChild(chip);
            });

            renderOverviewSummary();

            const roomsBox = document.getElementById('overviewRoomsWrap');
            roomsBox.innerHTML = '';

            const query = overviewState.query.trim().toLowerCase();
            const visibleRooms = ROOMS.filter(room => {
                const load = overviewRoomLoad(room.name, overviewState.day);
                const matchesQuery = !query || room.name.toLowerCase().includes(query) || String(room.type || '').toLowerCase().includes(query);
                const matchesFilter = overviewState.filter === 'all'
                    || (overviewState.filter === 'free' && load === 0)
                    || (overviewState.filter === 'in-use' && load > 0)
                    || (overviewState.filter === 'labs' && room.is_laboratory);
                return matchesQuery && matchesFilter;
            });

            if (!visibleRooms.length) {
                roomsBox.innerHTML = '<div class="ov-no-results"><strong>No matching rooms</strong><br>Try another search or filter.</div>';
            }

            FLOORS.forEach(floor => {
                const floorRooms = visibleRooms.filter(r => r.floor === floor);
                if (floorRooms.length === 0) return;

                const group = document.createElement('div');
                group.className = 'ov-floor-group';

                const heading = document.createElement('div');
                heading.className = 'floor-heading ov-floor-heading';
                heading.innerHTML = `<span>${escapeHtml(floor)}</span><span class="count">${floorRooms.length} ${floorRooms.length === 1 ? 'space' : 'spaces'}</span>`;
                group.appendChild(heading);

                const grid = document.createElement('div');
                grid.className = 'room-grid overview-rooms';

                floorRooms.forEach(room => {
                    const load = overviewRoomLoad(room.name, overviewState.day);
                    const state = load >= 3 ? 'red' : load >= 1 ? 'yellow' : 'green';
                    const occupiedHours = overviewRoomHours(room.name, overviewState.day);

                    const card = document.createElement('button');
                    card.type = 'button';
                    card.className = 'room-card ' + state + (room.name === overviewState.room ? ' selected' : '');
                    card.setAttribute('aria-pressed', String(room.name === overviewState.room));
                    card.setAttribute('aria-label', `${room.name}, ${load === 0 ? 'free all day' : `${load} classes, ${occupiedHours} hours occupied`}`);

                    const top = document.createElement('div');
                    top.className = 'ov-card-top';
                    const typeBadge = document.createElement('span');
                    typeBadge.className = 'ov-type-badge' + (room.is_laboratory ? ' is-lab' : '');
                    typeBadge.textContent = room.is_laboratory ? 'Laboratory' : (room.type || 'Room');
                    const capacity = document.createElement('span');
                    capacity.className = 'ov-capacity';
                    capacity.textContent = room.capacity > 0 ? `Cap: ${room.capacity}` : '';
                    top.append(typeBadge, capacity);

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
                    status.textContent = load === 0 ? 'Free all day' : `${load} ${load === 1 ? 'class' : 'classes'} · ${occupiedHours}h occupied`;

                    const timelineWrap = document.createElement('div');
                    timelineWrap.className = 'ov-timeline';
                    timelineWrap.appendChild(overviewTimeline(room.name, overviewState.day, state));

                    card.append(top, head, status, timelineWrap);
                    card.addEventListener('click', () => {
                        overviewState.room = room.name;
                        renderRoomOverview();
                        if (window.matchMedia('(max-width: 850px)').matches) {
                            requestAnimationFrame(() => document.querySelector('.ov-schedule-pane').scrollIntoView({
                                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                                block: 'start',
                            }));
                        }
                    });
                    grid.appendChild(card);
                });

                group.appendChild(grid);
                roomsBox.appendChild(group);
            });

            const selectedRoom = ROOMS.find(room => room.name === overviewState.room);
            document.getElementById('overviewListLabel').textContent = overviewState.room || 'No room selected';
            document.getElementById('overviewListMeta').textContent = selectedRoom
                ? `${WEEK[overviewState.day].day} · ${selectedRoom.is_laboratory ? 'Laboratory' : (selectedRoom.type || 'Room')}${selectedRoom.capacity > 0 ? ` · Capacity ${selectedRoom.capacity}` : ''}`
                : WEEK[overviewState.day].day;
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
                empty.innerHTML = `<div><strong>Free all day</strong><span>${escapeHtml(room)} has no classes plotted on ${escapeHtml(WEEK[day].day)}.</span></div>`;
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

        document.getElementById('overviewRoomSearch').addEventListener('input', event => {
            overviewState.query = event.target.value;
            renderOverviewControls();
        });

        document.querySelectorAll('#overviewRoomFilters .ov-filter').forEach(button => {
            button.addEventListener('click', () => {
                overviewState.filter = button.dataset.filter;
                document.querySelectorAll('#overviewRoomFilters .ov-filter').forEach(filter => {
                    const active = filter === button;
                    filter.classList.toggle('active', active);
                    filter.setAttribute('aria-pressed', String(active));
                });
                renderOverviewControls();
            });
        });

        document.getElementById('roomOverviewBtn').addEventListener('click', () => {
            overviewLastFocus = document.activeElement;
            overviewModal.classList.add('open');
            renderRoomOverview();
            requestAnimationFrame(() => document.getElementById('overviewRoomSearch').focus());
        });

        function closeRoomOverview() {
            if (!overviewModal.classList.contains('open')) return;
            overviewModal.classList.remove('open');
            if (overviewLastFocus && overviewLastFocus.isConnected) overviewLastFocus.focus();
        }

        document.getElementById('overviewClose').addEventListener('click', closeRoomOverview);
        document.getElementById('overviewCloseBottom').addEventListener('click', closeRoomOverview);
        overviewModal.addEventListener('click', e => { if (e.target === overviewModal) closeRoomOverview(); });

        // ===== Faculty context panel =====
        const facultyPanelModal = document.getElementById('facultyPanelModal');
        const facultyPanelState = { faculty: [], maxUnits: 0, query: '', filter: 'all' };
        let facultyPanelLastFocus = null;

        function facultySetupState(faculty) {
            const availabilitySet = Boolean(faculty.has_submitted_availability || (faculty.availability || []).length);
            const preferencesSet = Boolean((faculty.preferences || []).length);
            return { availabilitySet, preferencesSet, complete: availabilitySet && preferencesSet };
        }

        function facultyAvailabilityDetails(faculty) {
            const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            if (!(faculty.availability || []).length) return '<span class="fd-day is-unavailable">No availability submitted</span>';
            return days.map((day, index) => {
                const row = faculty.availability.find(item => Number(item.day_of_week) === index);
                if (!row || !row.is_available || row.is_available == 0) return `<span class="fd-day is-unavailable">${day} · Unavailable</span>`;
                const start = minutesTo12h(toMinutes((row.start_time || '00:00').slice(0, 5)));
                const end = minutesTo12h(toMinutes((row.end_time || '00:00').slice(0, 5)));
                return `<span class="fd-day">${day} · ${start}–${end}</span>`;
            }).join('');
        }

        function facultyPreferenceDetails(faculty) {
            if (!(faculty.preferences || []).length) return '<span class="fd-day is-unavailable">No preferences submitted</span>';
            return faculty.preferences.map(preference =>
                `<span class="fd-pref ${escapeHtml(preference.level)}" title="${escapeHtml(preference.title || preference.code)}">${escapeHtml(preference.code)} · ${escapeHtml(preference.level)}</span>`
            ).join('');
        }

        function renderFacultyPanel() {
            const list = document.getElementById('facultyPanelList');
            const summary = document.getElementById('facultyPanelSummary');
            const needle = facultyPanelState.query.trim().toLowerCase();
            const visible = facultyPanelState.faculty.filter(faculty => {
                const setup = facultySetupState(faculty);
                const matchesName = !needle || faculty.name.toLowerCase().includes(needle);
                const matchesFilter = facultyPanelState.filter === 'all'
                    || (facultyPanelState.filter === 'ready' && setup.complete)
                    || (facultyPanelState.filter === 'needs-setup' && !setup.complete);
                return matchesName && matchesFilter;
            });

            summary.textContent = `${visible.length} of ${facultyPanelState.faculty.length} faculty shown`;
            list.innerHTML = '';
            if (!visible.length) {
                list.innerHTML = '<div class="fd-state"><div><strong>No matching faculty</strong><span>Try another name or setup filter.</span></div></div>';
                return;
            }

            visible.forEach(faculty => {
                const setup = facultySetupState(faculty);
                const availableDays = (faculty.availability || []).filter(row => row.is_available && row.is_available != 0).length;
                const preferenceCount = (faculty.preferences || []).length;
                const units = Number(faculty.units || 0);
                const maxUnits = Number(facultyPanelState.maxUnits || 0);
                const loadPercent = maxUnits ? Math.min(100, (units / maxUnits) * 100) : 0;
                const initials = faculty.name.split(/\s+/).map(word => word[0]).slice(0, 2).join('').toUpperCase();
                const employment = faculty.employment_status === 'full_time' ? 'Full-time' : faculty.employment_status === 'part_time' ? 'Part-time' : 'Employment type not set';
                const card = document.createElement('article');
                card.className = `fd-card ${setup.complete ? 'is-ready' : 'needs-setup'}`;
                card.setAttribute('role', 'listitem');
                card.innerHTML = `
                    <div class="fd-card-main">
                        <div class="fd-person">
                            <div class="fd-avatar">${escapeHtml(initials)}</div>
                            <div><div class="fd-name">${escapeHtml(faculty.name)}</div><span class="fd-employment">${employment}</span></div>
                        </div>
                        <div class="fd-fact">
                            <span class="fd-fact-label">Current load</span>
                            <span class="fd-fact-value">${units}${maxUnits ? ` of ${maxUnits}` : ''} units · ${Number(faculty.weekly_hours || 0)} hrs/week</span>
                            ${maxUnits ? `<div class="fd-load-track" aria-hidden="true"><div class="fd-load-fill ${units >= maxUnits ? 'is-full' : ''}" style="width:${loadPercent}%"></div></div>` : ''}
                        </div>
                        <div class="fd-fact">
                            <span class="fd-fact-label">Availability</span>
                            <span class="fd-fact-value ${setup.availabilitySet ? 'is-ready' : 'needs-setup'}">${setup.availabilitySet ? `${availableDays} of 6 days available` : 'Not submitted'}</span>
                        </div>
                        <div class="fd-fact">
                            <span class="fd-fact-label">Preferences</span>
                            <span class="fd-fact-value ${setup.preferencesSet ? 'is-ready' : 'needs-setup'}">${setup.preferencesSet ? `${preferenceCount} subjects set` : 'Not submitted'}</span>
                        </div>
                        <button type="button" class="fd-details-btn" aria-expanded="false">View details</button>
                    </div>
                    <div class="fd-details" hidden>
                        <div class="fd-detail-group"><div class="fd-detail-title">Weekly availability</div><div class="fd-day-list">${facultyAvailabilityDetails(faculty)}</div></div>
                        <div class="fd-detail-group"><div class="fd-detail-title">Subject preferences</div><div class="fd-pref-list">${facultyPreferenceDetails(faculty)}</div></div>
                    </div>`;
                const button = card.querySelector('.fd-details-btn');
                const details = card.querySelector('.fd-details');
                button.addEventListener('click', () => {
                    const expanded = button.getAttribute('aria-expanded') === 'true';
                    button.setAttribute('aria-expanded', String(!expanded));
                    button.textContent = expanded ? 'View details' : 'Hide details';
                    details.hidden = expanded;
                });
                list.appendChild(card);
            });
        }

        async function loadFacultyPanel() {
            const list = document.getElementById('facultyPanelList');
            const results = document.getElementById('facultyPanelResults');
            results.setAttribute('aria-busy', 'true');
            document.getElementById('facultyPanelSummary').textContent = 'Loading faculty…';
            document.getElementById('facultyPanelCount').textContent = 'Loading…';
            list.innerHTML = Array.from({ length: 4 }, () => '<div class="fd-skeleton" aria-hidden="true"></div>').join('');

            try {
                const response = await fetch(CONFIG.facultyPanelUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
                if (!response.ok) throw new Error('Faculty request failed');
                const data = await response.json();

                facultyPanelState.faculty = (data.faculty || []).slice().sort((a, b) => a.name.localeCompare(b.name));
                facultyPanelState.maxUnits = data.max_units || 0;
                const availabilityCount = facultyPanelState.faculty.filter(faculty => facultySetupState(faculty).availabilitySet).length;
                const preferenceCount = facultyPanelState.faculty.filter(faculty => facultySetupState(faculty).preferencesSet).length;

                document.getElementById('facultyPanelSub').textContent = data.upcoming_term
                    ? `Availability & preferences for ${data.upcoming_term}. Current load for ${data.active_year || 'the active term'}.`
                    : 'No upcoming term configured yet — faculty cannot submit availability until an admin adds one.';
                document.getElementById('facultyPanelCount').textContent = `${facultyPanelState.faculty.length} faculty`;
                document.getElementById('facultyMetricTotal').textContent = facultyPanelState.faculty.length;
                document.getElementById('facultyMetricAvailable').textContent = availabilityCount;
                document.getElementById('facultyMetricPreferences').textContent = preferenceCount;
                results.setAttribute('aria-busy', 'false');

                if (!facultyPanelState.faculty.length) {
                    document.getElementById('facultyPanelSummary').textContent = '0 faculty';
                    list.innerHTML = '<div class="fd-state"><div><strong>No faculty found</strong><span>No faculty members are assigned to this department yet.</span></div></div>';
                    return;
                }
                renderFacultyPanel();
            } catch (e) {
                results.setAttribute('aria-busy', 'false');
                document.getElementById('facultyPanelSummary').textContent = '';
                document.getElementById('facultyPanelCount').textContent = 'Unavailable';
                list.innerHTML = '<div class="fd-state"><div><strong>Faculty could not be loaded</strong><span>Check your connection, then close and reopen this panel.</span></div></div>';
            }
        }

        document.getElementById('facultyPanelBtn').addEventListener('click', () => {
            facultyPanelLastFocus = document.activeElement;
            facultyPanelState.query = '';
            facultyPanelState.filter = 'all';
            document.getElementById('facultyPanelSearch').value = '';
            document.querySelectorAll('#facultyPanelFilters .fd-filter').forEach(button => {
                const active = button.dataset.filter === 'all';
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', String(active));
            });
            facultyPanelModal.classList.add('open');
            loadFacultyPanel();
            requestAnimationFrame(() => document.getElementById('facultyPanelSearch').focus());
        });

        document.getElementById('facultyPanelSearch').addEventListener('input', event => {
            facultyPanelState.query = event.target.value;
            renderFacultyPanel();
        });
        document.querySelectorAll('#facultyPanelFilters .fd-filter').forEach(button => {
            button.addEventListener('click', () => {
                facultyPanelState.filter = button.dataset.filter;
                document.querySelectorAll('#facultyPanelFilters .fd-filter').forEach(filter => {
                    const active = filter === button;
                    filter.classList.toggle('active', active);
                    filter.setAttribute('aria-pressed', String(active));
                });
                renderFacultyPanel();
            });
        });

        function closeFacultyPanel() {
            if (!facultyPanelModal.classList.contains('open')) return;
            facultyPanelModal.classList.remove('open');
            if (facultyPanelLastFocus && facultyPanelLastFocus.isConnected) facultyPanelLastFocus.focus();
        }

        document.getElementById('facultyPanelClose').addEventListener('click', closeFacultyPanel);
        document.getElementById('facultyPanelCloseBottom').addEventListener('click', closeFacultyPanel);
        facultyPanelModal.addEventListener('click', e => { if (e.target === facultyPanelModal) closeFacultyPanel(); });

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
            closeRoomOverview();
            approvalsModal.classList.remove('open');
            changeRequestsModal.classList.remove('open');
            closeFacultyPanel();
            closeFacultyModal();
        });

        // ---------- faculty assignment modal ----------
        const facultyModal = document.getElementById('facultyModal');
        let pendingPlot = null;
        let facultyModalLastFocus = null;

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

        function facultySubjectMatch(f, subject) {
            return (f.preferences || []).find(preference => preference.code === subject.code)?.level || '';
        }

        function facultyStatusMeta(slot, match, day, startRow, endRow) {
            if (slot.status === 'conflict') {
                const meeting = slot.meeting;
                return { tone: 'conflict', label: 'Schedule conflict', detail: `Teaching ${meeting.code || 'another class'} ${minutesTo12h(toMinutes(meeting.start))}–${minutesTo12h(toMinutes(meeting.end))}` };
            }
            if (slot.status === 'unavailable') {
                return { tone: 'unavailable', label: 'Availability conflict', detail: `Not available for ${WEEK[day].day} ${rowTo12h(startRow)}–${rowTo12h(endRow)}` };
            }
            if (match === 'preferred') return { tone: 'preferred', label: 'Preferred and available', detail: 'Best subject match' };
            if (match === 'qualified') return { tone: 'qualified', label: 'Qualified and available', detail: 'Qualified for this subject' };
            if (match === 'willing') return { tone: 'willing', label: 'Willing and available', detail: 'Willing to teach this subject' };
            return { tone: 'mismatch', label: 'Available', detail: 'No preference submitted for this subject' };
        }

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
            if (!facultyModal.classList.contains('open')) facultyModalLastFocus = document.activeElement;

            document.getElementById('facultyModalCode').textContent = subject.code;
            document.getElementById('facultyModalSubject').textContent = subject.title;
            document.getElementById('facultyModalMeeting').textContent =
                `${WEEK[day].day} · ${rowTo12h(startRow)}–${rowTo12h(endRow)} · ${roomName}`;
            document.getElementById('facultyModalWarnings').innerHTML = '';

            const list = document.getElementById('facultyModalList');
            const results = document.getElementById('facultyModalResults');
            const summary = document.getElementById('facultyModalSummary');
            results.setAttribute('aria-busy', 'true');
            summary.textContent = 'Loading faculty…';
            list.innerHTML = Array.from({ length: 4 }, () => '<div class="fc-skeleton" aria-hidden="true"><span></span><span></span><span></span></div>').join('');
            facultyModal.classList.add('open');
            requestAnimationFrame(() => document.getElementById('facultyModalSearch').focus());

            let data;
            try {
                const url = CONFIG.facultyPanelUrl + '?for_academic_year=' + encodeURIComponent(CONTEXT.academic_year);
                const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
                if (!response.ok) throw new Error('Faculty request failed');
                data = await response.json();
            } catch (e) {
                results.setAttribute('aria-busy', 'false');
                summary.textContent = '';
                list.innerHTML = '<div class="faculty-picker__state"><div><strong>Faculty could not be loaded</strong><span>Check your connection and try again, or assign this class later.</span><br><button type="button" class="btn btn-ghost faculty-picker__retry">Try again</button></div></div>';
                list.querySelector('.faculty-picker__retry').addEventListener('click', () => openFacultyModal(subject, day, startRow, endRow, roomName, reassignIndex));
                return;
            }
            results.setAttribute('aria-busy', 'false');

            const start = rowToHHMM(startRow);
            const end = rowToHHMM(endRow);
            const matchRank = { preferred: 0, qualified: 1, willing: 2, '': 3 };

            const candidates = (data.faculty || []).map(f => {
                const slot = classifyFacultySlot(f, subject, day, start, end, pendingPlot.reassignIndex != null);
                const match = facultySubjectMatch(f, subject);
                const overCap = data.max_units && (Number(f.units || 0) + (parseFloat(subject.units) || 0) > Number(data.max_units));
                return { f, slot, match, overCap };
            }).sort((a, b) => {
                const availabilityRank = candidate => candidate.slot.status === 'conflict' ? 2 : candidate.slot.status === 'unavailable' ? 1 : 0;
                return availabilityRank(a) - availabilityRank(b)
                    || (matchRank[a.match] ?? 3) - (matchRank[b.match] ?? 3)
                    || Number(a.overCap) - Number(b.overCap)
                    || a.f.units - b.f.units
                    || a.f.name.localeCompare(b.f.name);
            });
            const recommendedCandidate = candidates.find(candidate =>
                candidate.slot.status !== 'conflict'
                && candidate.slot.status !== 'unavailable'
                && !candidate.overCap
                && (candidate.match === 'preferred' || candidate.match === 'qualified'));
            if (recommendedCandidate) recommendedCandidate.recommended = true;

            if (!candidates.length) {
                summary.textContent = '0 faculty';
                list.innerHTML = '<div class="faculty-picker__state"><div><strong>No faculty found</strong><span>No faculty members are assigned to this department. You can assign this class later.</span></div></div>';
                return;
            }

            let activeFilter = 'all';
            const filters = Array.from(document.querySelectorAll('#facultyModalFilters .faculty-filter'));

            function renderCards(filterText = '') {
                list.innerHTML = '';
                const needle = (filterText || '').trim().toLowerCase();

                const visibleCandidates = candidates.filter(({ f, slot, match }) => {
                    const matchesSearch = !needle || f.name.toLowerCase().includes(needle);
                    const matchesFilter = activeFilter === 'all'
                        || (activeFilter === 'available' && slot.status !== 'conflict' && slot.status !== 'unavailable')
                        || (activeFilter === 'preferred' && match === 'preferred')
                        || (activeFilter === 'qualified' && match === 'qualified')
                        || (activeFilter === 'conflicts' && (slot.status === 'conflict' || slot.status === 'unavailable'));
                    return matchesSearch && matchesFilter;
                });

                summary.textContent = `${visibleCandidates.length} of ${candidates.length} faculty shown`;
                if (!visibleCandidates.length) {
                    list.innerHTML = '<div class="faculty-picker__state"><div><strong>No matching faculty</strong><span>Try another name or filter.</span></div></div>';
                    return;
                }

                visibleCandidates.forEach(({ f, slot, match, overCap, recommended }) => {
                        const meta = facultyStatusMeta(slot, match, day, startRow, endRow);
                        const card = document.createElement('div');
                        card.className = `faculty-card fc-tone-${meta.tone}${slot.status === 'conflict' ? ' fc-disabled' : ''}${recommended ? ' fc-recommended-row' : ''}`;
                        card.setAttribute('role', 'listitem');
                        const empLabel = f.employment_status === 'full_time' ? 'Full-time' : f.employment_status === 'part_time' ? 'Part-time' : 'Employment type not set';
                        const initials = f.name.split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();

                        const dayAvail = (f.availability || []).find(a => a.day_of_week === day);
                        const todayWindow = dayAvail
                            ? (dayAvail.is_available
                                ? `${minutesTo12h(toMinutes(dayAvail.start_time || '00:00'))} – ${minutesTo12h(toMinutes(dayAvail.end_time || '00:00'))}`
                                : 'Not available')
                            : 'Not submitted';
                        const loadPercent = data.max_units ? Math.min(100, (Number(f.units || 0) / Number(data.max_units)) * 100) : 0;
                        const workload = data.max_units ? `${f.units} of ${data.max_units} units` : `${f.units} units assigned`;
                        const matchLabel = match ? match.charAt(0).toUpperCase() + match.slice(1) : 'Not set';
                        const assignLabel = slot.status === 'unavailable' ? 'Assign anyway' : 'Assign';

                        card.innerHTML = `
                            <div class="fc-identity">
                                <div class="fc-avatar">${escapeHtml(initials)}</div>
                                <div class="fc-head-text">
                                    <div class="fc-name">${escapeHtml(f.name)}${recommended ? '<span class="fc-recommended">Recommended</span>' : ''}</div>
                                    <span class="fc-emp">${empLabel}</span>
                                    <div class="fc-status ${meta.tone}"><div><strong>${meta.label}</strong><span>${escapeHtml(meta.detail)}</span></div></div>
                                </div>
                            </div>
                            <dl class="fc-facts">
                                <div class="fc-fact"><dt>Workload</dt><dd class="${overCap ? 'is-conflict' : ''}">${workload}${overCap ? ' · over cap if assigned' : ''}</dd>${data.max_units ? `<div class="fc-load-track" aria-hidden="true"><div class="fc-load-fill ${overCap ? 'is-over' : ''}" style="width:${loadPercent}%"></div></div>` : ''}</div>
                                <div class="fc-fact"><dt>${WEEK[day].day} availability</dt><dd>${todayWindow}</dd></div>
                                <div class="fc-fact"><dt>Subject match</dt><dd>${matchLabel}</dd></div>
                            </dl>
                            <div class="fc-actions">
                                <button type="button" class="fc-btn fc-schedule" aria-expanded="false">View week</button>
                                <button type="button" class="fc-btn ${slot.status === 'unavailable' ? 'fc-btn-warning' : 'fc-btn-primary'} fc-assign" ${slot.status === 'conflict' ? 'disabled title="Schedule conflicts cannot be overridden"' : ''}>${assignLabel}</button>
                            </div>
                            <div class="fc-avail-panel" hidden></div>
                        `;

                        const scheduleBtn = card.querySelector('.fc-schedule');
                        const panel = card.querySelector('.fc-avail-panel');
                        scheduleBtn.addEventListener('click', () => {
                            const open = !panel.hidden;
                            panel.hidden = open;
                            scheduleBtn.textContent = open ? 'View week' : 'Hide week';
                            scheduleBtn.setAttribute('aria-expanded', String(!open));
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
                                    title: 'Override availability conflict?',
                                    message: `${f.name} is marked unavailable for ${WEEK[day].day} ${rowTo12h(startRow)}–${rowTo12h(endRow)}. `
                                        + 'This exception will be recorded. Add an optional reason below for the audit history.',
                                    confirmLabel: 'Assign anyway',
                                    danger: true,
                                    reason: true,
                                });
                                if (!proceed) return;
                                await recordFacultyOverride(f.id, 'unavailable', proceed.reason || '');
                                finalizePlot(f.id, f.name);
                            });
                        } else {
                            assignBtn.addEventListener('click', () => finalizePlot(f.id, f.name));
                        }

                        list.appendChild(card);
                    });
            }

            const searchInput = document.getElementById('facultyModalSearch');
            searchInput.value = '';
            searchInput.oninput = () => renderCards(searchInput.value);
            filters.forEach(filter => {
                filter.onclick = () => {
                    activeFilter = filter.dataset.filter;
                    filters.forEach(button => {
                        const selected = button === filter;
                        button.classList.toggle('active', selected);
                        button.setAttribute('aria-pressed', String(selected));
                    });
                    renderCards(searchInput.value);
                };
            });
            filters.forEach(filter => {
                const selected = filter.dataset.filter === 'all';
                filter.classList.toggle('active', selected);
                filter.setAttribute('aria-pressed', String(selected));
            });
            renderCards();
        }

        function closeFacultyModal() {
            if (!facultyModal.classList.contains('open')) return;
            pendingPlot = null;
            facultyModal.classList.remove('open');
            if (facultyModalLastFocus && facultyModalLastFocus.isConnected) facultyModalLastFocus.focus();
        }

        document.getElementById('facultyModalSkip').addEventListener('click', () => finalizePlot(null, null));
        document.getElementById('facultyModalCancel').addEventListener('click', closeFacultyModal);
        document.getElementById('facultyModalClose').addEventListener('click', closeFacultyModal);
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

                    // Blocks for subjects no longer in this term's curriculum
                    // were removed by the server; refresh so the grid matches.
                    if (Array.isArray(result.dropped) && result.dropped.length > 0) {
                        showToast('Removed outdated block(s) no longer in this term\'s curriculum: ' + result.dropped.join(', ') + '.', 'success');
                        await reloadFromServer();
                    }
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

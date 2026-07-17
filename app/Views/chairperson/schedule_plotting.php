<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Schedule Plotting</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f4f6fa;
            color: #1f2937;
            font-size: 14px;
        }

        /* ===== Header ===== */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            padding: 14px 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        .topbar h1 { font-size: 18px; }

        .topbar .right { display: flex; align-items: center; gap: 16px; }

        .topbar .user { text-align: right; }
        .topbar .user .name { font-weight: 600; }
        .topbar .user .role { font-size: 12px; color: #6b7280; }

        .btn {
            border: none;
            border-radius: 8px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-primary:disabled { background: #93c5fd; cursor: default; }
        .btn-ghost { background: #fff; color: #2563eb; border: 1px solid #2563eb; }
        .approval-badge {
            display: inline-block;
            background: #dc2626;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            border-radius: 999px;
            min-width: 17px;
            padding: 2px 5px;
            text-align: center;
            margin-left: 3px;
        }

        .approval-badge.zero { background: #d1d5db; }

        .approvals-list {
            max-height: 340px;
            overflow-y: auto;
            margin-bottom: 14px;
        }

        .approval-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 8px;
        }

        .approval-item .ap-who { font-size: 12px; font-weight: 600; margin-bottom: 4px; }
        .approval-item .ap-loads { font-size: 12px; color: #6b7280; margin-bottom: 8px; }
        .approval-item .ap-actions { display: flex; gap: 8px; justify-content: flex-end; }

        .approvals-empty {
            padding: 24px 12px;
            text-align: center;
            font-size: 12.5px;
            color: #6b7280;
        }

        .btn-danger { background: #dc2626; color: #fff; }
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
            color: #6b7280;
        }

        .sync-status .sync-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #16a34a;
        }

        .sync-status.saving .sync-dot { background: #d97706; }
        .sync-status.error .sync-dot { background: #dc2626; }
        .sync-status.error { color: #dc2626; }

        /* ===== Filters ===== */
        .filters {
            display: flex;
            gap: 12px;
            padding: 14px 24px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }

        .filters label {
            display: block;
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .filters select {
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            min-width: 160px;
        }

        /* ===== Layout ===== */
        .layout {
            display: grid;
            grid-template-columns: 260px 1fr 300px;
            gap: 16px;
            padding: 16px 24px;
            align-items: start;
        }

        .panel {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px;
        }

        .panel h2 { font-size: 14px; margin-bottom: 10px; }

        /* ===== Subjects rail ===== */
        .subject {
            border: 1px solid #e5e7eb;
            border-left: 4px solid #9ca3af;
            border-radius: 8px;
            padding: 8px 10px;
            margin-bottom: 8px;
            background: #fff;
            cursor: grab;
            user-select: none;
            transition: box-shadow .15s, opacity .15s;
        }

        .subject:active { cursor: grabbing; }
        .subject:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .subject.dragging { opacity: .4; }

        .subject .row1 {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
        }

        .subject .units { color: #6b7280; font-weight: 400; }
        .subject .title { font-weight: 600; margin: 2px 0; }
        .subject .meta { font-size: 12px; color: #6b7280; }

        /* A subject leaves the rail once it reaches its max meetings */
        .subject.is-plotted { display: none; }

        .subject .plot-count {
            font-size: 10px;
            color: #16a34a;
            font-weight: 600;
            margin-top: 2px;
        }

        .subject.blue   { border-left-color: #3b82f6; } .subject.blue .code   { color: #3b82f6; }
        .subject.purple { border-left-color: #8b5cf6; } .subject.purple .code { color: #8b5cf6; }
        .subject.cyan   { border-left-color: #06b6d4; } .subject.cyan .code   { color: #06b6d4; }
        .subject.red    { border-left-color: #ef4444; } .subject.red .code    { color: #ef4444; }
        .subject.green  { border-left-color: #22c55e; } .subject.green .code  { color: #22c55e; }
        .subject.yellow { border-left-color: #eab308; } .subject.yellow .code { color: #eab308; }

        .plotted-units {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
        }

        .plotted-units b { font-size: 15px; }

        .rail-hint { font-size: 11px; color: #9ca3af; margin-bottom: 10px; }

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
            color: #1d4ed8;
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            white-space: nowrap;
        }

        .rail-btn:hover { background: #dbeafe; }

        /* Curriculum preset modal */
        .curriculum-list {
            max-height: 320px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 14px;
        }

        .curriculum-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
        }

        .curriculum-row:last-child { border-bottom: none; }
        .curriculum-row:hover { background: #f9fafb; }

        .curriculum-row input { flex: 0 0 auto; }

        .curriculum-row .cr-details { min-width: 0; font-size: 12px; flex: 1; }
        .curriculum-row .cr-details .code { font-weight: 700; }
        .curriculum-row .cr-details .meta { color: #6b7280; }

        .curriculum-row .cr-delete {
            flex: 0 0 auto;
            border: none;
            background: none;
            color: #9ca3af;
            font-size: 15px;
            line-height: 1;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 6px;
        }

        .curriculum-row .cr-delete:hover { color: #dc2626; background: #fef2f2; }

        /* Add-subject form inside the curriculum modal */
        .add-subject {
            border: 1px dashed #93c5fd;
            background: #eff6ff;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }

        .add-subject .asf-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #1d4ed8;
            margin-bottom: 8px;
        }

        .asf-grid {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 8px;
        }

        .asf-grid input[type="text"], .asf-grid input[type="number"], .asf-grid select {
            padding: 7px 9px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-family: inherit;
            font-size: 12px;
            min-width: 0;
        }

        .asf-grid .asf-wide { grid-column: 2; }

        .asf-grid .asf-lab {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #374151;
        }

        .asf-grid .asf-btn { padding: 7px 12px; font-size: 12px; }
        .asf-actions { display: flex; gap: 6px; justify-content: flex-end; }

        .curriculum-row .cr-edit {
            flex: 0 0 auto;
            border: none;
            background: none;
            color: #9ca3af;
            font-size: 13px;
            line-height: 1;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 6px;
        }

        .curriculum-row .cr-edit:hover { color: #2563eb; background: #eff6ff; }

        /* Lab subjects: Saturday-only badge */
        .lab-badge {
            display: inline-block;
            background: #d97706;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 5px;
            margin-left: 4px;
            vertical-align: 1px;
        }

        /* ===== Weekly grid ===== */
        .schedule { overflow-x: auto; }

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
            color: #6b7280;
            margin-left: 6px;
        }

        .room-legend {
            display: flex;
            gap: 12px;
            font-size: 11px;
            color: #6b7280;
            flex-wrap: wrap;
        }

        .room-legend span { display: flex; align-items: center; gap: 5px; }

        .room-legend i {
            width: 10px;
            height: 10px;
            border-radius: 3px;
        }

        .room-legend .lg-green  { background: #16a34a; }
        .room-legend .lg-yellow { background: #d97706; }
        .room-legend .lg-red    { background: #dc2626; }
        .room-legend .lg-grey   { background: #9ca3af; }

        .grid {
            display: grid;
            grid-template-columns: 70px repeat(<?= count($week) ?>, minmax(120px, 1fr));
            min-width: 800px;
        }

        .grid .head {
            text-align: center;
            padding: 8px 4px;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
        }

        .grid .time {
            font-size: 11px;
            color: #6b7280;
            text-align: right;
            padding: 2px 8px 0 0;
            border-right: 1px solid #e5e7eb;
        }

        .day-col {
            position: relative;
            border-right: 1px solid #f3f4f6;
        }

        .slot { height: 28px; border-bottom: 1px solid #f3f4f6; }

        .block {
            position: absolute;
            left: 3px;
            right: 3px;
            border-radius: 6px;
            border-left: 4px solid;
            padding: 4px 18px 4px 6px;
            font-size: 11px;
            overflow: hidden;
        }

        .block .code  { font-weight: 700; }
        .block .title { font-weight: 600; }
        .block .meta  { color: #4b5563; }
        .block .meta.prof { color: #1f2937; font-weight: 600; }

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
            color: #6b7280;
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
            background: #f3f4f6;
            border-left-color: #9ca3af;
            border: 1px dashed #d1d5db;
            border-left: 4px solid #9ca3af;
            color: #6b7280;
            padding-right: 6px;
            z-index: 1;
        }

        .block.ghost .code, .block.ghost .title { color: #6b7280; }
        .block.ghost .meta { color: #9ca3af; }

        .block.ghost .section-tag {
            display: inline-block;
            background: #e5e7eb;
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
            border: 2px dashed #2563eb;
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
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .context-card .context-title {
            font-size: 11px;
            text-transform: uppercase;
            color: #2563eb;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .context-card .context-line { font-size: 12px; color: #1f2937; }

        .legend {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .legend-swatch {
            flex: 0 0 auto;
            width: 14px;
            height: 14px;
            margin-top: 1px;
            border: 1px dashed #d1d5db;
            border-left: 3px solid #9ca3af;
            background: #f3f4f6;
            border-radius: 3px;
        }

        .conflict {
            border: 1px solid #fecaca;
            background: #fef2f2;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .conflict .tag {
            display: inline-block;
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 999px;
            margin-bottom: 6px;
        }

        .conflict .when { font-size: 12px; color: #6b7280; margin-bottom: 6px; }

        .conflict .class-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 6px 8px;
            margin-bottom: 6px;
            font-size: 12px;
        }

        .conflict .class-card .code { font-weight: 700; }
        .conflict .class-card .meta { color: #6b7280; }

        .suggestion {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            border-radius: 8px;
            padding: 10px;
        }

        .suggestion .impact {
            display: inline-block;
            background: #3b82f6;
            color: #fff;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 999px;
            margin-bottom: 6px;
        }

        .suggestion .title { font-weight: 600; margin-bottom: 4px; }
        .suggestion .body { font-size: 12px; color: #4b5563; margin-bottom: 6px; }
        .suggestion a { font-size: 12px; color: #2563eb; }

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

        .modal .sub { font-size: 12px; color: #6b7280; margin-bottom: 14px; }

        .modal .modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .expand-btn {
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 12px;
            font-family: inherit;
            cursor: pointer;
            white-space: nowrap;
        }

        .expand-btn:hover { border-color: #2563eb; color: #2563eb; }

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
            color: #6b7280;
            padding-top: 4px;
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
            border: 1px solid #e5e7eb;
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
            background: #2563eb;
            border-color: #2563eb;
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
        .room-card.green .room-status { color: #16a34a; }
        .room-card.green .room-dot { background: #16a34a; }

        .room-card.yellow { border: 1px solid #fcd34d; background: #fffbeb; }
        .room-card.yellow .room-status { color: #d97706; }
        .room-card.yellow .room-dot { background: #d97706; }

        .room-card.red    { border: 1px solid #fca5a5; background: #fef2f2; }
        .room-card.red .room-status { color: #dc2626; }
        .room-card.red .room-dot { background: #dc2626; }

        .room-card:disabled {
            border: 1px solid #e5e7eb;
            background: #f3f4f6;
            cursor: not-allowed;
        }

        .room-card:disabled .room-name { color: #9ca3af; }
        .room-card:disabled .room-status { color: #9ca3af; }
        .room-card:disabled .room-dot { background: #9ca3af; }

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

        .modal .note { font-size: 11px; color: #9ca3af; margin-bottom: 12px; }

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
            color: #6b7280;
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
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            font-size: 12px;
            font-family: inherit;
            cursor: pointer;
        }

        .day-chip:hover { border-color: #93c5fd; }

        .day-chip.active {
            background: #2563eb;
            border-color: #2563eb;
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
            outline: 2px solid #2563eb;
            outline-offset: 1px;
        }

        .overview-list {
            max-height: 340px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 14px;
        }

        .overview-row {
            display: flex;
            gap: 12px;
            padding: 9px 12px;
            border-bottom: 1px solid #f3f4f6;
            align-items: flex-start;
        }

        .overview-row:last-child { border-bottom: none; }

        .overview-row .ov-time {
            flex: 0 0 132px;
            font-size: 12px;
            font-weight: 700;
            color: #1f2937;
        }

        .overview-row .ov-details { min-width: 0; font-size: 12px; }
        .overview-row .ov-details .code { font-weight: 700; }
        .overview-row .ov-details .meta { color: #6b7280; }

        .overview-row .ov-section {
            margin-left: auto;
            flex: 0 0 auto;
            background: #eff6ff;
            color: #2563eb;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            white-space: nowrap;
        }

        .overview-row.free {
            background: #f0fdf4;
        }

        .overview-row.free .ov-time { color: #16a34a; }
        .overview-row.free .ov-details { color: #16a34a; font-weight: 600; }

        .overview-empty {
            padding: 24px 12px;
            text-align: center;
            font-size: 12.5px;
            color: #6b7280;
        }

        /* ===== Toast ===== */
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(80px);
            background: #1f2937;
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
        .toast.error { background: #dc2626; }
        .toast.success { background: #16a34a; }
    </style>
</head>

<body>

    <header class="topbar">
        <h1>Schedule Plotting</h1>
        <div class="right">
            <div class="sync-status" id="syncStatus">
                <span class="sync-dot"></span>
                <span id="syncText">All changes saved</span>
            </div>
            <button type="button" class="btn btn-ghost" id="approvalsBtn">
                Swap Approvals <span class="approval-badge" id="approvalBadge">0</span>
            </button>
            <button type="button" class="btn btn-ghost" id="roomOverviewBtn">Room Overview</button>
            <div class="user">
                <div class="name"><?= esc($user['name']) ?></div>
                <div class="role"><?= esc($user['role']) ?></div>
            </div>
        </div>
    </header>

    <form class="filters" method="get" action="<?= site_url('chairperson/dashboard') ?>" id="contextForm">
        <?php
        $filterLabels = [
            'academic_year' => 'Academic Year',
            'program'       => 'Program',
            'year_level'    => 'Year Level',
            'section'       => 'Section',
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

    <main class="layout">

        <!-- Left rail: curriculum subjects (draggable) -->
        <aside class="panel">
            <div class="rail-head">
                <h2>Curriculum Subjects</h2>
                <button type="button" class="rail-btn" id="curriculumBtn">
                    <?= $preset_codes === null ? 'Create Curriculum' : 'Edit Curriculum' ?>
                </button>
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

            <?php foreach ($subjects as $subject): ?>
                <div class="subject <?= esc($subject['color'], 'attr') ?>"
                     draggable="true"
                     data-code="<?= esc($subject['code'], 'attr') ?>">
                    <div class="row1">
                        <span>
                            <span class="code"><?= esc($subject['code']) ?></span>
                            <?php if ($subject['is_lab']): ?><span class="lab-badge">LAB &middot; SAT</span><?php endif; ?>
                        </span>
                        <span class="units"><?= esc($subject['units']) ?></span>
                    </div>
                    <div class="title"><?= esc($subject['title']) ?></div>
                    <div class="meta"><?= esc($subject['hours']) ?></div>
                    <div class="plot-count"></div>
                </div>
            <?php endforeach; ?>

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
        </section>

        <!-- Right rail: AI scheduling assistant -->
        <aside class="panel">
            <h2>AI Scheduling Assistant</h2>

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
        </aside>

    </main>

    <!-- Room assignment modal -->
    <div class="modal-backdrop" id="roomModal">
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

    <!-- Swap approvals modal -->
    <div class="modal-backdrop" id="approvalsModal">
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

    <!-- Curriculum preset modal -->
    <div class="modal-backdrop" id="curriculumModal">
        <div class="modal">
            <h3>Curriculum Preset</h3>
            <div class="sub">
                Choose the subjects for <b><?= esc($selected['program']) ?> &middot;
                <?= esc($selected['year_level']) ?></b> in <?= esc($selected['academic_year']) ?>.
                Applies to every section of this year level.
            </div>
            <div class="add-subject">
                <div class="asf-title" id="asFormTitle">Add a new subject</div>
                <div class="asf-grid">
                    <input type="text" id="asCode" placeholder="Code (e.g. IT106)" maxlength="20">
                    <input type="text" id="asTitle" placeholder="Subject title" maxlength="200" class="asf-wide">
                    <input type="number" id="asUnits" min="0.5" max="6" step="0.5" value="3" title="Units">
                    <select id="asDuration" title="Meeting length">
                        <option value="1">1 hour</option>
                        <option value="1.5" selected>1.5 hours</option>
                        <option value="2">2 hours</option>
                        <option value="2.5">2.5 hours</option>
                        <option value="3">3 hours</option>
                    </select>
                    <label class="asf-lab"><input type="checkbox" id="asLab"> Lab (Sat)</label>
                    <div class="asf-actions">
                        <button type="button" class="btn btn-ghost asf-btn" id="asCancelEdit" style="display:none">Cancel</button>
                        <button type="button" class="btn btn-primary asf-btn" id="asAdd">+ Add</button>
                    </div>
                </div>
            </div>

            <div class="curriculum-list" id="curriculumList"></div>
            <div class="actions">
                <button type="button" class="btn btn-ghost" id="curriculumCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="curriculumSave">Save Curriculum</button>
            </div>
        </div>
    </div>

    <!-- Room overview modal -->
    <div class="modal-backdrop" id="overviewModal">
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

    <!-- Conflict warning modal -->
    <div class="modal-backdrop" id="conflictModal">
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
    <div class="modal-backdrop confirm-top" id="confirmModal">
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
            saturday: 5,
        };
        CONFIG.totalRows = (CONFIG.gridEndMin - CONFIG.gridStartMin) / 30;

        const SUBJECTS = <?= json_encode($subjects, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const CATALOG  = <?= json_encode($catalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const PRESET_CODES = <?= json_encode($preset_codes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const ROOMS    = <?= json_encode($rooms, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const WEEK     = <?= json_encode($week, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const CONTEXT  = <?= json_encode($selected, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let OTHERS     = <?= json_encode(array_values($other_blocks), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let blocks     = <?= json_encode(array_values($blocks), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let ASSIGNMENTS = <?= json_encode(array_values($assignments), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let APPROVALS   = <?= json_encode(array_values($swap_approvals), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

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
            const mins = CONFIG.gridStartMin + row * 30;
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
                prof.textContent = '👤 ' + b.prof;
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

        function renderBlocks() {
            document.querySelectorAll('.day-col .block').forEach(el => el.remove());

            WEEK.forEach((_, day) => {
                const col = document.querySelector(`.day-col[data-day="${day}"]`);
                if (!col) return;

                const items = [];

                blocks.forEach((b, i) => {
                    if (b.day !== day) return;
                    items.push({ startRow: blockStartRow(b), endRow: blockEndRow(b), el: ownEl(b, i) });
                });

                OTHERS.forEach(b => {
                    if (b.day !== day) return;
                    items.push({ startRow: blockStartRow(b), endRow: blockEndRow(b), el: ghostEl(b) });
                });

                assignLanes(items);

                items.forEach(item => {
                    positionBlock(item.el, item);
                    col.appendChild(item.el);
                });
            });

            updateRail();
            renderRoomOverview(); // keep the overview live with every change
        }

        const MAX_MEETINGS = 3; // a subject can meet up to 3 times a week

        function updateRail() {
            let units = 0;

            SUBJECTS.forEach(s => {
                const count = blocks.filter(b => b.code === s.code).length;
                const card = document.querySelector(`.subject[data-code="${s.code}"]`);

                if (card) {
                    card.classList.toggle('is-plotted', count >= MAX_MEETINGS);
                    const counter = card.querySelector('.plot-count');
                    if (counter) counter.textContent = count > 0 ? `✓ Plotted ${count}/${MAX_MEETINGS}` : '';
                }

                if (count > 0) units += parseFloat(s.units) || 0;
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
                    (dragged.is_lab && day !== CONFIG.saturday)
                    || !!sectionConflict(day, startRow, endRow)
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

                // Hard rule: laboratory subjects are Saturday-only.
                if (subject.is_lab && day !== CONFIG.saturday) {
                    showConflictModal({
                        tag: 'Invalid Day',
                        message: `${subject.code} is a laboratory subject — it can only be plotted on Saturdays.`,
                        attempted,
                        existing: null,
                    });
                    return;
                }

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

        // ---------- curriculum preset modal ----------
        const curriculumModal = document.getElementById('curriculumModal');
        const curriculumUrl = <?= json_encode(site_url('chairperson/curriculum-preset')) ?>;

        // Which catalog subjects are ticked for this year level's curriculum.
        let curriculumChecked = new Map();

        function renderCurriculumList() {
            const list = document.getElementById('curriculumList');
            list.innerHTML = '';

            CATALOG.forEach(s => {
                const row = document.createElement('label');
                row.className = 'curriculum-row';

                const box = document.createElement('input');
                box.type = 'checkbox';
                box.value = s.code;
                box.checked = curriculumChecked.get(s.code) === true;
                box.addEventListener('change', () => curriculumChecked.set(s.code, box.checked));

                const details = document.createElement('div');
                details.className = 'cr-details';
                const code = document.createElement('span');
                code.className = 'code';
                code.textContent = s.code;
                details.appendChild(code);
                details.appendChild(document.createTextNode(' — ' + s.title));
                const meta = document.createElement('div');
                meta.className = 'meta';
                meta.textContent = `${s.units} · ${s.hours}` + (s.is_lab ? ' · Lab (Saturday only)' : '');
                details.appendChild(meta);

                const edit = document.createElement('button');
                edit.type = 'button';
                edit.className = 'cr-edit';
                edit.title = 'Edit this subject';
                edit.textContent = '✎';
                edit.addEventListener('click', e => {
                    e.preventDefault();
                    e.stopPropagation();
                    enterEditMode(s);
                });

                const del = document.createElement('button');
                del.type = 'button';
                del.className = 'cr-delete';
                del.title = 'Delete this subject from the catalog';
                del.textContent = '×';
                del.addEventListener('click', e => {
                    e.preventDefault();
                    e.stopPropagation();
                    deleteSubject(s.code);
                });

                row.append(box, details, edit, del);
                list.appendChild(row);
            });
        }

        // ----- add/edit form mode -----
        let editingCode = null; // null = adding a new subject

        function enterEditMode(s) {
            editingCode = s.code;
            document.getElementById('asFormTitle').textContent = 'Edit subject: ' + s.code;
            document.getElementById('asCode').value = s.code;
            document.getElementById('asTitle').value = s.title;
            document.getElementById('asUnits').value = parseFloat(s.units) || 3;
            document.getElementById('asDuration').value = String(s.duration);
            document.getElementById('asLab').checked = !!s.is_lab;
            document.getElementById('asAdd').textContent = 'Save Changes';
            document.getElementById('asCancelEdit').style.display = '';
            document.getElementById('asCode').focus();
        }

        function exitEditMode() {
            editingCode = null;
            document.getElementById('asFormTitle').textContent = 'Add a new subject';
            document.getElementById('asCode').value = '';
            document.getElementById('asTitle').value = '';
            document.getElementById('asUnits').value = 3;
            document.getElementById('asDuration').value = '1.5';
            document.getElementById('asLab').checked = false;
            document.getElementById('asAdd').textContent = '+ Add';
            document.getElementById('asCancelEdit').style.display = 'none';
        }

        document.getElementById('asCancelEdit').addEventListener('click', exitEditMode);

        function openCurriculumModal() {
            // Existing preset: check its subjects. New preset: start with
            // everything checked and let the chairperson trim.
            curriculumChecked = new Map(CATALOG.map(s =>
                [s.code, PRESET_CODES === null || PRESET_CODES.includes(s.code)]));

            exitEditMode();
            renderCurriculumList();
            curriculumModal.classList.add('open');
        }

        async function deleteSubject(code) {
            const subject = CATALOG.find(s => s.code === code);
            const confirmed = await confirmDialog({
                title: 'Delete subject',
                message: `Delete ${code}${subject ? ' — ' + subject.title : ''} from the subject catalog? It disappears from every year level's curriculum. This is blocked while the subject is plotted on any schedule.`,
                confirmLabel: 'Delete',
                danger: true,
            });
            if (!confirmed) return;

            try {
                const response = await fetch(<?= json_encode(site_url('chairperson/subjects/delete')) ?>, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ code: code }),
                });
                const result = await response.json();

                if (response.ok && result.ok) {
                    const index = CATALOG.findIndex(s => s.code === code);
                    if (index !== -1) CATALOG.splice(index, 1);
                    curriculumChecked.delete(code);
                    renderCurriculumList();
                    showToast(`${code} deleted from the catalog.`, 'success');
                } else {
                    showToast(result.error || 'Could not delete the subject.', 'error');
                }
            } catch (err) {
                showToast('Network error while deleting the subject.', 'error');
            }
        }

        document.getElementById('asAdd').addEventListener('click', async () => {
            const payload = {
                code: document.getElementById('asCode').value.trim().toUpperCase(),
                title: document.getElementById('asTitle').value.trim(),
                units: parseFloat(document.getElementById('asUnits').value),
                duration: parseFloat(document.getElementById('asDuration').value),
                is_lab: document.getElementById('asLab').checked,
            };

            if (!payload.code || !payload.title) {
                showToast('Subject code and title are required.', 'error');
                return;
            }

            const editing = editingCode !== null;
            const details = `${payload.code} — ${payload.title} (${payload.units} units, ${payload.duration} ${payload.duration === 1 ? 'hour' : 'hours'}${payload.is_lab ? ', laboratory — Saturday only' : ''})`;

            const confirmed = await confirmDialog(editing
                ? {
                    title: 'Save subject changes',
                    message: `Save changes to ${editingCode}? New details: ${details}. Updates apply everywhere the subject is used.`,
                    confirmLabel: 'Save Changes',
                }
                : {
                    title: 'Add subject',
                    message: `Add ${details} to the subject catalog?`,
                    confirmLabel: 'Add Subject',
                });
            if (!confirmed) return;

            const btn = document.getElementById('asAdd');
            btn.disabled = true;

            try {
                const url = editing
                    ? <?= json_encode(site_url('chairperson/subjects/update')) ?>
                    : <?= json_encode(site_url('chairperson/subjects/add')) ?>;

                const body = editing ? { ...payload, original_code: editingCode } : payload;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(body),
                });
                const result = await response.json();

                if (response.ok && result.ok && result.subject) {
                    if (editing) {
                        // Reload so the rail, blocks, and presets all reflect
                        // the edited subject.
                        window.location.reload();
                        return;
                    }
                    CATALOG.push(result.subject);
                    CATALOG.sort((a, b) => a.code.localeCompare(b.code));
                    curriculumChecked.set(result.subject.code, true);
                    renderCurriculumList();
                    exitEditMode();
                    showToast(`${result.subject.code} added to the catalog.`, 'success');
                } else {
                    showToast(result.error || 'Could not save the subject.', 'error');
                }
            } catch (err) {
                showToast('Network error while saving the subject.', 'error');
            } finally {
                btn.disabled = false;
            }
        });

        document.getElementById('curriculumBtn').addEventListener('click', openCurriculumModal);
        document.getElementById('curriculumCancel').addEventListener('click', () => curriculumModal.classList.remove('open'));
        curriculumModal.addEventListener('click', e => { if (e.target === curriculumModal) curriculumModal.classList.remove('open'); });

        document.getElementById('curriculumSave').addEventListener('click', async () => {
            const codes = [...document.querySelectorAll('#curriculumList input:checked')].map(box => box.value);

            if (codes.length === 0) {
                showToast('Select at least one subject for the curriculum.', 'error');
                return;
            }

            const btn = document.getElementById('curriculumSave');
            btn.disabled = true;
            btn.textContent = 'Saving…';

            try {
                const response = await fetch(curriculumUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ context: CONTEXT, subjects: codes }),
                });
                const result = await response.json();

                if (response.ok && result.ok) {
                    // Reload so the rail reflects the new curriculum.
                    window.location.reload();
                } else {
                    showToast(result.error || 'Could not save the curriculum.', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Save Curriculum';
                }
            } catch (err) {
                showToast('Network error while saving the curriculum.', 'error');
                btn.disabled = false;
                btn.textContent = 'Save Curriculum';
            }
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

        function renderRoomGrid() {
            if (!modalArgs) return;
            const [subject, day, startRow, endRow] = modalArgs;

            roomGrid.innerHTML = '';

            FLOORS.forEach(floor => {
                if (floorFilter !== 'all' && floorFilter !== floor) return;

                const heading = document.createElement('div');
                heading.className = 'floor-heading';
                heading.textContent = floor;
                roomGrid.appendChild(heading);

                ROOMS.filter(r => r.floor === floor).forEach(room => {
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

                    const dot = document.createElement('span');
                    dot.className = 'room-dot';

                    head.append(name, dot);

                    const status = document.createElement('div');
                    status.className = 'room-status';
                    status.textContent = ROOM_STATUS_LABEL[state];

                    card.append(head, status);

                    if (!occupied) {
                        card.addEventListener('click', () => {
                            blocks.push({
                                day: day,
                                code: subject.code,
                                title: subject.title,
                                prof: subject.prof,
                                time_label: `${rowTo12h(startRow)} - ${rowTo12h(endRow)}`,
                                room: room.name,
                                theme: subject.color,
                                start: rowToHHMM(startRow),
                                end: rowToHHMM(endRow),
                            });
                            closeRoomModal();
                            renderBlocks();
                            scheduleSave();
                            showToast(`${subject.code} plotted in ${room.name}.`, 'success');
                        });
                    }

                    roomGrid.appendChild(card);
                });
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
            curriculumModal.classList.remove('open');
            approvalsModal.classList.remove('open');
        });

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
                                showToast(`${label} was claimed by ${a.username}.`, 'success');
                            } else if (known.get(a.key) !== a.username) {
                                showToast(`${label} was swapped from ${known.get(a.key)} to ${a.username}.`, 'success');
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
    </script>

</body>

</html>

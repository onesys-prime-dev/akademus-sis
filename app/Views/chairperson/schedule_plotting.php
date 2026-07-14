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
        .btn-publish { background: #16a34a; color: #fff; }
        .btn-publish:hover { background: #15803d; }
        .btn-publish:disabled { background: #86efac; cursor: default; }
        .btn-danger { background: #fff; color: #dc2626; border: 1px solid #dc2626; }
        .btn-danger:hover { background: #fef2f2; }

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

        .status-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 999px;
            vertical-align: 1px;
        }

        .status-badge.draft { background: #fef3c7; color: #b45309; }
        .status-badge.published { background: #dcfce7; color: #15803d; }

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

        .grid .head .date { font-size: 11px; color: #6b7280; font-weight: 400; }

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

        .room-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
        }

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
            <button type="button" class="btn btn-primary" id="saveDraftBtn">Save Draft</button>
            <button type="button" class="btn btn-publish" id="publishBtn">Publish</button>
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
            <h2>Curriculum Subjects</h2>
            <div class="rail-hint">Drag a subject onto the weekly grid to plot it.</div>

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
                    <div class="meta"><?= esc($subject['prof']) ?> &middot; <?= esc($subject['hours']) ?></div>
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
                    <span class="status-badge <?= $is_published ? 'published' : 'draft' ?>" id="statusBadge">
                        <?= $is_published ? 'Published' : 'Draft' ?>
                    </span>
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
                    <div class="head">
                        <?= esc($day['day']) ?>
                        <div class="date"><?= esc($day['date']) ?></div>
                    </div>
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
        <div class="modal">
            <h3>Assign a room</h3>
            <div class="sub" id="roomModalSub"></div>
            <div id="roomModalWarnings"></div>
            <div class="room-grid" id="roomGrid"></div>
            <div class="note">Occupied rooms are disabled. Room availability reflects the current draft in real time.</div>
            <div class="actions">
                <button type="button" class="btn btn-ghost" id="roomModalCancel">Cancel</button>
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

    <!-- Unsaved changes modal -->
    <div class="modal-backdrop" id="unsavedModal">
        <div class="modal">
            <h3>Unsaved changes</h3>
            <div class="sub">
                Your plotting for <b><?= esc($selected['section']) ?> &middot; <?= esc($selected['year_level']) ?></b>
                hasn't been saved. If you switch without saving, those changes will be lost.
            </div>
            <div class="actions">
                <button type="button" class="btn btn-ghost" id="unsavedStay">Stay here</button>
                <button type="button" class="btn btn-danger" id="unsavedDiscard">Discard &amp; switch</button>
                <button type="button" class="btn btn-primary" id="unsavedSave">Save as draft &amp; switch</button>
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
            saveUrl: <?= json_encode(site_url('chairperson/schedule/save-draft')) ?>,
            publishUrl: <?= json_encode(site_url('chairperson/schedule/publish')) ?>,
            othersUrl: <?= json_encode(site_url('chairperson/schedule/others')) ?>,
            pollMs: 10000,
            saturday: 5,
        };
        CONFIG.totalRows = (CONFIG.gridEndMin - CONFIG.gridStartMin) / 30;

        const SUBJECTS = <?= json_encode($subjects, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const ROOMS    = <?= json_encode($rooms, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const WEEK     = <?= json_encode($week, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const CONTEXT  = <?= json_encode($selected, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let OTHERS     = <?= json_encode(array_values($other_blocks), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let blocks     = <?= json_encode(array_values($blocks), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        // True whenever the grid differs from what's saved on the server.
        let isDirty = false;

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

        // ---------- rendering ----------
        function overlapsAny(list, day, startRow, endRow) {
            return list.some(b => b.day === day && rangesOverlap(startRow, endRow, blockStartRow(b), blockEndRow(b)));
        }

        function renderGhosts() {
            OTHERS.forEach(b => {
                const col = document.querySelector(`.day-col[data-day="${b.day}"]`);
                if (!col) return;

                const startRow = blockStartRow(b);
                const endRow = blockEndRow(b);

                const el = document.createElement('div');
                el.className = 'block ghost';
                el.style.top = (startRow * CONFIG.rowHeight) + 'px';
                el.style.height = ((endRow - startRow) * CONFIG.rowHeight - 4) + 'px';
                el.title = `${b.code} · ${b.section} · ${b.room}${b.prof ? ' · ' + b.prof : ''}`;

                // Share the column when one of our own blocks occupies the
                // same time, so it never hides behind the ghost.
                if (overlapsAny(blocks, b.day, startRow, endRow)) {
                    el.style.left = '52%';
                }

                const tag = document.createElement('div');
                tag.className = 'section-tag';
                tag.textContent = b.section;

                const code = document.createElement('div');
                code.className = 'code';
                code.textContent = b.code;

                const meta = document.createElement('div');
                meta.className = 'meta';
                meta.textContent = b.room;

                el.append(tag, code, meta);
                col.appendChild(el);
            });
        }

        function renderBlocks() {
            document.querySelectorAll('.day-col .block').forEach(el => el.remove());
            renderGhosts();

            blocks.forEach((b, i) => {
                const col = document.querySelector(`.day-col[data-day="${b.day}"]`);
                if (!col) return;

                const startRow = blockStartRow(b);
                const endRow = blockEndRow(b);

                const el = document.createElement('div');
                el.className = 'block ' + b.theme;
                el.style.top = (startRow * CONFIG.rowHeight) + 'px';
                el.style.height = ((endRow - startRow) * CONFIG.rowHeight - 4) + 'px';

                // Share the column with any overlapping ghost block.
                if (overlapsAny(OTHERS, b.day, startRow, endRow)) {
                    el.style.right = '50%';
                }

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

                const remove = document.createElement('button');
                remove.className = 'remove';
                remove.type = 'button';
                remove.title = 'Remove from schedule';
                remove.textContent = '×';
                remove.addEventListener('click', () => {
                    blocks.splice(i, 1);
                    isDirty = true;
                    setStatus('draft');
                    renderBlocks();
                    showToast('Removed ' + b.code + ' from the draft.', '');
                });

                el.append(code, title, time, room, remove);
                col.appendChild(el);
            });

            updateRail();
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
            document.getElementById('publishBtn').disabled = blocks.length === 0;
        }

        function setStatus(status) {
            const badge = document.getElementById('statusBadge');
            badge.className = 'status-badge ' + status;
            badge.textContent = status === 'published' ? 'Published' : 'Draft';
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
                `${a.subject.prof} · ${CONTEXT.section}`
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

        function openRoomModal(subject, day, startRow, endRow, warnings = []) {
            modalArgs = [subject, day, startRow, endRow, warnings];
            document.getElementById('roomModalSub').textContent =
                `${subject.code} - ${subject.title} · ${WEEK[day].day}, ${rowTo12h(startRow)} - ${rowTo12h(endRow)} · ${subject.prof}`;

            const warningsBox = document.getElementById('roomModalWarnings');
            warningsBox.innerHTML = '';
            warnings.forEach(text => {
                const warning = document.createElement('div');
                warning.className = 'modal-warning';
                warning.append(document.createTextNode(' ' + text));
                warningsBox.appendChild(warning);
            });

            roomGrid.innerHTML = '';

            ROOMS.forEach(room => {
                const state = roomStatus(room, day, startRow, endRow);
                const occupied = state === 'grey';

                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'room-card ' + state;
                card.disabled = occupied;

                const head = document.createElement('div');
                head.className = 'room-head';

                const name = document.createElement('div');
                name.className = 'room-name';
                name.textContent = room;

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
                            room: room,
                            theme: subject.color,
                            start: rowToHHMM(startRow),
                            end: rowToHHMM(endRow),
                        });
                        closeRoomModal();
                        isDirty = true;
                        setStatus('draft');
                        renderBlocks();
                        showToast(`${subject.code} plotted in ${room}.`, 'success');
                    });
                }

                roomGrid.appendChild(card);
            });

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
            closeRoomModal();
            document.getElementById('conflictModal').classList.remove('open');
            const unsaved = document.getElementById('unsavedModal');
            if (unsaved.classList.contains('open')) {
                document.getElementById('unsavedStay').click();
            }
        });

        // ---------- save draft / publish ----------
        async function submitSchedule(url, btn, busyLabel, idleLabel, successMessage) {
            btn.disabled = true;
            btn.textContent = busyLabel;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ context: CONTEXT, blocks: blocks }),
                });
                const result = await response.json();

                if (response.ok && result.ok) {
                    isDirty = false;
                    setStatus(result.status);
                    showToast(successMessage.replace('{n}', result.saved), 'success');
                } else {
                    showToast(result.error || 'Could not save the schedule.', 'error');
                }
            } catch (err) {
                showToast('Network error while saving the schedule.', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = idleLabel;
                updateRail();
            }
        }

        document.getElementById('saveDraftBtn').addEventListener('click', e =>
            submitSchedule(CONFIG.saveUrl, e.currentTarget, 'Saving…', 'Save Draft', 'Draft saved ({n} classes).'));

        document.getElementById('publishBtn').addEventListener('click', e =>
            submitSchedule(CONFIG.publishUrl, e.currentTarget, 'Publishing…', 'Publish', 'Schedule published ({n} classes).'));

        // ---------- unsaved-changes guard on filter switch ----------
        const contextForm = document.getElementById('contextForm');
        const contextSelects = contextForm.querySelectorAll('.context-select');
        const unsavedModal = document.getElementById('unsavedModal');

        const originalContext = {};
        contextSelects.forEach(sel => { originalContext[sel.name] = sel.value; });

        function revertContextSelects() {
            contextSelects.forEach(sel => { sel.value = originalContext[sel.name]; });
        }

        contextSelects.forEach(sel => sel.addEventListener('change', () => {
            if (!isDirty) {
                contextForm.submit();
                return;
            }
            unsavedModal.classList.add('open');
        }));

        function stayHere() {
            revertContextSelects();
            unsavedModal.classList.remove('open');
        }

        document.getElementById('unsavedStay').addEventListener('click', stayHere);
        unsavedModal.addEventListener('click', e => { if (e.target === unsavedModal) stayHere(); });

        document.getElementById('unsavedDiscard').addEventListener('click', () => {
            isDirty = false; // allow the navigation without the beforeunload prompt
            contextForm.submit();
        });

        document.getElementById('unsavedSave').addEventListener('click', async () => {
            const btn = document.getElementById('unsavedSave');
            btn.disabled = true;
            btn.textContent = 'Saving…';

            try {
                const response = await fetch(CONFIG.saveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ context: CONTEXT, blocks: blocks }),
                });
                const result = await response.json();

                if (response.ok && result.ok) {
                    isDirty = false;
                    contextForm.submit();
                } else {
                    stayHere();
                    showToast(result.error || 'Could not save the draft.', 'error');
                }
            } catch (err) {
                stayHere();
                showToast('Network error while saving the draft.', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save as draft & switch';
            }
        });

        // Warn before closing/leaving the tab with unsaved plotting.
        window.addEventListener('beforeunload', e => {
            if (isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // ---------- live updates from other sections ----------
        async function refreshOthers() {
            try {
                const query = new URLSearchParams(CONTEXT).toString();
                const response = await fetch(`${CONFIG.othersUrl}?${query}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) return;

                const data = await response.json();
                if (!Array.isArray(data.others)) return;

                if (JSON.stringify(data.others) !== JSON.stringify(OTHERS)) {
                    OTHERS = data.others;
                    renderBlocks();
                    // Refresh room availability if the modal is open right now.
                    if (modalArgs) openRoomModal(...modalArgs);
                }
            } catch (err) {
                // Offline or server hiccup: try again on the next tick.
            }
        }
        setInterval(refreshOthers, CONFIG.pollMs);

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
    </script>

</body>

</html>

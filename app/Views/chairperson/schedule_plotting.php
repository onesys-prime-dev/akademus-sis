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

        .subject .plotted-badge {
            display: none;
            font-size: 10px;
            color: #16a34a;
            font-weight: 600;
            margin-top: 2px;
        }

        .subject.is-plotted .plotted-badge { display: block; }

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

        /* ===== Weekly grid ===== */
        .schedule { overflow-x: auto; }

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
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            border-radius: 8px;
            padding: 10px;
            cursor: pointer;
            text-align: left;
            font-family: inherit;
        }

        .room-card .room-name { font-weight: 700; font-size: 13px; }
        .room-card .room-status { font-size: 11px; color: #16a34a; }

        .room-card:hover:not(:disabled) { border-color: #22c55e; box-shadow: 0 2px 8px rgba(34,197,94,.2); }

        .room-card:disabled {
            border-color: #e5e7eb;
            background: #f3f4f6;
            cursor: not-allowed;
        }

        .room-card:disabled .room-name { color: #9ca3af; }
        .room-card:disabled .room-status { color: #9ca3af; }

        .modal .note { font-size: 11px; color: #9ca3af; margin-bottom: 12px; }

        .modal .actions { text-align: right; }

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
                <select name="<?= esc($name, 'attr') ?>" onchange="this.form.submit()">
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
                        <span class="code"><?= esc($subject['code']) ?></span>
                        <span class="units"><?= esc($subject['units']) ?></span>
                    </div>
                    <div class="title"><?= esc($subject['title']) ?></div>
                    <div class="meta"><?= esc($subject['prof']) ?> &middot; <?= esc($subject['hours']) ?></div>
                    <div class="plotted-badge">&#10003; Plotted</div>
                </div>
            <?php endforeach; ?>

            <div class="plotted-units">
                <span>Plotted Units</span>
                <b id="plottedUnits">0</b>
            </div>
        </aside>

        <!-- Center: weekly schedule grid -->
        <section class="panel schedule">
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
            <div class="room-grid" id="roomGrid"></div>
            <div class="note">Occupied rooms are disabled. Room availability reflects the current draft in real time.</div>
            <div class="actions">
                <button type="button" class="btn btn-ghost" id="roomModalCancel">Cancel</button>
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
        };
        CONFIG.totalRows = (CONFIG.gridEndMin - CONFIG.gridStartMin) / 30;

        const SUBJECTS = <?= json_encode($subjects, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const ROOMS    = <?= json_encode($rooms, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const WEEK     = <?= json_encode($week, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const CONTEXT  = <?= json_encode($selected, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const OTHERS   = <?= json_encode(array_values($other_blocks), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let blocks     = <?= json_encode(array_values($blocks), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

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
                    renderBlocks();
                    showToast('Removed ' + b.code + ' from the draft.', '');
                });

                el.append(code, title, time, room, remove);
                col.appendChild(el);
            });

            updateRail();
        }

        function updateRail() {
            const plottedCodes = new Set(blocks.map(b => b.code));
            let units = 0;

            SUBJECTS.forEach(s => {
                const card = document.querySelector(`.subject[data-code="${s.code}"]`);
                const plotted = plottedCodes.has(s.code);
                if (card) card.classList.toggle('is-plotted', plotted);
                if (plotted) units += parseFloat(s.units) || 0;
            });

            document.getElementById('plottedUnits').textContent = units;
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
            const hours = parseFloat(subject.hours) || 1.5;
            return Math.max(1, Math.round(hours * 2));
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
        function roomOccupied(room, day, startRow, endRow) {
            return blocks.concat(OTHERS).some(b =>
                b.room === room && b.day === day && rangesOverlap(startRow, endRow, blockStartRow(b), blockEndRow(b)));
        }

        document.querySelectorAll('.day-col').forEach(col => {
            col.addEventListener('dragover', e => {
                if (!dragged) return;
                e.preventDefault();

                const day = Number(col.dataset.day);
                const { startRow, endRow } = dropRange(col, e.clientY, dragged);

                preview.style.top = (startRow * CONFIG.rowHeight) + 'px';
                preview.style.height = ((endRow - startRow) * CONFIG.rowHeight - 4) + 'px';
                preview.classList.toggle('invalid',
                    !!sectionConflict(day, startRow, endRow) || !!facultyConflict(dragged.prof, day, startRow, endRow));
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

                const clash = sectionConflict(day, startRow, endRow);
                if (clash) {
                    showToast(`Conflict: ${clash.code} already occupies ${clash.time_label} on ${WEEK[day].day}.`, 'error');
                    return;
                }

                const profClash = facultyConflict(subject.prof, day, startRow, endRow);
                if (profClash) {
                    showToast(`Faculty conflict: ${subject.prof} teaches ${profClash.code}${profClash.section ? ' (' + profClash.section + ')' : ''} at ${profClash.time_label}.`, 'error');
                    return;
                }

                openRoomModal(subject, day, startRow, endRow);
            });
        });

        // ---------- room assignment modal ----------
        const modal = document.getElementById('roomModal');
        const roomGrid = document.getElementById('roomGrid');

        function openRoomModal(subject, day, startRow, endRow) {
            document.getElementById('roomModalSub').textContent =
                `${subject.code} - ${subject.title} · ${WEEK[day].day}, ${rowTo12h(startRow)} - ${rowTo12h(endRow)} · ${subject.prof}`;

            roomGrid.innerHTML = '';

            ROOMS.forEach(room => {
                const occupied = roomOccupied(room, day, startRow, endRow);

                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'room-card';
                card.disabled = occupied;

                const name = document.createElement('div');
                name.className = 'room-name';
                name.textContent = room;

                const status = document.createElement('div');
                status.className = 'room-status';
                status.textContent = occupied ? 'Occupied' : 'Available';

                card.append(name, status);

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
                        renderBlocks();
                        showToast(`${subject.code} plotted in ${room}.`, 'success');
                    });
                }

                roomGrid.appendChild(card);
            });

            modal.classList.add('open');
        }

        function closeRoomModal() {
            modal.classList.remove('open');
        }

        document.getElementById('roomModalCancel').addEventListener('click', closeRoomModal);
        modal.addEventListener('click', e => { if (e.target === modal) closeRoomModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeRoomModal(); });

        // ---------- save draft ----------
        document.getElementById('saveDraftBtn').addEventListener('click', async () => {
            const btn = document.getElementById('saveDraftBtn');
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
                    showToast(`Draft saved (${result.saved} classes).`, 'success');
                } else {
                    showToast(result.error || 'Could not save the draft.', 'error');
                }
            } catch (err) {
                showToast('Network error while saving the draft.', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Draft';
            }
        });

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

<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'curriculum';
$pageTitle     = 'Curriculum';
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
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    /* ===== Curriculum info card ===== */
    .info-card { display: grid; grid-template-columns: 1.4fr 1fr 1fr; gap: 20px; align-items: center; }
    .info-card .field { margin-bottom: 0; }
    .info-card select {
        width: 100%; padding: 10px 14px; border: 1px solid var(--color-border);
        border-radius: var(--radius-sm); background: var(--color-surface-secondary);
        font-family: inherit; font-size: 14px; color: var(--color-text-primary);
    }
    .info-card select:focus { outline: 2px solid var(--color-primary); outline-offset: 1px; }

    /* ===== Year-level tabs ===== */
    .year-tabs {
        display: flex; gap: 4px; background: var(--color-surface-secondary);
        border-radius: var(--radius-sm); padding: 4px; margin: 20px 0;
    }
    .year-tab {
        flex: 1; text-align: center; padding: 10px 12px; border-radius: 9px;
        font-size: 13px; font-weight: 700; color: var(--color-text-secondary);
        text-decoration: none; transition: background .15s, color .15s, box-shadow .15s;
    }
    .year-tab:hover { color: var(--color-text-primary); }
    .year-tab.active { background: var(--color-surface); color: var(--color-primary); box-shadow: var(--shadow-sm); }
    .year-tab .count { display: block; font-size: 11px; font-weight: 600; opacity: .75; margin-top: 2px; }

    .readonly-banner {
        display: flex; align-items: center; gap: 10px;
        background: var(--color-warning-soft); border: 1px solid #fde68a; color: #92400e;
        border-radius: var(--radius-sm); padding: 12px 16px; font-size: 13px; margin-bottom: 20px;
    }
    .readonly-banner .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--color-warning); flex: none; }

    /* ===== Panel header row ===== */
    .panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
    .panel-head h2 { margin-bottom: 4px; }

    /* ===== Copy-from-another-term row (hidden until toggled) ===== */
    .copy-row {
        display: none; align-items: center; gap: 10px; margin: 12px 0 16px;
        padding: 12px 14px; background: var(--color-surface-secondary); border-radius: var(--radius-sm);
    }
    .copy-row.open { display: flex; }
    .copy-row select {
        flex: 1; padding: 10px 14px; border: 1px solid var(--color-border);
        border-radius: var(--radius-sm); background: var(--color-surface);
        font-family: inherit; font-size: 14px;
    }

    /* ===== Subject table ===== */
    .subject-table-wrap { border: 1px solid var(--color-border); border-radius: var(--radius-sm); overflow: hidden; }
    table.subject-table { width: 100%; border-collapse: collapse; }
    table.subject-table th {
        text-align: left; font-size: 10.5px; font-weight: 700; text-transform: uppercase;
        letter-spacing: .05em; color: var(--color-text-secondary); background: var(--color-surface-secondary);
        padding: 10px 14px; border-bottom: 1px solid var(--color-border);
    }
    table.subject-table td { padding: 12px 14px; border-bottom: 1px solid var(--color-border); font-size: 13.5px; vertical-align: middle; }
    table.subject-table tr:last-child td { border-bottom: none; }
    table.subject-table tbody tr:hover { background: var(--color-surface-secondary); }
    .subj-code { font-weight: 700; color: var(--color-primary); }
    .subj-title { font-weight: 600; color: var(--color-text-primary); }
    .type-chip {
        display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 10px;
        border-radius: var(--radius-pill); background: var(--color-surface-sunken); color: var(--color-text-secondary);
    }
    .type-chip.lab { background: var(--color-primary-soft); color: var(--color-primary); }
    .usage-chip {
        display: inline-block; font-size: 10.5px; font-weight: 600; padding: 3px 9px;
        border-radius: var(--radius-pill); background: var(--color-success-soft); color: #056945;
        margin: 2px 4px 2px 0;
    }
    .usage-chip--none { background: var(--color-surface-sunken); color: var(--color-text-secondary); }
    .type-select {
        font-size: 11.5px; font-weight: 700; padding: 4px 8px; border-radius: 8px;
        border: 1px solid var(--color-border); background: var(--color-surface-secondary);
        color: var(--color-text-primary); font-family: inherit;
    }
    .type-select.lab { background: var(--color-primary-soft); border-color: var(--color-primary); color: var(--color-primary); }
    .row-actions { display: flex; justify-content: flex-end; gap: 2px; }
    .icon-btn { border: none; background: none; cursor: pointer; color: var(--color-text-tertiary); padding: 6px; border-radius: 8px; display: flex; }
    .icon-btn:hover { background: var(--color-danger-soft); color: var(--color-danger); }
    .icon-btn.edit:hover { background: var(--color-surface-sunken); color: var(--color-text-primary); }
    .table-footer {
        display: flex; justify-content: flex-end; gap: 20px; padding: 10px 14px;
        background: var(--color-surface-secondary); border-top: 1px solid var(--color-border);
        font-size: 12px; font-weight: 700; color: var(--color-text-secondary);
    }
    .table-footer b { color: var(--color-text-primary); }

    /* ===== Add/edit subject modal ===== */
    .modal-backdrop {
        position: fixed; inset: 0; background: rgba(15, 23, 42, .5);
        display: flex; align-items: center; justify-content: center; z-index: 70;
        opacity: 0; visibility: hidden; pointer-events: none;
        transition: opacity var(--motion-normal) var(--ease-standard), visibility 0s linear var(--motion-normal);
    }
    .modal-backdrop.open {
        opacity: 1; visibility: visible; pointer-events: auto;
        transition: opacity var(--motion-normal) var(--ease-standard), visibility 0s linear 0s;
    }
    .modal-card {
        background: var(--color-surface); border-radius: var(--radius-lg);
        width: 560px; max-width: calc(100vw - 48px); max-height: calc(100vh - 64px);
        overflow-y: auto; padding: 32px; box-shadow: var(--shadow-md); box-sizing: border-box;
        transform: scale(.97) translateY(8px); opacity: 0;
        transition: transform var(--motion-normal) var(--ease-enter), opacity var(--motion-normal) var(--ease-enter);
    }
    .modal-backdrop.open .modal-card { transform: scale(1) translateY(0); opacity: 1; }
    .modal-card h2 { font-size: 21px; font-weight: 800; margin-bottom: 8px; letter-spacing: -.01em; }
    .modal-card .modal-sub { font-size: 13.5px; color: var(--color-text-secondary); margin-bottom: 28px; line-height: 1.5; }
    .modal-card .field { margin-bottom: 20px; min-width: 0; }
    .modal-card .field label { font-size: 11.5px; font-weight: 700; margin-bottom: 8px; }
    .modal-card .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
    .modal-card .field-row .field { margin-bottom: 0; min-width: 0; }
    .modal-card input, .modal-card select {
        box-sizing: border-box; width: 100%; max-width: 100%; padding: 12px 14px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm); background: var(--color-surface-secondary);
        font-family: inherit; font-size: 14.5px; color: var(--color-text-primary);
        transition: border-color .15s, background .15s;
    }
    .modal-card select { padding-right: 36px; }
    .modal-card input:hover, .modal-card select:hover { border-color: var(--color-text-tertiary); }
    .modal-card input:focus, .modal-card select:focus {
        outline: none; border-color: var(--color-primary); background: var(--color-surface);
        box-shadow: var(--focus-ring);
    }
    .subject-form-lab { display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: var(--color-text-secondary); margin-bottom: 28px; padding: 2px 0; }
    .subject-form-lab input { width: auto; margin: 0; }
    .modal-actions { border-top: 1px solid var(--color-border); margin-top: 4px; padding-top: 20px; }
    .modal-actions .btn { padding: 11px 22px; font-size: 14px; }
    .modal-actions { display: flex; justify-content: flex-end; gap: 8px; }

    /* ===== Full catalog toggle ===== */
    .catalog-toggle {
        background: none; border: none; color: var(--color-primary); font-weight: 700;
        font-size: 12.5px; cursor: pointer; padding: 0; display: flex; align-items: center; gap: 4px; margin-top: 18px;
    }
    .catalog-toggle .material-symbols-outlined { font-size: 18px; transition: transform .15s; }
    .catalog-toggle.open .material-symbols-outlined { transform: rotate(180deg); }
    #fullCatalog { display: none; margin-top: 14px; }
    #fullCatalog.open { display: block; }
    #fullCatalog .warn { font-size: 12px; color: #92400e; background: var(--color-warning-soft); border: 1px solid #fde68a; border-radius: var(--radius-sm); padding: 8px 12px; margin-bottom: 12px; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Curriculum</h2>
        <p class="ac-page-header__sub">Assign subjects per year level and manage your program's subject catalog.</p>
    </div>
</div>

<?php if ($deptName === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>You're not assigned to a department yet. Contact an administrator.</p>
        </div>
    </div>
<?php else: ?>

    <div id="feedback" class="flash" style="display:none;"></div>

    <div class="panel">
        <form class="info-card" method="get" action="<?= site_url('chairperson/curriculum') ?>" id="contextForm">
            <div class="field">
                <label>Program</label>
                <select name="program" class="context-select">
                    <?php foreach ($filters['program'] as $option): ?>
                        <option <?= $option === $selected['program'] ? 'selected' : '' ?>><?= esc($option) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="field">
                <label>Academic Year</label>
                <select name="ay_label" class="context-select">
                    <?php foreach ($filters['academic_year_labels'] as $label): ?>
                        <option <?= $label === $selected['ay_label'] ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="field">
                <label>Semester</label>
                <select name="semester" class="context-select">
                    <?php foreach ($filters['semester'] as $option): ?>
                        <option <?= $option === $selected['semester'] ? 'selected' : '' ?>><?= esc($option) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <input type="hidden" name="year_level" value="<?= esc($selected['year_level'], 'attr') ?>">
        </form>

        <div class="year-tabs">
            <?php foreach ($filters['year_level'] as $yl): ?>
                <a class="year-tab <?= $yl === $selected['year_level'] ? 'active' : '' ?>"
                   href="<?= site_url('chairperson/curriculum') . '?' . http_build_query(['ay_label' => $selected['ay_label'], 'semester' => $selected['semester'], 'academic_year' => $selected['academic_year'], 'program' => $selected['program'], 'year_level' => $yl]) ?>">
                    <?= esc($yl) ?>
                </a>
            <?php endforeach ?>
        </div>

        <?php if (! empty($managed_curriculum)): ?>
            <div class="readonly-banner">
                <span class="dot"></span>
                <span>These subjects come from published curriculum <b><?= esc($managed_curriculum['curriculum_code']) ?></b> (v<?= esc($managed_curriculum['version_number']) ?>) and are read-only here. To change them, publish a new curriculum version under Admin → Curriculum.</span>
            </div>
        <?php elseif (! $is_active_year): ?>
            <div class="readonly-banner">
                <span class="dot"></span>
                <span><b><?= esc($selected['academic_year']) ?></b> is not the active term — changes here are read-only.</span>
            </div>
        <?php endif ?>

        <div class="panel-head">
            <h2><?= esc($selected['year_level']) ?> Subjects</h2>
            <?php if ($is_active_year): ?>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-secondary" id="copyToggle">
                        <span class="material-symbols-outlined" style="font-size:18px;">content_copy</span> Copy from Another Term
                    </button>
                    <button type="button" class="btn btn-secondary" id="addToggle">
                        <span class="material-symbols-outlined" style="font-size:18px;">add</span> Add Subject
                    </button>
                </div>
            <?php endif ?>
        </div>
        <p class="panel-sub">Only subjects assigned here belong to <b><?= esc($selected['year_level']) ?></b> — removing one never affects another year level or semester.</p>

        <?php if ($is_active_year): ?>
            <div id="copyRow" class="copy-row">
                <select id="copySource">
                    <option value="">Choose a term to copy from…</option>
                    <?php foreach ($filters['academic_year'] as $ay): ?>
                        <?php if ($ay === $selected['academic_year']) continue; ?>
                        <option value="<?= esc($ay, 'attr') ?>"><?= esc($ay) ?></option>
                    <?php endforeach ?>
                </select>
                <button type="button" class="btn btn-primary" id="copyBtn">Copy</button>
                <button type="button" class="btn btn-secondary" id="copyCancel">Cancel</button>
            </div>
        <?php endif ?>

        <div class="subject-table-wrap">
            <table class="subject-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Subject</th>
                        <th>Units</th>
                        <th>Hours</th>
                        <th>Type</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="subjectList"></tbody>
            </table>
            <div class="table-footer" id="tableFooter"></div>
        </div>

        <button type="button" class="catalog-toggle" id="catalogToggle">
            <span class="material-symbols-outlined">expand_more</span>
            Manage full subject catalog (<?= esc($selected['program']) ?>, all year levels)
        </button>
        <div id="fullCatalog">
            <div class="warn">Editing or deleting here changes the subject everywhere it's used across every year level — not just <?= esc($selected['year_level']) ?>.</div>
            <div class="subject-table-wrap">
                <table class="subject-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Subject</th>
                            <th>Units</th>
                            <th>Hours</th>
                            <th>Type</th>
                            <th>Plotted In</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="catalogList"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add / edit subject modal -->
    <div class="modal-backdrop" id="subjectModal" role="dialog" aria-modal="true" aria-labelledby="formTitle">
        <div class="modal-card">
            <h2 id="formTitle">Add a New Subject</h2>
            <p class="modal-sub" id="formSub">Creates a subject in <?= esc($selected['program']) ?>'s catalog and assigns it to <?= esc($selected['year_level']) ?> right away.</p>

            <div class="field">
                <label>Code</label>
                <input type="text" id="asCode" placeholder="e.g. IT106" maxlength="20">
            </div>
            <div class="field">
                <label>Subject Title</label>
                <input type="text" id="asTitle" placeholder="Subject title" maxlength="200">
            </div>
            <div class="field-row">
                <div class="field">
                    <label>Units</label>
                    <input type="number" id="asUnits" min="0.5" max="6" step="0.5" value="3">
                </div>
                <div class="field">
                    <label>Meeting Length</label>
                    <select id="asDuration">
                        <option value="1">1 hour</option>
                        <option value="1.5" selected>1.5 hours</option>
                        <option value="2">2 hours</option>
                        <option value="2.5">2.5 hours</option>
                        <option value="3">3 hours</option>
                    </select>
                </div>
            </div>
            <label class="subject-form-lab"><input type="checkbox" id="asLab"> Laboratory subject</label>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="modalCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="asAdd">+ Add</button>
            </div>
        </div>
    </div>

<?php endif ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    document.querySelectorAll('#contextForm .context-select').forEach(sel =>
        sel.addEventListener('change', () => sel.form.submit()));

    function showFeedback(message, isError) {
        const el = document.getElementById('feedback');
        el.style.display = 'block';
        el.className = 'flash ' + (isError ? 'error' : 'success');
        el.textContent = message;
    }

    async function postJSON(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        return res.json();
    }

    <?php if ($deptName !== null): ?>

    const CONTEXT = <?= json_encode($selected, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    let CATALOG = <?= json_encode($catalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const ASSIGNED_SUBJECTS = <?= json_encode($assigned_subjects, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    // Every (year_level + academic_year/semester) each catalog subject is
    // currently plotted under, across the whole program — not just the
    // year level being viewed right now.
    let USAGE_BY_CODE = <?= json_encode($usage_by_code, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    // Subjects assigned to THIS year level only — never shared with other
    // year levels. A fresh year starts with NOTHING assigned.
    let ASSIGNED_CODES = ASSIGNED_SUBJECTS.map(s => s.code);
    // THIS curriculum's lab/lecture classification per subject — separate
    // from the subject's global default, since the same subject can be
    // lecture in one year level and laboratory in another.
    let ASSIGNED_LAB = new Map(ASSIGNED_SUBJECTS.map(s => [s.code, !!s.is_lab]));
    const IS_ACTIVE_YEAR = <?= json_encode($is_active_year) ?>;

    let editingCode = null; // null = adding a new subject

    function subjectByCode(code) {
        return CATALOG.find(s => s.code === code) || null;
    }

    function typeChip(s) {
        return s.is_lab ? '<span class="type-chip lab">Laboratory</span>' : '<span class="type-chip">Lecture</span>';
    }

    // Keeps the "Plotted In" column in the full-catalog panel accurate
    // without a page reload whenever this year level's assignment changes.
    function currentUsageLabel() {
        return `${CONTEXT.year_level} · ${CONTEXT.academic_year}`;
    }

    function markUsage(code, present) {
        const label = currentUsageLabel();
        const list = USAGE_BY_CODE[code] || [];
        USAGE_BY_CODE[code] = present
            ? (list.includes(label) ? list : [...list, label])
            : list.filter(l => l !== label);
    }

    function subjectRow(s, { removable, onEdit, onRemove, onAssign }) {
        const tr = document.createElement('tr');
        const isAssigned = ASSIGNED_CODES.includes(s.code);

        const assignBtn = onAssign
            ? (isAssigned
                ? `<span class="icon-btn" title="Already in ${CONTEXT.year_level}" style="cursor:default; color:#16a34a;">
                       <span class="material-symbols-outlined" style="font-size:18px;">check_circle</span>
                   </span>`
                : `<button type="button" class="icon-btn" title="Assign to ${CONTEXT.year_level}" data-act="assign">
                       <span class="material-symbols-outlined" style="font-size:18px;">playlist_add</span>
                   </button>`)
            : '';

        const actions = removable
            ? `<button type="button" class="icon-btn edit" title="Edit (applies everywhere)" data-act="edit">
                   <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
               </button>
               <button type="button" class="icon-btn" title="Remove from ${CONTEXT.year_level}" data-act="remove">
                   <span class="material-symbols-outlined" style="font-size:18px;">close</span>
               </button>`
            : `${assignBtn}
               <button type="button" class="icon-btn edit" title="Edit (applies everywhere)" data-act="edit">
                   <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
               </button>
               <button type="button" class="icon-btn" title="Delete from entire catalog" data-act="delete">
                   <span class="material-symbols-outlined" style="font-size:18px;">delete_forever</span>
               </button>`;

        const usage = USAGE_BY_CODE[s.code] || [];
        const usageCell = usage.length
            ? usage.map(u => `<span class="usage-chip">${u}</span>`).join('')
            : '<span class="usage-chip usage-chip--none">Not plotted anywhere</span>';

        tr.innerHTML = `
            <td class="subj-code">${s.code}</td>
            <td class="subj-title">${s.title}</td>
            <td>${s.units}</td>
            <td>${s.hours}</td>
            <td>${typeChip(s)}</td>
            <td>${usageCell}</td>
            <td><div class="row-actions">${actions}</div></td>
        `;

        tr.querySelector('[data-act="edit"]').addEventListener('click', onEdit);
        const removeBtn = tr.querySelector('[data-act="remove"], [data-act="delete"]');
        if (removeBtn) removeBtn.addEventListener('click', onRemove);
        const assignActionBtn = tr.querySelector('[data-act="assign"]');
        if (assignActionBtn) assignActionBtn.addEventListener('click', onAssign);

        return tr;
    }

    // ----- This year level's assigned subjects -----
    function assignedSubjectRow(s) {
        const tr = document.createElement('tr');
        const isLab = ASSIGNED_LAB.get(s.code);

        tr.innerHTML = `
            <td class="subj-code">${s.code}</td>
            <td class="subj-title">${s.title}</td>
            <td>${s.units}</td>
            <td>${s.hours}</td>
            <td>
                <select class="type-select ${isLab ? 'lab' : ''}" ${IS_ACTIVE_YEAR ? '' : 'disabled'}>
                    <option value="0" ${!isLab ? 'selected' : ''}>Lecture</option>
                    <option value="1" ${isLab ? 'selected' : ''}>Laboratory</option>
                </select>
            </td>
            <td>
                <div class="row-actions">
                    <button type="button" class="icon-btn edit" title="Edit (applies everywhere)" data-act="edit">
                        <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                    </button>
                    ${IS_ACTIVE_YEAR ? `<button type="button" class="icon-btn" title="Remove from ${CONTEXT.year_level}" data-act="remove">
                        <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                    </button>` : ''}
                </div>
            </td>
        `;

        tr.querySelector('.type-select').addEventListener('change', function () {
            updateSubjectType(s.code, this.value === '1');
        });
        tr.querySelector('[data-act="edit"]').addEventListener('click', () => enterEditMode(s));
        const removeBtn = tr.querySelector('[data-act="remove"]');
        if (removeBtn) removeBtn.addEventListener('click', () => unassignFromThisYear(s.code));

        return tr;
    }

    function renderSubjectList() {
        const body = document.getElementById('subjectList');
        body.innerHTML = '';

        const assigned = ASSIGNED_CODES.map(subjectByCode).filter(Boolean);

        if (assigned.length === 0) {
            body.innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--color-text-secondary); padding:28px;">
                No subjects assigned to ${CONTEXT.year_level} yet — add one below.
            </td></tr>`;
        } else {
            assigned.forEach(s => body.appendChild(assignedSubjectRow(s)));
        }

        const totalUnits = assigned.reduce((sum, s) => sum + (parseFloat(s.units) || 0), 0);
        document.getElementById('tableFooter').textContent = assigned.length
            ? `${assigned.length} subject${assigned.length === 1 ? '' : 's'} · ${totalUnits} units total`
            : '';
    }

    async function updateSubjectType(code, isLab) {
        const result = await postJSON('<?= site_url('chairperson/curriculum-preset/subject-type') ?>', {
            context: CONTEXT, code: code, is_laboratory: isLab,
        });

        if (!result.ok) {
            showFeedback(result.error || 'Could not update the subject type.', true);
            renderSubjectList(); // revert the select to the last known-good state
            return;
        }

        ASSIGNED_LAB.set(code, isLab);
        renderSubjectList();
        showFeedback(`${code} set to ${isLab ? 'Laboratory' : 'Lecture'} for ${CONTEXT.year_level}.`, false);
    }

    function entriesForSave(codes) {
        return codes.map(code => ({
            code,
            is_laboratory: ASSIGNED_LAB.has(code) ? ASSIGNED_LAB.get(code) : (subjectByCode(code)?.is_lab || false),
        }));
    }

    async function saveAssignedCodes(codes, successMessage) {
        const result = await postJSON('<?= site_url('chairperson/curriculum-preset') ?>', { context: CONTEXT, subjects: entriesForSave(codes) });

        if (!result.ok) {
            showFeedback(result.error || 'Could not update the curriculum.', true);
            return false;
        }

        ASSIGNED_CODES = codes;
        renderSubjectList();
        showFeedback(successMessage, false);
        return true;
    }

    async function unassignFromThisYear(code) {
        const s = subjectByCode(code);
        if (!confirm(`Remove ${code}${s ? ' — ' + s.title : ''} from ${CONTEXT.year_level}? It stays untouched in every other year level.`)) {
            return;
        }

        const codes = ASSIGNED_CODES.filter(c => c !== code);

        const successMessage = codes.length === 0
            ? `${code} removed — ${CONTEXT.year_level}'s curriculum is now empty.`
            : `${code} removed from ${CONTEXT.year_level}.`;

        const saved = await saveAssignedCodes(codes, successMessage);
        if (saved) {
            ASSIGNED_LAB.delete(code);
            markUsage(code, false);
        }
    }

    // ----- Add / edit a subject modal -----
    const subjectModal = document.getElementById('subjectModal');
    let subjectModalLastFocused = null;

    function openAddModal() {
        exitEditMode();
        subjectModalLastFocused = document.activeElement;
        subjectModal.classList.add('open');
        document.getElementById('asCode').focus();
    }

    function closeSubjectModal() {
        subjectModal.classList.remove('open');
        if (subjectModalLastFocused && typeof subjectModalLastFocused.focus === 'function') subjectModalLastFocused.focus();
    }

    document.getElementById('addToggle')?.addEventListener('click', openAddModal);
    document.getElementById('modalCancel').addEventListener('click', closeSubjectModal);
    subjectModal.addEventListener('click', e => { if (e.target === subjectModal) closeSubjectModal(); });
    document.addEventListener('keydown', e => {
        if (!subjectModal.classList.contains('open')) return;

        if (e.key === 'Escape') {
            closeSubjectModal();
            return;
        }

        if (e.key === 'Tab') {
            const focusables = Array.prototype.slice.call(
                subjectModal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])')
            );
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
        }
    });

    // ----- Copy from another term (explicit, one-click, never automatic) -----
    document.getElementById('copyToggle')?.addEventListener('click', () => {
        document.getElementById('copyRow').classList.toggle('open');
    });
    document.getElementById('copyCancel')?.addEventListener('click', () => {
        document.getElementById('copyRow').classList.remove('open');
    });
    document.getElementById('copyBtn')?.addEventListener('click', async () => {
        const source = document.getElementById('copySource').value;
        if (!source) {
            showFeedback('Choose a term to copy from first.', true);
            return;
        }

        const willReplace = ASSIGNED_CODES.length > 0;
        if (!confirm(`Copy ${CONTEXT.year_level}'s subjects from ${source}?`
            + (willReplace ? ` This REPLACES the ${ASSIGNED_CODES.length} subject(s) currently assigned to ${CONTEXT.year_level} for ${CONTEXT.academic_year}.` : ''))) {
            return;
        }

        const btn = document.getElementById('copyBtn');
        btn.disabled = true;

        const result = await postJSON('<?= site_url('chairperson/curriculum-preset/copy') ?>', {
            context: CONTEXT, source_academic_year: source,
        });

        btn.disabled = false;

        if (!result.ok) {
            showFeedback(result.error || 'Could not copy the curriculum.', true);
            return;
        }

        reloadWithMessage(`Copied ${result.copied} subject(s) from ${source}.`);
    });

    function enterEditMode(s) {
        editingCode = s.code;
        subjectModalLastFocused = document.activeElement;
        subjectModal.classList.add('open');
        document.getElementById('formTitle').textContent = 'Edit Subject: ' + s.code;
        document.getElementById('formSub').textContent = 'Changes apply everywhere this subject is used, across every year level.';
        document.getElementById('asCode').value = s.code;
        document.getElementById('asTitle').value = s.title;
        document.getElementById('asUnits').value = parseFloat(s.units) || 3;
        document.getElementById('asDuration').value = String(s.duration);
        document.getElementById('asLab').checked = !!s.is_lab;
        document.getElementById('asAdd').textContent = 'Save Changes';
        document.getElementById('asCode').focus();
    }

    function exitEditMode() {
        editingCode = null;
        document.getElementById('formTitle').textContent = 'Add a New Subject';
        document.getElementById('formSub').textContent = `Creates a subject in ${CONTEXT.program}'s catalog and assigns it to ${CONTEXT.year_level} right away.`;
        document.getElementById('asCode').value = '';
        document.getElementById('asTitle').value = '';
        document.getElementById('asUnits').value = 3;
        document.getElementById('asDuration').value = '1.5';
        document.getElementById('asLab').checked = false;
        document.getElementById('asAdd').textContent = '+ Add';
    }

    document.getElementById('asAdd')?.addEventListener('click', async () => {
        const payload = {
            code: document.getElementById('asCode').value.trim().toUpperCase(),
            title: document.getElementById('asTitle').value.trim(),
            units: parseFloat(document.getElementById('asUnits').value),
            duration: parseFloat(document.getElementById('asDuration').value),
            is_lab: document.getElementById('asLab').checked,
            program: CONTEXT.program,
        };

        if (!payload.code || !payload.title) {
            showFeedback('Subject code and title are required.', true);
            return;
        }

        const editing = editingCode !== null;
        const url = editing
            ? '<?= site_url('chairperson/subjects/update') ?>'
            : '<?= site_url('chairperson/subjects/add') ?>';
        const body = editing ? { ...payload, original_code: editingCode } : payload;

        const btn = document.getElementById('asAdd');
        btn.disabled = true;

        const result = await postJSON(url, body);
        btn.disabled = false;

        if (!result.ok || !result.subject) {
            showFeedback(result.error || 'Could not save the subject.', true);
            return;
        }

        if (editing) {
            reloadWithMessage(`${result.subject.code} updated.`);
            return;
        }

        CATALOG.push(result.subject);
        CATALOG.sort((a, b) => a.code.localeCompare(b.code));
        exitEditMode();
        closeSubjectModal();
        renderCatalogList();

        // New subjects are assigned to THIS year level only, immediately,
        // classified per the "Laboratory subject" checkbox just submitted.
        ASSIGNED_LAB.set(result.subject.code, !!result.subject.is_lab);
        const saved = await saveAssignedCodes([...ASSIGNED_CODES, result.subject.code], `${result.subject.code} added and assigned to ${CONTEXT.year_level}.`);
        if (saved) {
            markUsage(result.subject.code, true);
            renderCatalogList();
        }
    });

    // ----- Full catalog (secondary, collapsed by default) -----
    function renderCatalogList() {
        const body = document.getElementById('catalogList');
        if (!body) return;
        body.innerHTML = '';

        if (CATALOG.length === 0) {
            body.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--color-text-secondary); padding:24px;">No subjects in this program's catalog yet.</td></tr>`;
            return;
        }

        CATALOG.forEach(s => {
            body.appendChild(subjectRow(s, {
                removable: false,
                onEdit: () => enterEditMode(s),
                onRemove: () => deleteFromCatalog(s.code),
                onAssign: IS_ACTIVE_YEAR ? () => assignExistingSubject(s) : null,
            }));
        });
    }

    async function assignExistingSubject(s) {
        ASSIGNED_LAB.set(s.code, s.is_lab);
        const saved = await saveAssignedCodes([...ASSIGNED_CODES, s.code], `${s.code} assigned to ${CONTEXT.year_level}.`);
        if (saved) {
            markUsage(s.code, true);
            renderCatalogList();
        }
    }

    async function deleteFromCatalog(code) {
        const s = subjectByCode(code);
        if (!confirm(`Permanently delete ${code}${s ? ' — ' + s.title : ''} from the ENTIRE catalog? This removes it from every year level that currently uses it, not just ${CONTEXT.year_level}. Blocked while the subject is plotted on any schedule.`)) {
            return;
        }

        const result = await postJSON('<?= site_url('chairperson/subjects/delete') ?>', { code: code });

        if (!result.ok) {
            showFeedback(result.error || 'Could not delete the subject.', true);
            return;
        }

        const index = CATALOG.findIndex(s => s.code === code);
        if (index !== -1) CATALOG.splice(index, 1);
        ASSIGNED_CODES = ASSIGNED_CODES.filter(c => c !== code);
        ASSIGNED_LAB.delete(code);
        delete USAGE_BY_CODE[code];
        renderCatalogList();
        renderSubjectList();
        showFeedback(`${code} permanently deleted from the catalog.`, false);
    }

    document.getElementById('catalogToggle').addEventListener('click', function () {
        this.classList.toggle('open');
        document.getElementById('fullCatalog').classList.toggle('open');
    });

    function reloadWithMessage(message) {
        sessionStorage.setItem('curriculumFeedback', message);
        location.reload();
    }

    (function () {
        const pending = sessionStorage.getItem('curriculumFeedback');
        if (pending) {
            sessionStorage.removeItem('curriculumFeedback');
            showFeedback(pending, false);
        }
    })();

    renderSubjectList();
    renderCatalogList();

    <?php endif ?>
</script>
<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'curriculum-builder';
$pageTitle     = 'Curriculum Builder';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Curriculum Builder</h2>
        <p class="ac-page-header__sub">Build your program's curriculum by year level and semester. Subjects come from the shared subject catalog, owned by any department. Publishing is an Admin step — your changes here stay a draft until reviewed.</p>
    </div>
</div>

<?php if (empty($programs)): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>You're not assigned to any program yet. Contact an administrator.</p>
        </div>
    </div>
<?php else: ?>

    <div id="feedback" class="flash" style="display:none;"></div>

    <div class="panel" style="padding:16px 24px; margin-bottom:16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
        <label style="font-size:12px; font-weight:700;">Program</label>
        <select id="programSelect">
            <?php foreach ($programs as $p): ?>
                <option value="<?= (int) $p['program_id'] ?>" <?= (int) $p['program_id'] === (int) $selectedProgram ? 'selected' : '' ?>><?= esc($p['program_name']) ?> (<?= esc($p['program_code']) ?>)</option>
            <?php endforeach ?>
        </select>

        <?php
            $published = current(array_filter($versions, static fn ($v) => $v['status'] === 'published' && $v['is_current']));
            $archivedCount = count(array_filter($versions, static fn ($v) => $v['status'] === 'archived'));
        ?>
        <span class="mc-cap-pill">
            <?= $published ? 'Published: ' . esc($published['curriculum_code']) . ' v' . (int) $published['version_number'] : 'Not published yet' ?>
        </span>
        <?php if ($archivedCount > 0): ?>
            <span class="mc-cap-pill"><?= $archivedCount ?> archived version<?= $archivedCount === 1 ? '' : 's' ?></span>
        <?php endif ?>
    </div>

    <?php if ($draft === null): ?>
        <div class="panel">
            <div class="empty-state">
                <div class="material-symbols-outlined">edit_note</div>
                <p><?= $published ? 'No draft in progress. Start a new version from the published curriculum to make changes.' : 'This program has no curriculum yet.' ?></p>
                <form method="post" action="<?= site_url('chairperson/curriculum-builder/create-draft') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="program_id" value="<?= (int) $selectedProgram ?>">
                    <button type="submit" class="btn btn-primary"><?= $published ? 'Start New Draft Version' : 'Start Curriculum' ?></button>
                </form>
            </div>
        </div>
    <?php else: ?>

        <div class="panel mc-card">
            <div class="mc-card__head">
                <div class="mc-card__title-row">
                    <h2><?= esc($draft['curriculum_code']) ?> — v<?= (int) $draft['version_number'] ?></h2>
                    <span class="mc-count">Draft</span>
                </div>
                <p>Not visible to students or demand tracking until an admin publishes it. Published curricula are immutable — further changes need a new version.</p>
            </div>

            <div style="padding:16px 24px; display:flex; gap:8px; flex-wrap:wrap; border-bottom:1px solid var(--color-border);">
                <?php foreach ([1, 2, 3, 4] as $yl): ?>
                    <button type="button" class="btn <?= $yl === $yearLevel ? 'btn-primary' : 'btn-secondary' ?> yl-tab" data-year="<?= $yl ?>"><?= $yl ?><?= ['', 'st', 'nd', 'rd', 'th'][$yl] ?? 'th' ?> Year</button>
                <?php endforeach ?>
                <span style="width:1px; background:var(--color-border);"></span>
                <?php foreach ($semesters as $val => $label): ?>
                    <button type="button" class="btn <?= $val === $semester ? 'btn-primary' : 'btn-secondary' ?> sem-tab" data-sem="<?= $val ?>"><?= esc($label) ?></button>
                <?php endforeach ?>
            </div>

            <div style="padding:16px 24px;">
                <?php if (empty($termSubjects)): ?>
                    <div class="empty-state">
                        <div class="material-symbols-outlined">inbox</div>
                        <p>No subjects assigned to this term yet.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table" style="width:100%; border-collapse:collapse; margin-bottom:16px;">
                        <thead>
                            <tr style="text-align:left; font-size:11px; text-transform:uppercase; color:var(--color-text-tertiary); border-bottom:1px solid var(--color-border);">
                                <th style="padding:8px;">Code</th>
                                <th style="padding:8px;">Title</th>
                                <th style="padding:8px;">Units</th>
                                <th style="padding:8px;">Type</th>
                                <th style="padding:8px; text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($termSubjects as $ts): ?>
                                <?php $isLab = $ts['assignment_is_laboratory'] !== null ? (bool) $ts['assignment_is_laboratory'] : (bool) $ts['default_is_laboratory']; ?>
                                <tr data-cs-id="<?= (int) $ts['curriculum_subject_id'] ?>" style="border-bottom:1px solid var(--color-border);">
                                    <td style="padding:8px; font-weight:700;"><?= esc($ts['subject_code']) ?></td>
                                    <td style="padding:8px;"><?= esc($ts['subject_name']) ?></td>
                                    <td style="padding:8px;"><?= (float) $ts['units'] ?></td>
                                    <td style="padding:8px;">
                                        <select class="type-select">
                                            <option value="0" <?= ! $isLab ? 'selected' : '' ?>>Lecture</option>
                                            <option value="1" <?= $isLab ? 'selected' : '' ?>>Laboratory</option>
                                        </select>
                                    </td>
                                    <td style="padding:8px; text-align:right;">
                                        <button type="button" class="ac-btn-outline" data-act="remove">Remove</button>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                <?php endif ?>

                <div style="position:relative; max-width:420px;">
                    <input type="text" id="subjectSearch" placeholder="Search subjects by code or title to add…" autocomplete="off" style="width:100%; padding:8px 10px; border:1px solid var(--color-border); border-radius:6px;">
                    <div id="subjectSearchResults" style="display:none; position:absolute; left:0; right:0; top:100%; z-index:20; background:var(--color-surface, #fff); border:1px solid var(--color-border, #d1d5db); border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,.08); margin-top:4px; max-height:240px; overflow-y:auto;"></div>
                </div>
            </div>
        </div>

    <?php endif ?>

<?php endif ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const DRAFT_ID = <?= $draft ? (int) $draft['curriculum_id'] : 'null' ?>;
    const YEAR_LEVEL = <?= (int) $yearLevel ?>;
    const SEMESTER = '<?= esc($semester, 'js') ?>';
    const PROGRAM_ID = <?= (int) $selectedProgram ?>;

    function showFeedback(message, isError) {
        const el = document.getElementById('feedback');
        if (!el) return;
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

    function gotoTerm(year, sem) {
        const url = new URL(window.location.href);
        url.searchParams.set('program_id', PROGRAM_ID);
        url.searchParams.set('year_level', year);
        url.searchParams.set('semester', sem);
        window.location.href = url.toString();
    }

    document.getElementById('programSelect')?.addEventListener('change', (evt) => {
        const url = new URL(window.location.href);
        url.searchParams.set('program_id', evt.target.value);
        url.searchParams.delete('year_level');
        url.searchParams.delete('semester');
        window.location.href = url.toString();
    });

    document.querySelectorAll('.yl-tab').forEach(btn => btn.addEventListener('click', () => gotoTerm(btn.dataset.year, SEMESTER)));
    document.querySelectorAll('.sem-tab').forEach(btn => btn.addEventListener('click', () => gotoTerm(YEAR_LEVEL, btn.dataset.sem)));

    document.querySelectorAll('.type-select').forEach(select => select.addEventListener('change', async function () {
        const row = this.closest('tr');
        const result = await postJSON('<?= site_url('chairperson/curriculum-builder/subject') ?>/' + row.dataset.csId + '/classification', {
            is_laboratory: this.value === '1',
        });
        if (!result.ok) { showFeedback(result.error || 'Could not update.', true); }
    }));

    document.querySelectorAll('[data-act="remove"]').forEach(btn => btn.addEventListener('click', async function () {
        const row = this.closest('tr');
        const result = await postJSON('<?= site_url('chairperson/curriculum-builder/subject') ?>/' + row.dataset.csId + '/remove', {});
        if (!result.ok) { showFeedback(result.error || 'Could not remove.', true); return; }
        row.remove();
    }));

    const searchInput = document.getElementById('subjectSearch');
    const searchResults = document.getElementById('subjectSearchResults');
    let searchTimer = null;

    function hideSearchResults() {
        if (!searchResults) return;
        searchResults.style.display = 'none';
        searchResults.innerHTML = '';
    }

    searchInput?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const q = searchInput.value.trim();
        if (!q) { hideSearchResults(); return; }
        searchTimer = setTimeout(async () => {
            const res = await fetch('<?= site_url('chairperson/subjects/search') ?>?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => null);
            if (!data || !data.ok || !data.subjects.length) { hideSearchResults(); return; }
            searchResults.innerHTML = '';
            data.subjects.forEach(s => {
                const row = document.createElement('div');
                row.style.cssText = 'padding:8px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid var(--color-border, #eee);';
                row.innerHTML = `<b>${escapeHtml(s.code)}</b> — ${escapeHtml(s.title)} <span style="color:var(--color-text-secondary);">(${escapeHtml(s.units)})</span>`;
                row.addEventListener('mouseenter', () => { row.style.background = 'var(--color-surface-secondary, #f3f4f6)'; });
                row.addEventListener('mouseleave', () => { row.style.background = ''; });
                row.addEventListener('mousedown', evt => evt.preventDefault());
                row.addEventListener('click', async () => {
                    const result = await postJSON('<?= site_url('chairperson/curriculum-builder/add-subject') ?>', {
                        curriculum_id: DRAFT_ID, subject_id: s.subject_id, year_level: YEAR_LEVEL, semester: SEMESTER,
                        is_laboratory: s.is_lab,
                    });
                    if (!result.ok) { showFeedback(result.error || 'Could not add the subject.', true); hideSearchResults(); return; }
                    showFeedback(`${s.code} added to this term.`, false);
                    setTimeout(() => location.reload(), 500);
                });
                searchResults.appendChild(row);
            });
            searchResults.style.display = '';
        }, 250);
    });
    searchInput?.addEventListener('blur', () => setTimeout(hideSearchResults, 150));
</script>
<?= $this->endSection() ?>

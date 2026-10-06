<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/curriculum-builder.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/curriculum-builder.css') ?>">
<style>
    .cb-owner-tag { display: inline-block; margin-left: 8px; padding: 2px 8px; border-radius: 999px; background: #eef2ff; color: #3b4fb8; font-size: 10px; font-weight: 650; vertical-align: middle; }
    .cb-result small.cb-owner-tag { margin-left: 6px; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$yearNames = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'];
$termUnits = array_sum(array_column($termSubjects, 'units'));
?>

<div class="ac-breadcrumb" style="margin-bottom:10px;">
    <a href="<?= site_url('admin/curriculum-subjects') ?>">Curriculum Subjects</a>
    <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
    <span class="current"><?= esc($curriculum['curriculum_code']) ?></span>
</div>

<div id="feedback" class="cb-feedback" role="status" aria-live="polite" hidden></div>

<div class="cb-page" style="max-width:none;">
    <section class="cb-card cb-program" aria-label="Curriculum">
        <div class="cb-program-icon"><span class="material-symbols-outlined" aria-hidden="true">auto_stories</span></div>
        <div class="cb-program-field">
            <label for="curriculumSelect">CURRICULUM</label>
            <select id="curriculumSelect">
                <?php foreach ($curricula as $c): ?>
                    <option value="<?= (int) $c['curriculum_id'] ?>" <?= (int) $c['curriculum_id'] === (int) $curriculum['curriculum_id'] ? 'selected' : '' ?>>
                        <?= esc($c['program_name'] ?? 'Unassigned program') ?> — <?= esc($c['curriculum_code']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="cb-published"><span class="cb-version-code"><?= esc($curriculum['curriculum_name']) ?></span></div>
    </section>

    <section class="cb-card cb-workspace" aria-label="Curriculum subjects editor">
        <header class="cb-draft-header">
            <div><div class="cb-draft-title"><h3>Subjects</h3></div><p class="cb-draft-code">Changes apply immediately — this is the live curriculum.</p></div>
            <span class="cb-save-note" id="saveStatus" role="status"><span class="material-symbols-outlined" aria-hidden="true">cloud_done</span> Changes save automatically</span>
        </header>
        <div class="cb-editor">
            <aside class="cb-years">
                <div class="cb-eyebrow">YEAR LEVEL</div>
                <nav aria-label="Year level">
                    <?php foreach ($yearNames as $yl => $label): ?>
                        <button type="button" class="yl-tab <?= $yl === (int) $yearLevel ? 'is-active' : '' ?>" data-year="<?= $yl ?>" aria-current="<?= $yl === (int) $yearLevel ? 'true' : 'false' ?>">
                            <span class="cb-year-number">0<?= $yl ?></span><span><?= $label ?></span><span class="material-symbols-outlined cb-year-arrow" aria-hidden="true">chevron_right</span>
                        </button>
                    <?php endforeach ?>
                </nav>
                <div class="cb-year-help"><span class="material-symbols-outlined" aria-hidden="true">info</span><p>Choose a year, then a semester to manage its subjects.</p></div>
            </aside>
            <div class="cb-term">
                <nav class="cb-semesters" aria-label="Semester">
                    <?php foreach ($semesters as $val => $label): ?>
                        <button type="button" class="sem-tab <?= (string) $val === (string) $semester ? 'is-active' : '' ?>" data-sem="<?= esc((string) $val) ?>" aria-current="<?= (string) $val === (string) $semester ? 'true' : 'false' ?>"><?= esc($label) ?></button>
                    <?php endforeach ?>
                </nav>
                <div class="cb-term-body">
                    <div class="cb-term-heading">
                        <div><div class="cb-eyebrow"><?= esc($yearNames[$yearLevel]) ?></div><h3><?= esc($semesters[$semester]) ?></h3><p>Manage the subjects for this term.</p></div>
                        <div class="cb-totals"><div><strong id="subjectCount"><?= count($termSubjects) ?></strong><span>Subjects</span></div><div><strong id="unitCount"><?= (float) $termUnits ?></strong><span>Total units</span></div></div>
                    </div>
                    <div class="cb-catalog">
                        <label for="subjectSearch">Add a subject</label>
                        <div class="cb-search-field"><span class="material-symbols-outlined" aria-hidden="true">search</span><input type="search" id="subjectSearch" placeholder="Search the catalog by code or title…" autocomplete="off" aria-describedby="searchHint" aria-controls="subjectSearchResults" aria-expanded="false"><kbd>/</kbd></div>
                        <div id="subjectSearchResults" class="cb-search-results" hidden></div>
                        <p id="searchHint">Every active subject in the catalog is available here — each result shows its owning department.</p>
                    </div>
                    <div class="cb-table-wrap" id="subjectTableWrap" <?= empty($termSubjects) ? 'hidden' : '' ?>>
                        <table class="cb-table">
                            <caption class="cb-sr-only">Subjects assigned to <?= esc($yearNames[$yearLevel] . ', ' . $semesters[$semester]) ?></caption>
                            <thead><tr><th scope="col">Subject</th><th scope="col">Units</th><th scope="col">Class type</th><th scope="col"><span class="cb-sr-only">Actions</span></th></tr></thead>
                            <tbody id="subjectRows">
                                <?php foreach ($termSubjects as $ts): ?>
                                    <?php $isLab = $ts['assignment_is_laboratory'] !== null ? (bool) $ts['assignment_is_laboratory'] : (bool) $ts['default_is_laboratory']; ?>
                                    <tr data-cs-id="<?= (int) $ts['curriculum_subject_id'] ?>" data-units="<?= (float) $ts['units'] ?>">
                                        <td>
                                            <span class="cb-subject-code"><?= esc($ts['subject_code']) ?><?php if ($ts['owner_dept_name']): ?><span class="cb-owner-tag"><?= esc($ts['owner_dept_name']) ?></span><?php endif ?></span>
                                            <span class="cb-subject-name"><?= esc($ts['subject_name']) ?></span>
                                        </td>
                                        <td><span class="cb-unit"><?= (float) $ts['units'] ?></span></td>
                                        <td><select class="type-select" aria-label="Class type for <?= esc($ts['subject_code'], 'attr') ?>" data-saved="<?= $isLab ? '1' : '0' ?>"><option value="0" <?= !$isLab ? 'selected' : '' ?>>Lecture</option><option value="1" <?= $isLab ? 'selected' : '' ?>>Laboratory</option></select></td>
                                        <td><button type="button" class="cb-remove" data-act="remove" aria-label="Remove <?= esc($ts['subject_code'], 'attr') ?>"><span class="material-symbols-outlined" aria-hidden="true">delete</span><span>Remove</span></button></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="cb-empty cb-term-empty" id="termEmpty" <?= !empty($termSubjects) ? 'hidden' : '' ?>>
                        <span class="material-symbols-outlined" aria-hidden="true">library_books</span><h3>Nothing assigned to this term yet</h3><p>Search the catalog above to add the first subject.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(() => {
    const CURRICULUM_ID = <?= (int) $curriculum['curriculum_id'] ?>;
    const YEAR_LEVEL = <?= (int) $yearLevel ?>;
    const SEMESTER = '<?= esc($semester, 'js') ?>';
    const endpoint = '<?= site_url('admin/curriculum-subjects') ?>';
    const searchInput = document.getElementById('subjectSearch');
    const searchResults = document.getElementById('subjectSearchResults');
    let searchTimer, searchController, searchSequence = 0, mutationBusy = false;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    }
    function feedback(message, error = false) {
        const el = document.getElementById('feedback');
        el.hidden = false;
        el.className = 'cb-feedback' + (error ? ' is-error' : '');
        el.textContent = message;
    }
    function saveStatus(text) {
        const el = document.getElementById('saveStatus');
        if (el) el.textContent = text;
    }
    async function post(path, body) {
        const res = await fetch(endpoint + path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data?.ok) throw new Error(data?.error || 'Unable to save. Please try again.');
        return data;
    }
    function navigate(curriculumId, year, semester) {
        const url = new URL(endpoint + '/manage/' + curriculumId, location.origin);
        url.searchParams.set('year_level', year);
        url.searchParams.set('semester', semester);
        location.href = url.toString();
    }
    document.getElementById('curriculumSelect')?.addEventListener('change', evt => navigate(evt.target.value, 1, '1'));
    document.querySelectorAll('.yl-tab').forEach(btn => btn.addEventListener('click', () => navigate(CURRICULUM_ID, btn.dataset.year, SEMESTER)));
    document.querySelectorAll('.sem-tab').forEach(btn => btn.addEventListener('click', () => navigate(CURRICULUM_ID, YEAR_LEVEL, btn.dataset.sem)));
    function setBusy(busy) {
        mutationBusy = busy;
        document.querySelectorAll('.type-select, .cb-remove, .cb-result, .yl-tab, .sem-tab, #curriculumSelect').forEach(el => el.disabled = busy);
        if (searchInput) searchInput.disabled = busy;
    }
    document.querySelectorAll('.type-select').forEach(select => select.addEventListener('change', async () => {
        const row = select.closest('tr');
        setBusy(true); saveStatus('Saving changes…');
        try {
            await post('/subject/' + row.dataset.csId + '/classification', { is_laboratory: select.value === '1' });
            select.dataset.saved = select.value; saveStatus('All changes saved');
        } catch (error) { select.value = select.dataset.saved; feedback(error.message, true); saveStatus('Change not saved'); }
        finally { setBusy(false); }
    }));
    document.querySelectorAll('[data-act="remove"]').forEach(btn => btn.addEventListener('click', async () => {
        const row = btn.closest('tr');
        setBusy(true); saveStatus('Saving changes…');
        try {
            await post('/subject/' + row.dataset.csId + '/remove', {});
            const code = row.querySelector('.cb-subject-code').textContent;
            row.remove();
            const rows = [...document.querySelectorAll('#subjectRows tr')];
            document.getElementById('subjectCount').textContent = rows.length;
            document.getElementById('unitCount').textContent = Number(rows.reduce((sum, item) => sum + Number(item.dataset.units), 0).toFixed(2));
            document.getElementById('subjectTableWrap').hidden = !rows.length;
            document.getElementById('termEmpty').hidden = !!rows.length;
            saveStatus('All changes saved'); feedback(code + ' removed from this term.');
        } catch (error) { feedback(error.message, true); saveStatus('Change not saved'); }
        finally { setBusy(false); }
    }));
    function closeSearch() {
        clearTimeout(searchTimer); searchController?.abort(); searchSequence++;
        if (searchResults) { searchResults.hidden = true; searchResults.replaceChildren(); searchInput.setAttribute('aria-expanded', 'false'); }
    }
    function searchMessage(message) {
        searchResults.replaceChildren();
        const el = document.createElement('p'); el.className = 'cb-search-message'; el.setAttribute('role', 'status'); el.textContent = message;
        searchResults.append(el); searchResults.hidden = false; searchInput.setAttribute('aria-expanded', 'true');
    }
    searchInput?.addEventListener('input', () => {
        closeSearch();
        const q = searchInput.value.trim(); if (!q) return;
        const sequence = searchSequence;
        searchMessage('Searching the catalog…');
        searchTimer = setTimeout(async () => {
            searchController = new AbortController();
            try {
                const res = await fetch('<?= site_url('admin/curriculum-subjects/search') ?>?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: searchController.signal });
                const data = await res.json();
                if (sequence !== searchSequence) return;
                if (!res.ok || !data.ok) throw new Error('Search is unavailable. Please try again.');
                if (!data.subjects.length) { searchMessage('No subjects found. Try another code or title.'); return; }
                searchResults.replaceChildren();
                data.subjects.forEach(s => {
                    const button = document.createElement('button'); button.type = 'button'; button.className = 'cb-result';
                    const ownerTag = s.owner_dept_name ? `<span class="cb-owner-tag">${escapeHtml(s.owner_dept_name)}</span>` : '';
                    button.innerHTML = `<span><strong>${escapeHtml(s.code)}${ownerTag}</strong><span>${escapeHtml(s.title)}</span></span><small>${escapeHtml(s.units)} units</small><span class="material-symbols-outlined" aria-hidden="true">add</span>`;
                    button.addEventListener('click', async () => {
                        if (mutationBusy) return;
                        setBusy(true); saveStatus('Adding subject…');
                        try {
                            await post('/add', { curriculum_id: CURRICULUM_ID, subject_id: s.subject_id, year_level: YEAR_LEVEL, semester: SEMESTER, is_laboratory: s.is_lab });
                            feedback(s.code + ' added to this term.'); location.reload();
                        } catch (error) { feedback(error.message, true); saveStatus('Subject not added'); setBusy(false); }
                    });
                    searchResults.append(button);
                });
            } catch (error) { if (error.name !== 'AbortError' && sequence === searchSequence) searchMessage('Search is unavailable. Please try again.'); }
        }, 250);
    });
    document.addEventListener('click', evt => { if (!evt.target.closest('.cb-catalog')) closeSearch(); });
    document.querySelector('.cb-catalog')?.addEventListener('focusout', evt => { if (!evt.currentTarget.contains(evt.relatedTarget)) closeSearch(); });
    document.addEventListener('keydown', evt => {
        if (evt.key === '/' && !evt.ctrlKey && !evt.metaKey && !evt.altKey && !evt.target.closest('input, select, textarea, [contenteditable="true"]') && searchInput) { evt.preventDefault(); searchInput.focus(); }
        if (evt.key === 'Escape' && searchResults && !searchResults.hidden) { closeSearch(); searchInput.focus(); }
        if (evt.key === 'ArrowDown' && evt.target === searchInput) { const first = searchResults.querySelector('button'); if (first) { evt.preventDefault(); first.focus(); } }
        if (evt.target.matches('.cb-result') && ['ArrowDown', 'ArrowUp'].includes(evt.key)) { evt.preventDefault(); const next = evt.key === 'ArrowDown' ? evt.target.nextElementSibling : evt.target.previousElementSibling; (next || searchInput).focus(); }
    });
})();
</script>
<?= $this->endSection() ?>

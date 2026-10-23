<?= $this->extend('layouts/admin_console') ?>
<?php
$activeAdmin = 'curriculum';
$pageTitle = 'Curriculum Builder';
$brandSub = 'Chairperson Portal';
$userName = $user['name'];
$userRoleLabel = $user['role'];
$navGroups = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
$yearNames = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'];
$termUnits = array_sum(array_column($termSubjects, 'units'));
?>
<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pages/curriculum-builder.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/curriculum-builder.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="cb-page">
    <header class="cb-heading">
        <div><div class="cb-eyebrow">ACADEMIC PLANNING</div><h2>Curriculum Builder</h2><p>Manage curriculum subjects by year and semester.</p></div>
        <span class="cb-heading-note"><span class="material-symbols-outlined" aria-hidden="true">auto_stories</span> Program workspace</span>
    </header>
    <div id="feedback" class="cb-feedback" role="status" aria-live="polite" hidden></div>
    <?php if (empty($programs)): ?>
        <div class="cb-card cb-empty"><span class="material-symbols-outlined" aria-hidden="true">domain_disabled</span><h3>No program assigned</h3><p>Contact an administrator to assign a program to your account.</p></div>
    <?php else: ?>
        <section class="cb-card cb-program" aria-label="Program">
            <div class="cb-program-icon"><span class="material-symbols-outlined" aria-hidden="true">school</span></div>
            <div class="cb-program-field"><label for="programSelect">PROGRAM</label><select id="programSelect"><?php foreach ($programs as $p): ?><option value="<?= (int) $p['program_id'] ?>" <?= (int) $p['program_id'] === (int) $selectedProgram ? 'selected' : '' ?>><?= esc($p['program_name']) ?> (<?= esc($p['program_code']) ?>)</option><?php endforeach ?></select></div>
            <div class="cb-published"><span class="cb-version-code"><?= esc($curriculum['curriculum_code']) ?></span></div>
        </section>
            <section class="cb-card cb-workspace" aria-label="Curriculum editor">
                <header class="cb-draft-header"><div><div class="cb-draft-title"><h3>Curriculum</h3></div><p class="cb-draft-code"><?= esc($curriculum['curriculum_name']) ?></p></div><span class="cb-save-note" id="saveStatus" role="status"><span class="material-symbols-outlined" aria-hidden="true">cloud_done</span> Changes save automatically</span></header>
                <div class="cb-editor">
                    <aside class="cb-years"><div class="cb-eyebrow">YEAR LEVEL</div><nav aria-label="Year level"><?php foreach ($yearNames as $yl => $label): ?><button type="button" class="yl-tab <?= $yl === (int) $yearLevel ? 'is-active' : '' ?>" data-year="<?= $yl ?>" aria-current="<?= $yl === (int) $yearLevel ? 'true' : 'false' ?>"><span class="cb-year-number">0<?= $yl ?></span><span><?= $label ?></span><span class="material-symbols-outlined cb-year-arrow" aria-hidden="true">chevron_right</span></button><?php endforeach ?></nav><div class="cb-year-help"><span class="material-symbols-outlined" aria-hidden="true">info</span><p>Choose a year, then a semester to manage its subjects.</p></div></aside>
                    <div class="cb-term">
                        <nav class="cb-semesters" aria-label="Semester"><?php foreach ($semesters as $val => $label): ?><button type="button" class="sem-tab <?= (string) $val === (string) $semester ? 'is-active' : '' ?>" data-sem="<?= esc((string) $val) ?>" aria-current="<?= (string) $val === (string) $semester ? 'true' : 'false' ?>"><?= esc($label) ?></button><?php endforeach ?></nav>
                        <div class="cb-term-body"><div class="cb-term-heading"><div><div class="cb-eyebrow"><?= esc($yearNames[$yearLevel]) ?></div><h3><?= esc($semesters[$semester]) ?></h3><p>Manage the subjects for this term.</p></div><div class="cb-totals"><div><strong id="subjectCount"><?= count($termSubjects) ?></strong><span>Subjects</span></div><div><strong id="unitCount"><?= (float) $termUnits ?></strong><span>Total units</span></div></div></div>
                        <div class="cb-catalog"><label for="subjectSearch">Add a subject</label><div class="cb-search-field"><span class="material-symbols-outlined" aria-hidden="true">search</span><input type="search" id="subjectSearch" placeholder="Search the catalog by code or title…" autocomplete="off" aria-describedby="searchHint" aria-controls="subjectSearchResults" aria-expanded="false"><kbd>/</kbd></div><div id="subjectSearchResults" class="cb-search-results" hidden></div><p id="searchHint">Subjects from all departments are available in the shared catalog.</p></div>
                        <div class="ac-dtable cb-dtable" id="subjectTableWrap" <?= empty($termSubjects) ? 'hidden' : '' ?>>
                            <div class="ac-dtable__head">
                                <div>Subject</div>
                                <div>Units</div>
                                <div>Class type</div>
                                <div class="cb-sr-only">Actions</div>
                            </div>
                            <div id="subjectRows">
                                <?php foreach ($termSubjects as $ts): ?>
                                    <?php $isLab = $ts['assignment_is_laboratory'] !== null ? (bool) $ts['assignment_is_laboratory'] : (bool) $ts['default_is_laboratory']; ?>
                                    <div class="ac-dtable__row" data-cs-id="<?= (int) $ts['curriculum_subject_id'] ?>" data-units="<?= (float) $ts['units'] ?>">
                                        <div data-label="Subject"><span class="cb-subject-code"><?= esc($ts['subject_code']) ?></span><span class="cb-subject-name"><?= esc($ts['subject_name']) ?></span></div>
                                        <div data-label="Units"><span class="cb-unit" data-unit-word="<?= (float) $ts['units'] === 1.0 ? 'unit' : 'units' ?>"><?= (float) $ts['units'] ?></span></div>
                                        <div data-label="Class type"><select class="type-select" aria-label="Class type for <?= esc($ts['subject_code'], 'attr') ?>" data-saved="<?= $isLab ? '1' : '0' ?>"><option value="0" <?= !$isLab ? 'selected' : '' ?>>Lecture</option><option value="1" <?= $isLab ? 'selected' : '' ?>>Laboratory</option></select></div>
                                        <div data-label="Actions"><button type="button" class="cb-remove" data-act="remove" aria-label="Remove <?= esc($ts['subject_code'], 'attr') ?>"><span class="material-symbols-outlined" aria-hidden="true">delete</span><span>Remove</span></button></div>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        </div>
                        <div class="cb-empty cb-term-empty" id="termEmpty" <?= !empty($termSubjects) ? 'hidden' : '' ?>><span class="material-symbols-outlined" aria-hidden="true">library_books</span><h3>No subjects added yet</h3><p>Search the catalog above to add subjects.</p></div>
                        </div>
                    </div>
                </div>
            </section>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(() => {
    const CURRICULUM_ID = <?= $curriculum ? (int) $curriculum['curriculum_id'] : 'null' ?>;
    let YEAR_LEVEL = <?= (int) $yearLevel ?>;
    let SEMESTER = '<?= esc($semester, 'js') ?>';
    const PROGRAM_ID = <?= (int) $selectedProgram ?>;
    const YEAR_NAMES = <?= json_encode($yearNames) ?>;
    const endpoint = '<?= site_url('chairperson/curriculum-builder') ?>';
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
    // Switching year level / semester used to be a full page reload (the
    // whole console chrome — sidebar, topbar, notifications poll — repainting
    // along with it, ~600-800ms per click). It's the same curriculum editor
    // either way, so only the term workspace actually needs to change: fetch
    // just that term's subjects and patch the DOM in place instead.
    const termBody = document.querySelector('.cb-term-body');
    let navSeq = 0;
    // Same sliding "active" pill every other tab/toggle group in the console
    // uses (acInitSegmented, defined once in the layout) — the year-level
    // list and semester bar used to just snap their highlight to the new
    // button; this animates it sliding there instead.
    let ylIndicator = null, semIndicator = null;
    if (window.acInitSegmented) {
        ylIndicator = window.acInitSegmented(document.querySelector('.cb-years nav'), { buttonSelector: '.yl-tab' });
        semIndicator = window.acInitSegmented(document.querySelector('.cb-semesters'), { buttonSelector: '.sem-tab' });
        // Its own auto-detect looks for .active/aria-pressed, which these
        // tabs don't use (.is-active / aria-current) — position it on the
        // server-rendered active tab directly instead.
        ylIndicator?.move(document.querySelector('.yl-tab.is-active'), false);
        semIndicator?.move(document.querySelector('.sem-tab.is-active'), false);
    }
    function skeletonRows(count) {
        return Array.from({ length: count }, () => (
            '<div class="cb-skeleton-row">'
            + '<span class="cb-skeleton-bar cb-skeleton-bar--code"></span>'
            + '<span class="cb-skeleton-bar cb-skeleton-bar--title"></span>'
            + '<span class="cb-skeleton-bar cb-skeleton-bar--pill"></span>'
            + '<span class="cb-skeleton-bar cb-skeleton-bar--select"></span>'
            + '</div>'
        )).join('');
    }
    function rowHtml(s) {
        const isLab = !!s.is_laboratory;
        return `<div class="ac-dtable__row" data-cs-id="${s.curriculum_subject_id}" data-units="${s.units}">`
            + `<div data-label="Subject"><span class="cb-subject-code">${escapeHtml(s.subject_code)}</span><span class="cb-subject-name">${escapeHtml(s.subject_name)}</span></div>`
            + `<div data-label="Units"><span class="cb-unit" data-unit-word="${s.units === 1 ? 'unit' : 'units'}">${s.units}</span></div>`
            + `<div data-label="Class type"><select class="type-select" aria-label="Class type for ${escapeHtml(s.subject_code)}" data-saved="${isLab ? '1' : '0'}"><option value="0" ${!isLab ? 'selected' : ''}>Lecture</option><option value="1" ${isLab ? 'selected' : ''}>Laboratory</option></select></div>`
            + `<div data-label="Actions"><button type="button" class="cb-remove" data-act="remove" aria-label="Remove ${escapeHtml(s.subject_code)}"><span class="material-symbols-outlined" aria-hidden="true">delete</span><span>Remove</span></button></div>`
            + `</div>`;
    }
    function renderTerm(data) {
        YEAR_LEVEL = data.year_level; SEMESTER = String(data.semester);
        document.querySelectorAll('.yl-tab').forEach(btn => {
            const active = Number(btn.dataset.year) === YEAR_LEVEL;
            btn.classList.toggle('is-active', active); btn.setAttribute('aria-current', String(active));
            if (active) ylIndicator?.move(btn, true);
        });
        document.querySelectorAll('.sem-tab').forEach(btn => {
            const active = btn.dataset.sem === SEMESTER;
            btn.classList.toggle('is-active', active); btn.setAttribute('aria-current', String(active));
            if (active) semIndicator?.move(btn, true);
        });
        document.querySelector('.cb-term-heading .cb-eyebrow').textContent = YEAR_NAMES[YEAR_LEVEL];
        document.querySelector('.cb-term-heading h3').textContent = data.semester_label;
        const rows = document.getElementById('subjectRows');
        rows.innerHTML = data.subjects.map(rowHtml).join('');
        document.getElementById('subjectCount').textContent = data.subjects.length;
        document.getElementById('unitCount').textContent = Number(data.subjects.reduce((sum, s) => sum + Number(s.units), 0).toFixed(2));
        document.getElementById('subjectTableWrap').hidden = !data.subjects.length;
        document.getElementById('termEmpty').hidden = !!data.subjects.length;
        if (window.enhanceSelect) rows.querySelectorAll('.type-select').forEach(select => window.enhanceSelect(select));
        // Settles in the same spring-fade every other freshly-loaded panel in
        // the console uses, instead of just popping into place.
        termBody.classList.remove('animate-enter'); void termBody.offsetWidth; termBody.classList.add('animate-enter');
    }
    async function loadTerm(year, semester, pushState) {
        year = Number(year); semester = String(semester);
        const seq = ++navSeq;
        document.querySelectorAll('.yl-tab').forEach(btn => {
            const active = Number(btn.dataset.year) === year;
            btn.classList.toggle('is-active', active);
            if (active) ylIndicator?.move(btn, true);
        });
        document.querySelectorAll('.sem-tab').forEach(btn => {
            const active = btn.dataset.sem === semester;
            btn.classList.toggle('is-active', active);
            if (active) semIndicator?.move(btn, true);
        });
        setBusy(true);
        // Shimmering placeholder rows (same pattern as the cashier's
        // installment-plan preview) instead of just dimming the old
        // content — reads as "fetching the new term" rather than "frozen".
        document.getElementById('subjectRows').innerHTML = skeletonRows(3);
        document.getElementById('subjectTableWrap').hidden = false;
        document.getElementById('termEmpty').hidden = true;
        if (pushState) {
            const pageUrl = new URL(location.href);
            pageUrl.searchParams.set('program_id', PROGRAM_ID);
            pageUrl.searchParams.set('year_level', year);
            pageUrl.searchParams.set('semester', semester);
            history.pushState({ year, semester }, '', pageUrl);
        }
        try {
            const url = new URL(endpoint + '/term');
            url.searchParams.set('program_id', PROGRAM_ID);
            url.searchParams.set('year_level', year);
            url.searchParams.set('semester', semester);
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => null);
            if (seq !== navSeq) return;
            if (!res.ok || !data?.ok) throw new Error(data?.error || 'Unable to load this term.');
            renderTerm(data);
        } catch (error) {
            if (seq === navSeq) feedback(error.message, true);
        } finally {
            if (seq === navSeq) setBusy(false);
        }
    }
    function navigate(year, semester) { loadTerm(year, semester, true); }
    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(location.search);
        loadTerm(params.get('year_level') || 1, params.get('semester') || '1', false);
    });
    document.getElementById('programSelect')?.addEventListener('change', evt => {
        const url = new URL(location.href);
        url.searchParams.set('program_id', evt.target.value);
        url.searchParams.delete('year_level'); url.searchParams.delete('semester');
        location.href = url.toString();
    });
    document.querySelectorAll('.yl-tab').forEach(btn => btn.addEventListener('click', () => navigate(btn.dataset.year, SEMESTER)));
    document.querySelectorAll('.sem-tab').forEach(btn => btn.addEventListener('click', () => navigate(YEAR_LEVEL, btn.dataset.sem)));
    function setBusy(busy) {
        mutationBusy = busy;
        document.querySelectorAll('.type-select, .cb-remove, .cb-result, .yl-tab, .sem-tab, #programSelect').forEach(el => el.disabled = busy);
        if (searchInput) searchInput.disabled = busy;
    }
    // Delegated on the stable #subjectRows container, not the rows
    // themselves — renderTerm() replaces those wholesale on every
    // year/semester switch, so a listener bound to one specific row
    // wouldn't survive it.
    document.getElementById('subjectRows').addEventListener('change', async evt => {
        const select = evt.target.closest('.type-select');
        if (!select) return;
        const row = select.closest('.ac-dtable__row');
        setBusy(true); saveStatus('Saving changes…');
        try {
            await post('/subject/' + row.dataset.csId + '/classification', { is_laboratory: select.value === '1' });
            select.dataset.saved = select.value; saveStatus('All changes saved');
        } catch (error) { select.value = select.dataset.saved; feedback(error.message, true); saveStatus('Change not saved'); }
        finally { setBusy(false); }
    });
    document.getElementById('subjectRows').addEventListener('click', async evt => {
        const btn = evt.target.closest('[data-act="remove"]');
        if (!btn) return;
        const row = btn.closest('.ac-dtable__row');
        setBusy(true); saveStatus('Saving changes…');
        try {
            await post('/subject/' + row.dataset.csId + '/remove', {});
            const code = row.querySelector('.cb-subject-code').textContent;
            row.remove();
            const rows = [...document.querySelectorAll('#subjectRows .ac-dtable__row')];
            document.getElementById('subjectCount').textContent = rows.length;
            document.getElementById('unitCount').textContent = Number(rows.reduce((sum, item) => sum + Number(item.dataset.units), 0).toFixed(2));
            document.getElementById('subjectTableWrap').hidden = !rows.length;
            document.getElementById('termEmpty').hidden = !!rows.length;
            saveStatus('All changes saved'); feedback(code + ' removed from this term.');
        } catch (error) { feedback(error.message, true); saveStatus('Change not saved'); }
        finally { setBusy(false); }
    });
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
                const res = await fetch('<?= site_url('chairperson/subjects/search') ?>?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: searchController.signal });
                const data = await res.json();
                if (sequence !== searchSequence) return;
                if (!res.ok || !data.ok) throw new Error('Search is unavailable. Please try again.');
                if (!data.subjects.length) { searchMessage('No subjects found. Try another code or title.'); return; }
                searchResults.replaceChildren();
                // Merge a lecture+lab pair into one result, so adding a subject that
                // has both components is a single click instead of two separate finds.
                const byId = new Map(data.subjects.map(s => [s.subject_id, s]));
                const seen = new Set();
                const merged = [];
                data.subjects.forEach(s => {
                    if (seen.has(s.subject_id)) return;
                    const partner = s.pair_id ? byId.get(s.pair_id) : null;
                    seen.add(s.subject_id);
                    if (partner) seen.add(partner.subject_id);
                    const lecture = s.is_lab ? (partner || null) : s;
                    const lab = s.is_lab ? s : (partner || null);
                    merged.push({ primary: lecture || lab, lecture, lab });
                });
                merged.forEach(m => {
                    const mixed = m.lecture && m.lab;
                    const parts = [m.lecture, m.lab].filter(Boolean);
                    const totalUnits = parts.reduce((sum, p) => sum + parseFloat(p.units), 0);
                    const typeLabel = mixed ? 'Lecture + Lab' : (m.lab ? 'Laboratory' : 'Lecture');
                    const button = document.createElement('button'); button.type = 'button'; button.className = 'cb-result';
                    button.innerHTML = `<span><strong>${escapeHtml(m.primary.code)}</strong><span>${escapeHtml(m.primary.title)} · ${escapeHtml(typeLabel)}</span></span><small>${totalUnits} units</small><span class="material-symbols-outlined" aria-hidden="true">add</span>`;
                    button.addEventListener('click', async () => {
                        if (mutationBusy) return;
                        setBusy(true); saveStatus('Adding subject…');
                        try {
                            const results = await Promise.allSettled(parts.map(p =>
                                post('/add-subject', { curriculum_id: CURRICULUM_ID, subject_id: p.subject_id, year_level: YEAR_LEVEL, semester: SEMESTER, is_laboratory: p.is_lab })
                            ));
                            const failed = results.filter(r => r.status === 'rejected');
                            if (failed.length === results.length) throw new Error(failed[0].reason?.message || 'Unable to add this subject.');
                            feedback(m.primary.code + (mixed ? ' (lecture + lab)' : '') + ' added to this term.' + (failed.length ? ' One component was already assigned.' : ''));
                            closeSearch(); searchInput.value = '';
                            await loadTerm(YEAR_LEVEL, SEMESTER, false);
                            saveStatus('All changes saved');
                        } catch (error) { feedback(error.message, true); saveStatus('Subject not added'); }
                        finally { setBusy(false); }
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
    // Same animated floating-listbox select every other portal page uses
    // (window.enhanceSelect, loaded globally in the layout) instead of the
    // browser's own unstyled popup — wraps the native <select> in place, so
    // the change listeners above keep working untouched. Rows only ever
    // disappear (remove) or the whole page reloads (add subject), never grow
    // in place, so a one-time pass at load is all this needs.
    if (window.enhanceSelect) {
        window.enhanceSelect(document.getElementById('programSelect'));
        document.querySelectorAll('.type-select').forEach(select => window.enhanceSelect(select));
    }
})();
</script>
<?= $this->endSection() ?>

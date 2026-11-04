(() => {
    'use strict';

    // Native <details> has no open/close transition — it just snaps. This
    // animates the element's own height between its closed (summary-only)
    // and natural open height via the Web Animations API, which — unlike a
    // CSS height:auto transition — works directly off measured pixel
    // values, so it's reliable cross-browser without extra markup.
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function animateDetails(el) {
        if (!el || el.dataset.animatedToggle) return;
        el.dataset.animatedToggle = '1';

        const summary = el.querySelector(':scope > summary');
        if (!summary) return;

        // Captured once, while still closed (the markup's default state) —
        // reused for every open/close pass instead of re-measured, since
        // re-measuring mid-animation would read the in-progress height.
        const closedHeight = el.open ? null : el.offsetHeight + 'px';
        let running = null;

        function animateTo(fromHeight, toHeight, onDone) {
            if (running) running.cancel();
            if (reduceMotion) { onDone(); return; }
            el.style.overflow = 'hidden';
            running = el.animate({ height: [fromHeight, toHeight] }, { duration: 220, easing: 'ease-out' });
            running.onfinish = () => { running = null; el.style.height = el.style.overflow = ''; onDone(); };
            running.oncancel = () => { running = null; };
        }

        function expand() {
            const from = closedHeight ?? summary.offsetHeight + 'px';
            el.style.height = from;
            el.open = true;
            requestAnimationFrame(() => animateTo(from, el.scrollHeight + 'px', () => {}));
        }

        function shrink() {
            const to = closedHeight ?? summary.offsetHeight + 'px';
            animateTo(el.offsetHeight + 'px', to, () => { el.open = false; });
        }

        summary.addEventListener('click', e => {
            e.preventDefault();
            if (el.open) shrink(); else expand();
        });

        el.__scExpand = expand;
    }

    document.querySelectorAll('.sc-page details.panel').forEach(animateDetails);

    // Department -> Program cascade on the create form. Only present when a
    // chairperson manages more than one department (see sections.php); a
    // single-department chair just gets the plain Program select as before.
    const newDept = document.getElementById('newDepartment');
    const newProgram = document.getElementById('newProgram');
    let syncCreatePrograms = () => {};
    if (newDept) {
        const deptPrograms = JSON.parse(newDept.dataset.deptPrograms || '[]');
        const byDept = new Map(deptPrograms.map(d => [d.name, d.programs]));
        syncCreatePrograms = () => {
            const options = byDept.get(newDept.value) || [];
            newProgram.replaceChildren(...options.map(p => {
                const opt = document.createElement('option');
                opt.value = p;
                opt.textContent = p;
                return opt;
            }));
        };
        newDept.addEventListener('change', syncCreatePrograms);
        syncCreatePrograms();
    }

    // Single department control, in the page header — picking one there
    // also scopes the (otherwise independent) Create a section form to it,
    // so a chairperson managing several departments doesn't have to repeat
    // the choice.
    const headerDept = document.getElementById('scHeaderDept');
    headerDept?.addEventListener('change', () => {
        if (newDept && headerDept.value !== 'all' && [...newDept.options].some(o => o.value === headerDept.value)) {
            newDept.value = headerDept.value;
            syncCreatePrograms();
        }
    });

    const create = document.getElementById('scCreatePanel');
    const trigger = document.getElementById('scOpenCreate');
    trigger?.addEventListener('click', () => {
        if (create.open) {
            create.scrollIntoView({ behavior: 'smooth', block: 'start' });
            document.getElementById('newSectionName').focus({ preventScroll: true });
            return;
        }
        create.__scExpand?.();
        create.scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.getElementById('newSectionName').focus({ preventScroll: true });
    });
    const tools = document.getElementById('scDirectoryTools');
    if (!tools) return;
    const cards = [...document.querySelectorAll('[data-directory-section]')];
    const search = document.getElementById('scSearch');
    const term = document.getElementById('scTerm');
    const dept = headerDept;
    const program = document.getElementById('scProgram');
    const normalize = value => value.toLocaleLowerCase().replace(/\s+/g, ' ').trim();
    // Student names are searchable; assign-menu options are excluded.
    const content = new Map(cards.map(card => [card, normalize(card.querySelector('.sc-section-summary').textContent + ' ' + [...card.querySelectorAll('.roster-student')].map(row => row.textContent).join(' '))]));

    // Narrows the Program filter's options to the chosen department's
    // programs, same cascade as the create form — derived from the cards
    // actually on the page rather than re-embedding deptPrograms twice.
    const programsByDept = new Map();
    cards.forEach(card => {
        const d = card.dataset.department;
        if (!d) return;
        if (!programsByDept.has(d)) programsByDept.set(d, new Set());
        programsByDept.get(d).add(card.dataset.program);
    });

    function syncProgramOptions() {
        if (!dept) return;
        const programs = dept.value === 'all'
            ? [...new Set(cards.map(c => c.dataset.program))]
            : [...(programsByDept.get(dept.value) || [])];
        const current = program.value;
        program.replaceChildren(Object.assign(document.createElement('option'), { value: 'all', textContent: 'All programs' }));
        programs.sort().forEach(p => program.appendChild(Object.assign(document.createElement('option'), { value: p, textContent: p })));
        program.value = programs.includes(current) ? current : 'all';
    }

    function filter() {
        const query = normalize(search.value);
        cards.forEach(card => {
            card.hidden = (term.value !== 'all' && card.dataset.term !== term.value)
                || (dept && dept.value !== 'all' && card.dataset.department !== dept.value)
                || (program.value !== 'all' && card.dataset.program !== program.value)
                || !content.get(card).includes(query);
        });
        const count = cards.filter(card => !card.hidden).length;
        document.getElementById('scResults').textContent = `Showing ${count} of ${cards.length} sections`;
        document.getElementById('scNoResults').hidden = count > 0;
    }
    search.addEventListener('input', filter);
    term.addEventListener('change', filter);
    dept?.addEventListener('change', () => { syncProgramOptions(); filter(); });
    program.addEventListener('change', filter);
    document.getElementById('scClearFilters').addEventListener('click', () => {
        search.value = ''; term.value = 'all'; if (dept) dept.value = 'all'; syncProgramOptions(); program.value = 'all'; filter(); search.focus();
    });
    tools.hidden = false;
    document.getElementById('scResults').hidden = false;
    filter();
})();

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
    const program = document.getElementById('scProgram');
    const normalize = value => value.toLocaleLowerCase().replace(/\s+/g, ' ').trim();
    // Student names are searchable; assign-menu options are excluded.
    const content = new Map(cards.map(card => [card, normalize(card.querySelector('.sc-section-summary').textContent + ' ' + [...card.querySelectorAll('.roster-student')].map(row => row.textContent).join(' '))]));
    function filter() {
        const query = normalize(search.value);
        cards.forEach(card => {
            card.hidden = (term.value !== 'all' && card.dataset.term !== term.value) || (program.value !== 'all' && card.dataset.program !== program.value) || !content.get(card).includes(query);
        });
        const count = cards.filter(card => !card.hidden).length;
        document.getElementById('scResults').textContent = `Showing ${count} of ${cards.length} sections`;
        document.getElementById('scNoResults').hidden = count > 0;
    }
    search.addEventListener('input', filter);
    term.addEventListener('change', filter);
    program.addEventListener('change', filter);
    document.getElementById('scClearFilters').addEventListener('click', () => {
        search.value = ''; term.value = 'all'; program.value = 'all'; filter(); search.focus();
    });
    tools.hidden = false;
    document.getElementById('scResults').hidden = false;
    filter();
})();

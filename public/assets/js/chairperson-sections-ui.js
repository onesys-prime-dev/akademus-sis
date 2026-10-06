(() => {
    'use strict';
    const create = document.getElementById('scCreatePanel');
    const trigger = document.getElementById('scOpenCreate');
    trigger?.addEventListener('click', () => {
        create.open = true;
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

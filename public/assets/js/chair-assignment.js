/**
 * Chairperson assignment modal.
 *
 * Progressive enhancement for #modalChair: the server renders a plain
 * <select> of eligible staff (faculty + chairpersons only); this module
 * hides it behind a searchable, role-filtered picker with avatars, badges
 * and keyboard support while keeping the select as the real value holder.
 * Without JS the select still works.
 */
(function () {
    'use strict';

    var modal = document.getElementById('modalChair');
    if (!modal) return;

    var form        = document.getElementById('formChair');
    var select      = document.getElementById('chairExistingSelect');
    var picker      = document.getElementById('chairPicker');
    var existingBox = document.getElementById('chairExistingField');
    var newBox      = document.getElementById('chairNewFields');
    var modeInput   = document.getElementById('chairModeValue');
    var modeSwitch  = document.getElementById('chairModeSwitch');
    var modeBtns    = modal.querySelectorAll('[data-chair-mode]');
    var filterBtns  = modal.querySelectorAll('[data-chair-filter]');
    var search      = document.getElementById('chairUserSearch');
    var list        = document.getElementById('chairUserList');
    var empty       = document.getElementById('chairUserEmpty');
    var emptyText   = document.getElementById('chairUserEmptyText');
    var emptyReset  = document.getElementById('chairUserEmptyReset');
    var summary     = document.getElementById('chairSelectedSummary');
    var submitBtn   = document.getElementById('chairSubmitBtn');
    var submitLabel = document.getElementById('chairSubmitLabel');
    var nameInput   = document.getElementById('chairNewName');
    var emailInput  = document.getElementById('chairNewEmail');

    if (!form || !select || !picker || !list || !search) return;

    var candidates = Array.prototype.map.call(select.options, function (opt) {
        if (!opt.value) return null;

        return {
            id: opt.value,
            name: opt.dataset.name || opt.textContent.trim(),
            email: opt.dataset.email || '',
            roles: (opt.dataset.roles || '').split(' ').filter(Boolean),
            chairOf: opt.dataset.chairOf || '',
            current: opt.dataset.current === '1',
            tone: Math.min(6, Math.max(1, parseInt(opt.dataset.tone || '1', 10))),
        };
    }).filter(Boolean);

    function isChair(c) { return c.roles.indexOf('chairperson') !== -1; }
    function isFaculty(c) { return c.roles.indexOf('faculty') !== -1; }

    // Reassignments are the common case, so OTHER chairpersons open by
    // default. When the only chair is the current one (nothing selectable),
    // or this is a first appointment, start on Faculty instead of showing
    // a list with one disabled row.
    var selectableChairs = candidates.filter(function (c) { return isChair(c) && !c.current; });
    var filter = selectableChairs.length
        ? 'chairperson'
        : (candidates.some(isFaculty) ? 'faculty' : 'all');
    var query = '';
    var segmented = null;

    // A previously submitted selection must stay visible even if the default
    // filter wouldn't include it (e.g. it was a faculty member).
    if (select.value) filter = 'all';

    function matchesQuery(c) {
        if (query === '') return true;

        return (c.name + ' ' + c.email + ' ' + c.chairOf).toLowerCase().indexOf(query) !== -1;
    }

    function visibleCandidates() {
        return candidates.filter(function (c) {
            if (filter === 'chairperson' && !isChair(c)) return false;
            if (filter === 'faculty' && !isFaculty(c)) return false;

            return matchesQuery(c);
        });
    }

    function selectedCandidate() {
        if (!select.value) return null;

        return candidates.filter(function (c) { return c.id === select.value; })[0] || null;
    }

    function initials(name) {
        var parts = String(name).trim().split(/\s+/);

        return parts.length === 1
            ? parts[0].slice(0, 2).toUpperCase()
            : (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }

    function node(tag, className, text) {
        var el = document.createElement(tag);
        if (className) el.className = className;
        if (text !== undefined) el.textContent = text;

        return el;
    }

    function buildRow(c) {
        var row = node('button', 'chair-user');
        row.type = 'button';
        row.setAttribute('role', 'option');
        row.dataset.userId = c.id;
        row.setAttribute('aria-selected', select.value === c.id ? 'true' : 'false');

        if (select.value === c.id) row.classList.add('is-selected');

        if (c.current) {
            row.classList.add('is-current');
            row.setAttribute('aria-disabled', 'true');
            row.tabIndex = -1;
        }

        var avatar = node('span', 'chair-avatar chair-avatar--' + c.tone, initials(c.name));
        avatar.setAttribute('aria-hidden', 'true');

        var body = node('span', 'chair-user__body');
        var top  = node('span', 'chair-user__top');
        top.appendChild(node('span', 'chair-user__name', c.name));

        if (isChair(c)) top.appendChild(node('span', 'chair-tag chair-tag--chair', 'Chairperson'));
        if (isFaculty(c)) top.appendChild(node('span', 'chair-tag chair-tag--faculty', 'Faculty'));
        if (c.current) top.appendChild(node('span', 'chair-tag chair-tag--current', 'Current chair'));

        body.appendChild(top);

        if (c.email) body.appendChild(node('span', 'chair-user__email', c.email));

        if (c.chairOf) {
            var meta = node('span', 'chair-user__meta');
            var icon = node('span', 'material-symbols-outlined', 'account_balance');
            icon.setAttribute('aria-hidden', 'true');
            meta.appendChild(icon);
            meta.appendChild(node('span', '', 'Chairs ' + c.chairOf));
            body.appendChild(meta);
        }

        var check = node('span', 'material-symbols-outlined chair-user__check', 'check_circle');
        check.setAttribute('aria-hidden', 'true');

        row.appendChild(avatar);
        row.appendChild(body);
        row.appendChild(check);

        row.addEventListener('click', function () {
            if (!c.current) choose(c);
        });

        return row;
    }

    function updateSummary() {
        var mode    = modeInput.value;
        var current = selectedCandidate();

        if (mode === 'new') {
            summary.textContent = 'A new chairperson account will be created.';
            submitLabel.textContent = submitBtn.dataset.labelNew || 'Create & assign';
            submitBtn.disabled = false;

            return;
        }

        submitLabel.textContent = submitBtn.dataset.labelExisting || 'Assign chairperson';

        if (current === null) {
            summary.textContent = candidates.length === 0
                ? 'No eligible staff yet — create an account instead.'
                : 'Select a staff member to continue.';
            submitBtn.disabled = true;

            return;
        }

        summary.textContent = '';
        summary.appendChild(document.createTextNode('Assigning '));
        summary.appendChild(node('strong', '', current.name));
        submitBtn.disabled = false;
    }

    function render() {
        var counts = {
            chairperson: candidates.filter(isChair).length,
            faculty: candidates.filter(isFaculty).length,
            all: candidates.length,
        };

        var items = visibleCandidates();

        // Someone typing a name is looking for a PERSON, not a role. If the
        // current role filter has no match but the query matches elsewhere,
        // widen to all staff automatically (and move the active chip there)
        // instead of making them discover the filter themselves.
        if (query !== '' && items.length === 0 && filter !== 'all' && candidates.some(matchesQuery)) {
            filter = 'all';
            items = candidates.filter(matchesQuery);
        }

        filterBtns.forEach(function (btn) {
            var key   = btn.dataset.chairFilter;
            var count = btn.querySelector('[data-chair-count]');
            var on    = key === filter;

            if (count) count.textContent = counts[key];
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });

        list.innerHTML = '';

        items.forEach(function (c) { list.appendChild(buildRow(c)); });

        var isEmpty = items.length === 0;
        list.hidden = isEmpty;
        empty.hidden = !isEmpty;

        if (isEmpty) {
            emptyText.textContent = candidates.length === 0
                ? 'No eligible staff yet. Add a faculty member first, or create a new chairperson account.'
                : (query !== ''
                    ? 'No staff match “' + search.value.trim() + '”.'
                    : (filter === 'chairperson'
                        ? 'No chairpersons listed yet. Try the Faculty filter.'
                        : (filter === 'faculty' ? 'No faculty listed yet.' : 'No staff match your search.')));
            emptyReset.hidden = filter === 'all' || query !== '';
        }

        updateSummary();
    }


    function choose(c) {
        select.value = c.id;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        picker.classList.remove('is-invalid');
        render();

        var row = list.querySelector('[data-user-id="' + c.id + '"]');
        if (row) row.scrollIntoView({ block: 'nearest' });
    }

    function setMode(mode, focusField) {
        mode = mode === 'new' ? 'new' : 'existing';
        modeInput.value = mode;

        modeBtns.forEach(function (btn) {
            var on = btn.dataset.chairMode === mode;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });

        var isExisting = mode === 'existing';
        existingBox.hidden = !isExisting;
        newBox.hidden = isExisting;

        if (nameInput) nameInput.required = !isExisting;
        if (emailInput) emailInput.required = !isExisting;
        picker.classList.remove('is-invalid');

        updateSummary();

        if (focusField) {
            if (isExisting) search.focus();
            else if (nameInput) nameInput.focus();
        }
    }

    function focusRow(index) {
        var rows = Array.prototype.slice.call(list.querySelectorAll('.chair-user:not(.is-current)'));
        if (!rows.length) return;

        var next = Math.max(0, Math.min(rows.length - 1, index));
        rows[next].focus();
    }

    // ---- Events ---------------------------------------------------------

    modeBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setMode(btn.dataset.chairMode, true);
        });
    });

    filterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filter = btn.dataset.chairFilter;
            render();
        });
    });

    modal.querySelectorAll('[data-chair-mode-switch]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setMode(btn.dataset.chairModeSwitch, true);
        });
    });

    emptyReset.addEventListener('click', function () {
        filter = 'all';
        render();
        search.focus();
    });


    search.addEventListener('input', function () {
        query = search.value.trim().toLowerCase();
        render();
    });

    search.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            focusRow(0);

            return;
        }

        if (e.key === 'Enter') {
            // Exactly one match: Enter is an unambiguous confirmation.
            var matches = visibleCandidates().filter(function (c) { return !c.current; });

            if (matches.length === 1) {
                e.preventDefault();
                choose(matches[0]);
            }

            return;
        }

        if (e.key === 'Escape' && search.value !== '') {
            // First Escape clears the query; a second one closes the modal
            // (the layout's own document-level handler).
            e.preventDefault();
            e.stopPropagation();
            search.value = '';
            query = '';
            render();
        }
    });

    list.addEventListener('keydown', function (e) {
        var rows  = Array.prototype.slice.call(list.querySelectorAll('.chair-user:not(.is-current)'));
        var index = rows.indexOf(document.activeElement);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            focusRow(index < 0 ? 0 : index + 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            focusRow(index < 0 ? rows.length - 1 : index - 1);
        } else if (e.key === 'Home') {
            e.preventDefault();
            focusRow(0);
        } else if (e.key === 'End') {
            e.preventDefault();
            focusRow(rows.length - 1);
        } else if (e.key === 'Escape' && query !== '') {
            e.preventDefault();
            e.stopPropagation();
            search.value = '';
            query = '';
            render();
            search.focus();
        }
    });

    // "/" jumps straight into the search box, like a command palette.
    modal.addEventListener('keydown', function (e) {
        var active = document.activeElement;

        if (e.key === '/' && active !== search && !/^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName)) {
            e.preventDefault();
            search.focus();
        }
    });

    form.addEventListener('submit', function (e) {
        if (modeInput.value !== 'existing') return;

        var current = selectedCandidate();

        if (current === null || current.current) {
            e.preventDefault();
            picker.classList.add('is-invalid');
            search.focus();
        }
    });

    select.addEventListener('change', render);

    // ---- Boot -----------------------------------------------------------

    // Reveal the rich picker; the native select stays in the DOM as the
    // actual value holder (and remains the fallback if this script fails).
    picker.hidden = false;
    select.hidden = true;
    select.required = false;

    if (window.acInitSegmented && modeSwitch) {
        segmented = window.acInitSegmented(modeSwitch, { buttonSelector: '.chair-mode__btn' });
    }

    setMode(modeInput.value, false);
    render();

    // Called from the page's header button (via a listener below) and from
    // the validation-error restoration path, both of which need the picker
    // focused on open. The header button also keeps its own acOpenModal()
    // onclick, so the modal still opens even if this file fails to load.
    function onOpened() {
        // Fresh search on each open; an existing draft selection stays
        // selected and visible even if the default filter wouldn't list it.
        search.value = '';
        query = '';
        if (select.value) filter = 'all';

        if (segmented) {
            var active = modeSwitch.querySelector('.chair-mode__btn.is-active')
                || modeSwitch.querySelector('[aria-pressed="true"]');
            segmented.move(active, false);
        }

        render();

        setTimeout(function () {
            if (modeInput.value === 'existing') {
                search.focus();
            } else if (nameInput) {
                nameInput.focus();
            }
        }, 30);
    }

    window.acOpenChairModal = function () {
        if (typeof window.acOpenModal === 'function') {
            window.acOpenModal('modalChair');
        } else {
            modal.classList.add('is-open');
        }

        onOpened();
    };

    var opener = document.getElementById('chairAssignBtn');
    if (opener) {
        opener.addEventListener('click', function () { setTimeout(onOpened, 40); });
    }

    window.acSetChairMode = setMode;
})();


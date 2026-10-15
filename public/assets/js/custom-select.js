/**
 * Progressive enhancement for a native <select>: wraps it in a styled,
 * animated floating listbox (same look as the account-menu/notifications
 * dropdowns) instead of the OS-rendered popup a native select can't be
 * styled or animated in any browser.
 *
 * The original <select> is kept in the DOM (display:none via .cs-select__native)
 * as the real value holder — reading/writing .value and listening for
 * 'change' on it keeps working exactly as before, so existing page logic
 * doesn't need to change. A MutationObserver re-syncs the visible label
 * whenever the select's options or selection change from outside this
 * module (e.g. a page rebuilding the option list after a fetch).
 *
 * Usage: window.enhanceSelect(document.getElementById('mySelect'))
 */
window.enhanceSelect = function (select) {
    if (!select || select.dataset.csEnhanced) return null;
    select.dataset.csEnhanced = '1';

    var wrap = document.createElement('div');
    wrap.className = 'cs-select';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('cs-select__native');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');

    var triggerId = (select.id || 'csSelect') + 'Trigger';
    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'cs-select__trigger';
    trigger.id = triggerId;
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    if (select.hasAttribute('aria-describedby')) trigger.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));

    var label = document.createElement('span');
    label.className = 'cs-select__label';
    var chevron = document.createElement('span');
    chevron.className = 'material-symbols-outlined cs-select__chevron';
    chevron.setAttribute('aria-hidden', 'true');
    chevron.textContent = 'expand_more';
    trigger.appendChild(label);
    trigger.appendChild(chevron);
    wrap.appendChild(trigger);

    var panel = document.createElement('div');
    panel.className = 'cs-select__panel';
    panel.setAttribute('role', 'listbox');
    panel.tabIndex = -1;
    wrap.appendChild(panel);

    // A <label for="..."> pointing at the native select should still
    // activate this trigger, and screen readers announce it the same way.
    document.querySelectorAll('label[for="' + select.id + '"]').forEach(function (l) {
        l.setAttribute('for', triggerId);
    });

    function syncLabel() {
        var opt = select.options[select.selectedIndex];
        label.textContent = opt ? opt.textContent : '';
    }

    function activeOptions() {
        return Array.prototype.slice.call(panel.querySelectorAll('.cs-select__option'));
    }

    function moveActive(delta) {
        var items = activeOptions();
        if (!items.length) return;
        var current = items.findIndex(function (i) { return i.classList.contains('is-active'); });
        var next = Math.max(0, Math.min(items.length - 1, (current < 0 ? 0 : current) + delta));
        items.forEach(function (i) { i.classList.remove('is-active'); });
        items[next].classList.add('is-active');
        items[next].scrollIntoView({ block: 'nearest' });
    }

    function choose(item, opt) {
        select.value = opt.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        syncLabel();
        close();
        trigger.focus();
    }

    function buildPanel() {
        panel.innerHTML = '';
        Array.prototype.forEach.call(select.options, function (opt, i) {
            var item = document.createElement('div');
            item.className = 'cs-select__option' + (i === select.selectedIndex ? ' is-selected is-active' : '');
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', String(i === select.selectedIndex));
            item.textContent = opt.textContent;
            item.addEventListener('click', function () { choose(item, opt); });
            panel.appendChild(item);
        });
    }

    function onOutside(event) { if (!wrap.contains(event.target)) close(); }

    function onKeydown(event) {
        if (event.key === 'Escape') { close(); trigger.focus(); return; }
        if (event.key === 'ArrowDown') { event.preventDefault(); moveActive(1); return; }
        if (event.key === 'ArrowUp') { event.preventDefault(); moveActive(-1); return; }
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            var active = panel.querySelector('.cs-select__option.is-active');
            if (active) {
                var index = activeOptions().indexOf(active);
                choose(active, select.options[index]);
            }
        }
    }

    function open() {
        if (!select.options.length) return;
        buildPanel();
        wrap.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        document.addEventListener('click', onOutside, true);
        document.addEventListener('keydown', onKeydown, true);
    }

    function close() {
        wrap.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
        document.removeEventListener('click', onOutside, true);
        document.removeEventListener('keydown', onKeydown, true);
    }

    trigger.addEventListener('click', function () {
        if (wrap.classList.contains('is-open')) close(); else open();
    });

    // Catches option lists rebuilt (replaceChildren/innerHTML) or the
    // selection changed from outside this module.
    new MutationObserver(syncLabel).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['selected'] });
    select.addEventListener('change', syncLabel);

    syncLabel();
    return { sync: syncLabel, close: close, trigger: trigger };
};

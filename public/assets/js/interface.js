/* Shared, progressively enhanced navigation and confirmations. */
(function () {
    'use strict';
    const focusables = root => Array.from(root.querySelectorAll('a[href],button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex="0"]')).filter(el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');
    const sidebar = document.getElementById('acSidebar');
    const toggle = document.querySelector('.ac-nav-toggle');
    const backdrop = document.querySelector('.ac-nav-backdrop');
    const main = document.querySelector('.ac-main');
    const narrow = matchMedia('(max-width: 1100px)');
    function setNavigation(open, restoreFocus = true) {
        document.body.classList.toggle('ac-nav-open', open);
        toggle?.setAttribute('aria-expanded', String(open));
        if (backdrop) backdrop.hidden = !open;
        if (main) main.inert = open;
        if (sidebar) sidebar.inert = narrow.matches && !open;
        if (open) (sidebar.querySelector('[aria-current="page"]') || focusables(sidebar)[0])?.focus();
        else if (restoreFocus && narrow.matches) toggle?.focus();
    }
    toggle?.addEventListener('click', () => setNavigation(true));
    backdrop?.addEventListener('click', () => setNavigation(false));
    document.querySelector('.ac-nav-close')?.addEventListener('click', () => setNavigation(false));
    narrow.addEventListener('change', () => setNavigation(false, false));
    setNavigation(false, false);
    document.addEventListener('keydown', event => {
        if (!document.body.classList.contains('ac-nav-open')) return;
        if (event.key === 'Escape') { event.preventDefault(); setNavigation(false); }
        if (event.key === 'Tab') {
            const items = focusables(sidebar);
            const first = items[0], last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });

    // Page-specific search (e.g. teaching loads) keeps its existing handler.
    const search = document.getElementById('acSearchInput');
    if (search && search.placeholder === 'Find a page...') {
        const results = document.createElement('div');
        results.className = 'ac-search-results';
        results.id = 'acPageResults';
        results.hidden = true;
        results.setAttribute('aria-label', 'Matching pages');
        search.parentElement.append(results);
        search.setAttribute('aria-controls', results.id);
        search.setAttribute('aria-expanded', 'false');
        const links = Array.from(document.querySelectorAll('.ac-sidebar__nav a'));
        const close = () => { results.hidden = true; search.setAttribute('aria-expanded', 'false'); };
        search.addEventListener('input', () => {
            results.replaceChildren();
            const query = search.value.trim().toLowerCase();
            if (!query) { close(); return; }
            links.filter(link => link.querySelector('.ac-nav-label').textContent.toLowerCase().includes(query)).forEach(link => {
                const result = document.createElement('a');
                result.href = link.href;
                result.textContent = link.querySelector('.ac-nav-label').textContent;
                results.append(result);
            });
            if (!results.children.length) {
                const empty = document.createElement('p');
                empty.textContent = 'No matching pages. Try another name.';
                results.append(empty);
            }
            results.hidden = false;
            search.setAttribute('aria-expanded', 'true');
        });
        search.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') { event.preventDefault(); results.querySelector('a')?.focus(); }
            if (event.key === 'Escape') close();
        });
        results.addEventListener('keydown', event => {
            if (event.key === 'Escape') { close(); search.focus(); }
        });
        document.addEventListener('click', event => { if (!search.parentElement.contains(event.target)) close(); });
        search.parentElement.addEventListener('focusout', event => { if (!search.parentElement.contains(event.relatedTarget)) close(); });
    } else if (search) {
        document.querySelector('label[for="acSearchInput"]').textContent = search.placeholder;
    }

    // Native dialog supplies background inertness, Escape and focus containment.
    let activeConfirmation = false;
    window.acConfirm = function (message, options = {}) {
        if (activeConfirmation) return Promise.resolve(false);
        activeConfirmation = true;
        return new Promise(resolve => {
            const previous = document.activeElement;
            const dialog = document.createElement('dialog');
            dialog.className = 'ac-confirm';
            dialog.setAttribute('aria-labelledby', 'acConfirmTitle');
            dialog.setAttribute('aria-describedby', 'acConfirmMessage');
            const title = document.createElement('h2');
            title.id = 'acConfirmTitle';
            const destructive = /delete|remove|permanently|lock|replace/i.test(message);
            title.textContent = options.title || (destructive ? 'Confirm this change' : 'Review this change');
            const description = document.createElement('p');
            description.id = 'acConfirmMessage';
            description.textContent = message;
            const actions = document.createElement('div');
            actions.className = 'ac-confirm__actions';
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'ac-btn ac-btn-secondary';
            cancel.textContent = options.cancelLabel || 'Cancel';
            cancel.autofocus = true;
            const confirm = document.createElement('button');
            confirm.type = 'button';
            confirm.className = 'ac-btn ' + (destructive ? 'ac-btn-danger' : 'ac-btn-primary');
            confirm.textContent = options.confirmLabel || (destructive ? 'Confirm change' : 'Continue');
            let accepted = false;
            cancel.addEventListener('click', () => dialog.close());
            confirm.addEventListener('click', () => { accepted = true; dialog.close(); });
            dialog.addEventListener('close', () => {
                dialog.remove();
                activeConfirmation = false;
                if (previous?.isConnected) previous.focus();
                resolve(accepted);
            }, { once: true });
            actions.append(cancel, confirm);
            dialog.append(title, description, actions);
            document.body.append(dialog);
            dialog.addEventListener('keydown', event => {
                event.stopPropagation();
                if (event.key === 'Escape') { event.preventDefault(); dialog.close(); }
            });
            dialog.showModal();
        });
    };
    document.getElementById('acHelpBtn')?.addEventListener('click', () => {
        window.acConfirm('Use the navigation to move between your campus services. On smaller screens, open it with the menu button.\n\nFind a page searches the navigation available to your role. Use Tab to move between controls and Escape to close a dialog.\n\nFor account access or record corrections, contact your institution’s administrator.', {title: 'Using Akademus', cancelLabel: 'Close', confirmLabel: 'Got it'});
    });

    // Explicit data attributes replace native confirm without evaluating strings.
    const confirmed = new WeakSet();
    document.addEventListener('submit', async event => {
        const form = event.target;
        const submitter = event.submitter;
        const message = submitter?.dataset.confirm || form.dataset.confirm;
        if (!message || confirmed.has(form)) { confirmed.delete(form); return; }
        event.preventDefault();
        event.stopImmediatePropagation();
        if (await window.acConfirm(message)) {
            confirmed.add(form);
            form.requestSubmit(submitter || undefined);
            confirmed.delete(form);
        }
    }, true);
    document.querySelectorAll('.ac-modal__close:not([aria-label])').forEach(button => button.setAttribute('aria-label', 'Close dialog'));
    // Existing labels without "for" still need an explicit control association.
    document.querySelectorAll('.ac-field, .field').forEach((field, index) => {
        const label = field.querySelector('label');
        const input = field.querySelector('input:not([type="hidden"]),select,textarea');
        if (!label || !input || label.htmlFor || label.contains(input)) return;
        input.id = input.id || 'acField-' + index;
        label.htmlFor = input.id;
    });
})();

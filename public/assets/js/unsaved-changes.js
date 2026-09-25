/**
 * Global "unsaved changes" guard — one reusable system instead of each page
 * rolling its own dirty-tracking. Loaded once by the shared layout(s), it:
 *
 *   - Auto-tracks every <form> on the page (and any later added via AJAX/
 *     modals) for edits to text/select/checkbox/radio/date/time/textarea
 *     fields, with zero per-page wiring required.
 *   - Intercepts clicks on same-page navigation links while dirty, and
 *     shows a confirm modal before letting the navigation continue.
 *   - Arms the browser's native beforeunload prompt for refresh/back/close,
 *     since a page can't show a custom dialog for those.
 *   - Clears itself automatically when a tracked form is actually submitted
 *     (that's the "save"), so the very save action doesn't trigger its own
 *     warning.
 *   - Exposes window.UnsavedChanges so pages with non-<form> editable UI
 *     (e.g. a fetch()-driven modal) can opt in with track()/markClean()/
 *     guard() instead of duplicating a whole dirty-tracking system.
 */
(function () {
    'use strict';

    var dirty = false;
    var trackedForms = new WeakSet();
    var trackedContainers = new WeakSet();
    var suppressBeforeUnload = false;

    function isEditableField(el) {
        if (!el || !el.tagName) return false;
        var tag = el.tagName;
        if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
        if (tag === 'INPUT') {
            var type = (el.type || 'text').toLowerCase();
            return ['submit', 'button', 'reset', 'hidden', 'file', 'image'].indexOf(type) === -1;
        }
        return false;
    }

    function markDirty() { dirty = true; }
    function markClean() { dirty = false; }
    function isDirty() { return dirty; }

    function onFieldChange(e) {
        if (!isEditableField(e.target)) return;
        markDirty();
    }

    function trackForm(form) {
        if (!form || trackedForms.has(form)) return;
        // A GET form (filters, search, term pickers) only changes what is
        // shown; there is nothing to lose, so it must never arm the guard.
        // Otherwise changing a filter that reloads the page triggers the
        // browser's own "Leave site?" prompt.
        if (String(form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
        trackedForms.add(form);
        form.addEventListener('input', onFieldChange, true);
        form.addEventListener('change', onFieldChange, true);
        form.addEventListener('submit', function () {
            suppressBeforeUnload = true;
            markClean();
        });
    }

    // For editable UI that isn't inside a <form> (fetch()-driven modals).
    function trackContainer(container) {
        if (!container || trackedContainers.has(container)) return;
        trackedContainers.add(container);
        container.addEventListener('input', onFieldChange, true);
        container.addEventListener('change', onFieldChange, true);
    }

    function scanForms(root) {
        if (!root.querySelectorAll) return;
        var forms = root.matches && root.matches('form:not([data-no-dirty-tracking])')
            ? [root]
            : root.querySelectorAll('form:not([data-no-dirty-tracking])');
        forms.forEach ? forms.forEach(trackForm) : Array.prototype.forEach.call(forms, trackForm);
    }

    function init() { scanForms(document); }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Forms/fields added later (modals, AJAX-rendered content) get picked
    // up automatically too — no page needs to call track() for these.
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            m.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) return;
                scanForms(node);
            });
        });
    });
    if (document.body) {
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }

    // ---------- confirm modal ----------
    var modalEl = null;
    var pendingResolve = null;
    var lastFocused = null;

    function settle(result) {
        if (!pendingResolve) return;
        var resolve = pendingResolve;
        pendingResolve = null;
        modalEl.classList.remove('is-open');
        if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
        resolve(result);
    }

    function ensureModal() {
        if (modalEl) return modalEl;

        modalEl = document.createElement('div');
        modalEl.className = 'uc-modal';
        modalEl.setAttribute('role', 'alertdialog');
        modalEl.setAttribute('aria-modal', 'true');
        modalEl.setAttribute('aria-labelledby', 'ucModalTitle');
        modalEl.innerHTML =
            '<div class="uc-modal__panel">' +
                '<h3 class="uc-modal__title" id="ucModalTitle">Unsaved Changes</h3>' +
                '<p class="uc-modal__body">You have unsaved changes. If you leave now, your changes will be lost. Are you sure you want to continue?</p>' +
                '<div class="uc-modal__actions">' +
                    '<button type="button" class="ac-btn ac-btn-secondary" id="ucLeaveBtn">Leave Without Saving</button>' +
                    '<button type="button" class="ac-btn ac-btn-primary" id="ucStayBtn">Stay and Continue Editing</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(modalEl);

        modalEl.querySelector('#ucStayBtn').addEventListener('click', function () { settle(false); });
        modalEl.querySelector('#ucLeaveBtn').addEventListener('click', function () {
            markClean();
            settle(true);
        });
        modalEl.addEventListener('click', function (e) {
            if (e.target === modalEl) settle(false);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && pendingResolve) settle(false);
        });

        return modalEl;
    }

    /** Resolves true if it's safe to proceed (either clean, or user chose to leave). */
    function confirmLeave() {
        return new Promise(function (resolve) {
            if (!dirty) { resolve(true); return; }
            var modal = ensureModal();
            lastFocused = document.activeElement;
            pendingResolve = resolve;
            modal.classList.add('is-open');
            var leaveBtn = modal.querySelector('#ucLeaveBtn');
            if (leaveBtn) leaveBtn.focus();
        });
    }

    /** Runs action() immediately if clean; otherwise confirms first. */
    function guard(action) {
        if (!dirty) { action(); return; }
        confirmLeave().then(function (proceed) { if (proceed) action(); });
    }

    // ---------- same-page link interception ----------
    document.addEventListener('click', function (e) {
        if (!dirty) return;
        var link = e.target.closest ? e.target.closest('a[href]') : null;
        if (!link) return;
        if (link.target === '_blank' || link.hasAttribute('download') || link.hasAttribute('data-uc-ignore')) return;

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.toLowerCase().indexOf('javascript:') === 0) return;

        e.preventDefault();
        e.stopPropagation();
        confirmLeave().then(function (proceed) {
            if (proceed) window.location.href = link.href;
        });
    }, true);

    // ---------- refresh / back / close ----------
    window.addEventListener('beforeunload', function (e) {
        if (!dirty || suppressBeforeUnload) return undefined;
        e.preventDefault();
        e.returnValue = '';
        return '';
    });

    window.UnsavedChanges = {
        track: trackForm,
        trackContainer: trackContainer,
        markDirty: markDirty,
        markClean: markClean,
        isDirty: isDirty,
        confirmLeave: confirmLeave,
        guard: guard,
    };
})();

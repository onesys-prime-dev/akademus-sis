(() => {
    'use strict';
    const root = document.getElementById('gradeApprovals');
    const $ = id => document.getElementById(id);
    if (!root || !$('gaTabs')) return;
    const config = JSON.parse($('gaConfig').textContent);
    const tabs = [...root.querySelectorAll('[data-tab]')];
    const prefersReducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Sliding pill behind the active tab — created once, repositioned on
    // every activate() rather than toggling a background color per button.
    const tabsBar = $('gaTabs');
    const indicator = document.createElement('div');
    indicator.className = 'ga-tabs-indicator';
    indicator.setAttribute('aria-hidden', 'true');
    tabsBar.insertBefore(indicator, tabsBar.firstChild);
    function moveIndicatorTo(tab) {
        // tab.offsetLeft is already relative to .ga-tabs's own padding box
        // (its nearest positioned ancestor, since .ga-tabs is position:relative
        // and the indicator shares that same reference frame) — no further
        // offset math needed.
        indicator.style.width = `${tab.offsetWidth}px`;
        indicator.style.transform = `translateX(${tab.offsetLeft}px)`;
    }

    let currentPanel = null;
    let isFirstActivate = true;
    function activate(tab, updateUrl = true) {
        tabs.forEach(button => {
            const active = button === tab;
            button.setAttribute('aria-selected', String(active)); button.tabIndex = active ? 0 : -1;
        });
        moveIndicatorTo(tab);
        if (updateUrl) history.replaceState(null, '', `#${tab.dataset.tab}`);

        const nextPanel = $(tab.dataset.tab);
        if (nextPanel === currentPanel) return;
        const prevPanel = currentPanel;
        currentPanel = nextPanel;

        // The very first call just confirms the server-rendered hidden
        // state (one panel already shown, the rest already [hidden]) — no
        // real visual change happens, so animating it would be a flicker,
        // not a transition.
        if (isFirstActivate) {
            isFirstActivate = false;
            nextPanel.hidden = false;
            return;
        }

        if (prefersReducedMotion) {
            if (prevPanel) prevPanel.hidden = true;
            nextPanel.hidden = false;
            return;
        }

        const swapIn = () => {
            nextPanel.hidden = false;
            nextPanel.classList.add('ga-panel-enter');
            // Force layout so the browser registers the pre-transition state
            // before the class removal starts animating it — otherwise the
            // two style changes coalesce into one frame and nothing moves.
            void nextPanel.offsetWidth;
            nextPanel.classList.remove('ga-panel-enter');
            nextPanel.addEventListener('transitionend', () => nextPanel.classList.remove('ga-panel-enter'), { once: true });
        };

        if (prevPanel) {
            prevPanel.classList.add('ga-panel-leave');
            const finishLeave = () => { prevPanel.hidden = true; prevPanel.classList.remove('ga-panel-leave'); };
            prevPanel.addEventListener('transitionend', finishLeave, { once: true });
            setTimeout(finishLeave, 260); // fallback if transitionend never fires
            setTimeout(swapIn, 90); // slight overlap reads as one motion, not two
        } else {
            swapIn();
        }
    }
    tabs.forEach((tab, index) => {
        const panel = $(tab.dataset.tab);
        panel.setAttribute('role', 'tabpanel'); panel.setAttribute('aria-labelledby', tab.id);
        tab.addEventListener('click', () => activate(tab));
        tab.addEventListener('keydown', event => {
            const target = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowLeft' ? (index + tabs.length - 1) % tabs.length : null;
            if (target !== null) { event.preventDefault(); activate(tabs[target]); tabs[target].focus(); }
        });
    });
    addEventListener('resize', () => { const active = tabs.find(t => t.getAttribute('aria-selected') === 'true'); if (active) moveIndicatorTo(active); });
    function filter(panel) {
        const query = panel.querySelector('input[type=search]').value.trim().toLocaleLowerCase();
        const rows = [...panel.querySelectorAll('[data-search-row]')];
        rows.forEach(row => { row.hidden = !row.textContent.toLocaleLowerCase().includes(query); });
        const count = rows.filter(row => !row.hidden).length;
        panel.querySelector('[data-no-results]').hidden = !rows.length || !!count;
        const table = panel.querySelector('.ga-table-scroll'); if (table) table.hidden = !count;
        const noun = count === 1 ? (panel.dataset.nounSingular || 'item') : (panel.dataset.nounPlural || 'items');
        panel.querySelector('[data-result-count]').textContent = query ? `${count} of ${rows.length} ${noun} shown` : `${count} ${noun}${panel.dataset.countSuffix || ''}`;
    }
    root.querySelectorAll('[data-search]').forEach(input => input.addEventListener('input', () => filter($(input.dataset.search))));
    root.querySelectorAll('[data-search-control]').forEach(label => { label.hidden = false; });
    $('gaTabs').hidden = false;
    const requestedTab = tabs.find(tab => `#${tab.dataset.tab}` === location.hash);
    activate(requestedTab || tabs[0], false);

    const dialog = $('gaDecisionDialog');
    let pending = null, busy = false, returnFocus = null;
    root.addEventListener('click', event => {
        const button = event.target.closest('[data-decision]'); if (!button || busy) return;
        const row = button.closest('[data-request-id]');
        pending = { row, id: Number(row.dataset.requestId), action: button.dataset.decision };
        returnFocus = button;
        $('gaDecisionTitle').textContent = pending.action === 'approve' ? 'Approve grade correction?' : 'Reject grade correction?';
        $('gaDecisionDescription').textContent = pending.action === 'approve' ? 'The requested grade will replace the current official grade.' : 'The current official grade will remain unchanged.';
        const summary = $('gaDecisionSummary'); summary.replaceChildren();
        const title = document.createElement('strong'); title.textContent = `${row.querySelector('[data-student]').textContent} · ${row.querySelector('[data-subject]').textContent}`;
        const context = document.createElement('p'); context.textContent = row.querySelector('[data-context]').textContent;
        const reason = document.createElement('p'); reason.className = 'ga-reason'; reason.textContent = row.querySelector('[data-reason]').textContent;
        summary.append(title, context, row.querySelector('.ga-change').cloneNode(true), reason);
        $('gaNotes').value = ''; $('gaDecisionError').hidden = true;
        $('gaConfirmDecision').textContent = pending.action === 'approve' ? 'Approve correction' : 'Reject request';
        dialog.showModal(); $('gaCancelDecision').focus();
    });
    $('gaCancelDecision').addEventListener('click', () => { if (!busy) dialog.close(); });
    dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
    dialog.addEventListener('close', () => { if (returnFocus?.isConnected) returnFocus.focus(); else $('gaCorrectionsTab').focus(); });
    $('gaDecisionForm').addEventListener('submit', async event => {
        event.preventDefault(); if (busy || !pending) return;
        busy = true; dialog.setAttribute('aria-busy', 'true');
        const buttons = [...dialog.querySelectorAll('button,textarea')]; buttons.forEach(button => { button.disabled = true; });
        const label = $('gaConfirmDecision').textContent; $('gaConfirmDecision').textContent = 'Saving decision…'; $('gaDecisionError').hidden = true;
        try {
            const response = await fetch(`${config.endpoint}/${pending.action}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ request_id: pending.id, notes: $('gaNotes').value.trim() }) });
            const result = await response.json().catch(() => null);
            if (!response.ok || !result?.ok) throw new Error(result?.error || 'Could not save this decision. Please try again.');
            pending.row.remove();
            const remaining = $('gaRequestList').children.length;
            $('gaCorrectionCount').textContent = remaining; $('gaCorrectionsEmpty').hidden = remaining > 0;
            filter($('gaCorrections'));
            $('gaFeedback').textContent = `Correction request ${pending.action === 'approve' ? 'approved' : 'rejected'}.`;
            $('gaFeedback').hidden = false;
            dialog.close();
        } catch (error) {
            $('gaDecisionError').textContent = error.message; $('gaDecisionError').hidden = false;
        } finally {
            busy = false; dialog.removeAttribute('aria-busy'); buttons.forEach(button => { button.disabled = false; }); $('gaConfirmDecision').textContent = label;
        }
    });
})();

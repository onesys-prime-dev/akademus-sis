/* Page-local behavior. POST field names and endpoints stay server-owned. */
(function () {
    'use strict';
    const root = document.getElementById('facultyAvailability');
    if (!root) return;
    const availability = document.getElementById('availabilityForm');
    const preferences = document.getElementById('preferencesForm');
    const statuses = Array.from(root.querySelectorAll('.day-status'));
    const subjects = Array.from(root.querySelectorAll('.subj-row'));
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const byId = id => document.getElementById(id);
    const minutes = value => value ? Number(value.slice(0, 2)) * 60 + Number(value.slice(3, 5)) : null;
    const number = value => Number(value.toFixed(1)).toString();
    const timeLabel = value => {
        const hour = Math.floor(value / 60), minute = value % 60;
        return (hour % 12 || 12) + (minute ? ':' + String(minute).padStart(2, '0') : '') + (hour >= 12 ? ' PM' : ' AM');
    };
    const snapshot = form => form ? JSON.stringify(Array.from(new FormData(form)).filter(([key]) => !/csrf/i.test(key))) : '';
    let originals, initialDays, initialPreferences, activeFilter = '';
    let copying = false;

    function renderDay(select) {
        const day = select.dataset.day, available = select.value === 'available';
        const start = byId('start_' + day), end = byId('end_' + day), notes = byId('notes_' + day);
        start.disabled = end.disabled = !available;
        start.required = end.required = available;
        notes.disabled = select.value === '';
        if (!available) start.value = end.value = '';
        const from = minutes(start.value), to = minutes(end.value);
        end.setCustomValidity(available && from !== null && to !== null && to <= from ? 'End time must be after start time.' : '');
        const hours = available && from !== null && to > from ? (to - from) / 60 : 0;
        byId('hrs_' + day).textContent = available ? number(hours) + 'h' : '—';
        const row = byId('row_' + day);
        row.classList.toggle('is-available', available);
        row.classList.toggle('is-unavailable', select.value === 'unavailable');
        const detail = row.querySelector('details');
        detail.hidden = select.value === '';
        detail.querySelector('summary').textContent = notes.value ? 'Note for chairperson' : 'Add note';
        const copy = row.querySelector('.avail-copy-btn');
        if (copy) copy.disabled = !available || from === null || to === null || to <= from;
        return hours;
    }

    function renderTimeline() {
        const container = byId('heatmap');
        if (!container) return;
        // Expand the axis for early/late hours instead of clipping submitted times.
        let start = 7 * 60, end = 19 * 60;
        statuses.forEach(select => {
            if (select.value !== 'available') return;
            const from = minutes(byId('start_' + select.dataset.day).value), to = minutes(byId('end_' + select.dataset.day).value);
            if (from !== null) start = Math.min(start, Math.floor(from / 60) * 60);
            if (to !== null) end = Math.max(end, Math.ceil(to / 60) * 60);
        });
        container.replaceChildren();
        const axis = document.createElement('div');
        axis.className = 'heatmap-axis';
        const ticks = [];
        for (let value = start; value < end; value += 120) ticks.push(value);
        ticks.push(end);
        ticks.forEach(value => {
            const label = document.createElement('span');
            label.style.left = ((value - start) / (end - start) * 100) + '%';
            label.textContent = timeLabel(value);
            axis.append(label);
        });
        container.append(axis);
        const descriptions = [];
        statuses.forEach(select => {
            const day = select.dataset.day;
            const row = document.createElement('div'); row.className = 'heatmap-row';
            const label = document.createElement('span'); label.className = 'hm-day'; label.textContent = days[day];
            const track = document.createElement('div'); track.className = 'heatmap-track';
            ticks.forEach(value => {
                const tick = document.createElement('span'); tick.className = 'heatmap-tick';
                tick.style.left = ((value - start) / (end - start) * 100) + '%'; track.append(tick);
            });
            const from = minutes(byId('start_' + day).value), to = minutes(byId('end_' + day).value);
            const block = document.createElement('span');
            if (select.value === 'available' && from !== null && to !== null && to > from) {
                block.className = 'heatmap-block';
                block.style.left = ((from - start) / (end - start) * 100) + '%';
                block.style.width = ((to - from) / (end - start) * 100) + '%';
                block.textContent = timeLabel(from) + ' – ' + timeLabel(to);
            } else {
                block.className = 'heatmap-empty';
                block.textContent = select.value === 'available' ? 'Set a valid time range' : select.value === 'unavailable' ? 'Unavailable' : 'No preference';
            }
            descriptions.push(days[day] + ': ' + block.textContent);
            block.title = days[day] + ': ' + block.textContent;
            track.append(block); row.append(label, track); container.append(row);
        });
        container.setAttribute('aria-label', descriptions.join('. '));
    }

    function renderAvailability() {
        if (!availability) return;
        let hours = 0, count = 0;
        statuses.forEach(select => { hours += renderDay(select); if (select.value === 'available') count++; });
        byId('metricDays').textContent = count + ' available ' + (count === 1 ? 'day' : 'days');
        byId('metricHours').textContent = number(hours) + ' total hours';
        renderTimeline();
    }

    function renderPreferences() {
        if (!preferences) return;
        const counts = {preferred: 0, willing: 0, qualified: 0, unset: 0};
        const query = byId('subjSearch').value.trim().toLowerCase();
        let visible = 0;
        subjects.forEach(row => {
            const level = row.querySelector('.pref-input').value;
            row.dataset.level = level; counts[level || 'unset']++;
            row.querySelectorAll('.pref-pill').forEach(button => {
                button.classList.toggle('active', button.dataset.level === level);
                button.setAttribute('aria-pressed', String(button.dataset.level === level));
            });
            const show = (!activeFilter || (level || 'unset') === activeFilter) && row.dataset.search.includes(query);
            row.classList.toggle('is-hidden', !show); if (show) visible++;
        });
        root.querySelectorAll('.pref-tab').forEach(tab => {
            const level = tab.dataset.level;
            const title = {preferred: 'Preferred', willing: 'Willing', qualified: 'Qualified', unset: 'Not set'}[level] || 'All';
            tab.textContent = title + ' (' + (level ? counts[level] : subjects.length) + ')';
            tab.classList.toggle('active', level === activeFilter);
            tab.setAttribute('aria-pressed', String(level === activeFilter));
        });
        byId('subjEmpty').style.display = visible ? 'none' : '';
    }

    function dirty(form) { return !!form && originals && snapshot(form) !== originals[form.id]; }
    function updateDirty() {
        if (!originals) return;
        [[availability, 'availabilitySaveStatus', 'saveAvailability', 'discardBtn'], [preferences, 'preferencesSaveStatus', 'savePreferences', 'discardPreferences']].forEach(([form, status, save, discard]) => {
            if (!form) return;
            const changed = dirty(form);
            byId(status).textContent = changed ? 'Unsaved changes' : 'No unsaved changes';
            byId(save).disabled = !changed || copying;
            byId(discard).disabled = !changed || copying;
        });
        if (window.UnsavedChanges) window.UnsavedChanges[dirty(availability) || dirty(preferences) ? 'markDirty' : 'markClean']();
    }
    function setDay(day, status, start, end, notes) {
        byId('status_' + day).value = status;
        byId('start_' + day).value = start || ''; byId('end_' + day).value = end || '';
        if (notes !== undefined) {
            byId('notes_' + day).value = notes || '';
            byId('notes_' + day).closest('details').open = !!notes;
        }
    }
    const confirm = (message, confirmLabel, title = 'Unsaved changes') => window.acConfirm(message, {title, confirmLabel, cancelLabel: 'Stay'});

    renderAvailability(); renderPreferences();
    initialDays = statuses.map(select => ({day: select.dataset.day, status: select.value, start: byId('start_' + select.dataset.day).value, end: byId('end_' + select.dataset.day).value, notes: byId('notes_' + select.dataset.day).value}));
    initialPreferences = subjects.map(row => row.querySelector('.pref-input').value);
    originals = {availabilityForm: snapshot(availability), preferencesForm: snapshot(preferences)};
    updateDirty();
    availability?.addEventListener('input', () => { renderAvailability(); updateDirty(); });
    availability?.addEventListener('change', () => { renderAvailability(); updateDirty(); });
    root.querySelectorAll('.preset-btn').forEach(button => button.addEventListener('click', () => {
        const preset = button.dataset.preset;
        statuses.forEach(select => {
            const day = Number(select.dataset.day);
            if (preset === 'clear') setDay(day, '', '', '');
            else if (day < 5) setDay(day, 'available', preset === 'afternoon' ? '13:00' : '08:00', preset === 'morning' ? '12:00' : '17:00');
        });
        renderAvailability(); updateDirty();
    }));
    root.querySelectorAll('.avail-copy-btn').forEach(button => button.addEventListener('click', () => {
        const from = byId('start_' + button.dataset.day).value, to = byId('end_' + button.dataset.day).value;
        if (!from || !to || from >= to) return;
        for (let day = 0; day < 5; day++) setDay(day, 'available', from, to);
        renderAvailability(); updateDirty();
    }));
    byId('discardBtn')?.addEventListener('click', () => {
        initialDays.forEach(day => setDay(day.day, day.status, day.start, day.end, day.notes));
        renderAvailability(); updateDirty();
    });
    byId('discardPreferences')?.addEventListener('click', () => {
        subjects.forEach((row, i) => { row.querySelector('.pref-input').value = initialPreferences[i]; });
        renderPreferences(); updateDirty();
    });
    subjects.forEach(row => row.querySelectorAll('.pref-pill, .pref-pill-reset').forEach(button => button.addEventListener('click', () => {
        const input = row.querySelector('.pref-input'), level = button.dataset.level || '';
        input.value = input.value === level ? '' : level;
        renderPreferences(); updateDirty();
    })));
    root.querySelectorAll('.pref-tab').forEach(tab => tab.addEventListener('click', () => { activeFilter = tab.dataset.level; renderPreferences(); }));
    byId('subjSearch')?.addEventListener('input', renderPreferences);

    byId('copyTermBtn')?.addEventListener('click', async function () {
        const source = byId('copyTermSelect').value;
        if (!source) return;
        if (dirty(availability) && !await confirm('Copying another term will replace your unsaved weekly availability. Continue?', 'Copy availability')) return;
        const feedback = byId('copyFeedback');
        copying = true; this.disabled = true; this.textContent = 'Copying…'; updateDirty();
        // Prevent edits from racing the request; restore each field's prior state.
        const controls = Array.from(root.querySelectorAll('#availabilityForm input, #availabilityForm select, #availabilityForm button, .preset-btn, #termSelect, #copyTermSelect'));
        const disabled = controls.map(control => control.disabled);
        controls.forEach(control => { control.disabled = true; });
        try {
            const response = await fetch(root.dataset.copyUrl + '/' + encodeURIComponent(source), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (!response.ok) throw Error('Copy failed');
            const data = await response.json();
            if (!data.ok || !data.days) throw Error('Copy failed');
            statuses.forEach(select => { const day = data.days[select.dataset.day]; setDay(select.dataset.day, day?.status || '', day?.start, day?.end, day?.notes || ''); });
            feedback.textContent = 'Availability copied. Review the hours, then save.';
            feedback.classList.remove('is-error');
        } catch (_) {
            feedback.textContent = 'Could not copy availability. Your current hours have been kept. Try again.';
            feedback.classList.add('is-error');
        } finally {
            controls.forEach((control, i) => { control.disabled = disabled[i]; });
            copying = false; this.disabled = false; this.textContent = 'Copy'; feedback.hidden = false;
            renderAvailability(); updateDirty();
        }
    });

    const termSelect = byId('termSelect'), originalTerm = termSelect?.value;
    termSelect?.addEventListener('change', async () => {
        const next = termSelect.value; termSelect.value = originalTerm;
        if (next === originalTerm) return;
        if ((dirty(availability) || dirty(preferences)) && !await confirm('You have changes to your availability or subject preferences that have not been saved. Leave without saving?', 'Leave')) return;
        window.UnsavedChanges?.markClean();
        window.location.href = root.dataset.termUrl + '?ay_id=' + encodeURIComponent(next);
    });
    // Run before the shared link guard, using the shared accessible dialog.
    window.addEventListener('click', async event => {
        const link = event.target.closest?.('a[href]');
        if (!link || link.target === '_blank' || link.hasAttribute('download') || link.getAttribute('href').startsWith('#') || (!dirty(availability) && !dirty(preferences))) return;
        event.preventDefault(); event.stopImmediatePropagation();
        if (await confirm('You have changes to your availability or subject preferences that have not been saved. Leave without saving?', 'Leave')) {
            window.UnsavedChanges?.markClean(); window.location.href = link.href;
        }
    }, true);
    // Both endpoints redirect. Confirm before saving one section would discard the other.
    [availability, preferences].filter(Boolean).forEach(form => {
        let approved = false;
        form.addEventListener('submit', async event => {
            const other = form === availability ? preferences : availability;
            if (!approved && dirty(other)) {
                event.preventDefault(); event.stopImmediatePropagation();
                const section = form === availability ? 'subject preferences' : 'weekly availability';
                if (await confirm('Saving this section reloads the page. Unsaved changes to your ' + section + ' will be lost. Save this section and leave the other changes?', 'Save and continue')) {
                    approved = true; form.requestSubmit(event.submitter); approved = false;
                }
                return;
            }
            window.UnsavedChanges?.markClean();
        }, true);
    });
})();

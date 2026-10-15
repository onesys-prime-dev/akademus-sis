(() => {
    'use strict';
    const root = document.getElementById('studentSchedule');
    const config = document.getElementById('ssConfig');
    if (!root || !config) return;
    root.querySelectorAll('form[data-drop-code]').forEach(form => {
        form.addEventListener('submit', event => {
            if (!window.confirm(`Drop ${form.dataset.dropCode}? You can re-enroll later if a seat is available.`)) event.preventDefault();
        });
    });
    const raw = JSON.parse(config.textContent).meetings;
    if (!raw.length) return;
    const $ = id => document.getElementById(id);
    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const minutes = time => { const [h, m] = time.split(':').map(Number); return h * 60 + m; };
    const time = value => `${Math.floor(value / 60) % 12 || 12}:${String(value % 60).padStart(2, '0')} ${value < 720 ? 'AM' : 'PM'}`;
    const range = m => `${time(m.from)} – ${time(m.to)}`;
    const hours = value => Number((value / 60).toFixed(2));
    const totalMinutes = list => list.reduce((sum, m) => sum + m.to - m.from, 0);
    const classes = new Map();
    raw.forEach(m => {
        // A repeated code in another section or term is a separate class.
        const key = JSON.stringify([m.term, m.program, m.year, m.section, m.code]);
        if (!classes.has(key)) classes.set(key, { ...m, id: classes.size, meetings: [] });
        classes.get(key).meetings.push({ ...m, from: minutes(m.start), to: minutes(m.end) });
    });
    const classList = [...classes.values()];
    const meetings = [];
    classList.forEach(c => {
        const merged = [];
        c.meetings.sort((a, b) => a.day - b.day || a.from - b.from).forEach(m => {
            const last = merged[merged.length - 1];
            // Adjacent slots for the same class read as one continuous meeting.
            if (last && last.day === m.day && last.to === m.from && last.room === m.room && last.faculty === m.faculty) last.to = m.to;
            else merged.push({ ...m, classId: c.id });
        });
        c.meetings = merged;
        // Same accent the chairperson assigned when plotting this class (see
        // TeachingLoads::ACCENTS) — not an independently hashed color, so a
        // subject reads as the same color here as on the faculty's own board.
        c.accent = merged[0]?.accent || 'blue';
        merged.forEach(m => { m.id = meetings.length; m.accent = c.accent; meetings.push(m); });
    });
    const terms = [...new Set(classList.map(c => c.term))];
    $('ssTerm').replaceChildren(...terms.map(term => new Option(term, term)));
    let view = window.matchMedia('(max-width: 700px)').matches ? 'agenda' : 'calendar';
    let day = 'all', selected = null, returnFocus = null;
    let viewToggleIndicator = null, dayFilterIndicator = null;
    const dialog = $('ssClassDialog');
    const termMeetings = () => meetings.filter(m => m.term === $('ssTerm').value);
    const matchesSubject = m => $('ssSubject').value === 'all' || m.classId === Number($('ssSubject').value);

    function updateSubjects() {
        const options = classList.filter(c => c.term === $('ssTerm').value).sort((a, b) => a.code.localeCompare(b.code, undefined, { numeric: true }));
        $('ssSubject').replaceChildren(new Option('All subjects', 'all'), ...options.map(c => {
            const repeated = options.filter(other => other.code === c.code).length > 1;
            return new Option(`${c.code}${repeated ? ` · ${c.section} · ${c.program} · ${c.year}` : ''}`, String(c.id));
        }));
    }

    // Partition overlapping intervals into lanes, so no meeting hides another.
    function positionMeetings(list) {
        const sorted = list.map(m => ({ ...m })).sort((a, b) => a.from - b.from || a.to - b.to);
        let group = [], ends = [], groupEnd = -1;
        const finish = () => group.forEach(m => { m.lanes = ends.length; });
        sorted.forEach(m => {
            if (m.from >= groupEnd) { finish(); group = []; ends = []; }
            let lane = ends.findIndex(end => end <= m.from);
            if (lane < 0) lane = ends.length;
            ends[lane] = m.to;
            m.lane = lane; group.push(m); groupEnd = Math.max(...ends);
        });
        finish();
        return sorted;
    }

    function render() {
        const all = termMeetings();
        const visible = all.filter(m => matchesSubject(m) && (day === 'all' || m.day === Number(day)));
        // Always show the full Monday–Sunday week, same as the chairperson's
        // plotting calendar — not just the days that happen to have a class.
        const dayCount = 7;
        $('ssSubjectCount').textContent = new Set(all.map(m => m.code)).size;
        $('ssHours').textContent = hours(totalMinutes(all));
        $('ssDays').textContent = new Set(all.map(m => m.day)).size;
        root.querySelectorAll('[data-day]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.day === day)));
        root.querySelectorAll('[data-view]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.view === view)));
        $('ssCalendar').hidden = view !== 'calendar' || !visible.length;
        $('ssAgenda').hidden = view !== 'agenda' || !visible.length;
        $('ssFilteredEmpty').hidden = !!visible.length;
        const start = Math.floor(Math.min(420, ...all.map(m => m.from)) / 60) * 60;
        const end = Math.ceil(Math.max(1080, ...all.map(m => m.to)) / 60) * 60;
        const scale = 1.1, height = (end - start) * scale;
        const shownDays = day === 'all' ? Array.from({ length: dayCount }, (_, i) => i) : [Number(day)];
        const timeLabels = Array.from({ length: (end - start) / 30 }, (_, i) => {
            const t = start + i * 30;
            const onHour = t % 60 === 0;
            return `<div class="ss-time-label ${onHour ? 'is-hour' : ''}" style="height:${30 * scale}px">${onHour ? time(t).replace(':00', '') : time(t)}</div>`;
        }).join('');
        const columns = shownDays.map(index => {
            const list = positionMeetings(visible.filter(m => m.day === index));
            return `<div class="ss-day-column" style="height:${height}px">${!list.length ? '<span class="ss-free-day">No classes</span>' : ''}${list.map(m => {
                const duration = m.to - m.from;
                const label = `${m.code}, ${m.title}, ${days[m.day]}, ${range(m)}, ${m.room || 'Room to be assigned'}. View class details.`;
                return `<button type="button" class="ss-class ss-accent-${m.accent} ${duration <= 30 ? 'ss-class--short' : ''} ${selected?.classId === m.classId ? 'is-selected' : ''}" data-meeting="${m.id}" aria-haspopup="dialog" aria-label="${escape(label)}" title="${escape(label)}" style="top:${(m.from - start) * scale + 2}px;height:${Math.max(20, duration * scale - 4)}px;left:calc(${m.lane / m.lanes * 100}% + 4px);width:calc(${100 / m.lanes}% - 8px)"><span class="ss-class-code">${escape(m.code)}<span class="material-symbols-outlined" aria-hidden="true">arrow_outward</span></span><span class="ss-class-title">${escape(m.title)}</span><span class="ss-class-time">${range(m)}</span><span class="ss-class-room">${escape(m.room || 'Room to be assigned')}</span></button>`;
            }).join('')}</div>`;
        }).join('');
        $('ssCalendar').innerHTML = `<div class="ss-grid ${shownDays.length === 1 ? 'ss-grid--single' : ''}" style="--ss-days:${shownDays.length};--ss-hour:${30 * scale}px"><div class="ss-grid-head ss-grid-corner">GMT+8</div>${shownDays.map(index => `<div class="ss-grid-head"><strong>${days[index]}</strong><span>${visible.filter(m => m.day === index).length} meetings</span></div>`).join('')}<div class="ss-time-column">${timeLabels}</div>${columns}</div>`;
        $('ssAgenda').innerHTML = shownDays.map(index => {
            const list = visible.filter(m => m.day === index).sort((a, b) => a.from - b.from);
            if (!list.length) return '';
            return `<section class="ss-agenda-day"><header><h3>${days[index]}</h3><span>${list.length} ${list.length === 1 ? 'meeting' : 'meetings'} · ${hours(totalMinutes(list))} hours</span></header>${list.map(m => `<button type="button" class="ss-agenda-class ss-accent-${m.accent} ${selected?.classId === m.classId ? 'is-selected' : ''}" data-meeting="${m.id}" aria-haspopup="dialog"><span class="ss-agenda-time">${time(m.from)}<small>${time(m.to)}</small></span><span class="ss-agenda-subject"><strong>${escape(m.code)}</strong><span>${escape(m.title)}</span></span><span class="ss-agenda-room"><span class="material-symbols-outlined" aria-hidden="true">location_on</span>${escape(m.room || 'Room to be assigned')}</span><span class="material-symbols-outlined ss-agenda-arrow" aria-hidden="true">arrow_forward</span></button>`).join('')}</section>`;
        }).join('');
        $('ssResultCount').textContent = `${visible.length} ${visible.length === 1 ? 'meeting' : 'meetings'} · ${hours(totalMinutes(visible))} hours${day !== 'all' ? ` · ${days[Number(day)]}` : ' this week'}`;
    }

    function openDetails(id, trigger) {
        selected = meetings.find(m => m.id === id);
        if (!selected) return;
        if (trigger) returnFocus = trigger;
        const c = classList[selected.classId];
        dialog.className = `ss-dialog ss-accent-${selected.accent}`;
        $('ssDetailCode').textContent = selected.code;
        $('ssDetailTitle').textContent = selected.title;
        $('ssDetailSubtitle').textContent = selected.term;
        $('ssDetailDay').textContent = days[selected.day];
        $('ssDetailTime').textContent = range(selected);
        $('ssDetailDuration').textContent = `${hours(selected.to - selected.from)} hrs`;
        const facts = [
            ['person', 'Instructor', selected.faculty || 'To be assigned'],
            ['location_on', 'Room', selected.room || 'To be assigned'],
            ['groups', 'Section', selected.section],
            ['school', 'Program', selected.program],
            ['stairs', 'Year level', selected.year],
        ];
        $('ssDetailFacts').innerHTML = facts.map(([icon, label, value]) => `<div><dt><span class="material-symbols-outlined" aria-hidden="true">${icon}</span>${label}</dt><dd>${escape(value || 'Not available')}</dd></div>`).join('');
        $('ssDetailHours').textContent = `${hours(totalMinutes(c.meetings))} hours / week`;
        $('ssDetailMeetings').innerHTML = c.meetings.map(m => `<button type="button" data-detail-meeting="${m.id}" aria-pressed="${m.id === selected.id}"><span class="ss-meeting-day">${days[m.day].slice(0, 3)}</span><span><strong>${range(m)}</strong><small>${escape(m.room || 'Room to be assigned')}</small></span><span class="material-symbols-outlined" aria-hidden="true">${m.id === selected.id ? 'check_circle' : 'chevron_right'}</span></button>`).join('');
        root.querySelectorAll('[data-meeting]').forEach(button => button.classList.toggle('is-selected', meetings[Number(button.dataset.meeting)].classId === c.id));
        if (!dialog.open) { dialog.showModal(); $('ssCloseDetails').focus(); }
    }

    function preparePrint() {
        // Print all meetings in this term, independently of the screen filters.
        const list = termMeetings();
        const dayCount = 7;
        const start = Math.floor(Math.min(420, ...list.map(m => m.from)) / 60) * 60;
        const end = Math.ceil(Math.max(1080, ...list.map(m => m.to)) / 60) * 60;
        const span = end - start;
        let documentNode = $('ssPrintDocument');
        if (!documentNode) { documentNode = document.createElement('article'); documentNode.id = 'ssPrintDocument'; document.body.appendChild(documentNode); }
        const context = [...new Set(list.map(m => `${m.program} · ${m.year} · Section ${m.section}`))].join(' / ');
        const date = new Intl.DateTimeFormat('en-PH', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Manila' }).format(new Date());
        const labels = Array.from({ length: span / 30 }, (_, i) => {
            const t = start + i * 30;
            const onHour = t % 60 === 0;
            return `<span class="${onHour ? 'is-hour' : ''}" style="top:${i * 30 / span * 100}%">${onHour ? time(t).replace(':00', '') : time(t)}</span>`;
        }).join('');
        const columns = days.slice(0, dayCount).map((_, index) => `<div class="ss-print-column">${positionMeetings(list.filter(m => m.day === index)).map(m => `<div class="ss-print-class ss-accent-${m.accent} ${m.to - m.from <= 60 ? 'is-short' : ''} ${m.to - m.from <= 30 ? 'is-tiny' : ''}" style="top:calc(${(m.from - start) / span * 100}% + 1px);height:calc(${(m.to - m.from) / span * 100}% - 2px);left:calc(${m.lane / m.lanes * 100}% + 2px);width:calc(${100 / m.lanes}% - 4px)"><small>${range(m)}</small><strong>${escape(m.code)}</strong><span>${escape(m.room || 'Room to be assigned')}</span><span class="ss-print-faculty">${escape(m.faculty || 'Instructor to be assigned')}</span></div>`).join('')}</div>`).join('');
        documentNode.innerHTML = `<header class="ss-print-header"><div class="ss-print-brand"><strong>Akademus <span> / Student Portal</span></strong><span>WEEKLY TIMETABLE</span></div><h1>My class schedule</h1><p>${escape($('ssTerm').value)}</p><p>${escape(context)}</p><div class="ss-print-meta"><span>${new Set(list.map(m => m.code)).size} subjects · ${list.length} meetings · ${hours(totalMinutes(list))} hours / week</span><span>Printed ${escape(date)}</span></div></header><div class="ss-print-grid" style="--ss-days:${dayCount};--ss-print-hour:${124 * 30 / span}mm"><div class="ss-print-head">Time</div>${days.slice(0, dayCount).map(d => `<div class="ss-print-head">${d}</div>`).join('')}<div class="ss-print-times">${labels}</div>${columns}</div><footer class="ss-print-footer"><span>All times are in Philippine Standard Time (GMT+8).</span><span>Akademus SIS · Student schedule</span></footer>`;
        // Dense/short meetings get a readable continuation instead of losing
        // their room or instructor details inside a small timetable cell.
        const dense = days.some((_, index) => positionMeetings(list.filter(m => m.day === index)).some(m => m.lanes > 1));
        if (dense || list.some(m => m.to - m.from <= 60 || m.faculty.length > 32 || m.room.length > 30)) {
            documentNode.querySelector('.ss-print-footer').lastElementChild.textContent = 'Full meeting details continue on the next page.';
            const details = document.createElement('section');
            details.className = 'ss-print-details';
            details.innerHTML = `<h2>Meeting details</h2><p>${escape($('ssTerm').value)} · Complete class information</p><table><thead><tr><th scope="col">Subject</th><th scope="col">Day & time</th><th scope="col">Room</th><th scope="col">Instructor</th></tr></thead><tbody>${[...list].sort((a, b) => a.day - b.day || a.from - b.from).map(m => `<tr><td><strong>${escape(m.code)}</strong>${escape(m.title)}<small>Section ${escape(m.section)}</small></td><td>${days[m.day]}<small>${range(m)}</small></td><td>${escape(m.room || 'To be assigned')}</td><td>${escape(m.faculty || 'To be assigned')}</td></tr>`).join('')}</tbody></table>`;
            documentNode.appendChild(details);
        }
    }
    window.addEventListener('beforeprint', preparePrint);
    $('ssPrint').addEventListener('click', () => { preparePrint(); window.print(); });

    root.addEventListener('click', event => {
        const meeting = event.target.closest('[data-meeting]');
        if (meeting) openDetails(Number(meeting.dataset.meeting), meeting);
    });
    $('ssDetailMeetings').addEventListener('click', event => {
        const button = event.target.closest('[data-detail-meeting]');
        if (button) {
            const id = Number(button.dataset.detailMeeting);
            openDetails(id);
            $('ssDetailMeetings').querySelector(`[data-detail-meeting="${id}"]`).focus();
        }
    });
    $('ssCloseDetails').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) { const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close(); } });
    dialog.addEventListener('close', () => { if (returnFocus?.isConnected && !returnFocus.closest('[hidden]')) returnFocus.focus({ preventScroll: true }); });
    $('ssShowSubject').addEventListener('click', () => {
        $('ssSubject').value = String(selected.classId); day = 'all'; render();
        returnFocus = $('ssSubject'); dialog.close();
        $('ssSubject').focus();
    });
    $('ssViewToggle').addEventListener('click', event => {
        const button = event.target.closest('[data-view]');
        if (button) { view = button.dataset.view; render(); if (viewToggleIndicator) viewToggleIndicator.move(button, true); }
    });
    $('ssDayFilters').addEventListener('click', event => {
        const button = event.target.closest('[data-day]');
        if (button) { day = button.dataset.day; render(); $('ssDayFilters').querySelector(`[data-day="${day}"]`).focus({ preventScroll: true }); if (dayFilterIndicator) dayFilterIndicator.move(button, true); }
    });
    $('ssSubject').addEventListener('change', render);
    $('ssTerm').addEventListener('change', () => { selected = null; day = 'all'; updateSubjects(); render(); });
    $('ssReset').addEventListener('click', () => { day = 'all'; $('ssSubject').value = 'all'; render(); $('ssSubject').focus(); });
    ['ssTermField', 'ssOverview', 'ssViewToggle', 'ssToolbar', 'ssFooter', 'ssPrint'].forEach(id => { $(id).hidden = false; });
    // Same sliding-pill mechanism (and animation) every other segmented
    // control in the app uses — see acInitSegmented in layouts/admin_console.php.
    if (window.acInitSegmented) {
        viewToggleIndicator = window.acInitSegmented($('ssViewToggle'));
        dayFilterIndicator = window.acInitSegmented($('ssDayFilters'));
    }
    updateSubjects(); render();
})();

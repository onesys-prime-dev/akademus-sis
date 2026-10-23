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
    // "My enrolled subjects" opens onto a brief shimmer pass, then the rows
    // arrive on a stagger, so the table reads as built rather than blinked into
    // place. Placed before the `!raw.length` early-exit below so it still runs
    // for a student who has enrolled subjects but no plotted meetings yet.
    // Reduced-motion / no-JS users skip straight to the plain table.
    const enrollments = root.querySelector('.ss-enrollments');
    if (enrollments) {
        const enrollBody = enrollments.querySelector('.ss-enrollment-body');
        const enrollTable = enrollBody?.querySelector('.ss-enrollment-table');
        const enrollRows = [...(enrollBody?.querySelectorAll('.ss-table tbody tr') || [])];
        enrollRows.forEach((row, index) => row.style.setProperty('--row', Math.min(index, 8)));
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const ENROLL_LOAD_MS = 420;
        const skeletonCells = '<span class="ss-enroll-skeleton-bar ss-skeleton-w1"></span><span class="ss-enroll-skeleton-bar ss-skeleton-w2"></span><span class="ss-enroll-skeleton-bar ss-skeleton-w3"></span>';
        let enrollTimer = null, skeleton = null;
        const buildSkeleton = () => {
            skeleton = document.createElement('div');
            skeleton.className = 'ss-enroll-skeleton';
            skeleton.setAttribute('aria-hidden', 'true');
            const head = document.createElement('div');
            head.className = 'ss-enroll-skeleton-row ss-enroll-skeleton-row--head';
            head.innerHTML = skeletonCells + '<span></span>';
            skeleton.appendChild(head);
            enrollRows.forEach(() => {
                const row = document.createElement('div');
                row.className = 'ss-enroll-skeleton-row';
                row.innerHTML = skeletonCells + '<span class="ss-enroll-skeleton-bar ss-enroll-skeleton-bar--action"></span>';
                skeleton.appendChild(row);
            });
            enrollTable.appendChild(skeleton);
        };
        const resetEnrollments = () => { window.clearTimeout(enrollTimer); enrollBody.classList.remove('is-loading', 'is-revealed'); };
        const runEnrollmentReveal = () => {
            if (reduceMotion.matches) return;
            if (!skeleton) buildSkeleton();
            enrollBody.classList.remove('is-revealed');
            enrollBody.classList.add('is-loading');
            window.clearTimeout(enrollTimer);
            enrollTimer = window.setTimeout(() => {
                enrollBody.classList.remove('is-loading');
                enrollBody.classList.add('is-revealed');
            }, ENROLL_LOAD_MS);
        };
        if (enrollBody && enrollTable && enrollRows.length) {
            // `open` still holds the pre-click state here, so this fires before
            // the details actually opens (mouse and keyboard both dispatch click).
            enrollments.querySelector('summary')?.addEventListener('click', () => {
                if (enrollments.open) resetEnrollments(); else runEnrollmentReveal();
            });
            reduceMotion.addEventListener('change', event => { if (event.matches) resetEnrollments(); });
        }
    }
    const configData = JSON.parse(config.textContent);
    const raw = configData.meetings;
    // Shared verbatim with the faculty portal's own schedule page — the print
    // header/footer need to say which portal printed it rather than always
    // claiming "Student Portal".
    const portalLabel = configData.portalLabel || 'Student Portal';
    const showInstructor = configData.showInstructor !== false;
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
    // Two "classes" (different sections) that meet at the exact same
    // day/time/room every week aren't a coincidence — that's a merged class,
    // one physical session plotted once for two sections at once (see
    // ChairpersonController::sync()'s merge handling). Combine them into a
    // single displayed class instead of duplicate, overlapping blocks, with
    // the section/program shown as a short combined list.
    const meetingSignature = c => c.meetings.map(m => `${m.day}|${m.from}|${m.to}|${m.room}`).sort().join(';');
    const mergeGroups = new Map();
    [...classes.values()].forEach(c => {
        const key = `${c.term}|${c.code}|${meetingSignature(c)}`;
        if (!mergeGroups.has(key)) mergeGroups.set(key, []);
        mergeGroups.get(key).push(c);
    });
    const classList = [...mergeGroups.values()].map((group, index) => {
        const primary = group[0];
        // The short program code ("BSIT") reads cleaner than the spelled-out
        // name everywhere this shows up — not just once merging joins two of
        // them together.
        if (group.length === 1) return { ...primary, id: index, program: primary.programCode || primary.program };
        const programs = [...new Set(group.map(c => c.programCode || c.program))];
        const sections = [...new Set(group.map(c => c.section))];
        const years = [...new Set(group.map(c => c.year))];
        return { ...primary, id: index, program: programs.join(', '), section: sections.join(', '), year: years.join(', ') };
    });
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
    let termPicker = null, subjectPicker = null;
    const dialog = $('ssClassDialog');
    const termMeetings = () => meetings.filter(m => m.term === $('ssTerm').value);
    const matchesSubject = m => $('ssSubject').value === 'all' || m.classId === Number($('ssSubject').value);

    // On a phone the seven-day grid can't stay readable without horizontal
    // scrolling, so the calendar shows a single day there and the day chips
    // (one even row, no scroll) switch between days — see render() and the
    // handlers below.
    const narrowMq = window.matchMedia('(max-width: 700px)');
    const isNarrow = () => narrowMq.matches;
    const calendarViewButton = $('ssViewToggle').querySelector('[data-view="calendar"]');
    const syncViewLabel = () => { if (calendarViewButton) calendarViewButton.textContent = isNarrow() ? 'Day' : 'Week'; };
    const narrowDay = list => {
        const today = (new Date().getDay() + 6) % 7; // our columns are Monday-first
        if (list.some(m => m.day === today)) return today;
        return list.map(m => m.day).sort((a, b) => a - b)[0] ?? 0;
    };

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

    // "Weekly timetable" first paint lands on a skeleton of whatever view is
    // current (Agenda or Week), then the classes pop in on a stagger. Later
    // renders — term/day/subject filters, Week↔Agenda — skip the skeleton and
    // just replay the pop, so the board never looks like it reloads mid-use.
    const boardBody = $('ssBoardBody');
    const boardReduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const BOARD_LOAD_MS = 520;
    const boardBar = (variant = '') => `<span class="ss-bsk-bar ${variant}"></span>`;
    let boardTimer = null, boardIntroDone = false, boardSkeleton = null;
    function buildBoardSkeleton() {
        boardSkeleton = document.createElement('div');
        boardSkeleton.className = `ss-board-skeleton ss-board-skeleton--${view}`;
        boardSkeleton.setAttribute('aria-hidden', 'true');
        if (view === 'agenda') {
            const card = `<div class="ss-bsk-card">${boardBar('ss-bsk-bar--a')}${boardBar('ss-bsk-bar--b')}${boardBar('ss-bsk-bar--c')}<span class="ss-bsk-dot"></span></div>`;
            boardSkeleton.innerHTML = Array.from({ length: 3 }, () => `<div class="ss-bsk-day"><div class="ss-bsk-head">${boardBar('ss-bsk-bar--a')}${boardBar('ss-bsk-bar--c')}</div>${card.repeat(3)}</div>`).join('');
        } else {
            const head = `<div class="ss-bsk-head">${boardBar('ss-bsk-bar--a').repeat(8)}</div>`;
            const rail = `<div class="ss-bsk-rail">${boardBar('ss-bsk-bar--a').repeat(6)}</div>`;
            const cols = Array.from({ length: 7 }, (_, d) => `<div class="ss-bsk-col">${d % 2 ? '<span class="ss-bsk-block" style="height:128px"></span><span class="ss-bsk-block" style="height:74px"></span>' : '<span class="ss-bsk-block" style="height:164px"></span><span class="ss-bsk-block" style="height:96px"></span>'}</div>`).join('');
            boardSkeleton.innerHTML = `<div class="ss-bsk-grid">${head}<div class="ss-bsk-grid-body">${rail}${cols}</div></div>`;
        }
        boardBody.appendChild(boardSkeleton);
    }
    // Stagger index per rendered card (capped so a packed week still settles fast).
    function revealBoard() {
        if (!boardBody) return;
        $('ssAgenda').querySelectorAll('.ss-agenda-class').forEach((el, i) => el.style.setProperty('--row', Math.min(i, 12)));
        $('ssCalendar').querySelectorAll('.ss-class').forEach((el, i) => el.style.setProperty('--row', Math.min(i, 12)));
        if (boardIntroDone || boardReduceMotion.matches) return;
        boardIntroDone = true;
        if (!boardSkeleton) buildBoardSkeleton();
        boardBody.classList.remove('is-revealed');
        boardBody.classList.add('is-loading');
        window.clearTimeout(boardTimer);
        boardTimer = window.setTimeout(() => {
            boardBody.classList.remove('is-loading');
            boardBody.classList.add('is-revealed');
        }, BOARD_LOAD_MS);
    }
    boardReduceMotion.addEventListener('change', event => {
        if (event.matches && boardBody) { window.clearTimeout(boardTimer); boardBody.classList.remove('is-loading', 'is-revealed'); }
    });

    function render() {
        const all = termMeetings();
        // A phone calendar can't show a whole week, so if "All days" is selected
        // while it's showing, pin the chips to the single day actually rendered.
        if (view === 'calendar' && isNarrow() && day === 'all') day = String(narrowDay(all.filter(matchesSubject)));
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
        revealBoard();
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
            // On the faculty's own schedule this would just repeat their own
            // name back at them, so that portal leaves it out of its config.
            ...(showInstructor ? [['person', 'Instructor', selected.faculty || 'To be assigned']] : []),
            ['location_on', 'Room', selected.room || 'To be assigned'],
            // Pulled from the class (not the raw meeting) so a merged class
            // — the same session shared by more than one section — shows
            // its combined "A, B" / "BSCS, BSIT" labels here too.
            ['groups', 'Section', c.section],
            ['school', 'Program', c.program],
            ['stairs', 'Year level', c.year],
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
        documentNode.innerHTML = `<header class="ss-print-header"><div class="ss-print-brand"><strong>Akademus <span> / ${escape(portalLabel)}</span></strong><span>WEEKLY TIMETABLE</span></div><h1>My class schedule</h1><p>${escape($('ssTerm').value)}</p><p>${escape(context)}</p><div class="ss-print-meta"><span>${new Set(list.map(m => m.code)).size} subjects · ${list.length} meetings · ${hours(totalMinutes(list))} hours / week</span><span>Printed ${escape(date)}</span></div></header><div class="ss-print-grid" style="--ss-days:${dayCount};--ss-print-hour:${124 * 30 / span}mm"><div class="ss-print-head">Time</div>${days.slice(0, dayCount).map(d => `<div class="ss-print-head">${d}</div>`).join('')}<div class="ss-print-times">${labels}</div>${columns}</div><footer class="ss-print-footer"><span>All times are in Philippine Standard Time (GMT+8).</span><span>Akademus SIS · ${escape(portalLabel)} schedule</span></footer>`;
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
        if (subjectPicker) subjectPicker.sync();
        var focusTarget = subjectPicker ? subjectPicker.trigger : $('ssSubject');
        returnFocus = focusTarget; dialog.close();
        focusTarget.focus();
    });
    $('ssViewToggle').addEventListener('click', event => {
        const button = event.target.closest('[data-view]');
        if (!button) return;
        view = button.dataset.view;
        render();
        if (viewToggleIndicator) viewToggleIndicator.move(button, true);
        if (dayFilterIndicator) dayFilterIndicator.move($('ssDayFilters').querySelector(`[data-day="${day}"]`), true);
    });
    $('ssDayFilters').addEventListener('click', event => {
        const button = event.target.closest('[data-day]');
        if (!button) return;
        day = button.dataset.day;
        // A one-day grid can't represent "all days" on a phone; hand that
        // request to the agenda, which lists the whole week.
        if (day === 'all' && view === 'calendar' && isNarrow()) {
            view = 'agenda';
            const agendaButton = $('ssViewToggle').querySelector('[data-view="agenda"]');
            if (viewToggleIndicator && agendaButton) viewToggleIndicator.move(agendaButton, true);
        }
        render();
        button.focus({ preventScroll: true });
        if (dayFilterIndicator) dayFilterIndicator.move(button, true);
    });
    $('ssSubject').addEventListener('change', render);
    $('ssTerm').addEventListener('change', () => { selected = null; day = 'all'; updateSubjects(); render(); });
    $('ssReset').addEventListener('click', () => {
        day = 'all'; $('ssSubject').value = 'all'; render();
        if (subjectPicker) subjectPicker.sync();
        (subjectPicker ? subjectPicker.trigger : $('ssSubject')).focus();
    });
    ['ssTermField', 'ssOverview', 'ssViewToggle', 'ssToolbar', 'ssFooter', 'ssPrint'].forEach(id => { $(id).hidden = false; });
    syncViewLabel();
    // Crossing the phone/desktop line changes what the calendar can show, so
    // relabel the toggle, re-pin "All days" if needed, and redraw.
    narrowMq.addEventListener('change', () => {
        syncViewLabel();
        render();
        if (viewToggleIndicator) viewToggleIndicator.move($('ssViewToggle').querySelector(`[data-view="${view}"]`), false);
        if (dayFilterIndicator) dayFilterIndicator.move($('ssDayFilters').querySelector(`[data-day="${day}"]`), false);
    });
    // Same sliding-pill mechanism (and animation) every other segmented
    // control in the app uses — see acInitSegmented in layouts/admin_console.php.
    if (window.acInitSegmented) {
        viewToggleIndicator = window.acInitSegmented($('ssViewToggle'));
        dayFilterIndicator = window.acInitSegmented($('ssDayFilters'));
    }
    if (window.enhanceSelect) {
        termPicker = window.enhanceSelect($('ssTerm'));
        subjectPicker = window.enhanceSelect($('ssSubject'));
    }
    updateSubjects(); render();
    // The server-rendered aria-pressed reflects the desktop default (Week),
    // while the initial view can differ on a phone — put both pills on the
    // control that's actually selected before the first paint.
    if (viewToggleIndicator) viewToggleIndicator.move($('ssViewToggle').querySelector(`[data-view="${view}"]`), false);
    if (dayFilterIndicator) dayFilterIndicator.move($('ssDayFilters').querySelector(`[data-day="${day}"]`), false);
})();

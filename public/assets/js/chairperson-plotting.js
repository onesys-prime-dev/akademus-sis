// On-page error log: surfaces JS errors and [schedule] diagnostics without
// needing DevTools. Stays hidden until something actually goes wrong.
(function () {
    const list = document.getElementById('debugList');
    const panel = document.getElementById('debugPanel');
    const toggle = document.getElementById('debugToggle');
    const count = document.getElementById('debugCount');
    let total = 0;

    function logEntry(kind, message) {
        total++;
        count.textContent = total;
        toggle.hidden = false;

        const row = document.createElement('div');
        row.className = 'debug-row';
        const label = document.createElement('span');
        label.className = 'debug-kind';
        label.textContent = `[${kind} ${new Date().toLocaleTimeString()}] `;
        row.append(label, document.createTextNode(message));
        list.prepend(row);
    }

    toggle.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', String(!panel.hidden));
    });

    document.getElementById('debugClear').addEventListener('click', () => {
        list.replaceChildren();
        total = 0;
        count.textContent = '0';
        toggle.hidden = true;
        panel.hidden = true;
    });

    window.addEventListener('error', e => logEntry('JS error', `${e.message} — ${e.filename}:${e.lineno}:${e.colno}`));
    window.addEventListener('unhandledrejection', e => logEntry('unhandled promise', String((e.reason && e.reason.stack) || e.reason)));

    const original = console.error.bind(console);
    console.error = function (...args) {
        original(...args);
        logEntry('console.error', args.map(a => {
            try { return typeof a === 'string' ? a : JSON.stringify(a); } catch (_) { return String(a); }
        }).join(' '));
    };
})();

/**
 * Schedule plotting workspace (chairperson).
 *
 * Server data arrives through window.PLOTTING_BOOT (see
 * app/Views/chairperson/schedule_plotting.php). Everything the chairperson
 * changes is autosaved to chairperson/schedule/sync; the server re-validates
 * every rule, so this file only mirrors them to explain problems earlier.
 */
const BOOT = window.PLOTTING_BOOT;

function toMinutes(hhmm) {
    const [h, m] = hhmm.split(':').map(Number);
    return h * 60 + m;
}

const CONFIG = Object.assign({
    rowHeight: BOOT.rowHeight,
    gridStartMin: toMinutes(BOOT.gridStart),
    gridEndMin: toMinutes(BOOT.gridEnd),
}, BOOT.urls);
CONFIG.totalRows = (CONFIG.gridEndMin - CONFIG.gridStartMin) / 30;

const SUBJECTS = BOOT.subjects;
// SUBJECTS is already scoped server-side to subjects THIS department owns
// (see ChairpersonController::index()) — a block plotted for any other
// code is either this section's own curriculum-borrowed class or another
// department's, and must stay read-only here: only the owning department
// may move/re-room/reassign/remove it. See renderBlockView().
const OWNED_CODES = new Set(SUBJECTS.map(s => s.code));
// Some rooms are stored as "4th Floor" and the server appends " Floor" again.
const ROOMS    = BOOT.rooms.map(r => Object.assign({}, r, { floor: String(r.floor).replace(/(\s*floor)+$/i, '') + ' Floor' }));
const WEEK     = BOOT.week;
const CONTEXT  = BOOT.context;
let OTHERS          = BOOT.others;
let blocks          = BOOT.blocks;
let ASSIGNMENTS     = BOOT.assignments;
let APPROVALS       = BOOT.swapApprovals;
let CHANGE_REQUESTS = BOOT.changeRequests;

const SUBJECT_BY_CODE = new Map(SUBJECTS.map(s => [s.code, s]));
const ROOM_BY_NAME    = new Map(ROOMS.map(r => [r.name, r]));

/** A subject can meet at most this many times a week (matches the server-side cap). */
const MAX_MEETINGS = 3;

// Autosave state: every change syncs to the server shortly after it is made.
let saveTimer = null;   // debounce handle
let saving = false;     // a sync request is in flight
let saveQueued = false; // a change happened while saving
let stateVersion = '';  // long-poll change signature

const $ = id => document.getElementById(id);

/** Tiny element builder used by the workspace renderers. */
function mk(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

// ---------- time helpers ----------
function rowToHHMM(row) {
    const mins = CONFIG.gridStartMin + row * 30;
    return String(Math.floor(mins / 60)).padStart(2, '0') + ':' + String(mins % 60).padStart(2, '0');
}

function rowTo12h(row) {
    return minutesTo12h(CONFIG.gridStartMin + row * 30);
}

function minutesTo12h(mins) {
    let h = Math.floor(mins / 60);
    const m = mins % 60;
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return h + ':' + String(m).padStart(2, '0') + ' ' + ampm;
}

/** "8:00–9:30 AM", or "11:00 AM – 1:00 PM" when the range crosses noon. */
function rangeLabel(startMin, endMin) {
    const a = minutesTo12h(startMin);
    const b = minutesTo12h(endMin);
    const [aTime, aPeriod] = a.split(' ');
    return aPeriod === b.split(' ')[1] ? `${aTime}–${b}` : `${a} – ${b}`;
}

const blockRange = b => rangeLabel(toMinutes(b.start), toMinutes(b.end));
const rowRange   = (startRow, endRow) => rangeLabel(CONFIG.gridStartMin + startRow * 30, CONFIG.gridStartMin + endRow * 30);

function blockStartRow(b) { return (toMinutes(b.start) - CONFIG.gridStartMin) / 30; }
function blockEndRow(b)   { return (toMinutes(b.end)   - CONFIG.gridStartMin) / 30; }

function rangesOverlap(aStart, aEnd, bStart, bEnd) {
    return aStart < bEnd && bStart < aEnd;
}

const dayName  = day => (WEEK[day] || {}).day || '';
const dayShort = day => dayName(day).slice(0, 3);

function round1(n) { return Math.round(n * 10) / 10; }

function fmtHours(n) {
    const r = round1(n);
    return r === 1 ? '1 hr' : r + ' hrs';
}

/** Compact duration badge for a block ("30m", "1h", "1.5h") — fits a single 30-minute grid row. */
function fmtHoursShort(n) {
    const mins = Math.round(n * 60);
    if (mins < 60) return `${mins}m`;
    const hrs = mins / 60;
    return (Number.isInteger(hrs) ? hrs : hrs.toFixed(1)) + 'h';
}

function shortYear(yearLevel) {
    return (yearLevel || '').replace(' Year', ' Yr');
}

/** Shows or hides a section with a short fade instead of popping. Opening is animated in CSS. */
function setShown(el, show) {
    if (show) {
        el.classList.remove('is-closing');
        el.hidden = false;
        return;
    }
    if (el.hidden || el.classList.contains('is-closing')) return;
    el.classList.add('is-closing');
    setTimeout(() => {
        el.hidden = true;
        el.classList.remove('is-closing');
    }, 150);
}

function plural(count, one, many) {
    return `${count} ${count === 1 ? one : (many || one + 's')}`;
}

function initials(name) {
    return String(name || '?').split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
}

// ---------- grid construction ----------
const timeCol = $('timeCol');
for (let row = 0; row < CONFIG.totalRows; row++) {
    const cell = mk('div', 'time slot');
    const mins = CONFIG.gridStartMin + row * 30;
    if (mins % 60 === 0) {
        cell.classList.add('is-hour');
        cell.appendChild(mk('span', '', rowTo12h(row).replace(':00', '')));
    }
    timeCol.appendChild(cell);
}

// Cached once: the day columns are never replaced, only their block children.
const DAY_COLS = new Map();
document.querySelectorAll('.day-col').forEach(col => {
    for (let row = 0; row < CONFIG.totalRows; row++) {
        const slot = mk('div', 'slot');
        if ((CONFIG.gridStartMin + row * 30) % 60 === 0) slot.classList.add('is-hour');
        col.appendChild(slot);
    }
    DAY_COLS.set(Number(col.dataset.day), col);
});

if (DAY_COLS.size === 0) {
    console.error('[schedule] no .day-col elements found in the DOM — the weekly grid did not render.');
}

// ---------- confirmation dialog ----------
// Reserved for destructive actions and deliberate overrides. Everything else
// happens inline in the side panel.
const confirmModal = $('confirmModal');
let confirmResolve = null;
let confirmLastFocus = null;

function confirmDialog({ title, message, confirmLabel = 'Confirm', danger = false, reason = false }) {
    $('confirmTitle').textContent = title;
    $('confirmMessage').textContent = message;
    const reasonWrap = $('confirmReasonWrap');
    const reasonInput = $('confirmReason');
    reasonWrap.hidden = !reason;
    reasonInput.value = '';

    const okBtn = $('confirmOk');
    okBtn.textContent = confirmLabel;
    okBtn.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');

    confirmLastFocus = document.activeElement;
    confirmModal.classList.add('open');
    requestAnimationFrame(() => (reason ? reasonInput : $('confirmCancel')).focus());
    return new Promise(resolve => { confirmResolve = resolve; });
}

function settleConfirm(result) {
    if (confirmResolve === null) return;
    confirmModal.classList.remove('open');
    const resolve = confirmResolve;
    confirmResolve = null;
    if (confirmLastFocus && confirmLastFocus.isConnected) confirmLastFocus.focus();
    confirmLastFocus = null;
    resolve(result && !$('confirmReasonWrap').hidden
        ? { confirmed: true, reason: $('confirmReason').value.trim() }
        : result);
}

$('confirmOk').addEventListener('click', () => settleConfirm(true));
$('confirmCancel').addEventListener('click', () => settleConfirm(false));
confirmModal.addEventListener('click', e => { if (e.target === confirmModal) settleConfirm(false); });

// Keep Tab inside whichever modal is open.
document.addEventListener('keydown', e => {
    if (e.key !== 'Tab') return;

    const open = Array.from(document.querySelectorAll('.modal-backdrop.open'));
    if (!open.length) return;
    const modal = open.find(m => m.classList.contains('confirm-top')) || open[open.length - 1];

    const focusables = Array.from(modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
        .filter(n => n.offsetParent !== null);
    if (!focusables.length) return;

    const first = focusables[0];
    const last = focusables[focusables.length - 1];

    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
});
        // ---------- rendering ----------
        // Assign overlapping blocks to side-by-side lanes (interval
        // partitioning), so no block ever hides behind another.
        function assignLanes(items) {
            const sorted = items.slice().sort((a, b) => a.startRow - b.startRow || a.endRow - b.endRow);

            const clusters = [];
            let cluster = [];
            let clusterEnd = -1;

            sorted.forEach(item => {
                if (cluster.length && item.startRow >= clusterEnd) {
                    clusters.push(cluster);
                    cluster = [];
                    clusterEnd = -1;
                }
                cluster.push(item);
                clusterEnd = Math.max(clusterEnd, item.endRow);
            });
            if (cluster.length) clusters.push(cluster);

            clusters.forEach(group => {
                const laneEnds = [];
                group.forEach(item => {
                    let lane = laneEnds.findIndex(end => end <= item.startRow);
                    if (lane === -1) { lane = laneEnds.length; laneEnds.push(0); }
                    laneEnds[lane] = item.endRow;
                    item.lane = lane;
                });
                group.forEach(item => { item.lanes = laneEnds.length; });
            });
        }

        // Lay out one day column. Your own classes are laned only against
        // each other; other sections' classes (ghosts) are laned separately
        // and squeezed into a slim strip beside them, so a busy timetable
        // elsewhere never crushes what you are actually plotting. Where a
        // time cluster holds only one kind, it uses the full column width.
        function layoutColumn(items) {
            const sorted = items.slice().sort((a, b) => a.startRow - b.startRow || a.endRow - b.endRow);
            const clusters = [];
            let cluster = [];
            let clusterEnd = -1;

            sorted.forEach(item => {
                if (cluster.length && item.startRow >= clusterEnd) {
                    clusters.push(cluster);
                    cluster = [];
                    clusterEnd = -1;
                }
                cluster.push(item);
                clusterEnd = Math.max(clusterEnd, item.endRow);
            });
            if (cluster.length) clusters.push(cluster);

            clusters.forEach(group => {
                const own = group.filter(i => i.own);
                const ghosts = group.filter(i => !i.own);
                const split = own.length > 0 && ghosts.length > 0;

                assignLanes(own);
                assignLanes(ghosts);

                // Other sections' classes shrink to a slim strip beside yours
                // (details on hover) instead of squeezing what is being plotted.
                const ghostLanes = ghosts.length ? ghosts[0].lanes : 0;
                const zone = { split, strip: split ? Math.min(ghostLanes * 8, 24) : 0 };
                own.forEach(i => { i.zone = zone; });
                ghosts.forEach(i => { i.zone = zone; });
            });
        }

// ---------- faculty data ----------
// One shared fetch feeds the faculty picker and the availability checks on
// plotted classes. It is short-lived because loads change as classes move.
const FACULTY_TTL = 60000;
let facultyCache = null;   // { at, data }
let facultyRequest = null;

function loadFaculty(force = false) {
    if (!force && facultyCache && Date.now() - facultyCache.at < FACULTY_TTL) return Promise.resolve(facultyCache.data);
    if (facultyRequest) return facultyRequest;

    const url = CONFIG.facultyPanelUrl + '?for_academic_year=' + encodeURIComponent(CONTEXT.academic_year);
    facultyRequest = fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
        .then(response => {
            if (!response.ok) throw new Error('Faculty request failed');
            return response.json();
        })
        .then(data => {
            facultyCache = { at: Date.now(), data };
            return data;
        })
        .finally(() => { facultyRequest = null; });

    return facultyRequest;
}

function cachedFaculty(id) {
    if (!facultyCache || id == null) return null;
    return (facultyCache.data.faculty || []).find(f => Number(f.id) === Number(id)) || null;
}

/** How a faculty member's submitted availability relates to one day/time. */
function availabilityFit(f, day, start, end) {
    const a = (f.availability || []).find(x => Number(x.day_of_week) === day);
    if (!a) return { fit: 'none' };
    if (!a.is_available || a.is_available == 0) return { fit: 'off' };

    const from = a.start_time ? a.start_time.slice(0, 5) : null;
    const to = a.end_time ? a.end_time.slice(0, 5) : null;
    if ((from && from > start) || (to && to < end)) return { fit: 'outside', from, to };
    return { fit: 'ok', from, to };
}

function availabilityText(name, day, result) {
    if (result.fit === 'none') return `${name} has not submitted availability for ${dayName(day)}.`;
    if (result.fit === 'off') return `${name} marked ${dayName(day)} as unavailable.`;
    if (result.fit === 'outside') {
        const window = result.from && result.to ? rangeLabel(toMinutes(result.from), toMinutes(result.to)) : 'other hours';
        return `${name} is only available ${window} on ${dayName(day)}.`;
    }
    return '';
}

// ---------- hours and status ----------
function blockKey(b) { return `${b.code}|${b.day}|${b.start}`; }
function blockHours(b) { return (toMinutes(b.end) - toMinutes(b.start)) / 60; }

/** Hours plotted against a subject's weekly target, plus who teaches it. */
function subjectStats(code) {
    const subject = SUBJECT_BY_CODE.get(code) || null;
    const list = blocks.filter(b => b.code === code);
    const plotted = list.reduce((total, b) => total + blockHours(b), 0);
    const weekly = subject ? (parseFloat(subject.weekly_hours) || 0) : 0;
    const faculty = (list.find(b => b.prof) || {}).prof || null;
    const facultyId = (list.find(b => b.faculty_id) || {}).faculty_id || null;

    return {
        subject,
        meetings: list,
        count: list.length,
        plotted,
        weekly,
        remaining: weekly > 0 ? Math.max(0, weekly - plotted) : null,
        complete: weekly > 0 && plotted >= weekly - 1e-9,
        over: weekly > 0 && plotted > weekly + 1e-9,
        faculty,
        facultyId,
        staffed: list.length > 0 && list.every(b => b.prof),
    };
}

function assignedFaculty(code) {
    const b = blocks.find(x => x.code === code && x.faculty_id);
    return b ? { id: b.faculty_id, name: b.prof } : null;
}

function sectionTotals() {
    let weekly = 0;
    let plotted = 0;
    let complete = 0;

    SUBJECTS.forEach(s => {
        const st = subjectStats(s.code);
        weekly += st.weekly;
        plotted += st.weekly > 0 ? Math.min(st.plotted, st.weekly) : 0;
        if (st.complete) complete++;
    });

    return { weekly, plotted, complete, subjects: SUBJECTS.length };
}

// ---------- issues ----------
// Everything that deserves attention on a plotted class. Hard problems the
// server would reject are 'error'; everything a chairperson may knowingly
// keep is 'warn'.
let issueMap = new Map();

function blockIssues(b) {
    const issues = [];
    const subject = SUBJECT_BY_CODE.get(b.code);
    const day = Number(b.day);
    const startRow = blockStartRow(b);
    const endRow = blockEndRow(b);
    const room = ROOM_BY_NAME.get(b.room) || null;
    const others = blocks.filter(o => o !== b).concat(OTHERS);
    const clashing = others.filter(o => Number(o.day) === day && rangesOverlap(startRow, endRow, blockStartRow(o), blockEndRow(o)));

    const sameSection = clashing.find(o => blocks.includes(o));
    if (sameSection) {
        issues.push({ level: 'error', title: 'Section conflict', detail: `${CONTEXT.section} also has ${sameSection.code} at ${blockRange(sameSection)}.` });
    }

    const roomClash = clashing.find(o => o.room === b.room);
    if (roomClash) {
        issues.push({ level: 'error', title: 'Room conflict', detail: `${b.room} is also used by ${roomClash.code} · ${roomClash.section || CONTEXT.section}, ${blockRange(roomClash)}.` });
    }

    const facultyClash = b.prof && clashing.find(o => o.prof === b.prof);
    if (facultyClash) {
        issues.push({ level: 'error', title: 'Faculty conflict', detail: `${b.prof} also teaches ${facultyClash.code} · ${facultyClash.section || CONTEXT.section}, ${blockRange(facultyClash)}.` });
    }

    if (!b.prof) {
        issues.push({ level: 'warn', title: 'No faculty assigned', detail: 'Faculty cannot see or claim this class until someone is assigned.' });
    } else {
        const f = cachedFaculty(b.faculty_id);
        if (f) {
            const fit = availabilityFit(f, day, b.start, b.end);
            if (fit.fit === 'off' || fit.fit === 'outside') {
                issues.push({ level: 'warn', title: 'Outside faculty availability', detail: availabilityText(b.prof, day, fit) });
            }
        }
    }

    if (!room) {
        issues.push({ level: 'warn', title: 'Room no longer available', detail: b.room + ' was removed or deactivated. Choose another room for this class.' });
    }

    if (subject && subject.is_lab && room && !room.is_laboratory) {
        issues.push({ level: 'warn', title: 'Room type', detail: `${b.code} is a laboratory class, but ${b.room} is not configured as a laboratory.` });
    }

    const headcount = aiPlan.headcount || 0;
    if (room && room.capacity > 0 && headcount > room.capacity) {
        issues.push({ level: 'warn', title: 'Room capacity', detail: `${b.room} seats ${room.capacity}, but ${CONTEXT.section} has ${aiPlan.headcount_known ? '' : 'about '}${headcount} students.` });
    }

    const rules = aiPlan.rules;
    if (rules) {
        if ((rules.earliest_start && b.start < rules.earliest_start) || (rules.latest_end && b.end > rules.latest_end)) {
            issues.push({ level: 'warn', title: 'Outside operating hours', detail: `Department hours are ${rangeLabel(toMinutes(rules.earliest_start), toMinutes(rules.latest_end))}.` });
        }
        if (rules.lunch_enabled && rangesOverlap(toMinutes(b.start), toMinutes(b.end), toMinutes(rules.lunch_start), toMinutes(rules.lunch_end))) {
            issues.push({ level: 'warn', title: 'Overlaps lunch break', detail: `Lunch is kept free ${rangeLabel(toMinutes(rules.lunch_start), toMinutes(rules.lunch_end))}.` });
        }
        if (day === 5 && !rules.allow_saturday) {
            issues.push({ level: 'warn', title: 'Saturday class', detail: 'Saturday classes are switched off in this department\'s scheduling rules.' });
        }
    }

    return issues;
}

/** Every open issue for the section, sorted with hard problems first. */
function collectIssues() {
    const list = [];

    blocks.forEach(b => {
        (issueMap.get(blockKey(b)) || []).forEach(issue => list.push(Object.assign({ block: b }, issue)));
    });

    SUBJECTS.forEach(s => {
        const st = subjectStats(s.code);
        if (st.over) {
            list.push({ level: 'warn', title: 'More hours than required', detail: `${s.code} has ${fmtHours(st.plotted)} plotted against ${fmtHours(st.weekly)} required.`, code: s.code });
        }
    });

    return list.sort((a, b) => (a.level === b.level ? 0 : a.level === 'error' ? -1 : 1));
}

// ---------- block rendering ----------
/**
 * Two meetings of the same subject, in the same room, back-to-back with no
 * gap (e.g. a subject split into two 1.5h blocks that landed consecutively
 * on one day) read as one awkward double card. Mark them as joined so
 * positionBlock() can close the seam between them and the later one can
 * fold into a single continuous card instead of repeating its header.
 */
function markJoinedMeetings(items) {
    const own = items.filter(i => i.own);

    own.forEach(item => {
        item.joinNext = own.find(other => other !== item
            && other.lane === item.lane
            && other.startRow === item.endRow
            && other.b.code === item.b.code
            && other.b.room === item.b.room) || null;
    });
    own.forEach(item => { item.joinPrev = own.find(other => other.joinNext === item) || null; });
}

function positionBlock(el, item) {
    const zone = item.zone || { split: false, strip: 0 };
    const rows = item.endRow - item.startRow;

    const topPos = item.startRow * CONFIG.rowHeight + (item.joinPrev ? 0 : 1);
    const bottomPos = item.endRow * CONFIG.rowHeight - (item.joinNext ? 0 : 1);
    el.style.top = topPos + 'px';
    el.style.height = (bottomPos - topPos) + 'px';

    if (item.joinPrev) el.classList.add('is-join-top');
    if (item.joinNext) {
        el.classList.add('is-join-bottom');
        let last = item;
        while (last.joinNext) last = last.joinNext;
        const timeEl = el.querySelector('.b-time');
        if (timeEl) timeEl.textContent = blockRange({ start: item.b.start, end: last.b.end });
    }

    if (zone.split && !item.own) {
        const laneW = zone.strip / item.lanes;
        el.style.left = `calc(100% - ${zone.strip - item.lane * laneW}px)`;
        el.style.width = (laneW - 1) + 'px';
        el.classList.add('is-strip');
    } else if (zone.split) {
        const usable = `(100% - ${zone.strip + 5}px)`;
        el.style.left = `calc(${usable} * ${item.lane / item.lanes} + 2px)`;
        el.style.width = `calc(${usable} / ${item.lanes} - 2px)`;
    } else {
        el.style.left = `calc(${item.lane / item.lanes * 100}% + 2px)`;
        el.style.width = `calc(${100 / item.lanes}% - 4px)`;
    }

    // Several other-section classes at the same time: keep them legible only
    // while there is room, otherwise fall back to a bare strip.
    if (!item.own && !zone.split && item.lanes === 2) el.classList.add('is-compact');
    if (!item.own && !zone.split && item.lanes >= 3) el.classList.add('is-strip');
    if (rows <= 2) el.classList.add('is-short');
    if (rows <= 1) el.classList.add('is-tiny');
}

function ghostEl(b) {
    const el = mk('div', 'block is-other');
    el.title = `${b.code} · ${b.section}${b.year_level ? ' · ' + b.year_level : ''} · ${b.room}${b.prof ? ' · ' + b.prof : ''} · ${b.time_label}`;
    el.setAttribute('aria-hidden', 'true');
    el.append(
        mk('div', 'b-section', b.section + (b.year_level ? ' · ' + shortYear(b.year_level) : '')),
        mk('div', 'b-code', b.code),
        mk('div', 'b-line', b.room)
    );
    return el;
}

function ownEl(b) {
    const subject = SUBJECT_BY_CODE.get(b.code);
    const key = blockKey(b);
    const issues = issueMap.get(key) || [];
    const worst = issues.some(i => i.level === 'error') ? 'error' : issues.length ? 'warn' : '';
    const isLab = !!(subject && subject.is_lab);
    const isOwned = OWNED_CODES.has(b.code);

    const el = mk('div', 'block' + (isLab ? ' is-lab' : '') + (isOwned ? '' : ' is-readonly'));
    el.dataset.key = key;
    el.tabIndex = 0;
    el.setAttribute('role', 'button');
    el.setAttribute('aria-pressed', String(ui.blockKey === key));
    el.setAttribute('aria-label', `${b.code}, ${isLab ? 'laboratory' : 'lecture'}, ${dayName(b.day)} ${blockRange(b)}, ${b.room}, ${b.prof || 'no faculty assigned'}`
        + (issues.length ? `, ${plural(issues.length, 'issue')}` : '')
        + (isOwned ? '' : ', managed by another department'));
    el.draggable = isOwned;
    if (ui.blockKey === key) el.classList.add('is-selected');
    if (worst) el.dataset.issue = worst;
    if (ui.subjectCode && ui.subjectCode !== b.code) el.classList.add('is-dim');

    const head = mk('div', 'b-head');
    head.appendChild(mk('span', 'b-code', b.code));
    if (isLab) head.appendChild(mk('span', 'b-type', 'Lab'));
    // Shown even on a 30-min "tiny" block (where the fuller b-line rows are
    // hidden for space) so the plotted length is always visible at a glance.
    head.appendChild(mk('span', 'b-dur', fmtHoursShort(blockHours(b))));
    if (worst) {
        const flag = mk('span', 'b-flag', '!');
        flag.setAttribute('aria-hidden', 'true');
        head.appendChild(flag);
    }

    el.appendChild(head);
    el.appendChild(b.prof ? mk('div', 'b-line b-prof', b.prof) : mk('div', 'b-line b-noprof', 'No faculty'));
    el.appendChild(mk('div', 'b-line b-room', b.room));
    el.appendChild(mk('div', 'b-line b-time', blockRange(b)));

    // A 30-min "tiny" block is only ~32px tall — nowhere near enough room for
    // a legible, tappable +/- control next to the code/duration text. So the
    // resize row lives at normal (sidebar) button size, in the DOM for every
    // owned block but shown only when selected (CSS .is-selected), and the
    // block itself grows to fit it — safe because blocks are absolutely
    // positioned, so a taller selected tile just overlaps the row beneath it
    // instead of pushing the grid's layout around.
    if (isOwned) {
        const resize = mk('div', 'b-resize');
        resize.appendChild(mk('span', 'b-resize__label', 'Length'));
        resize.appendChild(resizeStepper(b, subjectStats(b.code), 'stepper stepper--inline'));
        el.appendChild(resize);
    }
    el.title = `${b.code} · ${b.title}\n${dayName(b.day)} ${blockRange(b)} · ${b.room}${b.prof ? ' · ' + b.prof : ''}`
        + issues.map(i => `\n${i.title}`).join('');

    el.addEventListener('click', e => {
        e.stopPropagation();
        selectBlock(key);
    });
    el.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            selectBlock(key);
        } else if (e.key === 'Delete' || e.key === 'Backspace') {
            e.preventDefault();
            removeBlock(key);
        }
    });
    if (isOwned) attachBlockDrag(el, b);

    return el;
}

/** Dashed stand-in for a class that is being placed but not yet committed. */
function pendingEl(place) {
    const el = mk('div', 'block is-pending' + (place.subject.is_lab ? ' is-lab' : ''));
    el.setAttribute('aria-hidden', 'true');
    el.append(
        mk('div', 'b-head', place.subject.code),
        mk('div', 'b-line', place.roomName || 'Choose a room'),
        mk('div', 'b-line', place.faculty === undefined ? '' : (place.faculty ? place.faculty.name : 'No faculty'))
    );
    return el;
}

let renderDeferred = false;

function renderBlocks(opts = {}) {
    // Rebuilding the grid mid-drag would drop the element being dragged.
    if (drag) {
        renderDeferred = true;
        return;
    }
    renderDeferred = false;

    const active = document.activeElement;
    const focusKey = active && active.classList && active.classList.contains('block') ? active.dataset.key : null;

    try {
        document.querySelectorAll('.day-col .block').forEach(el => el.remove());
        issueMap = new Map();
        blocks.forEach(b => issueMap.set(blockKey(b), blockIssues(b)));

        let placed = 0;

        for (const [day, col] of DAY_COLS) {
            const items = [];

            blocks.forEach(b => {
                if (Number(b.day) !== day) return;
                items.push({ own: true, startRow: blockStartRow(b), endRow: blockEndRow(b), el: ownEl(b), b });
            });

            OTHERS.forEach(b => {
                if (Number(b.day) !== day) return;
                items.push({ own: false, startRow: blockStartRow(b), endRow: blockEndRow(b), el: ghostEl(b) });
            });

            layoutColumn(items);
            markJoinedMeetings(items);

            items.forEach(item => {
                positionBlock(item.el, item);
                col.appendChild(item.el);
                placed++;
            });
        }

        if (ui.place && ui.place.kind === 'new' && DAY_COLS.get(ui.place.day)) {
            const el = pendingEl(ui.place);
            positionBlock(el, { own: true, startRow: ui.place.startRow, endRow: ui.place.endRow, lane: 0, lanes: 1 });
            DAY_COLS.get(ui.place.day).appendChild(el);
        }

        if (blocks.length + OTHERS.length > 0 && placed === 0) {
            console.error('[schedule] blocks exist in memory but none were placed on the grid.', { blocks, OTHERS, days: [...DAY_COLS.keys()] });
        }
    } catch (err) {
        console.error('[schedule] renderBlocks failed:', err);
        showToast('Something went wrong drawing the schedule. Check the console and reload.', 'error');
    }

    if (focusKey) {
        const again = document.querySelector(`.day-col .block[data-key="${CSS.escape(focusKey)}"]`);
        if (again) again.focus({ preventScroll: true });
    }

    syncSelection();
    renderQueue();
    updateBoardSummary();
    updateNoProfBanner();
    renderRoomOverview(); // keep the overview live with every change
    refreshInspector();
    if (!opts.skipSuggestions) queueSuggestions();
}

/** Draws attention to an existing class, e.g. the one a drop collided with. */
function flashBlock(target) {
    const list = blocks.includes(target) ? blocks : OTHERS;
    if (!list.includes(target)) return;

    const col = DAY_COLS.get(Number(target.day));
    if (!col) return;

    const key = blocks.includes(target) ? blockKey(target) : null;
    const el = key ? col.querySelector(`.block[data-key="${CSS.escape(key)}"]`) : null;
    const node = el || Array.from(col.querySelectorAll('.block.is-other')).find(n => n.title.startsWith(target.code + ' ·') && n.title.includes(target.time_label));
    if (!node) return;

    node.classList.remove('is-flash');
    void node.offsetWidth;
    node.classList.add('is-flash');
    node.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

function updateBoardSummary() {
    const totals = sectionTotals();
    const issues = collectIssues();
    const errors = issues.filter(i => i.level === 'error').length;

    $('boardSummary').textContent = blocks.length === 0
        ? 'Nothing plotted yet'
        : `${plural(blocks.length, 'class', 'classes')} · ${round1(totals.plotted)} of ${fmtHours(totals.weekly)} plotted`;

    const btn = $('issuesBtn');
    btn.hidden = issues.length === 0;
    if (issues.length) {
        btn.textContent = `${plural(issues.length, 'issue')}`;
        btn.dataset.level = errors ? 'error' : 'warn';
        btn.setAttribute('aria-label', `${plural(issues.length, 'scheduling issue')} need attention. Open the list.`);
    }

    const tab = $('tabInspectorCount');
    tab.hidden = issues.length === 0;
    tab.textContent = issues.length;
}

// Plotted subjects with no faculty never reach a faculty member's
// "My Assignments", so call them out and offer a one-click fix — but only
// for subjects this department owns; another department's unstaffed class
// isn't something this chairperson can fix from here.
function subjectsWithoutProf() {
    const codes = [];
    blocks.forEach(b => { if (!b.prof && OWNED_CODES.has(b.code) && !codes.includes(b.code)) codes.push(b.code); });
    return codes.filter(code => !blocks.some(b => b.code === code && b.prof));
}

function updateNoProfBanner() {
    const banner = $('noProfBanner');
    const codes = subjectsWithoutProf();
    banner.hidden = codes.length === 0;
    if (codes.length === 0) return;
    $('noProfText').textContent = codes.length === 1
        ? `${codes[0]} has no faculty, so no faculty member can see or take it.`
        : `${codes.length} plotted classes have no faculty (${codes.join(', ')}), so no faculty member can see or take them.`;
}

$('noProfAuto').addEventListener('click', () => {
    const codes = subjectsWithoutProf();
    const picks = (aiPlan.improvements || []).filter(i => i.type === 'assign_faculty' && i.faculty && codes.includes(i.code));

    if (picks.length === 0) {
        showToast('No faculty could be matched automatically. Select a class and choose one yourself.', 'error');
        return;
    }

    pushUndo(`assigned faculty to ${plural(picks.length, 'class', 'classes')}`);
    clearPreview();
    picks.forEach(item => {
        blocks.filter(x => x.code === item.code && !x.prof).forEach(x => {
            x.prof = item.faculty.name;
            x.faculty_id = item.faculty.id;
        });
    });
    renderBlocks();
    scheduleSave();

    const left = subjectsWithoutProf();
    showToast(left.length
        ? `Assigned ${picks.length}. ${left.join(', ')} still need faculty.`
        : `Faculty assigned to ${plural(picks.length, 'class', 'classes')}.`, left.length ? 'error' : 'success');
});
        // ---------- AI scheduling assistant ----------
        // The server ranks conflict-free placements for whatever is still
        // unplotted; applying one just adds a block and autosaves, so the
        // normal sync validation still has the last word.
        let aiTimer = null;
        let aiPlan = { suggestions: [], unplaceable: [] };
        let aiRequest = 0;

        function queueSuggestions() {
            clearTimeout(aiTimer);
            aiTimer = setTimeout(loadSuggestions, 700);
        }

        async function loadSuggestions() {
            // Wait for any pending save so the server sees the current grid.
            if (isSaveBusy()) {
                queueSuggestions();
                return;
            }

            const ticket = ++aiRequest;
            const status = document.getElementById('aiStatus');

            try {
                const response = await fetch(`${CONFIG.suggestUrl}?${new URLSearchParams(CONTEXT).toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();

                if (ticket !== aiRequest) return; // a newer request superseded this one
                if (!response.ok || !data.ok) throw new Error('bad response');

                const previousBasis = JSON.stringify([aiPlan.rules, aiPlan.headcount, aiPlan.headcount_known]);
                aiPlan = data;
                renderLunchBand(data.rules);
                if (rulesForm.hidden) fillRulesForm(data.rules);
                renderSuggestions();

                // Department rules and headcount feed the checks on plotted
                // classes, so refresh those once when they actually change.
                if (previousBasis !== JSON.stringify([aiPlan.rules, aiPlan.headcount, aiPlan.headcount_known])) {
                    renderBlocks({ skipSuggestions: true });
                }
            } catch (err) {
                status.textContent = 'The assistant could not analyze this schedule right now.';
                document.getElementById('aiFillAll').hidden = true;
                document.getElementById('aiSuggestions').replaceChildren();
            }
        }

        function aiEl(tag, className, text) {
            const el = document.createElement(tag);
            if (className) el.className = className;
            if (text !== undefined) el.textContent = text;
            return el;
        }

        // ----- hover preview -----
        // Hovering a suggestion ghosts it onto the grid: indigo where it
        // would go, amber where the class is now (for a move).
        function clearPreview() {
            document.querySelectorAll('.ai-preview').forEach(el => el.remove());
        }

        function showPreview(items) {
            clearPreview();
            let first = null;

            items.forEach(p => {
                const col = DAY_COLS.get(Number(p.day));
                if (!col) return;

                const startRow = (toMinutes(p.start) - CONFIG.gridStartMin) / 30;
                const endRow = (toMinutes(p.end) - CONFIG.gridStartMin) / 30;
                const el = document.createElement('div');

                el.className = 'ai-preview ' + (p.kind || 'to');
                el.style.top = (startRow * CONFIG.rowHeight) + 'px';
                el.style.height = ((endRow - startRow) * CONFIG.rowHeight - 4) + 'px';
                el.textContent = p.label || '';
                col.appendChild(el);

                if (!first && (p.kind || 'to') === 'to') first = el;
            });

            if (first && first.scrollIntoView) first.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }

        function hoverPreview(el, getItems) {
            el.addEventListener('mouseenter', () => showPreview(getItems()));
            el.addEventListener('mouseleave', clearPreview);
            el.addEventListener('focusin', () => showPreview(getItems()));
            el.addEventListener('focusout', clearPreview);
        }

        const slotPreview = (code, slot, kind = 'to') => ({ day: slot.day, start: slot.start, end: slot.end, kind, label: kind === 'to' ? `${code} · suggested` : `${code} · now` });

        // ----- undo -----
        const undoStack = [];

        function updateUndoButton() {
            const btn = document.getElementById('aiUndo');
            const last = undoStack[undoStack.length - 1];
            btn.disabled = !last;
            btn.title = last ? `Undo: ${last.label}` : 'Nothing to undo';
        }

        function pushUndo(label) {
            undoStack.push({ label, snapshot: JSON.stringify(blocks) });
            if (undoStack.length > 20) undoStack.shift();
            updateUndoButton();
        }

        function undoLast() {
            const last = undoStack.pop();
            if (!last) return;

            blocks = JSON.parse(last.snapshot);
            clearPreview();
            renderBlocks();
            scheduleSave();
            updateUndoButton();
            showToast(`Undid: ${last.label}`, '');
        }

        document.getElementById('aiUndo').addEventListener('click', undoLast);
        document.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key.toLowerCase() === 'z' && undoStack.length
                && !/^(INPUT|TEXTAREA|SELECT)$/.test((e.target.tagName || ''))) {
                e.preventDefault();
                undoLast();
            }
        });

        // ----- department rules -----
        const rulesForm = document.getElementById('aiRules');
        const rulesBtn = document.getElementById('aiRulesBtn');

        function timeOptions(select, fromMin, toMin) {
            for (let m = fromMin; m <= toMin; m += 30) {
                const opt = document.createElement('option');
                opt.value = String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
                opt.textContent = minutesTo12h(m);
                select.appendChild(opt);
            }
        }

        timeOptions(rulesForm.lunch_start, 600, 840);
        timeOptions(rulesForm.lunch_end, 630, 900);
        timeOptions(rulesForm.earliest_start, 420, 720);
        timeOptions(rulesForm.latest_end, 720, 1050);

        for (let h = 3; h <= 12; h++) {
            const opt = document.createElement('option');
            opt.value = String(h);
            opt.textContent = h + ' hours';
            rulesForm.max_hours_day.appendChild(opt);
        }

        function renderLunchBand(rules) {
            document.querySelectorAll('.lunch-band').forEach(el => el.remove());
            if (!rules || !rules.lunch_enabled) return;

            const top = (toMinutes(rules.lunch_start) - CONFIG.gridStartMin) / 30 * CONFIG.rowHeight;
            const height = (toMinutes(rules.lunch_end) - toMinutes(rules.lunch_start)) / 30 * CONFIG.rowHeight;
            if (height <= 0 || top < 0) return;

            DAY_COLS.forEach(col => {
                const band = document.createElement('div');
                band.className = 'lunch-band';
                band.style.top = top + 'px';
                band.style.height = height + 'px';
                band.textContent = 'Lunch break';
                band.setAttribute('aria-hidden', 'true');
                col.prepend(band);
            });
        }

        function fillRulesForm(rules) {
            renderLunchBand(rules);
            if (!rules) return;
            rulesForm.lunch_enabled.checked = !!rules.lunch_enabled;
            rulesForm.allow_saturday.checked = !!rules.allow_saturday;
            ['lunch_start', 'lunch_end', 'earliest_start', 'latest_end', 'day_pattern', 'spread'].forEach(k => { rulesForm[k].value = rules[k]; });
            rulesForm.max_hours_day.value = String(Math.round(rules.max_hours_day));
        }

        function readRulesForm() {
            return {
                lunch_enabled: rulesForm.lunch_enabled.checked,
                lunch_start: rulesForm.lunch_start.value,
                lunch_end: rulesForm.lunch_end.value,
                earliest_start: rulesForm.earliest_start.value,
                latest_end: rulesForm.latest_end.value,
                allow_saturday: rulesForm.allow_saturday.checked,
                max_hours_day: Number(rulesForm.max_hours_day.value),
                day_pattern: rulesForm.day_pattern.value,
                spread: rulesForm.spread.value,
            };
        }

        rulesBtn.addEventListener('click', () => {
            const opening = rulesForm.hidden || rulesForm.classList.contains('is-closing');
            setShown(rulesForm, opening);
            rulesBtn.setAttribute('aria-expanded', String(opening));
        });

        async function saveRules(payload, doneMessage) {
            try {
                const response = await fetch(CONFIG.rulesUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(payload),
                });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.error || 'failed');

                fillRulesForm(data.rules);
                showToast(doneMessage, 'success');
                loadSuggestions();
            } catch (err) {
                showToast('Could not save the rules.', 'error');
            }
        }

        rulesForm.addEventListener('submit', e => {
            e.preventDefault();
            saveRules(readRulesForm(), 'Rules saved. Re-checking the schedule…');
        });

        document.getElementById('aiRulesReset').addEventListener('click', () => saveRules({ reset: true }, 'Rules reset to defaults.'));

        const IMPACT_LABEL = { high: 'High impact', medium: 'Medium impact', low: 'Small gain' };

        function aiSection(box, label, count) {
            const head = aiEl('div', 'ai-section');
            head.append(aiEl('span', '', label), aiEl('span', 'count', String(count)));
            box.appendChild(head);
        }

        function aiFitRow(score) {
            const fit = Math.max(20, Math.min(99, Math.round((score - 40) / 80 * 100)));
            const row = aiEl('div', 'ai-fit');
            const bar = aiEl('span', 'bar');
            const fill = document.createElement('i');
            fill.style.width = fit + '%';
            bar.appendChild(fill);
            row.append(aiEl('span', '', 'Fit'), bar, aiEl('span', '', fit + '%'));
            return row;
        }

        function aiReasons(list) {
            const ul = aiEl('ul', 'ai-reasons');
            list.forEach(r => ul.appendChild(aiEl('li', '', r)));
            return ul;
        }

        function aiFacultyChip(f) {
            const chip = aiEl('div', 'ai-prof');
            chip.appendChild(aiEl('span', 'avatar', (f.name || '?').charAt(0).toUpperCase()));
            const text = aiEl('span', 'text', f.name);
            chip.appendChild(text);

            if (f.level) chip.appendChild(aiEl('span', 'level ' + f.level, f.level));

            if (f.setup_pending) {
                const pending = aiEl('span', 'level pending', 'Setup pending');
                pending.title = 'This faculty member has not finished setting their availability and/or subjects yet.';
                chip.appendChild(pending);
            }

            return chip;
        }

        /** Buttons + collapsible alternatives shared by every card type. */
        function aiActions(card, applyLabel, onApply, alternatives, describe, onUse, previewCard, previewAlt) {
            const actions = aiEl('div', 'ai-actions');

            if (previewCard) hoverPreview(card, previewCard);

            if (onApply) {
                const apply = aiEl('button', 'ai-apply', applyLabel);
                apply.type = 'button';
                apply.addEventListener('click', onApply);
                actions.appendChild(apply);
            }

            card.appendChild(actions);

            if (!alternatives || alternatives.length === 0) return;

            const toggle = aiEl('button', 'ai-alt-toggle', `View alternative options (${alternatives.length})`);
            toggle.type = 'button';
            const alts = aiEl('div', 'ai-alts');
            alts.hidden = true;

            alternatives.forEach(a => {
                const row = aiEl('div', 'ai-alt');
                row.appendChild(aiEl('span', '', describe(a)));
                const use = aiEl('button', '', 'Use');
                use.type = 'button';
                use.addEventListener('click', () => onUse(a));
                row.appendChild(use);

                if (previewAlt && previewCard) {
                    row.addEventListener('mouseenter', () => showPreview(previewAlt(a)));
                    row.addEventListener('mouseleave', () => showPreview(previewCard()));
                }

                alts.appendChild(row);
            });

            toggle.addEventListener('click', () => setShown(alts, alts.hidden || alts.classList.contains('is-closing')));
            actions.appendChild(toggle);
            card.appendChild(alts);
        }

        function improvementCard(item) {
            const card = aiEl('div', 'suggestion');
            card.appendChild(aiEl('span', 'impact ' + (item.impact === 'high' ? 'warn' : 'good'), `${IMPACT_LABEL[item.impact] || 'Improvement'} · ${item.code}`));

            if (item.type === 'assign_faculty') {
                if (!item.faculty) {
                    card.appendChild(aiEl('div', 'body', item.summary));
                    return card;
                }

                card.appendChild(aiEl('div', 'title', item.summary));
                card.appendChild(aiFacultyChip(item.faculty));
                card.appendChild(aiReasons(item.faculty.reasons));
                const assignPreview = () => blocks.filter(x => x.code === item.code)
                    .map(x => ({ day: x.day, start: x.start, end: x.end, kind: 'to', label: `${item.code} · ${item.faculty.name}` }));

                aiActions(card, 'Assign', () => applyAssignFaculty(item, item.faculty),
                    item.alternatives, f => `${f.name}${f.level ? ' · ' + f.level : ''}`, f => applyAssignFaculty(item, f), assignPreview);
                return card;
            }

            // move
            if (item.best) {
                card.appendChild(aiEl('div', 'title', `Move ${item.title} to ${item.best.day_name} ${item.best.time_label}`));
                card.appendChild(aiEl('div', 'body', item.summary));
                card.appendChild(aiEl('div', 'ai-now', 'Now: ' + item.block.label));
                card.appendChild(aiFitRow(item.best.score));
                card.appendChild(aiReasons(item.best.reasons));
                const movePreview = slot => [slotPreview(item.code, item.block, 'from'), slotPreview(item.code, slot, 'to')];

                aiActions(card, 'Move it', () => applyMove(item, item.best),
                    item.alternatives, a => `${a.day_name} ${a.time_label} · ${a.room}`, a => applyMove(item, a),
                    () => movePreview(item.best), movePreview);
            } else {
                card.appendChild(aiEl('div', 'title', item.title));
                card.appendChild(aiEl('div', 'body', item.summary));
                card.appendChild(aiEl('div', 'ai-now', 'Now: ' + item.block.label));
            }

            return card;
        }

        function placementCard(s) {
            const card = aiEl('div', 'suggestion');
            card.appendChild(aiEl('span', 'impact good', `${s.code} · meeting ${s.meeting} of ${s.of}`));
            card.appendChild(aiEl('div', 'title', `${s.title}: ${s.best.day_name} ${s.best.time_label}`));
            card.appendChild(aiEl('div', 'body', s.best.room));

            if (s.faculty) {
                const chipHolder = aiEl('div', 'ai-prof-holder');
                chipHolder.appendChild(aiFacultyChip(s.faculty));
                card.appendChild(chipHolder);

                // Any professor who could teach this plan, including those
                // who have not set their availability/subjects yet.
                const options = s.faculty_options || [];

                if (options.length > 1) {
                    const select = document.createElement('select');
                    select.className = 'ai-prof-select';
                    select.title = 'Choose different faculty for this subject';

                    options.forEach(o => {
                        const opt = document.createElement('option');
                        opt.value = String(o.id);
                        opt.textContent = o.name + (o.level ? ` · ${o.level}` : '') + (o.setup_pending ? ' · setup pending' : '');
                        if (o.id === s.faculty_id) opt.selected = true;
                        select.appendChild(opt);
                    });

                    select.addEventListener('change', () => {
                        const chosen = options.find(o => String(o.id) === select.value);
                        if (!chosen) return;

                        // Every meeting of this subject shares one professor.
                        (aiPlan.suggestions || []).filter(x => x.code === s.code).forEach(x => {
                            x.prof = chosen.name;
                            x.faculty_id = chosen.id;
                            x.faculty = chosen;
                        });

                        chipHolder.replaceChildren(aiFacultyChip(chosen));
                    });

                    card.appendChild(select);
                }
            } else if (s.faculty_note) {
                card.appendChild(aiEl('div', 'ai-note', s.faculty_note));
            }

            card.appendChild(aiFitRow(s.best.score));
            card.appendChild(aiReasons(s.best.reasons.concat(s.faculty ? s.faculty.reasons.slice(0, 2) : [])));
            aiActions(card, 'Plot this', () => applySuggestion(s, s.best),
                s.alternatives, a => `${a.day_name} ${a.time_label} · ${a.room}`, a => applySuggestion(s, a),
                () => [slotPreview(s.code, s.best)], a => [slotPreview(s.code, a)]);

            return card;
        }

        function renderSuggestions() {
            const box = document.getElementById('aiSuggestions');
            const status = document.getElementById('aiStatus');
            const fillAll = document.getElementById('aiFillAll');
            box.replaceChildren();
            clearPreview();

            const plan = aiPlan.suggestions || [];
            const stuck = aiPlan.unplaceable || [];
            const fixes = aiPlan.improvements || [];

            if (SUBJECTS.length === 0) {
                status.textContent = 'No classes for this term yet.';
                fillAll.hidden = true;
                return;
            }

            fillAll.hidden = plan.length === 0;

            if (plan.length === 0 && stuck.length === 0 && fixes.length === 0) {
                $('assistantCount').hidden = true;
                status.textContent = 'Schedule complete';

                const done = aiEl('div', 'ai-done');
                done.append(aiEl('span', 'material-symbols-outlined', 'task_alt'),
                    aiEl('div', '', 'Every class is plotted, well placed and conflict-free. Drag a class to fine-tune, or switch sections to keep going.'));
                box.appendChild(done);
                return;
            }

            $('assistantCount').hidden = false;
            $('assistantCount').textContent = fixes.length + plan.length;

            const parts = [];
            if (fixes.length) parts.push(`${fixes.length} improvement${fixes.length === 1 ? '' : 's'}`);
            if (plan.length) parts.push(`${plan.length} placement${plan.length === 1 ? '' : 's'}`);
            const size = aiPlan.headcount > 0 ? ` · ${aiPlan.headcount}${aiPlan.headcount_known ? '' : ' (est.)'} students` : '';
            status.textContent = (parts.join(' · ') || 'Needs attention') + size;

            if (fixes.length) {
                aiSection(box, 'Improve what is plotted', fixes.length);
                fixes.forEach(item => box.appendChild(improvementCard(item)));
            }

            if (plan.length || stuck.length) {
                aiSection(box, 'Still to plot', plan.length + stuck.length);
            }

            plan.forEach(s => box.appendChild(placementCard(s)));

            stuck.forEach(u => {
                const card = aiEl('div', 'suggestion');
                card.appendChild(aiEl('span', 'impact warn', `${u.code} · cannot place`));
                card.appendChild(aiEl('div', 'body', u.reason));
                box.appendChild(card);
            });
        }

        function suggestionBlock(s, slot) {
            const subject = SUBJECTS.find(x => x.code === s.code);

            return {
                day: slot.day,
                code: s.code,
                title: s.title,
                prof: s.prof || undefined,
                faculty_id: s.faculty_id || null,
                time_label: slot.time_label,
                room: slot.room,
                theme: subject ? subject.color : s.theme,
                start: slot.start,
                end: slot.end,
            };
        }

        function applySuggestion(s, slot) {
            pushUndo(`plotted ${s.code}`);
            clearPreview();
            blocks.push(suggestionBlock(s, slot));
            renderBlocks();
            scheduleSave();
            showToast(`${s.code} plotted on ${slot.day_name}, ${slot.time_label} (${slot.room}).`, 'success');
        }

        // Move one existing meeting. The block is found by what it looked
        // like when the suggestion was made; if the grid has changed since,
        // nothing is touched and the suggestions are recomputed.
        function applyMove(item, slot) {
            const b = blocks.find(x => x.code === item.code && Number(x.day) === item.block.day
                && x.start === item.block.start && x.room === item.block.room);

            if (!b) {
                showToast('The schedule changed — refreshing suggestions.', 'error');
                loadSuggestions();
                return;
            }

            pushUndo(`moved ${item.code}`);
            clearPreview();
            b.day = slot.day;
            b.start = slot.start;
            b.end = slot.end;
            b.time_label = slot.time_label;
            b.room = slot.room;

            renderBlocks();
            scheduleSave();
            showToast(`${item.code} moved to ${slot.day_name}, ${slot.time_label} (${slot.room}).`, 'success');
        }

        function applyAssignFaculty(item, faculty) {
            const targets = blocks.filter(x => x.code === item.code);

            if (targets.length === 0) {
                loadSuggestions();
                return;
            }

            pushUndo(`assigned ${faculty.name} to ${item.code}`);
            clearPreview();
            targets.forEach(x => {
                x.prof = faculty.name;
                x.faculty_id = faculty.id;
            });

            renderBlocks();
            scheduleSave();
            showToast(`${faculty.name} assigned to ${item.code}.`, 'success');
        }

        hoverPreview(document.getElementById('aiFillAll'), () => (aiPlan.suggestions || []).map(s => slotPreview(s.code, s.best)));

        document.getElementById('aiFillAll').addEventListener('click', async () => {
            const plan = aiPlan.suggestions || [];

            if (plan.length === 0) return;

            const ok = await confirmDialog({
                title: 'Plot everything?',
                message: `Add ${plan.length} suggested class meeting${plan.length === 1 ? '' : 's'} to this section's grid. You can still move or remove any of them afterwards.`,
                confirmLabel: 'Plot everything',
            });

            if (!ok) return;

            pushUndo(`plotted ${plan.length} meeting${plan.length === 1 ? '' : 's'}`);
            clearPreview();
            plan.forEach(s => blocks.push(suggestionBlock(s, s.best)));
            renderBlocks();
            scheduleSave();
            showToast(`${plan.length} class meeting${plan.length === 1 ? '' : 's'} plotted.`, 'success');
        });

// ---------- class queue ----------
const queueState = { query: '', status: 'all', type: 'all' };

const QUEUE_STATUS = [
    ['all', 'All'],
    ['unscheduled', 'Not started'],
    ['partial', 'In progress'],
    ['complete', 'Fully plotted'],
    ['issues', 'Has issues'],
];

function subjectHasIssues(code) {
    return blocks.some(b => b.code === code && (issueMap.get(blockKey(b)) || []).length > 0)
        || subjectStats(code).over;
}

function queueMatches(subject, stats) {
    const query = queueState.query.trim().toLowerCase();
    if (query) {
        const haystack = [subject.code, subject.title, stats.faculty || '', ...stats.meetings.map(b => b.room)].join(' ').toLowerCase();
        if (!haystack.includes(query)) return false;
    }
    if (queueState.type === 'lab' && !subject.is_lab) return false;
    if (queueState.type === 'lecture' && subject.is_lab) return false;

    switch (queueState.status) {
        case 'unscheduled': return stats.count === 0;
        case 'partial':     return stats.count > 0 && !stats.complete;
        case 'complete':    return stats.complete;
        case 'issues':      return subjectHasIssues(subject.code);
        default:            return true;
    }
}

function queueItem(subject, stats) {
    const li = mk('li', 'q-row');
    const item = mk('div', 'q-item' + (stats.complete ? ' is-complete' : '') + (ui.subjectCode === subject.code ? ' is-selected' : ''));
    item.dataset.code = subject.code;
    item.tabIndex = 0;
    item.setAttribute('role', 'button');
    item.setAttribute('aria-pressed', String(ui.subjectCode === subject.code));
    item.draggable = !stats.complete && stats.count < MAX_MEETINGS;

    const units = parseFloat(subject.units) || 0;
    const percent = stats.weekly > 0 ? Math.min(100, stats.plotted / stats.weekly * 100) : (stats.count ? 100 : 0);

    const top = mk('div', 'q-top');
    top.append(mk('span', 'q-code', subject.code), mk('span', 'q-type' + (subject.is_lab ? ' is-lab' : ''), subject.is_lab ? 'Laboratory' : 'Lecture'));

    const blocksNote = subject.duration ? ` · ${fmtHours(subject.duration)} blocks` : '';
    const meta = mk('div', 'q-meta', `${plural(units, 'unit')} · ${stats.weekly > 0 ? fmtHours(stats.weekly) + '/wk' : 'hours not set'}${blocksNote}`);

    const bar = mk('div', 'q-progress');
    bar.setAttribute('role', 'img');
    bar.setAttribute('aria-label', stats.weekly > 0 ? `${fmtHours(stats.plotted)} of ${fmtHours(stats.weekly)} plotted` : `${fmtHours(stats.plotted)} plotted`);
    const fill = document.createElement('i');
    fill.style.width = percent + '%';
    bar.appendChild(fill);

    const foot = mk('div', 'q-foot');
    foot.appendChild(mk('span', 'q-plotted', stats.weekly > 0 ? `${round1(stats.plotted)} / ${round1(stats.weekly)} hrs plotted` : `${round1(stats.plotted)} hrs plotted`));
    if (stats.over) foot.appendChild(mk('span', 'q-left is-warn', `${fmtHours(stats.plotted - stats.weekly)} over`));
    else if (stats.complete) foot.appendChild(mk('span', 'q-left is-done', 'Fully plotted'));
    else if (stats.remaining !== null) foot.appendChild(mk('span', 'q-left', `${fmtHours(stats.remaining)} left`));

    item.append(top, mk('div', 'q-title', subject.title), meta, bar, foot);

    if (stats.count > 0) {
        item.appendChild(stats.faculty
            ? mk('div', 'q-faculty', stats.faculty)
            : mk('div', 'q-faculty is-missing', 'No faculty assigned'));
    }

    item.addEventListener('click', () => selectSubject(subject.code));
    item.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            selectSubject(subject.code);
        }
    });
    item.addEventListener('dragstart', e => {
        if (!item.draggable) { e.preventDefault(); return; }
        beginDrag(e, { kind: 'new', subject }, item);
    });
    item.addEventListener('dragend', endDrag);

    li.appendChild(item);
    return li;
}

function renderQueue() {
    // The dragged card must survive until the drop, so rebuilds wait.
    if (drag) return;

    const list = $('queueList');
    const empty = $('queueEmpty');
    const rows = SUBJECTS.map((subject, index) => ({ subject, index, stats: subjectStats(subject.code) }));
    const visible = rows.filter(r => queueMatches(r.subject, r.stats))
        .sort((a, b) => Number(a.stats.complete) - Number(b.stats.complete) || a.index - b.index);

    list.replaceChildren(...visible.map(r => queueItem(r.subject, r.stats)));

    const totals = sectionTotals();
    const remaining = Math.max(0, totals.weekly - totals.plotted);
    $('queueSummary').textContent = SUBJECTS.length === 0
        ? ''
        : `${totals.complete} of ${totals.subjects} complete${remaining > 0 ? ` · ${fmtHours(remaining)} to plot` : ''}`;
    $('queueProgressFill').style.width = (totals.weekly > 0 ? totals.plotted / totals.weekly * 100 : 0) + '%';

    const filtered = queueState.query || queueState.status !== 'all' || queueState.type !== 'all';
    const activeFilters = Number(queueState.status !== 'all') + Number(queueState.type !== 'all');
    $('queueFilterCount').hidden = activeFilters === 0;
    $('queueFilterCount').textContent = activeFilters;
    $('queueClearFilters').hidden = !filtered;

    empty.replaceChildren();
    empty.hidden = visible.length > 0;
    if (visible.length === 0) {
        if (SUBJECTS.length === 0) {
            empty.append(mk('strong', '', 'No classes for this term'), mk('p', '', 'This year level has no curriculum yet. Set one up to start plotting.'));
        } else {
            empty.append(mk('strong', '', 'No classes match'), mk('p', '', 'Clear the search or filters to see every class.'));
        }
    }

    // Every class plotted: say so, above the (now all-complete) list.
    $('queueDone').hidden = !(SUBJECTS.length > 0 && totals.complete === totals.subjects);
}

function setupQueueControls() {
    const status = $('queueStatusChips');
    QUEUE_STATUS.forEach(([value, label]) => {
        const chip = mk('button', 'chip' + (value === 'all' ? ' is-active' : ''), label);
        chip.type = 'button';
        chip.dataset.value = value;
        chip.setAttribute('aria-pressed', String(value === 'all'));
        chip.addEventListener('click', () => {
            queueState.status = value;
            status.querySelectorAll('.chip').forEach(c => {
                c.classList.toggle('is-active', c === chip);
                c.setAttribute('aria-pressed', String(c === chip));
            });
            renderQueue();
        });
        status.appendChild(chip);
    });

    $('queueSearch').addEventListener('input', e => {
        queueState.query = e.target.value;
        renderQueue();
    });

    $('queueType').addEventListener('change', e => {
        queueState.type = e.target.value;
        renderQueue();
    });

    const toggle = $('queueFilterToggle');
    toggle.addEventListener('click', () => {
        const open = $('queueFilters').hidden || $('queueFilters').classList.contains('is-closing');
        setShown($('queueFilters'), open);
        toggle.setAttribute('aria-expanded', String(open));
    });

    $('queueClearFilters').addEventListener('click', () => {
        queueState.query = '';
        queueState.status = 'all';
        queueState.type = 'all';
        $('queueSearch').value = '';
        $('queueType').value = 'all';
        status.querySelectorAll('.chip').forEach(c => {
            const active = c.dataset.value === 'all';
            c.classList.toggle('is-active', active);
            c.setAttribute('aria-pressed', String(active));
        });
        renderQueue();
    });
}
setupQueueControls();

// ---------- placement rules ----------
// Mirrors the server's hard rules (section / faculty / room overlap, meeting
// cap) so a bad drop is explained before anything is sent, and adds the soft
// warnings a chairperson may knowingly accept.
function subjectSlots(subject) {
    // Every new placement (drag or the "Plot a meeting" picker) is one
    // 30-minute slot — a chairperson builds up a longer meeting by placing
    // adjacent slots, which auto-join (see touchingOwnBlocks/mergeIntoBlock)
    // instead of committing the subject's whole duration in a single drop.
    return 1;
}

/** Already-plotted blocks of this subject, same day, touching the given range (so a new drop can join them instead of becoming a separate meeting). */
function touchingOwnBlocks(code, day, startRow, endRow) {
    return blocks.filter(b => b.code === code && Number(b.day) === day
        && (blockEndRow(b) === startRow || blockStartRow(b) === endRow));
}

/** Extends/merges `touching` blocks (1 or 2 of them) and the new range into one continuous block, reusing the room/faculty they already have. Returns true if it merged, false if the merged range hit a hard conflict (and the drop was shown as a conflict). */
function mergeIntoBlock(touching, subject, day, startRow, endRow) {
    const startRows = touching.map(blockStartRow).concat(startRow);
    const endRows = touching.map(blockEndRow).concat(endRow);
    const merged = { startRow: Math.min(...startRows), endRow: Math.max(...endRows) };
    const anchor = touching[0];

    const result = evaluatePlacement({ subject, day, ...merged, ignoreKey: blockKey(anchor), roomName: anchor.room, prof: anchor.prof });
    if (result.hard) {
        showConflict(result.hard);
        return true;
    }

    pushUndo(`extended ${subject.code}`);
    touching.forEach(b => { if (b !== anchor) blocks.splice(blocks.indexOf(b), 1); });
    anchor.start = rowToHHMM(merged.startRow);
    anchor.end = rowToHHMM(merged.endRow);
    anchor.time_label = `${rowTo12h(merged.startRow)} - ${rowTo12h(merged.endRow)}`;
    ui.subjectCode = anchor.code;
    ui.blockKey = blockKey(anchor);
    ui.mode = 'block';
    renderBlocks();
    scheduleSave();
    focusBlock(ui.blockKey);
    showToast(`${anchor.code} extended to ${rowRange(merged.startRow, merged.endRow)}.`, 'success', { label: 'Undo', onClick: undoLast });
    announce(`${anchor.code} extended on ${dayName(day)} to ${blockRange(anchor)}.`);
    return true;
}

/** Grows (+1) or shrinks (-1) an already-plotted block's end time by one 30-minute slot, in place. */
function resizeBlock(b, deltaSlots) {
    const key = blockKey(b);
    const startRow = blockStartRow(b);
    const endRow = blockEndRow(b) + deltaSlots;
    if (endRow - startRow < 1 || endRow > CONFIG.totalRows) return;

    const subject = SUBJECT_BY_CODE.get(b.code) || { code: b.code, title: b.title, is_lab: false, units: '0', duration: blockHours(b) };
    const result = evaluatePlacement({ subject, day: Number(b.day), startRow, endRow, ignoreKey: key, roomName: b.room, prof: b.prof });
    if (result.hard) {
        showConflict(result.hard);
        return;
    }

    pushUndo(deltaSlots > 0 ? `extended ${b.code}` : `shortened ${b.code}`);
    b.end = rowToHHMM(endRow);
    b.time_label = `${rowTo12h(startRow)} - ${rowTo12h(endRow)}`;
    ui.blockKey = key;
    renderBlocks();
    renderInspector();
    scheduleSave();
    showToast(`${b.code} is now ${fmtHours((endRow - startRow) / 2)}.`, 'success', { label: 'Undo', onClick: undoLast });
}

/** How many 30-min slots a block could grow to without overshooting the subject's weekly target. */
function meetingMaxSlots(b, stats) {
    const slots = blockEndRow(b) - blockStartRow(b);
    const otherPlotted = stats.plotted - blockHours(b);
    return stats.weekly > 0
        ? Math.max(slots, Math.floor((stats.weekly - otherPlotted + 1e-9) / 0.5))
        : slots + 12;
}

/** The +/- buttons that grow/shrink a plotted block by 30 minutes, in place — shared by the Details panel and the calendar tile itself. `className` lets each caller size them appropriately. */
function resizeStepper(b, stats, className) {
    const slots = blockEndRow(b) - blockStartRow(b);
    const maxSlots = meetingMaxSlots(b, stats);

    const stepper = mk('span', className);
    const minus = mk('button', 'stepper__btn', '−');
    minus.type = 'button';
    minus.draggable = false;
    minus.setAttribute('aria-label', `Shorten ${b.code}`);
    minus.disabled = slots <= 1;
    minus.addEventListener('mousedown', e => e.stopPropagation());
    minus.addEventListener('click', e => { e.stopPropagation(); resizeBlock(b, -1); });

    const plus = mk('button', 'stepper__btn', '+');
    plus.type = 'button';
    plus.draggable = false;
    plus.setAttribute('aria-label', `Extend ${b.code}`);
    plus.disabled = slots >= maxSlots || blockEndRow(b) >= CONFIG.totalRows;
    plus.addEventListener('mousedown', e => e.stopPropagation());
    plus.addEventListener('click', e => { e.stopPropagation(); resizeBlock(b, 1); });

    stepper.append(minus, plus);
    return stepper;
}

/** "This meeting" fact value: the length, plus a +/- stepper to resize it directly (no dragging). */
function meetingLengthControl(b, subject, stats, isOwned) {
    const wrap = mk('span', 'meeting-length', fmtHours(blockHours(b)));
    if (isOwned) wrap.appendChild(resizeStepper(b, stats, 'stepper stepper--inline'));
    return wrap;
}

function evaluatePlacement({ subject, day, startRow, endRow, ignoreKey = null, roomName = null, prof = null }) {
    const attempted = { subject, day, startRow, endRow };
    const mine = blocks.filter(b => !ignoreKey || blockKey(b) !== ignoreKey);
    const everyone = mine.concat(OTHERS);
    const inSlot = b => Number(b.day) === day && rangesOverlap(startRow, endRow, blockStartRow(b), blockEndRow(b));
    const when = rowRange(startRow, endRow);

    const sectionClash = mine.find(inSlot);
    if (sectionClash) {
        return { hard: { type: 'section', title: 'Section conflict', attempted, existing: sectionClash,
            message: `${CONTEXT.section} already has ${sectionClash.code} at this time, and a section cannot attend two classes at once.` }, warnings: [] };
    }

    if (!ignoreKey && blocks.filter(b => b.code === subject.code).length >= MAX_MEETINGS) {
        return { hard: { type: 'limit', title: 'Meeting limit reached', attempted, existing: null,
            message: `${subject.code} already meets ${MAX_MEETINGS} times a week, which is the maximum.` }, warnings: [] };
    }

    const facultyClash = prof ? everyone.find(b => b.prof === prof && inSlot(b)) : null;
    if (facultyClash) {
        return { hard: { type: 'faculty', title: 'Faculty conflict', attempted, existing: facultyClash,
            message: `${prof} is already teaching ${facultyClash.code}${facultyClash.section ? ' for ' + facultyClash.section : ''} at this time. Choose another time, or change who teaches ${subject.code}.` }, warnings: [] };
    }

    const roomClash = roomName ? everyone.find(b => b.room === roomName && inSlot(b)) : null;
    if (roomClash) {
        return { hard: { type: 'room', title: 'Room conflict', attempted, existing: roomClash,
            message: `${roomName} is already in use by ${roomClash.code} · ${roomClash.section || CONTEXT.section} at this time.` }, warnings: [] };
    }

    if (roomName && !ROOM_BY_NAME.has(roomName)) {
        return { hard: { type: 'retired', title: 'Room no longer available', attempted, existing: null,
            message: roomName + ' was removed or deactivated. Change the room first, then move the class.' }, warnings: [] };
    }

    const roomsFree = ROOMS.filter(r => !everyone.some(b => b.room === r.name && inSlot(b))).length;
    if (!roomName && roomsFree === 0) {
        return { hard: { type: 'norooms', title: 'No room available', attempted, existing: null,
            message: `Every room is in use on ${dayName(day)}, ${when}. Try a different time.` }, warnings: [], roomsFree };
    }

    const warnings = [];
    const attendedDays = new Set(mine.map(b => Number(b.day)));
    if (attendedDays.size > 0 && !attendedDays.has(day)) {
        warnings.push(`${dayName(day)} is a new campus day for ${CONTEXT.section}.`);
    }

    const rules = aiPlan.rules;
    if (rules) {
        const start = rowToHHMM(startRow);
        const end = rowToHHMM(endRow);
        if ((rules.earliest_start && start < rules.earliest_start) || (rules.latest_end && end > rules.latest_end)) {
            warnings.push(`This is outside the department's hours (${rangeLabel(toMinutes(rules.earliest_start), toMinutes(rules.latest_end))}).`);
        }
        if (rules.lunch_enabled && rangesOverlap(toMinutes(start), toMinutes(end), toMinutes(rules.lunch_start), toMinutes(rules.lunch_end))) {
            warnings.push(`This overlaps the lunch break (${rangeLabel(toMinutes(rules.lunch_start), toMinutes(rules.lunch_end))}).`);
        }
        if (day === 5 && !rules.allow_saturday) {
            warnings.push('Saturday classes are switched off in this department\'s scheduling rules.');
        }
    }

    return { hard: null, warnings, roomsFree };
}

// ---------- drag & drop ----------
let drag = null; // { kind: 'new' | 'move', subject, block?, key?, grabRows?, el }
const dropPreview = mk('div', 'drop-preview');
const dropLabel = mk('span', 'drop-preview__label');
dropPreview.setAttribute('aria-hidden', 'true');
dropPreview.appendChild(dropLabel);

function beginDrag(e, payload, sourceEl) {
    drag = Object.assign({ el: sourceEl }, payload);
    e.dataTransfer.effectAllowed = payload.kind === 'move' ? 'move' : 'copy';
    e.dataTransfer.setData('text/plain', payload.subject.code);
    sourceEl.classList.add('is-dragging');
    document.body.classList.add('is-plot-dragging');

    // A 'new' drag always places one 30-minute slot (it joins an adjacent
    // slot of the same class automatically rather than sizing to the whole
    // subject duration) — only a 'move' drag keeps the block's real length.
    const lengthLabel = payload.kind === 'move' ? fmtHours(blockHours(payload.block)) : '30 min';
    const chip = mk('div', 'drag-chip', `${payload.subject.code} · ${lengthLabel}`);
    document.body.appendChild(chip);
    e.dataTransfer.setDragImage(chip, 14, 14);
    setTimeout(() => chip.remove(), 0);
}

function endDrag() {
    if (!drag) return;
    if (drag.el) drag.el.classList.remove('is-dragging');
    drag = null;
    dropPreview.remove();
    document.body.classList.remove('is-plot-dragging');
    if (renderDeferred) renderBlocks(); else renderQueue();
}

function attachBlockDrag(el, b) {
    el.addEventListener('dragstart', e => {
        const subject = SUBJECT_BY_CODE.get(b.code);
        if (!subject) { e.preventDefault(); return; }

        const rows = blockEndRow(b) - blockStartRow(b);
        const grabRows = Math.max(0, Math.min(rows - 1, Math.floor((e.clientY - el.getBoundingClientRect().top) / CONFIG.rowHeight)));
        beginDrag(e, { kind: 'move', subject, block: b, key: blockKey(b), grabRows }, el);
    });
    el.addEventListener('dragend', endDrag);
}

function dragRange(col, clientY) {
    const slots = drag.kind === 'move' ? blockEndRow(drag.block) - blockStartRow(drag.block) : subjectSlots(drag.subject);
    const rect = col.getBoundingClientRect();
    let startRow = Math.floor((clientY - rect.top) / CONFIG.rowHeight) - (drag.grabRows || 0);
    startRow = Math.max(0, Math.min(startRow, CONFIG.totalRows - slots));
    return { startRow, endRow: startRow + slots };
}

function evaluateDrag(day, range) {
    if (drag.kind === 'move') {
        return evaluatePlacement({ subject: drag.subject, day, ...range, ignoreKey: drag.key, roomName: drag.block.room, prof: drag.block.prof });
    }
    const faculty = assignedFaculty(drag.subject.code);
    return evaluatePlacement({ subject: drag.subject, day, ...range, prof: faculty && faculty.name });
}

let lastPreviewKey = '';

DAY_COLS.forEach((col, day) => {
    col.addEventListener('dragover', e => {
        if (!drag) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = drag.kind === 'move' ? 'move' : 'copy';

        const range = dragRange(col, e.clientY);
        const key = `${day}:${range.startRow}`;
        if (key === lastPreviewKey && dropPreview.parentElement === col) return;
        lastPreviewKey = key;

        const result = evaluateDrag(day, range);
        dropPreview.style.top = (range.startRow * CONFIG.rowHeight + 1) + 'px';
        dropPreview.style.height = ((range.endRow - range.startRow) * CONFIG.rowHeight - 2) + 'px';
        dropPreview.classList.toggle('is-invalid', !!result.hard);
        dropPreview.classList.toggle('is-warn', !result.hard && result.warnings.length > 0);
        dropLabel.textContent = result.hard
            ? result.hard.title
            : `${rowRange(range.startRow, range.endRow)}${result.roomsFree !== undefined ? ' · ' + plural(result.roomsFree, 'room') + ' free' : ''}`;
        if (dropPreview.parentElement !== col) col.appendChild(dropPreview);
    });

    col.addEventListener('drop', e => {
        if (!drag) return;
        e.preventDefault();

        const dragged = drag;
        const range = dragRange(col, e.clientY);
        dropPreview.remove();
        lastPreviewKey = '';

        if (dragged.kind === 'move') dropMove(dragged, day, range);
        else dropNew(dragged.subject, day, range);

        // dragend can be skipped when the source node is replaced; make sure
        // the drag state never outlives the drop.
        setTimeout(endDrag, 0);
    });
});

$('boardScroll').addEventListener('dragleave', e => {
    if (!e.relatedTarget || !$('boardScroll').contains(e.relatedTarget)) {
        dropPreview.remove();
        lastPreviewKey = '';
    }
});

function dropNew(subject, day, range) {
    const touching = touchingOwnBlocks(subject.code, day, range.startRow, range.endRow);
    if (touching.length) { mergeIntoBlock(touching, subject, day, range.startRow, range.endRow); return; }

    const faculty = assignedFaculty(subject.code);
    const result = evaluatePlacement({ subject, day, ...range, prof: faculty && faculty.name });

    if (result.hard) {
        showConflict(result.hard);
        return;
    }

    beginPlacement({ kind: 'new', subject, day, startRow: range.startRow, endRow: range.endRow, warnings: result.warnings });
}

function dropMove(dragged, day, range) {
    moveBlock(dragged.key, day, range.startRow, range.endRow);
}

/** Moves one plotted meeting; its room and faculty stay as they are. */
async function moveBlock(key, day, startRow, endRow) {
    const b = blocks.find(x => blockKey(x) === key);
    if (!b) return false;
    if (Number(b.day) === day && blockStartRow(b) === startRow) return false;

    const subject = SUBJECT_BY_CODE.get(b.code);
    const result = evaluatePlacement({ subject, day, startRow, endRow, ignoreKey: key, roomName: b.room, prof: b.prof });
    if (result.hard) {
        showConflict(result.hard);
        return false;
    }

    // Faculty availability is the one soft rule that needs an audit trail.
    const start = rowToHHMM(startRow);
    const end = rowToHHMM(endRow);
    const f = cachedFaculty(b.faculty_id);
    const fit = f ? availabilityFit(f, day, start, end) : { fit: 'ok' };
    let overrideReason = null;

    if (fit.fit === 'off' || fit.fit === 'outside') {
        const proceed = await confirmDialog({
            title: 'Move outside faculty availability?',
            message: `${availabilityText(b.prof, day, fit)} This exception will be recorded. Add an optional reason for the audit history.`,
            confirmLabel: 'Move anyway',
            danger: true,
            reason: true,
        });
        if (!proceed) return false;
        overrideReason = proceed.reason || '';
    }

    // The block may have changed while the dialog was open.
    if (!blocks.includes(b)) return false;

    pushUndo(`moved ${b.code}`);
    b.day = day;
    b.start = start;
    b.end = end;
    b.time_label = `${rowTo12h(startRow)} - ${rowTo12h(endRow)}`;
    if (ui.blockKey === key) ui.blockKey = blockKey(b);

    if (overrideReason !== null) {
        recordFacultyOverride({ facultyId: b.faculty_id, subjectCode: b.code, day, start, end, type: 'unavailable', reason: overrideReason });
    }

    renderBlocks();
    scheduleSave();
    showToast(`${b.code} moved to ${dayShort(day)} ${rangeLabel(toMinutes(start), toMinutes(end))}.`, 'success', { label: 'Undo', onClick: undoLast });
    result.warnings.forEach(w => announce(w));
    return true;
}

async function removeBlock(key) {
    const b = blocks.find(x => blockKey(x) === key);
    if (!b) return;
    if (!OWNED_CODES.has(b.code)) {
        showToast(`${b.code} is owned by another department — only they can remove it from the schedule.`, 'error');
        return;
    }

    // One meeting is easy to bring back, so this is undoable instead of
    // interrupting the chairperson with a confirmation.
    pushUndo(`removed ${b.code}`);
    blocks.splice(blocks.indexOf(b), 1);
    if (ui.blockKey === key) {
        ui.blockKey = null;
        ui.mode = ui.subjectCode ? 'subject' : 'overview';
    }
    renderBlocks();
    scheduleSave();
    showToast(`Removed ${b.code} (${dayShort(b.day)} ${blockRange(b)}).`, '', { label: 'Undo', onClick: undoLast });
}

/** Screen-reader announcements for changes that are otherwise only visual. */
function announce(message) {
    const region = $('srAnnounce');
    region.textContent = '';
    requestAnimationFrame(() => { region.textContent = message; });
}
        // ---------- swap approvals ----------
        const approvalsModal = document.getElementById('approvalsModal');

        function renderApprovals() {
            const badge = document.getElementById('approvalBadge');
            badge.textContent = APPROVALS.length;
            badge.classList.toggle('zero', APPROVALS.length === 0);

            const list = document.getElementById('approvalsList');
            list.innerHTML = '';

            if (APPROVALS.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'approvals-empty';
                empty.textContent = 'No swaps waiting for approval.';
                list.appendChild(empty);
                return;
            }

            APPROVALS.forEach(a => {
                const item = document.createElement('div');
                item.className = 'approval-item';

                const who = document.createElement('div');
                who.className = 'ap-who';
                who.textContent = a.requested
                    ? `${a.requester} ⇄ ${a.target} (exchange)`
                    : `${a.requester} → ${a.target} (handover)`;

                const loads = document.createElement('div');
                loads.className = 'ap-loads';
                loads.textContent = a.requested
                    ? `${a.requester} gives ${a.offered} · receives ${a.requested}`
                    : `${a.requester} hands over ${a.offered}`;

                const actions = document.createElement('div');
                actions.className = 'ap-actions';

                const reject = document.createElement('button');
                reject.type = 'button';
                reject.className = 'btn btn-ghost';
                reject.textContent = 'Reject';
                reject.addEventListener('click', () => decideSwap(a, false));

                const approve = document.createElement('button');
                approve.type = 'button';
                approve.className = 'btn btn-primary';
                approve.textContent = 'Approve';
                approve.addEventListener('click', () => decideSwap(a, true));

                actions.append(reject, approve);
                item.append(who, loads, actions);
                list.appendChild(item);
            });
        }

        async function decideSwap(a, approve) {
            const confirmed = await confirmDialog({
                title: (approve ? 'Approve' : 'Reject') + ' swap',
                message: (a.requested
                    ? `${a.requester} gives ${a.offered} and receives ${a.requested} from ${a.target}.`
                    : `${a.requester} hands ${a.offered} over to ${a.target}.`)
                    + (approve ? ' Approving transfers the load(s) immediately.' : ' Rejecting keeps everything as it is.'),
                confirmLabel: approve ? 'Approve Swap' : 'Reject Swap',
                danger: !approve,
            });
            if (!confirmed) return;

            try {
                const url = approve
                    ? CONFIG.swapApproveUrl
                    : CONFIG.swapRejectUrl;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ swap_id: a.id }),
                });
                const result = await response.json();

                if (response.ok && result.ok) {
                    APPROVALS = APPROVALS.filter(x => x.id !== a.id);
                    renderApprovals();
                    showToast(approve ? 'Swap approved — loads transferred.' : 'Swap rejected.', approve ? 'success' : '');
                } else {
                    showToast(result.error || 'Could not decide the swap.', 'error');
                }
            } catch (err) {
                showToast('Network error while deciding the swap.', 'error');
            }
        }

        document.getElementById('approvalsBtn').addEventListener('click', () => {
            renderApprovals();
            approvalsModal.classList.add('open');
        });

        // ===== Change requests =====
        const changeRequestsModal = document.getElementById('changeRequestsModal');

        function renderChangeRequests() {
            const badge = document.getElementById('changeRequestBadge');
            badge.textContent = CHANGE_REQUESTS.length;
            badge.classList.toggle('zero', CHANGE_REQUESTS.length === 0);

            const list = document.getElementById('changeRequestsList');
            list.innerHTML = '';

            if (CHANGE_REQUESTS.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'approvals-empty';
                empty.textContent = 'No change requests waiting for review.';
                list.appendChild(empty);
                return;
            }

            CHANGE_REQUESTS.forEach(cr => {
                const item = document.createElement('div');
                item.className = 'approval-item';

                const who = document.createElement('div');
                who.className = 'ap-who';
                who.textContent = `${cr.faculty} — ${cr.subject}`;

                const reason = document.createElement('div');
                reason.className = 'ap-loads';
                reason.textContent = cr.reason + (cr.proposed ? ` (proposed: ${cr.proposed})` : '');

                const actions = document.createElement('div');
                actions.className = 'ap-actions';

                const reject = document.createElement('button');
                reject.type = 'button';
                reject.className = 'btn btn-ghost';
                reject.textContent = 'Reject';
                reject.addEventListener('click', () => decideChangeRequest(cr, false));

                const approve = document.createElement('button');
                approve.type = 'button';
                approve.className = 'btn btn-primary';
                approve.textContent = 'Approve';
                approve.addEventListener('click', () => decideChangeRequest(cr, true));

                actions.append(reject, approve);
                item.append(who, reason, actions);
                list.appendChild(item);
            });
        }

        async function decideChangeRequest(cr, approve) {
            const confirmed = await confirmDialog({
                title: (approve ? 'Approve' : 'Reject') + ' change request',
                message: `${cr.faculty}'s request for ${cr.subject}: "${cr.reason}".`
                    + (approve ? ' Approving does not reassign the subject — you\'ll need to replot it with a different faculty member.' : ' Rejecting keeps the assignment as it is.'),
                confirmLabel: approve ? 'Approve' : 'Reject',
                danger: !approve,
            });
            if (!confirmed) return;

            try {
                const url = approve
                    ? CONFIG.changeApproveUrl
                    : CONFIG.changeRejectUrl;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ request_id: cr.id }),
                });
                const result = await response.json();

                if (response.ok && result.ok) {
                    CHANGE_REQUESTS = CHANGE_REQUESTS.filter(x => x.id !== cr.id);
                    renderChangeRequests();
                    showToast(approve ? 'Change request approved.' : 'Change request rejected.', approve ? 'success' : '');
                } else {
                    showToast(result.error || 'Could not decide the change request.', 'error');
                }
            } catch (err) {
                showToast('Network error while deciding the change request.', 'error');
            }
        }

        document.getElementById('changeRequestsBtn').addEventListener('click', () => {
            renderChangeRequests();
            changeRequestsModal.classList.add('open');
        });
        document.getElementById('changeRequestsClose').addEventListener('click', () => changeRequestsModal.classList.remove('open'));
        changeRequestsModal.addEventListener('click', e => { if (e.target === changeRequestsModal) changeRequestsModal.classList.remove('open'); });
        document.getElementById('approvalsClose').addEventListener('click', () => approvalsModal.classList.remove('open'));
        approvalsModal.addEventListener('click', e => { if (e.target === approvalsModal) approvalsModal.classList.remove('open'); });
        // ---------- room overview modal ----------
        const overviewModal = document.getElementById('overviewModal');
        const overviewState = { day: 0, room: ROOMS[0]?.name || '', query: '', filter: 'all' };
        let overviewLastFocus = null;

        function overviewRoomLoad(room, day) {
            return blocks.concat(OTHERS).filter(b => b.room === room && b.day === day).length;
        }

        function overviewRoomBlocks(room, day) {
            return blocks.concat(OTHERS)
                .filter(b => b.room === room && b.day === day)
                .sort((a, b) => blockStartRow(a) - blockStartRow(b));
        }

        function overviewRoomHours(room, day) {
            return overviewRoomBlocks(room, day).reduce((total, block) => total + ((blockEndRow(block) - blockStartRow(block)) / 2), 0);
        }

        function overviewTimeline(room, day, state) {
            const track = document.createElement('div');
            track.className = 'ov-timeline-track';
            overviewRoomBlocks(room, day).forEach(b => {
                const seg = document.createElement('div');
                seg.className = 'ov-timeline-seg' + (state === 'red' ? ' is-red' : '');
                const left = (blockStartRow(b) / CONFIG.totalRows) * 100;
                const width = ((blockEndRow(b) - blockStartRow(b)) / CONFIG.totalRows) * 100;
                seg.style.left = left + '%';
                seg.style.width = width + '%';
                seg.title = `${b.code}: ${rowTo12h(blockStartRow(b))} - ${rowTo12h(blockEndRow(b))}`;
                track.appendChild(seg);
            });
            return track;
        }

        function renderOverviewSummary() {
            const day = overviewState.day;
            const free = ROOMS.filter(r => overviewRoomLoad(r.name, day) === 0).length;
            const inUse = ROOMS.filter(r => overviewRoomLoad(r.name, day) > 0).length;
            const busy = ROOMS.filter(r => overviewRoomLoad(r.name, day) >= 3).length;
            const pct = ROOMS.length ? Math.round((free / ROOMS.length) * 100) : 0;
            const badge = document.getElementById('overviewSummary');
            badge.innerHTML = '';
            const dot = document.createElement('span');
            dot.className = 'dot';
            badge.appendChild(dot);
            badge.appendChild(document.createTextNode(`${pct}% free on ${WEEK[day].day}`));
            document.getElementById('overviewMetricFree').textContent = free;
            document.getElementById('overviewMetricUse').textContent = inUse;
            document.getElementById('overviewMetricBusy').textContent = busy;
        }

        function renderOverviewControls() {
            const daysBox = document.getElementById('overviewDays');
            daysBox.innerHTML = '';
            WEEK.forEach((d, i) => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'day-chip' + (i === overviewState.day ? ' active' : '');
                chip.textContent = d.day;
                chip.setAttribute('aria-pressed', String(i === overviewState.day));
                chip.addEventListener('click', () => {
                    overviewState.day = i;
                    renderRoomOverview();
                });
                daysBox.appendChild(chip);
            });

            renderOverviewSummary();

            const roomsBox = document.getElementById('overviewRoomsWrap');
            roomsBox.innerHTML = '';

            const query = overviewState.query.trim().toLowerCase();
            const visibleRooms = ROOMS.filter(room => {
                const load = overviewRoomLoad(room.name, overviewState.day);
                const matchesQuery = !query || room.name.toLowerCase().includes(query) || String(room.type || '').toLowerCase().includes(query);
                const matchesFilter = overviewState.filter === 'all'
                    || (overviewState.filter === 'free' && load === 0)
                    || (overviewState.filter === 'in-use' && load > 0)
                    || (overviewState.filter === 'labs' && room.is_laboratory);
                return matchesQuery && matchesFilter;
            });

            if (!visibleRooms.length) {
                roomsBox.innerHTML = '<div class="ov-no-results"><strong>No matching rooms</strong><br>Try another search or filter.</div>';
            }

            FLOORS.forEach(floor => {
                const floorRooms = visibleRooms.filter(r => r.floor === floor);
                if (floorRooms.length === 0) return;

                const group = document.createElement('div');
                group.className = 'ov-floor-group';

                const heading = document.createElement('div');
                heading.className = 'floor-heading ov-floor-heading';
                heading.innerHTML = `<span>${escapeHtml(floor)}</span><span class="count">${floorRooms.length} ${floorRooms.length === 1 ? 'space' : 'spaces'}</span>`;
                group.appendChild(heading);

                const grid = document.createElement('div');
                grid.className = 'room-grid overview-rooms';

                floorRooms.forEach(room => {
                    const load = overviewRoomLoad(room.name, overviewState.day);
                    const state = load >= 3 ? 'red' : load >= 1 ? 'yellow' : 'green';
                    const occupiedHours = overviewRoomHours(room.name, overviewState.day);

                    const card = document.createElement('button');
                    card.type = 'button';
                    card.className = 'room-card ' + state + (room.name === overviewState.room ? ' selected' : '');
                    card.setAttribute('aria-pressed', String(room.name === overviewState.room));
                    card.setAttribute('aria-label', `${room.name}, ${load === 0 ? 'free all day' : `${load} classes, ${occupiedHours} hours occupied`}`);

                    const top = document.createElement('div');
                    top.className = 'ov-card-top';
                    const typeBadge = document.createElement('span');
                    typeBadge.className = 'ov-type-badge' + (room.is_laboratory ? ' is-lab' : '');
                    typeBadge.textContent = room.is_laboratory ? 'Laboratory' : (room.type || 'Room');
                    const capacity = document.createElement('span');
                    capacity.className = 'ov-capacity';
                    capacity.textContent = room.capacity > 0 ? `Cap: ${room.capacity}` : '';
                    top.append(typeBadge, capacity);

                    const head = document.createElement('div');
                    head.className = 'room-head';
                    const name = document.createElement('div');
                    name.className = 'room-name';
                    name.textContent = room.name;
                    const dot = document.createElement('span');
                    dot.className = 'room-dot';
                    head.append(name, dot);

                    const status = document.createElement('div');
                    status.className = 'room-status';
                    status.textContent = load === 0 ? 'Free all day' : `${load} ${load === 1 ? 'class' : 'classes'} · ${occupiedHours}h occupied`;

                    const timelineWrap = document.createElement('div');
                    timelineWrap.className = 'ov-timeline';
                    timelineWrap.appendChild(overviewTimeline(room.name, overviewState.day, state));

                    card.append(top, head, status, timelineWrap);
                    card.addEventListener('click', () => {
                        overviewState.room = room.name;
                        renderRoomOverview();
                        if (window.matchMedia('(max-width: 850px)').matches) {
                            requestAnimationFrame(() => document.querySelector('.ov-schedule-pane').scrollIntoView({
                                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                                block: 'start',
                            }));
                        }
                    });
                    grid.appendChild(card);
                });

                group.appendChild(grid);
                roomsBox.appendChild(group);
            });

            const selectedRoom = ROOMS.find(room => room.name === overviewState.room);
            document.getElementById('overviewListLabel').textContent = overviewState.room || 'No room selected';
            document.getElementById('overviewListMeta').textContent = selectedRoom
                ? `${WEEK[overviewState.day].day} · ${selectedRoom.is_laboratory ? 'Laboratory' : (selectedRoom.type || 'Room')}${selectedRoom.capacity > 0 ? ` · Capacity ${selectedRoom.capacity}` : ''}`
                : WEEK[overviewState.day].day;
        }

        function overviewSectionLabel(b) {
            const section = b.section || CONTEXT.section;
            const yearLevel = b.year_level || CONTEXT.year_level;
            return `${section} · ${shortYear(yearLevel)}`;
        }

        function overviewClassRow(b) {
            const row = document.createElement('div');
            row.className = 'overview-row';

            const time = document.createElement('div');
            time.className = 'ov-time';
            time.textContent = `${rowTo12h(blockStartRow(b))} - ${rowTo12h(blockEndRow(b))}`;

            const details = document.createElement('div');
            details.className = 'ov-details';
            const code = document.createElement('span');
            code.className = 'code';
            code.textContent = b.code;
            details.appendChild(code);
            details.appendChild(document.createTextNode(' — ' + b.title));
            const meta = document.createElement('div');
            meta.className = 'meta';
            const program = b.program || CONTEXT.program;
            meta.textContent = (b.prof ? b.prof + ' · ' : '') + program;
            details.appendChild(meta);

            const section = document.createElement('span');
            section.className = 'ov-section';
            section.textContent = overviewSectionLabel(b);

            row.append(time, details, section);
            return row;
        }

        function overviewFreeRow(startRow, endRow) {
            const row = document.createElement('div');
            row.className = 'overview-row free';

            const time = document.createElement('div');
            time.className = 'ov-time';
            time.textContent = `${rowTo12h(startRow)} - ${rowTo12h(endRow)}`;

            const details = document.createElement('div');
            details.className = 'ov-details';
            const hours = (endRow - startRow) / 2;
            details.textContent = `Free · ${hours} ${hours === 1 ? 'hour' : 'hours'}`;

            row.append(time, details);
            return row;
        }

        function renderRoomOverview() {
            if (!overviewModal.classList.contains('open')) return;

            renderOverviewControls();

            const room = overviewState.room;
            const day = overviewState.day;
            const list = document.getElementById('overviewList');
            list.innerHTML = '';

            const items = blocks.concat(OTHERS)
                .filter(b => b.room === room && b.day === day)
                .sort((a, b) => blockStartRow(a) - blockStartRow(b));

            if (items.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'overview-empty';
                empty.innerHTML = `<div><strong>Free all day</strong><span>${escapeHtml(room)} has no classes plotted on ${escapeHtml(WEEK[day].day)}.</span></div>`;
                list.appendChild(empty);
                return;
            }

            // Interleave the occupied slots with the free gaps between them.
            let cursor = 0;
            items.forEach(b => {
                const startRow = blockStartRow(b);
                const endRow = blockEndRow(b);
                if (startRow > cursor) list.appendChild(overviewFreeRow(cursor, startRow));
                list.appendChild(overviewClassRow(b));
                cursor = Math.max(cursor, endRow);
            });
            if (cursor < CONFIG.totalRows) list.appendChild(overviewFreeRow(cursor, CONFIG.totalRows));
        }

        document.getElementById('overviewRoomSearch').addEventListener('input', event => {
            overviewState.query = event.target.value;
            renderOverviewControls();
        });

        document.querySelectorAll('#overviewRoomFilters .ov-filter').forEach(button => {
            button.addEventListener('click', () => {
                overviewState.filter = button.dataset.filter;
                document.querySelectorAll('#overviewRoomFilters .ov-filter').forEach(filter => {
                    const active = filter === button;
                    filter.classList.toggle('active', active);
                    filter.setAttribute('aria-pressed', String(active));
                });
                renderOverviewControls();
            });
        });

        document.getElementById('roomOverviewBtn').addEventListener('click', () => {
            overviewLastFocus = document.activeElement;
            overviewModal.classList.add('open');
            renderRoomOverview();
            requestAnimationFrame(() => document.getElementById('overviewRoomSearch').focus());
        });

        // Use the same preparation for the toolbar and the browser's Ctrl+P.
        window.addEventListener('beforeprint', () => {
            const workspace = document.getElementById('plottingWorkspace');
            workspace.style.setProperty('--print-rows', CONFIG.totalRows);
            const gridHeight = CONFIG.totalRows * CONFIG.rowHeight;
            workspace.querySelectorAll('.block, .lunch-band').forEach(el => {
                el.style.setProperty('--print-top', `${parseFloat(el.style.top) / gridHeight * 100}%`);
                el.style.setProperty('--print-height', `${parseFloat(el.style.height) / gridHeight * 100}%`);
            });
            const hours = blocks.reduce((sum, b) => sum + blockHours(b), 0);
            document.getElementById('printScheduleSummary').textContent =
                `${new Set(blocks.map(b => b.code)).size} subjects · ${blocks.length} meetings · ${round1(hours)} scheduled hours / week`;
            const date = new Intl.DateTimeFormat('en-PH', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Manila' }).format(new Date());
            document.getElementById('printScheduleDate').textContent = `Printed ${date}`;
        });

        document.getElementById('printScheduleBtn')?.addEventListener('click', () => {
            clearSelection();
            window.print();
        });

        function closeRoomOverview() {
            if (!overviewModal.classList.contains('open')) return;
            overviewModal.classList.remove('open');
            if (overviewLastFocus && overviewLastFocus.isConnected) overviewLastFocus.focus();
        }

        document.getElementById('overviewClose').addEventListener('click', closeRoomOverview);
        document.getElementById('overviewCloseBottom').addEventListener('click', closeRoomOverview);
        overviewModal.addEventListener('click', e => { if (e.target === overviewModal) closeRoomOverview(); });
        // ===== Faculty context panel =====
        const facultyPanelModal = document.getElementById('facultyPanelModal');
        const facultyPanelState = { faculty: [], maxUnits: 0, query: '', filter: 'all' };
        let facultyPanelLastFocus = null;

        function facultySetupState(faculty) {
            const availabilitySet = Boolean(faculty.has_submitted_availability || (faculty.availability || []).length);
            const preferencesSet = Boolean((faculty.preferences || []).length);
            return { availabilitySet, preferencesSet, complete: availabilitySet && preferencesSet };
        }

        function facultyAvailabilityDetails(faculty) {
            const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            if (!(faculty.availability || []).length) return '<span class="fd-day is-unavailable">No availability submitted</span>';
            const groups = [];
            days.forEach((day, index) => {
                const row = faculty.availability.find(item => Number(item.day_of_week) === index);
                const available = row && row.is_available && row.is_available != 0;
                const time = available
                    ? `${minutesTo12h(toMinutes((row.start_time || '00:00').slice(0, 5)))}–${minutesTo12h(toMinutes((row.end_time || '00:00').slice(0, 5)))}`
                    : 'Unavailable';
                const previous = groups[groups.length - 1];
                if (previous && previous.time === time) previous.end = day;
                else groups.push({ start: day, end: day, time, available });
            });
            return groups.map(group => `<div class="fd-schedule-row ${group.available ? '' : 'is-unavailable'}"><strong>${group.start}${group.end !== group.start ? `–${group.end}` : ''}</strong><span>${group.time}</span></div>`).join('');
        }

        function facultyPreferenceDetails(faculty) {
            if (!(faculty.preferences || []).length) return '<span class="fd-day is-unavailable">No preferences submitted</span>';
            const levels = [...new Set(['preferred', 'qualified', 'willing', ...faculty.preferences.map(item => item.level)])];
            return levels.map(level => {
                const subjects = faculty.preferences.filter(item => item.level === level);
                if (!subjects.length) return '';
                const chips = subjects.map(preference => `<span class="fd-pref" title="${escapeHtml(preference.title || preference.code)}">${escapeHtml(preference.code)}</span>`);
                return `<div class="fd-preference-group"><div class="fd-preference-heading">${escapeHtml(level)}<span>${subjects.length}</span></div><div class="fd-pref-list">${chips.slice(0, 6).join('')}</div>${chips.length > 6 ? `<details class="fd-more"><summary data-show="Show ${chips.length - 6} more subjects" data-hide="Show fewer subjects">Show ${chips.length - 6} more subjects</summary><div class="fd-pref-list">${chips.slice(6).join('')}</div></details>` : ''}</div>`;
            }).join('');
        }

        // Sliding pill behind the active filter tab.
        const facultyFilters = document.getElementById('facultyPanelFilters');
        const facultyFilterPill = mk('span', 'fd-filter-indicator');
        facultyFilterPill.setAttribute('aria-hidden', 'true');
        facultyFilters.prepend(facultyFilterPill);

        function moveFacultyFilterPill(instant = false) {
            const active = facultyFilters.querySelector('.fd-filter.active');
            if (!active || !active.offsetWidth) return;
            if (instant) facultyFilterPill.style.transition = 'none';
            facultyFilterPill.style.width = active.offsetWidth + 'px';
            facultyFilterPill.style.transform = `translateX(${active.offsetLeft}px)`;
            facultyFilters.classList.add('has-indicator');
            if (instant) {
                void facultyFilterPill.offsetWidth;
                facultyFilterPill.style.transition = '';
            }
        }
        window.addEventListener('resize', () => moveFacultyFilterPill(true));

        // Smoothly open/close a card's details (height + padding + fade).
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        function toggleFacultyDetails(details, open) {
            if (!details) return;
            if (details._anim) { details._anim.cancel(); details._anim = null; }
            if (open === !details.hidden) return;
            if (reduceMotion.matches) { details.hidden = !open; return; }

            const ease = 'cubic-bezier(0.22, 1, 0.36, 1)';
            const collapsed = { height: '0px', paddingTop: '0px', paddingBottom: '0px', opacity: 0 };
            let expandedFrame;

            if (open) {
                details.hidden = false;
                const cs = getComputedStyle(details);
                expandedFrame = { height: details.offsetHeight + 'px', paddingTop: cs.paddingTop, paddingBottom: cs.paddingBottom, opacity: 1 };
            } else {
                const cs = getComputedStyle(details);
                expandedFrame = { height: details.offsetHeight + 'px', paddingTop: cs.paddingTop, paddingBottom: cs.paddingBottom, opacity: 1 };
            }

            details.style.overflow = 'hidden';
            const anim = details.animate(
                open ? [collapsed, expandedFrame] : [expandedFrame, collapsed],
                { duration: open ? 360 : 260, easing: ease }
            );
            details._anim = anim;
            const done = () => {
                if (details._anim !== anim) return;
                details._anim = null;
                details.style.overflow = '';
                if (!open) details.hidden = true;
            };
            anim.onfinish = done;
            anim.oncancel = () => { details.style.overflow = ''; };
        }

        // "Show N more subjects": native <details> can't animate, so drive it.
        document.getElementById('facultyPanelList').addEventListener('click', event => {
            const summary = event.target.closest('.fd-more > summary');
            if (!summary) return;
            event.preventDefault();

            const more = summary.parentElement;
            const body = more.querySelector('.fd-pref-list');
            const opening = !more.open && !more.classList.contains('is-closing');
            if (more._anim) { more._anim.cancel(); more._anim = null; }
            more.classList.remove('is-closing');
            summary.textContent = opening ? summary.dataset.hide : summary.dataset.show;

            if (reduceMotion.matches) { more.open = opening; return; }

            if (opening) more.open = true;
            const height = body.offsetHeight;
            body.style.overflow = 'hidden';
            more.classList.toggle('is-closing', !opening);
            const frames = [{ height: '0px', opacity: 0 }, { height: height + 'px', opacity: 1 }];
            const anim = body.animate(opening ? frames : frames.reverse(), {
                duration: opening ? 320 : 220,
                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
            });
            more._anim = anim;
            anim.onfinish = () => {
                if (more._anim !== anim) return;
                more._anim = null;
                body.style.overflow = '';
                more.classList.remove('is-closing');
                if (!opening) more.open = false;
            };
            anim.oncancel = () => { body.style.overflow = ''; };
        });

        function renderFacultyPanel(animate = false) {
            const list = document.getElementById('facultyPanelList');
            const summary = document.getElementById('facultyPanelSummary');
            const needle = facultyPanelState.query.trim().toLowerCase();
            const visible = facultyPanelState.faculty.filter(faculty => {
                const setup = facultySetupState(faculty);
                const matchesName = !needle || faculty.name.toLowerCase().includes(needle);
                const matchesFilter = facultyPanelState.filter === 'all'
                    || (facultyPanelState.filter === 'ready' && setup.complete)
                    || (facultyPanelState.filter === 'needs-setup' && !setup.complete);
                return matchesName && matchesFilter;
            });

            summary.textContent = `${visible.length} of ${facultyPanelState.faculty.length} faculty shown`;
            list.innerHTML = '';
            list.classList.remove('is-switching');
            if (animate) {
                void list.offsetWidth; // restart the CSS animation
                list.classList.add('is-switching');
            }
            if (!visible.length) {
                list.innerHTML = '<div class="fd-state"><div><strong>No matching faculty</strong><span>Try another name or setup filter.</span></div></div>';
                return;
            }

            visible.forEach((faculty, index) => {
                const setup = facultySetupState(faculty);
                const availableDays = (faculty.availability || []).filter(row => row.is_available && row.is_available != 0).length;
                const preferenceCount = (faculty.preferences || []).length;
                const units = Number(faculty.units || 0);
                const maxUnits = Number(facultyPanelState.maxUnits || 0);
                const loadPercent = maxUnits ? Math.min(100, (units / maxUnits) * 100) : 0;
                const initials = faculty.name.split(/\s+/).map(word => word[0]).slice(0, 2).join('').toUpperCase();
                const employment = faculty.employment_status === 'full_time' ? 'Full-time' : faculty.employment_status === 'part_time' ? 'Part-time' : 'Employment type not set';
                const card = document.createElement('article');
                card.className = `fd-card ${setup.complete ? 'is-ready' : 'needs-setup'}`;
                card.setAttribute('role', 'listitem');
                card.style.setProperty('--i', Math.min(index, 8));
                card.innerHTML = `
                    <div class="fd-card-main">
                        <div class="fd-person">
                            <div class="fd-avatar">${escapeHtml(initials)}</div>
                            <div><div class="fd-name">${escapeHtml(faculty.name)}</div><span class="fd-employment">${employment}</span></div>
                        </div>
                        <div class="fd-fact">
                            <span class="fd-fact-label">Current load</span>
                            <span class="fd-fact-value">${units}${maxUnits ? ` of ${maxUnits}` : ''} units · ${Number(faculty.weekly_hours || 0)} hrs/week</span>
                            ${maxUnits ? `<div class="fd-load-track" aria-hidden="true"><div class="fd-load-fill ${units >= maxUnits ? 'is-full' : ''}" style="width:${loadPercent}%"></div></div>` : ''}
                        </div>
                        <div class="fd-fact">
                            <span class="fd-status ${setup.complete ? 'is-ready' : 'needs-setup'}">${setup.complete ? 'Setup complete' : 'Needs setup'}</span>
                            <span class="fd-fact-label">${setup.availabilitySet ? `${availableDays} days available` : 'Availability missing'} · ${setup.preferencesSet ? `${preferenceCount} subjects` : 'Preferences missing'}</span>
                        </div>
                        <button type="button" class="fd-details-btn" aria-expanded="false" aria-controls="facultyDetails${index}" aria-label="Details for ${escapeHtml(faculty.name)}">View details</button>
                    </div>
                    <div class="fd-details" id="facultyDetails${index}" hidden>
                        <div class="fd-detail-group"><div class="fd-detail-title">Weekly availability</div><div class="fd-day-list">${facultyAvailabilityDetails(faculty)}</div></div>
                        <div class="fd-detail-group"><div class="fd-detail-title">Subject preferences <span>${preferenceCount}</span></div><div class="fd-preferences">${facultyPreferenceDetails(faculty)}</div></div>
                    </div>`;
                const button = card.querySelector('.fd-details-btn');
                const details = card.querySelector('.fd-details');
                button.addEventListener('click', () => {
                    const expanded = button.getAttribute('aria-expanded') === 'true';
                    list.querySelectorAll('.fd-card').forEach(other => {
                        if (other === card) return;
                        other.classList.remove('is-expanded');
                        toggleFacultyDetails(other.querySelector('.fd-details'), false);
                        const toggle = other.querySelector('.fd-details-btn');
                        toggle.setAttribute('aria-expanded', 'false');
                        toggle.textContent = 'View details';
                    });
                    card.classList.toggle('is-expanded', !expanded);
                    button.setAttribute('aria-expanded', String(!expanded));
                    button.textContent = expanded ? 'View details' : 'Hide details';
                    toggleFacultyDetails(details, !expanded);
                });
                list.appendChild(card);
            });
        }

        async function loadFacultyPanel() {
            const list = document.getElementById('facultyPanelList');
            const results = document.getElementById('facultyPanelResults');
            results.setAttribute('aria-busy', 'true');
            facultyPanelState.faculty = [];
            ['facultyMetricTotal', 'facultyMetricAvailable', 'facultyMetricPreferences'].forEach(id => {
                document.getElementById(id).textContent = '—';
            });
            document.getElementById('facultyPanelSummary').textContent = 'Loading faculty…';
            document.getElementById('facultyPanelCount').textContent = 'Loading…';
            list.innerHTML = Array.from({ length: 4 }, () => '<div class="fd-skeleton" aria-hidden="true"></div>').join('');

            try {
                const response = await fetch(CONFIG.facultyPanelUrl + '?for_academic_year=' + encodeURIComponent(CONTEXT.academic_year), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
                if (!response.ok) throw new Error('Faculty request failed');
                const data = await response.json();

                facultyPanelState.faculty = (data.faculty || []).slice().sort((a, b) => a.name.localeCompare(b.name));
                facultyPanelState.maxUnits = data.max_units || 0;
                const availabilityCount = facultyPanelState.faculty.filter(faculty => facultySetupState(faculty).availabilitySet).length;
                const preferenceCount = facultyPanelState.faculty.filter(faculty => facultySetupState(faculty).preferencesSet).length;

                document.getElementById('facultyPanelSub').textContent = data.upcoming_term
                    ? `Availability & preferences · ${data.upcoming_term}`
                    : 'No upcoming term configured yet — faculty cannot submit availability until an admin adds one.';
                document.getElementById('facultyPanelCount').textContent = `${facultyPanelState.faculty.length} faculty`;
                document.getElementById('facultyMetricTotal').textContent = facultyPanelState.faculty.length;
                document.getElementById('facultyMetricAvailable').textContent = availabilityCount;
                document.getElementById('facultyMetricPreferences').textContent = preferenceCount;
                document.getElementById('facultyLoadTerm').textContent = `Teaching load · ${data.active_year || 'Active term'}`;
                results.setAttribute('aria-busy', 'false');

                if (!facultyPanelState.faculty.length) {
                    document.getElementById('facultyPanelSummary').textContent = '0 faculty';
                    list.innerHTML = '<div class="fd-state"><div><strong>No faculty found</strong><span>No faculty members are assigned to this department yet.</span></div></div>';
                    return;
                }
                renderFacultyPanel();
            } catch (e) {
                results.setAttribute('aria-busy', 'false');
                document.getElementById('facultyPanelSummary').textContent = '';
                document.getElementById('facultyPanelCount').textContent = 'Unavailable';
                list.innerHTML = '<div class="fd-state"><div><strong>Faculty could not be loaded</strong><span>Check your connection, then close and reopen this panel.</span></div></div>';
            }
        }

        document.getElementById('facultyPanelBtn').addEventListener('click', () => {
            facultyPanelLastFocus = document.activeElement;
            facultyPanelState.query = '';
            facultyPanelState.filter = 'all';
            document.getElementById('facultyPanelSearch').value = '';
            document.querySelectorAll('#facultyPanelFilters .fd-filter').forEach(button => {
                const active = button.dataset.filter === 'all';
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', String(active));
            });
            facultyPanelModal.classList.add('open');
            requestAnimationFrame(() => moveFacultyFilterPill(true));
            loadFacultyPanel();
            requestAnimationFrame(() => document.getElementById('facultyPanelSearch').focus());
        });

        document.getElementById('facultyPanelSearch').addEventListener('input', event => {
            facultyPanelState.query = event.target.value;
            renderFacultyPanel();
        });
        document.querySelectorAll('#facultyPanelFilters .fd-filter').forEach(button => {
            button.addEventListener('click', () => {
                facultyPanelState.filter = button.dataset.filter;
                document.querySelectorAll('#facultyPanelFilters .fd-filter').forEach(filter => {
                    const active = filter === button;
                    filter.classList.toggle('active', active);
                    filter.setAttribute('aria-pressed', String(active));
                });
                moveFacultyFilterPill();
                renderFacultyPanel(true);
            });
        });

        function closeFacultyPanel() {
            if (!facultyPanelModal.classList.contains('open')) return;
            facultyPanelModal.classList.remove('open');
            if (facultyPanelLastFocus && facultyPanelLastFocus.isConnected) facultyPanelLastFocus.focus();
        }

        document.getElementById('facultyPanelClose').addEventListener('click', closeFacultyPanel);
        document.getElementById('facultyPanelCloseBottom').addEventListener('click', closeFacultyPanel);
        facultyPanelModal.addEventListener('click', e => { if (e.target === facultyPanelModal) closeFacultyPanel(); });

// ---------- side panel ----------
// One panel, two tabs. "Inspector" follows whatever is selected or being
// placed; "Assistant" holds the suggestions. Nothing here opens a modal.
const ui = {
    tab: 'inspector',
    mode: 'overview',      // overview | subject | block | place | conflict
    subjectCode: null,     // class selected in the queue (or owning the selected block)
    blockKey: null,        // plotted meeting selected on the grid
    place: null,           // placement in progress, see beginPlacement()
    conflict: null,        // last rejected drop, see showConflict()
    moveOpen: false,       // "Move" form open on the block view
    draft: { day: 0, row: 0 },
};

const workspace = $('plottingWorkspace');
const inspectorBody = $('inspectorBody');

// The underline glides to the selected tab, and the incoming panel slides in
// from the side the tab sits on, so switching reads as movement.
const tabList = document.querySelector('.panel-tabs');
const tabIndicator = mk('span', 'panel-tabs__indicator');
tabIndicator.setAttribute('aria-hidden', 'true');
tabList.appendChild(tabIndicator);

function moveTabIndicator(animate = true) {
    const tab = $('tab-' + ui.tab);
    if (!tab || !tab.offsetWidth) return;
    if (!animate) tabIndicator.style.transition = 'none';
    tabIndicator.style.width = tab.offsetWidth + 'px';
    tabIndicator.style.transform = 'translateX(' + tab.offsetLeft + 'px)';
    if (!animate) {
        void tabIndicator.offsetWidth;
        tabIndicator.style.transition = '';
    }
}

function setTab(name) {
    const previous = ui.tab;
    ui.tab = name;
    ['inspector', 'assistant'].forEach(n => {
        const on = n === name;
        $('tab-' + n).setAttribute('aria-selected', String(on));
        $('tab-' + n).tabIndex = on ? 0 : -1;
        $('panel-' + n).hidden = !on;
    });

    moveTabIndicator(previous !== name);

    if (previous !== name) {
        const panel = $('panel-' + name);
        panel.dataset.dir = name === 'assistant' ? 'right' : 'left';
        panel.classList.remove('is-entering');
        void panel.offsetWidth;
        panel.classList.add('is-entering');
    }
}

// Badge counts and fonts change tab widths after the first paint.
if (window.ResizeObserver) {
    const watcher = new ResizeObserver(() => moveTabIndicator(false));
    document.querySelectorAll('.panel-tab').forEach(t => watcher.observe(t));
}
window.addEventListener('resize', () => moveTabIndicator(false));

document.querySelectorAll('.panel-tab').forEach(tab => {
    tab.addEventListener('click', () => setTab(tab.dataset.tab));
    tab.addEventListener('keydown', e => {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        const next = tab.dataset.tab === 'inspector' ? 'assistant' : 'inspector';
        setTab(next);
        $('tab-' + next).focus();
    });
});

// On narrower screens the panel is a drawer; it opens whenever there is
// something to show and can always be closed.
function openPanel() {
    workspace.dataset.panel = 'open';
    $('panelToggle').setAttribute('aria-expanded', 'true');
}

function closePanel() {
    workspace.dataset.panel = 'closed';
    $('panelToggle').setAttribute('aria-expanded', 'false');
}

$('panelToggle').addEventListener('click', () => (workspace.dataset.panel === 'open' ? closePanel() : openPanel()));
$('panelClose').addEventListener('click', closePanel);

// ---------- selection ----------
function updateSelectionClasses() {
    document.querySelectorAll('.day-col .block:not(.is-other):not(.is-pending)').forEach(el => {
        const key = el.dataset.key;
        const selected = key === ui.blockKey;
        el.classList.toggle('is-selected', selected);
        el.setAttribute('aria-pressed', String(selected));
        el.classList.toggle('is-dim', !!ui.subjectCode && key.split('|')[0] !== ui.subjectCode);
    });
}

function syncSelection() {
    if (ui.blockKey && !blocks.some(b => blockKey(b) === ui.blockKey)) {
        ui.blockKey = null;
        if (ui.mode === 'block') ui.mode = ui.subjectCode ? 'subject' : 'overview';
    }
    if (ui.mode === 'place' && !ui.place) ui.mode = 'overview';
}

function abandonPlacement() {
    if (!ui.place) return;
    ui.place = null;
    document.querySelectorAll('.block.is-pending').forEach(el => el.remove());
    if (ui.mode === 'place') ui.mode = 'overview';
}

function selectSubject(code) {
    abandonPlacement();
    ui.subjectCode = code;
    ui.blockKey = null;
    ui.conflict = null;
    ui.moveOpen = false;
    ui.mode = 'subject';
    ui.draft = { day: 0, row: 0, slots: 1 };
    setTab('inspector');
    openPanel();
    renderInspector();
    renderQueue();
    updateSelectionClasses();
}

function selectBlock(key) {
    const b = blocks.find(x => blockKey(x) === key);
    if (!b) return;

    abandonPlacement();
    ui.blockKey = key;
    ui.subjectCode = b.code;
    ui.conflict = null;
    ui.moveOpen = false;
    ui.mode = 'block';
    setTab('inspector');
    openPanel();
    renderInspector();
    renderQueue();
    updateSelectionClasses();
}

function clearSelection() {
    if (!ui.blockKey && !ui.subjectCode && ui.mode !== 'conflict') return;
    abandonPlacement();
    ui.blockKey = null;
    ui.subjectCode = null;
    ui.conflict = null;
    ui.moveOpen = false;
    ui.mode = 'overview';
    renderInspector();
    renderQueue();
    updateSelectionClasses();
}

$('boardScroll').addEventListener('click', e => {
    if (!e.target.closest('.block')) clearSelection();
});

function showConflict(conflict) {
    abandonPlacement();
    ui.conflict = conflict;
    ui.mode = 'conflict';
    setTab('inspector');
    openPanel();
    renderInspector();
    if (conflict.existing) flashBlock(conflict.existing);
    announce(`${conflict.title}. ${conflict.message}`);
}

function refreshInspector() {
    if (ui.mode === 'place') {
        refreshPlacementLists();
        return;
    }
    if (ui.mode === 'conflict') return;
    renderInspector();
}

// ---------- shared builders ----------
function typeLabel(subject) {
    return subject && subject.is_lab ? 'Laboratory' : 'Lecture';
}

function insHeader(kicker, subject, onClose, closeLabel) {
    const head = mk('header', 'ins-head');
    const text = mk('div', 'ins-head__text');
    text.appendChild(mk('div', 'ins-kicker', kicker));
    const line = mk('h3', 'ins-title');
    line.appendChild(mk('span', '', subject.code));
    line.appendChild(mk('span', 'ins-type' + (subject.is_lab ? ' is-lab' : ''), typeLabel(subject)));
    text.append(line, mk('p', 'ins-sub', subject.title));
    head.appendChild(text);

    if (onClose) {
        const close = mk('button', 'icon-btn');
        close.type = 'button';
        close.setAttribute('aria-label', closeLabel);
        close.appendChild(mk('span', 'material-symbols-outlined', 'close'));
        close.querySelector('span').setAttribute('aria-hidden', 'true');
        close.addEventListener('click', onClose);
        head.appendChild(close);
    }
    return head;
}

function factRow(label, content, action) {
    const row = mk('div', 'fact');
    row.appendChild(mk('dt', '', label));
    const value = mk('dd');
    value.append(content);
    row.appendChild(value);
    if (action) row.appendChild(action);
    return row;
}

function textButton(label, onClick, className = '') {
    const btn = mk('button', 'text-btn ' + className, label);
    btn.type = 'button';
    btn.addEventListener('click', onClick);
    return btn;
}

function hoursMeter(stats) {
    const wrap = mk('div', 'hours-meter');
    const bar = mk('div', 'q-progress');
    const fill = document.createElement('i');
    fill.style.width = (stats.weekly > 0 ? Math.min(100, stats.plotted / stats.weekly * 100) : 0) + '%';
    bar.appendChild(fill);
    const caption = stats.weekly > 0
        ? `${round1(stats.plotted)} of ${round1(stats.weekly)} hrs plotted · ${stats.over ? fmtHours(stats.plotted - stats.weekly) + ' over' : stats.complete ? 'complete' : fmtHours(stats.remaining) + ' left'}`
        : `${round1(stats.plotted)} hrs plotted · weekly hours not set`;
    wrap.append(bar, mk('div', 'hours-meter__text', caption));
    return wrap;
}

function issueList(issues, block) {
    const list = mk('ul', 'issue-list');
    issues.forEach(issue => {
        const li = mk('li', 'issue');
        li.dataset.level = issue.level;
        li.appendChild(mk('strong', '', issue.title));
        li.appendChild(mk('span', '', issue.detail));

        if (block) {
            const key = blockKey(block);
            const fix = {
                'No faculty assigned': ['Assign faculty', () => beginFacultyChange(key)],
                'Faculty conflict': ['Choose another faculty', () => beginFacultyChange(key)],
                'Outside faculty availability': ['Choose another faculty', () => beginFacultyChange(key)],
                'Room type': ['Change room', () => beginRoomChange(key)],
                'Room no longer available': ['Change room', () => beginRoomChange(key)],
                'Room capacity': ['Change room', () => beginRoomChange(key)],
                'Room conflict': ['Change room', () => beginRoomChange(key)],
                'Section conflict': ['Move class', () => { ui.moveOpen = true; renderInspector(); }],
            }[issue.title];
            if (fix) li.appendChild(textButton(fix[0], fix[1], 'issue__fix'));
        }
        list.appendChild(li);
    });
    return list;
}

function slotSelects(subject, draft, onChange) {
    const row = mk('div', 'slot-form');

    const dayField = mk('label', 'field');
    dayField.appendChild(mk('span', '', 'Day'));
    const day = mk('select');
    WEEK.forEach((d, i) => day.appendChild(new Option(d.day, String(i), false, i === draft.day)));
    dayField.appendChild(day);

    const timeField = mk('label', 'field');
    timeField.appendChild(mk('span', '', 'Starts'));
    const time = mk('select');
    const slots = draft.slots || 1;
    for (let r = 0; r <= CONFIG.totalRows - slots; r++) {
        time.appendChild(new Option(rowRange(r, r + slots), String(r), false, r === draft.row));
    }
    timeField.appendChild(time);

    const sync = () => {
        draft.day = Number(day.value);
        draft.row = Number(time.value);
        onChange();
    };
    day.addEventListener('change', sync);
    time.addEventListener('change', sync);

    row.append(dayField, timeField);
    return row;
}

/** "How many 30-min slots to place at once" +/- control, so building a longer meeting doesn't need repeated drags. */
function slotStepper(draft, maxSlots, onChange) {
    draft.slots = Math.min(Math.max(draft.slots || 1, 1), maxSlots);

    const field = mk('label', 'field');
    field.appendChild(mk('span', '', 'Length'));
    const row = mk('div', 'stepper');
    const minus = mk('button', 'stepper__btn', '−');
    minus.type = 'button';
    minus.setAttribute('aria-label', 'Shorter');
    const value = mk('span', 'stepper__value', fmtHours(draft.slots * 0.5));
    const plus = mk('button', 'stepper__btn', '+');
    plus.type = 'button';
    plus.setAttribute('aria-label', 'Longer');

    minus.disabled = draft.slots <= 1;
    plus.disabled = draft.slots >= maxSlots;
    minus.addEventListener('click', () => { draft.slots = Math.max(1, draft.slots - 1); onChange(); });
    plus.addEventListener('click', () => { draft.slots = Math.min(maxSlots, draft.slots + 1); onChange(); });

    row.append(minus, value, plus);
    field.appendChild(row);
    return field;
}

/** Live pass/fail line for a day/time chosen from the selects. */
function slotVerdict(result) {
    const line = mk('p', 'verdict');
    if (result.hard) {
        line.dataset.level = 'error';
        line.textContent = `${result.hard.title}. ${result.hard.message}`;
    } else if (result.warnings.length) {
        line.dataset.level = 'warn';
        line.textContent = result.warnings.join(' ');
    } else {
        line.dataset.level = 'ok';
        line.textContent = `Free. ${plural(result.roomsFree, 'room')} available at this time.`;
    }
    return line;
}

// ---------- inspector views ----------
// Room and faculty choices open in a modal: there is room to compare
// options, and the side panel keeps showing the class underneath.
const placeModal = $('placeModal');

function syncPlaceModal(placing) {
    const isOpen = placeModal.classList.contains('open');
    if (placing && !isOpen) placeModal.classList.add('open');
    if (!placing && isOpen) {
        placeModal.classList.remove('open');
        $('placeBody').replaceChildren();
    }
}

placeModal.addEventListener('click', e => { if (e.target === placeModal) cancelPlacement(); });

function renderInspector() {
    inspectorBody.replaceChildren();

    const placing = ui.mode === 'place' && !!ui.place;
    syncPlaceModal(placing);
    if (placing) {
        $('placeBody').replaceChildren();
        renderPlacement();
    }

    const mode = placing ? (ui.blockKey ? 'block' : ui.subjectCode ? 'subject' : 'overview') : ui.mode;
    inspectorBody.dataset.mode = mode;

    if (mode === 'conflict' && ui.conflict) renderConflictView();
    else if (mode === 'block' && ui.blockKey) renderBlockView();
    else if (mode === 'subject' && ui.subjectCode) renderSubjectView();
    else renderOverview();
}

function renderOverview() {
    const totals = sectionTotals();
    const issues = collectIssues();
    const box = inspectorBody;

    const head = mk('header', 'ins-head');
    const text = mk('div', 'ins-head__text');
    text.append(mk('div', 'ins-kicker', 'Section'), mk('h3', 'ins-title', CONTEXT.section + ' · ' + CONTEXT.year_level), mk('p', 'ins-sub', CONTEXT.program));
    head.appendChild(text);
    box.appendChild(head);

    const stats = mk('dl', 'stat-row');
    [
        ['Classes complete', `${totals.complete} / ${totals.subjects}`],
        ['Hours plotted', `${round1(totals.plotted)} / ${round1(totals.weekly)}`],
        ['Need attention', String(issues.length)],
    ].forEach(([label, value]) => {
        const item = mk('div', 'stat');
        item.append(mk('dt', '', label), mk('dd', '', value));
        stats.appendChild(item);
    });
    box.appendChild(stats);

    if (blocks.length === 0) {
        const empty = mk('div', 'ins-empty');
        empty.append(mk('strong', '', SUBJECTS.length ? 'Nothing is plotted yet' : 'No classes for this term'),
            mk('p', '', SUBJECTS.length ? 'Drag a class from the queue onto the timetable to begin plotting.' : 'Set up the curriculum for this year level to start plotting.'));
        box.appendChild(empty);
        return;
    }

    if (issues.length === 0) {
        const done = totals.subjects > 0 && totals.complete === totals.subjects;
        const ok = mk('div', 'ins-empty');
        ok.append(mk('strong', '', done ? 'Every class is fully scheduled' : 'No conflicts or warnings'),
            mk('p', '', done ? 'All classes for this section have been fully scheduled.' : 'Continue plotting the remaining classes from the queue.'));
        box.appendChild(ok);
        return;
    }

    const section = mk('section', 'ins-section');
    section.id = 'attentionList';
    section.appendChild(mk('h4', 'ins-heading', `Needs attention (${issues.length})`));
    const list = mk('ul', 'attention-list');
    issues.forEach(issue => {
        const li = mk('li');
        const btn = mk('button', 'attention');
        btn.type = 'button';
        btn.dataset.level = issue.level;
        const where = issue.block ? `${issue.block.code} · ${dayShort(issue.block.day)} ${blockRange(issue.block)}` : issue.code;
        btn.append(mk('span', 'attention__where', where), mk('strong', '', issue.title), mk('span', 'attention__detail', issue.detail));
        btn.addEventListener('click', () => {
            if (issue.block) selectBlock(blockKey(issue.block));
            else selectSubject(issue.code);
        });
        li.appendChild(btn);
        list.appendChild(li);
    });
    section.appendChild(list);
    box.appendChild(section);
}

function renderSubjectView() {
    const subject = SUBJECT_BY_CODE.get(ui.subjectCode);
    if (!subject) { ui.mode = 'overview'; renderOverview(); return; }

    const stats = subjectStats(subject.code);
    const box = inspectorBody;
    box.appendChild(insHeader('Class', subject, clearSelection, 'Close details'));

    const facts = mk('dl', 'facts');
    facts.append(
        factRow('Units', document.createTextNode(String(parseFloat(subject.units) || 0))),
        factRow('Meeting length', document.createTextNode(fmtHours(subject.duration))),
        factRow('Faculty', stats.count === 0 ? mk('span', 'muted', 'Chosen when you plot') : stats.faculty ? document.createTextNode(stats.faculty) : mk('span', 'is-warn', 'Not assigned'))
    );
    box.append(facts, hoursMeter(stats));

    if (stats.count) {
        const section = mk('section', 'ins-section');
        section.appendChild(mk('h4', 'ins-heading', `Plotted meetings (${stats.count})`));
        const list = mk('ul', 'meeting-list');
        stats.meetings.slice().sort((a, b) => a.day - b.day || a.start.localeCompare(b.start)).forEach(b => {
            const li = mk('li');
            const btn = mk('button', 'meeting');
            btn.type = 'button';
            btn.append(mk('strong', '', `${dayShort(b.day)} ${blockRange(b)}`), mk('span', '', b.room));
            btn.addEventListener('click', () => selectBlock(blockKey(b)));
            li.appendChild(btn);
            list.appendChild(li);
        });
        section.appendChild(list);
        box.appendChild(section);
    }

    const section = mk('section', 'ins-section');
    section.appendChild(mk('h4', 'ins-heading', 'Plot a meeting'));

    if (stats.complete || stats.count >= MAX_MEETINGS) {
        section.appendChild(mk('p', 'muted', stats.complete
            ? 'This class has all of its required hours. Remove a meeting to plot it differently.'
            : `${subject.code} already meets ${MAX_MEETINGS} times a week, which is the maximum.`));
        box.appendChild(section);
        return;
    }

    section.appendChild(mk('p', 'muted', 'Drag the class onto the timetable, or pick a time and length here.'));

    // How many 30-min slots one placement can cover — capped to what's still
    // needed, so the stepper can't place more than the class requires.
    const maxSlots = Math.max(1, Math.min(12, stats.remaining !== null ? Math.ceil(stats.remaining / 0.5) : 6));
    ui.draft.row = Math.min(ui.draft.row, CONFIG.totalRows - maxSlots);

    const verdict = mk('div', 'verdict-slot');
    const faculty = assignedFaculty(subject.code);
    const evaluate = () => evaluatePlacement({ subject, day: ui.draft.day, startRow: ui.draft.row, endRow: ui.draft.row + ui.draft.slots, prof: faculty && faculty.name });
    const go = mk('button', 'btn btn-primary', 'Choose room');
    go.type = 'button';

    const update = () => {
        const result = evaluate();
        verdict.replaceChildren(slotVerdict(result));
        go.disabled = !!result.hard;
    };

    section.appendChild(slotStepper(ui.draft, maxSlots, () => renderInspector()));
    section.appendChild(slotSelects(subject, ui.draft, update));
    section.append(verdict, go);
    go.addEventListener('click', () => {
        const range = { startRow: ui.draft.row, endRow: ui.draft.row + ui.draft.slots };
        const touching = touchingOwnBlocks(subject.code, ui.draft.day, range.startRow, range.endRow);
        if (touching.length) { mergeIntoBlock(touching, subject, ui.draft.day, range.startRow, range.endRow); return; }
        const result = evaluate();
        if (result.hard) { showConflict(result.hard); return; }
        beginPlacement({ kind: 'new', subject, day: ui.draft.day, ...range, warnings: result.warnings });
    });
    update();
    box.appendChild(section);
}

function renderBlockView() {
    const b = blocks.find(x => blockKey(x) === ui.blockKey);
    if (!b) { ui.mode = 'overview'; renderOverview(); return; }

    const subject = SUBJECT_BY_CODE.get(b.code) || { code: b.code, title: b.title, is_lab: false, units: '0', duration: blockHours(b) };
    const stats = subjectStats(b.code);
    const room = ROOM_BY_NAME.get(b.room) || null;
    const issues = issueMap.get(blockKey(b)) || [];
    const box = inspectorBody;
    const key = blockKey(b);
    const isOwned = OWNED_CODES.has(b.code);

    box.appendChild(insHeader('Plotted class', subject, clearSelection, 'Close details'));

    if (!isOwned) {
        const notice = mk('p', 'muted');
        notice.textContent = 'This class is owned by another department — only they can move it, change its room, assign faculty, or remove it. Use Shared Subject Requests if you need a change.';
        box.appendChild(notice);
    }

    const facts = mk('dl', 'facts');

    facts.appendChild(factRow('Schedule',
        mk('span', '', `${dayName(b.day)} · ${blockRange(b)}`),
        isOwned ? textButton(ui.moveOpen ? 'Cancel' : 'Move', () => {
            ui.moveOpen = !ui.moveOpen;
            ui.draft = { day: Number(b.day), row: blockStartRow(b) };
            renderInspector();
        }) : null));

    const roomKind = room ? `${room.is_laboratory ? 'Laboratory' : (room.type || 'Room')}${room.capacity ? ' · ' + room.capacity + ' seats' : ''}` : '';
    const roomValue = mk('span', '', b.room);
    if (roomKind) roomValue.append(mk('span', 'fact__sub', roomKind));
    facts.appendChild(factRow('Room', roomValue, isOwned ? textButton('Change', () => beginRoomChange(key)) : null));

    facts.appendChild(factRow('Faculty',
        b.prof ? mk('span', '', b.prof) : mk('span', 'is-warn', 'Not assigned'),
        isOwned ? textButton(b.prof ? 'Change' : 'Assign', () => beginFacultyChange(key)) : null));

    facts.appendChild(factRow('Section', document.createTextNode(`${CONTEXT.section} · ${CONTEXT.year_level}`)));
    facts.appendChild(factRow('Units', document.createTextNode(String(parseFloat(subject.units) || 0))));
    facts.appendChild(factRow('This meeting', meetingLengthControl(b, subject, stats, isOwned)));
    box.appendChild(facts);

    if (isOwned && ui.moveOpen) box.appendChild(moveForm(b, subject));

    if (b.faculty_id) {
        const f = cachedFaculty(b.faculty_id);
        if (f) {
            const fit = availabilityFit(f, Number(b.day), b.start, b.end);
            const line = mk('p', 'availability');
            line.dataset.fit = fit.fit;
            line.textContent = fit.fit === 'ok'
                ? `Available ${fit.from && fit.to ? rangeLabel(toMinutes(fit.from), toMinutes(fit.to)) : 'all day'} on ${dayName(b.day)}.`
                : availabilityText(b.prof, Number(b.day), fit);
            box.appendChild(line);
        }
    }

    box.appendChild(hoursMeter(stats));

    const attention = mk('section', 'ins-section');
    attention.appendChild(mk('h4', 'ins-heading', issues.length ? `Needs attention (${issues.length})` : 'Checks'));
    if (issues.length) attention.appendChild(issueList(issues, isOwned ? b : null));
    else attention.appendChild(mk('p', 'muted', 'No conflicts or warnings for this class.'));
    box.appendChild(attention);

    if (isOwned) {
        const actions = mk('div', 'ins-actions');
        const remove = mk('button', 'btn btn-danger-ghost', 'Remove from schedule');
        remove.type = 'button';
        remove.addEventListener('click', () => removeBlock(key));
        actions.appendChild(remove);
        box.appendChild(actions);
    }
}

function moveForm(b, subject) {
    const form = mk('section', 'ins-section move-form');
    form.appendChild(mk('h4', 'ins-heading', 'Move this meeting'));
    const key = blockKey(b);
    const slots = blockEndRow(b) - blockStartRow(b);
    const verdict = mk('div', 'verdict-slot');
    const apply = mk('button', 'btn btn-primary', 'Move here');
    apply.type = 'button';

    const evaluate = () => evaluatePlacement({ subject, day: ui.draft.day, startRow: ui.draft.row, endRow: ui.draft.row + slots, ignoreKey: key, roomName: b.room, prof: b.prof });
    const update = () => {
        const unchanged = ui.draft.day === Number(b.day) && ui.draft.row === blockStartRow(b);
        const result = evaluate();
        verdict.replaceChildren(unchanged ? mk('p', 'muted', 'Pick a new day or time.') : slotVerdict(result));
        apply.disabled = unchanged || !!result.hard;
    };

    // A meeting keeps its own length, so the selects are built from it.
    const shim = { duration: slots / 2 };
    form.appendChild(slotSelects(shim, ui.draft, update));
    form.append(verdict, apply);
    apply.addEventListener('click', async () => {
        const moved = await moveBlock(key, ui.draft.day, ui.draft.row, ui.draft.row + slots);
        if (moved) { ui.moveOpen = false; renderInspector(); }
    });
    update();
    return form;
}

function renderConflictView() {
    const c = ui.conflict;
    const box = inspectorBody;

    const head = mk('header', 'ins-head');
    const text = mk('div', 'ins-head__text');
    text.append(mk('div', 'ins-kicker', 'Cannot plot here'), mk('h3', 'ins-title', c.title));
    head.appendChild(text);
    const close = mk('button', 'icon-btn');
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss');
    const glyph = mk('span', 'material-symbols-outlined', 'close');
    glyph.setAttribute('aria-hidden', 'true');
    close.appendChild(glyph);
    close.addEventListener('click', dismissConflict);
    head.appendChild(close);
    box.appendChild(head);

    box.appendChild(mk('p', 'conflict-message', c.message));

    const cards = mk('div', 'conflict-cards');
    const a = c.attempted;
    const trying = mk('div', 'conflict-card');
    trying.append(mk('div', 'conflict-card__label', 'You are plotting'),
        mk('strong', '', `${a.subject.code} · ${a.subject.title}`),
        mk('span', '', `${dayName(a.day)} · ${rowRange(a.startRow, a.endRow)}`));
    cards.appendChild(trying);

    if (c.existing) {
        const e = c.existing;
        const has = mk('div', 'conflict-card is-existing');
        has.append(mk('div', 'conflict-card__label', 'Already scheduled'),
            mk('strong', '', `${e.code} · ${e.title}`),
            mk('span', '', `${dayName(e.day)} · ${blockRange(e)}`),
            mk('span', '', `${e.section || CONTEXT.section} · ${e.room}${e.prof ? ' · ' + e.prof : ''}`));
        cards.appendChild(has);
    }
    box.appendChild(cards);

    const actions = mk('div', 'ins-actions');
    const ok = mk('button', 'btn btn-secondary', 'Dismiss');
    ok.type = 'button';
    ok.addEventListener('click', dismissConflict);
    actions.appendChild(ok);
    box.appendChild(actions);
}

function dismissConflict() {
    ui.conflict = null;
    ui.mode = ui.blockKey ? 'block' : ui.subjectCode ? 'subject' : 'overview';
    renderInspector();
}

$('issuesBtn').addEventListener('click', () => {
    clearSelection();
    setTab('inspector');
    openPanel();
    const list = $('attentionList');
    if (list) list.scrollIntoView({ block: 'start', behavior: 'smooth' });
});

// ---------- placement: room, then faculty ----------
// Dropping a class (or choosing "Change" on a plotted one) opens the picker
// in the side panel while the grid keeps showing exactly where the class is
// going. Nothing is committed until the last step is answered.
const FACULTY_STATUS = {
    preferred:   'Preferred',
    ok:          'Available',
    unavailable: 'Unavailable',
    noavail:     'Not set',
    conflict:    'Conflict',
};

function beginPlacement(place) {
    abandonPlacement();
    ui.place = Object.assign({
        step: place.kind === 'faculty' ? 'faculty' : 'room',
        roomName: null,
        faculty: undefined,
        warnings: [],
        roomQuery: '', roomFilter: 'all', roomFloor: 'all', pendingRoom: null,
        facultyQuery: '', facultyFilter: 'all', facultyState: 'idle', facultyData: null, openWeek: null,
    }, place);
    ui.conflict = null;
    ui.moveOpen = false;
    ui.mode = 'place';
    setTab('inspector');
    renderInspector();
    if (ui.place.kind === 'new') renderBlocks();
    if (ui.place.step === 'faculty') loadFacultyForPicker();
    else loadFaculty(true).catch(() => {}); // ready by the time a room is chosen
    focusPicker();
}

function beginRoomChange(key) {
    const b = blocks.find(x => blockKey(x) === key);
    if (!b) return;
    const subject = SUBJECT_BY_CODE.get(b.code);
    if (!subject) return;
    beginPlacement({ kind: 'room', key, subject, day: Number(b.day), startRow: blockStartRow(b), endRow: blockEndRow(b), roomName: b.room, currentRoom: b.room });
}

function beginFacultyChange(key) {
    const b = blocks.find(x => blockKey(x) === key);
    if (!b) return;
    const subject = SUBJECT_BY_CODE.get(b.code);
    if (!subject) return;
    beginPlacement({ kind: 'faculty', key, subject, day: Number(b.day), startRow: blockStartRow(b), endRow: blockEndRow(b), roomName: b.room });
}

function cancelPlacement() {
    if (!ui.place) return;
    const key = ui.place.key;
    abandonPlacement();
    ui.mode = key && ui.blockKey ? 'block' : ui.subjectCode ? 'subject' : 'overview';
    renderInspector();
}

function focusPicker() {
    requestAnimationFrame(() => {
        const input = $('placeBody').querySelector('.step.is-open .picker__search input');
        if (input) input.focus({ preventScroll: true });
    });
}

function renderPlacement() {
    const p = ui.place;
    const box = $('placeBody');
    const kicker = p.kind === 'new' ? 'Plotting' : p.kind === 'room' ? 'Change room' : 'Change faculty';
    box.appendChild(insHeader(kicker, p.subject, cancelPlacement, 'Cancel'));

    const facts = mk('dl', 'facts');
    facts.appendChild(factRow('When', document.createTextNode(`${dayName(p.day)} · ${rowRange(p.startRow, p.endRow)}`)));
    if (p.kind === 'faculty') facts.appendChild(factRow('Room', document.createTextNode(p.roomName)));
    box.appendChild(facts);

    p.warnings.forEach(text => {
        const note = mk('p', 'verdict', text);
        note.dataset.level = 'warn';
        box.appendChild(note);
    });

    // Steps are stacked cards. The active one is open; finished ones fold
    // down to a single summary line, and the next one unfolds beneath.
    const steps = mk('ol', 'steps');
    if (p.kind !== 'faculty') steps.appendChild(stepCard('room', p.kind === 'new' ? '1' : ''));
    if (p.kind !== 'room') steps.appendChild(stepCard('faculty', p.kind === 'new' ? '2' : ''));
    box.appendChild(steps);

    paintSteps(p, false);
    box.appendChild(placeActions(p));
    refreshPlacementLists();
}

function stepCard(kind, number) {
    const card = mk('li', 'step');
    card.id = kind === 'room' ? 'stepRoom' : 'stepFaculty';
    card.dataset.step = kind;
    card.dataset.number = number;
    card.append(mk('div', 'step__head'), mk('div', 'step__body'));
    card.querySelector('.step__body').appendChild(mk('div', 'step__inner'));
    return card;
}

function stepHead(kind, number, p) {
    const done = kind === 'room' && p.kind === 'new' && p.step === 'faculty';
    const head = mk('div', 'step__head');
    head.id = (kind === 'room' ? 'stepRoom' : 'stepFaculty') + 'Head';

    const num = mk('span', 'step__num');
    if (done) {
        const check = mk('span', 'material-symbols-outlined', 'check');
        check.setAttribute('aria-hidden', 'true');
        num.appendChild(check);
    } else if (number) {
        num.textContent = number;
    } else {
        num.hidden = true;
    }
    head.append(num, mk('strong', '', kind === 'room' ? 'Room' : 'Faculty'));

    if (done) {
        head.appendChild(mk('span', 'step__value', p.roomName));
        head.appendChild(textButton('Change', () => showStep('room')));
    } else if (kind === 'faculty' && p.step !== 'faculty') {
        const inherited = p.kind === 'new' ? assignedFaculty(p.subject.code) : null;
        head.appendChild(mk('span', 'step__value muted', inherited ? `${inherited.name} (already teaches ${p.subject.code})` : 'Chosen after the room'));
    }
    return head;
}

/** Opens the active card and folds the others; the CSS animates the change. */
function paintSteps(p, animate) {
    ['room', 'faculty'].forEach(kind => {
        const card = $(kind === 'room' ? 'stepRoom' : 'stepFaculty');
        if (!card) return;

        const open = p.step === kind;
        const inner = card.querySelector('.step__inner');
        card.querySelector('.step__head').replaceWith(stepHead(kind, card.dataset.number, p));
        card.classList.toggle('is-done', kind === 'room' && p.kind === 'new' && p.step === 'faculty');

        if (open && !inner.firstChild) inner.appendChild(pickerControls(kind));

        if (!animate) {
            card.classList.toggle('is-open', open);
        } else if (open) {
            // Next frame, so the browser has the folded height to animate from.
            requestAnimationFrame(() => card.classList.add('is-open'));
        } else {
            card.classList.remove('is-open');
        }
    });
}

function placeActions(p) {
    const actions = mk('div', 'ins-actions');
    actions.id = 'placeActions';
    if (p.step === 'faculty') {
        const skip = mk('button', 'btn btn-secondary', p.kind === 'new' ? 'Plot without faculty' : 'Unassign faculty');
        skip.type = 'button';
        skip.addEventListener('click', () => commitFaculty(null));
        actions.appendChild(skip);
    }
    const cancel = mk('button', 'btn btn-ghost', 'Cancel');
    cancel.type = 'button';
    cancel.addEventListener('click', cancelPlacement);
    actions.appendChild(cancel);
    return actions;
}

/** Moves between the room and faculty cards. */
function showStep(next) {
    const p = ui.place;
    if (!p) return;
    if (!$('stepRoom') && !$('stepFaculty')) { p.step = next; renderInspector(); return; }

    p.step = next;
    p.pendingRoom = null;
    paintSteps(p, true);
    $('placeActions').replaceWith(placeActions(p));
    refreshPlacementLists();

    if (next === 'faculty') loadFacultyForPicker();
    setTimeout(focusPicker, 420);
}

function pickerControls(kind) {
    const p = ui.place;
    const wrap = mk('div', 'picker');

    const search = mk('label', 'search-field picker__search');
    search.appendChild(mk('span', 'sr-only', kind === 'room' ? 'Search rooms' : 'Search faculty'));
    const glyph = mk('span', 'material-symbols-outlined', 'search');
    glyph.setAttribute('aria-hidden', 'true');
    const input = mk('input');
    input.type = 'search';
    input.autocomplete = 'off';
    input.placeholder = kind === 'room' ? 'Search rooms, floors or types' : 'Search faculty by name';
    input.value = kind === 'room' ? p.roomQuery : p.facultyQuery;
    input.addEventListener('input', () => {
        if (kind === 'room') { p.roomQuery = input.value; renderRoomList(); } else { p.facultyQuery = input.value; renderFacultyList(); }
    });
    search.append(glyph, input);

    const options = kind === 'room'
        ? [['all', 'All'], ['free', 'Available'], ['lab', 'Laboratories']]
        : [['all', 'All'], ['available', 'Available'], ['preferred', 'Preferred'], ['blocked', 'Unavailable']];
    const current = kind === 'room' ? p.roomFilter : p.facultyFilter;
    const chips = mk('div', 'chips');
    chips.setAttribute('role', 'group');
    chips.setAttribute('aria-label', kind === 'room' ? 'Filter rooms' : 'Filter faculty');
    options.forEach(([value, label]) => {
        const chip = mk('button', 'chip' + (value === current ? ' is-active' : ''), label);
        chip.type = 'button';
        chip.setAttribute('aria-pressed', String(value === current));
        chip.addEventListener('click', () => {
            if (kind === 'room') p.roomFilter = value; else p.facultyFilter = value;
            chips.querySelectorAll('.chip').forEach(c => {
                c.classList.toggle('is-active', c === chip);
                c.setAttribute('aria-pressed', String(c === chip));
            });
            if (kind === 'room') renderRoomList(); else renderFacultyList();
        });
        chips.appendChild(chip);
    });

    let floors = null;
    if (kind === 'room' && FLOORS.length > 1) {
        floors = mk('div', 'chips chips--floors');
        floors.setAttribute('role', 'group');
        floors.setAttribute('aria-label', 'Filter by floor');
        [['all', 'All floors'], ...FLOORS.map(f => [f, f])].forEach(([value, label]) => {
            const chip = mk('button', 'chip' + (value === p.roomFloor ? ' is-active' : ''), label);
            chip.type = 'button';
            chip.setAttribute('aria-pressed', String(value === p.roomFloor));
            chip.addEventListener('click', () => {
                p.roomFloor = value;
                floors.querySelectorAll('.chip').forEach(c => {
                    c.classList.toggle('is-active', c === chip);
                    c.setAttribute('aria-pressed', String(c === chip));
                });
                renderRoomList();
            });
            floors.appendChild(chip);
        });
    }

    const summary = mk('p', 'picker__summary');
    summary.id = kind + 'Summary';
    summary.setAttribute('aria-live', 'polite');
    const list = mk('ul', 'pick-list');
    list.id = kind + 'List';

    wrap.append(search, ...(floors ? [floors] : []), chips, summary, list);
    return wrap;
}

function refreshPlacementLists() {
    if (!ui.place) return;
    if ($('roomList')) renderRoomList();
    if ($('facultyList')) renderFacultyList();
}

// ---------- room picker ----------
function roomWarnings(room, subject) {
    const list = [];
    if (subject.is_lab && !room.is_laboratory) {
        list.push(`${subject.code} is marked as a laboratory class, but ${room.name} is not configured as a laboratory.`);
    }
    const headcount = aiPlan.headcount || 0;
    if (room.capacity > 0 && headcount > room.capacity) {
        list.push(`${room.name} seats ${room.capacity}, but ${CONTEXT.section} has ${aiPlan.headcount_known ? '' : 'about '}${headcount} students.`);
    }
    return list;
}

function roomRow(room, occupant, load) {
    const p = ui.place;
    const li = mk('li', 'pick-item');
    const expanded = p.pendingRoom === room.name;
    const warnings = expanded ? roomWarnings(room, p.subject) : [];

    const btn = mk('button', 'pick-row');
    btn.type = 'button';
    btn.disabled = !!occupant;
    btn.dataset.state = occupant ? 'occupied' : 'free';
    btn.dataset.room = room.name;
    btn.setAttribute('aria-expanded', String(expanded));

    const main = mk('span', 'pick-main');
    const title = mk('span', 'pick-title', room.name);
    if (room.is_laboratory) title.appendChild(mk('span', 'pick-tag is-lab', 'Lab'));
    if (p.currentRoom === room.name) title.appendChild(mk('span', 'pick-tag', 'Current'));

    const seats = room.capacity > 0 ? `${room.capacity} seats` : '';
    const sub = [room.floor, room.is_laboratory ? 'Laboratory' : (room.type || 'Room')].filter(Boolean).join(' · ');
    main.append(title, mk('span', 'pick-sub', sub));
    if (seats) {
        const short = room.capacity > 0 && (aiPlan.headcount || 0) > room.capacity;
        main.appendChild(mk('span', 'pick-sub' + (short ? ' is-warn' : ''), short ? `${seats} · fewer than ${aiPlan.headcount} students` : seats));
    }
    if (occupant) {
        main.appendChild(mk('span', 'pick-note', `In use by ${occupant.code} · ${occupant.section || CONTEXT.section}, ${blockRange(occupant)}`));
    }

    const status = occupant ? 'In use' : load >= 3 ? `${load} classes today` : load > 0 ? `${plural(load, 'class', 'classes')} today` : 'Free';
    btn.append(main, mk('span', 'pick-status' + (occupant ? ' is-blocked' : ' is-ok'), status));
    btn.addEventListener('click', () => chooseRoom(room));
    li.appendChild(btn);

    if (warnings.length) {
        const warn = mk('div', 'pick-warn');
        warn.setAttribute('role', 'alert');
        warnings.forEach(text => warn.appendChild(mk('p', '', text)));
        const actions = mk('div', 'pick-actions');
        const use = mk('button', 'btn btn-primary btn-sm', 'Use this room');
        use.type = 'button';
        use.addEventListener('click', () => acceptRoom(room));
        const other = mk('button', 'btn btn-ghost btn-sm', 'Choose another');
        other.type = 'button';
        other.addEventListener('click', () => { p.pendingRoom = null; renderRoomList(); });
        actions.append(use, other);
        warn.appendChild(actions);
        li.appendChild(warn);
    }
    return li;
}

function renderRoomList() {
    const p = ui.place;
    const list = $('roomList');
    if (!p || !list) return;

    const everyone = blocks.filter(b => !(p.key && blockKey(b) === p.key)).concat(OTHERS);
    const inSlot = b => Number(b.day) === p.day && rangesOverlap(p.startRow, p.endRow, blockStartRow(b), blockEndRow(b));
    const query = p.roomQuery.trim().toLowerCase();

    const rows = ROOMS.map(room => ({
        room,
        occupant: everyone.find(b => b.room === room.name && inSlot(b)) || null,
        load: everyone.filter(b => b.room === room.name && Number(b.day) === p.day).length,
    })).filter(({ room, occupant }) => {
        if (p.roomFloor !== 'all' && room.floor !== p.roomFloor) return false;
        if (query && ![room.name, room.floor, room.type].some(v => String(v || '').toLowerCase().includes(query))) return false;
        if (p.roomFilter === 'free') return !occupant;
        if (p.roomFilter === 'lab') return room.is_laboratory;
        return true;
    });

    const groups = [];
    if (p.subject.is_lab) {
        groups.push(['Laboratories · recommended', rows.filter(r => r.room.is_laboratory)]);
        groups.push(['Other rooms', rows.filter(r => !r.room.is_laboratory)]);
    } else {
        FLOORS.forEach(floor => groups.push([floor, rows.filter(r => r.room.floor === floor)]));
    }

    list.replaceChildren();
    groups.forEach(([label, items]) => {
        if (!items.length) return;
        const heading = mk('li', 'pick-group', label);
        heading.setAttribute('role', 'presentation');
        list.appendChild(heading);
        items.slice().sort((a, b) => Number(!!a.occupant) - Number(!!b.occupant)).forEach(r => list.appendChild(roomRow(r.room, r.occupant, r.load)));
    });

    const free = rows.filter(r => !r.occupant).length;
    $('roomSummary').textContent = rows.length ? `${free} of ${rows.length} rooms free at this time` : '';

    if (!rows.length) {
        const empty = mk('li', 'pick-empty');
        empty.append(mk('strong', '', 'No rooms match'), mk('span', '', 'Try a different search or filter.'));
        list.appendChild(empty);
    }
}

function chooseRoom(room) {
    const p = ui.place;
    if (!p) return;

    if (roomWarnings(room, p.subject).length && p.pendingRoom !== room.name) {
        // Explain first; the chairperson decides whether to keep it.
        p.pendingRoom = room.name;
        renderRoomList();
        return;
    }
    acceptRoom(room);
}

function acceptRoom(room) {
    const p = ui.place;
    if (!p) return;

    p.pendingRoom = null;
    p.roomName = room.name;

    if (p.kind === 'room') {
        const b = blocks.find(x => blockKey(x) === p.key);
        if (b && b.room !== room.name) {
            pushUndo(`changed room for ${b.code}`);
            b.room = room.name;
            scheduleSave();
            showToast(`${b.code} moved to ${room.name}.`, 'success', { label: 'Undo', onClick: undoLast });
        }
        finishPlacement(p.key);
        return;
    }

    // One faculty member teaches every meeting of a subject, so a second
    // meeting reuses whoever already has it instead of asking again.
    const inherited = assignedFaculty(p.subject.code);
    if (inherited) {
        p.faculty = inherited;
        commitNew();
        return;
    }

    // Let the chosen card acknowledge the click, then slide to faculty.
    const picked = Array.from(document.querySelectorAll('#roomList .pick-row')).find(r => r.dataset.room === room.name);
    if (picked) picked.classList.add('is-picked');
    renderBlocks();
    setTimeout(() => { if (ui.place === p) showStep('faculty'); }, picked ? 280 : 0);
}

/** Leaves the picker and shows the class that was just edited. */
function finishPlacement(key) {
    ui.place = null;
    document.querySelectorAll('.block.is-pending').forEach(el => el.remove());
    ui.mode = key ? 'block' : 'overview';
    if (key) ui.blockKey = key;
    renderBlocks();
    focusBlock(key);
}

function focusBlock(key) {
    const el = key ? document.querySelector('.day-col .block[data-key="' + CSS.escape(key) + '"]') : null;
    if (el) el.focus({ preventScroll: true });
}

function commitNew() {
    const p = ui.place;
    if (!p) return;

    const faculty = p.faculty || null;
    const nb = {
        day: p.day,
        code: p.subject.code,
        title: p.subject.title,
        prof: faculty ? faculty.name : undefined,
        faculty_id: faculty ? faculty.id : null,
        time_label: `${rowTo12h(p.startRow)} - ${rowTo12h(p.endRow)}`,
        room: p.roomName,
        theme: p.subject.color,
        start: rowToHHMM(p.startRow),
        end: rowToHHMM(p.endRow),
    };

    pushUndo(`plotted ${nb.code}`);
    blocks.push(nb);
    // Any earlier meeting of this subject that had nobody joins the choice.
    if (faculty) {
        blocks.filter(b => b.code === nb.code && !b.faculty_id).forEach(b => { b.prof = faculty.name; b.faculty_id = faculty.id; });
    }

    ui.subjectCode = nb.code;
    ui.blockKey = blockKey(nb);
    ui.place = null;
    ui.mode = 'block';
    document.querySelectorAll('.block.is-pending').forEach(el => el.remove());
    renderBlocks();
    scheduleSave();
    focusBlock(ui.blockKey);
    showToast(`${nb.code} plotted in ${nb.room}${faculty ? ', assigned to ' + faculty.name : ''}.`, 'success', { label: 'Undo', onClick: undoLast });
    announce(`${nb.code} plotted on ${dayName(nb.day)}, ${blockRange(nb)}.`);
}

// ---------- faculty picker ----------
async function loadFacultyForPicker() {
    const p = ui.place;
    if (!p) return;

    p.facultyState = 'loading';
    renderFacultyList();

    try {
        // A roster fetched moments ago (while choosing the room) is fresh enough.
        const data = await loadFaculty(!facultyCache || Date.now() - facultyCache.at > 20000);
        if (ui.place !== p) return;
        p.facultyData = data;
        p.facultyState = 'ready';
    } catch (err) {
        if (ui.place !== p) return;
        p.facultyState = 'error';
    }
    renderFacultyList();
    renderBlocks({ skipSuggestions: true }); // fresh availability may explain new issues
}

function timeOverlap(aStart, aEnd, bStart, bEnd) {
    return aStart < bEnd && bStart < aEnd;
}

/** Every meeting of this subject the chosen faculty member would end up teaching. */
function placementSlots(p) {
    const slots = blocks.filter(b => b.code === p.subject.code)
        .map(b => ({ day: Number(b.day), start: b.start, end: b.end, own: true }));
    if (p.kind === 'new') slots.push({ day: p.day, start: rowToHHMM(p.startRow), end: rowToHHMM(p.endRow), own: false });
    return slots;
}

function classifyFaculty(f, p) {
    const slots = placementSlots(p);
    const elsewhere = blocks.filter(b => b.code !== p.subject.code).concat(OTHERS);
    const holdsSubject = Number(subjectStats(p.subject.code).facultyId) === Number(f.id);
    let soft = null;

    for (const slot of slots) {
        // The faculty roster only lists times, so this subject's own saved
        // meetings (held by its current faculty) must not count against it.
        const saved = (f.meetings || []).find(m => Number(m.day) === slot.day && timeOverlap(slot.start, slot.end, m.start, m.end)
            && !(holdsSubject && slots.some(s => s.own && s.day === Number(m.day) && s.start === m.start && s.end === m.end)));
        const local = elsewhere.find(b => b.prof === f.name && Number(b.day) === slot.day && timeOverlap(slot.start, slot.end, b.start, b.end));
        const clash = local || saved;

        if (clash) {
            return {
                status: 'conflict', slot,
                detail: `Already teaching ${clash.code || 'another class'} on ${dayShort(slot.day)}, ${rangeLabel(toMinutes(clash.start), toMinutes(clash.end))}.`,
            };
        }

        if (!soft) {
            const fit = availabilityFit(f, slot.day, slot.start, slot.end);
            if (fit.fit !== 'ok') {
                soft = { status: fit.fit === 'none' ? 'noavail' : 'unavailable', slot, detail: availabilityText(f.name, slot.day, fit) };
            }
        }
    }

    if (soft) return soft;

    const pref = (f.preferences || []).find(x => x.code === p.subject.code);
    return { status: pref && pref.level === 'preferred' ? 'preferred' : 'ok', match: pref ? pref.level : '' };
}

function facultyCandidates(p) {
    const data = p.facultyData;
    const max = Number(data.max_units || 0);
    const gain = parseFloat(p.subject.units) || 0;
    const currentId = subjectStats(p.subject.code).facultyId;
    const rank = { preferred: 0, qualified: 1, willing: 2, '': 3 };
    const blocked = c => (c.status === 'conflict' ? 2 : c.status === 'unavailable' || c.status === 'noavail' ? 1 : 0);

    const list = (data.faculty || []).map(f => {
        const result = classifyFaculty(f, p);
        const pref = (f.preferences || []).find(x => x.code === p.subject.code);
        const match = pref ? pref.level : '';
        const extra = p.kind === 'faculty' && Number(currentId) === Number(f.id) ? 0 : gain;
        return Object.assign(result, { f, match, overCap: max > 0 && Number(f.units || 0) + extra > max });
    }).sort((a, b) => blocked(a) - blocked(b)
        || (rank[a.match] ?? 3) - (rank[b.match] ?? 3)
        || Number(a.overCap) - Number(b.overCap)
        || (a.f.units || 0) - (b.f.units || 0)
        || a.f.name.localeCompare(b.f.name));

    const best = list.find(c => blocked(c) === 0 && !c.overCap && (c.match === 'preferred' || c.match === 'qualified'));
    if (best) best.recommended = true;
    return list;
}

function facultyRow(c, p) {
    const { f } = c;
    const max = Number(p.facultyData.max_units || 0);
    const units = Number(f.units || 0);
    const employment = f.employment_status === 'full_time' ? 'Full-time' : f.employment_status === 'part_time' ? 'Part-time' : '';

    const li = mk('li', 'pick-item');
    li.dataset.status = c.status;

    const row = mk('div', 'pick-row is-static');
    row.appendChild(mk('span', 'avatar', initials(f.name)));

    const main = mk('span', 'pick-main');
    const title = mk('span', 'pick-title', f.name);
    if (c.recommended) title.appendChild(mk('span', 'pick-tag is-good', 'Recommended'));
    main.appendChild(title);
    main.appendChild(mk('span', 'pick-sub', [employment, max ? `${round1(units)} / ${max} units` : `${round1(units)} units`].filter(Boolean).join(' · ')));
    if (max) {
        const bar = mk('span', 'load-bar' + (c.overCap ? ' is-over' : ''));
        bar.setAttribute('aria-hidden', 'true');
        const fill = document.createElement('i');
        fill.style.width = Math.min(100, units / max * 100) + '%';
        bar.appendChild(fill);
        main.appendChild(bar);
    }
    row.append(main, mk('span', 'pick-status is-' + c.status, FACULTY_STATUS[c.status]));
    li.appendChild(row);

    const notes = [];
    if (c.detail) notes.push(c.detail);
    else if (c.match) notes.push(`${c.match.charAt(0).toUpperCase() + c.match.slice(1)} for ${p.subject.code}.`);
    else notes.push(`No preference set for ${p.subject.code}.`);
    if (c.overCap) notes.push('Assigning this class may exceed the unit limit; the server checks on save.');
    li.appendChild(mk('p', 'pick-detail', notes.join(' ')));

    const actions = mk('div', 'pick-actions');
    const week = mk('button', 'btn btn-ghost btn-sm', p.openWeek === f.id ? 'Hide week' : 'View week');
    week.type = 'button';
    week.setAttribute('aria-expanded', String(p.openWeek === f.id));
    week.addEventListener('click', () => { p.openWeek = p.openWeek === f.id ? null : f.id; renderFacultyList(); });

    const assign = mk('button', 'btn btn-sm ' + (c.status === 'unavailable' || c.status === 'noavail' ? 'btn-warn' : 'btn-primary'),
        c.status === 'unavailable' || c.status === 'noavail' ? 'Assign anyway' : 'Assign');
    assign.type = 'button';
    if (c.status === 'conflict') {
        assign.disabled = true;
        assign.title = 'A faculty member cannot teach two classes at once. Choose another faculty or another time.';
    }
    assign.addEventListener('click', () => assignFaculty(c));
    actions.append(week, assign);
    li.appendChild(actions);

    if (p.openWeek === f.id) li.appendChild(weekTable(f));
    return li;
}

function weekTable(f) {
    const table = mk('table', 'week-table');
    const caption = mk('caption', 'sr-only', `${f.name} weekly availability`);
    table.appendChild(caption);
    const body = document.createElement('tbody');
    WEEK.forEach((w, day) => {
        const a = (f.availability || []).find(x => Number(x.day_of_week) === day);
        const tr = document.createElement('tr');
        tr.appendChild(mk('th', '', w.day.slice(0, 3)));
        const value = !a ? 'Not submitted' : a.is_available && a.is_available != 0
            ? rangeLabel(toMinutes((a.start_time || '00:00').slice(0, 5)), toMinutes((a.end_time || '00:00').slice(0, 5))) : 'Unavailable';
        tr.appendChild(mk('td', !a || !(a.is_available && a.is_available != 0) ? 'muted' : '', value));
        body.appendChild(tr);
    });
    table.appendChild(body);
    return table;
}

function renderFacultyList() {
    const p = ui.place;
    const list = $('facultyList');
    if (!p || !list) return;
    const summary = $('facultySummary');

    if (p.facultyState === 'loading' || p.facultyState === 'idle') {
        summary.textContent = 'Loading faculty…';
        list.replaceChildren(...Array.from({ length: 4 }, () => {
            const li = mk('li', 'pick-skeleton');
            li.setAttribute('aria-hidden', 'true');
            return li;
        }));
        return;
    }

    if (p.facultyState === 'error') {
        summary.textContent = '';
        const li = mk('li', 'pick-empty');
        const retry = mk('button', 'btn btn-secondary btn-sm', 'Try again');
        retry.type = 'button';
        retry.addEventListener('click', loadFacultyForPicker);
        li.append(mk('strong', '', 'Faculty could not be loaded'), mk('span', '', 'Check your connection, or plot without faculty and assign one later.'), retry);
        list.replaceChildren(li);
        return;
    }

    const all = facultyCandidates(p);
    const query = p.facultyQuery.trim().toLowerCase();
    const shown = all.filter(c => {
        if (query && !c.f.name.toLowerCase().includes(query)) return false;
        if (p.facultyFilter === 'available') return c.status === 'ok' || c.status === 'preferred';
        if (p.facultyFilter === 'preferred') return c.match === 'preferred';
        if (p.facultyFilter === 'blocked') return c.status === 'conflict' || c.status === 'unavailable' || c.status === 'noavail';
        return true;
    });

    summary.textContent = all.length ? `${shown.length} of ${all.length} faculty shown` : '';
    list.replaceChildren(...shown.map(c => facultyRow(c, p)));

    if (!shown.length) {
        const empty = mk('li', 'pick-empty');
        empty.append(mk('strong', '', all.length ? 'No faculty match' : 'No faculty in this department'),
            mk('span', '', all.length ? 'No faculty members match your current filters.' : 'You can plot this class now and assign faculty later.'));
        list.appendChild(empty);
    }
}

async function assignFaculty(c) {
    const p = ui.place;
    if (!p) return;

    if (c.status === 'unavailable' || c.status === 'noavail') {
        const proceed = await confirmDialog({
            title: 'Assign outside availability?',
            message: `${c.detail} This exception will be recorded. Add an optional reason for the audit history.`,
            confirmLabel: 'Assign anyway',
            danger: true,
            reason: true,
        });
        if (!proceed || ui.place !== p) return;

        for (const slot of placementSlots(p)) {
            if (availabilityFit(c.f, slot.day, slot.start, slot.end).fit !== 'ok') {
                recordFacultyOverride({ facultyId: c.f.id, subjectCode: p.subject.code, day: slot.day, start: slot.start, end: slot.end, type: 'unavailable', reason: proceed.reason || '' });
            }
        }
    }

    commitFaculty({ id: c.f.id, name: c.f.name });
}

function commitFaculty(faculty) {
    const p = ui.place;
    if (!p) return;

    if (p.kind === 'new') {
        p.faculty = faculty;
        commitNew();
        return;
    }

    // Faculty belongs to the subject, not to one meeting, so every meeting changes together.
    const targets = blocks.filter(b => b.code === p.subject.code);
    pushUndo(faculty ? `assigned ${faculty.name} to ${p.subject.code}` : `unassigned ${p.subject.code}`);
    targets.forEach(b => {
        b.prof = faculty ? faculty.name : undefined;
        b.faculty_id = faculty ? faculty.id : null;
    });
    scheduleSave();
    showToast(faculty ? `${faculty.name} now teaches ${p.subject.code}.` : `${p.subject.code} is unassigned.`, 'success', { label: 'Undo', onClick: undoLast });
    finishPlacement(p.key);
}

/** Best-effort audit trail for availability overrides; never blocks the plot. */
async function recordFacultyOverride({ facultyId, subjectCode, day, start, end, type, reason }) {
    try {
        await fetch(CONFIG.facultyOverrideUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                faculty_id: facultyId,
                academic_year: CONTEXT.academic_year,
                program: CONTEXT.program,
                year_level: CONTEXT.year_level,
                section: CONTEXT.section,
                subject_code: subjectCode,
                day, start, end,
                conflict_type: type,
                reason: reason || '',
            }),
        });
    } catch (e) {
        // The plot itself is what matters; the audit record is best-effort.
    }
}

const FLOORS = [...new Set(ROOMS.map(r => r.floor))];

// ---------- autosave ----------
// Every change is sent shortly after it is made. The header always says
// where that stands, and the shared UnsavedChanges guard is armed for as
// long as anything is unsent, so leaving mid-save asks first and leaving
// when everything is saved never does.
const SAVE_LABELS = { pending: 'Unsaved changes', saving: 'Saving…', error: 'Save failed' };
let syncState = 'saved';
let savedAt = null;
let changeRev = 0;      // bumps on every local edit
let retryTimer = null;

function isSaveBusy() {
    return saving || saveQueued || syncState === 'pending' || syncState === 'saving';
}

function agoLabel(timestamp) {
    const seconds = Math.round((Date.now() - timestamp) / 1000);
    if (seconds < 10) return 'just now';
    if (seconds < 60) return `${seconds} sec ago`;
    const minutes = Math.round(seconds / 60);
    return minutes < 60 ? `${minutes} min ago` : `${Math.round(minutes / 60)} hr ago`;
}

function renderSyncText() {
    if (syncState === 'saved') {
        $('syncText').textContent = savedAt ? `Saved ${agoLabel(savedAt)}` : 'All changes saved';
    } else {
        $('syncText').textContent = SAVE_LABELS[syncState];
    }
}

function setSync(state) {
    syncState = state;
    $('syncStatus').dataset.state = state;
    $('syncRetry').hidden = state !== 'error';
    if (state === 'saved') savedAt = Date.now();
    renderSyncText();

    if (window.UnsavedChanges) {
        if (state === 'saved') window.UnsavedChanges.markClean();
        else window.UnsavedChanges.markDirty();
    }
}

setInterval(renderSyncText, 15000);

function scheduleSave() {
    changeRev++;
    clearTimeout(retryTimer);
    setSync('pending');
    clearTimeout(saveTimer);
    saveTimer = setTimeout(doSave, 250);
}

$('syncRetry').addEventListener('click', () => {
    clearTimeout(retryTimer);
    clearTimeout(saveTimer);
    doSave();
});

let lastNoProfWarning = '';

async function doSave() {
    saveTimer = null;
    if (saving) { saveQueued = true; return; }
    saving = true;
    const revision = changeRev;
    setSync('saving');

    try {
        const response = await fetch(CONFIG.syncUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ context: CONTEXT, blocks: blocks }),
        });
        const result = await response.json();

        if (response.ok && result.ok) {
            // Another edit may have landed while this request was in flight.
            if (revision === changeRev) setSync('saved');
            else setSync('pending');

            const unstaffed = subjectsWithoutProf().join(', ');
            if (unstaffed && unstaffed !== lastNoProfWarning) {
                showToast(`Saved, but ${unstaffed} ${unstaffed.includes(',') ? 'have' : 'has'} no faculty yet.`, 'error');
            }
            lastNoProfWarning = unstaffed;

            // Blocks for subjects no longer in this term's curriculum were
            // removed by the server; refresh so the grid matches.
            if (Array.isArray(result.dropped) && result.dropped.length > 0) {
                showToast('Removed outdated block(s) no longer in this term\'s curriculum: ' + result.dropped.join(', ') + '.', 'success');
                await reloadFromServer();
            }
        } else {
            // The server refused the change (for example another user won a
            // room): fall back to what the server holds, and say why.
            showToast(result.error || 'The server rejected this change.', 'error');
            await reloadFromServer();
            setSync('saved');
        }
    } catch (err) {
        setSync('error');
        if (err && err.name === 'SessionExpiredError') {
            // Session ended: don't hammer the server. The changes stay on
            // screen and are saved as soon as the person signs back in.
            window.SessionGuard.whenRestored().then(() => { clearTimeout(retryTimer); doSave(); });
        } else {
            retryTimer = setTimeout(doSave, 3000);
        }
    } finally {
        saving = false;
        if (saveQueued) {
            saveQueued = false;
            doSave();
        }
    }
}

async function reloadFromServer() {
    const query = new URLSearchParams(CONTEXT).toString();
    const response = await fetch(`${CONFIG.stateUrl}?${query}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!response.ok) return;
    const data = await response.json();
    stateVersion = data.version || '';
    blocks = data.blocks || [];
    OTHERS = data.others || [];
    if (Array.isArray(data.assignments)) ASSIGNMENTS = data.assignments;
    renderBlocks();
}

// Changing term, program, year or section leaves the page, so it goes
// through the same guard as any other navigation.
document.querySelectorAll('#contextForm .context-select').forEach(select => {
    select.dataset.previous = select.value;
    select.addEventListener('change', () => {
        const form = select.form;
        const submitNow = () => {
            // Switching PROGRAM leaves year_level/section behind at
            // whatever they previously held — stale values that may not
            // even exist for the new program (see the "A" section leak,
            // and RED101/RED103 needing a year-level jump the server
            // can't make if a year_level param is already present). Drop
            // both so the server picks fresh defaults for the new
            // program, including its smart borrowed-subject year-level
            // jump (see ChairpersonController::bestYearLevelForBorrowedSubject()).
            if (select.id === 'plot-program') {
                const yearSelect = document.getElementById('plot-year_level');
                const sectionSelect = document.getElementById('plot-section');
                if (yearSelect) yearSelect.removeAttribute('name');
                if (sectionSelect) sectionSelect.removeAttribute('name');
            }
            form.submit();
        };

        if (window.UnsavedChanges && window.UnsavedChanges.isDirty()) {
            window.UnsavedChanges.confirmLeave().then(leave => {
                if (leave) submitNow();
                else select.value = select.dataset.previous;
            });
        } else {
            submitNow();
        }
    });
});

// ---------- context bar: custom dropdown ----------
// The context selects (academic year/semester/program/year level/section)
// are plain browser <select>s, whose open dropdown list browsers don't let
// a page style (no custom hover colors, badges, etc). This progressively
// enhances each one with a styled trigger + listbox that drives the real,
// still-present <select> — so every existing behavior (the dirty-check
// guard above, form submission via select.name, the program-change reset,
// no-JS fallback, screen readers) keeps working unchanged; only the visual
// chrome of the OPEN list changes.
function enhanceContextSelect(select) {
    const field = select.closest('.pl-field');
    if (!field) return;

    const wrap = mk('div', 'cx-select');
    const trigger = mk('button', 'cx-select__trigger');
    trigger.type = 'button';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    const list = mk('ul', 'cx-select__list');
    list.setAttribute('role', 'listbox');
    list.hidden = true;

    select.classList.add('cx-select__native');
    select.insertAdjacentElement('afterend', wrap);
    wrap.appendChild(select); // move inside .cx-select so its position:absolute is scoped to it, not the page
    wrap.append(trigger, list);

    // Options already carry " — N to plot" in their text (server-rendered,
    // year_level only) — read it back out instead of recomputing it here.
    const splitLabel = text => {
        const i = text.indexOf(' — ');
        return i === -1 ? { main: text, badge: null } : { main: text.slice(0, i), badge: text.slice(i + 3) };
    };

    function renderTrigger() {
        const opt = select.options[select.selectedIndex];
        trigger.replaceChildren();
        trigger.disabled = select.disabled || select.options.length === 0;
        if (!opt) return;
        const { main, badge } = splitLabel(opt.textContent);
        trigger.appendChild(mk('span', 'cx-select__value', main));
        if (badge) trigger.appendChild(mk('span', 'cx-select__badge', badge));
        trigger.appendChild(mk('span', 'cx-select__chevron material-symbols-outlined', 'expand_more'));
    }

    function renderList() {
        list.replaceChildren();
        [...select.options].forEach(opt => {
            const { main, badge } = splitLabel(opt.textContent);
            const li = mk('li', 'cx-select__option' + (opt.selected ? ' is-selected' : '') + (opt.disabled ? ' is-disabled' : ''));
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', String(opt.selected));
            li.tabIndex = -1;
            li.appendChild(mk('span', 'cx-select__value', main));
            if (badge) li.appendChild(mk('span', 'cx-select__badge', badge));
            if (!opt.disabled) {
                li.addEventListener('click', () => {
                    closeList();
                    if (select.value !== opt.value) {
                        select.value = opt.value;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    trigger.focus();
                });
            }
            list.appendChild(li);
        });
    }

    function openList() {
        if (trigger.disabled || !list.hidden) return;
        renderList();
        list.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        wrap.classList.add('is-open');
        (list.querySelector('.is-selected') || list.querySelector('.cx-select__option:not(.is-disabled)'))?.focus();
    }

    function closeList() {
        if (list.hidden) return;
        list.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        wrap.classList.remove('is-open');
    }

    trigger.addEventListener('click', () => (list.hidden ? openList() : closeList()));
    trigger.addEventListener('keydown', e => {
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) { e.preventDefault(); openList(); }
    });
    list.addEventListener('keydown', e => {
        const items = [...list.querySelectorAll('.cx-select__option:not(.is-disabled)')];
        const i = items.indexOf(document.activeElement);
        if (e.key === 'ArrowDown') { e.preventDefault(); (items[i + 1] || items[0])?.focus(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); (items[i - 1] || items[items.length - 1])?.focus(); }
        else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); document.activeElement?.click(); }
        else if (e.key === 'Escape') { e.preventDefault(); closeList(); trigger.focus(); }
        else if (e.key === 'Tab') closeList();
    });
    document.addEventListener('click', e => { if (!wrap.contains(e.target)) closeList(); });

    select.addEventListener('change', renderTrigger);
    renderTrigger();
}
document.querySelectorAll('#contextForm .context-select').forEach(enhanceContextSelect);

        // ---------- real-time sync (long-polling) ----------
        // The server holds each request open until the schedule changes, so
        // edits from other tabs/users appear in under a second.
        async function longPollLoop() {
            for (;;) {
                try {
                    const params = new URLSearchParams(CONTEXT);
                    if (stateVersion) params.set('version', stateVersion);

                    const response = await fetch(`${CONFIG.stateUrl}?${params.toString()}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!response.ok) throw new Error('poll failed');

                    const data = await response.json();
                    stateVersion = data.version || '';

                    // Notify the chairperson the moment a faculty member
                    // claims one of the plotted loads — or swaps it away.
                    if (Array.isArray(data.assignments)) {
                        const known = new Map(ASSIGNMENTS.map(a => [a.key, a.username]));
                        data.assignments.forEach(a => {
                            const label = `${a.code} (${a.section} · ${shortYear(a.year_level)})`;
                            if (!known.has(a.key)) {
                                showToast(`${label} was assigned to ${a.username}.`, 'success');
                            } else if (known.get(a.key) !== a.username) {
                                showToast(`${label} was reassigned from ${known.get(a.key)} to ${a.username}.`, 'success');
                            }
                        });
                        ASSIGNMENTS = data.assignments;
                    }

                    // Approvals queue: toast newly arrived requests.
                    if (Array.isArray(data.swap_approvals)) {
                        const knownIds = new Set(APPROVALS.map(a => a.id));
                        data.swap_approvals.forEach(a => {
                            if (!knownIds.has(a.id)) {
                                showToast(`Swap awaiting your approval: ${a.requester} ${a.requested ? '⇄' : '→'} ${a.target} (${a.offered}).`, 'success');
                            }
                        });
                        APPROVALS = data.swap_approvals;
                        renderApprovals();
                    }

                    // Change requests queue: toast newly arrived requests.
                    if (Array.isArray(data.change_requests)) {
                        const knownCrIds = new Set(CHANGE_REQUESTS.map(cr => cr.id));
                        data.change_requests.forEach(cr => {
                            if (!knownCrIds.has(cr.id)) {
                                showToast(`Change request from ${cr.faculty} for ${cr.subject}.`, 'success');
                            }
                        });
                        CHANGE_REQUESTS = data.change_requests;
                        renderChangeRequests();
                    }

                    if (typeof data.unread_notifications === 'number') {
                        window.UNREAD = data.unread_notifications;
                        if (window.updateNotifBadge) window.updateNotifBadge();
                    }

                    let changed = false;

                    if (Array.isArray(data.others) && JSON.stringify(data.others) !== JSON.stringify(OTHERS)) {
                        OTHERS = data.others;
                        changed = true;
                    }

                    // Adopt the server's copy of our own board only while no
                    // local change is being saved, so we never clobber a
                    // just-made edit.
                    if (Array.isArray(data.blocks) && !isSaveBusy()
                        && JSON.stringify(data.blocks) !== JSON.stringify(blocks)) {
                        blocks = data.blocks;
                        changed = true;
                    }

                    if (changed) renderBlocks();
                } catch (err) {
                    if (err && err.name === 'SessionExpiredError') {
                        // Wait for sign-in instead of polling a login page.
                        await window.SessionGuard.whenRestored();
                        continue;
                    }
                    // Offline or server hiccup: back off briefly, then resume.
                    await new Promise(resolve => setTimeout(resolve, 3000));
                }
            }
        }


// ---------- toast ----------
let toastTimer = null;

function showToast(message, kind, action) {
    const toast = $('toast');
    toast.replaceChildren(mk('span', 'toast__text', message));

    if (action) {
        const button = mk('button', 'toast__action', action.label);
        button.type = 'button';
        button.addEventListener('click', () => {
            toast.classList.remove('show');
            action.onClick();
        });
        toast.appendChild(button);
    }

    toast.className = 'toast show' + (kind ? ' ' + kind : '');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), action ? 6000 : kind === 'error' ? 7000 : 3500);
}

// ---------- keyboard ----------
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;

    // A confirmation answers first, then whichever modal is open, then the
    // panel state from the inside out.
    if (confirmResolve !== null) { settleConfirm(false); return; }
    if ($('overviewModal').classList.contains('open')) { closeRoomOverview(); return; }
    if ($('facultyPanelModal').classList.contains('open')) { closeFacultyPanel(); return; }
    if (approvalsModal.classList.contains('open')) { approvalsModal.classList.remove('open'); return; }
    if (changeRequestsModal.classList.contains('open')) { changeRequestsModal.classList.remove('open'); return; }

    if (ui.place) cancelPlacement();
    else if (ui.mode === 'conflict') dismissConflict();
    else if (ui.moveOpen) { ui.moveOpen = false; renderInspector(); }
    else if (ui.blockKey || ui.subjectCode) clearSelection();
    else if (workspace.dataset.panel === 'open') closePanel();
});

// ---------- start ----------
setTab('inspector');
closePanel();
renderBlocks();
// Open the timetable at the first plotted class rather than at the top of the day.
if (blocks.length) {
    const earliest = Math.min(...blocks.map(blockStartRow));
    $('boardScroll').scrollTop = Math.max(0, (earliest - 2) * CONFIG.rowHeight);
}
renderApprovals();
renderChangeRequests();

// Availability warnings on plotted classes need the faculty roster; load it
// once up front so they are there when the chairperson looks.
loadFaculty().then(() => renderBlocks({ skipSuggestions: true })).catch(() => {});
longPollLoop();

/**
 * Progressive enhancement for a native <input type="datetime-local">: swaps
 * the browser's own calendar+clock popup for a styled floating popover —
 * same Popover-API technique as custom-select.js's listbox, so it floats
 * over the page instead of the native control's tendency (at least on
 * Chrome/Windows) to push the rest of a card's content down when it opens.
 *
 * Layout: a calendar pane (month/year drill-down like the native control)
 * next to a time pane (an editable "HH:MM" text field + an AM/PM segmented
 * pill + Hour/Minute quick-pick columns), with an explicit Clear / Today /
 * Confirm footer — nothing commits to the real input until Confirm.
 *
 * The original input is kept in the DOM (visually hidden) as the real value
 * holder — its name/value/required/disabled and any 'change' listener a
 * page already has on it keep working untouched; this only replaces how
 * the human picks a value, not what gets submitted.
 *
 * Usage: window.enhanceDateTimeField(document.getElementById('myDateTime'))
 */
(function () {
    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var DOW = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    var autoIdCounter = 0;

    function pad(n) { return (n < 10 ? '0' : '') + n; }

    // datetime-local's own value format: "YYYY-MM-DDTHH:MM" (local wall-clock,
    // no timezone) — parsed by hand instead of `new Date(value)` since that
    // constructor treats a timezone-less string as UTC in some engines.
    function parseValue(v) {
        var m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(v || '');
        return m ? new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]) : null;
    }

    function toInputValue(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function formatDisplay(d) {
        if (!d) return 'mm/dd/yyyy --:-- --';
        var hours = d.getHours();
        var h12 = hours % 12 || 12;
        return (d.getMonth() + 1) + '/' + d.getDate() + '/' + d.getFullYear() + ' ' + h12 + ':' + pad(d.getMinutes()) + ' ' + (hours >= 12 ? 'PM' : 'AM');
    }

    function sameDay(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }

    function iconEl(name) {
        var el = document.createElement('span');
        // Keep navigation arrows readable even if the optional icon font
        // has not loaded yet (or a local font cache is stale).
        var fallback = { chevron_left: '‹', chevron_right: '›', expand_more: '⌄' };
        el.className = fallback[name] ? 'ac-dt__chevron' : 'material-symbols-outlined';
        el.setAttribute('aria-hidden', 'true');
        el.textContent = fallback[name] || name;
        return el;
    }

    window.enhanceDateTimeField = function (input) {
        if (!input || input.dataset.dtEnhanced) return null;
        input.dataset.dtEnhanced = '1';

        var wrap = document.createElement('div');
        wrap.className = 'ac-dt';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        input.classList.add('ac-dt__native');
        input.setAttribute('tabindex', '-1');
        input.setAttribute('aria-hidden', 'true');

        var triggerId = (input.id || 'acDt' + (++autoIdCounter)) + 'Trigger';
        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'ac-dt__trigger';
        trigger.id = triggerId;
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');

        var text = document.createElement('span');
        text.className = 'ac-dt__text';

        var clearIcon = iconEl('close');
        clearIcon.classList.add('ac-dt__clear-icon');
        clearIcon.title = 'Clear';
        clearIcon.addEventListener('click', function (e) {
            e.stopPropagation();
            commit(null);
            close();
        });

        var triggerIcon = iconEl('calendar_month');
        triggerIcon.classList.add('ac-dt__icon');
        trigger.appendChild(triggerIcon);
        trigger.appendChild(text);
        wrap.appendChild(trigger);
        wrap.appendChild(clearIcon);

        document.querySelectorAll('label[for="' + input.id + '"]').forEach(function (l) { l.setAttribute('for', triggerId); });

        var panel = document.createElement('div');
        panel.className = 'ac-dt__panel';
        panel.tabIndex = -1;
        if ('showPopover' in panel) panel.setAttribute('popover', 'manual');
        wrap.appendChild(panel);

        var usePopover = panel.hasAttribute('popover');

        var view = 'days'; // 'days' | 'months' | 'years'
        var viewDate = parseValue(input.value) || new Date();
        // Nothing reaches the real input until Confirm — `pending` is the
        // in-progress value the whole panel (calendar + time pane) edits.
        var pending = parseValue(input.value);

        function syncText() {
            var d = parseValue(input.value);
            text.textContent = formatDisplay(d);
            wrap.classList.toggle('has-value', !!d);
        }

        function syncDisabled() {
            trigger.disabled = input.disabled;
        }

        function commit(d) {
            input.value = d ? toInputValue(d) : '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            syncText();
        }

        function decadeStart(year) { return Math.floor(year / 12) * 12; }

        function go(newViewDate) { viewDate = newViewDate; buildPanel(); }
        function zoom(newView) { view = newView; buildPanel(); }

        // Applies a day pick onto whatever time pending already carries (or
        // a sensible 9:00 AM default the first time), the same way the
        // calendar pane and the time pane each edit one shared `pending`
        // value instead of two independent ones.
        function pickDay(day) {
            var h = pending ? pending.getHours() : 9;
            var m = pending ? pending.getMinutes() : 0;
            pending = new Date(viewDate.getFullYear(), viewDate.getMonth(), day, h, m);
            buildPanel();
        }

        function pickTime(h24, minute) {
            var base = pending || viewDate;
            pending = new Date(base.getFullYear(), base.getMonth(), base.getDate(), h24, minute);
            buildPanel();
        }

        function jumpDays(n) {
            var base = pending || new Date();
            var h = pending ? pending.getHours() : 9;
            var m = pending ? pending.getMinutes() : 0;
            pending = n === 0
                ? new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate(), h, m)
                : new Date(base.getFullYear(), base.getMonth(), base.getDate() + n, h, m);
            view = 'days';
            viewDate = pending;
            buildPanel();
        }

        function buildPresets() {
            var row = document.createElement('div');
            row.className = 'ac-dt__presets';

            var label = document.createElement('span');
            label.className = 'ac-dt__presets-label';
            label.textContent = 'Quick presets';
            row.appendChild(label);

            [['Today', 0], ['+7 Days', 7], ['+14 Days', 14]].forEach(function (pair) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ac-dt__preset';
                btn.textContent = pair[0];
                btn.addEventListener('click', function () { jumpDays(pair[1]); });
                row.appendChild(btn);
            });

            return row;
        }

        function buildHeader() {
            var header = document.createElement('div');
            header.className = 'ac-dt__header';

            var label = document.createElement('button');
            label.type = 'button';
            label.className = 'ac-dt__label';

            var nav = document.createElement('div');
            nav.className = 'ac-dt__nav';
            var prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'ac-dt__navbtn';
            prevBtn.setAttribute('aria-label', 'Previous');
            prevBtn.appendChild(iconEl('chevron_left'));
            var nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'ac-dt__navbtn';
            nextBtn.setAttribute('aria-label', 'Next');
            nextBtn.appendChild(iconEl('chevron_right'));

            if (view === 'days') {
                label.textContent = MONTHS[viewDate.getMonth()] + ' ' + viewDate.getFullYear();
                label.appendChild(iconEl('expand_more'));
                label.addEventListener('click', function () { zoom('months'); });
                prevBtn.addEventListener('click', function () { go(new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1)); });
                nextBtn.addEventListener('click', function () { go(new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1)); });
            } else if (view === 'months') {
                label.textContent = String(viewDate.getFullYear());
                label.appendChild(iconEl('expand_more'));
                label.addEventListener('click', function () { zoom('years'); });
                prevBtn.addEventListener('click', function () { go(new Date(viewDate.getFullYear() - 1, viewDate.getMonth(), 1)); });
                nextBtn.addEventListener('click', function () { go(new Date(viewDate.getFullYear() + 1, viewDate.getMonth(), 1)); });
            } else {
                var start = decadeStart(viewDate.getFullYear());
                label.textContent = start + ' – ' + (start + 11);
                label.disabled = true;
                prevBtn.addEventListener('click', function () { go(new Date(viewDate.getFullYear() - 12, viewDate.getMonth(), 1)); });
                nextBtn.addEventListener('click', function () { go(new Date(viewDate.getFullYear() + 12, viewDate.getMonth(), 1)); });
            }

            nav.appendChild(prevBtn);
            nav.appendChild(nextBtn);
            header.appendChild(label);
            header.appendChild(nav);
            return header;
        }

        function buildDaysGrid() {
            var grid = document.createElement('div');
            grid.className = 'ac-dt__grid ac-dt__grid--days';
            DOW.forEach(function (d) {
                var el = document.createElement('span');
                el.className = 'ac-dt__dow';
                el.textContent = d;
                grid.appendChild(el);
            });

            var first = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
            var daysInMonth = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
            var today = new Date();

            for (var i = 0; i < first.getDay(); i++) {
                var padCell = document.createElement('span');
                padCell.className = 'ac-dt__pad';
                grid.appendChild(padCell);
            }

            var makeDayBtn = function (day) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ac-dt__cell ac-dt__cell--day';
                btn.textContent = String(day);
                var cellDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), day);
                if (pending && sameDay(pending, cellDate)) btn.classList.add('is-selected');
                if (sameDay(today, cellDate)) btn.classList.add('is-today');
                btn.addEventListener('click', function () { pickDay(day); });
                return btn;
            };
            for (var day = 1; day <= daysInMonth; day++) grid.appendChild(makeDayBtn(day));

            return grid;
        }

        function buildMonthsGrid() {
            var grid = document.createElement('div');
            grid.className = 'ac-dt__grid ac-dt__grid--months';
            var now = new Date();

            MONTHS_SHORT.forEach(function (name, idx) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ac-dt__cell ac-dt__cell--month';
                btn.textContent = name;
                if (viewDate.getFullYear() === now.getFullYear() && idx === now.getMonth()) btn.classList.add('is-today');
                if (pending && pending.getFullYear() === viewDate.getFullYear() && pending.getMonth() === idx) btn.classList.add('is-selected');
                btn.addEventListener('click', function () {
                    viewDate = new Date(viewDate.getFullYear(), idx, 1);
                    zoom('days');
                });
                grid.appendChild(btn);
            });

            return grid;
        }

        function buildYearsGrid() {
            var grid = document.createElement('div');
            grid.className = 'ac-dt__grid ac-dt__grid--years';
            var start = decadeStart(viewDate.getFullYear());
            var now = new Date();

            for (var y = start; y < start + 12; y++) {
                (function (year) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'ac-dt__cell ac-dt__cell--year';
                    btn.textContent = String(year);
                    if (year === now.getFullYear()) btn.classList.add('is-today');
                    if (pending && pending.getFullYear() === year) btn.classList.add('is-selected');
                    btn.addEventListener('click', function () {
                        viewDate = new Date(year, viewDate.getMonth(), 1);
                        zoom('months');
                    });
                    grid.appendChild(btn);
                })(y);
            }

            return grid;
        }

        function buildCalendarPane() {
            var pane = document.createElement('div');
            pane.className = 'ac-dt__calendar';
            pane.appendChild(buildHeader());
            pane.appendChild(view === 'days' ? buildDaysGrid() : (view === 'months' ? buildMonthsGrid() : buildYearsGrid()));
            return pane;
        }

        // One Hour/Minute quick-pick column — a plain scrollable list
        // (5-minute steps for minutes) instead of a spinner, so most values
        // are a single click away; anything finer-grained goes through the
        // text field above it instead.
        function buildQuickColumn(label, values, formatter, current, onPick) {
            var wrapCol = document.createElement('div');
            wrapCol.className = 'ac-dt__col';

            var head = document.createElement('span');
            head.className = 'ac-dt__col-label';
            head.textContent = label;
            wrapCol.appendChild(head);

            var list = document.createElement('div');
            list.className = 'ac-dt__col-list';
            values.forEach(function (v) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ac-dt__colcell';
                btn.textContent = formatter(v);
                if (v === current) btn.classList.add('is-selected');
                btn.addEventListener('click', function () { onPick(v); });
                list.appendChild(btn);
            });
            wrapCol.appendChild(list);

            requestAnimationFrame(function () {
                var sel = list.querySelector('.is-selected');
                if (sel) sel.scrollIntoView({ block: 'center' });
            });

            return wrapCol;
        }

        function buildTimePane() {
            var pane = document.createElement('div');
            pane.className = 'ac-dt__timepane';

            var hour = pending ? pending.getHours() : 9;
            var minute = pending ? pending.getMinutes() : 0;
            var hour12 = hour % 12 || 12;
            var isPM = hour >= 12;

            var topRow = document.createElement('div');
            topRow.className = 'ac-dt__time-head';
            var caption = document.createElement('span');
            caption.className = 'ac-dt__time-caption';
            caption.appendChild(iconEl('schedule'));
            caption.appendChild(document.createTextNode('Time'));
            var badge = document.createElement('span');
            badge.className = 'ac-dt__time-badge';
            badge.textContent = pad(hour12) + ':' + pad(minute) + ' ' + (isPM ? 'PM' : 'AM');
            topRow.appendChild(caption);
            topRow.appendChild(badge);
            pane.appendChild(topRow);

            var editRow = document.createElement('div');
            editRow.className = 'ac-dt__time-edit';

            var timeText = document.createElement('input');
            timeText.type = 'text';
            timeText.className = 'ac-dt__time-text';
            timeText.inputMode = 'numeric';
            timeText.placeholder = 'HH:MM';
            timeText.value = pad(hour12) + ':' + pad(minute);
            timeText.addEventListener('change', function () {
                var m = /^(\d{1,2}):(\d{2})$/.exec(timeText.value.trim());
                if (!m) { timeText.value = pad(hour12) + ':' + pad(minute); return; }
                var h = Math.min(12, Math.max(1, +m[1]));
                var min = Math.min(59, Math.max(0, +m[2]));
                pickTime((h % 12) + (isPM ? 12 : 0), min);
            });
            editRow.appendChild(timeText);

            var pill = document.createElement('div');
            pill.className = 'ac-dt__ampm';
            ['AM', 'PM'].forEach(function (label) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ac-dt__ampm-btn' + ((label === 'PM') === isPM ? ' is-selected' : '');
                btn.textContent = label;
                btn.addEventListener('click', function () {
                    pickTime((hour % 12) + (label === 'PM' ? 12 : 0), minute);
                });
                pill.appendChild(btn);
            });
            editRow.appendChild(pill);
            pane.appendChild(editRow);

            var hours = [];
            for (var h = 1; h <= 12; h++) hours.push(h);
            var minutes = [];
            for (var mm = 0; mm < 60; mm += 5) minutes.push(mm);

            var cols = document.createElement('div');
            cols.className = 'ac-dt__cols';
            cols.appendChild(buildQuickColumn('Hour', hours, function (v) { return pad(v); }, hour12, function (v) {
                pickTime((v % 12) + (isPM ? 12 : 0), minute);
            }));
            cols.appendChild(buildQuickColumn('Min', minutes, function (v) { return pad(v); }, minute - (minute % 5), function (v) {
                pickTime(hour, v);
            }));
            pane.appendChild(cols);

            var shortcuts = document.createElement('div');
            shortcuts.className = 'ac-dt__shortcuts';
            var shortcutsLabel = document.createElement('span');
            shortcutsLabel.className = 'ac-dt__shortcuts-label';
            shortcutsLabel.textContent = 'Common cutoffs';
            shortcuts.appendChild(shortcutsLabel);
            var shortcutRow = document.createElement('div');
            shortcutRow.className = 'ac-dt__shortcuts-row';
            [['5:00 PM', 17, 0], ['11:59 PM', 23, 59]].forEach(function (s) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ac-dt__shortcut';
                btn.textContent = s[0];
                btn.addEventListener('click', function () { pickTime(s[1], s[2]); });
                shortcutRow.appendChild(btn);
            });
            shortcuts.appendChild(shortcutRow);
            pane.appendChild(shortcuts);

            return pane;
        }

        function buildFooter() {
            var footer = document.createElement('div');
            footer.className = 'ac-dt__footer';

            var left = document.createElement('div');
            left.className = 'ac-dt__footer-left';

            var clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'ac-dt__link ac-dt__link--danger';
            clearBtn.appendChild(iconEl('restart_alt'));
            clearBtn.appendChild(document.createTextNode('Clear'));
            clearBtn.addEventListener('click', function () { pending = null; commit(null); close(); });

            var todayBtn = document.createElement('button');
            todayBtn.type = 'button';
            todayBtn.className = 'ac-dt__today-btn';
            todayBtn.textContent = 'Today';
            todayBtn.addEventListener('click', function () { jumpDays(0); });

            left.appendChild(clearBtn);
            left.appendChild(todayBtn);

            var right = document.createElement('div');
            right.className = 'ac-dt__footer-right';

            var summary = document.createElement('span');
            summary.className = 'ac-dt__summary';
            summary.textContent = 'Selection: ' + formatDisplay(pending);

            var confirmBtn = document.createElement('button');
            confirmBtn.type = 'button';
            confirmBtn.className = 'ac-btn ac-btn-primary ac-dt__confirm';
            confirmBtn.textContent = 'Apply';
            confirmBtn.disabled = !pending;
            confirmBtn.addEventListener('click', function () { commit(pending); close(); });

            right.appendChild(summary);
            right.appendChild(confirmBtn);

            footer.appendChild(left);
            footer.appendChild(right);
            return footer;
        }

        function buildPanel() {
            panel.replaceChildren();
            panel.appendChild(buildPresets());

            var body = document.createElement('div');
            body.className = 'ac-dt__body';
            body.appendChild(buildCalendarPane());
            if (view === 'days') body.appendChild(buildTimePane());
            panel.appendChild(body);

            panel.appendChild(buildFooter());
            positionPanel();
        }

        function positionPanel() {
            var margin = 8;
            var triggerRect = trigger.getBoundingClientRect();
            var panelWidth = panel.getBoundingClientRect().width || 460;
            var left = triggerRect.left;
            if (left + panelWidth > window.innerWidth - margin) left = window.innerWidth - margin - panelWidth;
            if (left < margin) left = margin;

            var panelHeight = panel.getBoundingClientRect().height;
            var top = triggerRect.bottom + 6;
            if (top + panelHeight > window.innerHeight - margin && triggerRect.top - panelHeight - 6 > margin) {
                top = triggerRect.top - panelHeight - 6;
            }

            panel.style.left = left + 'px';
            panel.style.top = top + 'px';
        }

        function onOutside(e) { if (!wrap.contains(e.target)) close(); }
        function onKeydown(e) { if (e.key === 'Escape') { close(); trigger.focus(); } }

        function open() {
            if (trigger.disabled) return;
            view = 'days';
            pending = parseValue(input.value);
            viewDate = pending || new Date();
            buildPanel();
            wrap.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            if (usePopover) panel.showPopover();
            positionPanel();
            document.addEventListener('click', onOutside, true);
            document.addEventListener('keydown', onKeydown, true);
            window.addEventListener('resize', positionPanel);
            window.addEventListener('scroll', positionPanel, true);
        }

        function close() {
            wrap.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            if (usePopover && panel.matches(':popover-open')) panel.hidePopover();
            document.removeEventListener('click', onOutside, true);
            document.removeEventListener('keydown', onKeydown, true);
            window.removeEventListener('resize', positionPanel);
            window.removeEventListener('scroll', positionPanel, true);
        }

        trigger.addEventListener('click', function () {
            if (wrap.classList.contains('is-open')) close(); else open();
        });

        new MutationObserver(function () { syncText(); syncDisabled(); })
            .observe(input, { attributes: true, attributeFilter: ['value', 'disabled'] });
        input.addEventListener('change', syncText);

        syncText();
        syncDisabled();
        return { sync: syncText, close: close, trigger: trigger };
    };
})();

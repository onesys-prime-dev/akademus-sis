(function () {
    'use strict';

    var configEl = document.getElementById('sgConfig');
    if (!configEl) return;

    var terms = JSON.parse(configEl.textContent || '{}').terms || [];
    if (terms.length === 0) return;

    var yearSelect = document.getElementById('sgYear');
    var semesterSelect = document.getElementById('sgSemester');
    var tbody = document.getElementById('sgTableBody');
    var emptyTerm = document.getElementById('sgEmptyTerm');
    var tableCard = document.querySelector('.sg-table-card');
    var gwaValue = document.getElementById('sgGwaValue');
    var gwaMeta = document.getElementById('sgGwaMeta');
    var termTitle = document.getElementById('sgTermTitle');
    var courseCount = document.getElementById('sgCourseCount');
    var unitCount = document.getElementById('sgUnitCount');

    function uniqueInOrder(values) {
        var seen = {};
        var out = [];
        values.forEach(function (v) {
            if (!seen[v]) { seen[v] = true; out.push(v); }
        });
        return out;
    }

    var years = uniqueInOrder(terms.map(function (t) { return t.year; }));

    function semestersForYear(year) {
        return uniqueInOrder(terms.filter(function (t) { return t.year === year; }).map(function (t) { return t.semester; }));
    }

    function fillSelect(select, options) {
        select.innerHTML = '';
        options.forEach(function (opt) {
            var el = document.createElement('option');
            el.value = opt;
            el.textContent = opt || 'All';
            select.appendChild(el);
        });
    }

    function findTerm(year, semester) {
        return terms.find(function (t) { return t.year === year && t.semester === semester; }) || null;
    }

    function passRemarkClass(isPassing) {
        if (isPassing === true) return 'sg-pill sg-pill--pass';
        if (isPassing === false) return 'sg-pill sg-pill--fail';
        return 'sg-pill';
    }

    function passRemarkLabel(isPassing, remarks) {
        if (remarks) return remarks;
        if (isPassing === true) return 'PASSED';
        if (isPassing === false) return 'FAILED';
        return '—';
    }

    function render() {
        var year = yearSelect.value;
        var semester = semesterSelect.value;
        var term = findTerm(year, semester);
        var rows = term ? term.rows : [];

        tbody.innerHTML = '';

        if (rows.length === 0) {
            tableCard.querySelector('.sg-table-scroll').hidden = true;
            emptyTerm.hidden = false;
        } else {
            tableCard.querySelector('.sg-table-scroll').hidden = false;
            emptyTerm.hidden = true;

            rows.forEach(function (g) {
                var tr = document.createElement('tr');
                var gradeClass = g.is_passing === false ? 'sg-grade is-fail' : 'sg-grade';
                tr.innerHTML =
                    '<td data-label="Course"><span class="sg-course-code">' + escapeHtml(g.code) + '</span><span class="sg-course-title">' + escapeHtml(g.title) + '</span></td>' +
                    '<td data-label="Class details"><span class="sg-cell-primary">' + escapeHtml(g.schedule) + '</span><span class="sg-cell-secondary">Instructor · ' + escapeHtml(g.instructor) + '</span></td>' +
                    '<td data-label="Enrollment"><span class="sg-cell-primary">' + escapeHtml(g.program) + '</span><span class="sg-cell-secondary">' + escapeHtml(g.year_level) + ' · Section ' + escapeHtml(g.section) + '</span></td>' +
                    '<td data-label="Units"><span class="sg-cell-primary">' + escapeHtml(g.units) + '</span></td>' +
                    '<td data-label="Final grade"><span class="' + gradeClass + '">' + escapeHtml(g.grade) + '</span></td>' +
                    '<td data-label="Result"><span class="' + passRemarkClass(g.is_passing) + '">' + escapeHtml(passRemarkLabel(g.is_passing, g.remarks)) + '</span></td>';
                tbody.appendChild(tr);
            });
        }

        var totalUnits = rows.reduce(function (total, row) {
            var units = Number(row.units);
            return total + (Number.isFinite(units) ? units : 0);
        }, 0);
        termTitle.textContent = term ? term.year + (term.semester ? ' · ' + term.semester : '') : 'Official grades';
        courseCount.textContent = rows.length;
        unitCount.textContent = totalUnits % 1 === 0 ? String(totalUnits) : totalUnits.toFixed(1);
        gwaMeta.textContent = rows.length
            ? 'Weighted across ' + (totalUnits % 1 === 0 ? String(totalUnits) : totalUnits.toFixed(1)) + ' posted units'
            : 'No posted numeric grades for this term';

        if (term && term.gwa !== null && term.gwa !== undefined) {
            gwaValue.textContent = Number(term.gwa).toFixed(2);
        } else {
            gwaValue.textContent = '—';
        }
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    yearSelect.addEventListener('change', function () {
        fillSelect(semesterSelect, semestersForYear(yearSelect.value));
        render();
    });
    semesterSelect.addEventListener('change', render);

    fillSelect(yearSelect, years);
    fillSelect(semesterSelect, semestersForYear(years[0]));
    render();
})();

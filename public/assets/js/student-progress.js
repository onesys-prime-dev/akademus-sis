(function () {
    'use strict';

    var configEl = document.getElementById('spConfig');
    if (!configEl) return;

    var config = JSON.parse(configEl.textContent || '{}');
    var dialog = document.getElementById('spDetailDialog');
    var closeBtn = document.getElementById('spCloseDetail');
    var nameEl = document.getElementById('spDetailName');
    var metaEl = document.getElementById('spDetailMeta');
    var bodyEl = document.getElementById('spDetailBody');

    if (!dialog) return;

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function subjectList(items, emptyText, extra) {
        if (!items || items.length === 0) {
            return '<p class="sp-list-empty">' + escapeHtml(emptyText) + '</p>';
        }

        return '<ul class="sp-subject-list">' + items.map(function (item) {
            var extraHtml = extra ? extra(item) : '';
            var via = item.via ? '<small>via ' + escapeHtml(item.via) + '</small>' : '';

            return '<li><div><strong>' + escapeHtml(item.code) + '</strong><span>' + escapeHtml(item.title) + '</span>' + via + '</div>' + extraHtml + '</li>';
        }).join('') + '</ul>';
    }

    function render(student, progress) {
        var fullName = (student.last_name || '') + ', ' + (student.first_name || '');
        nameEl.textContent = fullName;
        metaEl.textContent = [student.student_number, student.program_name, student.curriculum_code].filter(Boolean).join(' · ');

        var pct = progress.completion_pct;
        var pctLabel = pct === null ? 'No curriculum assigned' : pct + '% complete';

        var html = '';
        html += '<div class="sp-detail-progress">';
        html += '<div class="sp-progress-bar sp-progress-bar--lg"><span style="width:' + (pct || 0) + '%"></span></div>';
        html += '<p>' + escapeHtml(pctLabel) + (pct !== null ? ' (' + progress.completed_units + ' / ' + progress.total_units + ' units)' : '') + '</p>';
        html += '</div>';

        html += '<section class="sp-detail-section"><h3>Failed <span>' + progress.failed.length + '</span></h3>' +
            subjectList(progress.failed, 'No failed subjects.', function (i) { return '<span class="sp-pill sp-pill--fail">' + escapeHtml(i.grade_value) + '</span>'; }) + '</section>';

        html += '<section class="sp-detail-section"><h3>Unresolved <span>' + progress.unresolved.length + '</span></h3>' +
            subjectList(progress.unresolved, 'None.', function (i) { return '<span class="sp-pill">' + escapeHtml(i.grade_value) + '</span>'; }) + '</section>';

        html += '<section class="sp-detail-section"><h3>In progress this term <span>' + progress.in_progress.length + '</span></h3>' +
            subjectList(progress.in_progress, 'Nothing currently enrolled.') + '</section>';

        var notStarted = progress.remaining.filter(function (r) { return r.status === 'not_started'; });
        html += '<section class="sp-detail-section"><h3>Not yet started <span>' + notStarted.length + '</span></h3>' +
            subjectList(notStarted, 'Nothing left to start.', function (i) {
                if (i.missing_prerequisites && i.missing_prerequisites.length) {
                    return '<span class="sp-pill sp-pill--warn">Needs ' + i.missing_prerequisites.map(escapeHtml).join(', ') + '</span>';
                }
                return '<span class="sp-pill sp-pill--ready">Eligible to enroll</span>';
            }) + '</section>';

        html += '<section class="sp-detail-section"><h3>Passed <span>' + progress.passed.length + '</span></h3>' +
            subjectList(progress.passed, 'None yet.') + '</section>';

        bodyEl.innerHTML = html;
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.sp-view-btn');
        if (!btn) return;

        var studentId = btn.getAttribute('data-student-id');

        bodyEl.innerHTML = '<p class="sp-list-empty">Loading…</p>';
        nameEl.textContent = '';
        metaEl.textContent = '';

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }

        fetch(config.detailUrl + '?student_id=' + encodeURIComponent(studentId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    bodyEl.innerHTML = '<p class="sp-list-empty">' + escapeHtml(data.error || 'Could not load this student.') + '</p>';
                    return;
                }
                render(data.student, data.progress);
            })
            .catch(function () {
                bodyEl.innerHTML = '<p class="sp-list-empty">Could not load this student.</p>';
            });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () { dialog.close ? dialog.close() : dialog.removeAttribute('open'); });
    }

    dialog.addEventListener('click', function (e) {
        if (e.target === dialog) {
            dialog.close ? dialog.close() : dialog.removeAttribute('open');
        }
    });
})();

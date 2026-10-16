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

    var currentStudentId = null;

    function render(student, progress, academicYear, overrides) {
        var fullName = (student.last_name || '') + ', ' + (student.first_name || '');
        nameEl.textContent = fullName;
        metaEl.textContent = [student.student_number, student.program_name, student.curriculum_code].filter(Boolean).join(' · ');

        var overrideByCode = {};
        (overrides || []).forEach(function (o) { overrideByCode[o.subject_code] = o; });

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
                var override = overrideByCode[i.code];

                if (override) {
                    return '<span class="sp-extra">' +
                        '<span class="sp-pill sp-pill--ready">Requirement Waived</span>' +
                        '<button type="button" class="sp-link-btn" data-revoke-override="' + override.id + '">Revoke</button>' +
                        '</span>';
                }

                if (i.missing_prerequisites && i.missing_prerequisites.length) {
                    var needsPill = '<span class="sp-pill sp-pill--warn">Needs ' + i.missing_prerequisites.map(escapeHtml).join(', ') + '</span>';

                    if (!academicYear) {
                        return needsPill;
                    }

                    return '<span class="sp-extra">' + needsPill +
                        '<button type="button" class="sp-link-btn" data-grant-override="' + escapeHtml(i.code) + '">Grant Override</button>' +
                        '</span>';
                }

                return '<span class="sp-pill sp-pill--ready">Eligible to enroll</span>';
            }) + '</section>';

        html += '<section class="sp-detail-section"><h3>Passed <span>' + progress.passed.length + '</span></h3>' +
            subjectList(progress.passed, 'None yet.') + '</section>';

        bodyEl.innerHTML = html;
    }

    function reloadDetail(studentId) {
        return fetch(config.detailUrl + '?student_id=' + encodeURIComponent(studentId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    bodyEl.innerHTML = '<p class="sp-list-empty">' + escapeHtml(data.error || 'Could not load this student.') + '</p>';
                    return;
                }
                render(data.student, data.progress, data.academicYear, data.overrides);
            });
    }

    document.addEventListener('click', function (e) {
        var viewBtn = e.target.closest('.sp-view-btn');

        if (viewBtn) {
            currentStudentId = viewBtn.getAttribute('data-student-id');

            bodyEl.innerHTML = '<p class="sp-list-empty">Loading…</p>';
            nameEl.textContent = '';
            metaEl.textContent = '';

            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', 'open');
            }

            reloadDetail(currentStudentId).catch(function () {
                bodyEl.innerHTML = '<p class="sp-list-empty">Could not load this student.</p>';
            });
            return;
        }

        var grantBtn = e.target.closest('[data-grant-override]');

        if (grantBtn && currentStudentId) {
            var subjectCode = grantBtn.getAttribute('data-grant-override');
            var reason = window.prompt('Reason for granting ' + subjectCode + ' to this student (required):');

            if (!reason || !reason.trim()) return;

            grantBtn.disabled = true;

            fetch(config.grantUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'student_id=' + encodeURIComponent(currentStudentId) + '&subject_code=' + encodeURIComponent(subjectCode) + '&reason=' + encodeURIComponent(reason.trim())
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        window.alert(data.error || 'Could not grant this override.');
                        grantBtn.disabled = false;
                        return;
                    }
                    reloadDetail(currentStudentId);
                })
                .catch(function () {
                    window.alert('Could not grant this override.');
                    grantBtn.disabled = false;
                });
            return;
        }

        var revokeBtn = e.target.closest('[data-revoke-override]');

        if (revokeBtn && currentStudentId) {
            var overrideId = revokeBtn.getAttribute('data-revoke-override');

            if (!window.confirm('Revoke this override? The subject will become blocked again.')) return;

            revokeBtn.disabled = true;

            fetch(config.revokeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'override_id=' + encodeURIComponent(overrideId)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        window.alert(data.error || 'Could not revoke this override.');
                        revokeBtn.disabled = false;
                        return;
                    }
                    reloadDetail(currentStudentId);
                })
                .catch(function () {
                    window.alert('Could not revoke this override.');
                    revokeBtn.disabled = false;
                });
        }
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

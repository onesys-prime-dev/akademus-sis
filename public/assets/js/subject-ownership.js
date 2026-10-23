(() => {
    'use strict';
    const configNode = document.getElementById('soConfig');
    if (!configNode) return;
    const config = JSON.parse(configNode.textContent);
    const byId = id => document.getElementById(id);
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    let subjects = config.subjects;
    let page = 1, status = 'all', editingId = null, statusId = null, busy = false;
    const pageSize = 10;
    const editor = byId('soEditor'), statusDialog = byId('soStatusDialog'), form = byId('soForm');
    const search = byId('soSearch'), type = byId('soType'), department = byId('soDepartment');
    const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
    const SEMESTERS = { 1: '1st Semester', 2: '2nd Semester', 3: 'Summer' };
    const courseGroups = byId('soCourseGroups'), courseDept = byId('soCourseDept');
    let displayRows = [];

    function roundHalf(n) { return Math.round(n * 2) / 2; }
    function formatNum(n) { return Number.isInteger(n) ? String(n) : n.toFixed(1); }
    function formatHrMin(hours) {
        const totalMin = Math.max(0, Math.round(hours * 60));
        const h = Math.floor(totalMin / 60), m = totalMin % 60;
        if (h && m) return `${h} hr ${m} min`;
        if (h) return `${h} ${h === 1 ? 'hr' : 'hrs'}`;
        return `${m} min`;
    }

    /** Merges the raw (always single-type) subject rows into one display row per logical subject. */
    function buildDisplayRows() {
        const byIdMap = new Map(subjects.map(s => [s.id, s]));
        const visited = new Set();
        const rows = [];
        subjects.forEach(s => {
            if (visited.has(s.id)) return;
            const partner = s.pairId ? byIdMap.get(s.pairId) : null;
            visited.add(s.id);
            if (partner) visited.add(partner.id);
            const lecture = s.is_lab ? (partner || null) : s;
            const lab = s.is_lab ? s : (partner || null);
            const primary = lecture || lab;
            rows.push({
                id: primary.id,
                code: primary.code,
                secondaryCode: lecture && lab ? (primary === lecture ? lab.code : lecture.code) : null,
                title: primary.title,
                department: primary.department,
                active: primary.active,
                lecture, lab,
                totalUnits: (lecture ? lecture.units : 0) + (lab ? lab.units : 0),
                totalHours: (lecture ? lecture.duration : 0) + (lab ? lab.duration : 0),
            });
        });
        return rows;
    }

    function renderCourses() {
        if (!courseGroups) return;
        const expanded = new Set([...courseGroups.querySelectorAll('details[open]')].map(node => node.dataset.course));
        const deptIds = Object.keys(config.departments).map(Number);
        const deptId = courseDept ? Number(courseDept.value) : deptIds[0];
        const courses = config.courses[deptId] || [];
        const deptSubjects = subjects.filter(s => s.department === deptId);
        byId('soCourseEmpty').hidden = !!courses.length;

        const assigned = new Set();
        const courseHtml = courses.map(course => {
            const terms = new Map();
            const courseSubjects = new Set();
            deptSubjects.forEach(subject => {
                (config.subjectCourses[subject.id] || [])
                    .filter(a => a.program_id === course.id)
                    .forEach(a => {
                        assigned.add(subject.id);
                        courseSubjects.add(subject.id);
                        const key = `${a.year_level}-${a.semester}`;
                        if (!terms.has(key)) terms.set(key, { year: a.year_level, semester: a.semester, subjects: [] });
                        terms.get(key).subjects.push(subject);
                    });
            });
            const termRows = [...terms.values()].sort((a, b) => a.year - b.year || a.semester - b.semester).map(t => `
                <div class="so-course-term">
                    <span class="so-course-term-label">Year ${t.year} · ${escape(SEMESTERS[t.semester] || `Term ${t.semester}`)}</span>
                    <div class="so-course-chips">${t.subjects.map(s => `<span class="so-chip ${s.active ? '' : 'is-inactive'}" title="${escape(s.title)}">${escape(s.code)}</span>`).join('')}</div>
                </div>`).join('');
            return `<details class="so-course-card" data-course="${escape(course.id)}" ${expanded.has(String(course.id)) ? 'open' : ''}>
                <summary><span class="so-course-icon material-symbols-outlined" aria-hidden="true">school</span><span class="so-course-name"><strong>${escape(course.code)}</strong><span>${escape(course.name)}</span></span><span class="so-course-total">${courseSubjects.size} <span>subjects</span></span><span class="so-course-chevron material-symbols-outlined" aria-hidden="true">expand_more</span></summary>
                <div class="so-course-content">${termRows || '<p class="so-course-empty">No subjects from your department are assigned to this course yet.</p>'}</div>
            </details>`;
        }).join('');

        const unassigned = deptSubjects.filter(s => !assigned.has(s.id));
        const unassignedHtml = unassigned.length ? `<details class="so-course-card so-course-card--unassigned" data-course="unassigned" ${expanded.has('unassigned') ? 'open' : ''}>
            <summary><span class="so-course-icon material-symbols-outlined" aria-hidden="true">inventory_2</span><span class="so-course-name"><strong>Not yet assigned</strong><span>Subjects awaiting course placement</span></span><span class="so-course-total">${unassigned.length} <span>subjects</span></span><span class="so-course-chevron material-symbols-outlined" aria-hidden="true">expand_more</span></summary>
            <div class="so-course-content"><div class="so-course-chips">${unassigned.map(s => `<span class="so-chip ${s.active ? '' : 'is-inactive'}" title="${escape(s.title)}">${escape(s.code)}</span>`).join('')}</div></div>
        </details>` : '';

        courseGroups.innerHTML = courseHtml + unassignedHtml;
    }
    courseDept?.addEventListener('change', renderCourses);

    function feedback(message) {
        byId('soFeedback').textContent = message;
        byId('soFeedback').hidden = false;
    }
    function errorIn(id, message) {
        byId(id).textContent = message;
        byId(id).hidden = false;
        byId(id).scrollIntoView({ block: 'nearest' });
    }
    function render() {
        const active = subjects.filter(subject => subject.active).length;
        byId('soTotal').textContent = subjects.length;
        byId('soActive').textContent = active;
        byId('soInactive').textContent = subjects.length - active;

        const all = buildDisplayRows();
        byId('soAllTab').textContent = all.length;
        byId('soCatalogCount').textContent = all.length;
        const query = search.value.trim().toLocaleLowerCase();
        const filtered = all.filter(r =>
            (!query || `${r.code} ${r.secondaryCode || ''} ${r.title}`.toLocaleLowerCase().includes(query)) &&
            (status === 'all' || r.active === (status === 'active')) &&
            (type.value === 'all' || (type.value === 'lecture' ? !!r.lecture : !!r.lab)) &&
            (!department || department.value === 'all' || r.department === Number(department.value))
        ).sort((a, b) => collator.compare(a[byId('soSort').value], b[byId('soSort').value]) || a.id - b.id);
        const pages = Math.max(1, Math.ceil(filtered.length / pageSize));
        page = Math.min(page, pages);
        const start = (page - 1) * pageSize;
        displayRows = filtered.slice(start, start + pageSize);
        byId('soRows').innerHTML = displayRows.map(r => {
            const mixed = r.lecture && r.lab;
            const typeLabel = mixed ? 'Lecture + Lab' : (r.lab ? 'Laboratory' : 'Lecture');
            const typeClass = mixed ? 'so-type--mixed' : (r.lab ? 'so-type--lab' : '');
            const lengthLabel = mixed
                ? `Lec ${formatHrMin(r.lecture.duration)} · Lab ${formatHrMin(r.lab.duration)}`
                : formatHrMin((r.lecture || r.lab).duration);
            return `<tr data-id="${r.id}" class="${r.active ? '' : 'is-inactive'}">
            <td><div class="so-subject"><span class="so-subject-icon material-symbols-outlined" aria-hidden="true">${mixed ? 'science' : (r.lab ? 'science' : 'menu_book')}</span><div><strong class="so-subject-code">${escape(r.code)}${r.secondaryCode ? ` <small class="so-subject-dept">+ ${escape(r.secondaryCode)}</small>` : ''}</strong><span class="so-subject-title">${escape(r.title)}</span>${Object.keys(config.departments).length > 1 ? `<small class="so-subject-dept">${escape(config.departments[r.department] || 'Department')}</small>` : ''}</div></div></td>
            <td class="so-units">${formatNum(r.totalUnits)}</td><td><span class="so-type ${typeClass}">${typeLabel}</span></td><td class="so-duration">${lengthLabel}</td>
            <td><span class="so-status ${r.active ? 'so-status--active' : ''}"><i class="so-dot"></i>${r.active ? 'Active' : 'Inactive'}</span></td>
            <td><div class="so-row-actions"><button type="button" class="so-edit" data-action="edit" aria-label="Edit ${escape(r.code)}"><span class="material-symbols-outlined" aria-hidden="true">edit</span>Edit</button><button type="button" class="so-icon-button so-toggle" data-action="status" title="${r.active ? 'Deactivate' : 'Activate'} ${escape(r.code)}" aria-label="${r.active ? 'Deactivate' : 'Activate'} ${escape(r.code)}"><span class="material-symbols-outlined" aria-hidden="true">${r.active ? 'pause_circle' : 'play_circle'}</span></button></div></td></tr>`;
        }).join('');
        byId('soTableWrap').hidden = !filtered.length;
        byId('soEmpty').hidden = !!filtered.length;
        byId('soEmptyTitle').textContent = all.length ? 'No matching subjects' : 'Your catalog starts here';
        byId('soEmptyText').textContent = all.length ? 'Try a different search or adjust your filters.' : 'Add your first subject to make it available to programs across departments.';
        byId('soEmpty').querySelector('.material-symbols-outlined').textContent = all.length ? 'search_off' : 'library_books';
        byId('soClearFilters').hidden = !all.length;
        byId('soEmptyAdd').hidden = !!all.length;
        byId('soRange').textContent = filtered.length ? `Showing ${start + 1}–${Math.min(start + pageSize, filtered.length)} of ${filtered.length} subjects` : '0 subjects';
        byId('soPageNumber').textContent = `${page} / ${pages}`;
        byId('soPrevious').disabled = page === 1;
        byId('soNext').disabled = page >= pages;
    }
    function resetFilters() {
        search.value = ''; type.value = 'all'; if (department) department.value = 'all';
        status = 'all'; page = 1;
        document.querySelectorAll('[data-status]').forEach(button => {
            const selected = button.dataset.status === status;
            button.classList.toggle('is-active', selected); button.setAttribute('aria-pressed', String(selected));
        });
        render();
    }
    [search, type, department, byId('soSort')].filter(Boolean).forEach(input => input.addEventListener(input === search ? 'input' : 'change', () => { page = 1; render(); }));
    document.querySelectorAll('[data-status]').forEach(button => button.addEventListener('click', () => {
        status = button.dataset.status; page = 1;
        document.querySelectorAll('[data-status]').forEach(tab => { tab.classList.toggle('is-active', tab === button); tab.setAttribute('aria-pressed', String(tab === button)); });
        render();
    }));
    byId('soClearFilters').addEventListener('click', resetFilters);
    byId('soPrevious').addEventListener('click', () => { page--; render(); });
    byId('soNext').addEventListener('click', () => { page++; render(); });
    function setBusy(value) {
        busy = value;
        [editor, statusDialog].forEach(dialog => {
            dialog.setAttribute('aria-busy', String(value));
            dialog.querySelectorAll('button, input, select').forEach(control => control.disabled = value);
        });
        if (!value) byId('soOwner').disabled = editingId !== null;
    }
    async function post(path, body) {
        const response = await fetch(config.endpoint + path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) });
        const data = await response.json().catch(() => null);
        if (!response.ok || !data?.ok) throw new Error(data?.error || 'Unable to save your changes. Please try again.');
        return data;
    }
    function updateComponentVisibility() {
        byId('soLectureComponent').hidden = !byId('soHasLecture').checked;
        byId('soLabComponent').hidden = !byId('soHasLab').checked;
    }
    function updateComputedPreview() {
        const hasLecture = byId('soHasLecture').checked, hasLab = byId('soHasLab').checked;
        const lectureUnits = Number(byId('soLectureUnits').value) || 0;
        const labUnits = Number(byId('soLabUnits').value) || 0;
        const lectureHours = hasLecture ? roundHalf(lectureUnits * config.academicRates.lecture) : 0;
        const labHours = hasLab ? roundHalf(labUnits * config.academicRates.lab) : 0;
        byId('soLectureLength').textContent = formatHrMin(lectureHours);
        byId('soLabLength').textContent = formatHrMin(labHours);
        byId('soLectureRate').textContent = formatNum(config.academicRates.lecture);
        byId('soLabRate').textContent = formatNum(config.academicRates.lab);
        const totalUnits = (hasLecture ? lectureUnits : 0) + (hasLab ? labUnits : 0);
        byId('soTotalUnits').textContent = formatNum(totalUnits);
        byId('soTotalHours').textContent = formatHrMin(lectureHours + labHours);
    }
    function guardAtLeastOne(evt) {
        if (!byId('soHasLecture').checked && !byId('soHasLab').checked) evt.target.checked = true;
        updateComponentVisibility();
        updateComputedPreview();
        window.UnsavedChanges?.markDirty();
    }
    byId('soHasLecture').addEventListener('change', guardAtLeastOne);
    byId('soHasLab').addEventListener('change', guardAtLeastOne);
    [byId('soLectureUnits'), byId('soLabUnits')].forEach(input => input.addEventListener('input', updateComputedPreview));
    function openEditor(row = null) {
        editingId = row?.id ?? null;
        form.reset(); byId('soFormError').hidden = true;
        const isEdit = !!row;
        byId('soEditorTitle').textContent = isEdit ? 'Edit subject' : 'Add a subject';
        byId('soEditorDescription').textContent = isEdit ? 'Keep your department’s subject details up to date.' : 'Create a subject for your department’s shared catalog.';
        byId('soSave').textContent = isEdit ? 'Save changes' : 'Add subject';
        byId('soCode').value = row ? row.code : '';
        byId('soCode').readOnly = isEdit;
        byId('soCodeHelp').textContent = isEdit ? 'The subject code is a permanent catalog identifier.' : 'Use a unique code, 2–20 characters. Letters are capitalized automatically.';
        byId('soTitle').value = row?.title ?? '';
        byId('soHasLecture').checked = isEdit ? !!row.lecture : true;
        byId('soHasLab').checked = isEdit ? !!row.lab : false;
        byId('soLectureUnits').value = row?.lecture?.units ?? 3;
        byId('soLabUnits').value = row?.lab?.units ?? 1;
        if (row) byId('soOwner').value = String(row.department);
        byId('soOwner').disabled = !!row;
        byId('soEditorHint').lastChild.textContent = isEdit ? ' A component that’s on a live schedule can’t change its meeting length, or be removed, until those classes are taken off the schedule.' : ' Already in the catalog? Reuse it through the Curriculum Builder.';
        updateComponentVisibility();
        updateComputedPreview();
        editor.showModal(); (isEdit ? byId('soTitle') : byId('soCode')).focus();
    }
    document.querySelectorAll('[data-open-add]').forEach(button => button.addEventListener('click', () => openEditor()));
    document.querySelectorAll('[data-close-editor]').forEach(button => button.addEventListener('click', () => { if (!busy) editor.close(); }));
    form.addEventListener('input', () => window.UnsavedChanges?.markDirty());
    editor.addEventListener('close', () => window.UnsavedChanges?.markClean());
    [editor, statusDialog].forEach(dialog => dialog.addEventListener('cancel', evt => { if (busy) evt.preventDefault(); }));
    form.addEventListener('submit', async evt => {
        evt.preventDefault(); if (busy) return;
        const hasLecture = byId('soHasLecture').checked, hasLab = byId('soHasLab').checked;
        const body = {
            code: byId('soCode').value.trim().toUpperCase(), title: byId('soTitle').value.trim(),
            has_lecture: hasLecture, lecture_units: Number(byId('soLectureUnits').value),
            has_lab: hasLab, lab_units: Number(byId('soLabUnits').value),
            sub_department_id: Number(byId('soOwner').value),
        };
        if (!body.title || (!editingId && !/^[A-Z0-9][A-Z0-9 ._-]{1,19}$/.test(body.code))) {
            errorIn('soFormError', !body.title ? 'Enter a subject title.' : 'Use a code of 2–20 letters or numbers. Spaces, periods, underscores, and hyphens are allowed.'); return;
        }
        if (!hasLecture && !hasLab) { errorIn('soFormError', 'Select at least one class type.'); return; }
        byId('soFormError').hidden = true; setBusy(true); byId('soSave').textContent = 'Saving…';
        const wasEditing = editingId !== null;
        try {
            const data = await post(wasEditing ? `/${editingId}/update` : '/store', body);
            const savedRowIds = new Set((data.rows || []).map(r => r.id));
            subjects = subjects.filter(s => !savedRowIds.has(s.id)).concat(data.rows || []);
            if (!wasEditing) { resetFilters(); search.value = body.code; }
            setBusy(false); editor.close(); render(); renderCourses();
            feedback(`${body.code} ${wasEditing ? 'updated' : 'added to your catalog'}.`);
            const focusId = (data.rows || []).find(r => r.active)?.id ?? editingId;
            focusRow(focusId);
            // Search is narrowed to the new code right above, so this is
            // reliably just the subject that was added — give it a beat of
            // highlight instead of letting it appear in the list cold.
            if (!wasEditing) byId('soRows').querySelector('tr')?.classList.add('so-row-added');
        } catch (error) { errorIn('soFormError', error.message); }
        finally { setBusy(false); byId('soSave').textContent = wasEditing ? 'Save changes' : 'Add subject'; }
    });
    function focusRow(id) {
        const button = byId('soRows').querySelector(`tr[data-id="${id}"] [data-action="edit"]`);
        (button || search).focus({ preventScroll: true });
    }
    byId('soRows').addEventListener('click', evt => {
        const button = evt.target.closest('button[data-action]'); if (!button || busy) return;
        const row = displayRows.find(r => r.id === Number(button.closest('tr').dataset.id));
        if (!row) return;
        if (button.dataset.action === 'edit') { openEditor(row); return; }
        statusId = row.id; byId('soStatusError').hidden = true;
        const mixed = row.lecture && row.lab;
        byId('soStatusTitle').textContent = row.active ? 'Deactivate subject?' : 'Activate subject?';
        byId('soStatusSubject').textContent = `${row.code}${row.secondaryCode ? ' + ' + row.secondaryCode : ''} · ${row.title}`;
        byId('soStatusDescription').textContent = row.active
            ? `This subject${mixed ? ' (both its lecture and laboratory components)' : ''} will no longer be available for new curriculum assignments. It cannot be deactivated while it is on a live schedule.`
            : `This subject${mixed ? ' (both its lecture and laboratory components)' : ''} will be available again for new curriculum assignments across programs.`;
        byId('soConfirmStatus').textContent = row.active ? 'Deactivate subject' : 'Activate subject';
        byId('soConfirmStatus').className = 'so-button ' + (row.active ? 'so-button--danger' : 'so-button--primary');
        statusDialog.showModal(); byId('soCancelStatus').focus();
    });
    [byId('soCloseStatus'), byId('soCancelStatus')].forEach(button => button.addEventListener('click', () => { if (!busy) statusDialog.close(); }));
    byId('soConfirmStatus').addEventListener('click', async () => {
        if (busy) return;
        const row = displayRows.find(r => r.id === statusId); if (!row) return;
        const label = byId('soConfirmStatus').textContent;
        byId('soStatusError').hidden = true; setBusy(true); byId('soConfirmStatus').textContent = 'Updating…';
        try {
            await post(`/${row.id}/toggle-status`, {});
            [row.lecture, row.lab].filter(Boolean).forEach(component => {
                const subject = subjects.find(s => s.id === component.id);
                if (subject) subject.active = !subject.active;
            });
            setBusy(false); statusDialog.close(); render(); renderCourses();
            feedback(`${row.code}${row.secondaryCode ? ' + ' + row.secondaryCode : ''} ${row.active ? 'deactivated' : 'activated'}.`); focusRow(row.id);
        } catch (error) { errorIn('soStatusError', error.message); }
        finally { setBusy(false); byId('soConfirmStatus').textContent = label; }
    });
    document.addEventListener('keydown', evt => {
        if (evt.key === '/' && !evt.ctrlKey && !evt.metaKey && !evt.altKey && !editor.open && !statusDialog.open && !evt.target.closest('input, select, textarea, [contenteditable="true"]')) { evt.preventDefault(); search.focus(); }
    });
    render();
    renderCourses();
})();

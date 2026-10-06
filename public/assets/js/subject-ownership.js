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
        byId('soAllTab').textContent = subjects.length;
        byId('soCatalogCount').textContent = subjects.length;
        const query = search.value.trim().toLocaleLowerCase();
        const filtered = subjects.filter(s =>
            (!query || `${s.code} ${s.title}`.toLocaleLowerCase().includes(query)) &&
            (status === 'all' || s.active === (status === 'active')) &&
            (type.value === 'all' || s.is_lab === (type.value === 'lab')) &&
            (!department || department.value === 'all' || s.department === Number(department.value))
        ).sort((a, b) => collator.compare(a[byId('soSort').value], b[byId('soSort').value]) || a.id - b.id);
        const pages = Math.max(1, Math.ceil(filtered.length / pageSize));
        page = Math.min(page, pages);
        const start = (page - 1) * pageSize;
        byId('soRows').innerHTML = filtered.slice(start, start + pageSize).map(s => `<tr data-id="${s.id}" class="${s.active ? '' : 'is-inactive'}">
            <td><div class="so-subject"><span class="so-subject-icon material-symbols-outlined" aria-hidden="true">${s.is_lab ? 'science' : 'menu_book'}</span><div><strong class="so-subject-code">${escape(s.code)}</strong><span class="so-subject-title">${escape(s.title)}</span>${Object.keys(config.departments).length > 1 ? `<small class="so-subject-dept">${escape(config.departments[s.department] || 'Department')}</small>` : ''}</div></div></td>
            <td class="so-units">${s.units}</td><td><span class="so-type ${s.is_lab ? 'so-type--lab' : ''}">${s.is_lab ? 'Laboratory' : 'Lecture'}</span></td><td class="so-duration">${s.duration} ${s.duration === 1 ? 'hour' : 'hours'}</td>
            <td><span class="so-status ${s.active ? 'so-status--active' : ''}"><i class="so-dot"></i>${s.active ? 'Active' : 'Inactive'}</span></td>
            <td><div class="so-row-actions"><button type="button" class="so-edit" data-action="edit" aria-label="Edit ${escape(s.code)}"><span class="material-symbols-outlined" aria-hidden="true">edit</span>Edit</button><button type="button" class="so-icon-button so-toggle" data-action="status" title="${s.active ? 'Deactivate' : 'Activate'} ${escape(s.code)}" aria-label="${s.active ? 'Deactivate' : 'Activate'} ${escape(s.code)}"><span class="material-symbols-outlined" aria-hidden="true">${s.active ? 'pause_circle' : 'play_circle'}</span></button></div></td></tr>`).join('');
        byId('soTableWrap').hidden = !filtered.length;
        byId('soEmpty').hidden = !!filtered.length;
        byId('soEmptyTitle').textContent = subjects.length ? 'No matching subjects' : 'Your catalog starts here';
        byId('soEmptyText').textContent = subjects.length ? 'Try a different search or adjust your filters.' : 'Add your first subject to make it available to programs across departments.';
        byId('soEmpty').querySelector('.material-symbols-outlined').textContent = subjects.length ? 'search_off' : 'library_books';
        byId('soClearFilters').hidden = !subjects.length;
        byId('soEmptyAdd').hidden = !!subjects.length;
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
    function openEditor(subject = null) {
        editingId = subject?.id ?? null;
        form.reset(); byId('soFormError').hidden = true;
        byId('soEditorTitle').textContent = subject ? 'Edit subject' : 'Add a subject';
        byId('soEditorDescription').textContent = subject ? 'Keep your department’s subject details up to date.' : 'Create a subject for your department’s shared catalog.';
        byId('soSave').textContent = subject ? 'Save changes' : 'Add subject';
        byId('soCode').value = subject?.code ?? ''; byId('soCode').readOnly = !!subject;
        byId('soCodeHelp').textContent = subject ? 'The subject code is a permanent catalog identifier.' : 'Use a unique code, 2–20 characters. Letters are capitalized automatically.';
        byId('soTitle').value = subject?.title ?? '';
        byId('soUnits').value = subject?.units ?? 3;
        // Retain an existing legacy duration rather than silently changing it.
        byId('soDuration').querySelectorAll('[data-legacy]').forEach(option => option.remove());
        if (subject && ![...byId('soDuration').options].some(option => Number(option.value) === subject.duration)) {
            const option = new Option(`${subject.duration} hours (current)`, String(subject.duration)); option.dataset.legacy = 'true'; byId('soDuration').add(option);
        }
        byId('soDuration').value = String(subject?.duration ?? 1.5);
        form.querySelector(`[name="class_type"][value="${subject?.is_lab ? 'lab' : 'lecture'}"]`).checked = true;
        if (subject) byId('soOwner').value = String(subject.department);
        byId('soOwner').disabled = !!subject;
        byId('soEditorHint').lastChild.textContent = subject ? ' Subjects on live schedules must keep their current meeting length and class type.' : ' Already in the catalog? Reuse it through the Curriculum Builder.';
        editor.showModal(); (subject ? byId('soTitle') : byId('soCode')).focus();
    }
    document.querySelectorAll('[data-open-add]').forEach(button => button.addEventListener('click', () => openEditor()));
    document.querySelectorAll('[data-close-editor]').forEach(button => button.addEventListener('click', () => { if (!busy) editor.close(); }));
    form.addEventListener('input', () => window.UnsavedChanges?.markDirty());
    editor.addEventListener('close', () => window.UnsavedChanges?.markClean());
    [editor, statusDialog].forEach(dialog => dialog.addEventListener('cancel', evt => { if (busy) evt.preventDefault(); }));
    form.addEventListener('submit', async evt => {
        evt.preventDefault(); if (busy) return;
        const body = {
            code: byId('soCode').value.trim().toUpperCase(), title: byId('soTitle').value.trim(),
            units: Number(byId('soUnits').value), duration: Number(byId('soDuration').value),
            is_lab: form.querySelector('[name="class_type"]:checked').value === 'lab',
            sub_department_id: Number(byId('soOwner').value),
        };
        if (!body.title || (!editingId && !/^[A-Z0-9][A-Z0-9 ._-]{1,19}$/.test(body.code))) {
            errorIn('soFormError', !body.title ? 'Enter a subject title.' : 'Use a code of 2–20 letters or numbers. Spaces, periods, underscores, and hyphens are allowed.'); return;
        }
        byId('soFormError').hidden = true; setBusy(true); byId('soSave').textContent = 'Saving…';
        const wasEditing = editingId !== null;
        try {
            const data = await post(wasEditing ? `/${editingId}/update` : '/store', body);
            if (wasEditing) {
                const subject = subjects.find(s => s.id === editingId);
                Object.assign(subject, { title: body.title, units: body.units, duration: body.duration, is_lab: body.is_lab });
            } else {
                const subject = { id: Number(data.subject_id), code: body.code, title: body.title, units: body.units, duration: body.duration, is_lab: body.is_lab, active: true, department: body.sub_department_id };
                subjects = subjects.filter(s => s.id !== subject.id); subjects.push(subject);
                resetFilters(); search.value = body.code;
            }
            setBusy(false); editor.close(); render(); renderCourses();
            feedback(`${body.code} ${wasEditing ? 'updated' : 'added to your catalog'}.`);
            focusRow(wasEditing ? editingId : Number(data.subject_id));
        } catch (error) { errorIn('soFormError', error.message); }
        finally { setBusy(false); byId('soSave').textContent = wasEditing ? 'Save changes' : 'Add subject'; }
    });
    function focusRow(id) {
        const button = byId('soRows').querySelector(`tr[data-id="${id}"] [data-action="edit"]`);
        (button || search).focus({ preventScroll: true });
    }
    byId('soRows').addEventListener('click', evt => {
        const button = evt.target.closest('button[data-action]'); if (!button || busy) return;
        const subject = subjects.find(s => s.id === Number(button.closest('tr').dataset.id));
        if (!subject) return;
        if (button.dataset.action === 'edit') { openEditor(subject); return; }
        statusId = subject.id; byId('soStatusError').hidden = true;
        byId('soStatusTitle').textContent = subject.active ? 'Deactivate subject?' : 'Activate subject?';
        byId('soStatusSubject').textContent = `${subject.code} · ${subject.title}`;
        byId('soStatusDescription').textContent = subject.active ? 'This subject will no longer be available for new curriculum assignments. It cannot be deactivated while it is on a live schedule.' : 'This subject will be available again for new curriculum assignments across programs.';
        byId('soConfirmStatus').textContent = subject.active ? 'Deactivate subject' : 'Activate subject';
        byId('soConfirmStatus').className = 'so-button ' + (subject.active ? 'so-button--danger' : 'so-button--primary');
        statusDialog.showModal(); byId('soCancelStatus').focus();
    });
    [byId('soCloseStatus'), byId('soCancelStatus')].forEach(button => button.addEventListener('click', () => { if (!busy) statusDialog.close(); }));
    byId('soConfirmStatus').addEventListener('click', async () => {
        if (busy) return;
        const subject = subjects.find(s => s.id === statusId); if (!subject) return;
        const label = byId('soConfirmStatus').textContent;
        byId('soStatusError').hidden = true; setBusy(true); byId('soConfirmStatus').textContent = 'Updating…';
        try {
            await post(`/${subject.id}/toggle-status`, {});
            subject.active = !subject.active; setBusy(false); statusDialog.close(); render(); renderCourses();
            feedback(`${subject.code} ${subject.active ? 'activated' : 'deactivated'}.`); focusRow(subject.id);
        } catch (error) { errorIn('soStatusError', error.message); }
        finally { setBusy(false); byId('soConfirmStatus').textContent = label; }
    });
    document.addEventListener('keydown', evt => {
        if (evt.key === '/' && !evt.ctrlKey && !evt.metaKey && !evt.altKey && !editor.open && !statusDialog.open && !evt.target.closest('input, select, textarea, [contenteditable="true"]')) { evt.preventDefault(); search.focus(); }
    });
    render();
    renderCourses();
})();

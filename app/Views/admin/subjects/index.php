<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('headerActions') ?>
<a href="<?= site_url('admin/subjects/create') ?>" class="ac-btn ac-btn-primary">
    <span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">add</span>
    <span>Add Subject</span>
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Subjects</h2>
        <p class="ac-page-header__sub">The subject catalog — units, contact hours, lecture/laboratory type, and owning department used when building curricula. Changes save automatically.</p>
    </div>
</div>

<div id="subjectsFeedback" class="ac-notice" style="margin-bottom:16px;" hidden>
    <span class="material-symbols-outlined" id="subjectsFeedbackIcon" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
    <span class="ac-notice__text" id="subjectsFeedbackText"></span>
</div>

<?php if (empty($subjects)): ?>
    <div class="ac-empty-block">
        <p>No subjects yet.</p>
        <p style="margin-top:6px;"><a href="<?= site_url('admin/subjects/create') ?>">Add the first subject</a> to start building curricula.</p>
    </div>
<?php else: ?>
    <div class="ac-field" style="max-width:360px; margin-bottom:14px;">
        <input type="search" id="subjectSearch" class="ac-input" placeholder="Search by code or name…" aria-label="Search subjects" autocomplete="off">
    </div>

    <div class="ac-table-card">
        <div class="ac-bulk-bar" id="bulkBar" hidden>
            <span id="bulkCount">0 selected</span>
            <button type="button" id="bulkDeleteBtn" class="ac-btn-outline ac-btn-outline--danger">Delete selected</button>
        </div>
        <table class="ac-table" id="subjectsTable">
            <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="selectAll" aria-label="Select all"></th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Units</th>
                    <th>Type</th>
                    <th>Weekly Hours</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $subject): ?>
                    <tr data-row-id="<?= (int) $subject['subject_id'] ?>" data-search="<?= esc(strtolower($subject['subject_code'] . ' ' . $subject['subject_name']), 'attr') ?>">
                        <td><input type="checkbox" class="row-check" value="<?= (int) $subject['subject_id'] ?>" aria-label="Select <?= esc($subject['subject_code'], 'attr') ?>"></td>
                        <td><span class="ac-cell-bold"><?= esc($subject['subject_code']) ?></span></td>
                        <td><?= esc($subject['subject_name']) ?></td>
                        <td><?= esc($subject['units']) ?></td>
                        <td class="ac-muted"><?= $subject['is_laboratory'] ? 'Laboratory' : 'Lecture' ?></td>
                        <td class="ac-muted"><?= esc($subject['weekly_hours']) ?></td>
                        <td>
                            <select class="ac-owner-select" data-subject-id="<?= (int) $subject['subject_id'] ?>" aria-label="Owner for <?= esc($subject['subject_code'], 'attr') ?>">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($subDepartments as $deptId => $deptName): ?>
                                    <option value="<?= (int) $deptId ?>" <?= (int) ($subject['sub_department_id'] ?? 0) === (int) $deptId ? 'selected' : '' ?>><?= esc($deptName) ?></option>
                                <?php endforeach ?>
                            </select>
                        </td>
                        <td>
                            <span class="ac-status-dot status-toggle <?= $subject['is_active'] ? 'ac-status-dot--active' : 'ac-status-dot--inactive' ?>" data-subject-id="<?= (int) $subject['subject_id'] ?>" data-active="<?= $subject['is_active'] ? '1' : '0' ?>" role="button" tabindex="0" title="Click to <?= $subject['is_active'] ? 'deactivate' : 'activate' ?>">
                                <?= $subject['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="ac-actions-cell">
                            <a href="<?= site_url('admin/subjects/edit/' . $subject['subject_id']) ?>" class="ac-btn-outline">Edit</a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        <p class="ac-empty-inline" id="noSearchResults" hidden style="padding:24px; text-align:center; color:var(--color-text-secondary);">No subjects match your search.</p>
    </div>
<?php endif ?>

<style>
    .ac-bulk-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 24px; background: var(--color-primary-soft, #eef2ff); border-bottom: 1px solid var(--color-border); font-size: 12.5px; font-weight: 650; color: var(--color-text-primary); }
    .ac-btn-outline--danger { color: var(--color-danger); border-color: var(--color-danger-border, var(--color-danger)); }
    .ac-btn-outline--danger:hover { background: var(--color-danger-soft); }
    .ac-owner-select { font-size: 12px; padding: 6px 8px; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text-primary); max-width: 180px; }
    .status-toggle { cursor: pointer; }
    .ac-notice.is-error { background: var(--color-danger-soft); border-color: var(--color-danger-border); }
    .ac-notice.is-error .ac-notice__text, .ac-notice.is-error .material-symbols-outlined { color: var(--color-danger) !important; }
</style>

<script>
(() => {
    const search = document.getElementById('subjectSearch');
    const rows = () => Array.from(document.querySelectorAll('#subjectsTable tbody tr'));
    const noResults = document.getElementById('noSearchResults');

    function showFeedback(message, isError = false) {
        const el = document.getElementById('subjectsFeedback');
        document.getElementById('subjectsFeedbackText').textContent = message;
        document.getElementById('subjectsFeedbackIcon').textContent = isError ? 'error' : 'check_circle';
        el.classList.toggle('is-error', isError);
        el.hidden = false;
    }

    search?.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        let visible = 0;
        rows().forEach(row => {
            const match = !q || row.dataset.search.includes(q);
            row.hidden = !match;
            if (match) visible++;
        });
        if (noResults) noResults.hidden = visible > 0;
    });

    const selectAll = document.getElementById('selectAll');
    const bar = document.getElementById('bulkBar');
    const count = document.getElementById('bulkCount');
    const rowChecks = () => Array.from(document.querySelectorAll('.row-check'));

    function refreshSelection() {
        const checked = rowChecks().filter(cb => cb.checked);
        bar.hidden = checked.length === 0;
        count.textContent = checked.length + ' selected';
        if (selectAll) selectAll.checked = checked.length > 0 && checked.length === rowChecks().length;
    }

    selectAll?.addEventListener('change', () => {
        rowChecks().forEach(cb => cb.checked = selectAll.checked);
        refreshSelection();
    });

    rowChecks().forEach(cb => cb.addEventListener('change', refreshSelection));

    // Owner reassignment: saves the moment a new value is picked, no separate save step.
    document.querySelectorAll('.ac-owner-select').forEach(select => {
        let previous = select.value;
        select.addEventListener('change', async () => {
            select.disabled = true;
            try {
                const res = await fetch('<?= site_url('admin/subjects/update-owner') ?>/' + select.dataset.subjectId, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ sub_department_id: select.value }),
                });
                if (!res.ok) throw new Error();
                previous = select.value;
                showFeedback('Owner updated.');
            } catch (e) {
                select.value = previous;
                showFeedback('Could not update the owner. Please try again.', true);
            } finally {
                select.disabled = false;
            }
        });
    });

    // Active/Inactive: click the status pill, saves immediately.
    document.querySelectorAll('.status-toggle').forEach(pill => {
        async function toggle() {
            pill.style.pointerEvents = 'none';
            try {
                const res = await fetch('<?= site_url('admin/subjects/toggle-status') ?>/' + pill.dataset.subjectId, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json().catch(() => null);
                if (!res.ok || !data?.ok) throw new Error();
                pill.dataset.active = data.is_active ? '1' : '0';
                pill.textContent = data.is_active ? 'Active' : 'Inactive';
                pill.classList.toggle('ac-status-dot--active', data.is_active);
                pill.classList.toggle('ac-status-dot--inactive', !data.is_active);
                pill.title = 'Click to ' + (data.is_active ? 'deactivate' : 'activate');
                showFeedback(data.is_active ? 'Subject activated.' : 'Subject deactivated.');
            } catch (e) {
                showFeedback('Could not update the status. Please try again.', true);
            } finally {
                pill.style.pointerEvents = '';
            }
        }
        pill.addEventListener('click', toggle);
        pill.addEventListener('keydown', evt => { if (evt.key === 'Enter' || evt.key === ' ') { evt.preventDefault(); toggle(); } });
    });

    // Bulk delete: the one action that asks first.
    document.getElementById('bulkDeleteBtn')?.addEventListener('click', async () => {
        const checked = rowChecks().filter(cb => cb.checked);
        if (!checked.length) return;

        const confirmed = window.acConfirm
            ? await window.acConfirm(`Permanently delete ${checked.length} selected subject(s)? Any still in use (plotted, curriculum-assigned, or enrolled) are skipped automatically.`)
            : confirm('Permanently delete the selected subjects?');
        if (!confirmed) return;

        const body = new URLSearchParams();
        checked.forEach(cb => body.append('ids[]', cb.value));

        try {
            const res = await fetch('<?= site_url('admin/subjects/bulk-delete') ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data?.ok) throw new Error();

            data.deleted.forEach(id => document.querySelector(`tr[data-row-id="${id}"]`)?.remove());
            refreshSelection();

            let message = data.deleted.length ? `${data.deleted.length} subject(s) deleted.` : '';
            if (data.skipped.length) {
                message += (message ? ' ' : '') + `${data.skipped.length} skipped (still in use): ${data.skipped.map(s => s.code).join(', ')}.`;
            }
            showFeedback(message || 'Nothing was deleted.', data.deleted.length === 0);
        } catch (e) {
            showFeedback('Could not delete the selected subjects. Please try again.', true);
        }
    });
})();
</script>

<?= $this->endSection() ?>

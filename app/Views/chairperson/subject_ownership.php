<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'subject-ownership';
$pageTitle     = 'Subject Ownership';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Subject Ownership</h2>
        <p class="ac-page-header__sub">Which subjects your department owns. These are the subjects the Curriculum Builder can draw from — yours and every other department's — never duplicated per program.</p>
    </div>
</div>

<?php if (! $hasAnyDept): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>You're not assigned to a department yet. Contact an administrator.</p>
        </div>
    </div>
<?php else: ?>

    <div id="feedback" class="flash" style="display:none;"></div>

    <div class="panel" style="padding:20px 24px; margin-bottom:20px;">
        <h3 style="margin:0 0 4px; font-size:15px;">Add a Subject</h3>
        <p style="font-size:12.5px; color:var(--color-text-secondary); margin:0 0 14px;">Creates a subject owned by your department. If the code already exists elsewhere, use the Curriculum Builder's search to reuse it instead.</p>

        <form id="addForm" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <?php if (count($deptNames) > 1): ?>
                <div>
                    <label style="display:block; font-size:12px; margin-bottom:4px;">Department</label>
                    <select name="sub_department_id" id="addSubDept" style="min-width:160px;">
                        <?php foreach ($deptNames as $id => $name): ?>
                            <option value="<?= (int) $id ?>"><?= esc($name) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            <?php endif ?>
            <div>
                <label style="display:block; font-size:12px; margin-bottom:4px;">Code</label>
                <input type="text" id="addCode" placeholder="e.g. ITE 101" style="width:140px;">
            </div>
            <div style="flex:1; min-width:200px;">
                <label style="display:block; font-size:12px; margin-bottom:4px;">Title</label>
                <input type="text" id="addTitle" placeholder="Subject title" style="width:100%;">
            </div>
            <div>
                <label style="display:block; font-size:12px; margin-bottom:4px;">Units</label>
                <input type="number" id="addUnits" min="0.5" max="6" step="0.5" value="3" style="width:80px;">
            </div>
            <div>
                <label style="display:block; font-size:12px; margin-bottom:4px;">Meeting length</label>
                <select id="addDuration" style="width:120px;">
                    <?php foreach ($durations as $d): ?>
                        <option value="<?= $d ?>" <?= $d === 1.5 ? 'selected' : '' ?>><?= rtrim(rtrim(number_format($d, 1), '0'), '.') ?> hrs</option>
                    <?php endforeach ?>
                </select>
            </div>
            <label style="display:flex; align-items:center; gap:6px;">
                <input type="checkbox" id="addLab"> Laboratory
            </label>
            <button type="submit" class="btn btn-primary">+ Add Subject</button>
        </form>
    </div>

    <div class="panel mc-card">
        <div class="mc-card__head">
            <div class="mc-card__title-row">
                <h2>Owned Subjects</h2>
                <span class="mc-count"><?= count($subjects) ?></span>
            </div>
            <p>Deactivating a subject hides it from new curriculum assignments — it's blocked outright while plotted on any live schedule.</p>
        </div>

        <?php if (empty($subjects)): ?>
            <div class="empty-state">
                <div class="material-symbols-outlined">menu_book</div>
                <p>Your department doesn't own any subjects yet. Add one above.</p>
            </div>
        <?php else: ?>
            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="text-align:left; font-size:11px; text-transform:uppercase; color:var(--color-text-tertiary); border-bottom:1px solid var(--color-border);">
                        <th style="padding:10px 24px;">Code</th>
                        <th style="padding:10px;">Title</th>
                        <th style="padding:10px;">Units</th>
                        <th style="padding:10px;">Type</th>
                        <th style="padding:10px;">Status</th>
                        <th style="padding:10px 24px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="subjectRows">
                    <?php foreach ($subjects as $s): ?>
                        <tr data-id="<?= (int) $s['subject_id'] ?>" data-duration="<?= (float) $s['contact_hours'] ?>" style="border-bottom:1px solid var(--color-border);">
                            <td style="padding:10px 24px; font-weight:700;"><?= esc($s['subject_code']) ?></td>
                            <td style="padding:10px;" class="s-title"><?= esc($s['subject_name']) ?></td>
                            <td style="padding:10px;" class="s-units"><?= (float) $s['units'] ?></td>
                            <td style="padding:10px;"><span class="s-lab"><?= $s['is_laboratory'] ? 'Laboratory' : 'Lecture' ?></span></td>
                            <td style="padding:10px;">
                                <span class="ac-status-dot <?= $s['is_active'] ? 'ac-status-dot--active' : 'ac-status-dot--inactive' ?>">
                                    <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="padding:10px 24px; text-align:right; white-space:nowrap;">
                                <button type="button" class="ac-btn-outline" data-act="edit">Edit</button>
                                <button type="button" class="ac-btn-outline" data-act="toggle"><?= $s['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>

<?php endif ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function showFeedback(message, isError) {
        const el = document.getElementById('feedback');
        if (!el) return;
        el.style.display = 'block';
        el.className = 'flash ' + (isError ? 'error' : 'success');
        el.textContent = message;
    }

    async function postJSON(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        return res.json();
    }

    document.getElementById('addForm')?.addEventListener('submit', async (evt) => {
        evt.preventDefault();

        const deptSelect = document.getElementById('addSubDept');
        const body = {
            sub_department_id: deptSelect ? parseInt(deptSelect.value, 10) : undefined,
            code: document.getElementById('addCode').value.trim().toUpperCase(),
            title: document.getElementById('addTitle').value.trim(),
            units: parseFloat(document.getElementById('addUnits').value),
            duration: parseFloat(document.getElementById('addDuration').value),
            is_lab: document.getElementById('addLab').checked,
        };

        if (!body.code || !body.title) {
            showFeedback('Code and title are required.', true);
            return;
        }

        const result = await postJSON('<?= site_url('chairperson/subject-ownership/store') ?>', body);

        if (!result.ok) {
            showFeedback(result.error || 'Could not add the subject.', true);
            return;
        }

        showFeedback(`${body.code} added.`, false);
        setTimeout(() => location.reload(), 600);
    });

    document.getElementById('subjectRows')?.addEventListener('click', async (evt) => {
        const btn = evt.target.closest('button[data-act]');
        if (!btn) return;
        const row = btn.closest('tr');
        const id = row.dataset.id;

        if (btn.dataset.act === 'toggle') {
            const result = await postJSON('<?= site_url('chairperson/subject-ownership') ?>/' + id + '/toggle-status', {});
            if (!result.ok) { showFeedback(result.error || 'Could not update status.', true); return; }
            location.reload();
            return;
        }

        if (btn.dataset.act === 'edit') {
            const title = row.querySelector('.s-title').textContent;
            const units = row.querySelector('.s-units').textContent;
            const isLab = row.querySelector('.s-lab').textContent.trim() === 'Laboratory';

            const newTitle = prompt('Title', title);
            if (newTitle === null) return;
            const newUnits = prompt('Units', units);
            if (newUnits === null) return;

            const result = await postJSON('<?= site_url('chairperson/subject-ownership') ?>/' + id + '/update', {
                title: newTitle.trim(), units: parseFloat(newUnits), duration: parseFloat(row.dataset.duration), is_lab: isLab,
            });

            if (!result.ok) { showFeedback(result.error || 'Could not save.', true); return; }
            location.reload();
        }
    });
</script>
<?= $this->endSection() ?>

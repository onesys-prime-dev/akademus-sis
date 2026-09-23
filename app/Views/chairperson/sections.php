<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'sections';
$pageTitle     = 'Sections';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = [
    '' => [
        'overview'        => ['label' => 'Dashboard',        'icon' => 'home',           'url' => 'chairperson/overview'],
        'profile'         => ['label' => 'Profile',           'icon' => 'person',          'url' => 'chairperson/profile'],
        'schedule'        => ['label' => 'Schedule Plotting', 'icon' => 'calendar_month',  'url' => 'chairperson/dashboard'],
        'sections'        => ['label' => 'Sections',          'icon' => 'groups',          'url' => 'chairperson/sections'],
        'offerings'       => ['label' => 'Merged Classes',    'icon' => 'merge_type',      'url' => 'chairperson/offerings'],
        'grade-approvals' => ['label' => 'Grade Approvals',   'icon' => 'grade',           'url' => 'chairperson/grade-approvals'],
        'curriculum'      => ['label' => 'Curriculum',        'icon' => 'menu_book',       'url' => 'chairperson/curriculum'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    .section-card { margin-bottom: 14px; }
    .section-card__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .section-card__title { font-size: 14.5px; font-weight: 700; color: var(--color-text-primary); }
    .section-card__meta { font-size: 12px; color: var(--color-text-secondary); }
    .roster-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--color-border); }
    .roster-row:last-child { border-bottom: none; }
    .roster-row__name { font-size: 13.5px; font-weight: 600; color: var(--color-text-primary); }
    .roster-empty { font-size: 12.5px; color: var(--color-text-secondary); padding: 8px 0; }
    select.ac-inline-select { padding: 7px 10px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-family: inherit; font-size: 12.5px; background: var(--color-surface-sunken); }
    .icon-btn { border: none; background: none; cursor: pointer; color: var(--color-text-secondary); padding: 4px; border-radius: var(--radius-sm); display: flex; }
    .icon-btn:hover { background: var(--color-danger-soft); color: var(--color-danger); }
    select.ac-input { width: 100%; padding: 10px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface-sunken); font-family: inherit; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Sections</h2></div>
</div>

<?php if ($deptName === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>You're not assigned to a department yet. Contact an administrator.</p>
        </div>
    </div>
<?php else: ?>

    <div class="panel" id="feedback" style="display:none;"></div>

    <div class="panel">
        <h2>Create a Section</h2>
        <p class="panel-sub">Sections created here are ready for student assignment right away — no schedule needs to be plotted first.</p>

        <div class="field-row">
            <div class="field">
                <label>Program</label>
                <select id="newProgram" class="ac-input">
                    <?php foreach ($programs as $p): ?>
                        <option value="<?= esc($p) ?>"><?= esc($p) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="field">
                <label>Year Level</label>
                <select id="newYearLevel" class="ac-input">
                    <?php foreach ($yearLevels as $yl): ?>
                        <option value="<?= esc($yl) ?>"><?= esc($yl) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Academic Year</label>
                <select id="newAcademicYear" class="ac-input">
                    <?php foreach ($academicYears as $ay): ?>
                        <option value="<?= esc($ay) ?>"><?= esc($ay) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="field">
                <label>Section Name</label>
                <input type="text" id="newSectionName" placeholder="e.g. 1A" maxlength="20">
            </div>
        </div>
        <div class="field">
            <label>Capacity (optional)</label>
            <input type="number" id="newCapacity" min="1" placeholder="Leave blank for unlimited" style="max-width:200px;">
        </div>

        <button type="button" class="btn btn-primary" onclick="createSection()">
            <span class="material-symbols-outlined" style="font-size:18px;">add</span>
            Create Section
        </button>
    </div>

    <?php if ($sections === []): ?>
        <div class="panel">
            <div class="empty-state">
                <div class="material-symbols-outlined">groups</div>
                <p>No sections yet. Create one above, or plot a schedule and one will appear automatically.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($sections as $sec): ?>
            <?php $roster = $rosterBySection[(int) $sec['section_id']] ?? []; ?>
            <div class="panel section-card">
                <div class="section-card__head">
                    <div>
                        <div class="section-card__title"><?= esc($sec['program']) ?> · <?= esc($sec['year_level']) ?> · Section <?= esc($sec['section']) ?></div>
                        <div class="section-card__meta">
                            <?= esc($sec['academic_year']) ?> &middot;
                            <?= count($roster) ?><?= $sec['capacity'] !== null ? ' / ' . esc($sec['capacity']) : '' ?> student<?= count($roster) === 1 ? '' : 's' ?>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <select class="ac-inline-select" id="assign-<?= esc($sec['section_id']) ?>">
                            <option value="">Add a student…</option>
                            <?php foreach ($students as $s): ?>
                                <?php if ((int) ($s['section_id'] ?? 0) === (int) $sec['section_id']) continue; ?>
                                <option value="<?= esc($s['id']) ?>"><?= esc($s['username']) ?><?= $s['section_id'] ? ' (currently assigned elsewhere)' : '' ?></option>
                            <?php endforeach ?>
                        </select>
                        <button type="button" class="btn btn-secondary" onclick="assignStudent(<?= (int) $sec['section_id'] ?>)">Add</button>
                        <button type="button" class="icon-btn" title="Delete section" onclick='deleteSection(<?= (int) $sec['section_id'] ?>, <?= json_encode($sec['program'] . ' · ' . $sec['year_level'] . ' · Section ' . $sec['section'], JSON_HEX_APOS) ?>)'>
                            <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                        </button>
                    </div>
                </div>

                <?php if ($roster === []): ?>
                    <div class="roster-empty">No students assigned yet.</div>
                <?php else: ?>
                    <?php foreach ($roster as $r): ?>
                        <div class="roster-row">
                            <div class="roster-row__name"><?= esc($r['username']) ?></div>
                            <button type="button" class="icon-btn" title="Remove from section" onclick="unassignStudent(<?= (int) $r['id'] ?>)">
                                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                            </button>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    <?php endif ?>

<?php endif ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function showFeedback(message, isError) {
        const el = document.getElementById('feedback');
        el.style.display = 'block';
        el.style.color = isError ? '#a11414' : '#056945';
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

    async function createSection() {
        const payload = {
            program: document.getElementById('newProgram').value,
            year_level: document.getElementById('newYearLevel').value,
            academic_year: document.getElementById('newAcademicYear').value,
            section: document.getElementById('newSectionName').value.trim(),
            capacity: document.getElementById('newCapacity').value,
        };

        if (!payload.section) {
            showFeedback('Enter a section name.', true);
            return;
        }

        const result = await postJSON('<?= site_url('chairperson/sections/create') ?>', payload);

        if (!result.ok) {
            showFeedback(result.error || 'Could not create the section.', true);
            return;
        }

        reloadWithMessage(`Section "${payload.section}" created.`);
    }

    async function assignStudent(sectionId) {
        const select = document.getElementById('assign-' + sectionId);
        const userId = select.value;
        const label  = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : 'Student';

        if (!userId) {
            showFeedback('Choose a student first.', true);
            return;
        }

        const result = await postJSON('<?= site_url('chairperson/sections/assign-student') ?>', {
            section_id: sectionId,
            user_id: userId,
        });

        if (!result.ok) {
            showFeedback(result.error || 'Could not assign the student.', true);
            return;
        }

        reloadWithMessage(`${label} added to the section.`);
    }

    async function deleteSection(sectionId, label) {
        if (!confirm(`Delete "${label}"? This cannot be undone.`)) {
            return;
        }

        const result = await postJSON('<?= site_url('chairperson/sections/delete') ?>', { section_id: sectionId });

        if (!result.ok) {
            showFeedback(result.error || 'Could not delete the section.', true);
            return;
        }

        reloadWithMessage(`Section "${label}" deleted.`);
    }

    async function unassignStudent(userId) {
        const result = await postJSON('<?= site_url('chairperson/sections/unassign-student') ?>', { user_id: userId });

        if (!result.ok) {
            showFeedback(result.error || 'Could not remove the student.', true);
            return;
        }

        reloadWithMessage('Student removed from the section.');
    }

    function reloadWithMessage(message) {
        sessionStorage.setItem('sectionsFeedback', message);
        location.reload();
    }

    (function () {
        const pending = sessionStorage.getItem('sectionsFeedback');
        if (pending) {
            sessionStorage.removeItem('sectionsFeedback');
            showFeedback(pending, false);
        }
    })();
</script>
<?= $this->endSection() ?>

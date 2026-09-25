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
    .icon-btn { border: none; background: none; cursor: pointer; color: var(--color-text-secondary); padding: 4px; border-radius: var(--radius-sm); display: flex; }
    .icon-btn:hover { background: var(--color-danger-soft); color: var(--color-danger); }

    /* ===== Create Section card ===== */
    .cs-card { padding: 0; }
    .cs-card__head { padding: 20px 24px; border-bottom: 1px solid var(--color-border); }
    .cs-card__title-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
    .cs-card__title-row h2 { margin: 0; font-size: 16px; font-weight: 800; color: var(--color-text-primary); }
    .cs-card__head p { margin: 6px 0 0; font-size: 12.5px; color: var(--color-text-secondary); }

    .cs-ready-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); background: var(--color-success-soft); color: #166534; flex: none; }
    .cs-ready-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--color-success); }

    .cs-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; padding: 24px; }
    .cs-field label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--color-text-secondary); margin-bottom: 6px; }
    .cs-field label .optional { text-transform: none; font-weight: 500; color: var(--color-text-tertiary); }
    .cs-field select, .cs-field input { width: 100%; padding: 10px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 13.5px; font-family: inherit; color: var(--color-text-primary); background: var(--color-surface); }
    .cs-field select:focus, .cs-field input:focus { outline: none; border-color: var(--color-primary); box-shadow: var(--focus-ring); }
    .cs-field .unit-hint { position: relative; }
    .cs-field .unit-hint input { padding-right: 52px; }
    .cs-field .unit-hint span { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); font-size: 11px; color: var(--color-text-tertiary); pointer-events: none; }

    .cs-form-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 24px 24px; border-top: 1px solid var(--color-border); flex-wrap: wrap; }
    .cs-form-footer .hint { font-size: 12px; color: var(--color-text-tertiary); }

    /* ===== Section list ===== */
    .cs-list-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: 8px 0 16px; }
    .cs-list-head h2 { margin: 0; font-size: 18px; font-weight: 800; color: var(--color-text-primary); }

    .section-card { margin-bottom: 16px; padding: 0; overflow: hidden; }
    .section-card:last-child { margin-bottom: 0; }
    .section-card__head { padding: 20px 24px; border-bottom: 1px solid var(--color-border); }
    .section-card__top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; }
    .section-card__title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .section-card__title-row h3 { margin: 0; font-size: 15.5px; font-weight: 800; color: var(--color-text-primary); }

    .cap-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-sm); white-space: nowrap; }
    .cap-badge--full { background: var(--color-success-soft); color: #166534; }
    .cap-badge--open { background: var(--color-info-soft); color: var(--color-info); }
    .cap-badge--unlimited { background: var(--color-surface-sunken); color: var(--color-text-secondary); }

    .section-card__meta { display: flex; flex-wrap: wrap; gap: 6px; font-size: 12px; color: var(--color-text-secondary); margin-top: 4px; }
    .section-card__meta .dot { color: var(--color-text-tertiary); }
    .section-card__meta strong { color: var(--color-text-primary); font-weight: 700; }

    .section-card__assign { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .section-card__assign select { min-width: 220px; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-family: inherit; font-size: 12.5px; background: var(--color-surface); color: var(--color-text-primary); }
    .section-card__assign select:focus { outline: none; border-color: var(--color-primary); box-shadow: var(--focus-ring); }

    .section-card__progress { padding: 16px 24px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; border-bottom: 1px solid var(--color-border); }
    .section-card__progress .track { flex: 1; max-width: 360px; height: 6px; border-radius: 999px; background: var(--color-surface-sunken); overflow: hidden; }
    .section-card__progress .fill { height: 100%; background: var(--color-primary); border-radius: 999px; }
    .section-card__progress .label { font-size: 12px; font-weight: 700; color: var(--color-text-secondary); white-space: nowrap; }

    table.roster-table { width: 100%; border-collapse: collapse; }
    table.roster-table th { text-align: left; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--color-text-secondary); background: var(--color-surface-secondary); padding: 10px 24px; border-bottom: 1px solid var(--color-border); }
    table.roster-table td { padding: 10px 24px; font-size: 13px; color: var(--color-text-primary); border-bottom: 1px solid var(--color-border); vertical-align: middle; }
    table.roster-table tr:last-child td { border-bottom: none; }
    table.roster-table tbody tr:hover { background: var(--color-surface-secondary); }

    .roster-student { display: flex; align-items: center; gap: 10px; }
    .roster-avatar { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 700; color: #fff; flex: none; }

    .roster-empty { font-size: 12.5px; color: var(--color-text-secondary); padding: 20px 24px; }

    @media (max-width: 640px) {
        .section-card__top { flex-direction: column; align-items: stretch; }
        .section-card__assign select { min-width: 0; flex: 1; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Sections</h2>
        <p class="ac-page-header__sub">Create sections and manage student rosters for your department.</p>
    </div>
</div>

<?php if ($deptName === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>You're not assigned to a department yet. Contact an administrator.</p>
        </div>
    </div>
<?php else: ?>

    <div id="feedback" class="flash" style="display:none;"></div>

    <div class="panel cs-card">
        <div class="cs-card__head">
            <div class="cs-card__title-row">
                <h2>Create a Section</h2>
                <span class="cs-ready-badge"><span class="cs-ready-dot"></span> Ready to Assign</span>
            </div>
            <p>Sections created here are ready for student assignment right away — no schedule needs to be plotted first.</p>
        </div>

        <div class="cs-form-grid">
            <div class="cs-field">
                <label for="newProgram">Program</label>
                <select id="newProgram">
                    <?php foreach ($programs as $p): ?>
                        <option value="<?= esc($p) ?>"><?= esc($p) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="cs-field">
                <label for="newYearLevel">Year Level</label>
                <select id="newYearLevel">
                    <?php foreach ($yearLevels as $yl): ?>
                        <option value="<?= esc($yl) ?>"><?= esc($yl) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="cs-field">
                <label for="newAcademicYear">Academic Year</label>
                <select id="newAcademicYear">
                    <?php foreach ($academicYears as $ay): ?>
                        <option value="<?= esc($ay) ?>"><?= esc($ay) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="cs-field">
                <label for="newSectionName">Section Name</label>
                <input type="text" id="newSectionName" placeholder="e.g. AIT3 or 1A" maxlength="20">
            </div>
            <div class="cs-field">
                <label for="newCapacity">Capacity <span class="optional">(Optional)</span></label>
                <div class="unit-hint">
                    <input type="number" id="newCapacity" min="1" placeholder="Leave blank for unlimited">
                    <span>seats</span>
                </div>
            </div>
        </div>

        <div class="cs-form-footer">
            <span class="hint">Newly created sections will appear immediately in your roster directory.</span>
            <button type="button" class="btn btn-primary" onclick="createSection()">
                <span class="material-symbols-outlined" style="font-size:18px;">add</span>
                Create Section
            </button>
        </div>
    </div>

    <?php if ($sections === []): ?>
        <div class="panel">
            <div class="empty-state">
                <div class="material-symbols-outlined">groups</div>
                <p>No sections yet. Create one above, or plot a schedule and one will appear automatically.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="cs-list-head">
            <h2>Active Department Sections</h2>
            <span class="ac-badge-active"><?= count($sections) ?> Section<?= count($sections) === 1 ? '' : 's' ?></span>
        </div>

        <?php
            $avatarColors = ['#2563eb', '#7c3aed', '#d97706', '#0891b2', '#db2777', '#16a34a'];

            $groups = [];
            foreach ($sections as $g) {
                $groups[$g['academic_year'] . '|' . $g['program'] . '|' . $g['year_level']] = $g;
            }
        ?>

        <div class="panel" style="margin-bottom:16px;">
            <h3 style="margin:0 0 6px;">Rebalance Sections</h3>
            <p style="margin:0 0 12px; color:var(--color-text-muted, #64748b); font-size:13px;">
                Preview how students would be redistributed across a year level's sections. Sections that are locked,
                finalized, or already have schedules, faculty, enrollments or grades are never moved.
            </p>
            <?php foreach ($groups as $g): ?>
                <button type="button" class="btn btn-secondary" style="margin:0 8px 8px 0;"
                        onclick='previewRebalance(<?= json_encode(['academic_year' => $g['academic_year'], 'program' => $g['program'], 'year_level' => $g['year_level']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                    <?= esc($g['program']) ?> · <?= esc($g['year_level']) ?> · <?= esc($g['academic_year']) ?>
                </button>
            <?php endforeach ?>
            <div id="rebalancePanel" style="display:none; margin-top:12px; border-top:1px solid #e2e8f0; padding-top:12px;"></div>
        </div>
        <?php foreach ($sections as $sec): ?>
            <?php
                $roster = $rosterBySection[(int) $sec['section_id']] ?? [];
                $count  = count($roster);
                $cap    = $sec['capacity'];

                if ($cap === null) {
                    $badgeClass = 'cap-badge--unlimited';
                    $badgeLabel = 'Unlimited';
                } elseif ($count >= $cap) {
                    $badgeClass = 'cap-badge--full';
                    $badgeLabel = 'Full Capacity';
                } else {
                    $remaining  = $cap - $count;
                    $badgeClass = 'cap-badge--open';
                    $badgeLabel = $remaining . ' Seat' . ($remaining === 1 ? '' : 's') . ' Available';
                }

                $pct = ($cap !== null && $cap > 0) ? round($count / $cap * 100) : null;
            ?>
            <div class="panel section-card">
                <div class="section-card__head">
                    <div class="section-card__top">
                        <div>
                            <div class="section-card__title-row">
                                <h3><?= esc($sec['program']) ?> · <?= esc($sec['year_level']) ?> · Section <?= esc($sec['section']) ?></h3>
                                <span class="cap-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                                <span class="cap-badge cap-badge--unlimited" title="Section status"><?= esc(ucfirst($sec['status'])) ?><?= ($sec['origin'] ?? 'manual') === 'auto' ? ' · auto' : '' ?></span>
                                <?php $isLocked = $sec['status'] === 'locked'; ?>
                                <button type="button" class="icon-btn" title="<?= $isLocked ? 'Unlock section' : 'Lock section (stops automatic balancing)' ?>"
                                        onclick="setSectionState(<?= (int) $sec['section_id'] ?>, {status: '<?= $isLocked ? 'open' : 'locked' ?>'})">
                                    <span class="material-symbols-outlined" style="font-size:18px;"><?= $isLocked ? 'lock' : 'lock_open' ?></span>
                                </button>
                                <?php if (! empty($sec['auto_balance'])): ?>
                                    <button type="button" class="icon-btn" title="Automatic balancing on — click to turn off" onclick="setSectionState(<?= (int) $sec['section_id'] ?>, {auto_balance: false})">
                                        <span class="material-symbols-outlined" style="font-size:18px;">balance</span>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="icon-btn" title="Automatic balancing off — click to turn on" onclick="setSectionState(<?= (int) $sec['section_id'] ?>, {auto_balance: true})">
                                        <span class="material-symbols-outlined" style="font-size:18px; opacity:.4;">block</span>
                                    </button>
                                <?php endif ?>
                            </div>
                            <div class="section-card__meta">
                                <span><?= esc($sec['academic_year']) ?></span>
                                <span class="dot">&middot;</span>
                                <span><strong><?= $count ?></strong> student<?= $count === 1 ? '' : 's' ?> enrolled</span>
                            </div>
                        </div>
                        <div class="section-card__assign">
                            <select id="assign-<?= esc($sec['section_id']) ?>">
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
                </div>

                <?php if ($pct !== null): ?>
                    <div class="section-card__progress">
                        <span class="label">Capacity</span>
                        <div class="track"><div class="fill" style="width:<?= min(100, $pct) ?>%;"></div></div>
                        <span class="label"><?= $count ?> / <?= esc($cap) ?> (<?= $pct ?>%)</span>
                    </div>
                <?php endif ?>

                <?php if ($roster === []): ?>
                    <?php $enrolledHere = $enrollmentsBySection[(int) $sec['section_id']] ?? null; ?>
                    <div class="roster-empty">
                        No students assigned yet.
                        <?php if ($enrolledHere !== null): ?>
                            <strong><?= esc($enrolledHere['students']) ?> student<?= $enrolledHere['students'] === 1 ? ' is' : 's are' ?> still enrolled in its subjects</strong>
                            (<?= esc($enrolledHere['subjects']) ?>), so this section can't be deleted until those enrollments are dropped.
                        <?php endif ?>
                    </div>
                <?php else: ?>
                    <table class="roster-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roster as $idx => $r): ?>
                                <tr>
                                    <td>
                                        <div class="roster-student">
                                            <div class="roster-avatar" style="background:<?= $avatarColors[$idx % count($avatarColors)] ?>;"><?= esc(mb_strtoupper(mb_substr($r['username'], 0, 1))) ?></div>
                                            <span><?= esc($r['username']) ?></span>
                                        </div>
                                    </td>
                                    <td style="text-align:right;">
                                        <button type="button" class="icon-btn" title="Remove from section" onclick="unassignStudent(<?= (int) $r['id'] ?>)">
                                            <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
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
        el.className = 'flash ' + (isError ? 'error' : 'success');
        el.textContent = message;
        // The banner sits at the top of a long page; without this a failed
        // delete further down looks like nothing happened.
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
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

        const body = { section_id: sectionId, user_id: userId };
        let result = await postJSON('<?= site_url('chairperson/sections/assign-student') ?>', body);

        if (!result.ok && result.over_capacity) {
            const reason = prompt(result.error + '\n\nOverride reason (recorded in the audit log):');

            if (!reason || !reason.trim()) {
                return;
            }

            body.override_reason = reason.trim();
            result = await postJSON('<?= site_url('chairperson/sections/assign-student') ?>', body);
        }

        if (!result.ok) {
            showFeedback(result.error || 'Could not assign the student.', true);
            return;
        }

        reloadWithMessage(`${label} added to the section.`);
    }

    async function deleteSection(sectionId, label) {
        if (!await acConfirm(`Delete "${label}"? This cannot be undone.`)) {
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

    async function setSectionState(sectionId, change) {
        const reason = prompt('Reason for this change (recorded in the audit log):');

        if (!reason || !reason.trim()) {
            return;
        }

        const result = await postJSON('<?= site_url('chairperson/sections/state') ?>', Object.assign({ section_id: sectionId, reason: reason.trim() }, change));

        if (!result.ok) {
            showFeedback(result.error || 'Could not update the section.', true);
            return;
        }

        reloadWithMessage('Section updated.');
    }

    const escHtml = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    async function previewRebalance(group) {
        const panel  = document.getElementById('rebalancePanel');
        const result = await postJSON('<?= site_url('chairperson/sections/rebalance/preview') ?>', group);

        panel.style.display = 'block';

        if (!result.ok) {
            panel.innerHTML = '<p>' + escHtml(result.error || 'Could not build the preview.') + '</p>';
            return;
        }

        const p = result.preview;
        const rows = p.sections.map(s =>
            '<tr><td>' + escHtml(s.section) + '</td><td>' + escHtml(s.status) + '</td><td>' + s.count + '</td><td>' +
            (s.can_rebalance ? 'Yes' : 'No — ' + escHtml(s.block_reason || s.status)) + '</td></tr>').join('');

        let html =
            '<p><strong>' + escHtml(group.program) + ' · ' + escHtml(group.year_level) + '</strong> — policy: min ' + p.policy.min +
            ', target ' + p.policy.target + ', max ' + p.policy.max + ', overflow ' + p.policy.overflow + '</p>' +
            '<table class="roster-table"><thead><tr><th>Section</th><th>Status</th><th>Students</th><th>Can rebalance</th></tr></thead><tbody>' + rows + '</tbody></table>' +
            '<p style="margin-top:10px;"><strong>Current:</strong> ' + (p.current_sizes.join(' / ') || '—') +
            ' &nbsp; <strong>Proposed:</strong> ' + (p.proposed_sizes.join(' / ') || '—') +
            ' &nbsp; <strong>Students affected:</strong> ' + p.students_affected + '</p>' +
            '<p>' + escHtml(p.explanation) + '</p>' +
            p.warnings.map(w => '<p style="color:#b45309;">⚠ ' + escHtml(w) + '</p>').join('');

        if (p.has_changes) {
            html += '<input type="text" id="rebalanceReason" placeholder="Reason for rebalancing (required)" style="width:60%; margin-right:8px;">' +
                    '<button type="button" class="btn btn-primary" id="rebalanceApply">Apply rebalance</button>';
        } else {
            html += '<p><em>No changes recommended.</em></p>';
        }

        panel.innerHTML = html;

        const apply = document.getElementById('rebalanceApply');

        if (apply) {
            apply.onclick = async () => {
                const reason = document.getElementById('rebalanceReason').value.trim();

                if (!reason) {
                    showFeedback('Enter a reason for the rebalance.', true);
                    return;
                }

                if (!await acConfirm('Apply this rebalance? ' + p.students_affected + ' student(s) will be moved.')) {
                    return;
                }

                const res = await postJSON('<?= site_url('chairperson/sections/rebalance/apply') ?>', Object.assign({}, group, { confirm: true, reason }));

                if (!res.ok) {
                    showFeedback(res.error || 'Rebalance failed.', true);
                    return;
                }

                reloadWithMessage('Rebalanced: ' + res.moved + ' student(s) moved' + (res.created.length ? ', created ' + res.created.join(', ') : '') + '.');
            };
        }
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

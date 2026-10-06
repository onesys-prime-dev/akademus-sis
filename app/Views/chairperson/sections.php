<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'sections';
$pageTitle     = 'Sections';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pages/chairperson-sections.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/chairperson-sections.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="sc-page">
<div class="sc-heading">
    <div>
        <h1>Sections</h1>
        <p class="sc-subtitle">Manage section capacity, student assignments, and rosters.</p>
    </div>
    <?php if ($deptName !== null): ?><button type="button" class="btn btn-primary" id="scOpenCreate"><span class="material-symbols-outlined" aria-hidden="true">add</span>Create section</button><?php endif ?>
</div>

<?php if ($deptName === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>You're not assigned to a department yet. Contact an administrator.</p>
        </div>
    </div>
<?php else: ?>

    <div id="feedback" class="flash" role="status" aria-live="polite" style="display:none;"></div>

    <details class="panel cs-card" id="scCreatePanel">
        <summary class="sc-tool-summary"><span class="material-symbols-outlined" aria-hidden="true">add_circle_outline</span><span><strong>Create a section</strong><small>Choose a program, term, and capacity.</small></span><span class="material-symbols-outlined sc-chevron" aria-hidden="true">expand_more</span></summary>
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
                        <option value="<?= esc($ay) ?>" <?= $ay === $activeAcademicYear ? 'selected' : '' ?>>
                            <?= esc($ay) ?><?= $ay === $activeAcademicYear ? ' (Active term)' : ' (Upcoming term)' ?>
                        </option>
                    <?php endforeach ?>
                </select>
                <small class="sc-field-help">Choose the active or upcoming term.</small>
            </div>
            <div class="cs-field">
                <label for="newSectionName">Section Name</label>
                <input type="text" id="newSectionName" required aria-label="Section name, required" placeholder="e.g. AIT3 or 1A" maxlength="20">
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
            <span class="hint">The section will be ready for student assignment.</span>
            <button type="button" class="btn btn-primary" onclick="createSection()">
                <span class="material-symbols-outlined" style="font-size:18px;">add</span>
                Create Section
            </button>
        </div>
    </details>

    <?php if ($sections === []): ?>
        <div class="panel">
            <div class="empty-state">
                <div class="material-symbols-outlined">groups</div>
                <p>No sections yet. Create your first section to begin assigning students.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="cs-list-head">
            <h2>Section directory</h2>
            <span class="sc-count"><?= count($sections) ?> Section<?= count($sections) === 1 ? '' : 's' ?></span>
        </div>

        <?php
            $avatarColors = ['#174ea6', '#0f766e', '#a65b08', '#15803d', '#4f5eb3'];

            $groups = [];
            foreach ($sections as $g) {
                $groups[$g['academic_year'] . '|' . $g['program'] . '|' . $g['year_level']] = $g;
            }
        ?>

        <details class="panel sc-rebalance" id="scRebalancePanel"><summary class="sc-tool-summary"><span class="material-symbols-outlined" aria-hidden="true">balance</span><span><strong>Rebalance sections</strong><small>Preview student allocation before applying changes.</small></span><span class="material-symbols-outlined sc-chevron" aria-hidden="true">expand_more</span></summary><div class="sc-rebalance-body"><p>Choose a cohort to preview its allocation. Protected sections remain unchanged.</p><div class="sc-rebalance-controls"><label class="sc-sr-only" for="scRebalanceGroup">Cohort to rebalance</label><select id="scRebalanceGroup"><?php foreach ($groups as $g): ?><option value="<?= esc(json_encode(['academic_year' => $g['academic_year'], 'program' => $g['program'], 'year_level' => $g['year_level']]), 'attr') ?>"><?= esc($g['program'] . ' / ' . $g['year_level'] . ' / ' . $g['academic_year']) ?></option><?php endforeach ?></select><button type="button" class="btn btn-secondary" onclick="previewRebalance(JSON.parse(document.getElementById('scRebalanceGroup').value))">Preview allocation</button></div>
            <div id="rebalancePanel" style="display:none; margin-top:12px; border-top:1px solid #e2e8f0; padding-top:12px;"></div>
        </div></details>
        <div class="sc-directory-toolbar" id="scDirectoryTools" hidden><label class="sc-search"><span class="material-symbols-outlined" aria-hidden="true">search</span><span class="sc-sr-only">Search sections or students</span><input type="search" id="scSearch" placeholder="Search section or student"></label><div class="sc-filter"><label for="scTerm">Term</label><select id="scTerm"><option value="all">All terms</option><?php foreach (array_unique(array_column($sections, 'academic_year')) as $term): ?><option value="<?= esc($term, 'attr') ?>"><?= esc($term) ?></option><?php endforeach ?></select></div><div class="sc-filter"><label for="scProgram">Program</label><select id="scProgram"><option value="all">All programs</option><?php foreach (array_unique(array_column($sections, 'program')) as $program): ?><option value="<?= esc($program, 'attr') ?>"><?= esc($program) ?></option><?php endforeach ?></select></div></div><p class="sc-directory-result" id="scResults" role="status" aria-live="polite" hidden></p><div class="sc-no-results" id="scNoResults" hidden><strong>No matching sections</strong><p>Try another search or adjust the program and term filters.</p><button type="button" class="btn btn-secondary" id="scClearFilters">Clear filters</button></div>
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
            <details class="panel section-card" data-directory-section data-term="<?= esc($sec['academic_year'], 'attr') ?>" data-program="<?= esc($sec['program'], 'attr') ?>"><summary class="sc-section-summary"><span class="sc-section-mark"><?= esc($sec['section']) ?></span><span class="sc-section-name"><strong><?= esc($sec['program']) ?></strong><small><?= esc($sec['year_level']) ?> &middot; <?= esc($sec['academic_year']) ?></small></span><span class="sc-summary-size"><strong><?= $count ?><?= $cap !== null ? ' / ' . (int) $cap : '' ?></strong><small>students<?= $cap === null ? ' / unlimited' : '' ?></small></span><span class="cap-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span><span class="sc-summary-state" data-state="<?= esc($sec['status'], 'attr') ?>"><?= esc(ucfirst($sec['status'])) ?></span><span class="material-symbols-outlined sc-chevron" aria-hidden="true">expand_more</span></summary>
                <div class="section-card__head">
                    <div class="section-card__top">
                        <div>
                            <div class="section-card__title-row">
                                <h3><?= esc($sec['program']) ?> · <?= esc($sec['year_level']) ?> · Section <?= esc($sec['section']) ?></h3>
                                <span class="cap-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                                <span class="cap-badge cap-badge--unlimited" title="Section status"><?= esc(ucfirst($sec['status'])) ?><?= ($sec['origin'] ?? 'manual') === 'auto' ? ' · auto' : '' ?></span>
                                <?php $isLocked = $sec['status'] === 'locked'; ?>
                                <button type="button" class="icon-btn" title="<?= $isLocked ? 'Unlock section' : 'Lock section (stops automatic balancing)' ?>" aria-label="<?= $isLocked ? 'Unlock section' : 'Lock section (stops automatic balancing)' ?>"
                                        onclick="setSectionState(<?= (int) $sec['section_id'] ?>, {status: '<?= $isLocked ? 'open' : 'locked' ?>'})">
                                    <span class="material-symbols-outlined" style="font-size:18px;"><?= $isLocked ? 'lock' : 'lock_open' ?></span>
                                </button>
                                <?php if (! empty($sec['auto_balance'])): ?>
                                    <button type="button" class="icon-btn" title="Automatic balancing on — click to turn off" aria-label="Automatic balancing on — click to turn off" onclick="setSectionState(<?= (int) $sec['section_id'] ?>, {auto_balance: false})">
                                        <span class="material-symbols-outlined" style="font-size:18px;">balance</span>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="icon-btn" title="Automatic balancing off — click to turn on" aria-label="Automatic balancing off — click to turn on" onclick="setSectionState(<?= (int) $sec['section_id'] ?>, {auto_balance: true})">
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
                            <select id="assign-<?= esc($sec['section_id']) ?>" aria-label="Student to assign to <?= esc($sec['section'], 'attr') ?>">
                                <option value="">Add a student…</option>
                                <?php foreach ($students as $s): ?>
                                    <?php if ((int) ($s['section_id'] ?? 0) === (int) $sec['section_id']) continue; ?>
                                    <option value="<?= esc($s['id']) ?>"><?= esc($s['username']) ?><?= $s['section_id'] ? ' (currently assigned elsewhere)' : '' ?></option>
                                <?php endforeach ?>
                            </select>
                            <button type="button" class="btn btn-secondary" onclick="assignStudent(<?= (int) $sec['section_id'] ?>)">Add</button>
                            <button type="button" class="icon-btn" title="Delete section" aria-label="Delete section" onclick='deleteSection(<?= (int) $sec['section_id'] ?>, <?= json_encode($sec['program'] . ' · ' . $sec['year_level'] . ' · Section ' . $sec['section'], JSON_HEX_APOS) ?>)'>
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
                                <th scope="col">Student</th>
                                <th scope="col" style="text-align:right;">Actions</th>
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
                                        <button type="button" class="icon-btn" title="Remove from section" aria-label="Remove from section" onclick="unassignStudent(<?= (int) $r['id'] ?>)">
                                            <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                <?php endif ?>
            </details>
        <?php endforeach ?>
    <?php endif ?>

<?php endif ?>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/chairperson-sections-ui.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/chairperson-sections-ui.js') ?>" defer></script>
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
        try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        const result = await res.json().catch(() => null);
        return result || {ok: false, error: 'Unable to process the request. Please try again.'};
        } catch (error) { return {ok: false, error: 'Connection failed. Please try again.'}; }
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

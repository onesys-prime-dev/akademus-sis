<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sections</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/tokens.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/base.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/base.css') ?>">
    <style>
        <?= $this->include('components/shell/style') ?>

        .section-card { margin-bottom: 14px; }
        .section-card__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .section-card__title { font-size: 14.5px; font-weight: 700; }
        .section-card__meta { font-size: 12px; color: var(--on-surface-variant); }
        .roster-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--outline-variant); }
        .roster-row:last-child { border-bottom: none; }
        .roster-row__name { font-size: 13.5px; font-weight: 600; }
        .roster-empty { font-size: 12.5px; color: var(--on-surface-variant); padding: 8px 0; }
        select.ac-inline-select { padding: 7px 10px; border: 1px solid var(--outline-variant); border-radius: 8px; font-family: inherit; font-size: 12.5px; background: var(--surface-container-low); }
        .icon-btn { border: none; background: none; cursor: pointer; color: var(--on-surface-variant); padding: 4px; border-radius: 8px; display: flex; }
        .icon-btn:hover { background: #fde8e8; color: var(--error); }
    </style>
</head>

<body>

    <aside class="side-drawer glass">
        <div class="brand"><span class="material-symbols-outlined">layers</span></div>
        <nav>
            <a class="drawer-link" href="<?= site_url('chairperson/overview') ?>" title="Dashboard">
                <span class="material-symbols-outlined">home</span>
                <span class="drawer-label">Dashboard</span>
            </a>
            <a class="drawer-link" href="<?= site_url('chairperson/profile') ?>" title="Profile">
                <span class="material-symbols-outlined">person</span>
                <span class="drawer-label">Profile</span>
            </a>
            <a class="drawer-link" href="<?= site_url('chairperson/dashboard') ?>" title="Schedule Plotting">
                <span class="material-symbols-outlined">calendar_month</span>
                <span class="drawer-label">Schedule Plotting</span>
            </a>
            <a class="drawer-link active" href="<?= site_url('chairperson/sections') ?>" title="Sections">
                <span class="material-symbols-outlined">groups</span>
                <span class="drawer-label">Sections</span>
            </a>
            <a class="drawer-link" href="<?= site_url('chairperson/offerings') ?>" title="Merged Classes">
                <span class="material-symbols-outlined">merge_type</span>
                <span class="drawer-label">Merged Classes</span>
            </a>
            <a class="drawer-link" href="<?= site_url('chairperson/grade-approvals') ?>" title="Grade Approvals">
                <span class="material-symbols-outlined">grade</span>
                <span class="drawer-label">Grade Approvals</span>
            </a>
            <a class="drawer-link" href="<?= site_url('chairperson/curriculum') ?>" title="Curriculum">
                <span class="material-symbols-outlined">menu_book</span>
                <span class="drawer-label">Curriculum</span>
            </a>
        </nav>

        <a class="drawer-link drawer-link--logout" href="<?= site_url('logout') ?>" title="Logout">
            <span class="material-symbols-outlined">logout</span>
            <span class="drawer-label">Logout</span>
        </a>
    </aside>

    <div class="app-main">

        <div class="header-card glass">
            <header class="topbar">
                <h1>Sections</h1>
                <div class="user-menu" id="userMenu">
                    <button type="button" class="user-trigger" id="userMenuBtn">
                        <div class="avatar-sm"><?= esc(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
                        <div class="user-text">
                            <div class="name"><?= esc($user['name']) ?></div>
                            <div class="role"><?= esc($user['role']) ?></div>
                        </div>
                        <span class="material-symbols-outlined chevron">expand_more</span>
                    </button>
                    <div class="user-dropdown">
                        <a href="<?= site_url('chairperson/profile') ?>"><span class="material-symbols-outlined" style="font-size:18px;">settings</span> Settings</a>
                        <hr>
                        <a href="<?= site_url('logout') ?>" class="danger"><span class="material-symbols-outlined" style="font-size:18px;">logout</span> Logout</a>
                    </div>
                </div>
            </header>
        </div>

        <div class="content">

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
                            <select id="newProgram" class="ac-input" style="width:100%; padding:10px 14px; border:1px solid var(--outline-variant); border-radius:12px; background:var(--surface-container-low); font-family:inherit;">
                                <?php foreach ($programs as $p): ?>
                                    <option value="<?= esc($p) ?>"><?= esc($p) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Year Level</label>
                            <select id="newYearLevel" class="ac-input" style="width:100%; padding:10px 14px; border:1px solid var(--outline-variant); border-radius:12px; background:var(--surface-container-low); font-family:inherit;">
                                <?php foreach ($yearLevels as $yl): ?>
                                    <option value="<?= esc($yl) ?>"><?= esc($yl) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label>Academic Year</label>
                            <select id="newAcademicYear" class="ac-input" style="width:100%; padding:10px 14px; border:1px solid var(--outline-variant); border-radius:12px; background:var(--surface-container-low); font-family:inherit;">
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

        </div>

    </div>

    <script>
        (function () {
            const menu = document.getElementById('userMenu');
            const btn  = document.getElementById('userMenuBtn');
            btn.addEventListener('click', (e) => { e.stopPropagation(); menu.classList.toggle('open'); });
            document.addEventListener('click', () => menu.classList.remove('open'));
        })();

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

</body>

</html>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Merged Classes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        <?= $this->include('components/shell/style') ?>

        .offer-card { margin-bottom: 14px; }
        .offer-card__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .offer-card__title { font-size: 14.5px; font-weight: 700; }
        .offer-card__meta { font-size: 12px; color: var(--on-surface-variant); }
        .cand-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--outline-variant); }
        .cand-row:last-child { border-bottom: none; }
        .cand-sections label { display: block; font-size: 12.5px; margin: 3px 0; }
        .sec-pill { display: inline-flex; align-items: center; gap: 6px; background: var(--surface-container-low); border: 1px solid var(--outline-variant); border-radius: 20px; padding: 4px 10px; font-size: 12px; margin: 2px 4px 2px 0; }
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
            <a class="drawer-link" href="<?= site_url('chairperson/sections') ?>" title="Sections">
                <span class="material-symbols-outlined">groups</span>
                <span class="drawer-label">Sections</span>
            </a>
            <a class="drawer-link active" href="<?= site_url('chairperson/offerings') ?>" title="Merged Classes">
                <span class="material-symbols-outlined">merge_type</span>
                <span class="drawer-label">Merged Classes</span>
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
                <h1>Merged Classes</h1>
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
                    <h2>Mergeable Subjects</h2>
                    <p class="panel-sub">A subject plotted for two or more of your sections in the same term. Merging combines their rosters into one class with one shared capacity — neither section's other subjects are affected.</p>

                    <?php if (empty($candidates)): ?>
                        <div class="empty-state">
                            <div class="material-symbols-outlined">merge_type</div>
                            <p>No subject is currently plotted for two or more of your sections in the same term.</p>
                        </div>
                    <?php endif ?>

                    <?php foreach ($candidates as $i => $c): ?>
                        <div class="cand-row">
                            <div>
                                <strong><?= esc($c['subject_code']) ?></strong>
                                <div class="offer-card__meta"><?= esc($c['academic_year']) ?></div>
                                <div class="cand-sections">
                                    <?php foreach ($c['sections'] as $s): ?>
                                        <label>
                                            <input type="checkbox" name="cand-<?= $i ?>" value="<?= esc($s['section_id']) ?>">
                                            <?= esc($s['program']) ?> &middot; <?= esc($s['year_level']) ?> &middot; Section <?= esc($s['section']) ?>
                                        </label>
                                    <?php endforeach ?>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <input type="number" min="1" placeholder="Cap (optional)" id="cap-<?= $i ?>" style="width:110px; padding:8px 10px; border:1px solid var(--outline-variant); border-radius:10px;">
                                <button type="button" class="btn btn-primary" onclick="mergeCandidate(<?= $i ?>, '<?= esc($c['academic_year'], 'js') ?>', '<?= esc($c['subject_code'], 'js') ?>')">
                                    <span class="material-symbols-outlined" style="font-size:18px;">merge_type</span>
                                    Merge Selected
                                </button>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>

                <div class="panel">
                    <h2>Merged Classes</h2>
                    <p class="panel-sub">Each card is one combined class instance. Removing a section returns its students to being counted against that section's own capacity — no one is dropped from the roster.</p>

                    <?php if (empty($offerings)): ?>
                        <div class="empty-state">
                            <div class="material-symbols-outlined">inbox</div>
                            <p>No merged classes yet.</p>
                        </div>
                    <?php endif ?>

                    <?php foreach ($offerings as $o): ?>
                        <div class="offer-card">
                            <div class="offer-card__head">
                                <div>
                                    <div class="offer-card__title"><?= esc($o['subject_code']) ?></div>
                                    <div class="offer-card__meta">
                                        <?= esc($o['academic_year']) ?> &middot;
                                        Roster <?= esc($o['roster_count']) ?><?= $o['capacity'] !== null ? ' / ' . esc($o['capacity']) : ' (unlimited)' ?>
                                        <?php if ($o['capacity'] !== null && $o['roster_count'] > $o['capacity']): ?>
                                            <span style="color:#a11414; font-weight:700;"> &middot; over capacity</span>
                                        <?php endif ?>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <?php foreach ($o['sections'] as $s): ?>
                                    <span class="sec-pill">
                                        <?= esc($s['program']) ?> &middot; <?= esc($s['year_level']) ?> &middot; Sec. <?= esc($s['section']) ?>
                                        <button type="button" class="icon-btn" title="Remove from merge" onclick="unmergeSection(<?= esc($o['offering_id']) ?>, <?= esc($s['section_id']) ?>, '<?= esc($s['section'], 'js') ?>')">
                                            <span class="material-symbols-outlined" style="font-size:16px;">close</span>
                                        </button>
                                    </span>
                                <?php endforeach ?>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>

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

        function reloadWithMessage(message) {
            sessionStorage.setItem('offeringsFeedback', message);
            location.reload();
        }

        async function mergeCandidate(i, academicYear, subjectCode) {
            const checked = Array.from(document.querySelectorAll(`input[name="cand-${i}"]:checked`)).map(el => parseInt(el.value, 10));

            if (checked.length < 2) {
                showFeedback('Select at least two sections to merge.', true);
                return;
            }

            const capEl = document.getElementById('cap-' + i);

            const result = await postJSON('<?= site_url('chairperson/offerings/create') ?>', {
                academic_year: academicYear,
                subject_code: subjectCode,
                section_ids: checked,
                capacity: capEl.value,
            });

            if (!result.ok) {
                showFeedback(result.error || 'Could not create the merge.', true);
                return;
            }

            let msg = `${subjectCode} merged across ${checked.length} sections (roster ${result.roster_count}).`;
            if (result.over_capacity) {
                msg += ' Warning: the combined roster already exceeds the capacity you set.';
            }
            reloadWithMessage(msg);
        }

        async function unmergeSection(offeringId, sectionId, label) {
            if (!confirm(`Remove Section ${label} from this merged class?`)) {
                return;
            }

            const result = await postJSON('<?= site_url('chairperson/offerings/unmerge') ?>', {
                offering_id: offeringId,
                section_id: sectionId,
            });

            if (!result.ok) {
                showFeedback(result.error || 'Could not unmerge.', true);
                return;
            }

            reloadWithMessage(`Section ${label} removed from the merge.`);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const msg = sessionStorage.getItem('offeringsFeedback');
            if (msg) {
                showFeedback(msg, false);
                sessionStorage.removeItem('offeringsFeedback');
            }
        });
    </script>

</body>
</html>

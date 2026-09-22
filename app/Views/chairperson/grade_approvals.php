<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Grade Approvals</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/tokens.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/base.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/base.css') ?>">
    <style>
        <?= $this->include('components/shell/style') ?>

        .cls-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--outline-variant); }
        .cls-row:last-child { border-bottom: none; }
        .cls-meta { font-size: 12px; color: var(--on-surface-variant); }
        .cr-row { padding: 10px 0; border-bottom: 1px solid var(--outline-variant); }
        .cr-row:last-child { border-bottom: none; }
        .cr-values { font-size: 13px; margin: 4px 0; }
        .cr-reason { font-size: 12.5px; color: var(--on-surface-variant); font-style: italic; }
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
            <a class="drawer-link" href="<?= site_url('chairperson/offerings') ?>" title="Merged Classes">
                <span class="material-symbols-outlined">merge_type</span>
                <span class="drawer-label">Merged Classes</span>
            </a>
            <a class="drawer-link active" href="<?= site_url('chairperson/grade-approvals') ?>" title="Grade Approvals">
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
                <h1>Grade Approvals</h1>
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
                    <h2>Awaiting Your Approval</h2>
                    <p class="panel-sub">Faculty has submitted grades for these classes. Review, then lock to make them official and visible to students.</p>

                    <?php if (empty($classes)): ?>
                        <div class="empty-state">
                            <div class="material-symbols-outlined">inbox</div>
                            <p>No submitted classes waiting on your approval.</p>
                        </div>
                    <?php endif ?>

                    <?php foreach ($classes as $c): ?>
                        <div class="cls-row">
                            <div>
                                <strong><?= esc($c['subject_code']) ?></strong>
                                <div class="cls-meta"><?= esc($c['program']) ?> &middot; <?= esc($c['year_level']) ?> &middot; Section <?= esc($c['section']) ?> &middot; <?= esc($c['academic_year']) ?> &middot; <?= esc($c['student_count']) ?> students</div>
                            </div>
                            <a class="btn btn-primary" href="<?= site_url('chairperson/grade-approvals/class') ?>?<?= http_build_query([
                                'academic_year' => $c['academic_year'],
                                'program'       => $c['program'],
                                'year_level'    => $c['year_level'],
                                'section'       => $c['section'],
                                'subject_code'  => $c['subject_code'],
                            ]) ?>">Review &amp; Lock</a>
                        </div>
                    <?php endforeach ?>
                </div>

                <div class="panel">
                    <h2>Pending Correction Requests</h2>
                    <p class="panel-sub">A faculty member is asking to change a grade that's already locked. Each request needs your approval or rejection.</p>

                    <?php if (empty($changeRequests)): ?>
                        <div class="empty-state">
                            <div class="material-symbols-outlined">fact_check</div>
                            <p>No pending correction requests.</p>
                        </div>
                    <?php endif ?>

                    <?php foreach ($changeRequests as $r): ?>
                        <div class="cr-row" data-request-id="<?= esc($r['request_id']) ?>">
                            <strong><?= esc($r['student_username']) ?></strong> &middot; <?= esc($r['subject_code']) ?> &middot;
                            <?= esc($r['program']) ?> <?= esc($r['year_level']) ?>-<?= esc($r['section']) ?> &middot; <?= esc($r['academic_year']) ?>
                            <div class="cr-values"><?= esc($r['current_value']) ?> &rarr; <strong><?= esc($r['requested_value']) ?></strong> &middot; requested by <?= esc($r['requested_by_username']) ?></div>
                            <div class="cr-reason">"<?= esc($r['reason']) ?>"</div>
                            <div style="display:flex; gap:8px; margin-top:8px;">
                                <button type="button" class="btn btn-primary" onclick="reviewChange(<?= esc($r['request_id']) ?>, 'approve')">Approve</button>
                                <button type="button" class="btn btn-secondary" onclick="reviewChange(<?= esc($r['request_id']) ?>, 'reject')">Reject</button>
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

        async function reviewChange(requestId, action) {
            const notes = prompt(action === 'approve' ? 'Optional note for this approval:' : 'Reason for rejecting (optional):') || '';

            const url = action === 'approve'
                ? '<?= site_url('chairperson/grade-approvals/change-requests/approve') ?>'
                : '<?= site_url('chairperson/grade-approvals/change-requests/reject') ?>';

            const result = await postJSON(url, { request_id: requestId, notes });

            if (!result.ok) {
                showFeedback(result.error || 'Could not process the request.', true);
                return;
            }

            document.querySelector(`[data-request-id="${requestId}"]`)?.remove();
            showFeedback(`Request ${action === 'approve' ? 'approved' : 'rejected'}.`, false);
        }
    </script>

</body>
</html>

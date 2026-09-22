<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Grading</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/tokens.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/base.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/base.css') ?>">
    <style>
        <?= $this->include('components/shell/style') ?>

        .class-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--outline-variant); }
        .class-row:last-child { border-bottom: none; }
        .class-title { font-weight: 700; font-size: 14px; }
        .class-meta { font-size: 12px; color: var(--on-surface-variant); }
        .status-pill { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .status-pill.locked { background: #dcfce7; color: #14532d; }
        .status-pill.submitted { background: #dff8fb; color: #164e63; }
        .status-pill.pending { background: #fef9c3; color: #713f12; }
    </style>
</head>

<body>

    <aside class="side-drawer glass">
        <div class="brand"><span class="material-symbols-outlined">layers</span></div>
        <nav>
            <a class="drawer-link" href="<?= site_url('faculty/dashboard') ?>" title="Dashboard">
                <span class="material-symbols-outlined">home</span>
                <span class="drawer-label">Dashboard</span>
            </a>
            <a class="drawer-link" href="<?= site_url('faculty/profile') ?>" title="Profile">
                <span class="material-symbols-outlined">person</span>
                <span class="drawer-label">Profile</span>
            </a>
            <a class="drawer-link" href="<?= site_url('faculty/schedule') ?>" title="Schedule">
                <span class="material-symbols-outlined">calendar_month</span>
                <span class="drawer-label">Schedule</span>
            </a>
            <a class="drawer-link" href="<?= site_url('faculty/availability') ?>" title="Availability">
                <span class="material-symbols-outlined">event_available</span>
                <span class="drawer-label">Availability</span>
            </a>
            <a class="drawer-link active" href="<?= site_url('faculty/grading') ?>" title="Grading">
                <span class="material-symbols-outlined">grade</span>
                <span class="drawer-label">Grading</span>
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
                <h1>Grading</h1>
                <div class="user-menu" id="userMenu">
                    <button type="button" class="user-trigger" id="userMenuBtn">
                        <div class="avatar-sm"><?= esc(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
                        <div class="user-text">
                            <div class="name"><?= esc($user['name']) ?></div>
                            <div class="role">Faculty</div>
                        </div>
                        <span class="material-symbols-outlined chevron">expand_more</span>
                    </button>
                    <div class="user-dropdown">
                        <a href="<?= site_url('faculty/profile') ?>"><span class="material-symbols-outlined" style="font-size:18px;">settings</span> Settings</a>
                        <hr>
                        <a href="<?= site_url('logout') ?>" class="danger"><span class="material-symbols-outlined" style="font-size:18px;">logout</span> Logout</a>
                    </div>
                </div>
            </header>
        </div>

        <div class="content">

            <div class="panel">
                <h2>My Classes</h2>
                <p class="panel-sub">Every subject you're assigned to teach. Enter grades, submit for chairperson approval, and track lock status here.</p>

                <?php if (empty($classes)): ?>
                    <div class="empty-state">
                        <div class="material-symbols-outlined">grade</div>
                        <p>You have no claimed teaching loads yet.</p>
                    </div>
                <?php endif ?>

                <?php foreach ($classes as $c): ?>
                    <?php $a = $c['assignment']; ?>
                    <div class="class-row">
                        <div>
                            <div class="class-title"><?= esc($a['subject_code']) ?></div>
                            <div class="class-meta">
                                <?= esc($a['program']) ?> &middot; <?= esc($a['year_level']) ?> &middot; Section <?= esc($a['section']) ?> &middot; <?= esc($a['academic_year']) ?>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span class="class-meta"><?= esc($c['graded_count']) ?> / <?= esc($c['student_count']) ?> graded</span>
                            <?php if ($c['locked']): ?>
                                <span class="status-pill locked">Locked</span>
                            <?php elseif ($c['submitted']): ?>
                                <span class="status-pill submitted">Submitted</span>
                            <?php elseif ($c['graded_count'] > 0): ?>
                                <span class="status-pill pending">In progress</span>
                            <?php endif ?>
                            <a class="btn btn-primary" href="<?= site_url('faculty/grading/class') ?>?<?= http_build_query([
                                'academic_year' => $a['academic_year'],
                                'program'       => $a['program'],
                                'year_level'    => $a['year_level'],
                                'section'       => $a['section'],
                                'subject_code'  => $a['subject_code'],
                            ]) ?>">Open</a>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>

        </div>

    </div>

    <script>
        (function () {
            const menu = document.getElementById('userMenu');
            const btn  = document.getElementById('userMenuBtn');
            btn.addEventListener('click', (e) => { e.stopPropagation(); menu.classList.toggle('open'); });
            document.addEventListener('click', () => menu.classList.remove('open'));
        })();
    </script>

</body>
</html>

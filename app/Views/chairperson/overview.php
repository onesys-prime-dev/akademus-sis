<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/tokens.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/base.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/base.css') ?>">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            /* Aliased onto the shared design-system tokens (public/assets/css/design-system/tokens.css). */
            --primary:            var(--color-primary-hover);
            --primary-dark:       #001849;
            --primary-container:  var(--color-primary);
            --primary-fixed:      var(--color-primary-soft);
            --warning:            var(--color-warning);
            --error:              var(--color-danger);
            --success:            var(--color-success);
            --success-container:  var(--color-success-soft);
            --surface:            var(--color-background);
            --surface-container-lowest: var(--color-surface);
            --surface-container-low:    var(--color-surface-secondary);
            --surface-container-high:   var(--color-surface-sunken);
            --on-surface:         var(--color-text-primary);
            --on-surface-variant: var(--color-text-secondary);
            --outline:            var(--color-text-tertiary);
            --outline-variant:    var(--color-border);
            --radius-sm: var(--radius-md);
            --radius-md: var(--radius-lg);
            --radius-lg: var(--radius-xl);
        }

        html, body { height: 100%; }

        body {
            display: flex;
            padding: 12px;
            gap: 12px;
            overflow: hidden;
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: var(--surface);
            color: var(--on-surface);
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            font-size: 20px;
            line-height: 1;
            vertical-align: middle;
        }

        .glass {
            background: rgba(255, 255, 255, .7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, .4);
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--outline-variant); border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--outline); }

        /* ===== Sidebar ===== */
        .side-drawer {
            width: 64px;
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 0;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            transition: width .16s ease;
            overflow: hidden;
            z-index: 40;
        }

        .side-drawer:hover { width: 200px; align-items: stretch; }

        .side-drawer .brand {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #fff;
            box-shadow: var(--shadow-sm);
            color: var(--primary-container);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            flex: 0 0 auto;
        }

        .side-drawer:hover .brand { margin-left: 12px; margin-right: auto; }

        .side-drawer nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
            align-items: center;
        }

        .side-drawer:hover nav { align-items: stretch; padding: 0 8px; }

        .drawer-link {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            color: var(--on-surface-variant);
            text-decoration: none;
            flex: 0 0 auto;
            justify-content: center;
            transition: background .15s, color .15s;
            white-space: nowrap;
            overflow: hidden;
        }

        .side-drawer:hover .drawer-link { width: 100%; justify-content: flex-start; padding: 0 12px; }

        .drawer-link .drawer-label { font-size: 13px; font-weight: 600; display: none; }
        .side-drawer:hover .drawer-label { display: inline; }

        .drawer-link:hover { color: var(--on-surface); background: var(--surface-container-high); }
        .drawer-link.active { color: var(--primary-container); background: rgba(0,64,166,.1); }
        .drawer-link.active .material-symbols-outlined { font-variation-settings: 'FILL' 1; }

        .drawer-link--logout { margin-top: auto; }
        .drawer-link--logout:hover { color: var(--error); background: #fef2f2; }

        /* ===== Shell ===== */
        .app-main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
            height: 100%;
            overflow: hidden;
        }

        .header-card {
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            flex: 0 0 auto;
            overflow: hidden;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--surface-container-lowest);
            padding: 14px 24px;
        }

        .topbar h1 { font-size: 20px; font-weight: 800; color: var(--primary); letter-spacing: -0.01em; }
        .topbar .user { text-align: right; }
        .topbar .user .name { font-weight: 700; }
        .topbar .user .role { font-size: 12px; color: var(--on-surface-variant); }

        /* ===== User menu (avatar dropdown) ===== */
        .user-menu { position: relative; }

        .user-trigger {
            display: flex;
            align-items: center;
            gap: 10px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 8px 4px 4px;
            border-radius: var(--radius-sm);
            font-family: inherit;
            transition: background .15s;
        }

        .user-trigger:hover { background: var(--surface-container-high); }

        .avatar-sm {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--primary-container);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
            flex: 0 0 auto;
        }

        .user-trigger .user-text { text-align: left; }
        .user-trigger .name { font-weight: 700; font-size: 13px; color: var(--on-surface); }
        .user-trigger .role { font-size: 11.5px; color: var(--on-surface-variant); }
        .user-trigger .chevron { font-size: 18px; color: var(--on-surface-variant); transition: transform .15s; }
        .user-menu.open .chevron { transform: rotate(180deg); }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 190px;
            background: var(--surface-container-lowest);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-md);
            padding: 6px;
            display: none;
            z-index: 50;
        }

        .user-menu.open .user-dropdown { display: block; }

        .user-dropdown a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: 8px;
            color: var(--on-surface);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .user-dropdown a:hover { background: var(--surface-container-high); }
        .user-dropdown a.danger { color: var(--error); }
        .user-dropdown a.danger:hover { background: #fde8e8; }
        .user-dropdown hr { border: none; border-top: 1px solid var(--outline-variant); margin: 4px 0; }

        .btn {
            border: none;
            border-radius: var(--radius-sm);
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: box-shadow .15s, background .15s;
        }

        .btn-primary { background: var(--primary-container); color: #fff; box-shadow: var(--shadow-sm); }
        .btn-primary:hover { background: var(--primary); box-shadow: var(--shadow-md); }

        .content { flex: 1; min-height: 0; overflow-y: auto; padding: 4px; }

        .panel {
            background: var(--surface-container-lowest);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: 20px;
        }

        .panel h2 { font-size: 15px; font-weight: 700; margin-bottom: 14px; color: var(--on-surface); }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 16px;
        }

        .stat-card {
            background: var(--surface-container-lowest);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .stat-card .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,64,166,.1);
            color: var(--primary-container);
        }

        .stat-card .stat-value { font-size: 28px; font-weight: 800; letter-spacing: -0.02em; }
        .stat-card .stat-label { font-size: 12.5px; color: var(--on-surface-variant); font-weight: 600; }

        .stat-card.warning .stat-icon { background: rgba(255,178,0,.15); color: var(--warning); }
        .stat-card.warning .stat-value { color: var(--warning); }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--on-surface-variant);
        }

        .empty-state .material-symbols-outlined { font-size: 40px; opacity: .4; margin-bottom: 8px; }

        .program-chip {
            display: inline-block;
            background: var(--surface-container-low);
            color: var(--on-surface);
            font-size: 12.5px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 999px;
            margin: 0 8px 8px 0;
        }

        @media (max-width: 900px) {
            .content { padding: 4px 16px; }
            .stat-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        }

        @media (max-width: 640px) {
            body { padding: 6px; gap: 6px; }
            .side-drawer { width: 52px; }
            .stat-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>

    <aside class="side-drawer glass">
        <div class="brand"><span class="material-symbols-outlined">layers</span></div>
        <nav>
            <a class="drawer-link active" href="<?= site_url('chairperson/overview') ?>" title="Dashboard">
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
            <a class="drawer-link" href="<?= site_url('chairperson/curriculum') ?>" title="Curriculum">
                <span class="material-symbols-outlined">menu_book</span>
                <span class="drawer-label">Curriculum</span>
            </a>
            <a class="drawer-link" href="<?= site_url('chairperson/grade-approvals') ?>" title="Grade Approvals">
                <span class="material-symbols-outlined">grade</span>
                <span class="drawer-label">Grade Approvals</span>
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
                <h1>Dashboard</h1>
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
            <?php elseif ($activeYear === null): ?>
                <div class="panel">
                    <div class="empty-state">
                        <div class="material-symbols-outlined">event_busy</div>
                        <p>No active academic year is set. An administrator needs to activate one under Admin &gt; Academic Years.</p>
                    </div>
                </div>
            <?php else: ?>

                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><span class="material-symbols-outlined">calendar_month</span></div>
                        <div class="stat-value"><?= esc($stats['plotted_classes']) ?></div>
                        <div class="stat-label">Classes plotted this term</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><span class="material-symbols-outlined">groups</span></div>
                        <div class="stat-value"><?= esc($stats['faculty_count']) ?></div>
                        <div class="stat-label">Faculty in department</div>
                    </div>
                    <div class="stat-card <?= $stats['pending_approvals'] > 0 ? 'warning' : '' ?>">
                        <div class="stat-icon"><span class="material-symbols-outlined">swap_horiz</span></div>
                        <div class="stat-value"><?= esc($stats['pending_approvals']) ?></div>
                        <div class="stat-label">Swap approvals pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><span class="material-symbols-outlined">checklist</span></div>
                        <div class="stat-value"><?= esc($stats['curriculum_set']) ?> / <?= esc($stats['curriculum_slots']) ?></div>
                        <div class="stat-label">Curriculum presets set</div>
                    </div>
                </div>

                <div class="panel" style="margin-bottom:16px;">
                    <h2>Your Department</h2>
                    <p style="color:var(--on-surface-variant); margin-bottom:10px;"><?= esc($deptName) ?> &middot; Active term: <?= esc($activeYear) ?></p>
                    <?php if ($programs === []): ?>
                        <p style="color:var(--on-surface-variant); font-size:13px;">No programs registered under this department yet.</p>
                    <?php else: ?>
                        <?php foreach ($programs as $program): ?>
                            <span class="program-chip"><?= esc($program) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <h2>Quick Actions</h2>
                    <a class="btn btn-primary" href="<?= site_url('chairperson/dashboard') ?>">
                        <span class="material-symbols-outlined" style="font-size:18px;">calendar_month</span>
                        Go to Schedule Plotting
                    </a>
                </div>

            <?php endif; ?>
        </div>

    </div>

    <script>
        (function () {
            const menu = document.getElementById('userMenu');
            const btn  = document.getElementById('userMenuBtn');
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                menu.classList.toggle('open');
            });
            document.addEventListener('click', () => menu.classList.remove('open'));
        })();
    </script>

</body>

</html>

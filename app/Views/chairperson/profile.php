<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:            #003385;
            --primary-dark:       #001849;
            --primary-container:  #0040a6;
            --primary-fixed:      #dae1ff;
            --warning:            #FFB200;
            --error:              #EF4444;
            --success:            #10B981;
            --success-container:  #e6f7ec;
            --surface:            #F5F5F7;
            --surface-container-lowest: #ffffff;
            --surface-container-low:    #f7f7f9;
            --surface-container-high:   #e4e4e8;
            --on-surface:         #1d1d1f;
            --on-surface-variant: #6e6e73;
            --outline:            #86868b;
            --outline-variant:    #e5e5ea;
            --radius-sm: 12px;
            --radius-md: 16px;
            --radius-lg: 20px;
            --shadow-sm: 0 2px 12px rgba(0,0,0,.03), 0 0 1px rgba(0,0,0,.1);
            --shadow-md: 0 8px 24px -6px rgba(0,0,0,.08), 0 0 1px rgba(0,0,0,.1);
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

        .content { flex: 1; min-height: 0; overflow-y: auto; padding: 4px; max-width: 640px; }

        .panel {
            background: var(--surface-container-lowest);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: 24px;
            margin-bottom: 16px;
        }

        .panel h2 { font-size: 15px; font-weight: 700; margin-bottom: 4px; color: var(--on-surface); }
        .panel .panel-sub { font-size: 12.5px; color: var(--on-surface-variant); margin-bottom: 18px; }

        .avatar-row { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }

        .avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--primary-container);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
            flex: 0 0 auto;
        }

        .avatar-row .avatar-name { font-size: 17px; font-weight: 700; }
        .avatar-row .avatar-role { font-size: 13px; color: var(--on-surface-variant); }

        .field { margin-bottom: 16px; }

        .field label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--on-surface-variant);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 6px;
        }

        .field input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--outline-variant);
            border-radius: var(--radius-sm);
            background: var(--surface-container-low);
            font-family: inherit;
            font-size: 14px;
            color: var(--on-surface);
        }

        .field input:focus { outline: 2px solid var(--primary-container); outline-offset: 1px; }
        .field input:disabled { color: var(--on-surface-variant); cursor: not-allowed; }
        .field .hint { font-size: 11.5px; color: var(--on-surface-variant); margin-top: 5px; }

        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        .btn {
            border: none;
            border-radius: var(--radius-sm);
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: box-shadow .15s, background .15s;
        }

        .btn-primary { background: var(--primary-container); color: #fff; box-shadow: var(--shadow-sm); }
        .btn-primary:hover { background: var(--primary); box-shadow: var(--shadow-md); }

        .flash {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            margin-bottom: 16px;
        }

        .flash.success { background: var(--success-container); color: #056945; }
        .flash.error { background: #fde8e8; color: #a11414; }
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
            <a class="drawer-link active" href="<?= site_url('chairperson/profile') ?>" title="Profile">
                <span class="material-symbols-outlined">person</span>
                <span class="drawer-label">Profile</span>
            </a>
            <a class="drawer-link" href="<?= site_url('chairperson/dashboard') ?>" title="Schedule Plotting">
                <span class="material-symbols-outlined">calendar_month</span>
                <span class="drawer-label">Schedule Plotting</span>
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
                <h1>Profile</h1>
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

            <?php if (session('profile_message')): ?>
                <div class="flash success"><?= esc(session('profile_message')) ?></div>
            <?php endif ?>
            <?php if (session('profile_error')): ?>
                <div class="flash error"><?= esc(session('profile_error')) ?></div>
            <?php endif ?>

            <div class="panel">
                <div class="avatar-row">
                    <div class="avatar"><?= esc(mb_strtoupper(mb_substr($account['username'], 0, 1))) ?></div>
                    <div>
                        <div class="avatar-name"><?= esc($account['username']) ?></div>
                        <div class="avatar-role"><?= esc($deptName ?? 'No department') ?><?= $deptCode ? ' (' . esc($deptCode) . ')' : '' ?> &middot; Chairperson</div>
                    </div>
                </div>

                <form method="post" action="<?= site_url('chairperson/profile/update') ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label>Display Name</label>
                        <input type="text" name="username" value="<?= esc($account['username']) ?>" maxlength="100" required>
                    </div>
                    <div class="field">
                        <label>Email</label>
                        <input type="email" value="<?= esc($account['email']) ?>" disabled>
                        <div class="hint">Email is your login and can't be changed here — contact an administrator.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>

            <div class="panel">
                <h2>Change Password</h2>
                <div class="panel-sub">Choose a new password of at least 8 characters.</div>

                <form method="post" action="<?= site_url('chairperson/profile/password') ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label>New Password</label>
                            <input type="password" name="new_password" minlength="8" required>
                        </div>
                        <div class="field">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" minlength="8" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>

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

<?php helper('admin'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akademus SIS | <?= esc($pageTitle ?? 'Admin Console') ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= base_url('assets/css/pages/admin-console.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/admin-console.css') ?>">
    <?= $this->renderSection('styles') ?>
</head>
<body class="ac-page">

<?php
$navItems = [
    'dashboard'      => ['label' => 'Overview',        'icon' => 'dashboard',      'url' => 'admin/dashboard'],
    'departments'    => ['label' => 'Colleges',        'icon' => 'hub',            'url' => 'admin/departments'],
    'students'       => ['label' => 'Student Records', 'icon' => 'school',         'url' => 'admin/students'],
    'users'          => ['label' => 'User Accounts',   'icon' => 'group',          'url' => 'admin/users'],
    'rooms'          => ['label' => 'Facilities',      'icon' => 'meeting_room',   'url' => 'admin/rooms'],
    'academic-years' => ['label' => 'Academic Years',  'icon' => 'event',          'url' => 'admin/academic-years'],
    'roles'          => ['label' => 'Roles',           'icon' => 'admin_panel_settings', 'url' => 'admin/roles'],
];
$active   = $activeAdmin ?? '';
$username = auth()->user()->username ?? 'Administrator';
$initial  = strtoupper(substr($username, 0, 1));
?>

<aside class="ac-sidebar">
    <div class="ac-sidebar__brand">
        <img src="<?= base_url('assets/images/logos/akademus.png') ?>" alt="Akademus" class="ac-sidebar__brand-logo">
        <div class="ac-sidebar__brand-text">
            <h1 class="ac-sidebar__brand-name">Akademus</h1>
            <p class="ac-sidebar__brand-sub">Admin Console</p>
        </div>
    </div>

    <nav class="ac-sidebar__nav">
        <?php foreach ($navItems as $key => $item): ?>
            <a href="<?= site_url($item['url']) ?>" class="ac-nav-item <?= $active === $key ? 'is-active' : '' ?>" title="<?= esc($item['label']) ?>">
                <span class="material-symbols-outlined <?= $active === $key ? 'icon-fill' : '' ?>"><?= $item['icon'] ?></span>
                <span class="ac-nav-label"><?= esc($item['label']) ?></span>
            </a>
        <?php endforeach ?>
    </nav>
</aside>

<main class="ac-main">
    <header class="ac-topbar">
        <nav class="ac-topbar__links">
            <a href="#">Directory</a>
            <a href="#">Quick Actions</a>
        </nav>

        <div class="ac-topbar__actions">
            <div class="ac-search">
                <span class="material-symbols-outlined ac-search__icon">search</span>
                <input class="ac-search__input" placeholder="Search Akademus..." type="text">
            </div>

            <div class="ac-topbar__icons">
                <button type="button" class="ac-topbar__iconbtn" title="Notifications">
                    <span class="material-symbols-outlined">notifications</span>
                    <span class="ac-topbar__dot"></span>
                </button>
                <button type="button" class="ac-topbar__iconbtn" title="Settings">
                    <span class="material-symbols-outlined">settings</span>
                </button>
                <button type="button" class="ac-topbar__iconbtn" title="Help">
                    <span class="material-symbols-outlined">help_outline</span>
                </button>
            </div>

            <?= $this->renderSection('headerActions') ?>

            <div class="ac-user-menu" id="acUserMenu">
                <button type="button" class="ac-avatar ac-avatar--primary ac-user-menu__trigger" id="acUserMenuBtn" title="<?= esc($username) ?>">
                    <?= esc($initial) ?>
                </button>
                <div class="ac-user-menu__dropdown">
                    <div class="ac-user-menu__who">
                        <p class="ac-user-menu__name"><?= esc($username) ?></p>
                        <p class="ac-user-menu__role">Administrator</p>
                    </div>
                    <hr>
                    <a href="<?= site_url('/logout') ?>" class="ac-user-menu__item ac-user-menu__item--danger">
                        <span class="material-symbols-outlined">logout</span>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="ac-content animate-enter">
        <?= $this->renderSection('content') ?>
    </div>
</main>

<div class="ac-toast-container" id="acToastContainer"></div>

<?= $this->renderSection('scripts') ?>

<script>
    (function () {
        var toasts = <?= json_encode(array_merge(
            session('message') ? [['kind' => 'success', 'msg' => session('message')]] : [],
            session('errors') ? array_map(fn ($e) => ['kind' => 'error', 'msg' => $e], array_values((array) session('errors'))) : []
        )) ?>;

        var container = document.getElementById('acToastContainer');

        toasts.forEach(function (t, i) {
            setTimeout(function () {
                var el = document.createElement('div');
                el.className = 'ac-toast' + (t.kind === 'error' ? ' ac-toast--error' : '');
                el.innerHTML = '<span class="ac-toast__text"></span>';
                el.querySelector('.ac-toast__text').textContent = t.msg;
                container.appendChild(el);
                setTimeout(function () { el.remove(); }, 4500);
            }, i * 120);
        });
    })();

    function acOpenModal(id) {
        document.getElementById(id).classList.add('is-open');
    }

    function acCloseModal(id) {
        document.getElementById(id).classList.remove('is-open');
    }

    (function () {
        var menu = document.getElementById('acUserMenu');
        var btn  = document.getElementById('acUserMenuBtn');
        if (!menu || !btn) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            menu.classList.toggle('is-open');
        });
        document.addEventListener('click', function () {
            menu.classList.remove('is-open');
        });
    })();
</script>

</body>
</html>

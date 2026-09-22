<?php helper('admin'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akademus SIS | <?= esc($pageTitle ?? 'Admin Console') ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/tokens.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/base.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/base.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/admin-console.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/admin-console.css') ?>">
    <?= $this->renderSection('styles') ?>
</head>
<body class="ac-page">

<?php
$navItems = [
    'dashboard'            => ['label' => 'Overview',        'icon' => 'dashboard',      'url' => 'admin/dashboard'],
    'departments'          => ['label' => 'Colleges',        'icon' => 'hub',            'url' => 'admin/departments'],
    'programs'             => ['label' => 'Programs',        'icon' => 'workspace_premium', 'url' => 'admin/programs'],
    'curriculum'           => ['label' => 'Curricula',       'icon' => 'auto_stories',   'url' => 'admin/curriculum'],
    'curriculum-subjects'  => ['label' => 'Curriculum Subjects', 'icon' => 'library_books', 'url' => 'admin/curriculum-subjects'],
    'subjects'             => ['label' => 'Subjects',        'icon' => 'menu_book',      'url' => 'admin/subjects'],
    'students'             => ['label' => 'Student Records', 'icon' => 'school',         'url' => 'admin/students'],
    'users'                => ['label' => 'User Accounts',   'icon' => 'group',          'url' => 'admin/users'],
    'rooms'                => ['label' => 'Facilities',      'icon' => 'meeting_room',   'url' => 'admin/rooms'],
    'academic-years'       => ['label' => 'Academic Years',  'icon' => 'event',          'url' => 'admin/academic-years'],
    'grading-scales'       => ['label' => 'Grading Scales',  'icon' => 'grade',          'url' => 'admin/grading-scales'],
    'academic-settings'    => ['label' => 'Scheduling Rules', 'icon' => 'tune',          'url' => 'admin/academic-settings'],
    'roles'                => ['label' => 'Roles',           'icon' => 'admin_panel_settings', 'url' => 'admin/roles'],
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

    <nav class="ac-sidebar__nav" aria-label="Admin console">
        <?php foreach ($navItems as $key => $item): ?>
            <a href="<?= site_url($item['url']) ?>" class="ac-nav-item <?= $active === $key ? 'is-active' : '' ?>" title="<?= esc($item['label']) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>>
                <span class="material-symbols-outlined <?= $active === $key ? 'icon-fill' : '' ?>" aria-hidden="true"><?= $item['icon'] ?></span>
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
            <div class="ac-search" role="search">
                <span class="material-symbols-outlined ac-search__icon" aria-hidden="true">search</span>
                <label for="acSearchInput" class="sr-only">Search Akademus</label>
                <input class="ac-search__input" id="acSearchInput" placeholder="Search Akademus..." type="text">
            </div>

            <div class="ac-topbar__icons">
                <button type="button" class="ac-topbar__iconbtn" title="Notifications" aria-label="Notifications">
                    <span class="material-symbols-outlined" aria-hidden="true">notifications</span>
                    <span class="ac-topbar__dot"></span>
                </button>
                <button type="button" class="ac-topbar__iconbtn" title="Settings" aria-label="Settings">
                    <span class="material-symbols-outlined" aria-hidden="true">settings</span>
                </button>
                <button type="button" class="ac-topbar__iconbtn" title="Help" aria-label="Help">
                    <span class="material-symbols-outlined" aria-hidden="true">help_outline</span>
                </button>
            </div>

            <?= $this->renderSection('headerActions') ?>

            <div class="ac-user-menu" id="acUserMenu">
                <button type="button" class="ac-avatar ac-avatar--primary ac-user-menu__trigger" id="acUserMenuBtn" title="<?= esc($username) ?>" aria-haspopup="true" aria-expanded="false">
                    <?= esc($initial) ?>
                </button>
                <div class="ac-user-menu__dropdown" role="menu">
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

<div class="ac-toast-container" id="acToastContainer" role="status" aria-live="polite"></div>

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

    var acLastFocused = null;

    function acFocusablesIn(el) {
        return Array.prototype.slice.call(
            el.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
        ).filter(function (n) { return n.offsetParent !== null; });
    }

    function acOpenModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        acLastFocused = document.activeElement;
        modal.classList.add('is-open');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        var focusables = acFocusablesIn(modal);
        if (focusables.length) focusables[0].focus();
    }

    function acCloseModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('is-open');
        if (acLastFocused && typeof acLastFocused.focus === 'function') acLastFocused.focus();
    }

    // Escape closes the topmost open modal; Tab is trapped inside it.
    document.addEventListener('keydown', function (e) {
        var openModal = document.querySelector('.ac-modal.is-open');
        if (!openModal) return;

        if (e.key === 'Escape') {
            openModal.classList.remove('is-open');
            if (acLastFocused && typeof acLastFocused.focus === 'function') acLastFocused.focus();
            return;
        }

        if (e.key === 'Tab') {
            var focusables = acFocusablesIn(openModal);
            if (!focusables.length) return;
            var first = focusables[0];
            var last  = focusables[focusables.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    // Clicking the dimmed backdrop (not the panel itself) closes the modal.
    document.addEventListener('click', function (e) {
        if (e.target.classList && e.target.classList.contains('ac-modal') && e.target.classList.contains('is-open')) {
            e.target.classList.remove('is-open');
            if (acLastFocused && typeof acLastFocused.focus === 'function') acLastFocused.focus();
        }
    });

    (function () {
        var menu = document.getElementById('acUserMenu');
        var btn  = document.getElementById('acUserMenuBtn');
        if (!menu || !btn) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = menu.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function () {
            menu.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && menu.classList.contains('is-open')) {
                menu.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
                btn.focus();
            }
        });
    })();
</script>

</body>
</html>

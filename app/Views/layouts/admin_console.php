<?php helper('admin'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token-value" content="<?= esc(csrf_hash()) ?>">
    <title>Akademus SIS | <?= esc($pageTitle ?? $brandSub ?? 'Admin Console') ?></title>

    <script>
        // Every role's pages POST via fetch() repeatedly from a single page
        // load (schedule plotting, grading, sections, teaching-load claims,
        // swaps) rather than submitting a <form>. Re-enabling CSRF protection
        // app-wide (see app/Config/Filters.php) would reject every one of
        // those calls unless they carry the token — rather than editing every
        // fetch() call site individually, this patches window.fetch once so
        // every same-origin, state-changing request automatically carries it.
        // Must run before any page script below fires its own fetch() calls.
        (function () {
            var tokenValue = document.querySelector('meta[name="csrf-token-value"]').content;
            var originalFetch = window.fetch;

            window.fetch = function (input, init) {
                init = init || {};
                var method = (init.method || 'GET').toUpperCase();

                if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
                    var url = typeof input === 'string' ? input : input.url;
                    var isSameOrigin = url.indexOf('http://') !== 0 && url.indexOf('https://') !== 0
                        || url.indexOf(window.location.origin) === 0;

                    if (isSameOrigin) {
                        init.headers = new Headers(init.headers || {});
                        if (!init.headers.has('X-CSRF-TOKEN')) {
                            init.headers.set('X-CSRF-TOKEN', tokenValue);
                        }
                    }
                }

                return originalFetch.call(this, input, init);
            };

            // csrf_field()-rendered <form> posts still work unchanged (CI4
            // reads the same token from the POST body); this only needed to
            // cover the fetch() paths, which carried nothing before.
        })();
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/tokens.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/base.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/base.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/admin-console.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/admin-console.css') ?>">
    <?= $this->renderSection('styles') ?>
</head>
<body class="ac-page">

<?= $this->include('components/loading-splash') ?>

<?php
/**
 * Shared application shell for every role (admin, chairperson, faculty,
 * student). Admin pages rely on the defaults below unchanged; other roles
 * pass $navGroups/$brandSub/$userName/$userRoleLabel to reuse the same
 * sidebar/topbar chrome instead of duplicating it per page.
 */
$navGroups = $navGroups ?? [
    '' => [
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
    ],
];
$active        = $activeAdmin ?? '';
$brandSub      = $brandSub ?? 'Admin Console';
$username      = $userName ?? (auth()->user()->username ?? 'Administrator');
$userRoleLabel = $userRoleLabel ?? 'Administrator';
$logoutUrl     = $logoutUrl ?? '/logout';
$initial       = strtoupper(substr($username, 0, 1));
// Every role group (admin, student, faculty, chairperson) routes an
// identically-shaped notifications/mark-read/mark-all-read trio off its own
// prefix — so the bell can stay in this one shared layout instead of being
// wired per page, by reading which role prefix the current URL is under.
$notifRole = service('request')->getUri()->getSegment(1) ?: 'admin';
?>

<aside class="ac-sidebar">
    <div class="ac-sidebar__brand">
        <img src="<?= base_url('assets/images/logos/akademus.png') ?>" alt="Akademus" class="ac-sidebar__brand-logo">
        <div class="ac-sidebar__brand-text">
            <h1 class="ac-sidebar__brand-name">Akademus</h1>
            <p class="ac-sidebar__brand-sub"><?= esc($brandSub) ?></p>
        </div>
    </div>

    <nav class="ac-sidebar__nav" aria-label="<?= esc($brandSub) ?>">
        <?php foreach ($navGroups as $groupLabel => $navItems): ?>
            <?php if ($groupLabel !== ''): ?>
                <div class="ac-nav-group-label"><?= esc($groupLabel) ?></div>
            <?php endif ?>
            <?php foreach ($navItems as $key => $item): ?>
                <a href="<?= site_url($item['url']) ?>" class="ac-nav-item <?= $active === $key ? 'is-active' : '' ?>" title="<?= esc($item['label']) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>>
                    <span class="material-symbols-outlined <?= $active === $key ? 'icon-fill' : '' ?>" aria-hidden="true"><?= $item['icon'] ?></span>
                    <span class="ac-nav-label"><?= esc($item['label']) ?></span>
                </a>
            <?php endforeach ?>
        <?php endforeach ?>
    </nav>
</aside>

<main class="ac-main">
    <header class="ac-topbar">
        <?php $topbarLinks = $this->renderSection('topbarLinks'); ?>
        <?php if ($topbarLinks !== ''): ?>
            <nav class="ac-topbar__links"><?= $topbarLinks ?></nav>
        <?php endif ?>

        <div class="ac-topbar__actions">
            <div class="ac-search" role="search">
                <span class="material-symbols-outlined ac-search__icon" aria-hidden="true">search</span>
                <label for="acSearchInput" class="sr-only">Search Akademus</label>
                <input class="ac-search__input" id="acSearchInput" placeholder="Search Akademus..." type="text">
            </div>

            <div class="ac-topbar__icons">
                <div class="ac-notif" id="acNotif">
                    <button type="button" class="ac-topbar__iconbtn" id="acNotifBtn" title="Notifications" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
                        <span class="material-symbols-outlined" aria-hidden="true">notifications</span>
                        <span class="ac-topbar__dot" id="acNotifDot" style="display:none;"></span>
                    </button>
                    <div class="ac-notif__panel" id="acNotifPanel" role="menu">
                        <div class="ac-notif__head">
                            <strong>Notifications</strong>
                            <button type="button" id="acNotifMarkAll">Mark all read</button>
                        </div>
                        <div class="ac-notif__list" id="acNotifList">
                            <p class="ac-notif__empty">Loading…</p>
                        </div>
                    </div>
                </div>
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
                        <p class="ac-user-menu__role"><?= esc($userRoleLabel) ?></p>
                    </div>
                    <hr>
                    <a href="<?= site_url($logoutUrl) ?>" class="ac-user-menu__item ac-user-menu__item--danger">
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

<script>
    // Reusable sliding "active" indicator for segmented controls (a real
    // client-side tab/toggle group — List/Calendar, board tabs, day chips —
    // as opposed to sidebar links, which are full page navigations and
    // can't share this kind of continuity). Pages call
    // acInitSegmented(container, { indicatorClass, buttonSelector }) once
    // per group; the container just needs position:relative and an
    // ".active" class already on the initially-selected button.
    //
    // Defined in its OWN script block here, before renderSection('scripts')
    // — pages' own scripts (which call this) render before the layout's
    // main trailing <script> block, so defining it down there left
    // window.acInitSegmented undefined when callers actually ran, silently
    // skipping the indicator (no error — the `if (window.acInitSegmented)`
    // guard in callers just evaluated false).
    window.acInitSegmented = function (root, opts) {
        if (!root) return null;
        opts = opts || {};
        var buttonSelector = opts.buttonSelector || 'button';
        var indicatorClass = opts.indicatorClass || 'ac-segmented__indicator';

        var indicator = document.createElement('span');
        indicator.className = indicatorClass;
        indicator.setAttribute('aria-hidden', 'true');
        root.insertBefore(indicator, root.firstChild);

        function move(btn, animate) {
            if (!btn) return;
            if (!animate) indicator.style.transition = 'none';
            indicator.style.width = btn.offsetWidth + 'px';
            indicator.style.transform = 'translateX(' + btn.offsetLeft + 'px)';
            if (!animate) {
                // Force layout so the "no transition" jump is committed
                // before transitions are re-enabled on the next frame —
                // otherwise the very first move would also animate from 0.
                void indicator.offsetWidth;
                requestAnimationFrame(function () { indicator.style.transition = ''; });
            }
        }

        var initial = root.querySelector(buttonSelector + '.active') || root.querySelector(buttonSelector);
        move(initial, false);

        root.querySelectorAll(buttonSelector).forEach(function (btn) {
            btn.addEventListener('click', function () { move(btn, true); });
        });

        // The sidebar's hover-expand and other layout shifts can change
        // button positions without a click (e.g. the window resizing) —
        // keep the indicator honest without re-measuring on every frame.
        window.addEventListener('resize', function () {
            move(root.querySelector(buttonSelector + '.active') || root.querySelector(buttonSelector), false);
        });

        return { move: move };
    };
</script>

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

    // Notification bell — same wiring on every role (admin/student/faculty/
    // chairperson all route an identically-shaped notifications trio off
    // their own prefix; see $notifRole above).
    (function () {
        var wrap  = document.getElementById('acNotif');
        var btn   = document.getElementById('acNotifBtn');
        var panel = document.getElementById('acNotifPanel');
        var dot   = document.getElementById('acNotifDot');
        var list  = document.getElementById('acNotifList');
        var markAllBtn = document.getElementById('acNotifMarkAll');
        if (!wrap || !btn) return;

        var indexUrl    = <?= json_encode(site_url($notifRole . '/notifications')) ?>;
        var markReadUrl = <?= json_encode(site_url($notifRole . '/notifications/mark-read')) ?>;
        var markAllUrl  = <?= json_encode(site_url($notifRole . '/notifications/mark-all-read')) ?>;

        function renderList(notifications) {
            list.innerHTML = '';
            if (!notifications || notifications.length === 0) {
                list.innerHTML = '<p class="ac-notif__empty">No notifications yet.</p>';
                return;
            }
            notifications.forEach(function (n) {
                var item = document.createElement('div');
                item.className = 'ac-notif__item' + (Number(n.is_read) === 0 ? ' is-unread' : '');
                item.textContent = n.message;
                item.addEventListener('click', function () {
                    fetch(markReadUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ id: n.id }),
                    }).finally(function () {
                        if (n.link) window.location.href = n.link;
                        else refreshUnread();
                    });
                });
                list.appendChild(item);
            });
        }

        function loadList() {
            list.innerHTML = '<p class="ac-notif__empty">Loading…</p>';
            fetch(indexUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    renderList(data.notifications);
                    setDot(data.unread || 0);
                })
                .catch(function () {
                    list.innerHTML = '<p class="ac-notif__empty">Could not load notifications.</p>';
                });
        }

        function setDot(unread) {
            dot.style.display = unread > 0 ? 'block' : 'none';
        }

        function refreshUnread() {
            fetch(indexUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) { setDot(data.unread || 0); })
                .catch(function () {});
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = wrap.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) loadList();
        });
        document.addEventListener('click', function () {
            wrap.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && wrap.classList.contains('is-open')) {
                wrap.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
                btn.focus();
            }
        });
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                fetch(markAllUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                }).finally(loadList).finally(function () { setDot(0); });
            });
        }

        refreshUnread();
        setInterval(refreshUnread, 30000);
    })();
</script>

</body>
</html>

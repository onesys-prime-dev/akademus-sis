<?php helper('admin'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token-value" content="<?= esc(csrf_hash()) ?>">
    <title>Akademus SIS | <?= esc($pageTitle ?? $brandSub ?? 'Admin Console') ?></title>

    <script>
        // Escapes a value for use inside an innerHTML template (text or a
        // quoted attribute). Anything user-entered — names, subject titles,
        // codes — must go through this before being interpolated into markup.
        window.escapeHtml = function (value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };
    </script>

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
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/components.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/components.css') ?>">
    <script src="<?= base_url('assets/js/session-guard.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/session-guard.js') ?>" data-login-url="<?= esc(site_url('login'), 'attr') ?>"></script>
    <script defer src="<?= base_url('assets/js/interface.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/interface.js') ?>"></script>
    <script src="<?= base_url('assets/js/unsaved-changes.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/unsaved-changes.js') ?>"></script>
</head>
<body class="ac-page">

<a class="ac-skip-link" href="#mainContent">Skip to content</a>

<?php
/**
 * Shared application shell for every role (admin, chairperson, faculty,
 * student). Admin pages rely on the defaults below unchanged; other roles
 * pass $navGroups/$brandSub/$userName/$userRoleLabel to reuse the same
 * sidebar/topbar chrome instead of duplicating it per page.
 */
$navGroups = $navGroups ?? [
    'Workspace' => [
        'dashboard'            => ['label' => 'Overview',        'icon' => 'dashboard',      'url' => 'admin/dashboard'],
    ],
    'Academics' => [
        'departments'          => ['label' => 'Colleges',        'icon' => 'hub',            'url' => 'admin/departments'],
        'programs'             => ['label' => 'Programs',        'icon' => 'workspace_premium', 'url' => 'admin/programs'],
        'curriculum'           => ['label' => 'Curricula',       'icon' => 'auto_stories',   'url' => 'admin/curriculum'],
        'curriculum-subjects'  => ['label' => 'Curriculum Subjects', 'icon' => 'library_books', 'url' => 'admin/curriculum-subjects'],
        'subjects'             => ['label' => 'Subjects',        'icon' => 'menu_book',      'url' => 'admin/subjects'],
    ],
    'People' => [
        'students'             => ['label' => 'Student Records', 'icon' => 'school',         'url' => 'admin/students'],
        'users'                => ['label' => 'User Accounts',   'icon' => 'group',          'url' => 'admin/users'],
    ],
    'Finance' => [
        'tuition-rates'        => ['label' => 'Tuition Rates',   'icon' => 'payments',       'url' => 'admin/tuition-rates'],
        'fee-types'            => ['label' => 'Fee Types',       'icon' => 'sell',           'url' => 'admin/fee-types'],
        'payment-proofs'       => ['label' => 'Payment Proofs',  'icon' => 'receipt_long',   'url' => 'admin/payment-proofs'],
    ],
    'Administration' => [
        'rooms'                => ['label' => 'Facilities',      'icon' => 'meeting_room',   'url' => 'admin/rooms'],
        'academic-years'       => ['label' => 'Academic Years',  'icon' => 'event',          'url' => 'admin/academic-years'],
        'grading-scales'       => ['label' => 'Grading Scales',  'icon' => 'grade',          'url' => 'admin/grading-scales'],
        'academic-settings'    => ['label' => 'Scheduling Rules', 'icon' => 'tune',          'url' => 'admin/academic-settings'],
        'roles'                => ['label' => 'Roles',           'icon' => 'admin_panel_settings', 'url' => 'admin/roles'],
        'audit-logs'           => ['label' => 'Audit Logs',      'icon' => 'history',        'url' => 'admin/audit-logs'],
        'academic-health'      => ['label' => 'Academic Health', 'icon' => 'health_and_safety', 'url' => 'admin/academic-health'],
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

// Forced first-login password change (see AccountController::setTempPassword
// and StudentModel::promoteFromApplicant). Checked via a raw column read,
// same convention used elsewhere in this layout/controllers for custom
// users columns Shield's own entity doesn't expose.
$mustChangePassword = auth()->loggedIn()
    ? (bool) (db_connect()->table('users')->select('must_change_password')->where('id', auth()->id())->get()->getRowArray()['must_change_password'] ?? 0)
    : false;
?>

<button class="ac-nav-backdrop" type="button" aria-label="Close navigation" hidden></button>
<aside class="ac-sidebar" id="acSidebar">
    <button class="ac-nav-close ac-topbar__iconbtn" type="button" aria-label="Close navigation"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
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
        <button class="ac-nav-toggle ac-topbar__iconbtn" type="button" aria-label="Open navigation" aria-controls="acSidebar" aria-expanded="false"><span class="material-symbols-outlined" aria-hidden="true">menu</span></button>
        <?php $topbarLinks = $this->renderSection('topbarLinks'); ?>
        <?php if ($topbarLinks !== ''): ?>
            <nav class="ac-topbar__links"><?= $topbarLinks ?></nav>
        <?php endif ?>

        <div class="ac-topbar__actions">
            <div class="ac-search" role="search">
                <span class="material-symbols-outlined ac-search__icon" aria-hidden="true">search</span>
                <label for="acSearchInput" class="sr-only">Find a page</label>
                <input class="ac-search__input" id="acSearchInput" placeholder="Find a page..." type="search" autocomplete="off">
            </div>

            <div class="ac-topbar__icons">
                <div class="ac-notif" id="acNotif">
                    <button type="button" class="ac-topbar__iconbtn" id="acNotifBtn" title="Notifications" aria-label="Notifications" aria-controls="acNotifPanel" aria-expanded="false">
                        <span class="material-symbols-outlined ac-bell" id="acBellSvg" aria-hidden="true">notifications</span>
                        <span class="ac-topbar__dot" id="acNotifDot" style="display:none;"></span>
                    </button>
                    <div class="ac-notif__panel" id="acNotifPanel" role="region" aria-label="Notifications">
                        <div class="ac-notif__head">
                            <strong>Notifications</strong>
                            <button type="button" id="acNotifMarkAll">Mark all read</button>
                        </div>
                        <div class="ac-notif__list" id="acNotifList">
                            <p class="ac-notif__empty">Loading…</p>
                        </div>
                    </div>
                </div>
                <button type="button" class="ac-topbar__iconbtn" title="Help" aria-label="Help" id="acHelpBtn">
                    <span class="material-symbols-outlined" aria-hidden="true">help_outline</span>
                </button>
            </div>

            <?= $this->renderSection('headerActions') ?>

            <div class="ac-user-menu" id="acUserMenu">
                <button type="button" class="ac-avatar ac-avatar--primary ac-user-menu__trigger" id="acUserMenuBtn" aria-label="Account menu for <?= esc($username) ?>" title="<?= esc($username) ?>" aria-haspopup="true" aria-expanded="false">
                    <?= esc($initial) ?>
                </button>
                <div class="ac-user-menu__dropdown">
                    <div class="ac-user-menu__who">
                        <span class="ac-avatar ac-avatar--primary ac-user-menu__who-avatar"><?= esc($initial) ?></span>
                        <div class="ac-user-menu__who-text">
                            <p class="ac-user-menu__name"><?= esc($username) ?></p>
                            <span class="ac-user-menu__role"><?= esc($userRoleLabel) ?></span>
                        </div>
                    </div>
                    <hr>
                    <a href="<?= site_url($notifRole === 'admin' ? 'admin/academic-settings' : $notifRole . '/profile') ?>" class="ac-user-menu__item">
                        <span class="ac-user-menu__item-icon material-symbols-outlined"><?= $notifRole === 'admin' ? 'settings' : 'person' ?></span>
                        <span><?= $notifRole === 'admin' ? 'Academic Settings' : 'Profile' ?></span>
                    </a>
                    <hr>
                    <a href="<?= site_url($logoutUrl) ?>" class="ac-user-menu__item ac-user-menu__item--danger">
                        <span class="ac-user-menu__item-icon material-symbols-outlined">logout</span>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="ac-content" id="mainContent" tabindex="-1">
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
            // Match the button's real rendered box on BOTH axes, measured
            // directly, instead of hand-tuned CSS top/bottom insets trying
            // to guess it from the container's padding — those drifted out
            // of sync with the button's actual height/position (and, in
            // teaching_loads specifically, clipped against the parent
            // .panel's own rounded corner + overflow:hidden). This is the
            // one source of truth every consumer of acInitSegmented gets
            // for free, no per-page CSS tuning required.
            indicator.style.width = btn.offsetWidth + 'px';
            indicator.style.height = btn.offsetHeight + 'px';
            indicator.style.top = '0';
            indicator.style.bottom = 'auto';
            indicator.style.transform = 'translate(' + btn.offsetLeft + 'px, ' + btn.offsetTop + 'px)';
            if (!animate) {
                // Force layout so the "no transition" jump is committed
                // before transitions are re-enabled on the next frame —
                // otherwise the very first move would also animate from 0.
                void indicator.offsetWidth;
                requestAnimationFrame(function () { indicator.style.transition = ''; });
            }
        }

        function moveToActive(animate) {
            move(root.querySelector(buttonSelector + '.active') || root.querySelector(buttonSelector), animate);
        }

        moveToActive(false);

        root.querySelectorAll(buttonSelector).forEach(function (btn) {
            btn.addEventListener('click', function () { move(btn, true); });
        });

        // The sidebar's hover-expand and other layout shifts can change
        // button positions without a click (e.g. the window resizing) —
        // keep the indicator honest without re-measuring on every frame.
        window.addEventListener('resize', function () { moveToActive(false); });

        // This script runs as soon as the HTML is parsed, which can be
        // before the Inter webfont has actually swapped in — the initial
        // measurement above may capture the button at its (narrower)
        // fallback-font width, leaving the indicator too small once Inter
        // loads and the text reflows wider. Re-sync once fonts are
        // confirmed ready (font-display swaps don't fire resize/load).
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () { moveToActive(false); });
        }

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

    // Scroll-lock while any modal is open (either the shared .ac-modal
    // pattern here, or the page-local .modal-backdrop pattern used by
    // teaching_loads/curriculum/schedule_plotting) — covers both without
    // any page needing its own code. Without this, the page behind a
    // modal can still scroll, and on Windows the native scrollbar renders
    // as its own compositor overlay that ignores the dimmed backdrop
    // entirely, leaving a bright sliver floating on top of it.
    (function () {
        function anyModalOpen() {
            return !!document.querySelector('.ac-modal.is-open, .modal-backdrop.open');
        }
        function syncScrollLock() {
            document.body.classList.toggle('ac-scroll-locked', anyModalOpen());
        }
        new MutationObserver(syncScrollLock).observe(document.body, {
            attributes: true, attributeFilter: ['class'], subtree: true,
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
        var title = modal.querySelector('.ac-modal__title');
        if (title) { title.id = title.id || id + '-title'; modal.setAttribute('aria-labelledby', title.id); }
        var focusables = acFocusablesIn(modal);
        if (focusables.length) focusables[0].focus();
    }

    // The actual DOM close, bypassing the unsaved-changes check — used once
    // the user has confirmed (or there was nothing to confirm).
    function acDoCloseModal(modal) {
        modal.classList.remove('is-open');
        if (acLastFocused && typeof acLastFocused.focus === 'function') acLastFocused.focus();
    }

    function acCloseModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        if (window.UnsavedChanges) {
            window.UnsavedChanges.guard(function () { acDoCloseModal(modal); });
        } else {
            acDoCloseModal(modal);
        }
    }

    // Escape closes the topmost open modal; Tab is trapped inside it.
    document.addEventListener('keydown', function (e) {
        // The unsaved-changes confirm dialog (if showing) owns Escape/Tab
        // while it's up — its own listeners handle this keypress instead.
        if (document.querySelector('.uc-modal.is-open')) return;

        var openModal = document.querySelector('.ac-modal.is-open');
        if (!openModal) return;

        if (e.key === 'Escape') {
            if (window.UnsavedChanges) {
                window.UnsavedChanges.guard(function () { acDoCloseModal(openModal); });
            } else {
                acDoCloseModal(openModal);
            }
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
        if (document.querySelector('.uc-modal.is-open')) return;
        if (e.target.classList && e.target.classList.contains('ac-modal') && e.target.classList.contains('is-open')) {
            var modal = e.target;
            if (window.UnsavedChanges) {
                window.UnsavedChanges.guard(function () { acDoCloseModal(modal); });
            } else {
                acDoCloseModal(modal);
            }
        }
    });

    // Suspend transitions while the window is being resized (see components.css).
    (function () {
        var root = document.documentElement, timer;
        window.addEventListener('resize', function () {
            root.classList.add('is-resizing');
            clearTimeout(timer);
            timer = setTimeout(function () { root.classList.remove('is-resizing'); }, 150);
        });
    })();

    (function () {
        var menu = document.getElementById('acUserMenu');
        var btn  = document.getElementById('acUserMenuBtn');
        if (!menu || !btn) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = menu.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) document.dispatchEvent(new CustomEvent('ac:topbar-popover', { detail: menu }));
        });
        // Only one topbar popover (account menu / notifications) open at a time.
        document.addEventListener('ac:topbar-popover', function (e) {
            if (e.detail === menu) return;
            menu.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
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
        var bell  = document.getElementById('acBellSvg');
        var markAllBtn = document.getElementById('acNotifMarkAll');
        if (!wrap || !btn) return;
        panel.addEventListener('click', function (e) { e.stopPropagation(); });

        var indexUrl    = <?= json_encode(site_url($notifRole . '/notifications')) ?>;
        var markReadUrl = <?= json_encode(site_url($notifRole . '/notifications/mark-read')) ?>;
        var markAllUrl  = <?= json_encode(site_url($notifRole . '/notifications/mark-all-read')) ?>;

        function renderList(notifications) {
            list.innerHTML = '';
            if (!notifications || notifications.length === 0) {
                list.innerHTML = '<p class="ac-notif__empty">You’re all caught up.<br>New updates will appear here.</p>';
                return;
            }
            notifications.forEach(function (n) {
                var item = document.createElement('button');
                item.type = 'button';
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
            list.innerHTML = '<div class="ac-notif__loading" role="status"><span class="sr-only">Loading notifications</span><span></span><span></span><span></span></div>';
            fetch(indexUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    renderList(data.notifications);
                    setDot(data.unread || 0);
                })
                .catch(function () {
                    list.innerHTML = '<p class="ac-notif__empty">Notifications couldn’t load.</p>';
                    var retry = document.createElement('button'); retry.type = 'button'; retry.className = 'ac-btn ac-btn-secondary'; retry.textContent = 'Try again'; retry.addEventListener('click', loadList); list.appendChild(retry);
                });
        }

        function setDot(unread) {
            dot.style.display = unread > 0 ? 'flex' : 'none';
            dot.textContent = unread > 99 ? '99+' : (unread > 0 ? String(unread) : '');
            if (bell) bell.classList.toggle('is-ringing', unread > 0);
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
            if (open) {
                document.dispatchEvent(new CustomEvent('ac:topbar-popover', { detail: wrap }));
                loadList();
            }
        });
        document.addEventListener('ac:topbar-popover', function (e) {
            if (e.detail === wrap) return;
            wrap.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
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

<?php if ($mustChangePassword): ?>
<!-- Forced password change: temp password from the welcome email must be replaced before anything else is usable. -->
<div id="tempPwModal" style="position:fixed; inset:0; z-index:99999; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,.55); backdrop-filter:blur(3px);">
    <div style="background:#fff; border-radius:12px; padding:28px; width:100%; max-width:380px; box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <h3 style="margin:0 0 6px; font-size:17px;">Set Your Password</h3>
        <p style="margin:0 0 18px; font-size:13px; color:var(--color-text-secondary);">You're signed in with a temporary password. Please set a new one to continue.</p>
        <div class="ac-field" style="margin-bottom:12px;">
            <label>New Password</label>
            <input type="password" id="tempPwNew" class="ac-input" placeholder="At least 8 characters">
        </div>
        <div class="ac-field" style="margin-bottom:6px;">
            <label>Confirm Password</label>
            <input type="password" id="tempPwConfirm" class="ac-input" placeholder="Re-enter your password">
        </div>
        <p id="tempPwError" style="display:none; color:var(--color-danger,#dc2626); font-size:12.5px; margin:6px 0 0;"></p>
        <button type="button" id="tempPwBtn" class="ac-btn ac-btn-primary" style="width:100%; margin-top:14px;" onclick="tempPwSubmit()">Save Password</button>
    </div>
</div>
<script>
    async function tempPwSubmit() {
        var btn     = document.getElementById('tempPwBtn');
        var errorEl = document.getElementById('tempPwError');
        var newPw   = document.getElementById('tempPwNew').value;
        var confirm = document.getElementById('tempPwConfirm').value;

        errorEl.style.display = 'none';

        if (newPw.length < 8) {
            errorEl.textContent = 'Password must be at least 8 characters.';
            errorEl.style.display = 'block';
            return;
        }

        if (newPw !== confirm) {
            errorEl.textContent = 'Passwords do not match.';
            errorEl.style.display = 'block';
            return;
        }

        btn.disabled = true;

        var form = new FormData();
        form.append('new_password', newPw);
        form.append('confirm_password', confirm);

        try {
            var res  = await fetch('<?= site_url('/account/set-temp-password') ?>', { method: 'POST', body: form });
            var data = await res.json();

            if (data.success) {
                window.location.reload();
                return;
            }

            errorEl.textContent = data.message || 'Could not save password.';
            errorEl.style.display = 'block';
        } catch (e) {
            errorEl.textContent = 'Network error — please try again.';
            errorEl.style.display = 'block';
        }

        btn.disabled = false;
    }
</script>
<?php endif ?>

</body>
</html>

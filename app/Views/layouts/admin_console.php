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

    <script>
        // data-confirm="..." on a plain <a>/<button> OUTSIDE any <form> (a
        // form's own data-confirm is already handled by interface.js's own
        // document-level 'submit' listener — duplicating that here caused
        // the two listeners to fight over the same submit event, each
        // re-triggering the other via requestSubmit() in an endless
        // confirm/resubmit loop that silently never reached the server).
        //
        // Uses the same styled acConfirm() modal every other confirmation
        // in this app uses (interface.js) instead of the browser's own
        // window.confirm(). interface.js loads deferred, so acConfirm may
        // not exist yet at the moment this file is merely parsed — checked
        // with typeof inside the handler (which only ever runs later, in
        // response to a real user action, by which point it's long loaded),
        // never at registration time. Falls back to window.confirm() if
        // acConfirm somehow still isn't available.
        function acRunConfirm(message) {
            return typeof window.acConfirm === 'function'
                ? window.acConfirm(message)
                : Promise.resolve(window.confirm(message));
        }

        document.addEventListener('click', function (e) {
            var el = e.target.closest('[data-confirm]');
            if (!el || el.closest('form')) return; // forms are handled by the submit listener above
            if (el.dataset.acConfirmBypass === '1') { delete el.dataset.acConfirmBypass; return; }

            var message = el.getAttribute('data-confirm');
            if (!message) return;

            e.preventDefault();
            e.stopImmediatePropagation();

            acRunConfirm(message).then(function (ok) {
                if (!ok) return;
                el.dataset.acConfirmBypass = '1';
                el.click();
            });
        }, true);
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
    <script defer src="<?= base_url('assets/js/connection-guard.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/connection-guard.js') ?>"></script>
    <script src="<?= base_url('assets/js/custom-select.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/custom-select.js') ?>"></script>
</head>
<body class="ac-page">

<div class="ac-progress-bar" id="acProgressBar" aria-hidden="true"></div>
<script>
    // A thin top-loading bar for full-page navigations — this app is a
    // traditional server-rendered MPA (every sidebar/topbar link is a real
    // <a href>, no client-side router), so there's no "old content" to keep
    // showing while the next page loads like an SPA would have. A skeleton
    // screen doesn't fit that model (you'd need to know the next page's
    // layout in advance); a sweep-in progress bar gives the same "something
    // is happening" feedback without faking content.
    (function () {
        var bar = document.getElementById('acProgressBar');
        var hideTimer = null;

        function start() {
            clearTimeout(hideTimer);
            bar.style.transition = 'none';
            bar.style.width = '0%';
            void bar.offsetWidth; // force reflow so the reset above actually applies before re-enabling the transition
            bar.style.transition = '';
            bar.classList.add('is-active');
            requestAnimationFrame(function () { bar.style.width = '72%'; });
        }

        function finish() {
            if (!bar.classList.contains('is-active')) return; // nothing in flight — avoid a flash on a page's own first load
            bar.style.width = '100%';
            hideTimer = setTimeout(function () {
                bar.classList.remove('is-active');
                bar.style.width = '0%';
            }, 200);
        }

        // Fires on every normal load AND on back/forward-cache restores, so
        // a bar left mid-animation when the user navigated away gets
        // cleaned up correctly either way.
        addEventListener('pageshow', finish);

        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[href]');
            if (!link || event.button !== 0) return;
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (link.target && link.target !== '_self') return;
            if (link.hasAttribute('download')) return;
            var href = link.getAttribute('href') || '';
            if (href === '' || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) return;
            try {
                var url = new URL(link.href, location.href);
                if (url.origin !== location.origin) return;
                if (url.pathname === location.pathname && url.search === location.search && url.hash) return; // pure in-page anchor jump
            } catch (e) { return; }
            // Deferred, not checked inline: this listener runs on document in
            // the CAPTURE phase, which fires before the link's own handlers
            // (confirm() dialogs, SPA-style interception) get a chance to
            // call preventDefault() during the target/bubble phase. Checking
            // event.defaultPrevented here would always see it as false even
            // when the click is about to be cancelled, leaving the bar
            // started with no matching finish() — a setTimeout(0) waits
            // until the event has fully finished dispatching first.
            setTimeout(function () { if (!event.defaultPrevented) start(); }, 0);
        }, true);

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (form.target && form.target !== '_self') return;
            setTimeout(function () { if (!event.defaultPrevented) start(); }, 0);
        }, true);
    })();
</script>

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
        'dashboard'            => ['label' => 'Overview',        'icon' => 'dashboard',      'url' => 'admin/dashboard', 'help' => [
            'title' => 'Admin Overview',
            'text'  => "A system-wide snapshot: enrollment counts, revenue collected, and anything flagged for your attention across every portal.",
        ]],
    ],
    'Academics' => [
        'departments'          => ['label' => 'Colleges',        'icon' => 'hub',            'url' => 'admin/departments', 'help' => [
            'title' => 'Colleges',
            'text'  => "The institution's colleges and their departments (sub-departments) — the structure chairpersons, deans, and subject ownership are all organized under.",
        ]],
        'programs'             => ['label' => 'Programs',        'icon' => 'workspace_premium', 'url' => 'admin/programs', 'help' => [
            'title' => 'Programs',
            'text'  => "The degree programs offered, each assigned to a department. A program needs a published curriculum (see Curricula) before students on it can be scheduled or enrolled.",
        ]],
        'curriculum'           => ['label' => 'Curricula',       'icon' => 'auto_stories',   'url' => 'admin/curriculum', 'help' => [
            'title' => 'Curricula',
            'text'  => "Each program's curriculum versions — which subjects belong to which year/term. There's one live curriculum per program; no draft/versioning workflow, so changes here take effect immediately.",
        ]],
        'curriculum-subjects'  => ['label' => 'Curriculum Subjects', 'icon' => 'library_books', 'url' => 'admin/curriculum-subjects', 'help' => [
            'title' => 'Curriculum Subjects',
            'text'  => "Assign subjects to a specific slot (year level + term) within a program's curriculum.",
        ]],
        'subjects'             => ['label' => 'Subjects',        'icon' => 'menu_book',      'url' => 'admin/subjects', 'help' => [
            'title' => 'Subjects',
            'text'  => "The institution's full subject catalog — code, title, units, and which department owns each one. Ownership here is what Subject Ownership and the shared-subject workflow read from.",
        ]],
    ],
    'People' => [
        'students'             => ['label' => 'Student Records', 'icon' => 'school',         'url' => 'admin/students', 'help' => [
            'title' => 'Student Records',
            'text'  => "Search and manage every student's record: enrollment, curriculum, status, and tuition — the same administrative view available to the registrar.",
        ]],
        'users'                => ['label' => 'User Accounts',   'icon' => 'group',          'url' => 'admin/users', 'help' => [
            'title' => 'User Accounts',
            'text'  => "Create and manage every account in the system, assign roles/groups, and reset a user's password. A password reset notifies the account holder.",
        ]],
    ],
    'Finance' => [
        'tuition-rates'        => ['label' => 'Tuition Rates',   'icon' => 'payments',       'url' => 'admin/tuition-rates', 'help' => [
            'title' => 'Tuition Rates',
            'text'  => "The per-unit tuition rate for each program/academic year, used to compute every student's assessment automatically on enrollment.",
        ]],
        'fee-types'            => ['label' => 'Fee Types',       'icon' => 'sell',           'url' => 'admin/fee-types', 'help' => [
            'title' => 'Fee Types',
            'text'  => "The itemized fee lines (Miscellaneous, Laboratory, etc.) added on top of per-unit tuition in a student's assessment.",
        ]],
        'payment-proofs'       => ['label' => 'Payment Proofs',  'icon' => 'receipt_long',   'url' => 'admin/payment-proofs', 'help' => [
            'title' => 'Payment Proofs',
            'text'  => "Review proof of payment students upload for off-site payments. Approving one records it as a real payment on their ledger.",
        ]],
    ],
    'Administration' => [
        'rooms'                => ['label' => 'Facilities',      'icon' => 'meeting_room',   'url' => 'admin/rooms', 'help' => [
            'title' => 'Facilities',
            'text'  => "The rooms available for scheduling classes into — what chairpersons pick from when plotting a section's meeting times.",
        ]],
        'academic-years'       => ['label' => 'Academic Years',  'icon' => 'event',          'url' => 'admin/academic-years', 'help' => [
            'title' => 'Academic Years',
            'text'  => "Set the active academic year/term and the enrollment, grading, and enlistment windows that gate what students and faculty can do across the whole system.",
        ]],
        'grading-scales'       => ['label' => 'Grading Scales',  'icon' => 'grade',          'url' => 'admin/grading-scales', 'help' => [
            'title' => 'Grading Scales',
            'text'  => "Define the grade values (e.g. 1.00–5.00, or letter grades) and which ones count as passing — what faculty pick from when entering a grade.",
        ]],
        'academic-settings'    => ['label' => 'Scheduling Rules', 'icon' => 'tune',          'url' => 'admin/academic-settings', 'help' => [
            'title' => 'Scheduling Rules',
            'text'  => "System-wide scheduling limits, like the maximum teaching load per faculty member — read by the plotting and swap-approval flows so a chairperson can't exceed them.",
        ]],
        'roles'                => ['label' => 'Roles',           'icon' => 'admin_panel_settings', 'url' => 'admin/roles', 'help' => [
            'title' => 'Roles',
            'text'  => "The permission groups (admin, registrar, dean, chairperson, faculty, finance, student) and what each one can access.",
        ]],
        'audit-logs'           => ['label' => 'Audit Logs',      'icon' => 'history',        'url' => 'admin/audit-logs', 'help' => [
            'title' => 'Audit Logs',
            'text'  => "A searchable trail of sensitive actions across the system — grade changes, status changes, overrides — with who did it, when, and why.",
        ]],
        'academic-health'      => ['label' => 'Academic Health', 'icon' => 'health_and_safety', 'url' => 'admin/academic-health', 'help' => [
            'title' => 'Academic Health',
            'text'  => "System-wide data-integrity checks — students missing a curriculum, programs with no tuition rate set, and similar gaps worth fixing before they cause problems downstream.",
        ]],
    ],
];

$active        = $activeAdmin ?? '';
$brandSub      = $brandSub ?? 'Admin Console';
$username      = $userName ?? (auth()->user()->username ?? 'Administrator');
$userRoleLabel = $userRoleLabel ?? 'Administrator';
$logoutUrl     = $logoutUrl ?? '/logout';
$initial       = strtoupper(substr($username, 0, 1));
$avatarColor   = avatar_color($username);
$avatarPhotoUrl = null;
if (auth()->loggedIn()) {
    $photoMime      = db_connect()->table('users')->select('photo_mime')->where('id', auth()->id())->get()->getRowArray()['photo_mime'] ?? null;
    $avatarPhotoUrl = avatar_photo_url((int) auth()->id(), $photoMime);
}
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

// The "?" help button's content: looked up from the current page's own
// $navGroups entry (every portal's nav array — see the default below,
// Finance/Registrar/DeanPortal::shell(), chairperson/_nav.php — can carry
// an optional 'help' => ['title' => ..., 'text' => ...] on each item) so
// it's specific to whatever page you're actually on, instead of one fixed
// blurb everywhere. Falls back to a role-aware generic when a page hasn't
// defined one yet.
$pageHelp = null;
foreach ($navGroups as $navItems) {
    if (isset($navItems[$active]['help'])) {
        $pageHelp = $navItems[$active]['help'];
        break;
    }
}
// Profile is in no sidebar — every portal reaches it from the account menu
// in the top-right — so there is no nav entry to carry its help text, and
// all six portals' profile pages are the same kind of page. One shared entry
// covers them rather than six copies of the same paragraph.
if ($pageHelp === null && $active === 'profile') {
    $pageHelp = [
        'title' => 'Profile',
        'text'  => "Your account details: display name, email, and password. You reach this page from the account menu in the top-right corner, not the sidebar.\n\nA password change applies from your next sign-in. For a role change or a correction to your official record, contact your institution's administrator — those aren't self-service here.",
    ];
}
$pageHelp ??= [
    'title' => 'Using the ' . $brandSub,
    'text'  => "Use the sidebar to move between the {$brandSub}'s tools. On smaller screens, open it with the menu button.\n\nFind a page searches the navigation available to your role.\n\nFor account access or record corrections, contact your institution's administrator.",
];
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
                    <?php if (! empty($item['badge'])): ?>
                        <span class="ac-nav-badge"><?= (int) $item['badge'] > 99 ? '99+' : (int) $item['badge'] ?></span>
                    <?php endif ?>
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
                <script type="application/json" id="acHelpConfig"><?= json_encode($pageHelp, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
            </div>

            <?= $this->renderSection('headerActions') ?>

            <div class="ac-user-menu" id="acUserMenu">
                <button type="button" class="ac-avatar ac-user-menu__trigger" id="acUserMenuBtn" style="<?= $avatarPhotoUrl === null ? 'background:' . esc($avatarColor, 'attr') . ';color:#fff;' : '' ?>" aria-label="Account menu for <?= esc($username) ?>" title="<?= esc($username) ?>" aria-haspopup="true" aria-expanded="false">
                    <?php if ($avatarPhotoUrl !== null): ?>
                        <img src="<?= esc($avatarPhotoUrl) ?>" alt="">
                    <?php else: ?>
                        <?= esc($initial) ?>
                    <?php endif ?>
                </button>
                <div class="ac-user-menu__dropdown">
                    <div class="ac-user-menu__who">
                        <span class="ac-avatar ac-user-menu__who-avatar" style="<?= $avatarPhotoUrl === null ? 'background:' . esc($avatarColor, 'attr') . ';color:#fff;' : '' ?>">
                            <?php if ($avatarPhotoUrl !== null): ?>
                                <img src="<?= esc($avatarPhotoUrl) ?>" alt="">
                            <?php else: ?>
                                <?= esc($initial) ?>
                            <?php endif ?>
                        </span>
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
    // per group; the container just needs position:relative, and the
    // initially-selected button should carry either an ".active" class or
    // aria-pressed="true".
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
            // Prefer an explicit ".active" class; otherwise use the button that
            // announces itself as selected via aria-pressed (the schedule view
            // toggle and day chips use that instead of a class), and only then
            // fall back to the first button. Without the aria-pressed step the
            // pill sat on whichever button happened to be first after a resize
            // or the webfont swap — e.g. on a phone the agenda view showed with
            // the pill stuck under "Day".
            move(root.querySelector(buttonSelector + '.active')
                || root.querySelector(buttonSelector + '[aria-pressed="true"]')
                || root.querySelector(buttonSelector), animate);
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

<!-- Shared in-app file preview modal — any staff view can open it via
     acOpenFileModal(url, title) instead of linking straight to a raw file
     in a new tab. The url must be a route that serves the file INLINE by
     default and supports a `?download=1` query string for the Download
     button (see StudentController::viewProof() for the established
     convention this follows). -->
<div class="ac-modal ac-file-modal" id="acFileModal">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title" id="acFileModalTitle">Document preview</div>
            </div>
            <button type="button" onclick="acCloseModal('acFileModal')" class="ac-modal__close">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <div class="ac-modal__body">
            <iframe id="acFileModalFrame" class="ac-file-frame" title="Document preview"></iframe>
            <div class="ac-modal__actions">
                <a id="acFileModalDownload" href="#" class="ac-btn ac-btn-secondary"><span class="material-symbols-outlined" aria-hidden="true">download</span>Download</a>
                <button type="button" class="ac-btn ac-btn-primary" onclick="acPrintFileModal()"><span class="material-symbols-outlined" aria-hidden="true">print</span>Print</button>
            </div>
        </div>
    </div>
</div>

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

    // Opens #acFileModal with the given file loaded inline — see the
    // markup's own comment above for the url convention it expects.
    function acOpenFileModal(url, title) {
        var titleEl = document.getElementById('acFileModalTitle');
        titleEl.textContent = title;
        titleEl.title = title;
        document.getElementById('acFileModalFrame').src = url;
        document.getElementById('acFileModalDownload').href = url + (url.indexOf('?') === -1 ? '?' : '&') + 'download=1';
        acOpenModal('acFileModal');
    }

    function acPrintFileModal() {
        var frame = document.getElementById('acFileModalFrame');
        if (frame && frame.contentWindow) {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        }
    }

    // The iframe keeps loading/playing the file in the background otherwise
    // (and a PDF viewer can keep making network requests) even after the
    // modal visually closes — clear its src once it's actually hidden.
    (function () {
        var fileModal = document.getElementById('acFileModal');
        if (!fileModal) return;
        new MutationObserver(function () {
            if (!fileModal.classList.contains('is-open')) {
                document.getElementById('acFileModalFrame').src = '';
            }
        }).observe(fileModal, { attributes: true, attributeFilter: ['class'] });
    })();

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

        // A short two-tone chime, synthesized on the fly (Web Audio API) —
        // no audio file to ship/host. Browsers refuse to play audio before
        // the user has interacted with the page at all, so the context is
        // created lazily on the first click/keypress anywhere and reused
        // after that; if it's still not unlocked by the time a notification
        // arrives, the chime is just silently skipped (the visual badge/
        // ring animation still happens either way).
        var audioCtx = null;
        function unlockAudio() {
            if (audioCtx) return;
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (Ctx) audioCtx = new Ctx();
            document.removeEventListener('click', unlockAudio);
            document.removeEventListener('keydown', unlockAudio);
        }
        document.addEventListener('click', unlockAudio);
        document.addEventListener('keydown', unlockAudio);

        function playChime() {
            if (!audioCtx || audioCtx.state !== 'running') return;
            [880, 1318.5].forEach(function (freq, i) {
                var osc  = audioCtx.createOscillator();
                var gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                var start = audioCtx.currentTime + i * 0.09;
                gain.gain.setValueAtTime(0, start);
                gain.gain.linearRampToValueAtTime(0.12, start + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.3);
                osc.connect(gain).connect(audioCtx.destination);
                osc.start(start);
                osc.stop(start + 0.32);
            });
        }

        // Only chimes when the count actually goes UP from what this tab
        // already knew about — never on the first load of a page (which
        // would chime for every already-unread notification every time
        // any page loads) and never when it drops from marking things read.
        var lastUnread = null;
        function setDot(unread) {
            if (lastUnread !== null && unread > lastUnread) playChime();
            lastUnread = unread;

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
        setInterval(refreshUnread, 5000);
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

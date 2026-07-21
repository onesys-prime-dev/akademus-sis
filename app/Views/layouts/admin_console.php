<?php helper('admin'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akademus SIS | <?= esc($pageTitle ?? 'Admin Console') ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#0037b0",
                        "primary-container": "#1d4ed8",
                        "on-primary": "#ffffff",
                        "on-primary-container": "#cad3ff",
                        "primary-fixed": "#dce1ff",
                        "primary-fixed-dim": "#b7c4ff",
                        "on-primary-fixed": "#001551",
                        "on-primary-fixed-variant": "#0039b5",
                        secondary: "#515f74",
                        "secondary-container": "#d5e3fc",
                        "on-secondary": "#ffffff",
                        "on-secondary-container": "#57657a",
                        "secondary-fixed": "#d5e3fc",
                        "secondary-fixed-dim": "#b9c7df",
                        "on-secondary-fixed": "#0d1c2e",
                        "on-secondary-fixed-variant": "#3a485b",
                        tertiary: "#3d445a",
                        "tertiary-container": "#545c72",
                        "on-tertiary": "#ffffff",
                        "on-tertiary-container": "#cdd5ef",
                        "tertiary-fixed": "#dae2fd",
                        "tertiary-fixed-dim": "#bec6e0",
                        "on-tertiary-fixed": "#131b2e",
                        "on-tertiary-fixed-variant": "#3f465c",
                        background: "#f7f9fb",
                        "on-background": "#191c1e",
                        surface: "#f7f9fb",
                        "surface-white": "#ffffff",
                        "surface-bright": "#f7f9fb",
                        "surface-tint": "#2151da",
                        "surface-dim": "#d8dadc",
                        "surface-variant": "#e0e3e5",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-low": "#f2f4f6",
                        "surface-container": "#eceef0",
                        "surface-container-high": "#e6e8ea",
                        "surface-container-highest": "#e0e3e5",
                        "on-surface": "#191c1e",
                        "on-surface-variant": "#434655",
                        "inverse-surface": "#2d3133",
                        "inverse-on-surface": "#eff1f3",
                        "inverse-primary": "#b7c4ff",
                        outline: "#747686",
                        "outline-variant": "#c4c5d7",
                        "border-subtle": "#eef2f7",
                        "status-success": "#16a34a",
                        "status-warning": "#d97706",
                        "status-error": "#dc2626",
                        error: "#ba1a1a",
                        "on-error": "#ffffff",
                        "error-container": "#ffdad6",
                        "on-error-container": "#93000a",
                        "system-blue": "#1d4ed8",
                        "canvas-midnight": "#0e1117",
                    },
                    borderRadius: { DEFAULT: "0.25rem", lg: "0.5rem", xl: "0.75rem", full: "9999px" },
                    spacing: { md: "16px", base: "8px", xl: "32px", lg: "24px", sm: "8px", xs: "4px", "page-margin": "20px", gutter: "16px" },
                    fontFamily: {
                        "headline-lg": ["Inter"], display: ["Inter"], "headline-lg-mobile": ["Inter"],
                        "body-default": ["Inter"], "headline-md": ["Inter"], micro: ["Inter"],
                        subtext: ["Inter"], "label-caps": ["Inter"], "headline-sm": ["Inter"], "body-bold": ["Inter"],
                    },
                    fontSize: {
                        "headline-lg": ["22px", { lineHeight: "28px", letterSpacing: "-0.02em", fontWeight: "800" }],
                        display: ["24px", { lineHeight: "32px", letterSpacing: "-0.02em", fontWeight: "800" }],
                        "headline-lg-mobile": ["20px", { lineHeight: "26px", fontWeight: "800" }],
                        "body-default": ["13px", { lineHeight: "18px", fontWeight: "500" }],
                        "headline-md": ["17px", { lineHeight: "24px", fontWeight: "800" }],
                        micro: ["10.5px", { lineHeight: "14px", fontWeight: "700" }],
                        subtext: ["12.5px", { lineHeight: "1.35", fontWeight: "400" }],
                        "label-caps": ["11px", { lineHeight: "16px", letterSpacing: "0.05em", fontWeight: "800" }],
                        "headline-sm": ["15px", { lineHeight: "20px", fontWeight: "800" }],
                        "body-bold": ["13px", { lineHeight: "18px", fontWeight: "700" }],
                    },
                },
            },
        };
    </script>

    <style>
        body { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .icon-fill { font-variation-settings: 'FILL' 1; }

        .spring-hover { transition: transform .4s cubic-bezier(.175,.885,.32,1.275), box-shadow .4s cubic-bezier(.175,.885,.32,1.275), background-color .2s ease; }
        .spring-hover:hover { transform: translateY(-2px) scale(1.01); box-shadow: 0 10px 25px -5px rgba(0,0,0,.1), 0 8px 10px -6px rgba(0,0,0,.05); }
        .spring-hover:active { transform: translateY(0) scale(.98); box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.05); transition: transform .1s ease, box-shadow .1s ease; }

        .animate-enter { opacity: 0; transform: translateY(15px); animation: springFadeIn .8s cubic-bezier(.175,.885,.32,1.275) forwards; }
        @keyframes springFadeIn { 0% { opacity:0; transform: translateY(15px); } 100% { opacity:1; transform:none; } }
        .delay-100{animation-delay:100ms}.delay-200{animation-delay:200ms}.delay-300{animation-delay:300ms}

        .custom-scrollbar::-webkit-scrollbar{width:4px}
        .custom-scrollbar::-webkit-scrollbar-track{background:transparent}
        .custom-scrollbar::-webkit-scrollbar-thumb{background-color:#c4c5d7;border-radius:10px}

        .glass-panel { background: rgba(255,255,255,.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,.3); }

        .segmented-control { background-color: #e6e8ea; padding: 2px; border-radius: 8px; display: inline-flex; }
        .segmented-control a { padding: 4px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; color: #434655; transition: all .2s ease; text-decoration: none; }
        .segmented-control a.active { background-color: #fff; color: #191c1e; box-shadow: 0 1px 3px rgba(0,0,0,.1); font-weight: 700; }

        .ac-toast-container { position: fixed; top: 16px; right: 16px; display: flex; flex-direction: column; gap: 8px; z-index: 9000; }
        .ac-toast { animation: acSlideIn .18s ease; display: flex; gap: 9px; align-items: flex-start; background: #fff; border-radius: 10px; padding: 11px 14px; max-width: 360px; box-shadow: 0 10px 30px rgba(0,0,0,.18); border-left: 4px solid #16a34a; }
        .ac-toast--error { border-left-color: #dc2626; }
        .ac-toast__text { font-size: 12.5px; color: #334155; line-height: 1.35; }
        @keyframes acSlideIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }
    </style>

    <link rel="stylesheet" href="<?= base_url('assets/css/pages/admin-console.css') ?>">
    <?= $this->renderSection('styles') ?>
</head>
<body class="bg-background text-on-background flex h-screen overflow-hidden">

<?php
$navItems = [
    'dashboard'      => ['label' => 'Overview',        'icon' => 'dashboard',      'url' => 'admin/dashboard'],
    'departments'    => ['label' => 'Departments',     'icon' => 'hub',            'url' => 'admin/departments'],
    'users'          => ['label' => 'User Accounts',   'icon' => 'group',          'url' => 'admin/users'],
    'rooms'          => ['label' => 'Facilities',      'icon' => 'meeting_room',   'url' => 'admin/rooms'],
    'academic-years' => ['label' => 'Academic Years',  'icon' => 'event',          'url' => 'admin/academic-years'],
    'roles'          => ['label' => 'Roles',           'icon' => 'admin_panel_settings', 'url' => 'admin/roles'],
];
$active   = $activeAdmin ?? '';
$username = auth()->user()->username ?? 'Administrator';
$initial  = strtoupper(substr($username, 0, 1));
?>

<!-- Side Nav -->
<aside class="hidden md:flex flex-col gap-md p-md border-r border-outline-variant/30 fixed left-0 top-0 h-full w-[280px] bg-white/70 backdrop-blur-xl shadow-sm z-50 overflow-y-auto custom-scrollbar">
    <div class="flex items-center gap-sm px-sm py-md">
        <img src="<?= base_url('assets/images/logos/akademus.png') ?>" alt="Akademus" class="w-10 h-10 rounded-lg object-cover shadow-sm bg-white">
        <div>
            <h1 class="font-headline-md text-headline-md text-primary tracking-tight m-0">Akademus</h1>
            <p class="font-subtext text-subtext text-secondary m-0">Admin Console</p>
        </div>
    </div>

    <nav class="flex-1 flex flex-col gap-xs mt-sm">
        <?php foreach ($navItems as $key => $item): ?>
            <a href="<?= site_url($item['url']) ?>"
               class="flex items-center gap-sm px-md py-[10px] rounded-lg font-body-default transition-all scale-95 duration-150 ease-in-out
                      <?= $active === $key
                          ? 'bg-secondary-container text-on-secondary-container font-body-bold'
                          : 'text-secondary hover:bg-surface-container-low' ?>">
                <span class="material-symbols-outlined <?= $active === $key ? 'icon-fill' : '' ?>"><?= $item['icon'] ?></span>
                <span><?= esc($item['label']) ?></span>
            </a>
        <?php endforeach ?>
    </nav>

    <div class="mt-auto flex flex-col gap-xs border-t border-outline-variant/30 pt-md">
        <div class="flex items-center gap-sm px-md py-[10px]">
            <div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-body-bold text-body-default flex-shrink-0"><?= esc($initial) ?></div>
            <div class="min-w-0">
                <p class="font-body-bold text-body-default text-on-surface m-0 truncate"><?= esc($username) ?></p>
                <p class="font-subtext text-subtext text-secondary m-0">Administrator</p>
            </div>
        </div>
        <a href="<?= site_url('/logout') ?>" class="flex items-center gap-sm px-md py-[10px] text-secondary hover:bg-surface-container-low transition-colors font-body-default rounded-lg">
            <span class="material-symbols-outlined">logout</span>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Main -->
<main class="flex-1 ml-0 md:ml-[280px] h-screen overflow-y-auto bg-background relative flex flex-col">
    <header class="sticky top-0 z-40 w-full flex justify-between items-center px-xl py-lg bg-surface-bright/80 backdrop-blur-md shadow-sm">
        <div class="md:hidden font-headline-lg-mobile text-headline-lg-mobile text-on-surface">Akademus</div>
        <div class="hidden md:block"></div>

        <div class="flex items-center gap-lg">
            <div class="relative hidden sm:block">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span>
                <input class="pl-10 pr-4 py-2 bg-surface-container-lowest border border-outline-variant rounded-full text-body-default font-body-default focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all duration-300 w-64" placeholder="Search Akademus..." type="text">
            </div>
            <?= $this->renderSection('headerActions') ?>
        </div>
    </header>

    <div class="p-page-margin md:p-xl max-w-7xl mx-auto w-full flex-1 animate-enter">
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
</script>

</body>
</html>

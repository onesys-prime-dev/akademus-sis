<?php
/**
 * Shell topbar. Expects: $pageTitle, $userName, $userRole, $settingsUrl.
 */
?>
<div class="header-card glass">
    <header class="topbar">
        <h1><?= esc($pageTitle) ?></h1>
        <div class="user-menu" id="userMenu">
            <button type="button" class="user-trigger" id="userMenuBtn">
                <div class="avatar-sm"><?= esc(mb_strtoupper(mb_substr($userName, 0, 1))) ?></div>
                <div class="user-text">
                    <div class="name"><?= esc($userName) ?></div>
                    <div class="role"><?= esc($userRole) ?></div>
                </div>
                <span class="material-symbols-outlined chevron">expand_more</span>
            </button>
            <div class="user-dropdown">
                <a href="<?= esc($settingsUrl) ?>"><span class="material-symbols-outlined" style="font-size:18px;">settings</span> Settings</a>
                <hr>
                <a href="<?= site_url('logout') ?>" class="danger"><span class="material-symbols-outlined" style="font-size:18px;">logout</span> Logout</a>
            </div>
        </div>
    </header>
</div>

<script>
    (function () {
        const menu = document.getElementById('userMenu');
        const btn  = document.getElementById('userMenuBtn');
        if (!menu || !btn) return;
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.classList.toggle('open');
        });
        document.addEventListener('click', () => menu.classList.remove('open'));
    })();
</script>

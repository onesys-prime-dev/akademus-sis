<?php
$userRole = esc($user->getGroups()[0]);
?>

<nav class="sidebar">
    <a class="logo" href="<?= site_url($userRole . '/dashboard') ?>">
        <img class="logo__icon" src="<?= base_url('assets/images/logos/akademus.png') ?>" alt="Logo">
    </a>

    <div class="navlinks">
        <div class="navlinks__group">

            <a class="navlink <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= site_url($userRole . '/dashboard') ?>">
                <img class="navlink__icon" src="<?= base_url('assets/images/icons/') . ($activePage === 'dashboard' ? 'home-white' : 'home') . '.png' ?>" alt="Home Icon">

                <!-- Hover Text -->
                <span class="navlink__label">Dashboard</span>
            </a>

            <a class="navlink <?= $activePage === 'profile' ? 'active' : '' ?>" href="<?= site_url($userRole . '/profile') ?>">
                <img class="navlink__icon" src="<?= base_url('assets/images/icons/') . ($activePage === 'profile' ? 'profile-white' : 'profile') . '.png' ?>" alt="Profile Icon">

                <!-- Hover Text -->
                <span class="navlink__label">Profile</span>
            </a>

        </div>

        <div class="navlinks__group">

            <a class="navlink <?= $activePage === 'settings' ? 'active' : '' ?>" href="<?= site_url($userRole . '/settings') ?>">
                <img class="navlink__icon" src="<?= base_url('assets/images/admin/icons/settings.png') ?>" alt="Settings Icon">

                <!-- Hover Text -->
                <span class="navlink__label">Settings</span>
            </a>

            <a class="navlink <?= $activePage === 'logout' ? 'active' : '' ?>" href="<?= site_url('/logout') ?>">
                <img class="navlink__icon" src="<?= base_url('assets/images/admin/icons/logout.png') ?>" alt="Logout Icon">

                <!-- Hover Text -->
                <span class="navlink__label">Logout</span>
            </a>

        </div>
    </div>
</nav>
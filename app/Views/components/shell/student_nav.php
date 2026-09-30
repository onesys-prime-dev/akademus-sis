<?php
/**
 * Sidebar nav for the student shell. $activePage: 'dashboard'|'profile'|'schedule'|'grades'|'tuition'.
 */
?>
<aside class="side-drawer glass">
    <div class="brand"><span class="material-symbols-outlined">layers</span></div>
    <nav>
        <a class="drawer-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= site_url('student/dashboard') ?>" title="Dashboard">
            <span class="material-symbols-outlined">home</span>
            <span class="drawer-label">Dashboard</span>
        </a>
        <a class="drawer-link <?= $activePage === 'profile' ? 'active' : '' ?>" href="<?= site_url('student/profile') ?>" title="Profile">
            <span class="material-symbols-outlined">person</span>
            <span class="drawer-label">Profile</span>
        </a>
        <a class="drawer-link <?= $activePage === 'tuition' ? 'active' : '' ?>" href="<?= site_url('student/tuition') ?>" title="Tuition & Fees">
            <span class="material-symbols-outlined">payments</span>
            <span class="drawer-label">Tuition & Fees</span>
        </a>
        <a class="drawer-link <?= $activePage === 'schedule' ? 'active' : '' ?>" href="<?= site_url('student/schedule') ?>" title="My Schedule">
            <span class="material-symbols-outlined">calendar_month</span>
            <span class="drawer-label">My Schedule</span>
        </a>
        <a class="drawer-link <?= $activePage === 'grades' ? 'active' : '' ?>" href="<?= site_url('student/grades') ?>" title="My Grades">
            <span class="material-symbols-outlined">grade</span>
            <span class="drawer-label">My Grades</span>
        </a>
    </nav>

    <a class="drawer-link drawer-link--logout" href="<?= site_url('logout') ?>" title="Logout">
        <span class="material-symbols-outlined">logout</span>
        <span class="drawer-label">Logout</span>
    </a>
</aside>

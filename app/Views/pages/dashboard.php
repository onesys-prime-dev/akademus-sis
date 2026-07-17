<?= $this->extend('layouts/main') ?>



<!--
|--------------------------------------------------------------------------
| Styles
|--------------------------------------------------------------------------
-->
<?= $this->section('styles') ?>

<link rel="stylesheet" href="<?= base_url('assets/css/pages/dashboard.css') . '?' . time() ?>">

<link rel="stylesheet" href="<?= base_url('assets/css/features/dashboard/welcome-banner.css') . '?' . time() ?>">

<?= $this->endSection() ?>



<!--
|--------------------------------------------------------------------------
| Content
|--------------------------------------------------------------------------
-->
<?= $this->section('content') ?>

<?php 
    $user = auth()->user();
    $username = ucwords($user->username);
?>

<div class="dashboard">

    <div class="welcome-banner__card card">
        <?= $this->include('components/dashboard/welcome-banner') ?>
    </div>

    <div class="announcement-list__section">

        <div class="announcement-list__header">

            <span class="announcement-list__title">Announcements</span>

        </div>

        <div class="announcement-list__card card">
            <?= $this->include('components/dashboard/announcement-list') ?>
        </div>
    
    </div>

</div>

<?= $this->endSection() ?>



<!--
|--------------------------------------------------------------------------
| Scripts
|--------------------------------------------------------------------------
-->
<?= $this->section('scripts') ?>

<script>
    console.log('Dashboard loaded.');
</script>

<?= $this->endSection() ?>
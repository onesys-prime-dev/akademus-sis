<?= $this->extend('layouts/main') ?>



<!--
|--------------------------------------------------------------------------
| Styles
|--------------------------------------------------------------------------
-->
<?= $this->section('styles') ?>

<link rel="stylesheet" href="<?= base_url('assets/css/pages/profile.css') . '?' . time() ?>">

<link rel="stylesheet" href="<?= base_url('assets/css/features/profile/profile-details.css') . '?' . time() ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/features/profile/profile-overview.css') . '?' . time() ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/features/profile/academic-history.css') . '?' . time() ?>">

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

<div class="profile">

    <div class="profile__header">

        <span class="profile__title">Student Profile</span>

        <div class="profile__actions">

            <button class="profile__button--edit btn btn-secondary">
                <img class="profile__action--icon" src="<?= base_url('assets/images/icons/edit.png') ?>" alt="Edit Icon">

                <span class="profile__action--label">Edit Record</span>
            </button>

        </div>

    </div>

    <div class="profile__section">

        <div class="profile-details__card card">

            <?= $this->include('components/profile/profile-details', [
                'user' => $user
            ]) ?>

        </div>

        <div class="profile-overview__card card">

            <?= $this->include('components/profile/profile-overview', [
                'user' => $user
            ]) ?>

        </div>

    </div>

    <div class="profile__section">

        <div class="academic-history__card card">

            <?= $this->include('components/profile/academic-history', [
                'user' => $user
            ]) ?>
            
        </div>

        
        <div class="record-detail__card card">

            <?= $this->include('components/profile/record-detail', [
                'user' => $user
            ]) ?>

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
    console.log('Profile loaded.');
</script>

<?= $this->endSection() ?>
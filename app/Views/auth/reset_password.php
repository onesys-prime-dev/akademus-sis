<?= $this->extend(config('Auth')->views['layout']) ?>


<?= $this->section('pageStyles') ?>

<link rel="stylesheet"
      href="<?= base_url('assets/css/pages/auth.css') ?>">

<?= $this->endSection() ?>


<?= $this->section('title') ?>

<?= lang('Auth.login') ?> 

<?= $this->endSection() ?>


<?= $this->section('main') ?>

    <div class="auth">

        <div class="auth__bubble auth__bubble--one"></div>
        <div class="auth__bubble auth__bubble--two"></div>
        <div class="auth__bubble auth__bubble--three"></div>
        <div class="auth__bubble auth__bubble--four"></div>
        <div class="auth__bubble auth__bubble--five"></div>

        <div class="auth-card card">

            <div class="auth__left">

                <div class="logo">
                    <img class="logo--img" src="<?= base_url('assets/images/logos/akademus-logo-with-text.png') ?>" alt="">
                </div>
                
                <div class="card-body">
                    <div class="card-title__group">
                        <h5 class="card-title mb-5">Create New Password</h5>
                        <span class="card-subtitle">Enter new password</span>
                    </div>

                    <?php if (session('error') !== null) : ?>
                        <div class="alert alert-danger" role="alert">
                            <img class="auth-alert--img" src="<?= base_url('assets/images/icons/alert-danger.png') ?>" alt="">
                            <?= esc(session('error')) ?>
                        </div>
                    <?php elseif (session('errors') !== null) : ?>
                        <div class="alert alert-danger" role="alert">
                            <?php if (is_array(session('errors'))) : ?>
                                <?php foreach (session('errors') as $error) : ?>
                                    <img class="auth-alert--img" src="<?= base_url('assets/images/icons/alert-danger.png') ?>" alt="">
                                    <?= esc($error) ?>
                                    <br>
                                <?php endforeach ?>
                            <?php else : ?>
                                <?= esc(session('errors')) ?>
                            <?php endif ?>
                        </div>
                    <?php endif ?>

                    <?php if (session('message') !== null) : ?>
                        <div class="alert alert-success" role="alert"><?= esc(session('message')) ?></div>
                    <?php endif ?>

                    <form method="post" action="<?= site_url('reset-password') ?>">
                        <?= csrf_field() ?>

                        <input type="hidden" name="token" value="<?= esc($token) ?>">

                        <!-- Password -->
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control input" id="floatingPasswordInput" name="password" inputmode="text" autocomplete="current-password" placeholder="New Password" required>
                        </div>

                        <!-- Password -->
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control input" id="floatingPasswordInput" name="password_confirm" inputmode="text" autocomplete="current-password" placeholder="Retype Password" required>
                        </div>

                        <div class="d-grid col-12 col-md-8 mx-auto m-3">
                            <button type="submit" class="auth-btn btn btn-primary btn-block">Confirm Password</button>
                        </div>

                    </form>

                    <p class="text-center">Remember your password? <a href="<?= site_url('login') ?>">Back to Login</a></p>

                </div>

            </div>

            <div class="auth__right">

                <div class="auth__right--hero">
                    <img class="auth__right--hero--img" src="<?= base_url('assets/images/background/auth-image.png') ?>" alt="">
                </div>

            </div>

        </div>
    </div>

<?= $this->endSection() ?>

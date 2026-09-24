<?= $this->extend(config('Auth')->views['layout']) ?>


<?= $this->section('pageStyles') ?>

<link rel="stylesheet"
      href="<?= base_url('assets/css/pages/auth.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/auth.css') ?>">

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
                        <h5 class="card-title mb-5">Welcome back</h5>
                        <span class="card-subtitle">Sign in to access your campus services.</span>
                    </div>

                    <!--
                    <div class="auth__user-options">
                        <button class="auth__user-options--tab toggle-btn toggle-btn--active">Student</button>
                        <button class="auth__user-options--tab toggle-btn">Faculty</button>
                        <button class="auth__user-options--tab toggle-btn">Admin</button>
                    </div>
                    -->

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

                    <form method="post" action="<?= url_to('login') ?>" id="loginForm">
                        <?= csrf_field() ?>

                        <!-- Email -->
                        <div class="form-floating mb-3">
                            <label for="floatingEmailInput">Email address</label>
                            <input type="email" class="form-control input" id="floatingEmailInput" name="email" inputmode="email" autocomplete="email" placeholder="<?= lang('Auth.email') ?>" value="<?= old('email') ?>" required>
                        </div>

                        <!-- Password -->
                        <div class="form-floating mb-3">
                            <label for="floatingPasswordInput">Password</label>
                            <input type="password" class="form-control input" id="floatingPasswordInput" name="password" inputmode="text" autocomplete="current-password" placeholder="<?= lang('Auth.password') ?>" required>
                        </div>

                        <div class="auth-actions">
                            <!-- Remember me -->
                            <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
                                <label class="form-check-label">
                                    <input type="checkbox" name="remember" class="form-check-input" <?php if (old('remember')): ?> checked<?php endif ?>>
                                    <?= lang('Auth.rememberMe') ?>
                                </label>
                            <?php endif; ?>

                            <?php if (setting('Auth.allowMagicLinkLogins')) : ?>
                                <p class="text-center"><a href="<?= base_url('forgot-password') ?>">Forgot Password?</a></p>
                            <?php endif ?>
                        </div>

                        <div class="d-grid col-12 col-md-8 mx-auto m-3">
                            <button type="submit" class="auth-btn btn btn-primary btn-block" id="loginSubmit"><?= lang('Auth.login') ?></button>
                        </div>

                        <?php if (setting('Auth.allowRegistration')) : ?>
                            <div class="registration">
                                <p class="text-center">FOR DEVELOPMENT ONLY <a href="<?= url_to('register') ?>"><?= lang('Auth.register') ?></a></p>
                            </div>
                        <?php endif ?>

                    </form>
                </div>

            </div>

            <div class="auth__right">

                <div class="auth__right--hero">
                    <img class="auth__right--hero--img" src="<?= base_url('assets/images/background/auth-image-clean.png') ?>" alt="">

                    <div class="hero-copy">
                        <h2>Your campus,<span>in one place.</span></h2>
                    </div>
                </div>

            </div>

        </div>
    </div>

<?= $this->endSection() ?>


<?= $this->section('pageScripts') ?>

<script src="<?= base_url('assets/js/auth-hero.js') ?>"></script>

<script>
    window.addEventListener('pageshow', function () {
        var button = document.getElementById('loginSubmit');
        button.disabled = false;
        button.textContent = <?= json_encode(lang('Auth.login')) ?>;
        button.removeAttribute('aria-busy');
    });
    document.getElementById('loginForm').addEventListener('submit', function () {
        document.getElementById('loginSubmit').disabled = true;
        document.getElementById('loginSubmit').textContent = 'Signing in…';
        document.getElementById('loginSubmit').setAttribute('aria-busy', 'true');
    });
</script>

<?= $this->endSection() ?>

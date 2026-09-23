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
                        <h5 class="card-title mb-5">Welcome Back!</h5>
                        <span class="card-subtitle">Access Resources And Services With Ease</span>
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
                            <input type="email" class="form-control input" id="floatingEmailInput" name="email" inputmode="email" autocomplete="email" placeholder="<?= lang('Auth.email') ?>" value="<?= old('email') ?>" required>
                        </div>

                        <!-- Password -->
                        <div class="form-floating mb-3">
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

    <div class="page-loader" id="pageLoader">
        <div class="page-loader__panel">

            <div class="page-loader__icon">
                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="loaderGrad" x1="0%" x2="100%" y1="0%" y2="100%">
                            <stop offset="0%" stop-color="var(--color-primary)"></stop>
                            <stop offset="100%" stop-color="var(--color-primary-hover)"></stop>
                        </linearGradient>
                    </defs>

                    <!-- Book base -->
                    <path d="M20 70 Q20 30 50 30 Q80 30 80 70 L20 70" fill="none" stroke="url(#loaderGrad)" stroke-linecap="round" stroke-width="4">
                        <animate attributeName="stroke-dasharray" dur="2s" from="0, 200" repeatCount="indefinite" to="200, 0"></animate>
                    </path>

                    <!-- Spine -->
                    <path d="M50 30 L50 70" opacity="0.6" stroke="url(#loaderGrad)" stroke-linecap="round" stroke-width="2"></path>

                    <!-- Flipping pages -->
                    <path d="M50 30 Q35 30 35 50" fill="none" stroke="url(#loaderGrad)" stroke-linecap="round" stroke-width="3">
                        <animate attributeName="d" dur="1.5s" repeatCount="indefinite" values="M50 30 Q35 30 35 50; M50 30 Q50 20 50 30; M50 30 Q35 30 35 50"></animate>
                    </path>
                    <path d="M50 30 Q65 30 65 50" fill="none" stroke="url(#loaderGrad)" stroke-linecap="round" stroke-width="3">
                        <animate attributeName="d" begin="0.75s" dur="1.5s" repeatCount="indefinite" values="M50 30 Q65 30 65 50; M50 30 Q50 20 50 30; M50 30 Q65 30 65 50"></animate>
                    </path>

                    <!-- Mortarboard -->
                    <path d="M50 15 L80 25 L50 35 L20 25 Z" fill="url(#loaderGrad)">
                        <animateTransform attributeName="transform" dur="3s" repeatCount="indefinite" type="translate" values="0 0; 0 -5; 0 0"></animateTransform>
                    </path>

                    <!-- Tassel -->
                    <path d="M80 25 L85 45" stroke="#f5b301" stroke-linecap="round" stroke-width="2">
                        <animateTransform attributeName="transform" dur="2s" repeatCount="indefinite" type="rotate" values="0 80 25; 10 80 25; 0 80 25"></animateTransform>
                    </path>
                </svg>
            </div>

            <h2 class="page-loader__title">Signing you in&hellip;</h2>

            <div class="page-loader__bar">
                <div class="page-loader__bar-fill" id="pageLoaderBar"></div>
            </div>

        </div>
    </div>

<?= $this->endSection() ?>


<?= $this->section('pageScripts') ?>

<script src="<?= base_url('assets/js/auth-hero.js') ?>"></script>

<script>
    document.getElementById('loginForm').addEventListener('submit', function () {
        document.getElementById('loginSubmit').disabled = true;
        document.getElementById('pageLoader').classList.add('is-active');

        requestAnimationFrame(function () {
            document.getElementById('pageLoaderBar').style.width = '100%';
        });
    });
</script>

<?= $this->endSection() ?>

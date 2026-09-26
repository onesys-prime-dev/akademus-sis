<?= $this->extend(config('Auth')->views['layout']) ?>


<?= $this->section('pageStyles') ?>

<link rel="stylesheet"
      href="<?= base_url('assets/css/pages/auth.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/auth.css') ?>">
<link rel="stylesheet"
      href="<?= base_url('assets/css/design-system/session-guard.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/session-guard.css') ?>">

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
                            <div class="pw-field">
                                <input type="password" class="form-control input" id="floatingPasswordInput" name="password" inputmode="text" autocomplete="current-password" placeholder="<?= lang('Auth.password') ?>" required>
                                <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password" aria-controls="floatingPasswordInput" aria-pressed="false">
                                    <svg class="pw-toggle__eye" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="pw-toggle__eye-off" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.9 17.9A10.1 10.1 0 0 1 12 19c-6.4 0-10-7-10-7a17.6 17.6 0 0 1 4.1-5.1M9.9 5.2A9.7 9.7 0 0 1 12 5c6.4 0 10 7 10 7a17.7 17.7 0 0 1-2.2 3.2M14.1 14.1a3 3 0 1 1-4.2-4.2"/><path d="M2 2l20 20"/></svg>
                                </button>
                            </div>
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

    <?php
    // Errors stay inline (red banner above the form); the modal is for the
    // session-ended notice and success messages. Priority: session ended > success.
    $notice = null;
    $icons = [
        'lock'    => '<rect x="4" y="11" width="16" height="10" rx="2.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'alert'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/>',
        'success' => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.8 2.8L16.5 9.5"/>',
    ];

    if (session('sessionEnded')) {
        $notice = [
            'type'  => 'info', 'icon' => 'lock',
            'title' => 'Your session has ended',
            'lines' => ['For your security you were signed out. Sign in again to pick up where you left off.'],
            'button' => 'Sign in again', 'focus' => 'floatingEmailInput',
        ];
    } elseif (session('message') !== null) {
        $message = (string) session('message');
        $notice = [
            'type'  => 'success', 'icon' => 'success',
            'title' => stripos($message, 'logged out') !== false ? 'Signed out'
                     : (stripos($message, 'password') !== false ? 'Password updated' : 'Success'),
            'lines' => [$message],
            'button' => 'Continue', 'focus' => 'floatingEmailInput',
        ];
    }
    ?>
    <?php if ($notice !== null) : ?>
        <dialog class="sg-dialog" id="loginNoticeDialog" aria-labelledby="loginNoticeTitle" aria-describedby="loginNoticeText" data-focus="<?= esc($notice['focus'], 'attr') ?>">
            <div class="sg-card" role="alertdialog">
                <div class="sg-icon sg-icon--<?= esc($notice['type'], 'attr') ?>" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $icons[$notice['icon']] ?></svg>
                </div>
                <h2 id="loginNoticeTitle"><?= esc($notice['title']) ?></h2>
                <div id="loginNoticeText">
                    <?php foreach ($notice['lines'] as $line) : ?>
                        <p><?= esc($line) ?></p>
                    <?php endforeach ?>
                </div>
                <div class="sg-actions">
                    <button type="button" class="sg-btn sg-btn--primary" id="loginNoticeOk"><?= esc($notice['button']) ?></button>
                </div>
            </div>
        </dialog>
    <?php endif ?>

<?= $this->endSection() ?>


<?= $this->section('pageScripts') ?>

<script src="<?= base_url('assets/js/auth-hero.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/auth-hero.js') ?>"></script>

<script>
    window.addEventListener('pageshow', function () {
        var button = document.getElementById('loginSubmit');
        button.disabled = false;
        button.textContent = <?= json_encode(lang('Auth.login')) ?>;
        button.removeAttribute('aria-busy');
    });
    <?php if (session('error') !== null || session('errors') !== null) : ?>
    (function () {
        var fields = document.querySelectorAll('#loginForm .form-control');
        fields.forEach(function (field) {
            field.classList.add('is-shaking');
            field.addEventListener('animationend', function () {
                field.classList.remove('is-shaking');
            }, { once: true });
            field.addEventListener('input', function () {
                field.classList.remove('is-shaking');
            }, { once: true });
        });
    })();
    <?php endif ?>
    (function () {
        var dialog = document.getElementById('loginNoticeDialog');
        if (!dialog) return;

        dialog.showModal();
        document.getElementById('loginNoticeOk').addEventListener('click', function () {
            dialog.close();
        });
        dialog.addEventListener('close', function () {
            var target = document.getElementById(dialog.dataset.focus);
            if (target) target.focus();
        });
    })();
    (function () {
        var input = document.getElementById('floatingPasswordInput');
        var toggle = document.getElementById('pwToggle');
        toggle.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.classList.toggle('is-showing', show);
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            input.focus();
        });
    })();
    document.getElementById('loginForm').addEventListener('submit', function () {
        document.getElementById('loginSubmit').disabled = true;
        document.getElementById('loginSubmit').textContent = 'Signing in…';
        document.getElementById('loginSubmit').setAttribute('aria-busy', 'true');
    });
</script>

<?= $this->endSection() ?>

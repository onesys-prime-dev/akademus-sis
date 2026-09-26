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
                        <h5 class="card-title mb-5">Reset Password</h5>
                        <span class="card-subtitle">Enter your email for the verification code</span>
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

                    <form method="post" action="<?= site_url('forgot-password') ?>">
                        <?= csrf_field() ?>

                        <!-- Email -->
                        <div class="form-floating mb-3">
                            <label for="floatingEmailInput">Email address</label>
                            <input type="email" class="form-control input" id="floatingEmailInput" name="email" inputmode="email" autocomplete="email" placeholder="<?= lang('Auth.email') ?>" value="<?= old('email') ?>" required>
                        </div>

                        <div class="d-grid col-12 col-md-8 mx-auto m-3">
                            <button type="submit" class="auth-btn btn btn-primary btn-block">Send Code</button>
                        </div>

                    </form>

                    <p class="text-center">Remember your password? <a href="<?= site_url('login') ?>">Back to Login</a></p>

                </div>

            </div>

            <div class="auth__right">

                <div class="auth__right--hero" data-hero="forgot">
                    <img class="auth__right--hero--img" src="<?= base_url('assets/images/background/forgot-pass-hero.png') ?>" alt="">
                </div>

            </div>

        </div>
    </div>

    <?php if (session('message') !== null) : ?>
        <dialog class="sg-dialog" id="noticeDialog" aria-labelledby="noticeTitle" aria-describedby="noticeText">
            <div class="sg-card" role="alertdialog">
                <div class="sg-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 7l8.5 6 8.5-6"/></svg>
                </div>
                <h2 id="noticeTitle">Check your email</h2>
                <p id="noticeText"><?= esc(session('message')) ?></p>
                <div class="sg-actions">
                    <button type="button" class="sg-btn sg-btn--primary" id="noticeOk">Got it</button>
                </div>
            </div>
        </dialog>
    <?php endif ?>

<?= $this->endSection() ?>


<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/auth-hero.js') ?>"></script>
<script>
    (function () {
        var dialog = document.getElementById('noticeDialog');
        if (!dialog) return;
        dialog.showModal();
        document.getElementById('noticeOk').addEventListener('click', function () { dialog.close(); });
        dialog.addEventListener('close', function () {
            document.getElementById('floatingEmailInput').focus();
        });
    })();
</script>
<?= $this->endSection() ?>

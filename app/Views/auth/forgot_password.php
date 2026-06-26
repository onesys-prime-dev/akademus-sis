<!DOCTYPE html>
<html>

<head>
    <title>Forgot Password</title>
</head>

<body>

    <h2>Forgot Password</h2>

    <?php if (session('error')) : ?>
        <div style="color:red;">
            <?= session('error') ?>
        </div>
    <?php endif; ?>

    <?php if (session('message')) : ?>
        <div style="color:green;">
            <?= session('message') ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= site_url('forgot-password') ?>">
        <?= csrf_field() ?>

        <label>Email</label><br>
        <input type="email" name="email" required>

        <br><br>

        <button type="submit">
            Send Reset Link
        </button>
    </form>

</body>

</html>
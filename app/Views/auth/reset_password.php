<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>

<h2>Reset Password</h2>

<form method="post" action="<?= site_url('reset-password') ?>">

    <?= csrf_field() ?>

    <input
        type="hidden"
        name="token"
        value="<?= esc($token) ?>"
    >

    <label>New Password</label>
    <br>

    <input
        type="password"
        name="password"
        required
    >

    <br><br>

    <label>Confirm Password</label>
    <br>

    <input
        type="password"
        name="password_confirm"
        required
    >

    <br><br>

    <button type="submit">
        Reset Password
    </button>

</form>

</body>
</html>
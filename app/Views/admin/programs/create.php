<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <?php if (session()->has('errors')): ?>
        <div style="color:red;">
            <?php foreach (session('errors') as $error): ?>
                <p><?= esc($error) ?></p>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if (session()->has('message')): ?>
        <div style="color:green;">
            <?= session('message') ?>
        </div>
    <?php endif ?>
    
    <h1>Create Program</h1>

    <form method="post" action="<?= site_url('admin/programs/store') ?>">

        <?= csrf_field() ?>

        <label>Program Code</label>
        <br>
        <input type="text" name="program_code" value="<?= old('program_code') ?>">
        <br><br>

        <label>Program Name</label>
        <br>
        <input type="text" name="program_name" value="<?= old('program_name') ?>">
        <br><br>

        <label>Description</label>
        <br>
        <textarea name="description"><?= old('description') ?></textarea>
        <br><br>

        <button type="submit">
            Create Program
        </button>

    </form>
</body>

</html>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Program</title>
</head>

<body>
    <?php if (session()->has('errors')): ?>
        <div style="color:red;">
            <?php foreach (session('errors') as $error): ?>
                <p><?= esc($error) ?></p>
            <?php endforeach ?>
        </div>
    <?php endif ?>
    
    <h1>Edit Program</h1>

    <form method="post"
        action="<?= site_url('admin/programs/update/' . $program['program_id']) ?>">

        <?= csrf_field() ?>

        <label>Program Code</label>
        <br>
        <input
            type="text"
            name="program_code"
            value="<?= esc($program['program_code']) ?>">
        <br><br>

        <label>Program Name</label>
        <br>
        <input
            type="text"
            name="program_name"
            value="<?= esc($program['program_name']) ?>">
        <br><br>

        <label>Description</label>
        <br>
        <textarea name="description"><?= esc($program['description']) ?></textarea>
        <br><br>

        <button type="submit">
            Update Program
        </button>

    </form>

</body>

</html>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <?php if (session()->has('errors')): ?>

        <div style="color:red">

            <?php foreach (session('errors') as $error): ?>

                <p><?= esc($error) ?></p>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>
    <?php if (session()->has('message')): ?>

        <div style="color:green">

            <?= session('message') ?>

        </div>

    <?php endif; ?>

    <form method="post"
        action="<?= site_url('admin/curriculum/store') ?>">

        <?= csrf_field() ?>

        <label>Program</label>
        <select name="program_id">

            <?php foreach ($programs as $program): ?>

                <option value="<?= $program['program_id'] ?>"
                    <?= old('program_id') == $program['program_id'] ? 'selected' : '' ?>>
                    <?= esc($program['program_name']) ?>
                </option>

            <?php endforeach ?>

        </select>

        <br><br>

        <label>Curriculum Code</label>
        <input type="text" name="curriculum_code" value="<?= old('curriculum_code') ?>">

        <br><br>

        <label>Curriculum Name</label>
        <input type="text" name="curriculum_name" value="<?= old('curriculum_name') ?>">

        <br><br>

        <label>Academic Year</label>
        <input type="text" name="academic_year" placeholder="2025-2026" value="<?= old('academic_year') ?>">

        <br><br>

        <label>Description</label>
        <textarea name="description"><?= old('description') ?></textarea>

        <br><br>

        <button type="submit">
            Save Curriculum
        </button>

    </form>

</body>

</html>
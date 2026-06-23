<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <form method="post"
        action="<?= site_url('admin/curriculum/store') ?>">

        <?= csrf_field() ?>

        <label>Program</label>
        <select name="program_id">

            <?php foreach ($programs as $program): ?>

                <option value="<?= $program['program_id'] ?>">
                    <?= esc($program['program_name']) ?>
                </option>

            <?php endforeach ?>

        </select>

        <br><br>

        <label>Curriculum Code</label>
        <input type="text" name="curriculum_code">

        <br><br>

        <label>Curriculum Name</label>
        <input type="text" name="curriculum_name">

        <br><br>

        <label>Academic Year</label>
        <input type="text" name="academic_year">

        <br><br>

        <label>Description</label>
        <textarea name="description"></textarea>

        <br><br>

        <button type="submit">
            Save Curriculum
        </button>

    </form>

    </select>
</body>

</html>
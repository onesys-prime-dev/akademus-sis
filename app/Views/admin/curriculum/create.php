<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <select name="program_id">

    <?php foreach ($programs as $program): ?>
        <option value="<?= $program['program_id'] ?>">
            <?= esc($program['program_name']) ?>
        </option>
    <?php endforeach; ?>

</select>
</body>
</html>
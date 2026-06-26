<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Curriculum</title>
</head>

<body>

    <?php if (session()->has('errors')): ?>

        <div style="color:red">

            <?php foreach (session('errors') as $error): ?>

                <p><?= esc($error) ?></p>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <form
        method="post"
        action="<?= site_url('admin/curriculum/update/' . $curriculum['curriculum_id']) ?>">

        <?= csrf_field() ?>

        <label>Program</label>

        <select name="program_id">

            <?php foreach ($programs as $program): ?>

                <option
                    value="<?= $program['program_id'] ?>"

                    <?= old(
                        'program_id',
                        $curriculum['program_id']
                    ) == $program['program_id']
                        ? 'selected'
                        : '' ?>>

                    <?= esc($program['program_name']) ?>

                </option>

            <?php endforeach; ?>

        </select>

        <br><br>

        <label>Curriculum Code</label>

        <input
            type="text"
            name="curriculum_code"
            value="<?= old('curriculum_code', $curriculum['curriculum_code']) ?>">

        <br><br>

        <label>Curriculum Name</label>

        <input
            type="text"
            name="curriculum_name"
            value="<?= old('curriculum_name', $curriculum['curriculum_name']) ?>">

        <br><br>

        <label>Academic Year</label>

        <input
            type="text"
            name="academic_year"
            value="<?= old('academic_year', $curriculum['academic_year']) ?>">

        <br><br>

        <label>Description</label>

        <textarea
            name="description"><?= old(
                                    'description',
                                    $curriculum['description']
                                ) ?></textarea>

        <br><br>

        <button>

            Update Curriculum

        </button>

    </form>

</body>

</html>
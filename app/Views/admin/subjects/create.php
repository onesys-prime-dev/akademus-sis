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

    <h1>Create Subject</h1>

    <form method="post" action="<?= site_url('admin/subjects/store') ?>">

        <?= csrf_field() ?>

        <label>Subject Code</label>
        <br>
        <input type="text" name="subject_code" value="<?= old('subject_code') ?>">
        <br><br>

        <label>Subject Name</label>
        <br>
        <input type="text" name="subject_name" value="<?= old('subject_name') ?>">
        <br><br>

        <label>Units</label>
        <br>
        <input type="number" step="0.5" name="units" value="<?= old('units', '3') ?>">
        <br><br>

        <label>
            <input type="checkbox" name="is_laboratory" value="1" <?= old('is_laboratory') ? 'checked' : '' ?>>
            Laboratory subject (may only be plotted on Saturdays)
        </label>
        <br><br>

        <label>Lecture Units (optional split, overrides Units above for hour calc)</label>
        <br>
        <input type="number" step="0.5" name="lecture_units" value="<?= old('lecture_units') ?>">
        <br><br>

        <label>Laboratory Units (optional split, overrides Units above for hour calc)</label>
        <br>
        <input type="number" step="0.5" name="laboratory_units" value="<?= old('laboratory_units') ?>">
        <br><br>

        <label>Contact Hours per Meeting (e.g. 1.5)</label>
        <br>
        <input type="number" step="0.5" name="contact_hours" value="<?= old('contact_hours', '1.5') ?>">
        <br><br>

        <label>Meetings per Week (optional target; leave blank for no fixed target)</label>
        <br>
        <input type="number" step="1" name="meetings_per_week" value="<?= old('meetings_per_week') ?>">
        <br><br>

        <label>Description</label>
        <br>
        <textarea name="description"><?= old('description') ?></textarea>
        <br><br>

        <button type="submit">
            Create Subject
        </button>

    </form>
</body>

</html>
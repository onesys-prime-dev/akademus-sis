<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Scheduling Rules</title>
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

    <h1>Academic Scheduling Rules</h1>

    <p>
        These are institution-wide defaults used to derive a subject's
        weekly contact hours when the subject doesn't specify its own
        contact hours / meetings-per-week or lecture/laboratory unit split.
        A subject's own explicit values always override these.
    </p>

    <form method="post" action="<?= site_url('admin/academic-settings/update') ?>">

        <?= csrf_field() ?>

        <label>Lecture Hours per Unit</label>
        <br>
        <input
            type="number"
            step="0.5"
            name="lecture_hours_per_unit"
            value="<?= old('lecture_hours_per_unit', $lectureHoursPerUnit) ?>">
        <br><br>

        <label>Laboratory Hours per Unit</label>
        <br>
        <input
            type="number"
            step="0.5"
            name="lab_hours_per_unit"
            value="<?= old('lab_hours_per_unit', $labHoursPerUnit) ?>">
        <br><br>

        <label>Max Faculty Units per Semester</label>
        <br>
        <input
            type="number"
            step="1"
            name="max_faculty_units"
            value="<?= old('max_faculty_units', $maxFacultyUnits) ?>">
        <br><br>

        <label>Max Meetings per Week (per subject)</label>
        <br>
        <input
            type="number"
            step="1"
            name="max_meetings_per_week"
            value="<?= old('max_meetings_per_week', $maxMeetingsPerWeek) ?>">
        <br><br>

        <label>Part-Time Faculty Max Units (optional; leave blank to use the same cap as full-time)</label>
        <br>
        <input
            type="number"
            step="1"
            name="part_time_max_units"
            value="<?= old('part_time_max_units', $partTimeMaxUnits) ?>">
        <br><br>

        <button type="submit">
            Save Rules
        </button>

    </form>
</body>

</html>

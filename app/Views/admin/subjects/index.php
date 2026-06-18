<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <h1>Subjects</h1>

    <a href="<?= site_url('admin/subjects/create') ?>">
        Add Subject
    </a>

    <table border="1">
        <tr>
            <th>ID</th>
            <th>Code</th>
            <th>Name</th>
            <th>Units</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($subjects as $subject): ?>
            <tr>
                <td><?= esc($subject['subject_id']) ?></td>
                <td><?= esc($subject['subject_code']) ?></td>
                <td><?= esc($subject['subject_name']) ?></td>
                <td><?= esc($subject['units']) ?></td>
                <td><?= esc($subject['description']) ?></td>
                <td>
                    <a href="<?= site_url('admin/subjects/edit/' . $subject['subject_id']) ?>">
                        Edit
                    </a>

                    <form method="post"
                        action="<?= site_url('admin/subjects/delete/' . $subject['subject_id']) ?>"
                        style="display:inline;">

                        <?= csrf_field() ?>

                        <button type="submit" onclick="return confirm('Deactivate this record? ')">
                            Delete
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>

</html>
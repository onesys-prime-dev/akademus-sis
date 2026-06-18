<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <?php if (session()->has('message')): ?>
        <div style="color:green;">
            <?= session('message') ?>
        </div>
    <?php endif ?>
    
    <h1>Programs</h1>

    <a href="<?= site_url('admin/programs/create') ?>">
        Add Program
    </a>

    <table border="1">
        <tr>
            <th>ID</th>
            <th>Code</th>
            <th>Name</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($programs as $program): ?>
            <tr>
                <td><?= esc($program['program_id']) ?></td>
                <td><?= esc($program['program_code']) ?></td>
                <td><?= esc($program['program_name']) ?></td>
                <td><?= esc($program['description']) ?></td>
                <td>
                    <a href="<?= site_url('admin/programs/edit/' . $program['program_id']) ?>">
                        Edit
                    </a>

                    <form method="post"
                        action="<?= site_url('admin/programs/delete/' . $program['program_id']) ?>"
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
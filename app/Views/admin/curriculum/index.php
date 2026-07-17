<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Curricula</title>
</head>

<body>

    <?php if (session()->has('message')): ?>

        <div style="color:green;">
            <?= session('message') ?>
        </div>

    <?php endif; ?>

    <h1>Curricula</h1>

    <a href="<?= site_url('admin/curriculum/create') ?>">
        Add Curriculum
    </a>

    <br><br>

    <table border="1" cellpadding="8">

        <tr>

            <th>Code</th>

            <th>Curriculum</th>

            <th>Program</th>

            <th>Academic Year</th>

            <th>Status</th>

            <th>Actions</th>

        </tr>

        <?php foreach ($curriculum as $item): ?>

            <tr>

                <td><?= esc($item['curriculum_code']) ?></td>

                <td><?= esc($item['curriculum_name']) ?></td>

                <td><?= esc($item['program_name']) ?></td>

                <td><?= esc($item['academic_year']) ?></td>

                <td>

                    <?php if ($item['is_active']): ?>

                        <span style="color:green">

                            Active

                        </span>

                    <?php else: ?>

                        <span style="color:red">

                            Inactive

                        </span>

                    <?php endif; ?>

                </td>

                <td>

                    <a href="<?= site_url('admin/curriculum/edit/' . $item['curriculum_id']) ?>">

                        Edit

                    </a>

                    <form
                        method="post"
                        action="<?= site_url('admin/curriculum/toggle/' . $item['curriculum_id']) ?>"
                        style="display:inline;">

                        <?= csrf_field() ?>

                        <button
                            type="submit"
                            onclick="return confirm('Change curriculum status?')">

                            <?= $item['is_active']
                                ? 'Deactivate'
                                : 'Activate' ?>

                        </button>

                    </form>

                </td>

            </tr>

        <?php endforeach; ?>

    </table>

</body>

</html>
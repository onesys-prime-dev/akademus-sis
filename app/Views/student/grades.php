<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Grades</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/tokens.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/base.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/base.css') ?>">
    <style>
        <?= $this->include('components/shell/style') ?>

        table.grade-table { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        table.grade-table th, table.grade-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--outline-variant); font-size: 13px; }
        .pass-pill { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .pass-pill.pass { background: #dcfce7; color: #14532d; }
        .pass-pill.fail { background: #fee2e2; color: #7f1d1d; }
    </style>
</head>

<body>

    <?php
    $activePage  = 'grades';
    $pageTitle   = 'My Grades';
    $userName    = auth()->user()->username;
    $userRole    = 'Student';
    $settingsUrl = site_url('student/profile');
    $this->setData(compact('activePage', 'pageTitle', 'userName', 'userRole', 'settingsUrl'));
    ?>

    <?= $this->include('components/shell/student_nav') ?>

    <div class="app-main">

        <?= $this->include('components/shell/topbar') ?>

        <div class="content">

            <div class="panel">
                <?php if (empty($byYear)): ?>
                    <div class="empty-state">
                        <div class="material-symbols-outlined">grade</div>
                        <p>No official grades have been posted yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($byYear as $year => $grades): ?>
                        <h2><?= esc($year) ?></h2>
                        <table class="grade-table">
                            <thead>
                                <tr><th>Subject</th><th>Grade</th><th>Remarks</th><th>Result</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grades as $g): ?>
                                    <tr>
                                        <td><?= esc($g['subject_code']) ?></td>
                                        <td><?= esc($g['grade_value']) ?></td>
                                        <td><?= $g['remarks'] !== null ? esc($g['remarks']) : '—' ?></td>
                                        <td>
                                            <?php if ($g['is_passing'] === null): ?>
                                                —
                                            <?php elseif ($g['is_passing']): ?>
                                                <span class="pass-pill pass">Passed</span>
                                            <?php else: ?>
                                                <span class="pass-pill fail">Failed</span>
                                            <?php endif ?>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    <?php endforeach ?>
                <?php endif ?>
            </div>

        </div>

    </div>

</body>

</html>

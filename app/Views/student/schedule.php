<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Schedule</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        <?= $this->include('components/shell/style') ?>
    </style>
</head>

<body>

    <?php
    $activePage  = 'schedule';
    $pageTitle   = 'My Schedule';
    $userName    = auth()->user()->username;
    $userRole    = 'Student';
    $settingsUrl = site_url('student/profile');
    $this->setData(compact('activePage', 'pageTitle', 'userName', 'userRole', 'settingsUrl'));
    ?>

    <?= $this->include('components/shell/student_nav') ?>

    <div class="app-main">

        <?= $this->include('components/shell/topbar') ?>

        <div class="content">

            <?php
            $days  = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $byDay = array_fill(0, 6, []);

            foreach ($meetings as $meeting) {
                $byDay[(int) $meeting['day']][] = $meeting;
            }

            foreach ($byDay as &$dayMeetings) {
                usort($dayMeetings, static fn (array $a, array $b) => strcmp($a['start_time'], $b['start_time']));
            }
            unset($dayMeetings);
            ?>

            <div class="panel">
                <?php if ($meetings === []): ?>
                    <div class="empty-state">
                        <div class="material-symbols-outlined">event_busy</div>
                        <p>You're not enrolled in any classes yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($byDay as $dayIndex => $dayMeetings): ?>
                        <?php if ($dayMeetings === []) continue; ?>
                        <div class="day-heading"><?= esc($days[$dayIndex]) ?></div>
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Room</th>
                                    <th>Faculty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dayMeetings as $m): ?>
                                    <tr>
                                        <td><?= esc($m['time_label']) ?></td>
                                        <td><?= esc($m['subject_code']) ?> &mdash; <?= esc($m['subject_title']) ?></td>
                                        <td><?= esc($m['room']) ?></td>
                                        <td><?= esc($m['prof'] ?? '—') ?></td>
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

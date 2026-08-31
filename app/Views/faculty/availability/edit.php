<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Availability &amp; Preferences</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 760px; margin: 32px auto; padding: 0 16px; color: #1a2233; }
        h1 { margin-bottom: 4px; }
        .sub { color: #515f74; margin-top: 0; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px; }
        .day-row { display: grid; grid-template-columns: 90px 160px 1fr 1fr; gap: 10px; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
        .day-row:last-child { border-bottom: none; }
        select, input[type=time], input[type=text] { padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; }
        .subject-row { display: grid; grid-template-columns: 140px 1fr 220px; gap: 10px; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
        .subject-row:last-child { border-bottom: none; }
        button { background: #0037b0; color: #fff; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        button:hover { background: #002a87; }
        .msg-ok { color: #15803d; background: #f0fdf4; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; }
        .msg-err { color: #b91c1c; background: #fef2f2; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; }
        .notice { background: #eff6ff; color: #1d4ed8; padding: 12px 16px; border-radius: 8px; }
        a.back { color: #0037b0; text-decoration: none; font-size: 13px; }
    </style>
</head>

<body>

    <a class="back" href="<?= site_url('faculty/dashboard') ?>">&larr; Back to Dashboard</a>
    <h1>Availability &amp; Subject Preferences</h1>
    <p class="sub"><?= $termLabel ? 'Submitting for ' . esc($termLabel) : 'Submit before the upcoming semester so your chairperson can see it while plotting.' ?></p>

    <?php if (session()->has('message')): ?>
        <div class="msg-ok"><?= esc(session('message')) ?></div>
    <?php endif ?>

    <?php if (session()->has('errors')): ?>
        <div class="msg-err">
            <?php foreach (session('errors') as $error): ?>
                <p style="margin:0;"><?= esc($error) ?></p>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if ($term === null): ?>
        <div class="notice">No upcoming term has been configured yet. Ask an admin to add the next academic year/semester before you can submit your availability.</div>
    <?php else: ?>

        <div class="card">
            <h2 style="margin-top:0;">Weekly Availability</h2>
            <p class="sub" style="margin-bottom:16px;">For each day, mark whether you're available, and if so, your time range. Leave a day as "No preference" if you'd rather not commit either way yet.</p>

            <form method="post" action="<?= site_url('faculty/availability/update') ?>">
                <?= csrf_field() ?>

                <?php foreach ($dayLabels as $day => $label): ?>
                    <?php $row = $availabilityByDay[$day] ?? null; ?>
                    <?php $status = $row ? ($row['is_available'] ? 'available' : 'unavailable') : ''; ?>
                    <div class="day-row">
                        <strong><?= esc($label) ?></strong>
                        <select name="day_<?= $day ?>_status" class="day-status" data-day="<?= $day ?>" onchange="toggleTimes(<?= $day ?>)">
                            <option value="" <?= $status === '' ? 'selected' : '' ?>>No preference</option>
                            <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="unavailable" <?= $status === 'unavailable' ? 'selected' : '' ?>>Not Available</option>
                        </select>
                        <input type="time" name="day_<?= $day ?>_start" id="start_<?= $day ?>" value="<?= esc($row['start_time'] ?? '') ?>">
                        <input type="time" name="day_<?= $day ?>_end" id="end_<?= $day ?>" value="<?= esc($row['end_time'] ?? '') ?>">
                    </div>
                <?php endforeach ?>

                <div style="margin-top:16px;">
                    <button type="submit">Save Availability</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Subject Preferences</h2>
            <p class="sub" style="margin-bottom:16px;">Let your chairperson know which subjects you'd like to teach this term.</p>

            <?php if (empty($subjects)): ?>
                <p class="sub">No subjects found for your department yet.</p>
            <?php else: ?>
                <form method="post" action="<?= site_url('faculty/preferences/update') ?>">
                    <?= csrf_field() ?>

                    <?php foreach ($subjects as $subject): ?>
                        <?php $current = $preferencesById[$subject['subject_id']] ?? ''; ?>
                        <div class="subject-row">
                            <strong><?= esc($subject['subject_code']) ?></strong>
                            <span><?= esc($subject['subject_name']) ?></span>
                            <select name="preference[<?= $subject['subject_id'] ?>]">
                                <option value="" <?= $current === '' ? 'selected' : '' ?>>Not set</option>
                                <?php foreach ($preferenceLevels as $level): ?>
                                    <option value="<?= $level ?>" <?= $current === $level ? 'selected' : '' ?>><?= esc(ucfirst($level)) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    <?php endforeach ?>

                    <div style="margin-top:16px;">
                        <button type="submit">Save Preferences</button>
                    </div>
                </form>
            <?php endif ?>
        </div>

    <?php endif ?>

    <script>
        function toggleTimes(day) {
            var status = document.querySelector('.day-status[data-day="' + day + '"]').value;
            var disabled = status !== 'available';
            document.getElementById('start_' + day).disabled = disabled;
            document.getElementById('end_' + day).disabled = disabled;
            if (disabled) {
                document.getElementById('start_' + day).value = '';
                document.getElementById('end_' + day).value = '';
            }
        }
        document.querySelectorAll('.day-status').forEach(function (el) {
            toggleTimes(el.dataset.day);
        });
    </script>

</body>

</html>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 60px 50px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1a1a1a; position: relative; }
    .watermark { position: fixed; top: 300px; left: 80px; font-size: 64px; color: #f0f0f0; transform: rotate(-30deg); z-index: -1; }
    .header { text-align: center; margin-bottom: 18px; }
    .header h1 { font-size: 16px; margin: 0 0 2px; letter-spacing: 1px; }
    .header p { margin: 0; font-size: 11px; color: #555; }
    .title { text-align: center; font-size: 13px; font-weight: bold; text-decoration: underline; margin: 18px 0; }
    table.info { width: 100%; margin-bottom: 14px; }
    table.info td { padding: 2px 4px; font-size: 10.5px; }
    table.grades { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.grades th, table.grades td { border: 1px solid #999; padding: 4px 6px; font-size: 9.5px; }
    table.grades th { background: #eee; }
    .footer { position: fixed; bottom: 30px; left: 50px; right: 50px; font-size: 8.5px; color: #555; display: table; width: 100%; }
    .footer .qr { float: right; }
    .footer .code { float: left; margin-top: 30px; }
</style>
</head>
<body>
    <div class="watermark">OFFICIAL</div>

    <div class="header">
        <h1>AKADEMUS INSTITUTE</h1>
        <p>Office of the Registrar &middot; Transcript of Records</p>
    </div>

    <div class="title">TRANSCRIPT OF RECORDS</div>

    <table class="info">
        <tr><td><b>Student Name:</b></td><td><?= esc(trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name'])) ?></td>
            <td><b>Student No.:</b></td><td><?= esc($student['student_number']) ?></td></tr>
        <tr><td><b>Program:</b></td><td><?= esc($student['program_name'] ?? 'N/A') ?></td>
            <td><b>Curriculum:</b></td><td><?= esc($student['curriculum_code'] ?? 'N/A') ?></td></tr>
        <tr><td><b>Status:</b></td><td><?= esc(ucfirst($student['student_status'])) ?></td>
            <td><b>Issued:</b></td><td><?= esc($issuedAt) ?></td></tr>
    </table>

    <table class="grades">
        <thead>
            <tr><th>Academic Year</th><th>Subject</th><th>Units</th><th>Grade</th><th>Remarks</th></tr>
        </thead>
        <tbody>
            <?php if (empty($grades)): ?>
                <tr><td colspan="5" style="text-align:center;">No locked grades on record.</td></tr>
            <?php endif ?>
            <?php foreach ($grades as $g): ?>
                <tr>
                    <td><?= esc($g['academic_year']) ?></td>
                    <td><?= esc($g['subject_name'] ?? $g['subject_code']) ?></td>
                    <td><?= esc($g['units'] ?? '—') ?></td>
                    <td><?= esc($g['grade_value']) ?></td>
                    <td><?= $g['is_passing'] ? 'Passed' : 'Failed' ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

    <div class="footer">
        <div class="qr"><img src="<?= $qrDataUri ?>" width="70" height="70"></div>
        <div class="code">Verification code: <?= esc($verificationCode) ?><br>Verify at: /verify/<?= esc($verificationCode) ?></div>
    </div>
</body>
</html>

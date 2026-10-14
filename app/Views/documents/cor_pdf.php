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
    table.subjects { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.subjects th, table.subjects td { border: 1px solid #999; padding: 4px 6px; font-size: 9.5px; }
    table.subjects th { background: #eee; }
    .status-line { margin-top: 14px; font-size: 11px; }
    .status-line b.paid { color: #166534; }
    .status-line b.partial { color: #92400e; }
    .footer { position: fixed; bottom: 30px; left: 50px; right: 50px; font-size: 8.5px; color: #555; display: table; width: 100%; }
    .footer .qr { float: right; }
    .footer .code { float: left; margin-top: 30px; }
</style>
</head>
<body>
    <div class="watermark">OFFICIAL</div>

    <div class="header">
        <h1>AKADEMUS INSTITUTE</h1>
        <p>Office of the Registrar &middot; Certificate of Registration</p>
    </div>

    <div class="title">CERTIFICATE OF REGISTRATION</div>

    <table class="info">
        <tr><td><b>Student Name:</b></td><td><?= esc(trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name'])) ?></td>
            <td><b>Student No.:</b></td><td><?= esc($student['student_number']) ?></td></tr>
        <tr><td><b>Program:</b></td><td><?= esc($student['program_name'] ?? 'N/A') ?></td>
            <td><b>Academic Year:</b></td><td><?= esc($assessment['academic_year'] ?? 'N/A') ?></td></tr>
        <tr><td><b>Status:</b></td><td><?= esc(ucfirst($student['student_status'])) ?></td>
            <td><b>Issued:</b></td><td><?= esc($issuedAt) ?></td></tr>
    </table>

    <table class="subjects">
        <thead>
            <tr><th>Subject</th><th>Title</th><th>Units</th><th>Year/Section</th></tr>
        </thead>
        <tbody>
            <?php if (empty($enrollments)): ?>
                <tr><td colspan="4" style="text-align:center;">No enrolled subjects on record for this term.</td></tr>
            <?php endif ?>
            <?php $totalUnits = 0; ?>
            <?php foreach ($enrollments as $e): ?>
                <?php $totalUnits += (float) ($e['units'] ?? 0); ?>
                <tr>
                    <td><?= esc($e['subject_code']) ?></td>
                    <td><?= esc($e['subject_name'] ?? '—') ?></td>
                    <td><?= esc($e['units'] ?? '—') ?></td>
                    <td><?= esc($e['year_level']) ?> &middot; <?= esc($e['section']) ?></td>
                </tr>
            <?php endforeach ?>
            <?php if (! empty($enrollments)): ?>
                <tr><td colspan="2" style="text-align:right;"><b>Total Units:</b></td><td colspan="2"><b><?= esc((string) $totalUnits) ?></b></td></tr>
            <?php endif ?>
        </tbody>
    </table>

    <p class="status-line">
        <b>Account Status:</b>
        <?php if (($assessment['status'] ?? null) === 'paid'): ?>
            <b class="paid">Fully Paid</b> — &#8369;<?= esc(number_format((float) $assessment['amount_paid'], 2)) ?> of &#8369;<?= esc(number_format((float) $assessment['total_amount'], 2)) ?>
        <?php elseif ($assessment !== null): ?>
            <b class="partial">Partial Payment</b> — &#8369;<?= esc(number_format((float) $assessment['amount_paid'], 2)) ?> of &#8369;<?= esc(number_format((float) $assessment['total_amount'], 2)) ?> paid
        <?php else: ?>
            N/A
        <?php endif ?>
    </p>

    <div class="footer">
        <div class="qr"><img src="<?= $qrDataUri ?>" width="70" height="70"></div>
        <div class="code">Verification code: <?= esc($verificationCode) ?><br>Verify at: /verify/<?= esc($verificationCode) ?></div>
    </div>
</body>
</html>

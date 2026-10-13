<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 24px 20px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }
    .header { text-align: center; margin-bottom: 10px; }
    .header h1 { font-size: 13px; margin: 0 0 2px; letter-spacing: 1px; }
    .header p { margin: 0; font-size: 9px; color: #555; }
    .or-number { text-align: center; font-size: 13px; font-weight: bold; margin: 10px 0; border: 1px solid #333; padding: 6px; }
    table.info { width: 100%; margin-bottom: 10px; }
    table.info td { padding: 2px 0; font-size: 9.5px; }
    .amount-box { text-align: center; font-size: 16px; font-weight: bold; border-top: 1px dashed #333; border-bottom: 1px dashed #333; padding: 10px 0; margin: 10px 0; }
    .footer { margin-top: 20px; font-size: 8.5px; color: #555; text-align: center; }
</style>
</head>
<body>
    <div class="header">
        <h1>AKADEMUS INSTITUTE</h1>
        <p>Office of Finance &amp; Accounting</p>
    </div>

    <div class="or-number">OFFICIAL RECEIPT<br><?= esc($orNumber) ?></div>

    <table class="info">
        <tr><td><b>Date:</b></td><td><?= esc($issuedAt) ?></td></tr>
        <tr><td><b>Received From:</b></td><td><?= esc(trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name'])) ?></td></tr>
        <tr><td><b>Student No.:</b></td><td><?= esc($student['student_number']) ?></td></tr>
        <tr><td><b>Method:</b></td><td><?= esc(ucfirst(str_replace('_', ' ', $payment['method']))) ?></td></tr>
        <tr><td><b>Reference No.:</b></td><td><?= esc($payment['reference_no'] ?? '—') ?></td></tr>
    </table>

    <div class="amount-box">₱ <?= number_format((float) $payment['amount'], 2) ?></div>

    <div class="footer">
        This receipt is computer-generated and valid without a signature.<br>
        Keep this for your records.
    </div>
</body>
</html>

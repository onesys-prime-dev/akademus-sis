<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 70px 60px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
    .watermark { position: fixed; top: 300px; left: 100px; font-size: 64px; color: #f0f0f0; transform: rotate(-30deg); z-index: -1; }
    .header { text-align: center; margin-bottom: 24px; }
    .header h1 { font-size: 17px; margin: 0 0 2px; letter-spacing: 1px; }
    .header p { margin: 0; font-size: 11px; color: #555; }
    .title { text-align: center; font-size: 15px; font-weight: bold; text-decoration: underline; margin: 28px 0; letter-spacing: 1px; }
    .body-text { line-height: 1.9; font-size: 12.5px; text-align: justify; margin: 0 20px; }
    .signature { margin-top: 70px; text-align: center; }
    .signature .line { border-top: 1px solid #333; width: 220px; margin: 0 auto 4px; }
    .footer { position: fixed; bottom: 30px; left: 60px; right: 60px; font-size: 8.5px; color: #555; }
    .footer .qr { float: right; }
    .footer .code { float: left; margin-top: 30px; }
</style>
</head>
<body>
    <div class="watermark">OFFICIAL</div>

    <div class="header">
        <h1>AKADEMUS INSTITUTE</h1>
        <p>Office of the Registrar</p>
    </div>

    <div class="title"><?= esc(strtoupper($typeLabel)) ?></div>

    <p class="body-text">
        This is to certify that <b><?= esc(trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name'])) ?></b>,
        with Student Number <b><?= esc($student['student_number']) ?></b>,
        <?= esc($student['program_name'] ?? 'N/A') ?>,
        <?php if ($type === 'certificate_enrollment'): ?>
            is currently enrolled at this institution as of <?= esc($issuedAt) ?>.
        <?php elseif ($type === 'certificate_graduation'): ?>
            has satisfactorily completed all academic requirements and is a graduate of this institution.
        <?php elseif ($type === 'good_moral'): ?>
            has, to the best of the Office of the Registrar's knowledge, maintained good moral character during their stay at this institution.
        <?php elseif ($type === 'certificate_candidacy'): ?>
            is a candidate for graduation, pending completion of all remaining academic and clearance requirements.
        <?php endif ?>
    </p>

    <p class="body-text">
        This certification is issued upon the student's request for whatever legal purpose it may serve.
    </p>

    <div class="signature">
        <div class="line"></div>
        <div>Office of the Registrar</div>
        <div style="margin-top:14px;">Issued: <?= esc($issuedAt) ?></div>
    </div>

    <div class="footer">
        <div class="qr"><img src="<?= $qrDataUri ?>" width="70" height="70"></div>
        <div class="code">Verification code: <?= esc($verificationCode) ?><br>Verify at: /verify/<?= esc($verificationCode) ?></div>
    </div>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Document Verification &mdash; Akademus Institute</title>
<style>
    body { font-family: system-ui, sans-serif; background: #f4f4f5; margin: 0; padding: 40px 16px; display: flex; justify-content: center; }
    .card { background: #fff; border-radius: 12px; padding: 32px; max-width: 420px; width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }
    .icon { font-size: 48px; margin-bottom: 12px; }
    h1 { font-size: 18px; margin: 0 0 8px; }
    p { color: #52525b; font-size: 14px; margin: 4px 0; }
    .valid .icon { color: #16a34a; }
    .invalid .icon { color: #dc2626; }
</style>
</head>
<body>
    <div class="card <?= $result !== null ? 'valid' : 'invalid' ?>">
        <?php if ($result !== null): ?>
            <div class="icon">&#10003;</div>
            <h1>Valid Document</h1>
            <p><?= esc($result['document_type']) ?></p>
            <p>Issued <?= esc(date('F j, Y', strtotime($result['issued_at']))) ?></p>
        <?php else: ?>
            <div class="icon">&#10007;</div>
            <h1>Not Found</h1>
            <p>This verification code does not match any issued document.</p>
        <?php endif ?>
    </div>
</body>
</html>

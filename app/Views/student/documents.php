<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'documents';
$pageTitle     = 'Documents';
$brandSub      = 'Student Portal';
$userName      = auth()->user()->username;
$userRoleLabel = 'Student';
$navGroups     = [
    '' => [
        'dashboard' => ['label' => 'Dashboard',   'icon' => 'home',           'url' => 'student/dashboard'],
        'profile'   => ['label' => 'Profile',     'icon' => 'person',         'url' => 'student/profile'],
        'tuition'   => ['label' => 'Tuition & Fees', 'icon' => 'payments',    'url' => 'student/tuition'],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule'],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades'],
        'enlistment' => ['label' => 'Enlistment', 'icon' => 'playlist_add_check', 'url' => 'student/enlistment'],
        'documents' => ['label' => 'Documents',   'icon' => 'description',    'url' => 'student/documents'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Documents</h2><p class="ac-page-header__sub">Request official documents and track their status.</p></div>
</div>

<?php if (session('errors')): ?>
    <div class="ac-alert" style="background:var(--color-danger-soft); margin-bottom:16px;">
        <div class="ac-alert__text" style="color:var(--color-danger);">
            <?php foreach (session('errors') as $error): ?><p><?= esc($error) ?></p><?php endforeach ?>
        </div>
    </div>
<?php endif ?>
<?php if (session('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;"><span class="ac-notice__text"><?= esc(session('message')) ?></span></div>
<?php endif ?>

<div class="ac-card" style="margin-bottom:20px; max-width:520px;">
    <div class="ac-dept-name">Certificate of Registration</div>
    <p style="font-size:12.5px; color:var(--color-text-secondary);">Available once you're enrolled for the current term and have made at least a downpayment.</p>

    <?php if ($corEligible): ?>
        <form method="post" action="<?= site_url('student/documents/request-cor') ?>" style="display:grid; gap:8px; margin-top:10px;">
            <?= csrf_field() ?>
            <input type="text" name="purpose" class="ac-input" placeholder="Purpose (optional)">
            <button type="submit" class="ac-btn ac-btn-primary">Request COR</button>
        </form>
    <?php else: ?>
        <div class="ac-empty-block" style="margin-top:10px;"><?= esc($corReason) ?></div>
    <?php endif ?>
</div>

<div class="ac-card" style="margin-bottom:20px; max-width:520px;">
    <div class="ac-dept-name">Certificate of Enrollment</div>
    <p style="font-size:12.5px; color:var(--color-text-secondary);">Available once you're officially enrolled for the current term — an outstanding balance does not block this one.</p>

    <?php if ($coeEligible): ?>
        <form method="post" action="<?= site_url('student/documents/request-coe') ?>" style="display:grid; gap:8px; margin-top:10px;">
            <?= csrf_field() ?>
            <input type="text" name="purpose" class="ac-input" placeholder="Purpose (optional)">
            <button type="submit" class="ac-btn ac-btn-primary">Request COE</button>
        </form>
    <?php else: ?>
        <div class="ac-empty-block" style="margin-top:10px;"><?= esc($coeReason) ?></div>
    <?php endif ?>
</div>

<div class="ac-card" style="margin-bottom:20px;">
    <div class="ac-dept-name">My Requests</div>
    <?php if ($requests === []): ?>
        <div class="ac-empty-block">No document requests yet.</div>
    <?php else: ?>
        <table class="ac-table">
            <thead><tr><th>Document</th><th>Purpose</th><th>Status</th><th>Requested</th></tr></thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?= esc(\App\Models\DocumentRequestModel::TYPE_LABELS[$r['document_type']] ?? $r['document_type']) ?></td>
                        <td><?= esc($r['purpose'] ?? '—') ?></td>
                        <td><span class="ac-pill <?= $r['status'] === 'released' ? 'ac-pill--success' : 'ac-pill--warning' ?>"><?= esc(ucfirst($r['status'])) ?></span></td>
                        <td><?= esc(date('M j, Y', strtotime($r['created_at']))) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<div class="ac-card">
    <div class="ac-dept-name">My Documents</div>
    <?php if ($generated === []): ?>
        <div class="ac-empty-block">No documents issued yet.</div>
    <?php else: ?>
        <table class="ac-table">
            <thead><tr><th>Document</th><th>Issued</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($generated as $g): ?>
                    <tr>
                        <td><?= esc(\App\Models\DocumentRequestModel::TYPE_LABELS[$g['document_type']] ?? $g['document_type']) ?></td>
                        <td><?= esc(date('M j, Y', strtotime($g['generated_at']))) ?></td>
                        <td><a href="<?= site_url('student/documents/download/' . $g['id']) ?>" class="ac-btn-outline">Download</a></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<?= $this->endSection() ?>

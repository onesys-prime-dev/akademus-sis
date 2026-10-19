<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'documents';
$pageTitle     = 'Documents';
$brandSub      = 'Student Portal';
$userName      = auth()->user()->username;
$userRoleLabel = 'Student';
$navGroups     = [
    '' => [
        'dashboard' => ['label' => 'Dashboard',   'icon' => 'home',           'url' => 'student/dashboard', 'help' => [
            'title' => 'Student Dashboard',
            'text'  => "Your at-a-glance view: today's schedule, upcoming classes, and recent grade/tuition activity. Use the sidebar to get to any other part of your student portal.",
        ]],
        'tuition'   => ['label' => 'Tuition & Fees', 'icon' => 'payments',    'url' => 'student/tuition', 'help' => [
            'title' => 'Tuition & Fees',
            'text'  => "Your current assessment, payment history, and balance. Upload proof of an off-site payment here for Finance to review, or see your installment schedule.",
        ]],
        'schedule'  => ['label' => 'My Schedule', 'icon' => 'calendar_month', 'url' => 'student/schedule', 'help' => [
            'title' => 'My Schedule',
            'text'  => "Your weekly class schedule for the current term — days, times, rooms, and instructors for everything you're enrolled in.",
        ]],
        'grades'    => ['label' => 'My Grades',   'icon' => 'grade',          'url' => 'student/grades', 'help' => [
            'title' => 'My Grades',
            'text'  => "Your official grades, term by term. A grade only appears here once it's been locked by the registrar.",
        ]],
        'enlistment' => ['label' => 'Enlistment', 'icon' => 'playlist_add_check', 'url' => 'student/enlistment', 'help' => [
            'title' => 'Enlistment',
            'text'  => "Select your subjects for next term and submit them for approval. Only available while your department's enlistment window is open; otherwise you'll enroll directly once sections open.",
        ]],
        'documents' => ['label' => 'Documents',   'icon' => 'description',    'url' => 'student/documents', 'help' => [
            'title' => 'Documents',
            'text'  => "Request a Certificate of Registration (needs at least a downpayment) or Certificate of Enrollment (available once you're enrolled, balance or not), and track their status.",
        ]],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/student-documents.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/student-documents.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="sd-page" id="studentDocuments">
    <header class="sd-hero">
        <div>
            <span class="sd-eyebrow">OFFICIAL DOCUMENTS</span>
            <h1>Documents</h1>
            <p>Request official documents and track their status.</p>
        </div>
    </header>

    <?php if (session('errors')): ?>
        <div class="sd-notice sd-notice--error" role="alert">
            <?php foreach (session('errors') as $error): ?><p><?= esc($error) ?></p><?php endforeach ?>
        </div>
    <?php endif ?>
    <?php if (session('message')): ?>
        <div class="sd-notice" role="status"><?= esc(session('message')) ?></div>
    <?php endif ?>

    <section class="sd-requests">
        <article class="sd-card sd-request">
            <div class="sd-request__icon"><span class="material-symbols-outlined" aria-hidden="true">badge</span></div>
            <div class="sd-request__body">
                <h2>Certificate of Registration</h2>
                <p>Available once you're enrolled for the current term and have made at least a downpayment.</p>
                <?php if ($corEligible): ?>
                    <form method="post" action="<?= site_url('student/documents/request-cor') ?>" class="sd-request__form">
                        <?= csrf_field() ?>
                        <label class="sd-sr-only" for="sdCorPurpose">Purpose (optional)</label>
                        <input type="text" id="sdCorPurpose" name="purpose" class="sd-input" placeholder="Purpose (optional)">
                        <button type="submit" class="sd-button sd-button--primary">Request COR</button>
                    </form>
                <?php else: ?>
                    <div class="sd-ineligible"><span class="material-symbols-outlined" aria-hidden="true">info</span><span><?= esc($corReason) ?></span></div>
                <?php endif ?>
            </div>
        </article>

        <article class="sd-card sd-request">
            <div class="sd-request__icon"><span class="material-symbols-outlined" aria-hidden="true">how_to_reg</span></div>
            <div class="sd-request__body">
                <h2>Certificate of Enrollment</h2>
                <p>Available once you're officially enrolled for the current term — an outstanding balance does not block this one.</p>
                <?php if ($coeEligible): ?>
                    <form method="post" action="<?= site_url('student/documents/request-coe') ?>" class="sd-request__form">
                        <?= csrf_field() ?>
                        <label class="sd-sr-only" for="sdCoePurpose">Purpose (optional)</label>
                        <input type="text" id="sdCoePurpose" name="purpose" class="sd-input" placeholder="Purpose (optional)">
                        <button type="submit" class="sd-button sd-button--primary">Request COE</button>
                    </form>
                <?php else: ?>
                    <div class="sd-ineligible"><span class="material-symbols-outlined" aria-hidden="true">info</span><span><?= esc($coeReason) ?></span></div>
                <?php endif ?>
            </div>
        </article>
    </section>

    <section class="sd-card sd-table-card">
        <header class="sd-table-head"><div><h2>My Requests</h2><p>Every document request you've submitted, newest first.</p></div></header>
        <?php if ($requests === []): ?>
            <div class="sd-empty"><span class="material-symbols-outlined" aria-hidden="true">inbox</span><p>No document requests yet.</p></div>
        <?php else: ?>
            <div class="sd-table-scroll" role="region" aria-label="My document requests" tabindex="0">
                <table class="sd-table">
                    <thead><tr><th scope="col">Document</th><th scope="col">Purpose</th><th scope="col">Status</th><th scope="col">Requested</th></tr></thead>
                    <tbody>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td data-label="Document"><strong><?= esc(\App\Models\DocumentRequestModel::TYPE_LABELS[$r['document_type']] ?? $r['document_type']) ?></strong></td>
                                <td data-label="Purpose"><?= esc($r['purpose'] ?? '—') ?></td>
                                <td data-label="Status"><span class="sd-pill <?= $r['status'] === 'released' ? 'sd-pill--success' : 'sd-pill--warning' ?>"><?= esc(ucfirst($r['status'])) ?></span></td>
                                <td data-label="Requested"><?= esc(date('M j, Y', strtotime($r['created_at']))) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>

    <section class="sd-card sd-table-card">
        <header class="sd-table-head"><div><h2>My Documents</h2><p>Issued, ready to view or download.</p></div></header>
        <?php if ($generated === []): ?>
            <div class="sd-empty"><span class="material-symbols-outlined" aria-hidden="true">description</span><p>No documents issued yet.</p></div>
        <?php else: ?>
            <div class="sd-table-scroll" role="region" aria-label="My issued documents" tabindex="0">
                <table class="sd-table">
                    <thead><tr><th scope="col">Document</th><th scope="col">Issued</th><th scope="col"><span class="sd-sr-only">Actions</span></th></tr></thead>
                    <tbody>
                        <?php foreach ($generated as $g): ?>
                            <?php $label = \App\Models\DocumentRequestModel::TYPE_LABELS[$g['document_type']] ?? $g['document_type']; ?>
                            <tr>
                                <td data-label="Document"><strong><?= esc($label) ?></strong></td>
                                <td data-label="Issued"><?= esc(date('M j, Y', strtotime($g['generated_at']))) ?></td>
                                <td data-label="Actions" class="sd-table-actions">
                                    <button type="button" class="sd-button sd-button--outline" data-view-doc="<?= (int) $g['id'] ?>" data-view-title="<?= esc($label, 'attr') ?>" data-view-url="<?= site_url('student/documents/view/' . $g['id']) ?>"><span class="material-symbols-outlined" aria-hidden="true">visibility</span>View</button>
                                    <a href="<?= site_url('student/documents/download/' . $g['id']) ?>" class="sd-button sd-button--outline"><span class="material-symbols-outlined" aria-hidden="true">download</span>Download</a>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>
</div>

<dialog class="sd-dialog" id="sdViewDialog" aria-labelledby="sdViewTitle">
    <header class="sd-dialog-header">
        <div><span class="sd-eyebrow">DOCUMENT PREVIEW</span><h2 id="sdViewTitle"></h2></div>
        <div class="sd-dialog-actions">
            <a class="sd-button sd-button--outline" id="sdViewDownload" download><span class="material-symbols-outlined" aria-hidden="true">download</span>Download</a>
            <button type="button" class="sd-icon-button" id="sdViewClose" aria-label="Close preview"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
        </div>
    </header>
    <div class="sd-dialog-body">
        <div class="sd-dialog-loading" id="sdViewLoading"><span class="sd-spinner" aria-hidden="true"></span><span id="sdViewLoadingText">Loading document…</span></div>
        <iframe id="sdViewFrame" title="Document preview"></iframe>
    </div>
</dialog>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/student-documents.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/student-documents.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'profile';
$pageTitle     = 'Profile';
$brandSub      = 'Student Portal';
$userName      = $account->username;
$userRoleLabel = $section !== null ? "{$section['program']} · {$section['year_level']}" : 'Student';
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
            'text'  => "Build your subject cart for next term and submit it for chair → dean → registrar approval. Only available while your department's enlistment window is open; otherwise you'll enroll directly once sections open.",
        ]],
        'documents' => ['label' => 'Documents',   'icon' => 'description',        'url' => 'student/documents', 'help' => [
            'title' => 'Documents',
            'text'  => "Request a Certificate of Registration (needs at least a downpayment) or Certificate of Enrollment (available once you're enrolled, balance or not), and track their status.",
        ]],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Profile</h2></div>
</div>

<?php if (session('profile_message')): ?>
    <div class="flash success"><?= esc(session('profile_message')) ?></div>
<?php endif ?>
<?php if (session('profile_error')): ?>
    <div class="flash error"><?= esc(session('profile_error')) ?></div>
<?php endif ?>

<div class="panel">
    <div class="avatar-row">
        <div class="avatar"><?= esc(mb_strtoupper(mb_substr($account->username, 0, 1))) ?></div>
        <div>
            <div class="avatar-name"><?= esc($account->username) ?></div>
            <div class="avatar-role">
                <?= $section !== null ? esc($section['program']) . ' · ' . esc($section['year_level']) . ' · Section ' . esc($section['section']) : 'No section assigned' ?>
            </div>
        </div>
    </div>

    <form method="post" action="<?= site_url('student/profile/update') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>Display Name</label>
            <input type="text" name="username" value="<?= esc($account->username) ?>" maxlength="100" required>
            <div class="hint">Shown across the portal (sidebar, headers) — doesn't have to be your legal name.</div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>First Name</label>
                <input type="text" name="first_name" value="<?= esc(old('first_name', $studentDetail['first_name'] ?? '')) ?>" maxlength="100">
            </div>
            <div class="field">
                <label>Middle Name</label>
                <input type="text" name="middle_name" value="<?= esc(old('middle_name', $studentDetail['middle_name'] ?? '')) ?>" maxlength="100">
            </div>
            <div class="field">
                <label>Last Name</label>
                <input type="text" name="last_name" value="<?= esc(old('last_name', $studentDetail['last_name'] ?? '')) ?>" maxlength="100">
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Mobile Number</label>
                <input type="text" name="contact_number" value="<?= esc(old('contact_number', $studentDetail['contact_number'] ?? '')) ?>" maxlength="30" placeholder="e.g. 09171234567">
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" value="<?= esc($account->email) ?>" disabled>
                <div class="hint">Email is your login and can't be changed here — contact an administrator.</div>
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" value="<?= esc(old('date_of_birth', $studentDetail['date_of_birth'] ?? '')) ?>">
            </div>
            <div class="field">
                <label>Gender</label>
                <input type="text" name="gender" value="<?= esc(old('gender', $studentDetail['gender'] ?? '')) ?>" maxlength="20" placeholder="e.g. Male, Female">
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Home Address</label>
                <input type="text" name="home_address" value="<?= esc(old('home_address', $studentDetail['home_address'] ?? '')) ?>" maxlength="255">
            </div>
            <div class="field">
                <label>City</label>
                <input type="text" name="city" value="<?= esc(old('city', $studentDetail['city'] ?? '')) ?>" maxlength="100">
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" value="<?= esc(old('emergency_contact_name', $studentDetail['emergency_contact_name'] ?? '')) ?>" maxlength="150">
            </div>
            <div class="field">
                <label>Emergency Contact Number</label>
                <input type="text" name="emergency_contact_number" value="<?= esc(old('emergency_contact_number', $studentDetail['emergency_contact_number'] ?? '')) ?>" maxlength="20">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
</div>

<div class="panel">
    <h2>Change Password</h2>
    <div class="panel-sub">Choose a new password of at least 8 characters.</div>

    <form method="post" action="<?= site_url('student/profile/password') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label>Current Password</label>
            <input type="password" name="current_password" required>
        </div>
        <div class="field-row">
            <div class="field">
                <label>New Password</label>
                <input type="password" name="new_password" minlength="8" required>
            </div>
            <div class="field">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" minlength="8" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Change Password</button>
    </form>
</div>

<div class="panel">
    <h2>Academic Information</h2>
    <p class="panel-sub">Read-only — the same record shown on your Enlistment page. Contact the registrar to correct anything here.</p>
    <?php if ($studentDetail === null): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>No student record is linked to your account yet. Contact an administrator.</p>
        </div>
    <?php else: ?>
        <div class="field-row">
            <div class="field">
                <label>Student No.</label>
                <input type="text" value="<?= esc($studentDetail['student_number'] ?? '—') ?>" disabled>
            </div>
            <div class="field">
                <label>Status</label>
                <input type="text" value="<?= esc($studentDetail['student_status'] !== null ? ucfirst($studentDetail['student_status']) : '—') ?>" disabled>
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Program</label>
                <input type="text" value="<?= esc($studentDetail['program_name'] ?? 'Not set') ?>" disabled>
            </div>
            <div class="field">
                <label>Year &amp; Section</label>
                <input type="text" value="<?= $studentDetail['section'] !== null ? esc($studentDetail['section']['year_level'] . ' · ' . $studentDetail['section']['section']) : 'Not assigned' ?>" disabled>
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Curriculum</label>
                <input type="text" value="<?= esc($studentDetail['curriculum_code'] ?? 'Not assigned') ?>" disabled>
            </div>
            <div class="field">
                <label>Academic Year</label>
                <input type="text" value="<?= esc($section['academic_year'] ?? '—') ?>" disabled>
            </div>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>

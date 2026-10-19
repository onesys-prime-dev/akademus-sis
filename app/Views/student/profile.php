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
            'text'  => "Select your subjects for next term and submit them for approval. Only available while your department's enlistment window is open; otherwise you'll enroll directly once sections open.",
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
    <?php $photoUrl = avatar_photo_url((int) $account->id, $studentDetail['photo_mime'] ?? null); ?>
    <div class="avatar-row">
        <div class="avatar" style="<?= $photoUrl === null ? 'background:' . esc(avatar_color($account->username), 'attr') . ';' : '' ?> overflow:hidden;">
            <?php if ($photoUrl !== null): ?>
                <img src="<?= esc($photoUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
                <?= esc(mb_strtoupper(mb_substr($account->username, 0, 1))) ?>
            <?php endif ?>
        </div>
        <div>
            <div class="avatar-name"><?= esc($account->username) ?></div>
            <div class="avatar-role">
                <?= $section !== null ? esc($section['program']) . ' · ' . esc($section['year_level']) . ' · Section ' . esc($section['section']) : 'No section assigned' ?>
            </div>
            <div class="avatar-photo-actions">
                <form method="post" action="<?= site_url('student/profile/photo') ?>" enctype="multipart/form-data" id="avatarUploadForm">
                    <?= csrf_field() ?>
                    <label class="btn btn-secondary avatar-upload-btn">
                        Change photo
                        <input type="file" name="photo" id="avatarFileInput" accept="image/jpeg,image/png,image/webp" hidden>
                    </label>
                </form>
                <?php if ($photoUrl !== null): ?>
                    <form method="post" action="<?= site_url('student/profile/photo/remove') ?>" data-confirm="Remove your profile photo?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-secondary">Remove photo</button>
                    </form>
                <?php endif ?>
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
                <?php $genderValue = old('gender', $studentDetail['gender'] ?? ''); ?>
                <select name="gender">
                    <option value="" <?= $genderValue === '' ? 'selected' : '' ?>>Select&hellip;</option>
                    <option value="Male" <?= $genderValue === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $genderValue === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Non-binary" <?= $genderValue === 'Non-binary' ? 'selected' : '' ?>>Non-binary</option>
                </select>
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

<div class="avatar-crop-backdrop" id="avatarCropBackdrop" hidden>
    <div class="avatar-crop-modal" role="dialog" aria-modal="true" aria-labelledby="avatarCropTitle">
        <div class="avatar-crop-head">
            <h3 id="avatarCropTitle">Choose profile picture</h3>
            <button type="button" class="icon-btn" id="avatarCropClose" aria-label="Cancel">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="avatar-crop-stage" id="avatarCropStage">
            <img id="avatarCropImg" alt="" draggable="false" hidden>
            <div class="avatar-crop-status" id="avatarCropStatus">Loading photo&hellip;</div>
        </div>
        <div class="avatar-crop-zoom">
            <span class="material-symbols-outlined">remove</span>
            <input type="range" id="avatarCropZoom" min="100" max="300" value="100">
            <span class="material-symbols-outlined">add</span>
        </div>
        <div class="avatar-crop-actions">
            <button type="button" class="btn btn-secondary" id="avatarCropCancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="avatarCropSave">Save</button>
        </div>
    </div>
</div>

<script>
(function () {
    var fileInput = document.getElementById('avatarFileInput');
    var uploadForm = document.getElementById('avatarUploadForm');
    var backdrop   = document.getElementById('avatarCropBackdrop');
    var stage      = document.getElementById('avatarCropStage');
    var img        = document.getElementById('avatarCropImg');
    var zoomInput  = document.getElementById('avatarCropZoom');
    var saveBtn    = document.getElementById('avatarCropSave');
    var cancelBtn  = document.getElementById('avatarCropCancel');
    var closeBtn   = document.getElementById('avatarCropClose');

    var STAGE_SIZE  = 280;
    var OUTPUT_SIZE = 480;

    var statusEl = document.getElementById('avatarCropStatus');

    var naturalW = 0, naturalH = 0, baseScale = 1, offsetX = 0, offsetY = 0;
    var dragging = false, dragStartX = 0, dragStartY = 0, dragStartOffsetX = 0, dragStartOffsetY = 0;
    var objectUrl = null;
    var loadTimeoutId = null;

    function currentScale() {
        return baseScale * (parseInt(zoomInput.value, 10) / 100);
    }

    function applyTransform() {
        var scale = currentScale();
        var dispW = naturalW * scale;
        var dispH = naturalH * scale;

        // Never let the image shrink smaller than the circular viewport —
        // clamp so there's never a gap at any zoom/pan combination.
        offsetX = Math.min(0, Math.max(STAGE_SIZE - dispW, offsetX));
        offsetY = Math.min(0, Math.max(STAGE_SIZE - dispH, offsetY));

        img.style.width  = dispW + 'px';
        img.style.height = dispH + 'px';
        img.style.left   = offsetX + 'px';
        img.style.top    = offsetY + 'px';
    }

    function showStatus(message) {
        statusEl.textContent = message;
        statusEl.hidden = false;
        img.hidden = true;
    }

    // Opens the dialog immediately with a "Loading…" placeholder (so a
    // large phone-camera photo that takes a moment to decode never looks
    // like a frozen blank circle), then swaps in the real image once it's
    // actually ready — or shows a clear error instead of staying stuck if
    // the file can't be decoded at all (e.g. an unsupported format like HEIC).
    function openCropper(file) {
        backdrop.hidden = false;
        showStatus('Loading photo…');

        if (objectUrl) { URL.revokeObjectURL(objectUrl); }
        objectUrl = URL.createObjectURL(file);

        var loadedImg = new Image();

        loadTimeoutId = setTimeout(function () {
            showStatus('Still loading… this photo may be large.');
        }, 4000);

        loadedImg.onload = function () {
            clearTimeout(loadTimeoutId);
            naturalW = loadedImg.naturalWidth;
            naturalH = loadedImg.naturalHeight;

            if (!naturalW || !naturalH) {
                showStatus("Couldn't read this image. Try a JPG or PNG instead.");
                return;
            }

            img.src = objectUrl;
            img.hidden = false;
            statusEl.hidden = true;

            // "cover" fit: smallest scale that still fills the circular
            // viewport on both axes, so there's never an empty gap.
            baseScale = Math.max(STAGE_SIZE / naturalW, STAGE_SIZE / naturalH);
            zoomInput.value = 100;
            offsetX = (STAGE_SIZE - naturalW * baseScale) / 2;
            offsetY = (STAGE_SIZE - naturalH * baseScale) / 2;
            applyTransform();
        };

        loadedImg.onerror = function () {
            clearTimeout(loadTimeoutId);
            showStatus("Couldn't read this image. Try a JPG or PNG instead.");
        };

        loadedImg.src = objectUrl;
    }

    function closeCropper() {
        clearTimeout(loadTimeoutId);
        backdrop.hidden = true;
        img.hidden = true;
        fileInput.value = '';
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
    }

    fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files[0]) {
            openCropper(fileInput.files[0]);
        }
    });

    zoomInput.addEventListener('input', applyTransform);

    stage.addEventListener('pointerdown', function (e) {
        dragging = true;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        dragStartOffsetX = offsetX;
        dragStartOffsetY = offsetY;
        stage.setPointerCapture(e.pointerId);
    });
    stage.addEventListener('pointermove', function (e) {
        if (!dragging) return;
        offsetX = dragStartOffsetX + (e.clientX - dragStartX);
        offsetY = dragStartOffsetY + (e.clientY - dragStartY);
        applyTransform();
    });
    stage.addEventListener('pointerup', function () { dragging = false; });
    stage.addEventListener('pointercancel', function () { dragging = false; });

    cancelBtn.addEventListener('click', closeCropper);
    closeBtn.addEventListener('click', closeCropper);
    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) closeCropper(); });

    saveBtn.addEventListener('click', function () {
        if (!naturalW || !naturalH) {
            return; // nothing decoded yet (still loading, or it failed) — the status text already explains why
        }

        var scale = currentScale();
        var sx = -offsetX / scale;
        var sy = -offsetY / scale;
        var sSize = STAGE_SIZE / scale;

        var canvas = document.createElement('canvas');
        canvas.width = OUTPUT_SIZE;
        canvas.height = OUTPUT_SIZE;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, sx, sy, sSize, sSize, 0, 0, OUTPUT_SIZE, OUTPUT_SIZE);

        canvas.toBlob(function (blob) {
            var croppedFile = new File([blob], 'avatar.png', { type: 'image/png' });
            var dt = new DataTransfer();
            dt.items.add(croppedFile);
            fileInput.files = dt.files;
            backdrop.hidden = true;
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
            uploadForm.requestSubmit();
        }, 'image/png');
    });
})();
</script>

<?= $this->endSection() ?>

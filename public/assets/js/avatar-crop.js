/**
 * Facebook-style profile photo editor: a small pencil/camera badge on the
 * avatar opens a tiny Change/Remove menu; Change opens a drag-to-pan,
 * slide-to-zoom circular cropper that renders the result to a canvas and
 * submits it as a real file — no server-side cropping needed.
 *
 * Fully generic — every element it touches is referenced by a fixed,
 * role-independent id (avatarEditTrigger, avatarCropBackdrop, etc.), so any
 * profile page gets the same editor just by including this file plus the
 * matching markup block (see student/profile.php for the markup to copy).
 * First built for the student profile, extracted here so other roles
 * (chairperson, etc.) get the exact same behavior instead of a duplicated
 * copy silently drifting out of sync.
 */
(function () {
    var fileInput  = document.getElementById('avatarFileInput');
    var uploadForm = document.getElementById('avatarUploadForm');
    if (!fileInput || !uploadForm) return; // this page doesn't have the avatar editor markup

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

    var removeBtn       = document.getElementById('avatarRemoveBtn');
    var removeForm      = document.getElementById('avatarRemoveForm');
    var removeBackdrop  = document.getElementById('avatarRemoveBackdrop');
    var removeCancelBtn = document.getElementById('avatarRemoveCancel');
    var removeConfirmBtn = document.getElementById('avatarRemoveConfirm');

    if (removeBtn) {
        removeBtn.addEventListener('click', function () { removeBackdrop.hidden = false; });
        removeCancelBtn.addEventListener('click', function () { removeBackdrop.hidden = true; });
        removeBackdrop.addEventListener('click', function (e) { if (e.target === removeBackdrop) removeBackdrop.hidden = true; });
        removeConfirmBtn.addEventListener('click', function () { removeForm.requestSubmit(); });
    }
})();

// Facebook-style "pencil badge" on the avatar: a small trigger that opens a
// tiny menu with Change/Remove, instead of two full-size buttons sitting
// under the name.
(function () {
    var trigger = document.getElementById('avatarEditTrigger');
    var menu    = document.getElementById('avatarEditMenu');
    if (!trigger || !menu) return;

    function closeMenu() {
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    }
    function openMenu() {
        menu.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
    }

    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        if (menu.hidden) openMenu(); else closeMenu();
    });
    menu.querySelectorAll('.avatar-edit-menu__item').forEach(function (item) {
        item.addEventListener('click', closeMenu);
    });
    document.addEventListener('click', function (e) {
        if (!menu.hidden && !menu.contains(e.target) && e.target !== trigger) closeMenu();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !menu.hidden) { closeMenu(); trigger.focus(); }
    });
})();

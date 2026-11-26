<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/finance-additional-fees.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/finance-additional-fees.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/additional-fees') ?>" class="ac-back-link"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Back to Additional Fees</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;">Bulk Apply a Fee</h2>
        <p class="ac-page-header__sub">Charge the same fee to every student matching a program/year level/section filter, in one action.</p>
    </div>
</div>

<?php if ($activeTerm === null): ?>
    <div class="ac-empty-block">No active academic term is set — bulk apply needs one to know which students' sections to match.</div>
<?php else: ?>

<div class="af-section">
<div class="af-card">
    <form method="post" action="<?= site_url('finance/additional-fees/bulk/apply') ?>" id="bulkForm" onsubmit="return bulkConfirmSubmit(event);">
        <?= csrf_field() ?>
        <input type="hidden" name="academic_year" value="<?= esc($activeTerm) ?>">

        <div class="af-card__head">
            <div>
                <h3 class="af-card__title">Who gets charged</h3>
                <p class="af-card__hint" style="margin-bottom:0;">Pick a filter, or apply to everyone at once.</p>
            </div>
            <span class="af-term-pill"><span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>Term <strong><?= esc($activeTerm) ?></strong></span>
        </div>

        <label class="af-everyone">
            <input type="checkbox" id="bulkEveryone" onchange="bulkToggleEveryone()">
            <span class="af-everyone__text"><strong>Apply to every active student</strong>Skips the filters below and ignores program, year level, and section entirely.</span>
        </label>

        <div id="bulkFilterFields" class="af-field-grid">
            <div class="af-field">
                <label for="bulkProgram">Program</label>
                <select name="program" id="bulkProgram" class="ac-input" onchange="bulkResetPreview()">
                    <option value="">— Any program —</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?= esc($p, 'attr') ?>"><?= esc($p) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="af-field">
                <label for="bulkYearLevel">Year Level</label>
                <select name="year_level" id="bulkYearLevel" class="ac-input" onchange="bulkResetPreview()">
                    <option value="">— Any year level —</option>
                    <?php foreach (array_values(array_unique(array_column($sections, 'year_level'))) as $yl): ?>
                        <option value="<?= esc($yl, 'attr') ?>"><?= esc($yl) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="af-field">
                <label for="bulkSection">Section</label>
                <select name="section" id="bulkSection" class="ac-input" onchange="bulkResetPreview()">
                    <option value="">— Any section —</option>
                    <?php foreach (array_values(array_unique(array_column($sections, 'section'))) as $sec): ?>
                        <option value="<?= esc($sec, 'attr') ?>"><?= esc($sec) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <h3 class="af-card__title" style="margin-top:4px;">The fee</h3>
        <div class="af-field-grid af-field-grid--split">
            <div class="af-field">
                <label for="bulkLabel">Fee Label</label>
                <input type="text" name="label" id="bulkLabel" class="ac-input" placeholder="e.g. University Week 2026" required maxlength="100" oninput="bulkResetPreview()">
            </div>
            <div class="af-field">
                <label for="bulkAmount">Amount (per student)</label>
                <input type="number" name="amount" id="bulkAmount" class="ac-input" step="0.01" min="0.01" required>
            </div>
        </div>
        <div class="af-field">
            <label for="bulkRemarks">Remarks</label>
            <input type="text" name="remarks" id="bulkRemarks" class="ac-input" placeholder="Optional — shown on each student's fee history">
        </div>

        <div class="af-results" id="bulkResults">
            <div class="af-bulk-count" id="bulkCount"></div>
            <ul class="af-bulk-list" id="bulkList"></ul>
        </div>

        <div class="af-actions">
            <button type="button" class="ac-btn ac-btn-secondary" onclick="bulkPreview()"><span class="material-symbols-outlined" aria-hidden="true">visibility</span>Preview</button>
            <button type="submit" class="ac-btn ac-btn-primary" id="bulkApplyBtn"><span class="material-symbols-outlined" aria-hidden="true">bolt</span>Apply to All Matching Students</button>
        </div>
    </form>
</div>
</div>

<?php endif ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/avatar-helper.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/avatar-helper.js') ?>"></script>
<script>
    window.bulkConfirmed = false;
    window.bulkLastCount = 0;
    window.bulkLastLabel = '';
    const bulkAvatarBaseUrl = <?= json_encode(site_url('avatar')) ?>;

    (function () {
        ['bulkProgram', 'bulkYearLevel', 'bulkSection'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el && window.enhanceSelect) window.enhanceSelect(el);
        });
    })();

    function bulkResetPreview() {
        window.bulkConfirmed = false;
        document.getElementById('bulkResults').classList.remove('is-open');
    }

    // "Apply to every active student" is a deliberate, named choice rather
    // than something staff only discover by accident from leaving all three
    // filters blank. Checking it visibly disables the filters and forces
    // them back to "Any" so there's no ambiguity about what's about to be
    // charged — the same "everyone" scope the filters already supported
    // when left empty, just no longer implicit.
    function bulkToggleEveryone() {
        const everyone = document.getElementById('bulkEveryone').checked;
        const fields = document.getElementById('bulkFilterFields');
        fields.classList.toggle('is-disabled', everyone);
        ['bulkProgram', 'bulkYearLevel', 'bulkSection'].forEach(function (id) {
            const el = document.getElementById(id);
            el.disabled = everyone;
            if (everyone) el.value = '';
        });
        bulkResetPreview();
    }

    function bulkConfirmSubmit(e) {
        e.preventDefault();

        if (!window.bulkConfirmed) {
            alert('Run Preview first to see how many students this will charge.');
            return false;
        }

        const form = e.target;
        const scope = window.bulkScopeText || `${window.bulkLastCount} student${window.bulkLastCount === 1 ? '' : 's'}`;
        const message = `Charge "${window.bulkLastLabel}" to ${scope}? This can't be bulk-undone — each one would need to be revoked individually.`;

        (typeof window.acConfirm === 'function' ? window.acConfirm(message) : Promise.resolve(confirm(message))).then(function (ok) {
            // form.submit() (not requestSubmit()) deliberately bypasses the
            // submit event entirely — requestSubmit() would re-dispatch it
            // and re-run this same onsubmit handler, looping forever.
            if (ok) form.submit();
        });

        return false;
    }

    async function bulkPreview() {
        const params = new URLSearchParams({
            program: document.getElementById('bulkProgram').value,
            year_level: document.getElementById('bulkYearLevel').value,
            section: document.getElementById('bulkSection').value,
        });

        const res = await fetch('<?= site_url('finance/additional-fees/bulk/preview') ?>?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json().catch(() => null);

        const resultsBox = document.getElementById('bulkResults');
        const countEl = document.getElementById('bulkCount');
        const listEl = document.getElementById('bulkList');

        if (!data?.ok) {
            countEl.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">error</span><span>Could not load a preview — try again.</span>';
            listEl.replaceChildren();
            resultsBox.classList.add('is-open');
            window.bulkConfirmed = false;
            return;
        }

        const label = document.getElementById('bulkLabel').value.trim() || '(this fee)';
        const everyoneChosen = document.getElementById('bulkEveryone').checked;
        const noFilter = !document.getElementById('bulkProgram').value
            && !document.getElementById('bulkYearLevel').value
            && !document.getElementById('bulkSection').value;
        // No filter selected means no WHERE clause at all server-side — every
        // active student in the active term, across every program/section.
        // Said out loud here so this scope is never ambiguous — worded as a
        // confirmation when "Apply to every active student" was explicitly
        // checked, or as a warning when the filters just happen to be blank
        // without that checkbox (the one state that's easy to hit by
        // accident: picking nothing from any of the three dropdowns).
        window.bulkScopeText = noFilter ? 'EVERY active student, across ALL programs and sections' : 'the students matching your filter';
        const isWarning = !everyoneChosen && noFilter && data.count > 0;
        resultsBox.classList.toggle('af-results--warning', isWarning);

        const icon = data.count === 0 ? 'search_off' : (isWarning ? 'warning' : 'groups');
        const text = data.count === 0
            ? 'No students match this filter — nothing would be charged.'
            : (everyoneChosen
                ? `This will charge "${label}" to ${window.bulkScopeText}: all ${data.count} active student${data.count === 1 ? '' : 's'}.`
                : (noFilter
                    ? `No program/year/section filter is set — this will charge "${label}" to ${window.bulkScopeText}: all ${data.count} active student${data.count === 1 ? '' : 's'}.`
                    : `This will charge "${label}" to ${data.count} student${data.count === 1 ? '' : 's'}.`));
        countEl.innerHTML = '';
        const iconEl = document.createElement('span');
        iconEl.className = 'material-symbols-outlined';
        iconEl.setAttribute('aria-hidden', 'true');
        iconEl.textContent = icon;
        const textEl = document.createElement('span');
        textEl.textContent = text;
        countEl.append(iconEl, textEl);

        listEl.replaceChildren();
        data.students.forEach(s => {
            const li = document.createElement('li');

            const avatar = document.createElement('span');
            avatar.className = 'af-bulk-list__avatar';
            const photoUrl = AvatarHelper.photoUrl(bulkAvatarBaseUrl, s.user_id, s.photo_mime);
            if (photoUrl) {
                const img = document.createElement('img');
                img.src = photoUrl;
                img.alt = '';
                avatar.append(img);
            } else {
                avatar.style.background = AvatarHelper.color(s.username);
                avatar.style.color = '#fff';
                avatar.textContent = AvatarHelper.initials(s.username);
            }

            const name = document.createElement('span');
            name.className = 'af-bulk-list__name';
            name.textContent = s.username;

            const meta = document.createElement('span');
            meta.className = 'af-bulk-list__meta';
            meta.textContent = '· ' + s.student_number;

            li.append(avatar, name, meta);
            listEl.append(li);
        });
        if (data.count > data.students.length) {
            const more = document.createElement('li');
            more.className = 'af-bulk-more';
            more.textContent = `…and ${data.count - data.students.length} more.`;
            listEl.append(more);
        }

        resultsBox.classList.add('is-open');
        window.bulkConfirmed = data.count > 0;
        window.bulkLastCount = data.count;
        window.bulkLastLabel = label;
    }
</script>
<?= $this->endSection() ?>

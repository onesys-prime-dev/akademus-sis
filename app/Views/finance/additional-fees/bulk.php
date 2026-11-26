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
            <span class="af-everyone__text"><strong>Apply to every active student</strong> Skips the filters below and ignores program, year level, and section entirely.</span>
        </label>

        <div id="bulkFilterFields" class="af-field-grid">
            <div class="af-field">
                <label for="bulkProgram">Program</label>
                <select name="program" id="bulkProgram" class="ac-input">
                    <option value="">— Any program —</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?= esc($p, 'attr') ?>"><?= esc($p) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="af-field">
                <label for="bulkYearLevel">Year Level</label>
                <select name="year_level" id="bulkYearLevel" class="ac-input">
                    <option value="">— Any year level —</option>
                    <?php foreach (array_values(array_unique(array_column($sections, 'year_level'))) as $yl): ?>
                        <option value="<?= esc($yl, 'attr') ?>"><?= esc($yl) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="af-field">
                <label for="bulkSection">Section</label>
                <select name="section" id="bulkSection" class="ac-input">
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
                <input type="text" name="label" id="bulkLabel" class="ac-input" placeholder="e.g. University Week 2026" required maxlength="100">
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

        <div class="af-actions">
            <button type="submit" class="ac-btn ac-btn-primary" id="bulkApplyBtn"><span class="material-symbols-outlined" aria-hidden="true">bolt</span>Apply to All Matching Students</button>
        </div>
    </form>
</div>
</div>

<?php endif ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
    (function () {
        ['bulkProgram', 'bulkYearLevel', 'bulkSection'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el && window.enhanceSelect) window.enhanceSelect(el);
        });
    })();

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
    }

    function bulkConfirmSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const label = document.getElementById('bulkLabel').value.trim() || '(this fee)';
        const everyoneChosen = document.getElementById('bulkEveryone').checked;
        const noFilter = !document.getElementById('bulkProgram').value
            && !document.getElementById('bulkYearLevel').value
            && !document.getElementById('bulkSection').value;
        // No filter selected means no WHERE clause at all server-side — every
        // active student in the active term, across every program/section.
        // Said out loud here so this scope is never ambiguous, whether it's
        // the deliberate "Apply to everyone" choice or filters just happen
        // to be left blank.
        const scope = (everyoneChosen || noFilter)
            ? 'EVERY active student, across ALL programs and sections'
            : 'the students matching your filter';
        const message = `Charge "${label}" to ${scope}? This can't be bulk-undone — each one would need to be revoked individually.`;

        (typeof window.acConfirm === 'function' ? window.acConfirm(message) : Promise.resolve(confirm(message))).then(function (ok) {
            // form.submit() (not requestSubmit()) deliberately bypasses the
            // submit event entirely — requestSubmit() would re-dispatch it
            // and re-run this same onsubmit handler, looping forever.
            if (ok) form.submit();
        });

        return false;
    }
</script>
<?= $this->endSection() ?>

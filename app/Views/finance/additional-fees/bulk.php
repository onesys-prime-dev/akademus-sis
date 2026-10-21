<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<style>
    .fin-bulk-results { border: 1px solid var(--color-border); border-radius: var(--radius-sm); margin-top: 14px; padding: 14px; background: var(--color-surface-sunken, #f8fafc); }
    .fin-bulk-results.is-empty { display: none; }
    .fin-bulk-count { font-weight: 700; font-size: 14px; color: var(--color-text-primary); margin-bottom: 8px; }
    .fin-bulk-list { max-height: 200px; overflow-y: auto; font-size: 12.5px; color: var(--color-text-secondary); }
    .fin-bulk-list div { padding: 3px 0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('finance/additional-fees') ?>" style="font-size:12.5px; color:var(--color-text-secondary);">&larr; Back to Additional Fees</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;">Bulk Apply a Fee</h2>
        <p class="ac-page-header__sub">Charge the same fee to every student matching a program/year level/section filter, in one action.</p>
    </div>
</div>

<?php if ($activeTerm === null): ?>
    <div class="ac-card"><p class="panel-sub">No active academic term is set — bulk apply needs one to know which students' sections to match.</p></div>
<?php else: ?>

<div class="ac-card">
    <form method="post" action="<?= site_url('finance/additional-fees/bulk/apply') ?>" id="bulkForm" onsubmit="return bulkConfirmSubmit(event);">
        <?= csrf_field() ?>
        <input type="hidden" name="academic_year" value="<?= esc($activeTerm) ?>">

        <p style="font-size:12.5px; color:var(--color-text-secondary); margin:0 0 16px;">Term: <strong><?= esc($activeTerm) ?></strong></p>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
            <div class="ac-field">
                <label>Program</label>
                <select name="program" id="bulkProgram" class="ac-input" onchange="bulkResetPreview()">
                    <option value="">— Any program —</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?= esc($p, 'attr') ?>"><?= esc($p) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="ac-field">
                <label>Year Level</label>
                <select name="year_level" id="bulkYearLevel" class="ac-input" onchange="bulkResetPreview()">
                    <option value="">— Any year level —</option>
                    <?php foreach (array_values(array_unique(array_column($sections, 'year_level'))) as $yl): ?>
                        <option value="<?= esc($yl, 'attr') ?>"><?= esc($yl) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="ac-field">
                <label>Section</label>
                <select name="section" id="bulkSection" class="ac-input" onchange="bulkResetPreview()">
                    <option value="">— Any section —</option>
                    <?php foreach (array_values(array_unique(array_column($sections, 'section'))) as $sec): ?>
                        <option value="<?= esc($sec, 'attr') ?>"><?= esc($sec) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px; margin-top:4px;">
            <div class="ac-field">
                <label>Fee Label</label>
                <input type="text" name="label" id="bulkLabel" class="ac-input" placeholder="e.g. University Week 2026" required maxlength="100" oninput="bulkResetPreview()">
            </div>
            <div class="ac-field">
                <label>Amount (per student)</label>
                <input type="number" name="amount" class="ac-input" step="0.01" min="0.01" required>
            </div>
        </div>
        <div class="ac-field">
            <label>Remarks</label>
            <input type="text" name="remarks" class="ac-input" placeholder="Optional — shown on each student's fee history">
        </div>

        <div class="fin-bulk-results is-empty" id="bulkResults">
            <div class="fin-bulk-count" id="bulkCount"></div>
            <div class="fin-bulk-list" id="bulkList"></div>
        </div>

        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="button" class="ac-btn ac-btn-secondary" onclick="bulkPreview()">Preview</button>
            <button type="submit" class="ac-btn ac-btn-primary" id="bulkApplyBtn">Apply to All Matching Students</button>
        </div>
    </form>
</div>

<?php endif ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
    window.bulkConfirmed = false;
    window.bulkLastCount = 0;
    window.bulkLastLabel = '';

    function bulkResetPreview() {
        window.bulkConfirmed = false;
        document.getElementById('bulkResults').classList.add('is-empty');
    }

    function bulkConfirmSubmit(e) {
        if (!window.bulkConfirmed) {
            alert('Run Preview first to see how many students this will charge.');
            return false;
        }

        return confirm(`Charge "${window.bulkLastLabel}" to ${window.bulkLastCount} student${window.bulkLastCount === 1 ? '' : 's'}? This can\'t be bulk-undone — each one would need to be revoked individually.`);
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
            countEl.textContent = 'Could not load a preview — try again.';
            listEl.replaceChildren();
            resultsBox.classList.remove('is-empty');
            window.bulkConfirmed = false;
            return;
        }

        const label = document.getElementById('bulkLabel').value.trim() || '(this fee)';
        countEl.textContent = data.count === 0
            ? 'No students match this filter — nothing would be charged.'
            : `This will charge "${label}" to ${data.count} student${data.count === 1 ? '' : 's'}.`;

        listEl.replaceChildren();
        data.students.forEach(s => {
            const row = document.createElement('div');
            row.textContent = `${s.username} · ${s.student_number}`;
            listEl.append(row);
        });
        if (data.count > data.students.length) {
            const more = document.createElement('div');
            more.textContent = `…and ${data.count - data.students.length} more.`;
            listEl.append(more);
        }

        resultsBox.classList.remove('is-empty');
        window.bulkConfirmed = data.count > 0;
        window.bulkLastCount = data.count;
        window.bulkLastLabel = label;
    }
</script>
<?= $this->endSection() ?>

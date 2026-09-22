<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<button type="button" class="ac-btn ac-btn-primary" onclick="acOpenModal('modalAddScale')">
    <?= admin_icon('plus', 16) ?> Add Grading Scale
</button>

<?php if (empty($scales)): ?>
    <div class="ac-empty-block" style="margin-top:16px;">No grading scales yet.</div>
<?php else: ?>
    <div class="ac-grid ac-grid--sm" style="margin-top:16px;">
        <?php foreach ($scales as $scale): ?>
            <div class="ac-card" style="<?= $scale['is_active'] ? 'border-color:#bbf7d0;' : '' ?>">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="ac-dept-name"><?= esc($scale['name']) ?></span>
                    <?php if ($scale['is_active']): ?>
                        <span class="ac-badge-active">ACTIVE</span>
                    <?php endif ?>
                </div>
                <div class="ac-dept-meta">
                    <?php if ($scale['type'] === 'letter'): ?>
                        Letter &middot; <?= esc($scale['letter_options']) ?> &middot; Failing: <?= esc($scale['failing_letters']) ?>
                    <?php else: ?>
                        <?= $scale['type'] === 'numeric_gwa' ? 'Numeric GWA' : 'Percentage' ?> &middot;
                        <?= esc($scale['min_value']) ?>&ndash;<?= esc($scale['max_value']) ?> &middot;
                        Passing <?= $scale['is_lower_better'] ? '&le;' : '&ge;' ?> <?= esc($scale['passing_value']) ?>
                    <?php endif ?>
                </div>

                <?php if (! $scale['is_active']): ?>
                    <form method="post" action="<?= site_url('admin/grading-scales/set-active/' . $scale['scale_id']) ?>" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <button type="submit" class="ac-btn ac-btn-secondary ac-btn-block">Set as active scale</button>
                    </form>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<!-- Add grading scale modal -->
<div class="ac-modal" id="modalAddScale">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div class="ac-modal__title">Add Grading Scale</div>
            <button type="button" class="ac-modal__close" onclick="acCloseModal('modalAddScale')"><?= admin_icon('x', 16) ?></button>
        </div>

        <form method="post" action="<?= site_url('admin/grading-scales/store') ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Name</label>
                    <input type="text" name="name" class="ac-input" placeholder="e.g. GWA (1.00 - 5.00)" value="<?= old('name') ?>" required>
                </div>
                <div class="ac-field">
                    <label>Type</label>
                    <select name="type" class="ac-input" id="scaleType" onchange="acToggleScaleFields()" required>
                        <option value="numeric_gwa">Numeric GWA (lower is better)</option>
                        <option value="percentage">Percentage (higher is better)</option>
                        <option value="letter">Letter grade</option>
                    </select>
                </div>

                <div id="numericFields">
                    <div class="ac-field">
                        <label>Minimum value</label>
                        <input type="number" step="0.01" name="min_value" class="ac-input" value="<?= old('min_value') ?>">
                    </div>
                    <div class="ac-field">
                        <label>Maximum value</label>
                        <input type="number" step="0.01" name="max_value" class="ac-input" value="<?= old('max_value') ?>">
                    </div>
                    <div class="ac-field">
                        <label>Passing value</label>
                        <input type="number" step="0.01" name="passing_value" class="ac-input" value="<?= old('passing_value') ?>">
                    </div>
                </div>

                <div id="letterFields" style="display:none;">
                    <div class="ac-field">
                        <label>Letter options (best to worst, comma-separated)</label>
                        <input type="text" name="letter_options" class="ac-input" placeholder="A,B+,B,C+,C,D,F" value="<?= old('letter_options') ?>">
                    </div>
                    <div class="ac-field">
                        <label>Failing letters (comma-separated)</label>
                        <input type="text" name="failing_letters" class="ac-input" placeholder="F" value="<?= old('failing_letters') ?>">
                    </div>
                </div>
            </div>
            <div class="ac-modal__actions">
                <button type="button" class="ac-btn ac-btn-secondary" onclick="acCloseModal('modalAddScale')">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Add</button>
            </div>
        </form>
    </div>
</div>

<script>
    function acToggleScaleFields() {
        const type = document.getElementById('scaleType').value;
        document.getElementById('numericFields').style.display = type === 'letter' ? 'none' : '';
        document.getElementById('letterFields').style.display = type === 'letter' ? '' : 'none';
    }
</script>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (session('errors')): ?>
<script>document.getElementById('modalAddScale').classList.add('is-open');</script>
<?php endif ?>
<?= $this->endSection() ?>

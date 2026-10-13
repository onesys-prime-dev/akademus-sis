<?= $this->extend('layouts/admin_console') ?>

<?php $basePath = $basePath ?? 'admin'; ?>

<?= $this->section('content') ?>

<?php if ($basePath === 'admin'): ?>
<div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
    <button type="button" class="ac-btn ac-btn-primary" onclick="acOpenModal('modalAddAy')">
        <?= admin_icon('plus', 16) ?> Add Academic Year
    </button>

    <form method="post" action="<?= site_url('admin/academic-years/sync-active') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="ac-btn ac-btn-secondary" data-confirm="Set the active term to whichever one's date range covers today (<?= esc(date('M j, Y')) ?>)?">
            <?= admin_icon('cal', 16) ?> Sync to Today (<?= esc(date('M j, Y')) ?>)
        </button>
    </form>
</div>
<?php else: ?>
<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Academic Calendar</h2><p class="ac-page-header__sub">Set grading and registration windows per term.</p></div>
</div>
<?php endif ?>

<?php
$ayModel = new \App\Models\AcademicYearModel();
?>

<?php if (empty($years)): ?>
    <div class="ac-empty-block" style="margin-top:16px;">No academic years yet.</div>
<?php else: ?>
    <div class="ac-grid ac-grid--sm" style="margin-top:16px;">
        <?php foreach ($years as $ay): ?>
            <?php
                $gradingOpen      = $ayModel->isGradingOpen((int) $ay['ay_id']);
                $registrationOpen = $ayModel->isRegistrationOpen((int) $ay['ay_id']);
                $enlistmentOpen   = $ayModel->isEnlistmentOpen((int) $ay['ay_id']);
            ?>
            <div class="ac-card" style="<?= $ay['is_active'] ? 'border-color:#bbf7d0;' : '' ?>">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span class="ac-dept-name"><?= esc($ay['label']) ?></span>
                    <?php if ($ay['is_active']): ?>
                        <span class="ac-badge-active">ACTIVE</span>
                    <?php endif ?>
                </div>
                <div class="ac-dept-meta"><?= esc($ay['semester']) ?> Semester</div>
                <div class="ac-dept-meta">
                    <?php if ($ay['start_date'] && $ay['end_date']): ?>
                        <?= esc(date('M j, Y', strtotime($ay['start_date']))) ?> &ndash; <?= esc(date('M j, Y', strtotime($ay['end_date']))) ?>
                    <?php else: ?>
                        <span style="color:var(--color-text-tertiary);">No dates set</span>
                    <?php endif ?>
                </div>

                <div style="display:flex; gap:6px; margin-top:10px;">
                    <span class="ac-pill <?= $gradingOpen ? 'ac-pill--success' : 'ac-pill--danger' ?>">Grading <?= $gradingOpen ? 'OPEN' : 'CLOSED' ?></span>
                    <span class="ac-pill <?= $registrationOpen ? 'ac-pill--success' : 'ac-pill--danger' ?>">Registration <?= $registrationOpen ? 'OPEN' : 'CLOSED' ?></span>
                    <span class="ac-pill <?= $enlistmentOpen ? 'ac-pill--success' : 'ac-pill--danger' ?>">Enlistment <?= $enlistmentOpen ? 'OPEN' : 'CLOSED' ?></span>
                </div>

                <details style="margin-top:10px;">
                    <summary style="cursor:pointer; font-size:12.5px; color:var(--color-text-secondary);">Edit windows</summary>
                    <form method="post" action="<?= site_url($basePath . '/academic-' . ($basePath === 'admin' ? 'years' : 'calendar') . '/' . $ay['ay_id'] . '/window') ?>" style="display:grid; gap:8px; margin-top:8px;">
                        <?= csrf_field() ?>
                        <label style="font-size:11.5px;">Grading opens<input type="datetime-local" name="grading_opens_at" class="ac-input" value="<?= $ay['grading_opens_at'] ? esc(str_replace(' ', 'T', $ay['grading_opens_at'])) : '' ?>"></label>
                        <label style="font-size:11.5px;">Grading closes<input type="datetime-local" name="grading_closes_at" class="ac-input" value="<?= $ay['grading_closes_at'] ? esc(str_replace(' ', 'T', $ay['grading_closes_at'])) : '' ?>"></label>
                        <label style="font-size:11.5px;">Registration opens<input type="datetime-local" name="registration_opens_at" class="ac-input" value="<?= $ay['registration_opens_at'] ? esc(str_replace(' ', 'T', $ay['registration_opens_at'])) : '' ?>"></label>
                        <label style="font-size:11.5px;">Registration closes<input type="datetime-local" name="registration_closes_at" class="ac-input" value="<?= $ay['registration_closes_at'] ? esc(str_replace(' ', 'T', $ay['registration_closes_at'])) : '' ?>"></label>
                        <label style="font-size:11.5px;">Enlistment opens<input type="datetime-local" name="enlistment_opens_at" class="ac-input" value="<?= $ay['enlistment_opens_at'] ? esc(str_replace(' ', 'T', $ay['enlistment_opens_at'])) : '' ?>"></label>
                        <label style="font-size:11.5px;">Enlistment closes<input type="datetime-local" name="enlistment_closes_at" class="ac-input" value="<?= $ay['enlistment_closes_at'] ? esc(str_replace(' ', 'T', $ay['enlistment_closes_at'])) : '' ?>"></label>
                        <button type="submit" class="ac-btn ac-btn-secondary ac-btn-block">Save windows</button>
                    </form>
                </details>

                <?php if ($basePath === 'admin' && ! $ay['is_active']): ?>
                    <form method="post" action="<?= site_url('admin/academic-years/set-active/' . $ay['ay_id']) ?>" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <button type="submit" class="ac-btn ac-btn-secondary ac-btn-block">Set as active term</button>
                    </form>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<!-- Add academic year modal -->
<div class="ac-modal" id="modalAddAy">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div class="ac-modal__title">Add Academic Year</div>
            <button type="button" class="ac-modal__close" onclick="acCloseModal('modalAddAy')"><?= admin_icon('x', 16) ?></button>
        </div>

        <form method="post" action="<?= site_url('admin/academic-years/store') ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Academic Year</label>
                    <input type="text" name="label" class="ac-input" placeholder="2027-2028" value="<?= old('label') ?>" required>
                </div>
                <div class="ac-field">
                    <label>Semester</label>
                    <select name="semester" class="ac-input" required>
                        <option value="1st">1st</option>
                        <option value="2nd">2nd</option>
                        <option value="Summer">Summer</option>
                    </select>
                </div>
                <div class="ac-field">
                    <label>Start Date <span class="optional">(Optional)</span></label>
                    <input type="date" name="start_date" class="ac-input" value="<?= old('start_date') ?>">
                </div>
                <div class="ac-field">
                    <label>End Date <span class="optional">(Optional)</span></label>
                    <input type="date" name="end_date" class="ac-input" value="<?= old('end_date') ?>">
                </div>
            </div>
            <div class="ac-modal__actions">
                <button type="button" class="ac-btn ac-btn-secondary" onclick="acCloseModal('modalAddAy')">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Add</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (session('errors')): ?>
<script>document.getElementById('modalAddAy').classList.add('is-open');</script>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?php $basePath = $basePath ?? 'admin'; ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/academic-calendar.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/academic-calendar.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ay-page">

<?php if ($basePath === 'admin'): ?>
<div class="ay-toolbar">
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
    <div class="ay-grid">
        <?php foreach ($years as $ay): ?>
            <?php
                $gradingOpen      = $ayModel->isGradingOpen((int) $ay['ay_id']);
                $registrationOpen = $ayModel->isRegistrationOpen((int) $ay['ay_id']);
                $enlistmentOpen   = $ayModel->isEnlistmentOpen((int) $ay['ay_id']);
            ?>
            <div class="ay-card <?= $ay['is_active'] ? 'ay-card--active' : '' ?>">
                <div class="ay-card__head">
                    <div>
                        <div class="ay-card__year"><?= esc($ay['label']) ?></div>
                        <div class="ay-card__term"><?= esc($ay['semester']) ?> Semester</div>
                    </div>
                    <?php if ($ay['is_active']): ?>
                        <span class="ay-badge">Active</span>
                    <?php endif ?>
                </div>

                <?php if ($ay['start_date'] && $ay['end_date']): ?>
                    <div class="ay-card__dates">
                        <span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>
                        <span><?= esc(date('M j, Y', strtotime($ay['start_date']))) ?> &ndash; <?= esc(date('M j, Y', strtotime($ay['end_date']))) ?></span>
                    </div>
                <?php else: ?>
                    <div class="ay-card__dates ay-card__dates--empty">
                        <span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>
                        <span>No dates set</span>
                    </div>
                <?php endif ?>

                <div class="ay-card__pills">
                    <span class="ay-pill <?= $gradingOpen ? 'ay-pill--open' : 'ay-pill--closed' ?>">Grading <?= $gradingOpen ? 'OPEN' : 'CLOSED' ?></span>
                    <span class="ay-pill <?= $registrationOpen ? 'ay-pill--open' : 'ay-pill--closed' ?>">Registration <?= $registrationOpen ? 'OPEN' : 'CLOSED' ?></span>
                    <span class="ay-pill <?= $enlistmentOpen ? 'ay-pill--open' : 'ay-pill--closed' ?>">Enlistment <?= $enlistmentOpen ? 'OPEN' : 'CLOSED' ?></span>
                </div>

                <button type="button" class="ac-btn ac-btn-secondary ay-card__edit-btn" onclick="acOpenModal('modalEditWindows<?= (int) $ay['ay_id'] ?>')">
                    <span class="material-symbols-outlined" aria-hidden="true">tune</span> Edit windows
                </button>

                <?php if ($basePath === 'admin' && ! $ay['is_active']): ?>
                    <form method="post" action="<?= site_url('admin/academic-years/set-active/' . $ay['ay_id']) ?>" class="ay-card__setactive">
                        <?= csrf_field() ?>
                        <button type="submit" class="ac-btn ac-btn-secondary">Set as active term</button>
                    </form>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

</div>

<?php if (! empty($years)): ?>
<?php foreach ($years as $ay): ?>
    <div class="ac-modal" id="modalEditWindows<?= (int) $ay['ay_id'] ?>">
        <div class="ac-modal__panel">
            <div class="ac-modal__header">
                <div class="ac-modal__title">Edit Windows &mdash; <?= esc($ay['label']) ?> &middot; <?= esc($ay['semester']) ?></div>
                <button type="button" class="ac-modal__close" onclick="acCloseModal('modalEditWindows<?= (int) $ay['ay_id'] ?>')"><?= admin_icon('x', 16) ?></button>
            </div>

            <form method="post" action="<?= site_url($basePath . '/academic-' . ($basePath === 'admin' ? 'years' : 'calendar') . '/' . $ay['ay_id'] . '/window') ?>">
                <?= csrf_field() ?>
                <div class="ac-modal__body ay-edit-form">
                    <label class="ay-edit-form__field">Grading opens<input type="datetime-local" name="grading_opens_at" value="<?= $ay['grading_opens_at'] ? esc(str_replace(' ', 'T', $ay['grading_opens_at'])) : '' ?>"></label>
                    <label class="ay-edit-form__field">Grading closes<input type="datetime-local" name="grading_closes_at" value="<?= $ay['grading_closes_at'] ? esc(str_replace(' ', 'T', $ay['grading_closes_at'])) : '' ?>"></label>
                    <label class="ay-edit-form__field">Registration opens<input type="datetime-local" name="registration_opens_at" value="<?= $ay['registration_opens_at'] ? esc(str_replace(' ', 'T', $ay['registration_opens_at'])) : '' ?>"></label>
                    <label class="ay-edit-form__field">Registration closes<input type="datetime-local" name="registration_closes_at" value="<?= $ay['registration_closes_at'] ? esc(str_replace(' ', 'T', $ay['registration_closes_at'])) : '' ?>"></label>
                    <label class="ay-edit-form__field">Enlistment opens<input type="datetime-local" name="enlistment_opens_at" value="<?= $ay['enlistment_opens_at'] ? esc(str_replace(' ', 'T', $ay['enlistment_opens_at'])) : '' ?>"></label>
                    <label class="ay-edit-form__field">Enlistment closes<input type="datetime-local" name="enlistment_closes_at" value="<?= $ay['enlistment_closes_at'] ? esc(str_replace(' ', 'T', $ay['enlistment_closes_at'])) : '' ?>"></label>
                </div>
                <div class="ac-modal__actions">
                    <button type="button" class="ac-btn ac-btn-secondary" onclick="acCloseModal('modalEditWindows<?= (int) $ay['ay_id'] ?>')">Cancel</button>
                    <button type="submit" class="ac-btn ac-btn-primary">Save windows</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach ?>
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

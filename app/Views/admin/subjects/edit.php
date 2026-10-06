<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <div class="ac-breadcrumb">
            <a href="<?= site_url('admin/subjects') ?>">Subjects</a>
            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            <span class="current">Edit</span>
        </div>
        <h2 class="ac-page-header__title">Edit Subject</h2>
    </div>
</div>

<?php if (session()->has('errors')): ?>
    <div class="ac-alert" style="background:var(--color-danger-soft); border-color:var(--color-danger-border);">
        <span class="material-symbols-outlined" style="color:var(--color-danger);" aria-hidden="true">error</span>
        <div class="ac-alert__text" style="color:var(--color-danger);">
            <?php foreach (session('errors') as $error): ?>
                <p><?= esc($error) ?></p>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<div class="ac-card" style="max-width:640px;">
    <form method="post" action="<?= site_url('admin/subjects/update/' . $subject['subject_id']) ?>">
        <?= csrf_field() ?>

        <div class="ac-field">
            <label for="subject_code">Subject Code</label>
            <input type="text" id="subject_code" name="subject_code" class="ac-input" value="<?= old('subject_code', $subject['subject_code']) ?>" required>
        </div>

        <div class="ac-field">
            <label for="subject_name">Subject Name</label>
            <input type="text" id="subject_name" name="subject_name" class="ac-input" value="<?= old('subject_name', $subject['subject_name']) ?>" required>
        </div>

        <div class="ac-field">
            <label for="sub_department_id">Owning Department</label>
            <select id="sub_department_id" name="sub_department_id" class="ac-input">
                <option value="">— Unassigned —</option>
                <?php foreach ($subDepartments as $deptId => $deptName): ?>
                    <option value="<?= (int) $deptId ?>" <?= old('sub_department_id', $subject['sub_department_id']) == $deptId ? 'selected' : '' ?>><?= esc($deptName) ?></option>
                <?php endforeach ?>
            </select>
            <p style="font-size:11.5px; color:var(--color-text-secondary); margin-top:4px;">The department whose chairperson manages this subject's catalog entry and scheduling.</p>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="ac-field">
                <label for="units">Units</label>
                <input type="number" step="0.5" id="units" name="units" class="ac-input" value="<?= old('units', $subject['units']) ?>">
            </div>
            <div class="ac-field">
                <label style="display:flex; align-items:center; gap:8px; margin-top:22px;">
                    <input type="checkbox" name="is_laboratory" value="1" <?= old('is_laboratory', $subject['is_laboratory']) ? 'checked' : '' ?>>
                    <span style="text-transform:none; font-weight:600; color:var(--color-text-primary); font-size:13px;">Laboratory subject</span>
                </label>
                <p style="font-size:11.5px; color:var(--color-text-secondary); margin-top:4px;">May only be plotted on Saturdays.</p>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="ac-field">
                <label for="lecture_units">Lecture Units</label>
                <input type="number" step="0.5" id="lecture_units" name="lecture_units" class="ac-input" value="<?= old('lecture_units', $subject['lecture_units']) ?>">
                <p style="font-size:11.5px; color:var(--color-text-secondary); margin-top:4px;">Optional split; overrides Units above for hour calc.</p>
            </div>
            <div class="ac-field">
                <label for="laboratory_units">Laboratory Units</label>
                <input type="number" step="0.5" id="laboratory_units" name="laboratory_units" class="ac-input" value="<?= old('laboratory_units', $subject['laboratory_units']) ?>">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="ac-field">
                <label for="contact_hours">Contact Hours per Meeting</label>
                <input type="number" step="0.5" id="contact_hours" name="contact_hours" class="ac-input" value="<?= old('contact_hours', $subject['contact_hours']) ?>">
            </div>
            <div class="ac-field">
                <label for="meetings_per_week">Meetings per Week</label>
                <input type="number" step="1" id="meetings_per_week" name="meetings_per_week" class="ac-input" value="<?= old('meetings_per_week', $subject['meetings_per_week']) ?>">
            </div>
        </div>

        <div class="ac-field">
            <label for="weekly_hours">Weekly Hours (derived, read-only)</label>
            <input type="text" id="weekly_hours" class="ac-input" value="<?= esc($subject['weekly_hours']) ?>" disabled>
        </div>

        <div class="ac-field">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="ac-input" rows="3"><?= esc($subject['description']) ?></textarea>
        </div>

        <div style="display:flex; gap:10px; margin-top:8px;">
            <a href="<?= site_url('admin/subjects') ?>" class="ac-btn ac-btn-secondary">Cancel</a>
            <button type="submit" class="ac-btn ac-btn-primary">Update Subject</button>
        </div>
    </form>
</div>

<div class="ac-card" style="max-width:640px; margin-top:16px;">
    <h3 style="margin:0 0 4px; font-size:15px;">Prerequisites</h3>
    <p style="font-size:12px; color:var(--color-text-secondary); margin:0 0 12px;">
        A student must have a <b>locked, passing grade</b> in every prerequisite before they can enroll in
        <b><?= esc($subject['subject_code']) ?></b>.
    </p>

    <?php if ($prerequisites === []): ?>
        <p style="font-size:13px; color:var(--color-text-secondary); margin:0 0 12px;">No prerequisites — any student may enroll.</p>
    <?php else: ?>
        <ul style="list-style:none; padding:0; margin:0 0 12px;">
            <?php foreach ($prerequisites as $p): ?>
                <li style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid var(--color-border, #e5e7eb);">
                    <span><b><?= esc($p['subject_code']) ?></b> — <?= esc($p['subject_name']) ?></span>
                    <form method="post" action="<?= site_url('admin/subjects/' . $subject['subject_id'] . '/prerequisites/' . $p['subject_id'] . '/remove') ?>" data-confirm="Remove <?= esc($p['subject_code'], 'attr') ?> as a prerequisite?">
                        <?= csrf_field() ?>
                        <button type="submit" class="ac-btn ac-btn-secondary">Remove</button>
                    </form>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>

    <form method="post" action="<?= site_url('admin/subjects/' . $subject['subject_id'] . '/prerequisites') ?>" style="display:flex; gap:10px; align-items:flex-end;">
        <?= csrf_field() ?>
        <div class="ac-field" style="flex:1; margin:0;">
            <label for="prerequisite_subject_id">Add prerequisite</label>
            <select id="prerequisite_subject_id" name="prerequisite_subject_id" class="ac-input" required <?= $candidates === [] ? 'disabled' : '' ?>>
                <option value=""><?= $candidates === [] ? 'No eligible subjects' : 'Choose a subject…' ?></option>
                <?php foreach ($candidates as $c): ?>
                    <option value="<?= esc($c['subject_id'], 'attr') ?>"><?= esc($c['subject_code']) ?> — <?= esc($c['subject_name']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <button type="submit" class="ac-btn ac-btn-primary" <?= $candidates === [] ? 'disabled' : '' ?>>Add</button>
    </form>
</div>

<div class="ac-card" style="max-width:640px; margin-top:16px;">
    <h3 style="margin:0 0 4px; font-size:15px;">Components</h3>
    <p style="font-size:12px; color:var(--color-text-secondary); margin:0 0 12px;">
        Optional: split this subject into independently schedulable parts (e.g. Lecture + Laboratory). Leave this empty
        for a simple subject — nothing changes for offerings that don't need components. Changes here apply to offerings
        created from now on; already-created offerings keep their own snapshot.
    </p>

    <?php if (empty($componentTemplates)): ?>
        <p style="font-size:13px; color:var(--color-text-secondary); margin:0 0 12px;">No components configured — this subject schedules as a single class.</p>
    <?php else: ?>
        <ul style="list-style:none; padding:0; margin:0 0 12px;">
            <?php foreach ($componentTemplates as $t): ?>
                <li style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid var(--color-border, #e5e7eb);">
                    <span>
                        <b><?= esc($t['component_type_name']) ?></b><?= $t['name'] ? ' — ' . esc($t['name']) : '' ?>
                        <span style="color:var(--color-text-secondary); font-size:12px;">
                            · <?= $t['is_required'] ? 'Required' : 'Optional' ?>
                            · <?= esc($t['assignment_rule']) ?>
                            <?= $t['required_hours'] !== null ? '· ' . esc($t['required_hours']) . 'h/wk' : '' ?>
                            <?= ! $t['is_active'] ? '· inactive' : '' ?>
                        </span>
                    </span>
                    <?php if ($t['is_active']): ?>
                        <form method="post" action="<?= site_url('admin/subjects/' . $subject['subject_id'] . '/components/' . $t['id'] . '/deactivate') ?>" data-confirm="Deactivate this component template? Existing offering components are unaffected.">
                            <?= csrf_field() ?>
                            <button type="submit" class="ac-btn ac-btn-secondary">Deactivate</button>
                        </form>
                    <?php endif ?>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>

    <form method="post" action="<?= site_url('admin/subjects/' . $subject['subject_id'] . '/components') ?>" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <?= csrf_field() ?>
        <div>
            <label style="display:block; font-size:12px; margin-bottom:4px;">Component type</label>
            <select name="component_type_id" required style="min-width:160px;">
                <option value="">Choose…</option>
                <?php foreach ($componentTypes as $ct): ?>
                    <option value="<?= (int) $ct['id'] ?>"><?= esc($ct['name']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:12px; margin-bottom:4px;">Instance name (optional)</label>
            <input type="text" name="name" placeholder="e.g. Laboratory">
        </div>
        <div>
            <label style="display:block; font-size:12px; margin-bottom:4px;">Required hours/wk</label>
            <input type="number" step="0.5" name="required_hours" style="width:100px;">
        </div>
        <div>
            <label style="display:block; font-size:12px; margin-bottom:4px;">Default capacity</label>
            <input type="number" step="1" name="default_capacity" style="width:100px;">
        </div>
        <div>
            <label style="display:block; font-size:12px; margin-bottom:4px;">Assignment rule</label>
            <select name="assignment_rule">
                <option value="shared">Shared (any number of instances)</option>
                <option value="one_of">One of (exactly one instance per student)</option>
            </select>
        </div>
        <div>
            <label style="display:flex; align-items:center; gap:6px;">
                <input type="checkbox" name="is_required" value="1" checked> Required
            </label>
        </div>
        <button type="submit" class="ac-btn ac-btn-primary">Add Component</button>
    </form>
</div>

<?= $this->endSection() ?>

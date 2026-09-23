<?= $this->extend('layouts/admin_console') ?>

<?php
/**
 * Availability & Subject Preferences — Faculty view (Akademus SIS)
 * Same visual shell as Teaching Loads (sidebar/topbar/cards) so the faculty
 * side reads as one consistent app, matching the chairperson side's design
 * language (schedule_plotting.php / admin console).
 */
$activeAdmin   = 'availability';
$pageTitle     = 'Availability & Preferences';
$brandSub      = 'Faculty Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = [
    '' => [
        'dashboard'    => ['label' => 'Dashboard',    'icon' => 'dashboard',        'url' => 'faculty/dashboard'],
        'profile'      => ['label' => 'Profile',      'icon' => 'person',           'url' => 'faculty/profile'],
        'schedule'     => ['label' => 'Schedule',     'icon' => 'calendar_month',   'url' => 'faculty/schedule'],
        'availability' => ['label' => 'Availability', 'icon' => 'event_available',  'url' => 'faculty/availability'],
        'grading'      => ['label' => 'Grading',      'icon' => 'grade',            'url' => 'faculty/grading'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
:root {
    --green: var(--color-success); --green-soft: var(--color-success-soft); --green-border: #bbf7d0;
    --red: var(--color-danger); --red-soft: var(--color-danger-soft); --red-border: #fecaca;
    --amber: var(--color-warning); --amber-soft: var(--color-warning-soft); --amber-border: #fde68a;
    --indigo: #4338ca; --indigo-soft: #e0e7ff; --indigo-border: #c7d2fe;
}

.container { max-width: 880px; margin: 0 auto; }

.page-head { margin-bottom: 24px; }
.page-head h1 { font-size: 22px; font-weight: 700; letter-spacing: -.02em; margin-bottom: 4px; color: var(--color-text-primary); }
.page-head p { color: var(--color-text-secondary); font-size: 13.5px; }

.notice { background: var(--indigo-soft); color: var(--indigo); padding: 16px 20px; border-radius: var(--radius-md); font-size: 13.5px; margin-bottom: 20px; }

.term-bar {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg);
    padding: 14px 20px; margin-bottom: 24px; box-shadow: var(--shadow-sm);
}
.term-bar label { font-size: 12px; font-weight: 600; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: .03em; }
.term-bar select {
    padding: 8px 32px 8px 14px; border: 1px solid var(--color-border); border-radius: var(--radius-sm);
    font-family: inherit; font-size: 13.5px; font-weight: 500; color: var(--color-text-primary);
    background: var(--color-surface) url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%226%22%3E%3Cpath d=%22M1 1l4 4 4-4%22 stroke=%22%2386868b%22 stroke-width=%221.5%22 fill=%22none%22/%3E%3C/svg%3E') no-repeat right 12px center;
    appearance: none; cursor: pointer;
}
.term-bar select:hover { border-color: var(--color-primary); }
.term-pill { font-size: 11.5px; font-weight: 700; color: var(--color-primary); background: var(--color-primary-soft); padding: 3px 10px; border-radius: var(--radius-pill); }

.avail-row { display: grid; grid-template-columns: 88px 150px 1fr 1fr; gap: 12px; align-items: center; padding: 12px 4px; border-bottom: 1px solid var(--color-border); }
.avail-row:last-of-type { border-bottom: none; }
.avail-day { font-size: 13.5px; font-weight: 700; color: var(--color-text-primary); }
.avail-row select, .avail-row input[type=time] {
    padding: 8px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm);
    font-family: inherit; font-size: 13px; color: var(--color-text-primary); background: var(--color-surface); width: 100%;
}
.avail-row select {
    background: var(--color-surface) url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%226%22%3E%3Cpath d=%22M1 1l4 4 4-4%22 stroke=%22%2386868b%22 stroke-width=%221.5%22 fill=%22none%22/%3E%3C/svg%3E') no-repeat right 12px center;
    appearance: none; padding-right: 30px; cursor: pointer;
}
.avail-row select:focus, .avail-row input:focus { outline: none; border-color: var(--color-primary); box-shadow: var(--focus-ring); }
.avail-row.is-available { background: var(--green-soft); border-radius: var(--radius-md); border-bottom-color: transparent; }
.avail-row.is-unavailable { background: var(--red-soft); border-radius: var(--radius-md); border-bottom-color: transparent; }
.avail-row input:disabled { background: var(--color-surface-sunken); color: var(--color-text-secondary); cursor: not-allowed; }

.subj-row { display: grid; grid-template-columns: 110px 1fr 170px; gap: 12px; align-items: center; padding: 12px 4px; border-bottom: 1px solid var(--color-border); }
.subj-row:last-of-type { border-bottom: none; }
.subj-code { font-size: 13px; font-weight: 700; color: var(--color-primary); }
.subj-title { font-size: 13px; color: var(--color-text-primary); }
.subj-row select {
    padding: 8px 30px 8px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm);
    font-family: inherit; font-size: 13px; font-weight: 600;
    background: var(--color-surface) url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%226%22%3E%3Cpath d=%22M1 1l4 4 4-4%22 stroke=%22%2386868b%22 stroke-width=%221.5%22 fill=%22none%22/%3E%3C/svg%3E') no-repeat right 12px center;
    appearance: none; cursor: pointer; width: 100%;
}
.subj-row select.lvl-preferred { color: var(--green); background-color: var(--green-soft); border-color: var(--green-border); }
.subj-row select.lvl-willing { color: #854d0e; background-color: var(--amber-soft); border-color: var(--amber-border); }
.subj-row select.lvl-qualified { color: var(--indigo); background-color: var(--indigo-soft); border-color: var(--indigo-border); }

.save-row { margin-top: 20px; display: flex; justify-content: flex-end; }

.legend { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 4px; margin-bottom: 16px; }
.legend span { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--color-text-secondary); }
.legend .dot { width: 8px; height: 8px; border-radius: 50%; }

@media (max-width: 640px) {
    .avail-row { grid-template-columns: 1fr; gap: 6px; padding: 14px 4px; }
    .subj-row { grid-template-columns: 1fr; gap: 6px; padding: 14px 4px; }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="container">

    <div class="page-head">
        <h1>Set Your Availability</h1>
        <p>Tell your chairperson when you can teach and which subjects you'd like — this shows up while they're plotting the schedule.</p>
    </div>

    <?php if (! empty($allTerms)): ?>
        <div class="term-bar">
            <label for="termSelect">Term</label>
            <select id="termSelect" onchange="if(this.value) window.location.href = '<?= site_url('faculty/availability') ?>?ay_id=' + this.value;">
                <?php foreach ($allTerms as $t): ?>
                    <option value="<?= (int) $t['ay_id'] ?>" <?= ($term && (int) $term['ay_id'] === (int) $t['ay_id']) ? 'selected' : '' ?>>
                        <?= esc(\App\Models\AcademicYearModel::format($t['label'], $t['semester'])) ?>
                    </option>
                <?php endforeach ?>
            </select>
            <?php if ($term && $term['is_active']): ?>
                <span class="term-pill">Active term</span>
            <?php endif ?>
        </div>
    <?php endif ?>

    <?php if (session()->has('message')): ?>
        <div class="flash success"><?= esc(session('message')) ?></div>
    <?php endif ?>

    <?php if (session()->has('errors')): ?>
        <div class="flash error">
            <?php foreach (session('errors') as $error): ?>
                <p style="margin:0;"><?= esc($error) ?></p>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if ($term === null): ?>
        <div class="notice">No academic term has been configured yet. Ask an admin to add one before you can submit your availability.</div>
    <?php else: ?>

        <div class="panel">
            <h2>Weekly Availability</h2>
            <p class="panel-sub">For each day, mark whether you're available, and if so, your time range. Leave a day as "No preference" if you'd rather not commit either way yet.</p>

            <form method="post" action="<?= site_url('faculty/availability/update') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="ay_id" value="<?= (int) $term['ay_id'] ?>">

                <?php foreach ($dayLabels as $day => $label): ?>
                    <?php $row = $availabilityByDay[$day] ?? null; ?>
                    <?php $status = $row ? ($row['is_available'] ? 'available' : 'unavailable') : ''; ?>
                    <div class="avail-row <?= $status === 'available' ? 'is-available' : ($status === 'unavailable' ? 'is-unavailable' : '') ?>" id="row_<?= $day ?>">
                        <div class="avail-day"><?= esc($label) ?></div>
                        <select name="day_<?= $day ?>_status" class="day-status" data-day="<?= $day ?>" onchange="toggleTimes(<?= $day ?>)">
                            <option value="" <?= $status === '' ? 'selected' : '' ?>>No preference</option>
                            <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="unavailable" <?= $status === 'unavailable' ? 'selected' : '' ?>>Not Available</option>
                        </select>
                        <input type="time" name="day_<?= $day ?>_start" id="start_<?= $day ?>" value="<?= esc($row['start_time'] ?? '') ?>">
                        <input type="time" name="day_<?= $day ?>_end" id="end_<?= $day ?>" value="<?= esc($row['end_time'] ?? '') ?>">
                    </div>
                <?php endforeach ?>

                <div class="save-row">
                    <button type="submit" class="btn btn-primary">Save Availability</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h2>Subject Preferences</h2>
            <p class="panel-sub">Let your chairperson know which subjects you'd like to teach this term.</p>

            <?php if (empty($subjects)): ?>
                <p class="panel-sub" style="margin-bottom:0;">No subjects found for your department yet.</p>
            <?php else: ?>
                <div class="legend">
                    <span><span class="dot" style="background:var(--green);"></span> Preferred</span>
                    <span><span class="dot" style="background:var(--amber);"></span> Willing</span>
                    <span><span class="dot" style="background:var(--indigo);"></span> Qualified</span>
                </div>

                <form method="post" action="<?= site_url('faculty/preferences/update') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ay_id" value="<?= (int) $term['ay_id'] ?>">

                    <?php foreach ($subjects as $subject): ?>
                        <?php $current = $preferencesById[$subject['subject_id']] ?? ''; ?>
                        <div class="subj-row">
                            <span class="subj-code"><?= esc($subject['subject_code']) ?></span>
                            <span class="subj-title"><?= esc($subject['subject_name']) ?></span>
                            <select name="preference[<?= $subject['subject_id'] ?>]" class="<?= $current !== '' ? 'lvl-' . $current : '' ?>" onchange="this.className = this.value ? 'lvl-' + this.value : ''">
                                <option value="" <?= $current === '' ? 'selected' : '' ?>>Not set</option>
                                <?php foreach ($preferenceLevels as $level): ?>
                                    <option value="<?= $level ?>" <?= $current === $level ? 'selected' : '' ?>><?= esc(ucfirst($level)) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    <?php endforeach ?>

                    <div class="save-row">
                        <button type="submit" class="btn btn-primary">Save Preferences</button>
                    </div>
                </form>
            <?php endif ?>
        </div>

    <?php endif ?>

</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function toggleTimes(day) {
        var status = document.querySelector('.day-status[data-day="' + day + '"]').value;
        var disabled = status !== 'available';
        document.getElementById('start_' + day).disabled = disabled;
        document.getElementById('end_' + day).disabled = disabled;
        if (disabled) {
            document.getElementById('start_' + day).value = '';
            document.getElementById('end_' + day).value = '';
        }
        var row = document.getElementById('row_' + day);
        row.classList.remove('is-available', 'is-unavailable');
        if (status === 'available') row.classList.add('is-available');
        else if (status === 'unavailable') row.classList.add('is-unavailable');
    }
    document.querySelectorAll('.day-status').forEach(function (el) {
        toggleTimes(el.dataset.day);
    });
</script>
<?= $this->endSection() ?>

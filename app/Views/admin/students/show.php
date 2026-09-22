<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<?php $s = $student; ?>

<div class="ac-page-header">
    <div>
        <a href="<?= site_url('admin/students') ?>" style="font-size:12.5px; color:#515f74;">&larr; Back to Student Records</a>
        <h2 class="ac-page-header__title" style="margin-top:6px;"><?= esc(trim($s['first_name'] . ' ' . $s['middle_name'] . ' ' . $s['last_name'])) ?></h2>
        <p class="ac-page-header__sub"><?= esc($s['student_number']) ?> &middot; <?= esc($s['program_code'] ?? 'No program set') ?> &middot;
            <span class="ac-status-dot ac-status-dot--<?= $s['student_status'] === 'active' ? 'active' : ($s['student_status'] === 'dropped' ? 'pending' : 'inactive') ?>"><?= esc(ucfirst($s['student_status'])) ?></span>
        </p>
    </div>
</div>

<div class="ac-tabs">
    <div class="ac-tabs__links">
        <a href="#" class="ac-tabs__link is-active" data-tab="personal" onclick="return srTab(event,'personal')">Personal Info</a>
        <a href="#" class="ac-tabs__link" data-tab="admission" onclick="return srTab(event,'admission')">Admission Info</a>
        <a href="#" class="ac-tabs__link" data-tab="program" onclick="return srTab(event,'program')">Program &amp; Curriculum</a>
        <a href="#" class="ac-tabs__link" data-tab="section" onclick="return srTab(event,'section')">Current Section</a>
        <a href="#" class="ac-tabs__link" data-tab="history" onclick="return srTab(event,'history')">Enrollment History</a>
        <a href="#" class="ac-tabs__link" data-tab="status" onclick="return srTab(event,'status')">Academic Status</a>
    </div>
</div>

<!-- Personal Info -->
<div class="ac-table-card sr-panel" id="sr-personal">
    <div style="padding:20px 24px; display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div><label class="ac-muted" style="font-size:11px; text-transform:uppercase;">First Name</label><p><?= esc($s['first_name'] ?: '—') ?></p></div>
        <div><label class="ac-muted" style="font-size:11px; text-transform:uppercase;">Last Name</label><p><?= esc($s['last_name'] ?: '—') ?></p></div>
        <div><label class="ac-muted" style="font-size:11px; text-transform:uppercase;">Middle Name</label><p><?= esc($s['middle_name'] ?: '—') ?></p></div>
        <div><label class="ac-muted" style="font-size:11px; text-transform:uppercase;">Username</label><p><?= esc($s['username']) ?></p></div>
        <div><label class="ac-muted" style="font-size:11px; text-transform:uppercase;">Email</label><p><?= esc($s['email'] ?: '—') ?></p></div>
        <div><label class="ac-muted" style="font-size:11px; text-transform:uppercase;">Contact Number</label><p><?= esc($s['contact_number'] ?: '—') ?></p></div>
    </div>
    <p style="padding:0 24px 16px; font-size:11.5px; color:#515f74;">Editable by Admin/Registrar. Owned by SIS once promoted from Admissions — corrections here do not sync back to the Admissions Portal.</p>
</div>

<!-- Admission Info -->
<div class="ac-table-card sr-panel" id="sr-admission" hidden>
    <div style="padding:20px 24px; display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div><label class="ac-muted" style="font-size:11px; text-transform:uppercase;">Applicant ID</label><p><?= esc($s['applicant_id']) ?></p></div>
        <div><label class="ac-muted" style="font-size:11px; text-transform:uppercase;">Admission Date</label><p><?= esc($s['admission_date'] ? date('M j, Y g:i A', strtotime($s['admission_date'])) : '—') ?></p></div>
    </div>
    <p style="padding:0 24px 16px; font-size:11.5px; color:#515f74;">Read-only — a historical fact owned by the Admissions Portal, recorded at the moment this student was promoted (<code>POST /api/students/promote</code>).</p>
</div>

<!-- Program & Curriculum -->
<div class="ac-table-card sr-panel" id="sr-program" hidden>
    <div style="padding:20px 24px; display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div><label class="ac-muted" style="font-size:11px; text-transform:uppercase;">Program</label><p><?= esc($s['program_name'] ?? '—') ?> <?= $s['program_code'] ? '(' . esc($s['program_code']) . ')' : '' ?></p></div>
        <div>
            <label class="ac-muted" style="font-size:11px; text-transform:uppercase;">Curriculum Version</label>
            <p>
                <?php if ($s['curriculum_code']): ?>
                    <?= esc($s['curriculum_code']) ?> &middot; v<?= esc($s['curriculum_version']) ?>
                    <span class="ac-status-dot ac-status-dot--<?= $s['curriculum_status'] === 'published' ? 'active' : 'inactive' ?>"><?= esc(ucfirst($s['curriculum_status'])) ?></span>
                <?php else: ?>
                    <span class="ac-muted">Not assigned</span>
                <?php endif ?>
            </p>
        </div>
    </div>

    <div style="padding:0 24px 20px; max-width:720px;">
        <form method="post" action="<?= site_url('admin/students/' . $s['student_id'] . '/curriculum') ?>" style="display:grid; grid-template-columns:1fr 2fr auto; gap:10px; align-items:end;">
            <?= csrf_field() ?>
            <div class="ac-field" style="margin:0;">
                <label>Assign Curriculum Version</label>
                <select name="curriculum_id" class="ac-input" required>
                    <option value="">— Select —</option>
                    <?php foreach ($s['available_curricula'] as $c): ?>
                        <option value="<?= $c['curriculum_id'] ?>" <?= (int) $s['curriculum_id'] === (int) $c['curriculum_id'] ? 'disabled' : '' ?>>
                            <?= esc($c['curriculum_code']) ?> — v<?= esc($c['version_number']) ?><?= $c['is_current'] ? ' (current)' : '' ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Reason (required)</label>
                <input type="text" name="reason" class="ac-input" placeholder="Why is this student moving curriculum versions?" required minlength="5">
            </div>
            <button type="submit" class="ac-btn ac-btn-primary" onclick="return confirm('Reassign this student\'s curriculum version? This is recorded permanently.')">Assign</button>
        </form>
        <?php if (empty($s['available_curricula'])): ?>
            <p class="ac-muted" style="font-size:11.5px; margin-top:8px;">No published curriculum exists for this student's program yet — publish one from Admin &rarr; Curricula.</p>
        <?php endif ?>
    </div>

    <h4 style="padding:0 24px; font-size:13px;">Curriculum History</h4>
    <table class="ac-table">
        <thead><tr><th>When</th><th>From</th><th>To</th><th>Reason</th><th>Changed By</th></tr></thead>
        <tbody>
        <?php if (empty($s['curriculum_history'])): ?>
            <tr><td colspan="5" class="ac-muted">No curriculum reassignments recorded yet.</td></tr>
        <?php endif ?>
        <?php foreach ($s['curriculum_history'] as $log): ?>
            <tr>
                <td><?= esc(date('M j, Y g:i A', strtotime($log['created_at']))) ?></td>
                <td><?= esc($log['from_code'] ? $log['from_code'] . ' v' . $log['from_version'] : '—') ?></td>
                <td><?= esc($log['to_code'] ? $log['to_code'] . ' v' . $log['to_version'] : '—') ?></td>
                <td><?= esc($log['reason']) ?></td>
                <td><?= esc($log['changed_by_username'] ?? '—') ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <p style="padding:12px 24px; font-size:11.5px; color:#515f74;">
        This governs the <code>curriculum</code>/<code>curriculum_subjects</code> system only. The chairperson-facing
        <code>curriculum_presets</code> (used for schedule-plotting templates) is a separate, still-unreconciled concept —
        see the SIS development roadmap.
    </p>
</div>

<!-- Current Section -->
<div class="ac-table-card sr-panel" id="sr-section" hidden>
    <div style="padding:20px 24px;">
        <?php if ($s['section']): ?>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
                <div><label class="ac-muted" style="font-size:11px; text-transform:uppercase;">Section</label><p><?= esc($s['section']['section']) ?></p></div>
                <div><label class="ac-muted" style="font-size:11px; text-transform:uppercase;">Year Level</label><p><?= esc($s['section']['year_level']) ?></p></div>
                <div><label class="ac-muted" style="font-size:11px; text-transform:uppercase;">Academic Year</label><p><?= esc($s['section']['academic_year']) ?></p></div>
            </div>
        <?php else: ?>
            <div class="ac-empty-panel">Not yet assigned to a section.</div>
        <?php endif ?>
    </div>
    <p style="padding:0 24px 16px; font-size:11.5px; color:#515f74;">Read-only here by design — section assignment is owned by the Chairperson (<code>ChairpersonController::assignStudent</code>), scoped to their own sub-department. Reassign from Chairperson &rarr; Sections.</p>
</div>

<!-- Enrollment History -->
<div class="ac-table-card sr-panel" id="sr-history" hidden>
    <table class="ac-table">
        <thead><tr><th>Academic Year</th><th>Subject</th><th>Section</th><th>Method</th><th>Status</th><th>Enrolled On</th></tr></thead>
        <tbody>
        <?php if (empty($s['enrollment_history'])): ?>
            <tr><td colspan="6" class="ac-muted">No enrollment records yet.</td></tr>
        <?php endif ?>
        <?php foreach ($s['enrollment_history'] as $h): ?>
            <tr>
                <td><?= esc($h['academic_year']) ?></td>
                <td><?= esc($h['subject_code']) ?></td>
                <td><?= esc($h['program']) ?> &middot; <?= esc($h['year_level']) ?> &middot; <?= esc($h['section']) ?></td>
                <td><?= esc(ucfirst($h['method'])) ?></td>
                <td><?= esc(ucfirst($h['status'])) ?></td>
                <td><?= esc(date('M j, Y', strtotime($h['created_at']))) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <p style="padding:12px 24px; font-size:11.5px; color:#515f74;">Append-only — sourced directly from <code>enrollments</code>. This is the complete record; there is no separate archival table.</p>
</div>

<!-- Academic Status -->
<div class="ac-table-card sr-panel" id="sr-status" hidden>
    <div style="padding:20px 24px;">
        <p style="margin-bottom:14px;">Current status: <span class="ac-status-dot ac-status-dot--<?= $s['student_status'] === 'active' ? 'active' : ($s['student_status'] === 'dropped' ? 'pending' : 'inactive') ?>"><?= esc(ucfirst($s['student_status'])) ?></span></p>

        <form method="post" action="<?= site_url('admin/students/' . $s['student_id'] . '/status') ?>" style="display:grid; grid-template-columns:1fr 2fr auto; gap:10px; align-items:end; max-width:720px;">
            <?= csrf_field() ?>
            <div class="ac-field" style="margin:0;">
                <label>New Status</label>
                <select name="student_status" class="ac-input" required>
                    <?php foreach ($statuses as $st): ?>
                        <option value="<?= esc($st) ?>" <?= $st === $s['student_status'] ? 'disabled' : '' ?>><?= esc(ucfirst($st)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="ac-field" style="margin:0;">
                <label>Reason (required)</label>
                <input type="text" name="reason" class="ac-input" placeholder="Why is this status changing?" required minlength="5">
            </div>
            <button type="submit" class="ac-btn ac-btn-primary" onclick="return confirm('Change this student\'s status? This is recorded permanently.')">Change Status</button>
        </form>

        <h4 style="margin:24px 0 8px; font-size:13px;">Status History</h4>
        <table class="ac-table">
            <thead><tr><th>When</th><th>From</th><th>To</th><th>Reason</th><th>Changed By</th></tr></thead>
            <tbody>
            <?php if (empty($s['status_history'])): ?>
                <tr><td colspan="5" class="ac-muted">No status changes recorded yet.</td></tr>
            <?php endif ?>
            <?php foreach ($s['status_history'] as $log): ?>
                <tr>
                    <td><?= esc(date('M j, Y g:i A', strtotime($log['created_at']))) ?></td>
                    <td><?= esc($log['from_status'] ? ucfirst($log['from_status']) : '—') ?></td>
                    <td><?= esc(ucfirst($log['to_status'])) ?></td>
                    <td><?= esc($log['reason']) ?></td>
                    <td><?= esc($log['changed_by_username'] ?? '—') ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function srTab(e, name) {
    e.preventDefault();
    document.querySelectorAll('.sr-panel').forEach(function (p) { p.hidden = true; });
    document.querySelectorAll('.ac-tabs__link').forEach(function (l) { l.classList.remove('is-active'); });
    document.getElementById('sr-' + name).hidden = false;
    document.querySelector('.ac-tabs__link[data-tab="' + name + '"]').classList.add('is-active');
    return false;
}
</script>
<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'enlistment-approvals';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Enlistment Approvals</h2><p class="ac-page-header__sub">Submitted enlistments awaiting your recommendation, on their way to the dean. Click a row to see the subjects enlisted and the student's grade history.</p></div>
</div>

<?php if (session('errors')): ?>
    <div class="ac-alert" style="background:var(--color-danger-soft); margin-bottom:16px;">
        <div class="ac-alert__text" style="color:var(--color-danger);">
            <?php foreach (session('errors') as $error): ?><p><?= esc($error) ?></p><?php endforeach ?>
        </div>
    </div>
<?php endif ?>
<?php if (session('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;"><span class="ac-notice__text"><?= esc(session('message')) ?></span></div>
<?php endif ?>

<?php if ($requests === []): ?>
    <div class="ac-empty-block"><p>Nothing awaiting your review.</p></div>
<?php else: ?>
    <div class="ac-dtable ea-table">
        <div class="ac-dtable__head">
            <div>Student</div><div>Program</div><div>Academic Year</div><div>Submitted</div><div>Actions</div>
        </div>
        <?php foreach ($requests as $r): ?>
            <?php
            $initials = strtoupper(mb_substr($r['username'], 0, 1));
            $photoUrl = avatar_photo_url((int) $r['user_id'], $r['photo_mime'] ?? null);
            ?>
            <div class="ac-dtable__row ea-row" data-id="<?= (int) $r['id'] ?>" tabindex="0" role="button" aria-label="View enlistment details for <?= esc($r['username'], 'attr') ?>">
                <div class="ac-user-cell" data-label="Student">
                    <div class="ac-avatar" style="<?= $photoUrl === null ? 'background:' . esc(avatar_color($r['username']), 'attr') . ';color:#fff;' : '' ?>" aria-hidden="true">
                        <?php if ($photoUrl !== null): ?>
                            <img src="<?= esc($photoUrl) ?>" alt="">
                        <?php else: ?>
                            <?= esc($initials) ?>
                        <?php endif ?>
                    </div>
                    <div>
                        <p class="ac-user-cell__name"><?= esc($r['username']) ?></p>
                        <p class="ac-user-cell__id"><?= esc($r['student_number']) ?></p>
                    </div>
                </div>
                <div class="ac-cell" data-label="Program"><?= esc($r['program_name'] ?? '—') ?></div>
                <div class="ac-cell" data-label="Academic Year"><?= esc($r['academic_year']) ?></div>
                <div class="ac-cell-muted" data-label="Submitted"><?= esc(date('M j, Y g:i A', strtotime($r['submitted_at']))) ?></div>
                <div data-label="Actions" class="ea-actions" id="eaActions<?= (int) $r['id'] ?>">
                    <div class="ea-actions__default" style="display:flex; gap:8px;">
                        <form method="post" action="<?= site_url('chairperson/enlistment-approvals/recommend') ?>" data-confirm="Recommend <?= esc($r['username'], 'attr') ?>'s enlistment to the dean?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="ac-btn ac-btn-primary">Recommend</button>
                        </form>
                        <button type="button" class="ac-btn-outline" onclick="eaToggleReturn(event, <?= (int) $r['id'] ?>, true)">Return</button>
                    </div>
                    <form method="post" action="<?= site_url('chairperson/enlistment-approvals/return') ?>" class="ea-actions__return" style="display:none; gap:6px;" data-confirm="Return this enlistment to the student? They'll see your reason.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <input type="text" name="notes" class="ac-input" placeholder="Reason (required)" required minlength="3" style="min-width:140px;" onclick="event.stopPropagation()">
                        <button type="submit" class="ac-btn ac-btn-primary">Confirm</button>
                        <button type="button" class="ac-btn-outline" onclick="eaToggleReturn(event, <?= (int) $r['id'] ?>, false)">Cancel</button>
                    </form>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<!-- Detail modal: subjects enlisted + grade history, loaded on demand -->
<div class="ac-modal" id="eaDetailModal">
    <div class="ac-modal__panel" style="max-width:640px;">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title" id="eaDetailTitle">Enlistment Details</div>
                <div class="ac-modal__subtitle" id="eaDetailSubtitle"></div>
            </div>
            <button type="button" onclick="acCloseModal('eaDetailModal')" class="ac-modal__close">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
        <div class="ac-modal__body" id="eaDetailBody">
            <div class="cs-skeleton-rows">
                <div class="cs-skeleton-row"></div>
                <div class="cs-skeleton-row"></div>
                <div class="cs-skeleton-row"></div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function eaToggleReturn(e, id, show) {
    e.stopPropagation();
    var wrap = document.getElementById('eaActions' + id);
    wrap.querySelector('.ea-actions__default').style.display = show ? 'none' : 'flex';
    var form = wrap.querySelector('.ea-actions__return');
    form.style.display = show ? 'flex' : 'none';
    if (show) form.querySelector('input[name="notes"]').focus();
}

(function () {
    var detailUrlBase = <?= json_encode(site_url('chairperson/enlistment-approvals')) ?>;
    var body = document.getElementById('eaDetailBody');
    var subtitle = document.getElementById('eaDetailSubtitle');

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function gradeClass(g) {
        if (g.is_passing === null || g.is_passing === undefined || g.is_passing === '') return 'ac-pill--neutral';
        return (g.is_passing === true || g.is_passing === 1 || g.is_passing === '1') ? 'ac-pill--success' : 'ac-pill--danger';
    }

    function renderDetail(data) {
        var items  = data.items || [];
        var grades = data.grades || [];
        var html   = '';

        html += '<p style="font-size:11px; font-weight:700; color:var(--color-text-secondary); text-transform:uppercase; letter-spacing:.04em; margin:0 0 8px;">Subjects Enlisted (' + items.length + ' &middot; ' + escapeHtml(data.units) + ' units)</p>';
        if (items.length === 0) {
            html += '<p class="ac-muted" style="font-size:13px; margin:0 0 20px;">No subjects on this enlistment.</p>';
        } else {
            html += '<table class="ac-table" style="margin-bottom:20px;"><thead><tr><th>Code</th><th>Subject</th><th>Section</th><th>Units</th></tr></thead><tbody>';
            items.forEach(function (it) {
                var section = it.program ? (escapeHtml(it.program) + ' · ' + escapeHtml(it.year_level) + ' · ' + escapeHtml(it.section)) : '—';
                html += '<tr><td style="white-space:nowrap;"><strong>' + escapeHtml(it.subject_code) + '</strong></td><td>' + escapeHtml(it.subject_name || '—') + '</td><td>' + section + '</td><td>' + escapeHtml(it.units) + '</td></tr>';
            });
            html += '</tbody></table>';
        }

        html += '<p style="font-size:11px; font-weight:700; color:var(--color-text-secondary); text-transform:uppercase; letter-spacing:.04em; margin:0 0 8px;">Grade History</p>';
        if (grades.length === 0) {
            html += '<p class="ac-muted" style="font-size:13px; margin:0;">No locked grades on record yet.</p>';
        } else {
            html += '<table class="ac-table"><thead><tr><th>Academic Year</th><th>Code</th><th>Subject</th><th>Grade</th></tr></thead><tbody>';
            grades.forEach(function (g) {
                html += '<tr><td>' + escapeHtml(g.academic_year) + '</td><td>' + escapeHtml(g.subject_code) + '</td><td>' + escapeHtml(g.subject_name || '—') + '</td>'
                    + '<td><span class="ac-pill ' + gradeClass(g) + '">' + escapeHtml(g.grade_value) + '</span></td></tr>';
            });
            html += '</tbody></table>';
        }

        body.className = 'ac-modal__body animate-enter';
        body.innerHTML = html;
    }

    function openDetail(id, name, studentNo) {
        subtitle.textContent = name + ' · ' + studentNo;
        body.className = 'ac-modal__body';
        body.innerHTML = '<div class="cs-skeleton-rows"><div class="cs-skeleton-row"></div><div class="cs-skeleton-row"></div><div class="cs-skeleton-row"></div></div>';
        acOpenModal('eaDetailModal');

        fetch(detailUrlBase + '/' + id + '/detail', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) { body.innerHTML = '<p class="ac-muted">' + escapeHtml(data.error || 'Could not load details.') + '</p>'; return; }
                renderDetail(data);
            })
            .catch(function () {
                body.innerHTML = '<p class="ac-muted">Could not load details — please try again.</p>';
            });
    }

    document.querySelectorAll('.ea-row').forEach(function (row) {
        function go(e) {
            if (e.target.closest('a, button, input, form')) return;
            var nameEl = row.querySelector('.ac-user-cell__name');
            var idEl = row.querySelector('.ac-user-cell__id');
            openDetail(row.dataset.id, nameEl.textContent, idEl.textContent);
        }
        row.style.cursor = 'pointer';
        row.addEventListener('click', go);
        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(e); }
        });
    });
})();
</script>
<?= $this->endSection() ?>

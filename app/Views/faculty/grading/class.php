<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($context['subject_code']) ?> Grading</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/tokens.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/base.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/base.css') ?>">
    <style>
        <?= $this->include('components/shell/style') ?>

        table.grade-table { width: 100%; border-collapse: collapse; }
        table.grade-table th, table.grade-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--outline-variant); font-size: 13px; }
        table.grade-table input[type=text] { width: 90px; padding: 6px 8px; border: 1px solid var(--outline-variant); border-radius: 8px; }
        table.grade-table input[type=text].remarks { width: 160px; }
        .status-pill { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .status-pill.locked { background: #dcfce7; color: #14532d; }
        .status-pill.submitted { background: #dff8fb; color: #164e63; }
        .status-pill.draft { background: #fef9c3; color: #713f12; }
    </style>
</head>

<body>

    <aside class="side-drawer glass">
        <div class="brand"><span class="material-symbols-outlined">layers</span></div>
        <nav>
            <a class="drawer-link" href="<?= site_url('faculty/dashboard') ?>" title="Dashboard">
                <span class="material-symbols-outlined">home</span>
                <span class="drawer-label">Dashboard</span>
            </a>
            <a class="drawer-link" href="<?= site_url('faculty/profile') ?>" title="Profile">
                <span class="material-symbols-outlined">person</span>
                <span class="drawer-label">Profile</span>
            </a>
            <a class="drawer-link" href="<?= site_url('faculty/schedule') ?>" title="Schedule">
                <span class="material-symbols-outlined">calendar_month</span>
                <span class="drawer-label">Schedule</span>
            </a>
            <a class="drawer-link" href="<?= site_url('faculty/availability') ?>" title="Availability">
                <span class="material-symbols-outlined">event_available</span>
                <span class="drawer-label">Availability</span>
            </a>
            <a class="drawer-link active" href="<?= site_url('faculty/grading') ?>" title="Grading">
                <span class="material-symbols-outlined">grade</span>
                <span class="drawer-label">Grading</span>
            </a>
        </nav>

        <a class="drawer-link drawer-link--logout" href="<?= site_url('logout') ?>" title="Logout">
            <span class="material-symbols-outlined">logout</span>
            <span class="drawer-label">Logout</span>
        </a>
    </aside>

    <div class="app-main">

        <div class="header-card glass">
            <header class="topbar">
                <h1><?= esc($context['subject_code']) ?> &middot; <?= esc($context['program']) ?> <?= esc($context['year_level']) ?>-<?= esc($context['section']) ?></h1>
            </header>
        </div>

        <div class="content">

            <div class="panel" id="feedback" style="display:none;"></div>

            <div class="panel">
                <?php if ($offering !== null): ?>
                    <p class="panel-sub">This class is merged with other sections into one shared roster (<?= count($roster) ?> students).</p>
                <?php endif ?>

                <?php if (empty($roster)): ?>
                    <div class="empty-state">
                        <div class="material-symbols-outlined">group_off</div>
                        <p>No students are enrolled in this class yet.</p>
                    </div>
                <?php else: ?>
                    <table class="grade-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Grade</th>
                                <th>Remarks</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roster as $row): ?>
                                <?php $g = $row['grade']; $locked = $g !== null && $g['status'] === 'locked'; ?>
                                <tr data-enrollment-id="<?= esc($row['enrollment_id']) ?>">
                                    <td><?= esc($row['username']) ?></td>
                                    <td>
                                        <input type="text" class="grade-input" value="<?= $g !== null ? esc($g['grade_value']) : '' ?>" <?= $locked ? 'disabled' : '' ?>>
                                    </td>
                                    <td>
                                        <input type="text" class="remarks-input" value="<?= $g !== null ? esc($g['remarks']) : '' ?>" <?= $locked ? 'disabled' : '' ?>>
                                    </td>
                                    <td>
                                        <?php if ($g !== null): ?>
                                            <span class="status-pill <?= esc($g['status']) ?>"><?= esc(ucfirst($g['status'])) ?></span>
                                        <?php endif ?>
                                    </td>
                                    <td>
                                        <?php if ($locked): ?>
                                            <button type="button" class="btn btn-secondary" onclick="openRequestChange(<?= esc($g['grade_id']) ?>, '<?= esc($g['grade_value'], 'js') ?>')">Request Change</button>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>

                    <div style="display:flex; gap:10px; margin-top:16px;">
                        <button type="button" class="btn btn-secondary" onclick="saveDraft()">Save Draft</button>
                        <button type="button" class="btn btn-primary" onclick="submitClass()">Submit for Approval</button>
                    </div>
                <?php endif ?>
            </div>

        </div>

    </div>

    <!-- Request change modal (simple inline prompt-based, matches offerings.php's plain confirm()/prompt() style) -->

    <script>
        const CONTEXT = <?= json_encode($context) ?>;

        async function postJSON(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(body),
            });
            return res.json();
        }

        function showFeedback(message, isError) {
            const el = document.getElementById('feedback');
            el.style.display = 'block';
            el.style.color = isError ? '#a11414' : '#056945';
            el.textContent = message;
        }

        function collectGrades() {
            return Array.from(document.querySelectorAll('tr[data-enrollment-id]')).map((row) => ({
                enrollment_id: parseInt(row.dataset.enrollmentId, 10),
                value: row.querySelector('.grade-input').value.trim(),
                remarks: row.querySelector('.remarks-input').value.trim(),
            }));
        }

        async function saveDraft() {
            const result = await postJSON('<?= site_url('faculty/grading/save') ?>', { ...CONTEXT, grades: collectGrades() });
            if (!result.ok) {
                showFeedback((result.errors || [result.error]).join(' '), true);
                return;
            }
            showFeedback('Grades saved.', false);
        }

        async function submitClass() {
            if (!confirm('Submit this class\'s grades for chairperson approval? You can still edit until it is locked.')) return;

            const saveResult = await postJSON('<?= site_url('faculty/grading/save') ?>', { ...CONTEXT, grades: collectGrades() });
            if (!saveResult.ok) {
                showFeedback((saveResult.errors || [saveResult.error]).join(' '), true);
                return;
            }

            const result = await postJSON('<?= site_url('faculty/grading/submit') ?>', CONTEXT);
            if (!result.ok) {
                showFeedback(result.error || 'Could not submit.', true);
                return;
            }

            showFeedback('Submitted for chairperson approval.', false);
            setTimeout(() => location.reload(), 800);
        }

        async function openRequestChange(gradeId, currentValue) {
            const newValue = prompt(`Current value: ${currentValue}\nEnter the corrected value:`);
            if (!newValue) return;
            const reason = prompt('Reason for this correction (required):');
            if (!reason) return;

            const result = await postJSON('<?= site_url('faculty/grading/request-change') ?>', {
                grade_id: gradeId,
                requested_value: newValue.trim(),
                reason: reason.trim(),
            });

            if (!result.ok) {
                showFeedback(result.error || 'Could not file the request.', true);
                return;
            }

            showFeedback('Change request submitted to the chairperson.', false);
        }
    </script>

</body>
</html>

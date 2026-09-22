<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($context['subject_code']) ?> Grade Approval</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/tokens.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/base.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/base.css') ?>">
    <style>
        <?= $this->include('components/shell/style') ?>

        table.grade-table { width: 100%; border-collapse: collapse; }
        table.grade-table th, table.grade-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--outline-variant); font-size: 13px; }
        .status-pill { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .status-pill.submitted { background: #dff8fb; color: #164e63; }
        .status-pill.locked { background: #dcfce7; color: #14532d; }
    </style>
</head>

<body>

    <aside class="side-drawer glass">
        <div class="brand"><span class="material-symbols-outlined">layers</span></div>
        <nav>
            <a class="drawer-link" href="<?= site_url('chairperson/overview') ?>" title="Dashboard">
                <span class="material-symbols-outlined">home</span>
                <span class="drawer-label">Dashboard</span>
            </a>
            <a class="drawer-link active" href="<?= site_url('chairperson/grade-approvals') ?>" title="Grade Approvals">
                <span class="material-symbols-outlined">grade</span>
                <span class="drawer-label">Grade Approvals</span>
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
                    <p class="panel-sub">This is a merged class combining multiple sections' rosters (<?= count($roster) ?> students).</p>
                <?php endif ?>

                <?php if (empty($roster)): ?>
                    <div class="empty-state">
                        <div class="material-symbols-outlined">group_off</div>
                        <p>No students are enrolled in this class.</p>
                    </div>
                <?php else: ?>
                    <table class="grade-table">
                        <thead>
                            <tr><th>Student</th><th>Grade</th><th>Remarks</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php $allSubmitted = true; ?>
                            <?php foreach ($roster as $row): ?>
                                <?php $g = $row['grade']; if ($g === null || $g['status'] !== 'submitted') { $allSubmitted = false; } ?>
                                <tr>
                                    <td><?= esc($row['username']) ?></td>
                                    <td><?= $g !== null ? esc($g['grade_value']) : '—' ?></td>
                                    <td><?= $g !== null && $g['remarks'] !== null ? esc($g['remarks']) : '—' ?></td>
                                    <td><?= $g !== null ? '<span class="status-pill ' . esc($g['status']) . '">' . esc(ucfirst($g['status'])) . '</span>' : '—' ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>

                    <div style="margin-top:16px;">
                        <?php if ($allSubmitted): ?>
                            <button type="button" class="btn btn-primary" onclick="lockClass()">Lock All &amp; Make Official</button>
                        <?php else: ?>
                            <p class="panel-sub">Not every student has a submitted grade yet — nothing to lock.</p>
                        <?php endif ?>
                    </div>
                <?php endif ?>
            </div>

        </div>

    </div>

    <script>
        const CONTEXT = <?= json_encode($context) ?>;

        function showFeedback(message, isError) {
            const el = document.getElementById('feedback');
            el.style.display = 'block';
            el.style.color = isError ? '#a11414' : '#056945';
            el.textContent = message;
        }

        async function lockClass() {
            if (!confirm('Lock these grades? Once locked, changes require a formal correction request.')) return;

            const res = await fetch('<?= site_url('chairperson/grade-approvals/lock') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(CONTEXT),
            });
            const result = await res.json();

            if (!result.ok) {
                showFeedback(result.error || 'Could not lock.', true);
                return;
            }

            showFeedback('Grades locked and now official.', false);
            setTimeout(() => { window.location.href = '<?= site_url('chairperson/grade-approvals') ?>'; }, 800);
        }
    </script>

</body>
</html>

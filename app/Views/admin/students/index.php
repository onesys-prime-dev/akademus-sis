<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Student Records</h2>
        <p class="ac-page-header__sub">Every student promoted from Admissions, independent of their own self-service portal.</p>
    </div>
</div>

<form method="get" style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto; gap:10px; margin-bottom:16px; align-items:end;">
    <div class="ac-field" style="margin:0;">
        <label>Search</label>
        <input type="text" name="q" class="ac-input" placeholder="Name, student number, or email" value="<?= esc($filters['q'] ?? '') ?>">
    </div>
    <div class="ac-field" style="margin:0;">
        <label>Program</label>
        <select name="program_id" class="ac-input">
            <option value="">All programs</option>
            <?php foreach ($programs as $p): ?>
                <option value="<?= esc($p['program_id']) ?>" <?= (string) ($filters['program_id'] ?? '') === (string) $p['program_id'] ? 'selected' : '' ?>><?= esc($p['program_code']) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="ac-field" style="margin:0;">
        <label>Year Level</label>
        <select name="year_level" class="ac-input">
            <option value="">All year levels</option>
            <?php foreach ($yearLevels as $yl): ?>
                <option value="<?= esc($yl) ?>" <?= ($filters['year_level'] ?? '') === $yl ? 'selected' : '' ?>><?= esc($yl) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="ac-field" style="margin:0;">
        <label>Status</label>
        <select name="status" class="ac-input">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= esc($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= esc(ucfirst($s)) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <button type="submit" class="ac-btn ac-btn-primary">Filter</button>
</form>

<div class="ac-dtable">
    <div class="ac-dtable__head">
        <div>Student</div>
        <div>Student No.</div>
        <div>Program</div>
        <div>Section</div>
        <div>Status</div>
        <div style="text-align:right;">Actions</div>
    </div>

    <?php if (empty($students)): ?>
        <div class="ac-empty-panel">No students match these filters. If you expected results, confirm the Admission&nbsp;→&nbsp;SIS handoff has run (<code>POST /api/students/promote</code>) or the development seeder has been applied.</div>
    <?php endif ?>

    <?php foreach ($students as $s): ?>
        <?php $initials = strtoupper(substr($s['first_name'] ?? '?', 0, 1) . substr($s['last_name'] ?? '', 0, 1)); ?>
        <div class="ac-dtable__row">
            <div class="ac-user-cell">
                <div class="ac-avatar" style="background:<?= esc(avatar_color($s['username']), 'attr') ?>;color:#fff;"><?= esc($initials) ?></div>
                <div>
                    <p class="ac-user-cell__name"><?= esc(trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''))) ?: esc($s['username']) ?></p>
                    <p class="ac-user-cell__id"><?= esc($s['email'] ?? $s['username']) ?></p>
                </div>
            </div>
            <div class="ac-cell-muted"><?= esc($s['student_number']) ?></div>
            <div class="ac-cell"><?= esc($s['program_code'] ?? '—') ?></div>
            <div class="ac-cell">
                <?php if ($s['section']): ?>
                    <?= esc($s['section']) ?> · <?= esc($s['year_level']) ?>
                <?php else: ?>
                    <span class="ac-muted">Unassigned</span>
                <?php endif ?>
            </div>
            <div>
                <span class="ac-status-dot ac-status-dot--<?= $s['student_status'] === 'active' ? 'active' : ($s['student_status'] === 'dropped' ? 'pending' : 'inactive') ?>">
                    <?= esc(ucfirst($s['student_status'])) ?>
                </span>
            </div>
            <div class="ac-actions-cell">
                <a href="<?= site_url($basePath . '/students/' . $s['student_id']) ?>" class="ac-btn-outline">View Record</a>
            </div>
        </div>
    <?php endforeach ?>

    <?php
        $rangeStart = $totalStudents === 0 ? 0 : (($page - 1) * $perPage) + 1;
        $rangeEnd   = min($page * $perPage, $totalStudents);
        $qs         = array_filter([
            'q'          => $filters['q'] ?? '',
            'program_id' => $filters['program_id'] ?? '',
            'year_level' => $filters['year_level'] ?? '',
            'status'     => $filters['status'] ?? '',
        ], static fn ($v) => $v !== '' && $v !== null);
    ?>
    <div class="ac-pagination">
        <span>Showing <?= $rangeStart ?>–<?= $rangeEnd ?> of <?= $totalStudents ?> student<?= $totalStudents === 1 ? '' : 's' ?></span>

        <?php if ($totalPages > 1): ?>
            <div class="ac-pagination__pages">
                <?php
                    $pageUrl = static function (int $p) use ($qs, $basePath) {
                        return site_url($basePath . '/students') . '?' . http_build_query($qs + ['page' => $p]);
                    };
                ?>
                <a class="ac-pagination__link" href="<?= $pageUrl(max(1, $page - 1)) ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>" aria-label="Previous page">
                    <span class="material-symbols-outlined" style="font-size:16px;" aria-hidden="true">chevron_left</span>
                </a>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a class="ac-pagination__link <?= $p === $page ? 'is-active' : '' ?>" href="<?= $pageUrl($p) ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
                <?php endfor ?>
                <a class="ac-pagination__link" href="<?= $pageUrl(min($totalPages, $page + 1)) ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>" aria-label="Next page">
                    <span class="material-symbols-outlined" style="font-size:16px;" aria-hidden="true">chevron_right</span>
                </a>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>

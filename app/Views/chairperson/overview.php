<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'overview';
$pageTitle     = 'Dashboard';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/chairperson-dashboard.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/chairperson-dashboard.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php if ($deptName === null): ?>

    <div class="cd-panel">
        <div class="cd-empty">
            <span class="material-symbols-outlined" aria-hidden="true">domain_disabled</span>
            <p>You're not assigned to a department yet. Contact an administrator.</p>
        </div>
    </div>

<?php elseif ($activeYear === null): ?>

    <div class="cd-panel">
        <div class="cd-empty">
            <span class="material-symbols-outlined" aria-hidden="true">event_busy</span>
            <p>No active academic year is set. An administrator needs to activate one under Admin &gt; Academic Years.</p>
        </div>
    </div>

<?php else: ?>

    <?php
        // Group plotting gaps by program so each program renders as one block.
        $gapsByProgram = [];
        foreach ($plottingGaps as $gap) {
            $key = strtolower(trim($gap['program']));
            $gapsByProgram[$key]['items'][] = $gap;
        }

        // Registered sections per program, shown as a count in each program foot.
        $sectionsByProgram = [];
        foreach ($deptSections ?? [] as $sec) {
            $sectionsByProgram[strtolower(trim($sec['program']))][] = $sec;
        }
    ?>

    <header class="cd-hero">
        <div class="cd-hero__main">
            <p class="cd-hero__eyebrow">Chairperson Workspace</p>
            <h1 class="cd-hero__title">Welcome back, <?= esc($user['name']) ?>.</h1>
            <p class="cd-hero__sub">
                Department overview for <strong><?= esc($deptName) ?></strong> — live tracking of class plotting and faculty teaching loads.
            </p>
        </div>
        <div class="cd-hero__side">
            <p class="cd-term">
                <span class="cd-term__dot" aria-hidden="true"></span>
                <span class="cd-term__meta">Active Term</span>
                <span class="cd-term__sep" aria-hidden="true"></span>
                <strong class="cd-term__value"><?= esc($activeYear) ?></strong>
            </p>
            <div class="cd-hero__actions">
                <a class="cd-btn cd-btn--primary" href="<?= site_url('chairperson/dashboard') ?>">
                    <span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>
                    Schedule Plotting
                </a>
                <a class="cd-btn cd-btn--ghost" href="<?= site_url('chairperson/sections') ?>">
                    <span class="material-symbols-outlined" aria-hidden="true">groups</span>
                    Sections &amp; Roster
                </a>
            </div>
        </div>
    </header>

    <section class="cd-stats" aria-label="Department at a glance">

        <article class="cd-stat">
            <div class="cd-stat__top">
                <span class="cd-stat__icon"><span class="material-symbols-outlined" aria-hidden="true">calendar_today</span></span>
            </div>
            <p class="cd-stat__value"><?= esc($stats['plotted_classes']) ?></p>
            <p class="cd-stat__label">Classes plotted this term</p>
        </article>

        <article class="cd-stat">
            <div class="cd-stat__top">
                <span class="cd-stat__icon"><span class="material-symbols-outlined" aria-hidden="true">groups</span></span>
            </div>
            <p class="cd-stat__value"><?= esc($stats['faculty_count']) ?></p>
            <p class="cd-stat__label">Faculty in department</p>
        </article>

        <article class="cd-stat">
            <div class="cd-stat__top">
                <span class="cd-stat__icon<?= $stats['pending_approvals'] > 0 ? ' cd-stat__icon--warn' : '' ?>"><span class="material-symbols-outlined" aria-hidden="true">swap_horiz</span></span>
                <?php if ($stats['pending_approvals'] > 0): ?>
                    <span class="cd-stat__flag cd-stat__flag--warn">Action required</span>
                <?php else: ?>
                    <span class="cd-stat__flag cd-stat__flag--ok">All clear</span>
                <?php endif ?>
            </div>
            <p class="cd-stat__value"><?= esc($stats['pending_approvals']) ?></p>
            <p class="cd-stat__label">Swap approvals pending</p>
        </article>

        <article class="cd-stat">
            <div class="cd-stat__top">
                <span class="cd-stat__icon"><span class="material-symbols-outlined" aria-hidden="true">menu_book</span></span>
            </div>
            <p class="cd-stat__value"><?= esc($stats['curriculum_set']) ?><small> / <?= esc($stats['curriculum_slots']) ?></small></p>
            <p class="cd-stat__label">Curriculum terms published</p>
        </article>

        <article class="cd-stat">
            <div class="cd-stat__top">
                <span class="cd-stat__icon<?= $stats['needs_plotting_count'] > 0 ? ' cd-stat__icon--warn' : '' ?>"><span class="material-symbols-outlined" aria-hidden="true">hub</span></span>
                <?php if ($stats['needs_plotting_count'] > 0): ?>
                    <span class="cd-stat__flag cd-stat__flag--warn">Action required</span>
                <?php else: ?>
                    <span class="cd-stat__flag cd-stat__flag--ok">All clear</span>
                <?php endif ?>
            </div>
            <p class="cd-stat__value"><?= esc($stats['needs_plotting_count']) ?></p>
            <p class="cd-stat__label">Classes needing your subjects</p>
        </article>

    </section>

    <div class="cd-grid">

        <section class="cd-panel" aria-labelledby="cdProgramsTitle">

            <header class="cd-panel__head">
                <div>
                    <h2 class="cd-panel__title" id="cdProgramsTitle">Academic Programs &amp; Sections to Plot</h2>
                    <p class="cd-panel__sub"><?= esc($deptName) ?> &middot; <?= esc($activeYear) ?></p>
                </div>
            </header>

            <?php if ($programs === []): ?>

                <div class="cd-empty">
                    <span class="material-symbols-outlined" aria-hidden="true">folder_off</span>
                    <p>No programs are registered under this department yet.</p>
                </div>

            <?php else: ?>

                <?php foreach ($programs as $progIdx => $program): ?>
                    <?php
                        $progKey      = strtolower(trim($program));
                        $progGaps     = $gapsByProgram[$progKey]['items'] ?? [];
                        $progSections = $sectionsByProgram[$progKey] ?? [];
                        $modalId      = 'progModal_' . $progIdx;
                    ?>

                    <article class="cd-program">

                        <header class="cd-program__head">
                            <span class="cd-program__icon"><span class="material-symbols-outlined" aria-hidden="true">school</span></span>
                            <h3 class="cd-program__name"><?= esc($program) ?></h3>
                            <?php if ($progGaps !== []): ?>
                                <span class="cd-flag cd-flag--warn"><?= count($progGaps) ?> to plot</span>
                            <?php else: ?>
                                <span class="cd-flag cd-flag--ok">All clear</span>
                            <?php endif ?>
                            <button type="button" class="cd-inspect" data-modal-target="<?= esc($modalId, 'attr') ?>">
                                <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                                All classes
                            </button>
                        </header>

                        <?php if ($progGaps !== []): ?>

                            <?php foreach ($progGaps as $gap): ?>
                                <div class="cd-row">
                                    <div class="cd-row__main">
                                        <span class="cd-row__cohort"><?= esc($gap['year_level']) ?></span>
                                        <span class="cd-row__dot" aria-hidden="true">&middot;</span>
                                        <?php if ($gap['section'] === null): ?>
                                            <span class="cd-row__section cd-row__section--none">No section</span>
                                        <?php else: ?>
                                            <span class="cd-row__section">Section <?= esc($gap['section']) ?></span>
                                        <?php endif ?>
                                        <?php foreach ($gap['missing_codes'] as $code): ?>
                                            <span class="cd-code"><?= esc($code) ?></span>
                                        <?php endforeach ?>
                                    </div>
                                    <div class="cd-row__action">
                                        <?php if ($gap['section'] === null): ?>
                                            <form class="gap-ensure-form" method="post"
                                                  action="<?= esc($gap['ensureUrl'], 'attr') ?>"
                                                  data-no-dirty-tracking
                                                  data-plot-url-base="<?= esc($gap['plotUrlBase'], 'attr') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="academic_year" value="<?= esc($gap['academicYear'], 'attr') ?>">
                                                <input type="hidden" name="ay_label" value="<?= esc($gap['ayLabel'], 'attr') ?>">
                                                <input type="hidden" name="semester" value="<?= esc($gap['semester'], 'attr') ?>">
                                                <input type="hidden" name="program" value="<?= esc($gap['program'], 'attr') ?>">
                                                <input type="hidden" name="year_level" value="<?= esc($gap['year_level'], 'attr') ?>">
                                                <button type="submit" class="gap-ensure cd-btn cd-btn--mini cd-btn--solid">
                                                    <span>Create section</span>
                                                    <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <a class="cd-btn cd-btn--mini" href="<?= esc($gap['url']) ?>">
                                                <span>Plot</span>
                                                <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                                            </a>
                                        <?php endif ?>
                                    </div>
                                </div>
                            <?php endforeach ?>

                        <?php else: ?>

                            <div class="cd-allclear">
                                <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                                <span>All sections in this program are fully plotted for this term.</span>
                            </div>

                        <?php endif ?>

                        <footer class="cd-program__foot">
                            <div class="cd-links">
                                <a class="cd-link" href="<?= site_url('chairperson/dashboard') ?>?program=<?= urlencode($program) ?>">Plotter<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
                                <a class="cd-link" href="<?= site_url('chairperson/sections') ?>?program=<?= urlencode($program) ?>">Sections<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
                            </div>
                            <span class="cd-foot__meta"><?= count($progSections) ?> registered section<?= count($progSections) === 1 ? '' : 's' ?></span>
                        </footer>

                    </article>
                <?php endforeach ?>

            <?php endif ?>

        </section>

        <aside class="cd-rail">

            <section class="cd-panel">
                <h2 class="cd-rail__title">Quick Navigation</h2>
                <nav class="cd-nav" aria-label="Frequent actions">
                    <a class="cd-nav__item" href="<?= site_url('chairperson/dashboard') ?>">
                        <span class="cd-nav__icon"><span class="material-symbols-outlined" aria-hidden="true">calendar_month</span></span>
                        <span class="cd-nav__text">
                            <span class="cd-nav__label">Schedule Plotter</span>
                            <span class="cd-nav__sub">Weekly visual grid</span>
                        </span>
                        <span class="material-symbols-outlined cd-nav__arrow" aria-hidden="true">chevron_right</span>
                    </a>
                    <a class="cd-nav__item" href="<?= site_url('chairperson/sections') ?>">
                        <span class="cd-nav__icon"><span class="material-symbols-outlined" aria-hidden="true">groups</span></span>
                        <span class="cd-nav__text">
                            <span class="cd-nav__label">Sections &amp; Roster</span>
                            <span class="cd-nav__sub">Student cohort list</span>
                        </span>
                        <span class="material-symbols-outlined cd-nav__arrow" aria-hidden="true">chevron_right</span>
                    </a>
                    <a class="cd-nav__item" href="<?= site_url('chairperson/offerings') ?>">
                        <span class="cd-nav__icon"><span class="material-symbols-outlined" aria-hidden="true">merge_type</span></span>
                        <span class="cd-nav__text">
                            <span class="cd-nav__label">Merged Classes</span>
                            <span class="cd-nav__sub">Cross-section pool</span>
                        </span>
                        <span class="material-symbols-outlined cd-nav__arrow" aria-hidden="true">chevron_right</span>
                    </a>
                    <a class="cd-nav__item" href="<?= site_url('chairperson/curriculum') ?>">
                        <span class="cd-nav__icon"><span class="material-symbols-outlined" aria-hidden="true">menu_book</span></span>
                        <span class="cd-nav__text">
                            <span class="cd-nav__label">Curriculum</span>
                            <span class="cd-nav__sub">Subject catalog</span>
                        </span>
                        <span class="material-symbols-outlined cd-nav__arrow" aria-hidden="true">chevron_right</span>
                    </a>
                    <a class="cd-nav__item" href="<?= site_url('chairperson/grade-approvals') ?>">
                        <span class="cd-nav__icon"><span class="material-symbols-outlined" aria-hidden="true">grade</span></span>
                        <span class="cd-nav__text">
                            <span class="cd-nav__label">Grade Approvals</span>
                            <span class="cd-nav__sub">Lock &amp; review</span>
                        </span>
                        <span class="material-symbols-outlined cd-nav__arrow" aria-hidden="true">chevron_right</span>
                    </a>
                    <a class="cd-nav__item" href="<?= site_url('chairperson/profile') ?>">
                        <span class="cd-nav__icon"><span class="material-symbols-outlined" aria-hidden="true">person</span></span>
                        <span class="cd-nav__text">
                            <span class="cd-nav__label">Profile</span>
                            <span class="cd-nav__sub">Account settings</span>
                        </span>
                        <span class="material-symbols-outlined cd-nav__arrow" aria-hidden="true">chevron_right</span>
                    </a>
                </nav>
            </section>

        </aside>

    </div>

    <!-- ===== Modals for Each Program ===== -->
    <?php if ($programs !== []): ?>
        <?php foreach ($programs as $progIdx => $program): ?>
            <?php
                $progKey      = strtolower(trim($program));
                $progGaps     = $gapsByProgram[$progKey]['items'] ?? [];
                $progSections = $sectionsByProgram[$progKey] ?? [];
                $modalId      = 'progModal_' . $progIdx;
            ?>
            <div class="apple-modal-backdrop" id="<?= esc($modalId) ?>" role="dialog" aria-modal="true" aria-labelledby="<?= esc($modalId) ?>_title">
                <div class="apple-modal">
                    <div class="apple-modal__header">
                        <div class="apple-modal__title-box">
                            <div class="tool-icon-squircle">
                                <span class="material-symbols-outlined">school</span>
                            </div>
                            <div>
                                <h3 id="<?= esc($modalId) ?>_title"><?= esc($program) ?></h3>
                                <p><?= esc($deptName) ?> &middot; <?= esc($activeYear) ?></p>
                            </div>
                        </div>
                        <button type="button" class="apple-modal__close" data-modal-close aria-label="Close modal">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <div class="apple-modal__body">
                        <!-- Sections Needing Plotting in this Program -->
                        <div>
                            <div class="modal-section-title">Sections Needing Schedule Plotting</div>
                            <?php if ($progGaps !== []): ?>
                                <div class="program-gaps-container">
                                    <?php foreach ($progGaps as $gap): ?>
                                        <div class="prog-gap-item" style="padding:10px 14px;">
                                            <div class="prog-gap-item__left">
                                                <span class="prog-cohort-tag" style="font-size:13px;"><?= esc($gap['year_level']) ?></span>
                                                <span class="sep" style="color:var(--color-border-medium);">&middot;</span>
                                                <?php if ($gap['section'] === null): ?>
                                                    <span class="section-pill-sm section-pill-sm--empty">No section yet</span>
                                                <?php else: ?>
                                                    <span class="section-pill-sm">Section <?= esc($gap['section']) ?></span>
                                                <?php endif; ?>
                                                <span class="sep" style="color:var(--color-border-medium);">&middot;</span>
                                                <span style="font-size:11.5px;color:var(--color-text-secondary);"><?= (int)$gap['missing_count'] ?> subjects missing:</span>
                                                <?php foreach ($gap['missing_codes'] as $code): ?>
                                                    <span class="gap-code-chip-sm"><?= esc($code) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="prog-gap-item__right">
                                                <?php if ($gap['section'] === null): ?>
                                                    <a class="btn-plot-mini" href="<?= site_url('chairperson/sections') ?>">
                                                        <span>Create Section</span>
                                                        <span class="material-symbols-outlined">add</span>
                                                    </a>
                                                <?php else: ?>
                                                    <a class="btn-plot-mini" href="<?= esc($gap['url']) ?>">
                                                        <span>Plot Now</span>
                                                        <span class="material-symbols-outlined">arrow_forward</span>
                                                    </a>
                                                <?php endif ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="prog-all-clear" style="padding:10px 14px;">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    <span>All sections in <?= esc($program) ?> are completely plotted for <?= esc($activeYear) ?>.</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Registered Cohorts & Sections -->
                        <div>
                            <div class="modal-section-title">Registered Program Sections (Term Roster)</div>
                            <?php if ($progSections !== []): ?>
                                <table class="modal-sections-table">
                                    <thead>
                                        <tr>
                                            <th>Year Level</th>
                                            <th>Section Code</th>
                                            <th>Capacity</th>
                                            <th>Status</th>
                                            <th style="text-align:right;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($progSections as $sec): ?>
                                            <tr>
                                                <td><strong><?= esc($sec['year_level']) ?></strong></td>
                                                <td><span class="section-pill-sm">Section <?= esc($sec['section']) ?></span></td>
                                                <td><?= esc($sec['capacity'] ?? '30') ?> students</td>
                                                <td>
                                                    <span class="kpi-flag kpi-flag--ok"><?= esc(ucfirst($sec['status'] ?? 'active')) ?></span>
                                                </td>
                                                <td style="text-align:right;">
                                                    <a class="btn-plot-mini" href="<?= site_url('chairperson/dashboard') ?>?program=<?= urlencode($program) ?>&year_level=<?= urlencode($sec['year_level']) ?>&section=<?= urlencode($sec['section']) ?>">
                                                        <span>Plot Grid</span>
                                                        <span class="material-symbols-outlined">arrow_forward</span>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="font-size:12.5px;color:var(--color-text-tertiary);margin:0;">No sections currently registered under this program for this term.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="apple-modal__footer">
                        <button type="button" class="ac-btn ac-btn-secondary" data-modal-close>Close</button>
                        <a class="ac-btn ac-btn-primary" href="<?= site_url('chairperson/dashboard') ?>?program=<?= urlencode($program) ?>">
                            <span class="material-symbols-outlined" style="font-size:16px;">calendar_month</span>
                            <span>Open in Plotter</span>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(() => {
    // Modal dialog controller (Apple-style smooth dismiss and backdrop click)
    const openModal = (id) => {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    };

    const closeModal = (modal) => {
        if (!modal) return;
        modal.classList.remove('is-open');
        if (!document.querySelector('.apple-modal-backdrop.is-open')) {
            document.body.style.overflow = '';
        }
    };

    document.querySelectorAll('[data-modal-target]').forEach(trigger => {
        trigger.addEventListener('click', (evt) => {
            evt.preventDefault();
            const targetId = trigger.getAttribute('data-modal-target');
            openModal(targetId);
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.apple-modal-backdrop');
            closeModal(modal);
        });
    });

    document.querySelectorAll('.apple-modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', (evt) => {
            if (evt.target === backdrop) {
                closeModal(backdrop);
            }
        });
    });

    document.addEventListener('keydown', (evt) => {
        if (evt.key === 'Escape') {
            const openBackdrop = document.querySelector('.apple-modal-backdrop.is-open');
            if (openBackdrop) closeModal(openBackdrop);
        }
    });

    // Progressive enhancement for "Create section" fast-path
    document.querySelectorAll('.gap-ensure-form').forEach(form => {
        form.addEventListener('submit', async evt => {
            const btn = form.querySelector('.gap-ensure');
            evt.preventDefault();
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            const original = btn.innerHTML;
            btn.innerHTML = '<span>Creating…</span>';
            try {
                const payload = {};
                new FormData(form).forEach((value, key) => { payload[key] = value; });
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => null);
                if (!res.ok || !data?.ok) throw new Error(data?.error || 'Could not create the section.');
                window.location.href = form.dataset.plotUrlBase + '&section=' + encodeURIComponent(data.section);
            } catch (error) {
                btn.disabled = false;
                btn.innerHTML = original;
                alert(error.message);
            }
        });
    });
})();
</script>
<?= $this->endSection() ?>

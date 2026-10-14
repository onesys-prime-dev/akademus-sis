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

    <div class="ac-page-header">
        <div>
            <h2 class="ac-page-header__title">Welcome back, <?= esc($user['name']) ?></h2>
            <p class="ac-page-header__sub">Department overview for <strong><?= esc($deptName) ?></strong> &middot; Active term <strong><?= esc($activeYear) ?></strong></p>
        </div>
        <div class="cd-header-actions">
            <a class="ac-btn ac-btn-secondary" href="<?= site_url('chairperson/sections') ?>">
                <span class="material-symbols-outlined" aria-hidden="true">groups</span>
                Sections &amp; Roster
            </a>
            <a class="ac-btn ac-btn-primary" href="<?= site_url('chairperson/dashboard') ?>">
                <span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>
                Schedule Plotting
            </a>
        </div>
    </div>

    <div class="ac-bento" aria-label="Department at a glance">

        <div class="ac-bento-card">
            <div class="ac-bento-card__top">
                <div class="ac-bento-card__icon ac-bento-card__icon--primary"><span class="material-symbols-outlined icon-fill" aria-hidden="true">calendar_today</span></div>
            </div>
            <p class="ac-bento-card__label">Classes plotted this term</p>
            <h3 class="ac-bento-card__value"><?= esc($stats['plotted_classes']) ?></h3>
        </div>

        <div class="ac-bento-card">
            <div class="ac-bento-card__top">
                <div class="ac-bento-card__icon ac-bento-card__icon--tertiary"><span class="material-symbols-outlined icon-fill" aria-hidden="true">groups</span></div>
            </div>
            <p class="ac-bento-card__label">Faculty in department</p>
            <h3 class="ac-bento-card__value"><?= esc($stats['faculty_count']) ?></h3>
        </div>

        <div class="ac-bento-card">
            <div class="ac-bento-card__top">
                <div class="ac-bento-card__icon<?= $stats['pending_approvals'] > 0 ? ' ac-bento-card__icon--warning' : ' ac-bento-card__icon--primary' ?>"><span class="material-symbols-outlined icon-fill" aria-hidden="true">swap_horiz</span></div>
                <?php if ($stats['pending_approvals'] > 0): ?>
                    <span class="ac-pill ac-pill--warning">Action required</span>
                <?php else: ?>
                    <span class="ac-pill ac-pill--success">All clear</span>
                <?php endif ?>
            </div>
            <p class="ac-bento-card__label">Swap approvals pending</p>
            <h3 class="ac-bento-card__value"><?= esc($stats['pending_approvals']) ?></h3>
        </div>

        <div class="ac-bento-card">
            <div class="ac-bento-card__top">
                <div class="ac-bento-card__icon ac-bento-card__icon--primary"><span class="material-symbols-outlined icon-fill" aria-hidden="true">menu_book</span></div>
            </div>
            <p class="ac-bento-card__label">Curriculum terms published</p>
            <h3 class="ac-bento-card__value"><?= esc($stats['curriculum_set']) ?><small> / <?= esc($stats['curriculum_slots']) ?></small></h3>
        </div>

        <div class="ac-bento-card">
            <div class="ac-bento-card__top">
                <div class="ac-bento-card__icon<?= $stats['needs_plotting_count'] > 0 ? ' ac-bento-card__icon--warning' : ' ac-bento-card__icon--primary' ?>"><span class="material-symbols-outlined icon-fill" aria-hidden="true">hub</span></div>
                <?php if ($stats['needs_plotting_count'] > 0): ?>
                    <span class="ac-pill ac-pill--warning">Action required</span>
                <?php else: ?>
                    <span class="ac-pill ac-pill--success">All clear</span>
                <?php endif ?>
            </div>
            <p class="ac-bento-card__label">Classes needing your subjects</p>
            <h3 class="ac-bento-card__value"><?= esc($stats['needs_plotting_count']) ?></h3>
        </div>

    </div>

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
                            <div class="cd-program__identity">
                                <h3 class="cd-program__name"><?= esc($program) ?></h3>
                                <span class="cd-program__meta"><?= count($progSections) ?> registered section<?= count($progSections) === 1 ? '' : 's' ?></span>
                            </div>
                            <button type="button" class="cd-inspect" data-modal-target="<?= esc($modalId, 'attr') ?>">
                                View all
                                <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                            </button>
                        </header>

                        <?php if ($progGaps !== []): ?>

                            <?php foreach ($progGaps as $gap): ?>
                                <div class="cd-row">
                                    <div class="cd-row__main">
                                        <div class="cd-row__title">
                                            <span class="cd-row__cohort"><?= esc($gap['year_level']) ?></span>
                                            <span class="cd-row__dot" aria-hidden="true">&middot;</span>
                                            <?php if ($gap['section'] === null): ?>
                                                <span class="cd-row__section cd-row__section--none">No section created</span>
                                            <?php else: ?>
                                                <span class="cd-row__section">Section <?= esc($gap['section']) ?></span>
                                            <?php endif ?>
                                        </div>
                                        <div class="cd-row__missing">
                                            <span class="cd-row__missing-label">Missing</span>
                                            <?php foreach ($gap['missing_codes'] as $code): ?>
                                                <span class="cd-code"><?= esc($code) ?></span>
                                            <?php endforeach ?>
                                        </div>
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

                    </article>
                <?php endforeach ?>

            <?php endif ?>

        </section>

        <aside class="cd-rail">

            <?php
                $curriculumRemaining = max(0, (int) $stats['curriculum_slots'] - (int) $stats['curriculum_set']);
                $attentionCount = ($stats['pending_approvals'] > 0 ? 1 : 0)
                    + ($stats['needs_plotting_count'] > 0 ? 1 : 0)
                    + ($plottingGaps !== [] ? 1 : 0)
                    + ($curriculumRemaining > 0 ? 1 : 0);
            ?>
            <section class="cd-panel cd-attention" aria-labelledby="cdAttentionTitle">
                <header class="cd-attention__head">
                    <div>
                        <p class="cd-attention__eyebrow">Today</p>
                        <h2 class="cd-attention__title" id="cdAttentionTitle">Attention Needed</h2>
                    </div>
                    <span class="cd-attention__count<?= $attentionCount === 0 ? ' cd-attention__count--clear' : '' ?>">
                        <?= $attentionCount === 0 ? 'All clear' : $attentionCount . ' item' . ($attentionCount === 1 ? '' : 's') ?>
                    </span>
                </header>

                <?php if ($attentionCount === 0): ?>
                    <div class="cd-attention__empty">
                        <span class="material-symbols-outlined" aria-hidden="true">verified</span>
                        <h3>You&rsquo;re all caught up</h3>
                        <p>No schedule, approval, or curriculum issues need your attention right now.</p>
                    </div>
                <?php else: ?>
                    <div class="cd-attention__list">
                        <?php if ($plottingGaps !== []): ?>
                            <a class="cd-task cd-task--warning" href="#cdProgramsTitle">
                                <span class="cd-task__icon"><span class="material-symbols-outlined" aria-hidden="true">event_busy</span></span>
                                <span class="cd-task__body">
                                    <strong><?= count($plottingGaps) ?> section<?= count($plottingGaps) === 1 ? '' : 's' ?> need plotting</strong>
                                    <small>Complete missing subject schedules below</small>
                                </span>
                                <span class="material-symbols-outlined cd-task__arrow" aria-hidden="true">arrow_downward</span>
                            </a>
                        <?php endif ?>

                        <?php if ($stats['pending_approvals'] > 0): ?>
                            <a class="cd-task cd-task--warning" href="<?= site_url('chairperson/dashboard') ?>">
                                <span class="cd-task__icon"><span class="material-symbols-outlined" aria-hidden="true">swap_horiz</span></span>
                                <span class="cd-task__body">
                                    <strong><?= (int) $stats['pending_approvals'] ?> swap approval<?= $stats['pending_approvals'] == 1 ? '' : 's' ?> pending</strong>
                                    <small>Review faculty schedule requests</small>
                                </span>
                                <span class="material-symbols-outlined cd-task__arrow" aria-hidden="true">chevron_right</span>
                            </a>
                        <?php endif ?>

                        <?php if ($stats['needs_plotting_count'] > 0): ?>
                            <a class="cd-task" href="<?= site_url('chairperson/shared-subjects') ?>">
                                <span class="cd-task__icon"><span class="material-symbols-outlined" aria-hidden="true">hub</span></span>
                                <span class="cd-task__body">
                                    <strong><?= (int) $stats['needs_plotting_count'] ?> shared class<?= $stats['needs_plotting_count'] == 1 ? '' : 'es' ?> waiting</strong>
                                    <small>Assign subjects requested by other departments</small>
                                </span>
                                <span class="material-symbols-outlined cd-task__arrow" aria-hidden="true">chevron_right</span>
                            </a>
                        <?php endif ?>

                        <?php if ($curriculumRemaining > 0): ?>
                            <a class="cd-task" href="<?= site_url('chairperson/curriculum') ?>">
                                <span class="cd-task__icon"><span class="material-symbols-outlined" aria-hidden="true">menu_book</span></span>
                                <span class="cd-task__body">
                                    <strong><?= $curriculumRemaining ?> curriculum term<?= $curriculumRemaining === 1 ? '' : 's' ?> unpublished</strong>
                                    <small>Finish the department curriculum setup</small>
                                </span>
                                <span class="material-symbols-outlined cd-task__arrow" aria-hidden="true">chevron_right</span>
                            </a>
                        <?php endif ?>
                    </div>
                <?php endif ?>
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
                $modalMissing = array_sum(array_map(static fn (array $gap): int => (int) $gap['missing_count'], $progGaps));
                $modalRows    = [];

                foreach ($progSections as $sec) {
                    $matchingGap = null;
                    foreach ($progGaps as $gap) {
                        if ($gap['section'] !== null
                            && strcasecmp((string) $gap['year_level'], (string) $sec['year_level']) === 0
                            && strcasecmp((string) $gap['section'], (string) $sec['section']) === 0) {
                            $matchingGap = $gap;
                            break;
                        }
                    }
                    $modalRows[] = ['section' => $sec, 'gap' => $matchingGap];
                }

                foreach ($progGaps as $gap) {
                    if ($gap['section'] === null) {
                        $modalRows[] = ['section' => null, 'gap' => $gap];
                    }
                }
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
                                <p><?= count($progSections) ?> section<?= count($progSections) === 1 ? '' : 's' ?> &middot; <?= $modalMissing ?> subject<?= $modalMissing === 1 ? '' : 's' ?> remaining &middot; <?= esc($activeYear) ?></p>
                            </div>
                        </div>
                        <button type="button" class="apple-modal__close" data-modal-close aria-label="Close modal">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <div class="apple-modal__body">
                        <div class="modal-section-title">Program Sections</div>
                        <?php if ($modalRows !== []): ?>
                            <div class="modal-program-list">
                                <?php foreach ($modalRows as $row): ?>
                                    <?php
                                        $sec = $row['section'];
                                        $gap = $row['gap'];
                                        $hasSection = $sec !== null;
                                        $plotUrl = $gap['url'] ?? ($hasSection
                                            ? site_url('chairperson/dashboard') . '?program=' . urlencode($program) . '&year_level=' . urlencode($sec['year_level']) . '&section=' . urlencode($sec['section'])
                                            : site_url('chairperson/sections'));
                                    ?>
                                    <article class="modal-program-row">
                                        <div class="modal-program-row__content">
                                            <div class="modal-program-row__title">
                                                <strong><?= esc($hasSection ? $sec['year_level'] : $gap['year_level']) ?></strong>
                                                <span aria-hidden="true">&middot;</span>
                                                <span class="section-pill-sm<?= $hasSection ? '' : ' section-pill-sm--empty' ?>">
                                                    <?= $hasSection ? 'Section ' . esc($sec['section']) : 'No section created' ?>
                                                </span>
                                            </div>
                                            <p class="modal-program-row__meta">
                                                <?= $hasSection ? esc($sec['capacity'] ?? '30') . ' students' : 'Section setup required' ?>
                                            </p>
                                            <?php if ($gap !== null): ?>
                                                <div class="modal-program-row__missing">
                                                    <span>Missing</span>
                                                    <?php foreach ($gap['missing_codes'] as $code): ?>
                                                        <span class="gap-code-chip-sm"><?= esc($code) ?></span>
                                                    <?php endforeach ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="modal-program-row__complete">
                                                    <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                                                    Schedule complete
                                                </div>
                                            <?php endif ?>
                                        </div>
                                        <a class="btn-plot-mini" href="<?= esc($plotUrl, 'attr') ?>">
                                            <span><?= $hasSection ? 'Plot' : 'Create section' ?></span>
                                            <span class="material-symbols-outlined" aria-hidden="true"><?= $hasSection ? 'arrow_forward' : 'add' ?></span>
                                        </a>
                                    </article>
                                <?php endforeach ?>
                            </div>
                        <?php else: ?>
                            <div class="modal-program-empty">
                                <span class="material-symbols-outlined" aria-hidden="true">group_off</span>
                                <p>No sections are registered under this program for the active term.</p>
                            </div>
                        <?php endif ?>
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

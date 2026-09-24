<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'offerings';
$pageTitle     = 'Merged Classes';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = [
    '' => [
        'overview'        => ['label' => 'Dashboard',        'icon' => 'home',           'url' => 'chairperson/overview'],
        'profile'         => ['label' => 'Profile',           'icon' => 'person',          'url' => 'chairperson/profile'],
        'schedule'        => ['label' => 'Schedule Plotting', 'icon' => 'calendar_month',  'url' => 'chairperson/dashboard'],
        'sections'        => ['label' => 'Sections',          'icon' => 'groups',          'url' => 'chairperson/sections'],
        'offerings'       => ['label' => 'Merged Classes',    'icon' => 'merge_type',      'url' => 'chairperson/offerings'],
        'grade-approvals' => ['label' => 'Grade Approvals',   'icon' => 'grade',           'url' => 'chairperson/grade-approvals'],
        'curriculum'      => ['label' => 'Curriculum',        'icon' => 'menu_book',       'url' => 'chairperson/curriculum'],
    ],
];
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    .mc-card { padding: 0; }

    .mc-card__head { padding: 20px 24px; border-bottom: 1px solid var(--color-border); }
    .mc-card__title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .mc-card__title-row h2 { margin: 0; font-size: 16px; font-weight: 800; color: var(--color-text-primary); }
    .mc-card__head p { margin: 6px 0 0; font-size: 12.5px; color: var(--color-text-secondary); max-width: 640px; line-height: 1.5; }

    .mc-count { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); background: var(--color-primary-soft); color: var(--color-primary); }
    .mc-count--success { background: var(--color-success-soft); color: #166534; }

    .mc-row { padding: 20px 24px; border-bottom: 1px solid var(--color-border); display: flex; flex-wrap: wrap; gap: 20px; align-items: center; justify-content: space-between; transition: background-color var(--transition-fast); }
    .mc-row:last-child { border-bottom: none; }
    .mc-row:hover { background: var(--color-surface-secondary); }
    .mc-row__main { flex: 1; min-width: 280px; }

    .mc-code { font-size: 15px; font-weight: 800; color: var(--color-text-primary); letter-spacing: -.01em; }
    .mc-meta { font-size: 11.5px; color: var(--color-text-tertiary); font-weight: 600; margin: 3px 0 10px; }

    .mc-sections { display: flex; flex-wrap: wrap; gap: 8px; }
    .mc-sec-check { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); cursor: pointer; font-size: 12.5px; font-weight: 600; color: var(--color-text-primary); transition: border-color var(--transition-fast), background-color var(--transition-fast); }
    .mc-sec-check:hover { border-color: var(--color-primary); background: var(--color-primary-soft); }
    .mc-sec-check input { accent-color: var(--color-primary); width: 15px; height: 15px; flex: none; }

    .mc-cap-pill { font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: var(--radius-pill); background: var(--color-surface-sunken); color: var(--color-text-secondary); white-space: nowrap; }

    .mc-row__actions { display: flex; align-items: center; gap: 10px; }
    .mc-row__actions input[type="number"] { width: 130px; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 13px; font-family: inherit; color: var(--color-text-primary); }
    .mc-row__actions input[type="number"]:focus { outline: none; border-color: var(--color-primary); box-shadow: var(--focus-ring); }

    .mc-merged { padding: 20px 24px; }
    .mc-merged-card { border: 1px solid var(--color-primary-soft); background: linear-gradient(90deg, var(--color-primary-soft) 0%, var(--color-surface) 40%); border-radius: var(--radius-lg); padding: 18px 20px; margin-bottom: 16px; }
    .mc-merged-card:last-child { margin-bottom: 0; }
    .mc-merged-card__top { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 12px; }
    .mc-merged-card__title { font-size: 14.5px; font-weight: 800; color: var(--color-text-primary); }

    .mc-active-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: var(--radius-pill); background: var(--color-success-soft); color: #166534; }
    .mc-active-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--color-success); }

    .mc-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
    .mc-pill { display: inline-flex; align-items: center; gap: 4px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 5px 6px 5px 12px; font-size: 12px; font-weight: 600; color: var(--color-text-primary); }
    .mc-pill button { border: none; background: none; padding: 4px; border-radius: 50%; display: flex; cursor: pointer; color: var(--color-text-tertiary); }
    .mc-pill button:hover { background: var(--color-danger-soft); color: var(--color-danger); }

    .mc-progress-wrap { max-width: 360px; }
    .mc-progress-head { display: flex; justify-content: space-between; gap: 8px; font-size: 11.5px; font-weight: 700; margin-bottom: 5px; }
    .mc-progress-head span:first-child { color: var(--color-text-secondary); }
    .mc-progress-head span:last-child { color: var(--color-primary); }
    .mc-progress-head.is-over span:last-child { color: var(--color-danger); }
    .mc-track { height: 6px; border-radius: 999px; background: var(--color-surface-sunken); overflow: hidden; }
    .mc-fill { height: 100%; background: var(--color-primary); border-radius: 999px; }
    .mc-fill.is-over { background: var(--color-danger); }

    @media (max-width: 640px) {
        .mc-row { flex-direction: column; align-items: stretch; }
        .mc-row__actions { justify-content: space-between; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Merged Classes</h2>
        <p class="ac-page-header__sub">Combine the same subject across sections into one shared roster and capacity.</p>
    </div>
</div>

<?php if ($deptName === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>You're not assigned to a department yet. Contact an administrator.</p>
        </div>
    </div>
<?php else: ?>

    <div id="feedback" class="flash" style="display:none;"></div>

    <div class="panel mc-card">
        <div class="mc-card__head">
            <div class="mc-card__title-row">
                <h2>Mergeable Subjects</h2>
                <span class="mc-count"><?= count($candidates) ?> Available</span>
            </div>
            <p>A subject plotted for two or more of your sections in the same term. Merging combines their rosters into one class with one shared capacity — neither section's other subjects are affected.</p>
        </div>

        <?php if (empty($candidates)): ?>
            <div class="empty-state">
                <div class="material-symbols-outlined">merge_type</div>
                <p>No subject is currently plotted for two or more of your sections in the same term.</p>
            </div>
        <?php else: ?>
            <?php foreach ($candidates as $i => $c): ?>
                <article class="mc-row">
                    <div class="mc-row__main">
                        <span class="mc-code"><?= esc($c['subject_code']) ?></span>
                        <div class="mc-meta"><?= esc($c['academic_year']) ?></div>
                        <div class="mc-sections">
                            <?php foreach ($c['sections'] as $s): ?>
                                <label class="mc-sec-check">
                                    <input type="checkbox" name="cand-<?= $i ?>" value="<?= esc($s['section_id']) ?>">
                                    <span><?= esc($s['program']) ?> &middot; <?= esc($s['year_level']) ?> &middot; Section <?= esc($s['section']) ?></span>
                                    <span class="mc-cap-pill"><?= $s['capacity'] !== null ? 'Cap ' . esc($s['capacity']) : 'Unlimited' ?></span>
                                </label>
                            <?php endforeach ?>
                        </div>
                    </div>
                    <div class="mc-row__actions">
                        <input type="number" min="1" placeholder="Cap (optional)" id="cap-<?= $i ?>">
                        <button type="button" class="btn btn-primary" onclick="mergeCandidate(<?= $i ?>, '<?= esc($c['academic_year'], 'js') ?>', '<?= esc($c['subject_code'], 'js') ?>')">
                            <span class="material-symbols-outlined" style="font-size:18px;">merge_type</span>
                            Merge Selected
                        </button>
                    </div>
                </article>
            <?php endforeach ?>
        <?php endif ?>
    </div>

    <div class="panel mc-card">
        <div class="mc-card__head">
            <div class="mc-card__title-row">
                <h2>Merged Classes</h2>
                <span class="mc-count mc-count--success"><?= count($offerings) ?> Active</span>
            </div>
            <p>Each card is one combined class instance. Removing a section returns its students to being counted against that section's own capacity — no one is dropped from the roster.</p>
        </div>

        <?php if (empty($offerings)): ?>
            <div class="empty-state">
                <div class="material-symbols-outlined">inbox</div>
                <p>No merged classes yet.</p>
            </div>
        <?php else: ?>
            <div class="mc-merged">
                <?php foreach ($offerings as $o): ?>
                    <?php
                        $overCapacity = $o['capacity'] !== null && $o['roster_count'] > $o['capacity'];
                        $pct = ($o['capacity'] !== null && $o['capacity'] > 0) ? round($o['roster_count'] / $o['capacity'] * 100) : null;
                    ?>
                    <div class="mc-merged-card">
                        <div class="mc-merged-card__top">
                            <span class="mc-merged-card__title"><?= esc($o['subject_code']) ?></span>
                            <span class="mc-active-badge"><span class="mc-active-dot"></span> Active Merged</span>
                            <span class="mc-cap-pill"><?= esc($o['academic_year']) ?></span>
                        </div>
                        <div class="mc-pills">
                            <?php foreach ($o['sections'] as $s): ?>
                                <span class="mc-pill">
                                    <?= esc($s['program']) ?> &middot; <?= esc($s['year_level']) ?> &middot; Sec. <?= esc($s['section']) ?>
                                    <button type="button" title="Remove from merge" onclick="unmergeSection(<?= esc($o['offering_id']) ?>, <?= esc($s['section_id']) ?>, '<?= esc($s['section'], 'js') ?>')">
                                        <span class="material-symbols-outlined" style="font-size:15px;">close</span>
                                    </button>
                                </span>
                            <?php endforeach ?>
                        </div>
                        <?php if ($pct !== null): ?>
                            <div class="mc-progress-wrap">
                                <div class="mc-progress-head <?= $overCapacity ? 'is-over' : '' ?>">
                                    <span>Roster</span>
                                    <span><?= esc($o['roster_count']) ?> / <?= esc($o['capacity']) ?> (<?= $pct ?>%)<?= $overCapacity ? ' · over capacity' : '' ?></span>
                                </div>
                                <div class="mc-track"><div class="mc-fill <?= $overCapacity ? 'is-over' : '' ?>" style="width:<?= min(100, $pct) ?>%;"></div></div>
                            </div>
                        <?php else: ?>
                            <div class="mc-meta" style="margin-bottom:0;">Roster <?= esc($o['roster_count']) ?> (unlimited capacity)</div>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>

<?php endif ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function showFeedback(message, isError) {
        const el = document.getElementById('feedback');
        el.style.display = 'block';
        el.className = 'flash ' + (isError ? 'error' : 'success');
        el.textContent = message;
    }

    async function postJSON(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        return res.json();
    }

    function reloadWithMessage(message) {
        sessionStorage.setItem('offeringsFeedback', message);
        location.reload();
    }

    async function mergeCandidate(i, academicYear, subjectCode) {
        const checked = Array.from(document.querySelectorAll(`input[name="cand-${i}"]:checked`)).map(el => parseInt(el.value, 10));

        if (checked.length < 2) {
            showFeedback('Select at least two sections to merge.', true);
            return;
        }

        const capEl = document.getElementById('cap-' + i);

        const result = await postJSON('<?= site_url('chairperson/offerings/create') ?>', {
            academic_year: academicYear,
            subject_code: subjectCode,
            section_ids: checked,
            capacity: capEl.value,
        });

        if (!result.ok) {
            showFeedback(result.error || 'Could not create the merge.', true);
            return;
        }

        let msg = `${subjectCode} merged across ${checked.length} sections (roster ${result.roster_count}).`;
        if (result.over_capacity) {
            msg += ' Warning: the combined roster already exceeds the capacity you set.';
        }
        reloadWithMessage(msg);
    }

    async function unmergeSection(offeringId, sectionId, label) {
        if (!await acConfirm(`Remove Section ${label} from this merged class?`)) {
            return;
        }

        const result = await postJSON('<?= site_url('chairperson/offerings/unmerge') ?>', {
            offering_id: offeringId,
            section_id: sectionId,
        });

        if (!result.ok) {
            showFeedback(result.error || 'Could not unmerge.', true);
            return;
        }

        reloadWithMessage(`Section ${label} removed from the merge.`);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const msg = sessionStorage.getItem('offeringsFeedback');
        if (msg) {
            showFeedback(msg, false);
            sessionStorage.removeItem('offeringsFeedback');
        }
    });
</script>
<?= $this->endSection() ?>

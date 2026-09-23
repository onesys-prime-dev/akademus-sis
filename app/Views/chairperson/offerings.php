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
    .offer-card { margin-bottom: 14px; }
    .offer-card__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .offer-card__title { font-size: 14.5px; font-weight: 700; color: var(--color-text-primary); }
    .offer-card__meta { font-size: 12px; color: var(--color-text-secondary); }
    .cand-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--color-border); }
    .cand-row:last-child { border-bottom: none; }
    .cand-sections label { display: block; font-size: 12.5px; margin: 3px 0; }
    .sec-pill { display: inline-flex; align-items: center; gap: 6px; background: var(--color-surface-sunken); border: 1px solid var(--color-border); border-radius: var(--radius-pill); padding: 4px 10px; font-size: 12px; margin: 2px 4px 2px 0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Merged Classes</h2></div>
</div>

<?php if ($deptName === null): ?>
    <div class="panel">
        <div class="empty-state">
            <div class="material-symbols-outlined">domain_disabled</div>
            <p>You're not assigned to a department yet. Contact an administrator.</p>
        </div>
    </div>
<?php else: ?>

    <div class="panel" id="feedback" style="display:none;"></div>

    <div class="panel">
        <h2>Mergeable Subjects</h2>
        <p class="panel-sub">A subject plotted for two or more of your sections in the same term. Merging combines their rosters into one class with one shared capacity — neither section's other subjects are affected.</p>

        <?php if (empty($candidates)): ?>
            <div class="empty-state">
                <div class="material-symbols-outlined">merge_type</div>
                <p>No subject is currently plotted for two or more of your sections in the same term.</p>
            </div>
        <?php endif ?>

        <?php foreach ($candidates as $i => $c): ?>
            <div class="cand-row">
                <div>
                    <strong><?= esc($c['subject_code']) ?></strong>
                    <div class="offer-card__meta"><?= esc($c['academic_year']) ?></div>
                    <div class="cand-sections">
                        <?php foreach ($c['sections'] as $s): ?>
                            <label>
                                <input type="checkbox" name="cand-<?= $i ?>" value="<?= esc($s['section_id']) ?>">
                                <?= esc($s['program']) ?> &middot; <?= esc($s['year_level']) ?> &middot; Section <?= esc($s['section']) ?>
                            </label>
                        <?php endforeach ?>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <input type="number" min="1" placeholder="Cap (optional)" id="cap-<?= $i ?>" style="width:110px; padding:8px 10px; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
                    <button type="button" class="btn btn-primary" onclick="mergeCandidate(<?= $i ?>, '<?= esc($c['academic_year'], 'js') ?>', '<?= esc($c['subject_code'], 'js') ?>')">
                        <span class="material-symbols-outlined" style="font-size:18px;">merge_type</span>
                        Merge Selected
                    </button>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <div class="panel">
        <h2>Merged Classes</h2>
        <p class="panel-sub">Each card is one combined class instance. Removing a section returns its students to being counted against that section's own capacity — no one is dropped from the roster.</p>

        <?php if (empty($offerings)): ?>
            <div class="empty-state">
                <div class="material-symbols-outlined">inbox</div>
                <p>No merged classes yet.</p>
            </div>
        <?php endif ?>

        <?php foreach ($offerings as $o): ?>
            <div class="offer-card">
                <div class="offer-card__head">
                    <div>
                        <div class="offer-card__title"><?= esc($o['subject_code']) ?></div>
                        <div class="offer-card__meta">
                            <?= esc($o['academic_year']) ?> &middot;
                            Roster <?= esc($o['roster_count']) ?><?= $o['capacity'] !== null ? ' / ' . esc($o['capacity']) : ' (unlimited)' ?>
                            <?php if ($o['capacity'] !== null && $o['roster_count'] > $o['capacity']): ?>
                                <span style="color:var(--color-danger); font-weight:700;"> &middot; over capacity</span>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
                <div>
                    <?php foreach ($o['sections'] as $s): ?>
                        <span class="sec-pill">
                            <?= esc($s['program']) ?> &middot; <?= esc($s['year_level']) ?> &middot; Sec. <?= esc($s['section']) ?>
                            <button type="button" class="icon-btn" title="Remove from merge" onclick="unmergeSection(<?= esc($o['offering_id']) ?>, <?= esc($s['section_id']) ?>, '<?= esc($s['section'], 'js') ?>')">
                                <span class="material-symbols-outlined" style="font-size:16px;">close</span>
                            </button>
                        </span>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>

<?php endif ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function showFeedback(message, isError) {
        const el = document.getElementById('feedback');
        el.style.display = 'block';
        el.style.color = isError ? '#a11414' : '#056945';
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
        if (!confirm(`Remove Section ${label} from this merged class?`)) {
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

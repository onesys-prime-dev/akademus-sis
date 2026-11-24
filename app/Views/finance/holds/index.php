<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/holds.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/holds.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="hld-page">
    <div class="ac-page-header">
        <div><h2 class="ac-page-header__title">Financial Holds</h2><p class="ac-page-header__sub">Blocks enrollment/drop and document release until released.</p></div>
    </div>

    <div class="ac-stat-grid" style="margin-bottom:24px;">
        <div class="ac-metric-card cs-stagger-in" style="animation-delay:0ms;">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px; color:<?= $holdCount > 0 ? 'var(--color-warning)' : 'var(--color-text-tertiary)' ?>;" aria-hidden="true">block</span><span>Active Holds</span></div>
            <div class="ac-metric-card__value" style="color:<?= $holdCount > 0 ? 'var(--color-warning)' : 'inherit' ?>;"><?= $holdCount ?></div>
        </div>
        <div class="ac-metric-card cs-stagger-in" style="animation-delay:35ms;">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">group</span><span>Students Affected</span></div>
            <div class="ac-metric-card__value"><?= $uniqueStudents ?></div>
        </div>
        <div class="ac-metric-card cs-stagger-in" style="animation-delay:70ms;">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px; color:<?= $oldestDays !== null && $oldestDays >= 14 ? 'var(--color-danger)' : 'var(--color-text-tertiary)' ?>;" aria-hidden="true">schedule</span><span>Oldest Hold</span></div>
            <div class="ac-metric-card__value" style="color:<?= $oldestDays !== null && $oldestDays >= 14 ? 'var(--color-danger)' : 'inherit' ?>;"><?= $oldestDays !== null ? $oldestDays . 'd' : '—' ?></div>
        </div>
    </div>

    <div class="hld-search-card">
        <div class="hld-search-card__label">Find a student</div>
        <p class="hld-search-card__sub">Search by username or student number to place or review a hold.</p>
        <div class="hld-search">
            <span class="material-symbols-outlined hld-search__icon" aria-hidden="true">search</span>
            <input type="text" id="finSearchInput" placeholder="Username or student number…" autocomplete="off">
            <div class="hld-results" id="finResults" hidden></div>
        </div>
    </div>

    <h3 style="margin-bottom:14px;">Active Holds</h3>
    <?php if ($holds === []): ?>
        <div class="ac-empty-block">No active holds.</div>
    <?php else: ?>
        <div class="ac-table-card">
            <table class="ac-table">
                <thead><tr><th>Student</th><th>Student #</th><th>Type</th><th>Reason</th><th>Placed</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($holds as $h): ?>
                        <?php $hPhotoUrl = avatar_photo_url((int) $h['user_id'], $h['photo_mime'] ?? null); ?>
                        <tr>
                            <td>
                                <div class="hld-student">
                                    <span class="hld-student__avatar" style="<?= $hPhotoUrl === null ? 'background:' . esc(avatar_color($h['username']), 'attr') . ';color:#fff;' : '' ?>">
                                        <?php if ($hPhotoUrl !== null): ?>
                                            <img src="<?= esc($hPhotoUrl) ?>" alt="">
                                        <?php else: ?>
                                            <?= esc(strtoupper(substr($h['username'], 0, 2))) ?>
                                        <?php endif ?>
                                    </span>
                                    <?= esc($h['username']) ?>
                                </div>
                            </td>
                            <td><?= esc($h['student_number']) ?></td>
                            <td><span class="hld-type-pill hld-type-pill--<?= esc($h['hold_type']) ?>"><?= esc(ucfirst(str_replace('_', ' ', $h['hold_type']))) ?></span></td>
                            <td><?= esc($h['reason']) ?></td>
                            <td><?= esc(date('M j, Y', strtotime($h['placed_at']))) ?></td>
                            <td><a href="<?= site_url('finance/holds/student/' . $h['student_id']) ?>" class="ac-btn-outline">Review</a></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/avatar-helper.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/avatar-helper.js') ?>"></script>
<script>
    const searchInput = document.getElementById('finSearchInput');
    const resultsBox = document.getElementById('finResults');
    const avatarBaseUrl = <?= json_encode(site_url('avatar')) ?>;
    let searchTimer = null;

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const query = searchInput.value.trim();
        if (query.length < 2) { resultsBox.hidden = true; resultsBox.replaceChildren(); return; }
        searchTimer = setTimeout(async () => {
            const res = await fetch('<?= site_url('finance/holds/search-students') ?>?q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => null);
            resultsBox.replaceChildren();
            if (!data?.ok || !data.results.length) {
                const empty = document.createElement('div');
                empty.className = 'hld-results__empty';
                empty.textContent = 'No matching students found.';
                resultsBox.append(empty);
                resultsBox.hidden = false;
                return;
            }
            data.results.forEach(row => {
                const item = document.createElement('a');
                item.className = 'hld-result';
                item.href = '<?= site_url('finance/holds/student') ?>/' + row.student_id;

                const avatar = document.createElement('span');
                avatar.className = 'hld-result__avatar';
                const photoUrl = AvatarHelper.photoUrl(avatarBaseUrl, row.user_id, row.photo_mime);
                if (photoUrl) {
                    const img = document.createElement('img');
                    img.src = photoUrl;
                    img.alt = '';
                    avatar.append(img);
                } else {
                    avatar.style.background = AvatarHelper.color(row.username);
                    avatar.style.color = '#fff';
                    avatar.textContent = AvatarHelper.initials(row.username);
                }

                const name = document.createElement('span');
                name.className = 'hld-result__name';
                name.textContent = row.username;

                const meta = document.createElement('span');
                meta.className = 'hld-result__meta';
                meta.textContent = row.student_number + (row.program_name ? ' · ' + row.program_name : '');

                const text = document.createElement('span');
                text.append(name, meta);

                item.append(avatar, text);
                resultsBox.append(item);
            });
            resultsBox.hidden = false;
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.hld-search')) resultsBox.hidden = true;
    });
</script>
<?= $this->endSection() ?>

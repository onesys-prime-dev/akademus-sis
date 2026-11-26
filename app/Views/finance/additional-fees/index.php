<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/finance-additional-fees.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/finance-additional-fees.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Additional Fees</h2><p class="ac-page-header__sub">One-off charges outside the regular tuition/miscellaneous structure — campus events, penalties, surcharges.</p></div>
    <a href="<?= site_url('finance/additional-fees/bulk') ?>" class="ac-btn ac-btn-primary">
        <span class="material-symbols-outlined" style="font-size:18px;">groups</span>
        <span>Bulk Apply</span>
    </a>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<?php if (session()->has('errors')): ?>
    <div class="ac-alert" style="background:var(--color-danger-soft); border-color:var(--color-danger-border); margin-bottom:16px;">
        <span class="material-symbols-outlined" style="color:var(--color-danger);" aria-hidden="true">error</span>
        <div class="ac-alert__text" style="color:var(--color-danger);">
            <?php foreach ((array) session('errors') as $error): ?>
                <p><?= esc($error) ?></p>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<div class="af-section">

    <div class="ac-stat-grid" style="margin-bottom:20px;">
        <div class="ac-metric-card">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">toggle_on</span><span>Active Fees</span></div>
            <div class="ac-metric-card__value"><?= $activeCount ?></div>
            <div class="ac-metric-card__top" style="margin-top:6px;">₱<?= number_format($activeTotal, 2) ?> total</div>
        </div>
        <div class="ac-metric-card">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">today</span><span>Granted Today</span></div>
            <div class="ac-metric-card__value"><?= $todayCount ?></div>
            <div class="ac-metric-card__top" style="margin-top:6px;">₱<?= number_format($todayTotal, 2) ?> total</div>
        </div>
    </div>

    <div class="af-card">
        <h3 class="af-card__title">Charge a Student</h3>
        <p class="af-card__hint">Search by student username or student number, then add a fee from their account page.</p>
        <div class="af-search">
            <span class="material-symbols-outlined af-search__icon" aria-hidden="true">search</span>
            <input type="text" id="finSearchInput" placeholder="e.g. jdoe or 2026-00001" autocomplete="off">
            <div class="af-results" id="finResults" hidden></div>
        </div>
    </div>

    <div class="af-card">
        <h3 class="af-card__title">Recently Charged</h3>
        <p class="af-card__hint">The last fees applied, across all students.</p>
        <?php if ($recentFees === []): ?>
            <p class="af-recent-empty">No fees charged yet.</p>
        <?php else: ?>
            <ul class="af-recent-list">
                <?php foreach ($recentFees as $f): ?>
                    <?php $photoUrl = avatar_photo_url((int) $f['user_id'], $f['photo_mime'] ?? null); ?>
                    <li>
                        <span class="af-recent-list__avatar" style="<?= $photoUrl === null ? 'background:' . esc(avatar_color($f['username']), 'attr') . ';color:#fff;' : '' ?>">
                            <?php if ($photoUrl !== null): ?>
                                <img src="<?= esc($photoUrl) ?>" alt="">
                            <?php else: ?>
                                <?= esc(strtoupper(substr($f['username'], 0, 2))) ?>
                            <?php endif ?>
                        </span>
                        <span class="af-recent-list__info">
                            <span class="af-recent-list__name"><?= esc($f['username']) ?> <span style="font-weight:450; color:var(--af-muted);">· <?= esc($f['student_number']) ?></span></span>
                            <span class="af-recent-list__meta"><?= esc($f['label']) ?> &middot; <?= esc(date('M j, Y', strtotime($f['granted_at']))) ?><?= $f['status'] === 'revoked' ? ' &middot; Revoked' : '' ?></span>
                        </span>
                        <span class="af-recent-list__amount">₱<?= number_format((float) $f['amount'], 2) ?></span>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>

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
            const res = await fetch('<?= site_url('finance/additional-fees/search-students') ?>?q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => null);
            resultsBox.replaceChildren();
            if (!data?.ok || !data.results.length) {
                const empty = document.createElement('div');
                empty.className = 'af-results__empty';
                empty.textContent = 'No matching students found.';
                resultsBox.append(empty);
                resultsBox.hidden = false;
                return;
            }
            data.results.forEach(row => {
                const item = document.createElement('a');
                item.className = 'af-result';
                item.href = '<?= site_url('finance/additional-fees/student') ?>/' + row.student_id;

                const avatar = document.createElement('span');
                avatar.className = 'af-result__avatar';
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
                name.className = 'af-result__name';
                name.textContent = row.username;

                const meta = document.createElement('span');
                meta.className = 'af-result__meta';
                meta.textContent = row.student_number;

                const text = document.createElement('span');
                text.append(name, meta);

                item.append(avatar, text);
                resultsBox.append(item);
            });
            resultsBox.hidden = false;
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!resultsBox.hidden && !e.target.closest('.af-search')) resultsBox.hidden = true;
    });
</script>
<?= $this->endSection() ?>

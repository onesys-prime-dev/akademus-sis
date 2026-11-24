<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/cashier.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/cashier.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="csh-page">
    <div class="ac-page-header">
        <div><h2 class="ac-page-header__title">Cashier</h2><p class="ac-page-header__sub">Search a student to record a payment against their current assessment.</p></div>
    </div>

    <div class="ac-stat-grid" style="margin-bottom:24px;">
        <div class="ac-metric-card cs-stagger-in" style="animation-delay:0ms;">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px; color:var(--color-success);" aria-hidden="true">payments</span><span>Collected Today</span></div>
            <div class="ac-metric-card__value" style="color:var(--color-success);">₱<?= number_format($todayTotal, 2) ?></div>
        </div>
        <div class="ac-metric-card cs-stagger-in" style="animation-delay:35ms;">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px;" aria-hidden="true">receipt_long</span><span>Transactions Today</span></div>
            <div class="ac-metric-card__value"><?= $todayCount ?></div>
        </div>
        <div class="ac-metric-card cs-stagger-in" style="animation-delay:70ms;">
            <div class="ac-metric-card__top"><span class="material-symbols-outlined" style="font-size:18px; color:<?= $pendingProofs > 0 ? 'var(--color-warning)' : 'var(--color-text-tertiary)' ?>;" aria-hidden="true">pending_actions</span><span>Pending Proofs</span></div>
            <div class="ac-metric-card__value" style="color:<?= $pendingProofs > 0 ? 'var(--color-warning)' : 'inherit' ?>;"><?= $pendingProofs ?></div>
        </div>
    </div>

    <div class="csh-layout">
        <div class="csh-search-card">
            <div class="csh-search-card__label">Find a student</div>
            <div class="csh-search">
                <span class="material-symbols-outlined csh-search__icon" aria-hidden="true">search</span>
                <input type="text" id="finSearchInput" placeholder="Username or student number…" autofocus autocomplete="off">
                <div class="csh-results" id="finResults" hidden></div>
            </div>
            <p class="csh-search-card__hint">Type at least 2 characters to search by username or student number.</p>
        </div>

        <div class="csh-recent-card">
            <div class="csh-card-head"><span class="material-symbols-outlined" aria-hidden="true">history</span><h3>Recent Payments</h3></div>
            <?php if ($recentPayments === []): ?>
                <p class="csh-recent-empty">No payments recorded yet.</p>
            <?php else: ?>
                <ul class="csh-recent-list">
                    <?php foreach ($recentPayments as $p): ?>
                        <?php $photoUrl = avatar_photo_url((int) $p['user_id'], $p['photo_mime'] ?? null); ?>
                        <li>
                            <span class="csh-recent-list__avatar" style="<?= $photoUrl === null ? 'background:' . esc(avatar_color($p['username']), 'attr') . ';color:#fff;' : '' ?>">
                                <?php if ($photoUrl !== null): ?>
                                    <img src="<?= esc($photoUrl) ?>" alt="">
                                <?php else: ?>
                                    <?= esc(strtoupper(substr($p['username'], 0, 2))) ?>
                                <?php endif ?>
                            </span>
                            <span class="csh-recent-list__info">
                                <span class="csh-recent-list__name"><?= esc($p['username']) ?></span>
                                <span class="csh-recent-list__meta"><?= esc(ucfirst(str_replace('_', ' ', $p['method']))) ?> &middot; <?= esc(date('M j, g:i A', strtotime($p['payment_date']))) ?></span>
                            </span>
                            <span class="csh-recent-list__amount">₱<?= number_format((float) $p['amount'], 2) ?></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
    const searchInput = document.getElementById('finSearchInput');
    const resultsBox = document.getElementById('finResults');
    let searchTimer = null;

    function initials(username) {
        return (username || '?').slice(0, 2).toUpperCase();
    }

    // Mirrors avatar_color()/avatar_photo_url() in app/Helpers/admin_helper.php
    // — same palette, same crc32 hash — so a student's avatar color here
    // matches the one shown everywhere else in the app.
    const AVATAR_PALETTE = ['#174ea6', '#0f766e', '#a65b08', '#15803d', '#4f5eb3', '#b91c1c', '#7c3aed', '#0e7490'];
    let crc32Table = null;
    function crc32(str) {
        if (!crc32Table) {
            crc32Table = [];
            for (let n = 0; n < 256; n++) {
                let c = n;
                for (let k = 0; k < 8; k++) c = c & 1 ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
                crc32Table[n] = c;
            }
        }
        let crc = 0xFFFFFFFF;
        for (let i = 0; i < str.length; i++) crc = crc32Table[(crc ^ str.charCodeAt(i)) & 0xFF] ^ (crc >>> 8);
        return (crc ^ 0xFFFFFFFF) >>> 0;
    }
    function avatarColor(seed) {
        return AVATAR_PALETTE[crc32(seed) % AVATAR_PALETTE.length];
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const query = searchInput.value.trim();
        if (query.length < 2) { resultsBox.hidden = true; resultsBox.replaceChildren(); return; }
        searchTimer = setTimeout(async () => {
            const res = await fetch('<?= site_url('finance/cashier/search-students') ?>?q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => null);
            resultsBox.replaceChildren();
            if (!data?.ok || !data.results.length) {
                const empty = document.createElement('div');
                empty.className = 'csh-results__empty';
                empty.textContent = 'No matching students found.';
                resultsBox.append(empty);
                resultsBox.hidden = false;
                return;
            }
            data.results.forEach(row => {
                const item = document.createElement('a');
                item.className = 'csh-result';
                item.href = '<?= site_url('finance/cashier/student') ?>/' + row.student_id;

                const avatar = document.createElement('span');
                avatar.className = 'csh-result__avatar';
                if (row.photo_mime) {
                    const img = document.createElement('img');
                    img.src = '<?= site_url('avatar') ?>/' + row.user_id;
                    img.alt = '';
                    avatar.append(img);
                } else {
                    avatar.style.background = avatarColor(row.username);
                    avatar.style.color = '#fff';
                    avatar.textContent = initials(row.username);
                }

                const name = document.createElement('span');
                name.className = 'csh-result__name';
                name.textContent = row.username;

                const meta = document.createElement('span');
                meta.className = 'csh-result__meta';
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
        if (!e.target.closest('.csh-search')) resultsBox.hidden = true;
    });
</script>
<?= $this->endSection() ?>

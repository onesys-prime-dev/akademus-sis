<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/finance-reports.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/finance-reports.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Reports</h2><p class="ac-page-header__sub">Institution-wide collection and receivables reports, plus a per-student ledger lookup.</p></div>
</div>

<div class="fr-section">

    <div class="fr-link-grid">
        <a href="<?= site_url('finance/reports/daily-collection') ?>" class="fr-link-card">
            <div class="fr-link-card__icon fr-link-card__icon--success"><span class="material-symbols-outlined" aria-hidden="true">today</span></div>
            <div class="fr-link-card__body">
                <p class="fr-link-card__label">Daily Collection Report</p>
                <p class="fr-link-card__hint">Every payment on a given day, grouped by payment method.</p>
            </div>
            <span class="material-symbols-outlined fr-link-card__arrow" aria-hidden="true">arrow_forward</span>
        </a>
        <a href="<?= site_url('finance/reports/ar-aging') ?>" class="fr-link-card">
            <div class="fr-link-card__icon fr-link-card__icon--warning"><span class="material-symbols-outlined" aria-hidden="true">schedule</span></div>
            <div class="fr-link-card__body">
                <p class="fr-link-card__label">AR Aging Report</p>
                <p class="fr-link-card__hint">Every outstanding balance, bucketed by days since billed.</p>
            </div>
            <span class="material-symbols-outlined fr-link-card__arrow" aria-hidden="true">arrow_forward</span>
        </a>
        <a href="<?= site_url('finance/reports/revenue-by-program') ?>" class="fr-link-card">
            <div class="fr-link-card__icon fr-link-card__icon--success"><span class="material-symbols-outlined" aria-hidden="true">bar_chart</span></div>
            <div class="fr-link-card__body">
                <p class="fr-link-card__label">Revenue by Program</p>
                <p class="fr-link-card__hint">Billed, collected, and outstanding, by program and year level.</p>
            </div>
            <span class="material-symbols-outlined fr-link-card__arrow" aria-hidden="true">arrow_forward</span>
        </a>
        <a href="<?= site_url('finance/reports/balances-by-program') ?>" class="fr-link-card">
            <div class="fr-link-card__icon fr-link-card__icon--warning"><span class="material-symbols-outlined" aria-hidden="true">account_balance</span></div>
            <div class="fr-link-card__body">
                <p class="fr-link-card__label">Outstanding Balances by Program</p>
                <p class="fr-link-card__hint">Which programs and year levels owe the most, this term.</p>
            </div>
            <span class="material-symbols-outlined fr-link-card__arrow" aria-hidden="true">arrow_forward</span>
        </a>
        <a href="<?= site_url('finance/reports/monthly-collection') ?>" class="fr-link-card">
            <div class="fr-link-card__icon fr-link-card__icon--success"><span class="material-symbols-outlined" aria-hidden="true">calendar_month</span></div>
            <div class="fr-link-card__body">
                <p class="fr-link-card__label">Monthly Collection Summary</p>
                <p class="fr-link-card__hint">Total collections per month, across a full year.</p>
            </div>
            <span class="material-symbols-outlined fr-link-card__arrow" aria-hidden="true">arrow_forward</span>
        </a>
        <a href="<?= site_url('finance/reports/discounts-fees-summary') ?>" class="fr-link-card">
            <div class="fr-link-card__icon fr-link-card__icon--warning"><span class="material-symbols-outlined" aria-hidden="true">sell</span></div>
            <div class="fr-link-card__body">
                <p class="fr-link-card__label">Discounts &amp; Additional Fees Summary</p>
                <p class="fr-link-card__hint">What was actually granted, by discount type and fee label.</p>
            </div>
            <span class="material-symbols-outlined fr-link-card__arrow" aria-hidden="true">arrow_forward</span>
        </a>
    </div>

    <div class="fr-card">
        <h3 class="fr-card__title">Student Ledger Summary</h3>
        <p class="fr-card__hint">Search a student to see their full chronological ledger — charges, payments, and adjustments, with a running balance.</p>
        <div class="fr-search">
            <span class="material-symbols-outlined fr-search__icon" aria-hidden="true">search</span>
            <input type="text" id="finSearchInput" placeholder="e.g. jdoe or 2026-00001" autocomplete="off">
            <div class="fr-results" id="finResults" hidden></div>
        </div>
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
            const res = await fetch('<?= site_url('finance/reports/search-students') ?>?q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => null);
            resultsBox.replaceChildren();
            if (!data?.ok || !data.results.length) {
                const empty = document.createElement('div');
                empty.className = 'fr-results__empty';
                empty.textContent = 'No matching students found.';
                resultsBox.append(empty);
                resultsBox.hidden = false;
                return;
            }
            data.results.forEach(row => {
                const item = document.createElement('a');
                item.className = 'fr-result';
                item.href = '<?= site_url('finance/reports/ledger') ?>/' + row.student_id;

                const avatar = document.createElement('span');
                avatar.className = 'fr-result__avatar';
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
                name.className = 'fr-result__name';
                name.textContent = row.username;

                const meta = document.createElement('span');
                meta.className = 'fr-result__meta';
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
        if (!resultsBox.hidden && !e.target.closest('.fr-search')) resultsBox.hidden = true;
    });
</script>
<?= $this->endSection() ?>

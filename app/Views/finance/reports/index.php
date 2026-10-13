<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<style>
    .fin-search { position: relative; max-width: 480px; }
    .fin-search input { width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); }
    .fin-results { border: 1px solid var(--color-border); border-radius: var(--radius-sm); margin-top: 6px; max-height: 240px; overflow-y: auto; }
    .fin-result { padding: 10px 12px; display: block; border-bottom: 1px solid var(--color-border); }
    .fin-result:last-child { border-bottom: none; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Reports</h2></div>
</div>

<div class="ac-bento">
    <a href="<?= site_url('finance/reports/daily-collection') ?>" class="ac-bento-card spring-hover" style="text-decoration:none; color:inherit;">
        <div class="ac-bento-card__top"><div class="ac-bento-card__icon ac-bento-card__icon--success"><span class="material-symbols-outlined icon-fill">today</span></div></div>
        <p class="ac-bento-card__label">Daily Collection Report</p>
        <h3 class="ac-bento-card__value" style="font-size:16px;">By payment method</h3>
    </a>
    <a href="<?= site_url('finance/reports/ar-aging') ?>" class="ac-bento-card spring-hover" style="text-decoration:none; color:inherit;">
        <div class="ac-bento-card__top"><div class="ac-bento-card__icon ac-bento-card__icon--warning"><span class="material-symbols-outlined icon-fill">schedule</span></div></div>
        <p class="ac-bento-card__label">AR Aging Report</p>
        <h3 class="ac-bento-card__value" style="font-size:16px;">Outstanding balances</h3>
    </a>
</div>

<div class="ac-card" style="margin-top:20px;">
    <h3>Student Ledger Summary</h3>
    <p class="panel-sub">Search a student to see their full chronological ledger.</p>
    <div class="fin-search">
        <input type="text" id="finSearchInput" placeholder="e.g. jdoe or 2026-00001">
        <div class="fin-results" id="finResults" hidden></div>
    </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
    const searchInput = document.getElementById('finSearchInput');
    const resultsBox = document.getElementById('finResults');
    let searchTimer = null;

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const query = searchInput.value.trim();
        if (query.length < 2) { resultsBox.hidden = true; resultsBox.replaceChildren(); return; }
        searchTimer = setTimeout(async () => {
            const res = await fetch('<?= site_url('finance/reports/search-students') ?>?q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => null);
            resultsBox.replaceChildren();
            if (!data?.ok || !data.results.length) { resultsBox.hidden = true; return; }
            data.results.forEach(row => {
                const item = document.createElement('a');
                item.className = 'fin-result';
                item.href = '<?= site_url('finance/reports/ledger') ?>/' + row.student_id;
                item.textContent = `${row.username} · ${row.student_number}`;
                resultsBox.append(item);
            });
            resultsBox.hidden = false;
        }, 300);
    });
</script>
<?= $this->endSection() ?>

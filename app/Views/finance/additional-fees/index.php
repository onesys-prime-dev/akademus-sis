<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<style>
    .fin-search { position: relative; max-width: 480px; margin-bottom: 20px; }
    .fin-search input { width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); }
    .fin-results { border: 1px solid var(--color-border); border-radius: var(--radius-sm); margin-top: 6px; max-height: 240px; overflow-y: auto; }
    .fin-result { padding: 10px 12px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); }
    .fin-result:last-child { border-bottom: none; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Additional Fees</h2><p class="ac-page-header__sub">One-off charges outside the regular tuition/miscellaneous structure — campus events, penalties, surcharges.</p></div>
    <a href="<?= site_url('finance/additional-fees/bulk') ?>" class="ac-btn ac-btn-primary">
        <span class="material-symbols-outlined" style="font-size:18px;">groups</span>
        <span>Bulk Apply</span>
    </a>
</div>

<div class="ac-card">
    <h3>Charge a Student</h3>
    <p class="panel-sub">Search by student username or student number, then add a fee from their account page.</p>
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
            const res = await fetch('<?= site_url('finance/additional-fees/search-students') ?>?q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => null);
            resultsBox.replaceChildren();
            if (!data?.ok || !data.results.length) { resultsBox.hidden = true; return; }
            data.results.forEach(row => {
                const item = document.createElement('a');
                item.className = 'fin-result';
                item.href = '<?= site_url('finance/additional-fees/student') ?>/' + row.student_id;
                item.textContent = `${row.username} · ${row.student_number}`;
                resultsBox.append(item);
            });
            resultsBox.hidden = false;
        }, 300);
    });
</script>
<?= $this->endSection() ?>

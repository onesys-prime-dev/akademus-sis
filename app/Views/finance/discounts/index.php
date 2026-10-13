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
    <div><h2 class="ac-page-header__title">Discounts &amp; Scholarships</h2><p class="ac-page-header__sub">Configure the discount catalog, then grant one to a student.</p></div>
</div>

<div class="ac-card" style="margin-bottom:20px;">
    <h3>Grant a Discount</h3>
    <p class="panel-sub">Search by student username or student number.</p>
    <div class="fin-search">
        <input type="text" id="finSearchInput" placeholder="e.g. jdoe or 2026-00001">
        <div class="fin-results" id="finResults" hidden></div>
    </div>
</div>

<h3>Catalog</h3>
<div class="ac-table-card" style="margin-bottom:20px;">
    <table class="ac-table">
        <thead><tr><th>Name</th><th>Code</th><th>Type</th><th>Default Value</th><th>Status</th><th></th></tr></thead>
        <tbody>
            <?php if ($discountTypes === []): ?>
                <tr><td colspan="6" class="ac-muted">No discount types configured yet.</td></tr>
            <?php endif ?>
            <?php foreach ($discountTypes as $d): ?>
                <tr>
                    <td><?= esc($d['name']) ?></td>
                    <td><?= esc($d['code']) ?></td>
                    <td><?= esc(ucfirst($d['value_type'])) ?></td>
                    <td><?= $d['value_type'] === 'percentage' ? esc($d['default_value']) . '%' : '₱' . number_format((float) $d['default_value'], 2) ?></td>
                    <td><span class="ac-status <?= $d['is_active'] ? 'locked' : '' ?>"><?= $d['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <form method="post" action="<?= site_url('finance/discounts/' . $d['id'] . '/toggle-active') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="ac-btn-outline"><?= $d['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<div class="ac-card">
    <h3>Add Discount Type</h3>
    <form method="post" action="<?= site_url('finance/discounts/store') ?>" style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto; gap:10px; align-items:end;">
        <?= csrf_field() ?>
        <div class="ac-field" style="margin:0;"><label>Name</label><input type="text" name="name" class="ac-input" placeholder="Academic Scholarship" required></div>
        <div class="ac-field" style="margin:0;"><label>Code</label><input type="text" name="code" class="ac-input" placeholder="ACAD-SCHOL" required></div>
        <div class="ac-field" style="margin:0;"><label>Value Type</label>
            <select name="value_type" class="ac-input" required>
                <option value="percentage">Percentage</option>
                <option value="fixed">Fixed Amount</option>
            </select>
        </div>
        <div class="ac-field" style="margin:0;"><label>Default Value</label><input type="number" name="default_value" class="ac-input" step="0.01" min="0.01" required></div>
        <button type="submit" class="ac-btn ac-btn-primary">Add</button>
    </form>
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
            const res = await fetch('<?= site_url('finance/discounts/search-students') ?>?q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => null);
            resultsBox.replaceChildren();
            if (!data?.ok || !data.results.length) { resultsBox.hidden = true; return; }
            data.results.forEach(row => {
                const item = document.createElement('a');
                item.className = 'fin-result';
                item.href = '<?= site_url('finance/discounts/student') ?>/' + row.student_id;
                item.textContent = `${row.username} · ${row.student_number}`;
                resultsBox.append(item);
            });
            resultsBox.hidden = false;
        }, 300);
    });
</script>
<?= $this->endSection() ?>

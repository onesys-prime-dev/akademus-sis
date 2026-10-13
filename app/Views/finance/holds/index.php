<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<style>
    .fin-search { position: relative; max-width: 480px; margin-bottom: 20px; }
    .fin-search input { width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); }
    .fin-results { border: 1px solid var(--color-border); border-radius: var(--radius-sm); margin-top: 6px; max-height: 240px; overflow-y: auto; }
    .fin-result { padding: 10px 12px; display: block; border-bottom: 1px solid var(--color-border); }
    .fin-result:last-child { border-bottom: none; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Financial Holds</h2><p class="ac-page-header__sub">Blocks enrollment/drop and document release until released.</p></div>
</div>

<div class="ac-card" style="margin-bottom:20px;">
    <h3>Find a Student</h3>
    <p class="panel-sub">Search by username or student number to place or review a hold.</p>
    <div class="fin-search">
        <input type="text" id="finSearchInput" placeholder="e.g. jdoe or 2026-00001">
        <div class="fin-results" id="finResults" hidden></div>
    </div>
</div>

<h3>Active Holds</h3>
<?php if ($holds === []): ?>
    <div class="ac-empty-block">No active holds.</div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead><tr><th>Student</th><th>Student #</th><th>Type</th><th>Reason</th><th>Placed</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($holds as $h): ?>
                    <tr>
                        <td><?= esc($h['username']) ?></td>
                        <td><?= esc($h['student_number']) ?></td>
                        <td><?= esc(ucfirst(str_replace('_', ' ', $h['hold_type']))) ?></td>
                        <td><?= esc($h['reason']) ?></td>
                        <td><?= esc(date('M j, Y', strtotime($h['placed_at']))) ?></td>
                        <td><a href="<?= site_url('finance/holds/student/' . $h['student_id']) ?>" class="ac-btn-outline">Review</a></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

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
            const res = await fetch('<?= site_url('finance/holds/search-students') ?>?q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => null);
            resultsBox.replaceChildren();
            if (!data?.ok || !data.results.length) { resultsBox.hidden = true; return; }
            data.results.forEach(row => {
                const item = document.createElement('a');
                item.className = 'fin-result';
                item.href = '<?= site_url('finance/holds/student') ?>/' + row.student_id;
                item.textContent = `${row.username} · ${row.student_number}`;
                resultsBox.append(item);
            });
            resultsBox.hidden = false;
        }, 300);
    });
</script>
<?= $this->endSection() ?>

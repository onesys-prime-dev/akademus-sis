<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<style>
    .doc-search { position: relative; max-width: 480px; margin-bottom: 20px; }
    .doc-search input { width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); }
    .doc-results { border: 1px solid var(--color-border); border-radius: var(--radius-sm); margin-top: 6px; max-height: 240px; overflow-y: auto; }
    .doc-result { padding: 10px 12px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); gap: 10px; }
    .doc-result:last-child { border-bottom: none; }
    .doc-result select { padding: 6px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Document Requests</h2><p class="ac-page-header__sub">Transcript of Records, certificates of enrollment/graduation/good-moral/candidacy.</p></div>
</div>

<div class="ac-card" style="margin-bottom:20px;">
    <h3>Generate Ad Hoc</h3>
    <p class="panel-sub">Search by student username or student number to generate a document on the spot.</p>
    <div class="doc-search">
        <input type="text" id="docSearchInput" placeholder="e.g. jdoe or 2026-00001">
        <div class="doc-results" id="docResults" hidden></div>
    </div>
</div>

<h3>Pending Requests</h3>
<?php if ($requests === []): ?>
    <div class="ac-empty-block">No document requests pending.</div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead><tr><th>Student</th><th>Student #</th><th>Document</th><th>Purpose</th><th>Requested</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?= esc($r['username']) ?></td>
                        <td><?= esc($r['student_number']) ?></td>
                        <td><?= esc(\App\Models\DocumentRequestModel::TYPE_LABELS[$r['document_type']] ?? $r['document_type']) ?></td>
                        <td><?= esc($r['purpose'] ?? '—') ?></td>
                        <td><?= esc(date('M j, Y', strtotime($r['created_at']))) ?></td>
                        <td>
                            <form method="post" action="<?= site_url('registrar/documents/generate') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="student_id" value="<?= (int) $r['student_id'] ?>">
                                <input type="hidden" name="document_type" value="<?= esc($r['document_type']) ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="ac-btn-outline">Generate &amp; Release</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
    const searchInput = document.getElementById('docSearchInput');
    const resultsBox = document.getElementById('docResults');
    let searchTimer = null;

    const TYPE_LABELS = <?= json_encode(\App\Models\DocumentRequestModel::TYPE_LABELS) ?>;

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const query = searchInput.value.trim();
        if (query.length < 2) { resultsBox.hidden = true; resultsBox.replaceChildren(); return; }
        searchTimer = setTimeout(async () => {
            const res = await fetch('<?= site_url('registrar/documents/search') ?>?q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => null);
            resultsBox.replaceChildren();
            if (!data?.ok || !data.results.length) { resultsBox.hidden = true; return; }
            data.results.forEach(row => {
                const item = document.createElement('div');
                item.className = 'doc-result';
                const label = document.createElement('span');
                label.textContent = `${row.username} · ${row.student_number}`;
                const select = document.createElement('select');
                Object.entries(TYPE_LABELS).forEach(([value, text]) => {
                    const option = document.createElement('option');
                    option.value = value; option.textContent = text;
                    select.append(option);
                });
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'ac-btn ac-btn-primary'; button.textContent = 'Generate';
                button.addEventListener('click', () => generate(row.student_id, select.value, button));
                item.append(label, select, button);
                resultsBox.append(item);
            });
            resultsBox.hidden = false;
        }, 300);
    });

    function generate(studentId, documentType, button) {
        button.disabled = true;
        const form = document.createElement('form');
        form.method = 'post'; form.action = '<?= site_url('registrar/documents/generate') ?>';
        form.innerHTML = `<?= csrf_field() ?>`;
        const sid = document.createElement('input'); sid.type = 'hidden'; sid.name = 'student_id'; sid.value = studentId;
        const dtype = document.createElement('input'); dtype.type = 'hidden'; dtype.name = 'document_type'; dtype.value = documentType;
        form.append(sid, dtype);
        document.body.append(form);
        form.submit();
    }
</script>
<?= $this->endSection() ?>

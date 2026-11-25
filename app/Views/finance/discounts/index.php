<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/finance-discounts.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/finance-discounts.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Discounts &amp; Scholarships</h2><p class="ac-page-header__sub">Configure the discount catalog, then grant one to a student.</p></div>
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

<div class="fd-section">

    <div class="fd-card">
        <h3 class="fd-card__title">Grant a Discount</h3>
        <p class="fd-card__hint">Search by student username or student number.</p>
        <div class="fd-search">
            <span class="material-symbols-outlined fd-search__icon" aria-hidden="true">search</span>
            <input type="text" id="finSearchInput" placeholder="e.g. jdoe or 2026-00001" autocomplete="off">
            <div class="fd-results" id="finResults" hidden></div>
        </div>
    </div>

    <div class="fd-card fd-card--flush">
        <div class="fd-card__head">
            <h3 class="fd-card__title">Catalog</h3>
            <p class="fd-card__hint" style="margin-bottom:0;">Discount and scholarship types available to grant.</p>
        </div>

        <?php if ($discountTypes === []): ?>
            <div class="ac-empty-block" style="border:none; border-radius:0;">No discount types configured yet &mdash; add one below.</div>
        <?php else: ?>
            <div class="ac-table-card">
                <table class="ac-table">
                    <thead><tr><th>Name</th><th>Code</th><th>Type</th><th>Default Value</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($discountTypes as $d): ?>
                            <tr>
                                <td><?= esc($d['name']) ?></td>
                                <td><code><?= esc($d['code']) ?></code></td>
                                <td><?= esc(ucfirst($d['value_type'])) ?></td>
                                <td><?= $d['value_type'] === 'percentage' ? esc($d['default_value']) . '%' : '₱' . number_format((float) $d['default_value'], 2) ?></td>
                                <td><span class="fd-status <?= $d['is_active'] ? 'fd-status--active' : 'fd-status--inactive' ?>"><?= $d['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td class="ac-actions-cell">
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
        <?php endif ?>
    </div>

    <div class="fd-card">
        <h3 class="fd-card__title">Add Discount Type</h3>
        <p class="fd-card__hint">New types appear in the catalog above and become available to grant immediately.</p>
        <form method="post" action="<?= site_url('finance/discounts/store') ?>" class="fd-form">
            <?= csrf_field() ?>
            <div class="fd-field"><label for="fdName">Name</label><input id="fdName" type="text" name="name" class="ac-input" placeholder="Academic Scholarship" required></div>
            <div class="fd-field"><label for="fdCode">Code</label><input id="fdCode" type="text" name="code" class="ac-input" placeholder="ACAD-SCHOL" required></div>
            <div class="fd-field">
                <label for="fdValueType">Value Type</label>
                <select id="fdValueType" name="value_type" class="ac-input" required>
                    <option value="percentage">Percentage</option>
                    <option value="fixed">Fixed Amount</option>
                </select>
            </div>
            <div class="fd-field"><label for="fdDefaultValue">Default Value</label><input id="fdDefaultValue" type="number" name="default_value" class="ac-input" step="0.01" min="0.01" required></div>
            <button type="submit" class="ac-btn ac-btn-primary">Add</button>
        </form>
    </div>

</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/avatar-helper.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/avatar-helper.js') ?>"></script>
<script>
    (function () {
        var valueType = document.getElementById('fdValueType');
        if (valueType && window.enhanceSelect) window.enhanceSelect(valueType);
    })();

    const searchInput = document.getElementById('finSearchInput');
    const resultsBox = document.getElementById('finResults');
    const avatarBaseUrl = <?= json_encode(site_url('avatar')) ?>;
    let searchTimer = null;

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const query = searchInput.value.trim();
        if (query.length < 2) { resultsBox.hidden = true; resultsBox.replaceChildren(); return; }
        searchTimer = setTimeout(async () => {
            const res = await fetch('<?= site_url('finance/discounts/search-students') ?>?q=' + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => null);
            resultsBox.replaceChildren();
            if (!data?.ok || !data.results.length) {
                const empty = document.createElement('div');
                empty.className = 'fd-results__empty';
                empty.textContent = 'No matching students found.';
                resultsBox.append(empty);
                resultsBox.hidden = false;
                return;
            }
            data.results.forEach(row => {
                const item = document.createElement('a');
                item.className = 'fd-result';
                item.href = '<?= site_url('finance/discounts/student') ?>/' + row.student_id;

                const avatar = document.createElement('span');
                avatar.className = 'fd-result__avatar';
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
                name.className = 'fd-result__name';
                name.textContent = row.username;

                const meta = document.createElement('span');
                meta.className = 'fd-result__meta';
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
        if (!resultsBox.hidden && !e.target.closest('.fd-search')) resultsBox.hidden = true;
    });
</script>
<?= $this->endSection() ?>

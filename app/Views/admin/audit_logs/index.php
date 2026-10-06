<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Audit Logs</h2>
        <p class="ac-page-header__sub">Every official action the system records: who did it, when, and what changed. Append-only — nothing here can be edited or deleted.</p>
    </div>
</div>

<div class="ac-table-card" style="padding:18px 20px; margin-bottom:18px;">
    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <div>
            <label style="display:block; font-size:11px; color:var(--color-text-secondary); margin-bottom:6px;">Entity type</label>
            <select name="entity_type" style="min-width:160px;">
                <option value="">All</option>
                <?php foreach ($entityTypes as $type): ?>
                    <option value="<?= esc($type) ?>" <?= $filters['entity_type'] === $type ? 'selected' : '' ?>><?= esc($type) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:11px; color:var(--color-text-secondary); margin-bottom:6px;">Action starts with</label>
            <input type="text" name="action" value="<?= esc($filters['action']) ?>" placeholder="e.g. grade" style="min-width:160px;">
        </div>
        <div>
            <label style="display:block; font-size:11px; color:var(--color-text-secondary); margin-bottom:6px;">Actor user ID</label>
            <input type="number" name="actor_id" value="<?= esc($filters['actor_id']) ?>" style="width:110px;">
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($filters['entity_type'] !== '' || $filters['action'] !== '' || $filters['actor_id'] !== ''): ?>
            <a class="btn btn-secondary" href="<?= site_url('admin/audit-logs') ?>">Clear</a>
        <?php endif ?>
    </form>
</div>

<div class="ac-table-card">
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">manage_search</div>
            <p>No log entries match these filters.</p>
        </div>
    <?php else: ?>
        <table class="ac-table">
            <thead>
                <tr><th>When</th><th>Actor</th><th>Action</th><th>Entity</th><th>Reason</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="ac-muted"><?= esc($row['created_at']) ?></td>
                        <td><?= esc($row['actor_name']) ?><?php if ($row['actor_role']): ?><br><span class="ac-pill ac-pill--neutral"><?= esc($row['actor_role']) ?></span><?php endif ?></td>
                        <td><code><?= esc($row['action']) ?></code></td>
                        <td class="ac-muted"><?= esc($row['entity_type']) ?><?= $row['entity_id'] !== null ? ' #' . esc($row['entity_id']) : '' ?></td>
                        <td class="ac-muted"><?= $row['reason'] !== null ? esc($row['reason']) : '—' ?></td>
                        <td>
                            <?php if ($row['before_json'] !== null || $row['after_json'] !== null): ?>
                                <details>
                                    <summary style="cursor:pointer; font-size:11px; color:var(--color-primary);">Details</summary>
                                    <div style="display:flex; gap:14px; margin-top:8px; font-size:11px;">
                                        <?php if ($row['before_json'] !== null): ?>
                                            <div><strong>Before</strong><pre style="white-space:pre-wrap; margin:4px 0 0; font-size:10px;"><?= esc(json_encode(json_decode($row['before_json']), JSON_PRETTY_PRINT)) ?></pre></div>
                                        <?php endif ?>
                                        <?php if ($row['after_json'] !== null): ?>
                                            <div><strong>After</strong><pre style="white-space:pre-wrap; margin:4px 0 0; font-size:10px;"><?= esc(json_encode(json_decode($row['after_json']), JSON_PRETTY_PRINT)) ?></pre></div>
                                        <?php endif ?>
                                    </div>
                                </details>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 20px; border-top:1px solid var(--color-border); font-size:11px; color:var(--color-text-secondary);">
            <span><?= number_format($total) ?> entries &middot; page <?= (int) $page ?> of <?= (int) $lastPage ?></span>
            <div style="display:flex; gap:8px;">
                <?php $query = array_filter($filters); ?>
                <?php if ($page > 1): ?><a class="btn btn-secondary" href="?<?= http_build_query($query + ['page' => $page - 1]) ?>">Previous</a><?php endif ?>
                <?php if ($page < $lastPage): ?><a class="btn btn-secondary" href="?<?= http_build_query($query + ['page' => $page + 1]) ?>">Next</a><?php endif ?>
            </div>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Academic Health</h2>
        <p class="ac-page-header__sub">Live integrity check of the College &rarr; Department &rarr; Program hierarchy and its dependents. Nothing here is auto-fixed — each finding tells you what's wrong and where to go to fix it.</p>
    </div>
</div>

<div class="ac-stats" style="margin-bottom:18px;">
    <div class="ac-stat-card">
        <div class="ac-stat-card__icon" style="background:var(--color-danger-soft); color:var(--color-danger);"><span class="material-symbols-outlined">error</span></div>
        <div class="ac-stat-card__value"><?= (int) $bySeverity['critical'] ?></div>
        <div class="ac-stat-card__label">Critical</div>
    </div>
    <div class="ac-stat-card">
        <div class="ac-stat-card__icon" style="background:var(--color-warning-soft); color:var(--color-warning);"><span class="material-symbols-outlined">warning</span></div>
        <div class="ac-stat-card__value"><?= (int) $bySeverity['warning'] ?></div>
        <div class="ac-stat-card__label">Warning</div>
    </div>
    <div class="ac-stat-card">
        <div class="ac-stat-card__icon" style="background:var(--color-info-soft); color:var(--color-info);"><span class="material-symbols-outlined">info</span></div>
        <div class="ac-stat-card__value"><?= (int) $bySeverity['informational'] ?></div>
        <div class="ac-stat-card__label">Informational</div>
    </div>
</div>

<div class="ac-table-card">
    <?php if ($findings === []): ?>
        <div class="empty-state">
            <div class="material-symbols-outlined">task_alt</div>
            <p>No integrity issues found.</p>
        </div>
    <?php else: ?>
        <table class="ac-table">
            <thead>
                <tr><th>Severity</th><th>Category</th><th>Entity</th><th>Problem</th><th>Suggested action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($findings as $f): ?>
                    <tr>
                        <td>
                            <?php if ($f['severity'] === 'critical'): ?>
                                <span class="ac-badge" style="background:var(--color-danger-soft); color:var(--color-danger);">Critical</span>
                            <?php elseif ($f['severity'] === 'warning'): ?>
                                <span class="ac-badge" style="background:var(--color-warning-soft); color:var(--color-warning);">Warning</span>
                            <?php else: ?>
                                <span class="ac-badge" style="background:var(--color-info-soft); color:var(--color-info);">Info</span>
                            <?php endif ?>
                        </td>
                        <td class="ac-muted"><?= esc(ucfirst($f['category'])) ?></td>
                        <td class="ac-muted"><?= esc($f['entity_type']) ?><?= $f['entity_id'] !== null ? ' #' . esc((string) $f['entity_id']) : '' ?></td>
                        <td><?= esc($f['message']) ?></td>
                        <td class="ac-muted"><?= esc($f['suggestion']) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<?= $this->endSection() ?>

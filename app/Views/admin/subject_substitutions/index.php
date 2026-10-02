<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Subject Substitutions</h2>
        <p class="ac-page-header__sub">Explicit, auditable rules: when a passed subject may satisfy another subject's academic requirement. These subjects remain separate catalog records — configuring a relationship never merges them.</p>
    </div>
</div>

<?php if (session()->has('message')): ?>
    <div class="ac-notice" style="margin-bottom:16px;">
        <span class="material-symbols-outlined" style="font-size:16px; color:var(--color-success); margin-top:1px;" aria-hidden="true">check_circle</span>
        <span class="ac-notice__text"><?= esc(session('message')) ?></span>
    </div>
<?php endif ?>

<?php if (session()->has('errors')): ?>
    <div class="ac-notice" style="margin-bottom:16px; border-color:#fca5a5;">
        <?php foreach (session('errors') as $error): ?>
            <span class="ac-notice__text"><?= esc($error) ?></span>
        <?php endforeach ?>
    </div>
<?php endif ?>

<div class="ac-table-card" style="padding:20px; margin-bottom:20px;">
    <h3 style="margin:0 0 12px; font-size:14px;">Add a Relationship</h3>
    <form method="post" action="<?= site_url('admin/subject-substitutions/store') ?>" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <div>
            <label style="display:block; font-size:12px; margin-bottom:4px;">Required Subject</label>
            <select name="required_subject_id" required style="min-width:220px;">
                <option value="">Choose…</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= (int) $s['subject_id'] ?>"><?= esc($s['subject_code']) ?> — <?= esc($s['subject_name']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:12px; margin-bottom:4px;">May be satisfied by</label>
            <select name="substitute_subject_id" required style="min-width:220px;">
                <option value="">Choose…</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= (int) $s['subject_id'] ?>"><?= esc($s['subject_code']) ?> — <?= esc($s['subject_name']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div style="flex:1; min-width:200px;">
            <label style="display:block; font-size:12px; margin-bottom:4px;">Notes (optional)</label>
            <input type="text" name="notes" style="width:100%;" placeholder="Why this relationship was configured">
        </div>
        <button type="submit" class="ac-btn ac-btn-primary">Add Relationship</button>
    </form>
    <p class="ac-muted" style="margin-top:10px; font-size:12px;">This makes the substitute subject satisfy the required subject's academic requirement — the direction matters. The reverse is NOT automatically true unless configured separately. This never merges or renames either subject.</p>
</div>

<?php if (empty($relationships)): ?>
    <div class="ac-empty-block">
        <p>No substitution relationships configured yet.</p>
    </div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead>
                <tr>
                    <th>Required Subject</th>
                    <th>Satisfied By</th>
                    <th>Notes</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($relationships as $r): ?>
                    <tr>
                        <td><span class="ac-cell-bold"><?= esc($r['required_code']) ?></span> — <?= esc($r['required_name']) ?></td>
                        <td><span class="ac-cell-bold"><?= esc($r['substitute_code']) ?></span> — <?= esc($r['substitute_name']) ?></td>
                        <td class="ac-muted"><?= esc($r['notes'] ?? '') ?></td>
                        <td>
                            <span class="ac-status-dot <?= $r['is_active'] ? 'ac-status-dot--active' : 'ac-status-dot--inactive' ?>">
                                <?= $r['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="ac-actions-cell">
                            <?php if ($r['is_active']): ?>
                                <form method="post" action="<?= site_url('admin/subject-substitutions/deactivate/' . $r['id']) ?>" onsubmit="return confirm('Deactivate this relationship? It can be reactivated later by adding the same relationship again.');">
                                    <button type="submit" class="ac-btn-outline">Deactivate</button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

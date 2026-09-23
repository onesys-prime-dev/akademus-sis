<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div style="font-size:13px; color:var(--color-text-muted); margin-bottom:14px;">
    The permission model enforced across every screen. Assign these roles to accounts under <b>User Accounts</b>.
</div>

<div class="ac-grid ac-grid--sm">
    <?php foreach ($roleModel as $r): ?>
        <div class="ac-card">
            <div style="display:flex; align-items:center; gap:10px;">
                <div class="ac-role-card__icon" style="background: <?= $r['bg'] ?>; color: <?= $r['color'] ?>;">
                    <?= admin_icon('shield', 17) ?>
                </div>
                <span class="ac-dept-name"><?= esc($r['role']) ?></span>
            </div>
            <ul class="ac-role-perms">
                <?php foreach ($r['perms'] as $p): ?>
                    <li>
                        <span style="color: <?= $r['color'] ?>; margin-top:1px;"><?= admin_icon('check', 13) ?></span>
                        <?= esc($p) ?>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endforeach ?>
</div>

<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Graduation Clearance</h2><p class="ac-page-header__sub">Accounting's pending clearance items, across every student.</p></div>
</div>

<?php if ($items === []): ?>
    <div class="ac-empty-block">No pending accounting clearance items.</div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead><tr><th>Student</th><th>Student #</th><th>Academic Year</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= esc($item['username']) ?></td>
                        <td><?= esc($item['student_number']) ?></td>
                        <td><?= esc($item['academic_year']) ?></td>
                        <td>
                            <form method="post" action="<?= site_url('finance/clearance/' . $item['clearance_id'] . '/item/' . $item['id'] . '/clear') ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="text" name="remarks" placeholder="Remarks (optional)" style="width:160px; font-size:11.5px;">
                                <button type="submit" class="ac-btn-outline">Clear</button>
                            </form>
                            <form method="post" action="<?= site_url('finance/clearance/' . $item['clearance_id'] . '/item/' . $item['id'] . '/deny') ?>" style="display:inline;" onsubmit="return confirm('Deny this item? A reason is required.');">
                                <?= csrf_field() ?>
                                <input type="text" name="remarks" placeholder="Reason (required)" style="width:160px; font-size:11.5px;" required>
                                <button type="submit" class="ac-btn-outline">Deny</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

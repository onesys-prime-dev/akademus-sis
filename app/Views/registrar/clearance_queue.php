<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div><h2 class="ac-page-header__title">Graduation Clearance</h2><p class="ac-page-header__sub">In-progress clearance cases across every office.</p></div>
    <a href="<?= site_url('registrar/students') ?>" class="ac-btn ac-btn-secondary">Open Student Records to initiate one</a>
</div>

<?php if ($cases === []): ?>
    <div class="ac-empty-block" style="margin-top:16px;">No clearance cases in progress.</div>
<?php else: ?>
    <div class="ac-table-card">
        <table class="ac-table">
            <thead><tr><th>Student</th><th>Student #</th><th>Academic Year</th><th>Opened</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($cases as $c): ?>
                    <tr>
                        <td><?= esc($c['username']) ?></td>
                        <td><?= esc($c['student_number']) ?></td>
                        <td><?= esc($c['academic_year']) ?></td>
                        <td><?= esc(date('M j, Y', strtotime($c['created_at']))) ?></td>
                        <td><a href="<?= site_url('registrar/clearance/' . $c['id']) ?>" class="ac-btn-outline">Review</a></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('headerActions') ?>
<button type="button" onclick="acOpenModal('modalCreateAccount')" class="ml-sm bg-system-blue hover:bg-primary text-on-primary font-body-bold px-4 py-2 rounded-lg flex items-center gap-xs transition-colors shadow-sm active:scale-[0.98]">
    <span class="material-symbols-outlined text-[18px]">add</span>
    <span class="hidden sm:inline">Create Account</span>
</button>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$roleBadge = [
    'admin'       => 'bg-surface-dim text-on-surface-variant border border-outline-variant/30',
    'chairperson' => 'bg-primary-container/10 text-primary border border-primary-container/20',
    'faculty'     => 'bg-secondary-fixed/50 text-on-secondary-fixed border border-secondary-fixed',
    'student'     => 'bg-surface-container-high text-on-surface border border-outline-variant/30',
];
?>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-md">
    <div>
        <h2 class="font-display text-display text-on-surface mb-xs">User Accounts</h2>
        <p class="font-subtext text-subtext text-on-surface-variant">Manage all user profiles and roles across the institution.</p>
    </div>

    <div class="segmented-control w-full sm:w-auto overflow-x-auto whitespace-nowrap">
        <a href="<?= site_url('admin/users') ?>" class="<?= $roleFilter === 'all' ? 'active' : '' ?>">All</a>
        <?php foreach ($roles as $r): ?>
            <a href="<?= site_url('admin/users?role=' . $r) ?>" class="<?= $roleFilter === $r ? 'active' : '' ?>"><?= esc(ucfirst($r)) ?></a>
        <?php endforeach ?>
    </div>
</div>

<div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/20 flex-1 flex flex-col overflow-hidden">

    <div class="grid grid-cols-[minmax(200px,2fr)_minmax(150px,1.5fr)_minmax(120px,1fr)_minmax(120px,1fr)_minmax(100px,1fr)_100px] gap-4 p-4 border-b border-outline-variant/20 bg-surface-bright/50 font-label-caps text-label-caps text-on-surface-variant uppercase tracking-wider">
        <div class="pl-2">Name</div>
        <div>Email</div>
        <div>Role</div>
        <div>Department</div>
        <div>Status</div>
        <div class="text-right pr-2">Actions</div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <?php if (empty($users)): ?>
            <div class="p-8 text-center text-on-surface-variant font-body-default text-body-default">No accounts for this role.</div>
        <?php endif ?>

        <?php foreach ($users as $u): ?>
            <?php $initials = strtoupper(substr($u['username'], 0, 1) . (strpos($u['username'], ' ') !== false ? substr(strrchr($u['username'], ' '), 1, 1) : '')); ?>
            <div class="grid grid-cols-[minmax(200px,2fr)_minmax(150px,1.5fr)_minmax(120px,1fr)_minmax(120px,1fr)_minmax(100px,1fr)_100px] gap-4 p-4 border-b border-outline-variant/10 hover:bg-surface-container-low/50 transition-colors items-center min-h-[64px] group">

                <div class="flex items-center gap-3 pl-2">
                    <div class="w-10 h-10 rounded-full bg-secondary-container flex items-center justify-center border border-outline-variant/20 flex-shrink-0">
                        <span class="font-headline-sm text-on-secondary-container"><?= esc($initials) ?></span>
                    </div>
                    <div class="min-w-0">
                        <div class="font-body-bold text-body-bold text-on-surface truncate"><?= esc($u['username']) ?></div>
                        <div class="font-micro text-micro text-on-surface-variant mt-0.5">ID #<?= esc($u['id']) ?></div>
                    </div>
                </div>

                <div class="font-body-default text-body-default text-secondary truncate"><?= esc($u['email']) ?></div>

                <div>
                    <span class="inline-flex items-center px-2 py-1 rounded-full font-label-caps text-label-caps <?= $roleBadge[$u['group']] ?? 'bg-surface-container text-on-surface-variant' ?>">
                        <?= esc(ucfirst($u['group'])) ?>
                    </span>
                </div>

                <div class="font-body-default text-body-default text-on-surface truncate"><?= esc($u['dept_name'] ?? '—') ?></div>

                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full <?= $u['active'] ? 'bg-status-success' : 'bg-status-warning' ?>"></span>
                        <span class="font-body-default text-body-default text-on-surface"><?= $u['active'] ? 'Active' : 'Pending' ?></span>
                    </div>
                </div>

                <div class="text-right pr-2">
                    <?php if (! $u['active']): ?>
                        <form method="post" action="<?= site_url('admin/users/activate/' . $u['id']) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="px-3 py-1.5 text-body-default font-body-bold text-system-blue border border-outline-variant rounded-md hover:bg-surface-container-low transition-colors">Activate</button>
                        </form>
                    <?php endif ?>
                </div>

            </div>
        <?php endforeach ?>
    </div>

    <div class="p-4 border-t border-outline-variant/20 flex items-center justify-between bg-surface-bright">
        <div class="font-subtext text-subtext text-on-surface-variant">Showing <?= count($users) ?> of <?= count($users) ?> users</div>
    </div>

</div>

<!-- Create account modal -->
<div class="fixed inset-0 bg-on-surface/40 backdrop-blur-sm z-[70] items-center justify-center p-4 hidden" id="modalCreateAccount" style="display:none;">
    <div class="bg-surface-white rounded-xl w-full max-w-md p-6 shadow-2xl">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h3 class="font-headline-sm text-headline-sm text-on-surface m-0">Create Account</h3>
                <p class="font-subtext text-subtext text-on-surface-variant mt-1">An invite is created; the user sets their password on first sign-in.</p>
            </div>
            <button type="button" onclick="acCloseModal('modalCreateAccount')" class="p-1.5 bg-surface-container-low rounded-lg text-on-surface-variant hover:bg-surface-container transition-colors">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/users/store') ?>">
            <?= csrf_field() ?>
            <div class="flex flex-col gap-3">
                <div>
                    <label class="font-label-caps text-label-caps text-on-surface-variant mb-1 block">Full Name</label>
                    <input type="text" name="username" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-default focus:outline-none focus:ring-2 focus:ring-system-blue/20 focus:border-system-blue" placeholder="Prof. Maria Reyes" value="<?= old('username') ?>" required>
                </div>
                <div>
                    <label class="font-label-caps text-label-caps text-on-surface-variant mb-1 block">Email</label>
                    <input type="email" name="email" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-default focus:outline-none focus:ring-2 focus:ring-system-blue/20 focus:border-system-blue" placeholder="mreyes@spum.edu.ph" value="<?= old('email') ?>" required>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-label-caps text-label-caps text-on-surface-variant mb-1 block">Role</label>
                        <select name="role" id="acRoleSelect" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-default focus:outline-none focus:ring-2 focus:ring-system-blue/20 focus:border-system-blue" required onchange="acToggleDeptField()">
                            <option value="faculty" <?= old('role') === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                            <option value="chairperson" <?= old('role') === 'chairperson' ? 'selected' : '' ?>>Chairperson</option>
                            <option value="student" <?= old('role') === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="admin" <?= old('role') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div id="acDeptField">
                        <label class="font-label-caps text-label-caps text-on-surface-variant mb-1 block">Department</label>
                        <select name="department_id" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-default focus:outline-none focus:ring-2 focus:ring-system-blue/20 focus:border-system-blue">
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= esc($d['dept_id']) ?>"><?= esc($d['code']) ?> — <?= esc($d['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <div class="hidden items-start gap-2 bg-primary-container/10 border border-primary-container/20 rounded-lg p-3" id="acChairNotice">
                    <span class="material-symbols-outlined text-primary text-[16px] mt-0.5">info</span>
                    <span class="font-subtext text-subtext text-on-surface-variant">This will set the account as the selected department's chairperson, replacing the current one if any.</span>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" onclick="acCloseModal('modalCreateAccount')" class="flex-1 py-2.5 border border-outline-variant rounded-lg font-body-bold text-body-default text-on-surface-variant hover:bg-surface-container-low transition-colors">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 bg-system-blue text-on-primary rounded-lg font-body-bold text-body-default hover:bg-primary transition-colors">Create &amp; Invite</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>
    #modalCreateAccount.is-open { display: flex !important; }
</style>
<script>
    function acToggleDeptField() {
        var role = document.getElementById('acRoleSelect').value;
        document.getElementById('acDeptField').style.display = role === 'admin' ? 'none' : 'block';
        document.getElementById('acChairNotice').style.display = role === 'chairperson' ? 'flex' : 'none';
    }
    document.addEventListener('DOMContentLoaded', acToggleDeptField);
</script>
<?php if (session('errors')): ?>
<script>document.getElementById('modalCreateAccount').classList.add('is-open'); acToggleDeptField();</script>
<?php endif ?>
<?= $this->endSection() ?>

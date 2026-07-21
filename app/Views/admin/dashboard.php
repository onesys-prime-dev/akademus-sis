<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="mb-xl">
    <h2 class="font-display text-display text-on-surface m-0 tracking-tight">Dashboard</h2>
    <p class="font-body-default text-body-default text-secondary mt-1">Overview of institutional metrics and pending actions.</p>
</div>

<?php if ($stats['unassigned'] > 0): ?>
    <div class="mb-xl animate-enter delay-100" id="deptAlert">
        <div class="bg-error-container/30 border border-status-warning/20 rounded-xl p-md flex items-start gap-md">
            <div class="bg-status-warning/10 p-2 rounded-full mt-1">
                <span class="material-symbols-outlined text-status-warning icon-fill">warning</span>
            </div>
            <div class="flex-1">
                <h4 class="font-body-bold text-body-bold text-on-surface m-0">Action Required: Unassigned Departments</h4>
                <p class="font-body-default text-body-default text-secondary mt-1 mb-3">
                    <?= esc($stats['unassigned']) ?> department<?= $stats['unassigned'] > 1 ? 's are' : ' is' ?> currently lacking a designated chairperson.
                </p>
                <div class="flex gap-sm">
                    <a href="<?= site_url('admin/departments') ?>" class="px-3 py-1.5 bg-surface-white border border-outline-variant rounded-md font-body-default text-body-default text-on-surface shadow-sm hover:bg-surface-container-low transition-colors">Review Assignments</a>
                    <button type="button" onclick="document.getElementById('deptAlert').remove()" class="px-3 py-1.5 text-secondary hover:bg-black/5 rounded-md font-body-default text-body-default transition-colors">Dismiss</button>
                </div>
            </div>
        </div>
    </div>
<?php endif ?>

<!-- Bento Grid Stats -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter mb-xl animate-enter delay-200">

    <div class="bg-surface-white rounded-[16px] p-lg shadow-[0_2px_4px_rgba(0,0,0,0.05)] border border-border-subtle spring-hover relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-bl-[100px] -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
        <div class="flex items-center justify-between mb-md relative z-10">
            <div class="p-2 bg-secondary-container text-primary rounded-lg"><span class="material-symbols-outlined icon-fill">hub</span></div>
            <span class="font-label-caps text-label-caps text-status-success bg-status-success/10 px-2 py-0.5 rounded-full"><?= esc($stats['chaired']) ?>/<?= esc($stats['departments']) ?> chaired</span>
        </div>
        <div class="relative z-10">
            <p class="font-subtext text-subtext text-secondary mb-1">Total Departments</p>
            <h3 class="font-headline-lg text-headline-lg text-on-surface m-0"><?= esc($stats['departments']) ?></h3>
        </div>
    </div>

    <div class="bg-surface-white rounded-[16px] p-lg shadow-[0_2px_4px_rgba(0,0,0,0.05)] border border-border-subtle spring-hover relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-tertiary-container/10 rounded-bl-[100px] -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
        <div class="flex items-center justify-between mb-md relative z-10">
            <div class="p-2 bg-tertiary-container/20 text-tertiary rounded-lg"><span class="material-symbols-outlined icon-fill">library_books</span></div>
            <span class="font-label-caps text-label-caps text-secondary bg-surface-container-high px-2 py-0.5 rounded-full">Active</span>
        </div>
        <div class="relative z-10">
            <p class="font-subtext text-subtext text-secondary mb-1">Active Programs</p>
            <h3 class="font-headline-lg text-headline-lg text-on-surface m-0"><?= esc($stats['programs']) ?></h3>
        </div>
    </div>

    <div class="bg-surface-white rounded-[16px] p-lg shadow-[0_2px_4px_rgba(0,0,0,0.05)] border border-border-subtle spring-hover relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-bl-[100px] -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
        <div class="flex items-center justify-between mb-md relative z-10">
            <div class="p-2 bg-secondary-container text-primary rounded-lg"><span class="material-symbols-outlined icon-fill">person_4</span></div>
            <?php if ($stats['faculty_invited'] > 0): ?>
                <span class="font-label-caps text-label-caps text-status-warning bg-status-warning/10 px-2 py-0.5 rounded-full"><?= esc($stats['faculty_invited']) ?> invited</span>
            <?php endif ?>
        </div>
        <div class="relative z-10">
            <p class="font-subtext text-subtext text-secondary mb-1">Faculty Members</p>
            <h3 class="font-headline-lg text-headline-lg text-on-surface m-0"><?= esc($stats['faculty']) ?></h3>
        </div>
    </div>

    <div class="bg-surface-white rounded-[16px] p-lg shadow-[0_2px_4px_rgba(0,0,0,0.05)] border border-border-subtle spring-hover relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-status-warning/10 rounded-bl-[100px] -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
        <div class="flex items-center justify-between mb-md relative z-10">
            <div class="p-2 bg-status-warning/20 text-status-warning rounded-lg"><span class="material-symbols-outlined icon-fill">school</span></div>
            <span class="font-label-caps text-label-caps text-secondary bg-surface-container-high px-2 py-0.5 rounded-full">Enrolled</span>
        </div>
        <div class="relative z-10">
            <p class="font-subtext text-subtext text-secondary mb-1">Total Students</p>
            <h3 class="font-headline-lg text-headline-lg text-on-surface m-0"><?= esc($stats['students']) ?></h3>
        </div>
    </div>

</div>

<!-- Recent Activity & Setup -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter animate-enter delay-300 pb-xl">

    <div class="lg:col-span-2 bg-surface-white rounded-[16px] shadow-[0_2px_4px_rgba(0,0,0,0.05)] border border-border-subtle overflow-hidden">
        <div class="px-lg py-md border-b border-border-subtle flex justify-between items-center bg-surface-container-lowest">
            <h3 class="font-headline-sm text-headline-sm text-on-surface m-0">Recent Institutional Changes</h3>
        </div>
        <div class="p-0">
            <?php if (empty($activity)): ?>
                <div class="px-lg py-xl text-center text-secondary font-body-default text-body-default">Nothing recorded yet.</div>
            <?php else: ?>
                <?php foreach ($activity as $i => $item): ?>
                    <div class="flex items-center justify-between px-lg py-md <?= $i < count($activity) - 1 ? 'border-b border-border-subtle' : '' ?> hover:bg-surface-container-lowest transition-colors">
                        <div class="flex items-center gap-md">
                            <div class="w-10 h-10 rounded-full bg-surface-container-high flex items-center justify-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-[20px]"><?= esc($item['icon']) ?></span>
                            </div>
                            <div>
                                <p class="font-body-bold text-body-bold text-on-surface m-0"><?= esc($item['title']) ?></p>
                                <p class="font-subtext text-subtext text-secondary m-0"><?= esc($item['detail']) ?></p>
                            </div>
                        </div>
                        <span class="font-subtext text-subtext text-outline"><?= esc(time_ago($item['time'])) ?></span>
                    </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>

    <div class="lg:col-span-1 flex flex-col gap-gutter">

        <div class="bg-surface-white rounded-[16px] shadow-[0_2px_4px_rgba(0,0,0,0.05)] border border-border-subtle p-lg">
            <h3 class="font-headline-sm text-headline-sm text-on-surface m-0 mb-4">System Setup</h3>
            <div class="w-full bg-surface-container-highest rounded-full h-2 mb-2">
                <div class="bg-primary h-2 rounded-full" style="width: <?= esc($setupPercent) ?>%"></div>
            </div>
            <p class="font-subtext text-subtext text-secondary mb-4">
                <?= esc($setupPercent) ?>% complete.
                <?= $setupPercent < 100
                    ? 'Finish assigning chairpersons to unlock full reporting.'
                    : 'All departments have a chairperson assigned.' ?>
            </p>
            <?php if ($setupPercent < 100): ?>
                <a href="<?= site_url('admin/departments') ?>" class="block text-center w-full py-2 bg-surface-container border border-outline-variant rounded-lg font-body-default text-body-default text-on-surface hover:bg-surface-container-high transition-colors">Continue Setup</a>
            <?php endif ?>
        </div>

        <div class="rounded-[16px] shadow-[0_2px_4px_rgba(0,0,0,0.05)] border border-border-subtle overflow-hidden relative h-40 spring-hover group">
            <img src="<?= base_url('assets/images/background/auth-image.png') ?>" alt="Campus" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
            <div class="absolute inset-0 bg-gradient-to-t from-canvas-midnight/80 to-transparent flex items-end p-md">
                <div class="text-white">
                    <p class="font-label-caps text-label-caps m-0 opacity-80">Active Term</p>
                    <p class="font-body-bold text-body-bold m-0">
                        <?= $stats['active_ay'] ? esc($stats['active_ay']['label']) . ' · ' . esc($stats['active_ay']['semester']) . ' Sem' : 'No active term set' ?>
                    </p>
                </div>
            </div>
        </div>

    </div>

</div>

<?= $this->endSection() ?>

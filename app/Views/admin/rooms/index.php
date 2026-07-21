<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('content') ?>

<div class="flex flex-col md:flex-row md:items-end justify-between gap-md">
    <div>
        <h1 class="font-display text-display text-on-surface mb-xs">Rooms</h1>
        <p class="font-subtext text-subtext text-secondary">Manage spaces across all building floors.</p>
    </div>
    <button type="button" onclick="acOpenModal('modalAddRoom')" class="bg-system-blue text-white px-md py-[10px] rounded-lg font-body-bold text-body-bold shadow-sm hover:bg-primary transition-colors flex items-center gap-xs w-fit">
        <span class="material-symbols-outlined text-[18px]">add</span>
        New Room
    </button>
</div>

<!-- Floor Tabs -->
<div class="flex items-center justify-between border-b border-outline-variant/30 pb-xs mt-lg">
    <div class="flex gap-lg overflow-x-auto">
        <a href="<?= site_url('admin/rooms') ?>"
           class="pb-sm whitespace-nowrap <?= $activeFloor === 'overview' ? 'text-primary font-body-bold border-b-2 border-primary' : 'text-on-surface-variant font-body-default hover:text-on-surface transition-colors' ?>">Overview</a>
        <?php foreach ($floors as $floor): ?>
            <a href="<?= site_url('admin/rooms?floor=' . urlencode($floor)) ?>"
               class="pb-sm whitespace-nowrap <?= $activeFloor === $floor ? 'text-primary font-body-bold border-b-2 border-primary' : 'text-on-surface-variant font-body-default hover:text-on-surface transition-colors' ?>"><?= esc($floor) ?> Floor</a>
        <?php endforeach ?>
    </div>
    <button type="button" onclick="acOpenModal('modalAddRoom')" class="hidden md:flex items-center gap-xs px-sm py-xs rounded-lg border border-dashed border-outline-variant text-secondary hover:bg-surface-container-low transition-colors ml-lg shrink-0">
        <span class="material-symbols-outlined text-[16px]">add</span>
        <span class="font-body-default text-body-default">Add Floor</span>
    </button>
</div>

<?php if ($activeFloor === 'overview'): ?>

    <?php if (empty($floors)): ?>
        <div class="mt-lg bg-surface-white rounded-xl border border-dashed border-outline-variant p-xl text-center text-secondary font-body-default text-body-default">
            No rooms yet. Add the first one to start assigning schedules.
        </div>
    <?php else: ?>
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-md mt-lg">
            <?php foreach ($floors as $floor): ?>
                <a href="<?= site_url('admin/rooms?floor=' . urlencode($floor)) ?>"
                   class="bg-surface-white rounded-2xl p-md shadow-[0_2px_4px_rgba(0,0,0,0.05)] border border-border-subtle hover:shadow-[0_8px_16px_rgba(0,0,0,0.08)] transition-all cursor-pointer group aspect-square flex flex-col justify-between">
                    <div class="w-12 h-12 rounded-full bg-secondary-fixed text-primary flex items-center justify-center group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined icon-filled">layers</span>
                    </div>
                    <div>
                        <h3 class="font-headline-md text-headline-md text-on-surface mb-xs"><?= esc($floor) ?> Floor</h3>
                        <p class="font-subtext text-subtext text-secondary">
                            <?= esc($floorSummary[$floor]['count']) ?> Room<?= $floorSummary[$floor]['count'] === 1 ? '' : 's' ?> &middot; <?= esc($floorSummary[$floor]['seats']) ?> seats
                        </p>
                    </div>
                </a>
            <?php endforeach ?>
        </section>
    <?php endif ?>

<?php else: ?>

    <div class="mt-lg">
        <?php if (empty($currentRooms)): ?>
            <div class="bg-surface-white rounded-xl border border-dashed border-outline-variant p-xl text-center">
                <p class="font-body-default text-body-default text-secondary mb-md">No rooms on this floor yet.</p>
                <button type="button" onclick="acOpenModal('modalAddRoom')" class="bg-system-blue text-white px-md py-[10px] rounded-lg font-body-bold text-body-bold shadow-sm hover:bg-primary transition-colors inline-flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[18px]">add</span> Add Room
                </button>
            </div>
        <?php else: ?>
            <div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/20 overflow-hidden">
                <div class="grid grid-cols-[2fr_1fr_1fr_1fr_220px] gap-4 p-4 border-b border-outline-variant/20 bg-surface-bright/50 font-label-caps text-label-caps text-on-surface-variant uppercase tracking-wider">
                    <div>Room</div>
                    <div>Capacity</div>
                    <div>Type</div>
                    <div>Status</div>
                    <div class="text-right pr-2">Actions</div>
                </div>
                <?php foreach ($currentRooms as $room): ?>
                    <div class="grid grid-cols-[2fr_1fr_1fr_1fr_220px] gap-4 p-4 border-b border-outline-variant/10 last:border-b-0 hover:bg-surface-container-low/50 transition-colors items-center">
                        <div class="font-body-bold text-body-bold text-on-surface"><?= esc($room['name']) ?></div>
                        <div class="font-body-default text-body-default text-secondary"><?= esc($room['capacity']) ?> seats</div>
                        <div><span class="inline-flex px-2 py-1 rounded-full bg-surface-container-high text-on-surface font-label-caps text-label-caps"><?= esc($room['type']) ?></span></div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full <?= $room['is_active'] ? 'bg-status-success' : 'bg-outline' ?>"></span>
                            <span class="font-body-default text-body-default text-on-surface"><?= $room['is_active'] ? 'Active' : 'Inactive' ?></span>
                        </div>
                        <div class="flex items-center justify-end gap-2 pr-2">
                            <form method="post" action="<?= site_url('admin/rooms/toggle-status/' . $room['room_id']) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" onclick="return confirm('Change room status?')" class="px-3 py-1.5 text-body-default font-body-bold text-system-blue border border-outline-variant rounded-md hover:bg-surface-container-low transition-colors whitespace-nowrap">
                                    <?= $room['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                            <button type="button" onclick="acOpenModal('modalEditRoom<?= $room['room_id'] ?>')" title="Edit" class="p-1.5 text-on-surface-variant hover:text-system-blue border border-outline-variant rounded-md hover:bg-surface-container-low transition-colors">
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </button>
                            <form method="post" action="<?= site_url('admin/rooms/delete/' . $room['room_id']) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" onclick="return confirm('Delete <?= esc($room['name'], 'js') ?>? This cannot be undone.')" title="Delete" class="p-1.5 text-status-error border border-outline-variant rounded-md hover:bg-error-container/30 transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Edit room modal -->
                    <div class="fixed inset-0 bg-on-surface/40 backdrop-blur-sm z-[70] items-center justify-center p-4 hidden" id="modalEditRoom<?= $room['room_id'] ?>">
                        <div class="bg-surface-white rounded-xl w-full max-w-md p-6 shadow-2xl">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="font-headline-sm text-headline-sm text-on-surface m-0">Edit Room</h3>
                                <button type="button" onclick="acCloseModal('modalEditRoom<?= $room['room_id'] ?>')" class="p-1.5 bg-surface-container-low rounded-lg text-on-surface-variant hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">close</span>
                                </button>
                            </div>

                            <form method="post" action="<?= site_url('admin/rooms/update/' . $room['room_id']) ?>">
                                <?= csrf_field() ?>
                                <div class="flex flex-col gap-3">
                                    <div>
                                        <label class="font-label-caps text-label-caps text-on-surface-variant mb-1 block">Room Name</label>
                                        <input type="text" name="name" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-default focus:outline-none focus:ring-2 focus:ring-system-blue/20 focus:border-system-blue" value="<?= esc($room['name']) ?>" required>
                                    </div>
                                    <div>
                                        <label class="font-label-caps text-label-caps text-on-surface-variant mb-1 block">Floor</label>
                                        <input type="text" name="floor" list="floorOptions" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-default focus:outline-none focus:ring-2 focus:ring-system-blue/20 focus:border-system-blue" value="<?= esc($room['floor']) ?>" required>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="font-label-caps text-label-caps text-on-surface-variant mb-1 block">Capacity</label>
                                            <input type="number" name="capacity" min="1" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-default focus:outline-none focus:ring-2 focus:ring-system-blue/20 focus:border-system-blue" value="<?= esc($room['capacity']) ?>" required>
                                        </div>
                                        <div>
                                            <label class="font-label-caps text-label-caps text-on-surface-variant mb-1 block">Type</label>
                                            <select name="type" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-default focus:outline-none focus:ring-2 focus:ring-system-blue/20 focus:border-system-blue" required>
                                                <?php foreach (['Lecture', 'Laboratory', 'Computer Laboratory'] as $t): ?>
                                                    <option value="<?= $t ?>" <?= $room['type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                                                <?php endforeach ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex gap-3 mt-6">
                                    <button type="button" onclick="acCloseModal('modalEditRoom<?= $room['room_id'] ?>')" class="flex-1 py-2.5 border border-outline-variant rounded-lg font-body-bold text-body-default text-on-surface-variant hover:bg-surface-container-low transition-colors">Cancel</button>
                                    <button type="submit" class="flex-1 py-2.5 bg-system-blue text-on-primary rounded-lg font-body-bold text-body-default hover:bg-primary transition-colors">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>

<?php endif ?>

<!-- Add room modal -->
<div class="fixed inset-0 bg-on-surface/40 backdrop-blur-sm z-[70] items-center justify-center p-4 hidden" id="modalAddRoom">
    <div class="bg-surface-white rounded-xl w-full max-w-md p-6 shadow-2xl">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h3 class="font-headline-sm text-headline-sm text-on-surface m-0">Add Room</h3>
                <p class="font-subtext text-subtext text-on-surface-variant mt-1">Rooms are shared across all departments.</p>
            </div>
            <button type="button" onclick="acCloseModal('modalAddRoom')" class="p-1.5 bg-surface-container-low rounded-lg text-on-surface-variant hover:bg-surface-container transition-colors">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/rooms/store') ?>">
            <?= csrf_field() ?>
            <div class="flex flex-col gap-3">
                <div>
                    <label class="font-label-caps text-label-caps text-on-surface-variant mb-1 block">Room Name</label>
                    <input type="text" name="name" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-default focus:outline-none focus:ring-2 focus:ring-system-blue/20 focus:border-system-blue" placeholder="Room 210" value="<?= old('name') ?>" required>
                </div>
                <div>
                    <label class="font-label-caps text-label-caps text-on-surface-variant mb-1 block">Floor</label>
                    <input type="text" name="floor" list="floorOptions" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-default focus:outline-none focus:ring-2 focus:ring-system-blue/20 focus:border-system-blue" placeholder="2nd"
                           value="<?= old('floor', $activeFloor !== 'overview' ? $activeFloor : '') ?>" required>
                    <datalist id="floorOptions">
                        <?php foreach ($floors as $floor): ?>
                            <option value="<?= esc($floor) ?>"></option>
                        <?php endforeach ?>
                    </datalist>
                    <p class="font-subtext text-subtext text-on-surface-variant mt-1">Type a new name here to start a new floor.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-label-caps text-label-caps text-on-surface-variant mb-1 block">Capacity</label>
                        <input type="number" name="capacity" min="1" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-default focus:outline-none focus:ring-2 focus:ring-system-blue/20 focus:border-system-blue" value="<?= old('capacity', '40') ?>" required>
                    </div>
                    <div>
                        <label class="font-label-caps text-label-caps text-on-surface-variant mb-1 block">Type</label>
                        <select name="type" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-default focus:outline-none focus:ring-2 focus:ring-system-blue/20 focus:border-system-blue" required>
                            <option value="Lecture">Lecture</option>
                            <option value="Laboratory">Laboratory</option>
                            <option value="Computer Laboratory">Computer Laboratory</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" onclick="acCloseModal('modalAddRoom')" class="flex-1 py-2.5 border border-outline-variant rounded-lg font-body-bold text-body-default text-on-surface-variant hover:bg-surface-container-low transition-colors">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 bg-system-blue text-on-primary rounded-lg font-body-bold text-body-default hover:bg-primary transition-colors">Add Room</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>
    #modalAddRoom.is-open, [id^="modalEditRoom"].is-open { display: flex !important; }
</style>
<?php if (session('errors')): ?>
<script>document.getElementById('modalAddRoom').classList.add('is-open');</script>
<?php endif ?>
<?= $this->endSection() ?>

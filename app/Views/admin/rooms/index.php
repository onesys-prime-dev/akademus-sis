<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('headerActions') ?>
<button type="button" onclick="acOpenModal('modalAddRoom')" class="ac-btn ac-btn-primary">
    <span class="material-symbols-outlined" style="font-size:18px;">add</span>
    <span>New Room</span>
</button>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
// Floor names are free text (e.g. "4th", but also "4th Floor" if someone
// types it that way) — only append " Floor" when it isn't already there,
// so labels never read "4th Floor Floor".
$floorLabel = static fn (string $name): string => preg_match('/\bfloor$/i', trim($name)) ? esc($name) : esc($name) . ' Floor';
?>

<div class="ac-page-header" style="margin-bottom:0;">
    <div>
        <h1 class="ac-page-header__title">Rooms</h1>
        <p class="ac-page-header__sub">Manage spaces across all building floors.</p>
    </div>
</div>

<!-- Floor Tabs -->
<div class="ac-tabs">
    <div class="ac-tabs__links">
        <a href="<?= site_url('admin/rooms') ?>" class="ac-tabs__link <?= $activeFloor === 'overview' ? 'is-active' : '' ?>">Overview</a>
        <?php foreach ($floors as $floor): ?>
            <a href="<?= site_url('admin/rooms?floor=' . urlencode($floor)) ?>" class="ac-tabs__link <?= $activeFloor === $floor ? 'is-active' : '' ?>"><?= $floorLabel($floor) ?></a>
        <?php endforeach ?>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
        <?php if ($activeFloor !== 'overview' && in_array($activeFloor, $floors, true)): ?>
            <form method="post" action="<?= site_url('admin/floors/delete') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="name" value="<?= esc($activeFloor, 'attr') ?>">
                <button type="submit" class="ac-tabs__add-floor" data-confirm="Delete <?= esc($activeFloor, 'attr') ?> floor? Only floors with no rooms can be deleted.">
                    <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                    <span>Delete Floor</span>
                </button>
            </form>
        <?php endif ?>
        <button type="button" onclick="acOpenModal('modalAddFloor')" class="ac-tabs__add-floor">
            <span class="material-symbols-outlined" style="font-size:16px;">add</span>
            <span>Add Floor</span>
        </button>
    </div>
</div>

<?php if ($activeFloor === 'overview'): ?>

    <?php if (empty($floors)): ?>
        <div class="ac-empty-block">No rooms yet. Add the first one to start assigning schedules.</div>
    <?php else: ?>
        <section class="ac-tile-grid">
            <?php foreach ($floors as $floor): ?>
                <a href="<?= site_url('admin/rooms?floor=' . urlencode($floor)) ?>" class="ac-tile">
                    <div class="ac-tile__icon"><span class="material-symbols-outlined icon-fill">layers</span></div>
                    <div>
                        <h3 class="ac-tile__title"><?= $floorLabel($floor) ?></h3>
                        <p class="ac-tile__meta">
                            <?= esc($floorSummary[$floor]['count']) ?> Room<?= $floorSummary[$floor]['count'] === 1 ? '' : 's' ?> &middot; <?= esc($floorSummary[$floor]['seats']) ?> seats
                        </p>
                    </div>
                </a>
            <?php endforeach ?>
        </section>
    <?php endif ?>

<?php else: ?>

    <?php if (empty($currentRooms)): ?>
        <div class="ac-empty-block">
            <p style="margin-bottom:16px;">No rooms on this floor yet.</p>
            <button type="button" onclick="acOpenModal('modalAddRoom')" class="ac-btn ac-btn-primary" style="display:inline-flex;">
                <span class="material-symbols-outlined" style="font-size:18px;">add</span> Add Room
            </button>
        </div>
    <?php else: ?>
        <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
            <button type="button" onclick="acOpenModal('modalAddRoom')" class="ac-btn ac-btn-primary">
                <span class="material-symbols-outlined" style="font-size:18px;">add</span> Add Room
            </button>
        </div>
        <div class="ac-dtable ac-rtable">
            <div class="ac-dtable__head">
                <div>Room</div>
                <div>Capacity</div>
                <div>Type</div>
                <div>Status</div>
                <div style="text-align:right;">Actions</div>
            </div>

            <?php foreach ($currentRooms as $room): ?>
                <div class="ac-dtable__row">
                    <div class="ac-cell-bold"><?= esc($room['name']) ?></div>
                    <div class="ac-cell-muted"><?= esc($room['capacity']) ?> seats</div>
                    <div>
                        <span class="ac-chip"><?= esc($room['type']) ?></span>
                        <?php if ($room['is_laboratory']): ?>
                            <span class="ac-chip" style="background:#eef2ff; color:#4338ca; margin-left:4px;">Laboratory</span>
                        <?php endif ?>
                    </div>
                    <div>
                        <span class="ac-status-dot <?= $room['is_active'] ? 'ac-status-dot--active' : 'ac-status-dot--inactive' ?>">
                            <?= $room['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    <div class="ac-actions-cell">
                        <form method="post" action="<?= site_url('admin/rooms/toggle-status/' . $room['room_id']) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" data-confirm="Change room status?" class="ac-btn-outline">
                                <?= $room['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                        <button type="button" onclick="acOpenModal('modalEditRoom<?= $room['room_id'] ?>')" title="Edit" class="ac-icon-action">
                            <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                        </button>
                        <form method="post" action="<?= site_url('admin/rooms/delete/' . $room['room_id']) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" data-confirm="Delete <?= esc($room['name'], 'attr') ?>? This cannot be undone." title="Delete" class="ac-icon-action ac-icon-action--danger">
                                <span class="material-symbols-outlined" style="font-size:18px;">delete</span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Edit room modal -->
                <div class="ac-modal" id="modalEditRoom<?= $room['room_id'] ?>">
                    <div class="ac-modal__panel">
                        <div class="ac-modal__header">
                            <div class="ac-modal__title">Edit Room</div>
                            <button type="button" onclick="acCloseModal('modalEditRoom<?= $room['room_id'] ?>')" class="ac-modal__close">
                                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                            </button>
                        </div>

                        <form method="post" action="<?= site_url('admin/rooms/update/' . $room['room_id']) ?>">
                            <?= csrf_field() ?>
                            <div class="ac-modal__body">
                                <div class="ac-field">
                                    <label>Room Name</label>
                                    <input type="text" name="name" class="ac-input" value="<?= esc($room['name']) ?>" required>
                                </div>
                                <div class="ac-field">
                                    <label>Floor</label>
                                    <select name="floor" class="ac-input" required>
                                        <?php foreach ($floors as $floor): ?>
                                            <option value="<?= esc($floor) ?>" <?= $floor === $room['floor'] ? 'selected' : '' ?>><?= esc($floor) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                </div>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                                    <div class="ac-field">
                                        <label>Capacity</label>
                                        <input type="number" name="capacity" min="1" class="ac-input" value="<?= esc($room['capacity']) ?>" required>
                                    </div>
                                    <div class="ac-field">
                                        <label>Type</label>
                                        <select name="type" class="ac-input" required>
                                            <?php foreach (['Regular Classroom', 'Lecture', 'Lecture Hall', 'Laboratory', 'Computer Laboratory', 'Science Laboratory', 'Networking Laboratory', 'Other'] as $t): ?>
                                                <option value="<?= $t ?>" <?= $room['type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                                            <?php endforeach ?>
                                        </select>
                                    </div>
                                </div>
                                <label style="display:flex; align-items:center; gap:8px; font-size:13px; margin-top:4px;">
                                    <input type="checkbox" name="is_laboratory" value="1" <?= $room['is_laboratory'] ? 'checked' : '' ?>>
                                    Laboratory room (recommended first when plotting laboratory subjects)
                                </label>
                            </div>

                            <div class="ac-modal__actions">
                                <button type="button" onclick="acCloseModal('modalEditRoom<?= $room['room_id'] ?>')" class="ac-btn ac-btn-secondary">Cancel</button>
                                <button type="submit" class="ac-btn ac-btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>

<?php endif ?>

<!-- Add floor modal: a floor is now its own record, so it can be created
     empty — before, "Add Floor" secretly required adding a room too. -->
<div class="ac-modal" id="modalAddFloor">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title">Add Floor</div>
                <div class="ac-modal__subtitle">You can add rooms to it afterward.</div>
            </div>
            <button type="button" onclick="acCloseModal('modalAddFloor')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/floors/store') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="floor">
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Floor Name</label>
                    <input type="text" name="name" class="ac-input" placeholder="4th" value="<?= old('form') === 'floor' ? old('name') : '' ?>" required autofocus>
                </div>
            </div>

            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalAddFloor')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Add Floor</button>
            </div>
        </form>
    </div>
</div>

<!-- Add room modal -->
<div class="ac-modal" id="modalAddRoom">
    <div class="ac-modal__panel">
        <div class="ac-modal__header">
            <div>
                <div class="ac-modal__title">Add Room</div>
                <div class="ac-modal__subtitle">Rooms are shared across all departments.</div>
            </div>
            <button type="button" onclick="acCloseModal('modalAddRoom')" class="ac-modal__close">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>

        <form method="post" action="<?= site_url('admin/rooms/store') ?>">
            <?= csrf_field() ?>
            <div class="ac-modal__body">
                <div class="ac-field">
                    <label>Room Name</label>
                    <input type="text" name="name" class="ac-input" placeholder="Room 210" value="<?= old('form') !== 'floor' ? old('name') : '' ?>" required>
                </div>
                <div class="ac-field">
                    <label>Floor</label>
                    <select name="floor" class="ac-input" required>
                        <?php $selectedFloor = old('form') !== 'floor' ? old('floor', $activeFloor !== 'overview' ? $activeFloor : '') : ''; ?>
                        <?php if ($selectedFloor === ''): ?>
                            <option value="" disabled selected>Select a floor</option>
                        <?php endif ?>
                        <?php foreach ($floors as $floor): ?>
                            <option value="<?= esc($floor) ?>" <?= $floor === $selectedFloor ? 'selected' : '' ?>><?= esc($floor) ?></option>
                        <?php endforeach ?>
                    </select>
                    <?php if (empty($floors)): ?>
                        <p style="font-size:12.5px; color:var(--color-text-secondary); margin-top:4px;">No floors yet — add one first.</p>
                    <?php endif ?>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="ac-field">
                        <label>Capacity</label>
                        <input type="number" name="capacity" min="1" class="ac-input" value="<?= old('capacity', '40') ?>" required>
                    </div>
                    <div class="ac-field">
                        <label>Type</label>
                        <select name="type" class="ac-input" required>
                            <option value="Regular Classroom">Regular Classroom</option>
                            <option value="Lecture">Lecture</option>
                            <option value="Lecture Hall">Lecture Hall</option>
                            <option value="Laboratory">Laboratory</option>
                            <option value="Computer Laboratory">Computer Laboratory</option>
                            <option value="Science Laboratory">Science Laboratory</option>
                            <option value="Networking Laboratory">Networking Laboratory</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; margin-top:4px;">
                    <input type="checkbox" name="is_laboratory" value="1">
                    Laboratory room (recommended first when plotting laboratory subjects)
                </label>
            </div>

            <div class="ac-modal__actions">
                <button type="button" onclick="acCloseModal('modalAddRoom')" class="ac-btn ac-btn-secondary">Cancel</button>
                <button type="submit" class="ac-btn ac-btn-primary">Add Room</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (session('errors') && old('form') === 'floor'): ?>
<script>document.getElementById('modalAddFloor').classList.add('is-open');</script>
<?php elseif (session('errors')): ?>
<script>document.getElementById('modalAddRoom').classList.add('is-open');</script>
<?php endif ?>
<?= $this->endSection() ?>

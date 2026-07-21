<?= $this->extend('layouts/admin_console') ?>

<?= $this->section('headerActions') ?>
<button type="button" onclick="acOpenModal('modalAddRoom')" class="ac-btn ac-btn-primary">
    <span class="material-symbols-outlined" style="font-size:18px;">add</span>
    <span>New Room</span>
</button>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

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
            <a href="<?= site_url('admin/rooms?floor=' . urlencode($floor)) ?>" class="ac-tabs__link <?= $activeFloor === $floor ? 'is-active' : '' ?>"><?= esc($floor) ?> Floor</a>
        <?php endforeach ?>
    </div>
    <button type="button" onclick="acOpenModal('modalAddRoom')" class="ac-tabs__add-floor">
        <span class="material-symbols-outlined" style="font-size:16px;">add</span>
        <span>Add Floor</span>
    </button>
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
                        <h3 class="ac-tile__title"><?= esc($floor) ?> Floor</h3>
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
                    <div><span class="ac-chip"><?= esc($room['type']) ?></span></div>
                    <div>
                        <span class="ac-status-dot <?= $room['is_active'] ? 'ac-status-dot--active' : 'ac-status-dot--inactive' ?>">
                            <?= $room['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    <div class="ac-actions-cell">
                        <form method="post" action="<?= site_url('admin/rooms/toggle-status/' . $room['room_id']) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" onclick="return confirm('Change room status?')" class="ac-btn-outline">
                                <?= $room['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                        <button type="button" onclick="acOpenModal('modalEditRoom<?= $room['room_id'] ?>')" title="Edit" class="ac-icon-action">
                            <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                        </button>
                        <form method="post" action="<?= site_url('admin/rooms/delete/' . $room['room_id']) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" onclick="return confirm('Delete <?= esc($room['name'], 'js') ?>? This cannot be undone.')" title="Delete" class="ac-icon-action ac-icon-action--danger">
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
                                    <input type="text" name="floor" list="floorOptions" class="ac-input" value="<?= esc($room['floor']) ?>" required>
                                </div>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                                    <div class="ac-field">
                                        <label>Capacity</label>
                                        <input type="number" name="capacity" min="1" class="ac-input" value="<?= esc($room['capacity']) ?>" required>
                                    </div>
                                    <div class="ac-field">
                                        <label>Type</label>
                                        <select name="type" class="ac-input" required>
                                            <?php foreach (['Lecture', 'Laboratory', 'Computer Laboratory'] as $t): ?>
                                                <option value="<?= $t ?>" <?= $room['type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                                            <?php endforeach ?>
                                        </select>
                                    </div>
                                </div>
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
                    <input type="text" name="name" class="ac-input" placeholder="Room 210" value="<?= old('name') ?>" required>
                </div>
                <div class="ac-field">
                    <label>Floor</label>
                    <input type="text" name="floor" list="floorOptions" class="ac-input" placeholder="2nd"
                           value="<?= old('floor', $activeFloor !== 'overview' ? $activeFloor : '') ?>" required>
                    <datalist id="floorOptions">
                        <?php foreach ($floors as $floor): ?>
                            <option value="<?= esc($floor) ?>"></option>
                        <?php endforeach ?>
                    </datalist>
                    <p style="font-size:12.5px; color:#515f74; margin-top:4px;">Type a new name here to start a new floor.</p>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="ac-field">
                        <label>Capacity</label>
                        <input type="number" name="capacity" min="1" class="ac-input" value="<?= old('capacity', '40') ?>" required>
                    </div>
                    <div class="ac-field">
                        <label>Type</label>
                        <select name="type" class="ac-input" required>
                            <option value="Lecture">Lecture</option>
                            <option value="Laboratory">Laboratory</option>
                            <option value="Computer Laboratory">Computer Laboratory</option>
                        </select>
                    </div>
                </div>
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
<?php if (session('errors')): ?>
<script>document.getElementById('modalAddRoom').classList.add('is-open');</script>
<?php endif ?>
<?= $this->endSection() ?>

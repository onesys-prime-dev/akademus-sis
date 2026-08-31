<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\FloorModel;
use App\Models\RoomModel;

class RoomController extends BaseController
{
    protected RoomModel $roomModel;
    protected FloorModel $floorModel;

    public function __construct()
    {
        $this->roomModel  = new RoomModel();
        $this->floorModel = new FloorModel();
    }

    public function index()
    {
        $rooms = $this->roomModel->orderBy('floor', 'asc')->orderBy('name', 'asc')->findAll();

        // Floors is the source of truth for "which floors exist" (an empty
        // floor with no rooms yet still needs to show up), unioned with any
        // room floor value in case a room was ever saved with a floor that
        // doesn't have its own floors row (defensive; shouldn't happen once
        // store()/update() below always upsert into floors too).
        $floors = array_values(array_unique(array_merge(
            $this->floorModel->names(),
            array_column($rooms, 'floor')
        )));
        sort($floors, SORT_NATURAL | SORT_FLAG_CASE);

        $activeFloor = $this->request->getGet('floor') ?: 'overview';

        $floorSummary = [];
        foreach ($floors as $floor) {
            $floorRooms = array_values(array_filter($rooms, fn ($r) => $r['floor'] === $floor));
            $floorSummary[$floor] = [
                'count' => count($floorRooms),
                'seats' => array_sum(array_column($floorRooms, 'capacity')),
            ];
        }

        $currentRooms = $activeFloor !== 'overview'
            ? array_values(array_filter($rooms, fn ($r) => $r['floor'] === $activeFloor))
            : [];

        return view('admin/rooms/index', [
            'pageTitle'    => 'Rooms',
            'activeAdmin'  => 'rooms',
            'rooms'        => $rooms,
            'floors'       => $floors,
            'floorSummary' => $floorSummary,
            'activeFloor'  => $activeFloor,
            'currentRooms' => $currentRooms,
        ]);
    }

    public function store()
    {
        $rules = [
            'name'     => 'required|max_length[100]',
            'capacity' => 'required|integer|greater_than[0]',
            'type'     => 'required|max_length[50]',
            'floor'    => 'required|max_length[30]',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $floor = $this->request->getPost('floor');
        $this->ensureFloorExists($floor);

        $this->roomModel->insert([
            'name'          => $this->request->getPost('name'),
            'capacity'      => (int) $this->request->getPost('capacity'),
            'type'          => $this->request->getPost('type'),
            'is_laboratory' => $this->request->getPost('is_laboratory') ? 1 : 0,
            'floor'         => $floor,
        ]);

        return redirect()
            ->to('/admin/rooms?floor=' . urlencode($floor))
            ->with('message', 'Room added successfully.');
    }

    /**
     * A standalone floor, with no room required — the actual "Add Floor"
     * action. `store()`/`update()` above also upsert into `floors` when a
     * new name is typed there, so both paths keep `floors` as the single
     * source of truth for which floors exist.
     */
    public function storeFloor()
    {
        $rules = ['name' => 'required|max_length[30]'];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $name = trim((string) $this->request->getPost('name'));

        if ($this->floorModel->where('name', $name)->first() !== null) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', ['name' => "Floor \"{$name}\" already exists."]);
        }

        $this->floorModel->insert(['name' => $name]);

        return redirect()
            ->to('/admin/rooms?floor=' . urlencode($name))
            ->with('message', "Floor \"{$name}\" added — add its first room below.");
    }

    private function ensureFloorExists(string $name): void
    {
        if ($this->floorModel->where('name', $name)->first() === null) {
            $this->floorModel->insert(['name' => $name]);
        }
    }

    public function update($id)
    {
        $room = $this->roomModel->find($id);

        if (! $room) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'name'     => 'required|max_length[100]',
            'capacity' => 'required|integer|greater_than[0]',
            'type'     => 'required|max_length[50]',
            'floor'    => 'required|max_length[30]',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $floor = $this->request->getPost('floor');
        $this->ensureFloorExists($floor);

        $this->roomModel->update($id, [
            'name'          => $this->request->getPost('name'),
            'capacity'      => (int) $this->request->getPost('capacity'),
            'type'          => $this->request->getPost('type'),
            'is_laboratory' => $this->request->getPost('is_laboratory') ? 1 : 0,
            'floor'         => $floor,
        ]);

        return redirect()
            ->to('/admin/rooms?floor=' . urlencode($floor))
            ->with('message', 'Room updated successfully.');
    }

    public function delete($id)
    {
        $room = $this->roomModel->find($id);

        if (! $room) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $this->roomModel->delete($id);

        return redirect()
            ->to('/admin/rooms?floor=' . urlencode($room['floor']))
            ->with('message', "{$room['name']} deleted.");
    }

    public function toggleStatus($id)
    {
        $room = $this->roomModel->find($id);

        if (! $room) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $this->roomModel->update($id, ['is_active' => $room['is_active'] ? 0 : 1]);

        return redirect()
            ->back()
            ->with('message', 'Room status updated.');
    }
}

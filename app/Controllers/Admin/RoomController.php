<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\RoomModel;

class RoomController extends BaseController
{
    protected RoomModel $roomModel;

    public function __construct()
    {
        $this->roomModel = new RoomModel();
    }

    public function index()
    {
        $rooms       = $this->roomModel->orderBy('floor', 'asc')->orderBy('name', 'asc')->findAll();
        $floors      = $this->roomModel->floors();
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

        $this->roomModel->insert([
            'name'     => $this->request->getPost('name'),
            'capacity' => (int) $this->request->getPost('capacity'),
            'type'     => $this->request->getPost('type'),
            'floor'    => $floor,
        ]);

        return redirect()
            ->to('/admin/rooms?floor=' . urlencode($floor))
            ->with('message', 'Room added successfully.');
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

        $this->roomModel->update($id, [
            'name'     => $this->request->getPost('name'),
            'capacity' => (int) $this->request->getPost('capacity'),
            'type'     => $this->request->getPost('type'),
            'floor'    => $floor,
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

<?php

namespace App\Models;

use CodeIgniter\Model;

class RoomModel extends Model
{
    protected $table      = 'rooms';
    protected $primaryKey = 'room_id';

    protected $allowedFields = ['name', 'capacity', 'type', 'is_laboratory', 'floor', 'is_active'];

    protected $useTimestamps = true;

    public function floors(): array
    {
        return $this->select('floor')
            ->distinct()
            ->orderBy('floor', 'asc')
            ->findColumn('floor') ?? [];
    }
}

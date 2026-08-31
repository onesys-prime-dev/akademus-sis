<?php

namespace App\Models;

use CodeIgniter\Model;

class FloorModel extends Model
{
    protected $table      = 'floors';
    protected $primaryKey = 'floor_id';

    protected $allowedFields = ['name'];

    protected $useTimestamps = true;

    public function names(): array
    {
        return $this->orderBy('name', 'asc')->findColumn('name') ?? [];
    }
}

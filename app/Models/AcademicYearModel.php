<?php

namespace App\Models;

use CodeIgniter\Model;

class AcademicYearModel extends Model
{
    protected $table      = 'academic_years';
    protected $primaryKey = 'ay_id';

    protected $allowedFields = ['label', 'semester', 'is_active'];

    protected $useTimestamps = true;

    public function setActive(int $id): void
    {
        $this->builder()->set('is_active', 0)->update();
        $this->update($id, ['is_active' => 1]);
    }
}

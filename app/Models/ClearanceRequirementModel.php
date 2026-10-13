<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Configurable catalog behind graduation_clearances/graduation_clearance_items
 * — see CreateClearanceRequirements migration.
 */
class ClearanceRequirementModel extends Model
{
    protected $table      = 'clearance_requirements';
    protected $primaryKey = 'id';

    protected $allowedFields = ['name', 'office_role', 'is_active'];

    protected $useTimestamps = true;

    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }
}

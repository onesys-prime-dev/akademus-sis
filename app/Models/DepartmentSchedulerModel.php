<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Explicit delegation rows: a user may schedule for a sub-department other
 * than the one they belong to. See CreateSubDepartmentSchedulers migration.
 */
class DepartmentSchedulerModel extends Model
{
    protected $table      = 'department_schedulers';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'department_id',
        'user_id',
        'created_at',
    ];

    /**
     * department_id values a user has been explicitly delegated scheduling
     * authority for, beyond their own sub-department.
     *
     * @return list<int>
     */
    public function subDeptIdsForUser(int $userId): array
    {
        return array_map('intval', array_column(
            $this->where('user_id', $userId)->findAll(),
            'department_id'
        ));
    }
}

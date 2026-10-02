<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Explicit delegation rows: a user may schedule for a sub-department other
 * than the one they belong to. See CreateSubDepartmentSchedulers migration.
 */
class SubDepartmentSchedulerModel extends Model
{
    protected $table      = 'sub_department_schedulers';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'sub_dept_id',
        'user_id',
        'created_at',
    ];

    /**
     * sub_dept_id values a user has been explicitly delegated scheduling
     * authority for, beyond their own sub-department.
     *
     * @return list<int>
     */
    public function subDeptIdsForUser(int $userId): array
    {
        return array_map('intval', array_column(
            $this->where('user_id', $userId)->findAll(),
            'sub_dept_id'
        ));
    }
}

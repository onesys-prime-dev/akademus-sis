<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Explicit delegation rows: a user may act as dean for a college other than
 * the one they primary-head. See CreateDepartmentDeans migration. Mirrors
 * SubDepartmentSchedulerModel exactly, one level up the hierarchy.
 */
class DepartmentDeanModel extends Model
{
    protected $table      = 'department_deans';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'dept_id',
        'user_id',
        'created_at',
    ];

    /**
     * dept_id values a user has been explicitly delegated dean authority
     * for, beyond their own primary college.
     *
     * @return list<int>
     */
    public function deptIdsForUser(int $userId): array
    {
        return array_map('intval', array_column(
            $this->where('user_id', $userId)->findAll(),
            'dept_id'
        ));
    }
}

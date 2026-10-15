<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * A Department (chairperson-headed), nested under a College (dean-headed).
 */
class SubDepartmentModel extends Model
{
    protected $table      = 'sub_departments';
    protected $primaryKey = 'sub_dept_id';

    protected $allowedFields = ['college_id', 'code', 'name', 'is_active'];

    protected $useTimestamps = true;

    /**
     * Row-locks this department for the duration of the enclosing
     * transaction so concurrent chair-assignment requests for it serialize
     * instead of racing — each department has exactly one chairperson at a
     * time. FOR UPDATE is only valid under MySQL (production); it's a no-op
     * elsewhere (the SQLite test connection is single-process anyway).
     */
    public function lockForUpdate(int $subDeptId): ?array
    {
        $db     = $this->db;
        $suffix = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';

        return $db->query("SELECT * FROM sub_departments WHERE sub_dept_id = ?{$suffix}", [$subDeptId])->getRowArray();
    }
}

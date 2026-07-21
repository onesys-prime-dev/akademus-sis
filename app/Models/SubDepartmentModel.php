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
}

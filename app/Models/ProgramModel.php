<?php

namespace App\Models;

use CodeIgniter\Model;

class ProgramModel extends Model
{
    protected $table = 'programs';
    protected $primaryKey = 'program_id';

    protected $allowedFields = [
        'sub_department_id',
        'program_code',
        'program_name',
        'description',
        'is_active'
    ];

    protected $returnType = 'array';
    
    protected $useTimestamps = true;
    
}

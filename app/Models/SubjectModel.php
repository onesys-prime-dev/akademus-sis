<?php

namespace App\Models;

use CodeIgniter\Model;

class SubjectModel extends Model
{
    protected $table = 'subjects';
    protected $primaryKey = 'subject_id';

    protected $allowedFields = [
        'sub_department_id',
        'program_id',
        'subject_code',
        'subject_name',
        'units',
        'contact_hours',
        'is_laboratory',
        'description',
        'is_active'
    ];

    protected $returnType = 'array';
    
    protected $useTimestamps = true;
}

<?php

namespace App\Models;

use CodeIgniter\Model;

class CurriculumModel extends Model
{
    protected $table            = 'curriculum';
    protected $primaryKey       = 'curriculum_id';

    protected $allowedFields = [
        'program_id',
        'curriculum_code',
        'curriculum_name',
        'academic_year',
        'description',
        'is_active',
    ];

    protected $useTimestamps = true;
}

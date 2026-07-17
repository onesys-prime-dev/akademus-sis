<?php

namespace App\Models;

use CodeIgniter\Model;

class FacultyAssignmentModel extends Model
{
    protected $table      = 'faculty_assignments';
    protected $primaryKey = 'assignment_id';

    protected $allowedFields = [
        'user_id',
        'academic_year',
        'program',
        'year_level',
        'section',
        'subject_code',
        'method',
        'status',
    ];

    protected $useTimestamps = true;
}

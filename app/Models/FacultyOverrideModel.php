<?php

namespace App\Models;

use CodeIgniter\Model;

class FacultyOverrideModel extends Model
{
    protected $table      = 'faculty_assignment_overrides';
    protected $primaryKey = 'override_id';

    protected $allowedFields = [
        'chairperson_id',
        'faculty_id',
        'academic_year',
        'program',
        'year_level',
        'section',
        'subject_code',
        'day',
        'start_time',
        'end_time',
        'conflict_type',
        'reason',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = '';
}

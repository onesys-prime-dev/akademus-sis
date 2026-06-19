<?php

namespace App\Models;

use CodeIgniter\Model;

class CurriculumSubjectModel extends Model
{
    protected $table            = 'curriculum_subjects';
    protected $primaryKey       = 'curriculum_subject_id';

    protected $allowedFields = [
        'curriculum_id',
        'subject_id',
        'year_level',
        'semester',
        'is_required',
    ];

    protected $useTimestamps = true;
}

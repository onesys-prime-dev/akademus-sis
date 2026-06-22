<?php

namespace App\Models;

use CodeIgniter\Model;

class SubjectPrerequisiteModel extends Model
{
    protected $table            = 'subject_prerequisite';
    protected $primaryKey       = 'subject_prerequisite_id';
    protected $allowedFields = 
    [
        'subject_id',
        'prerequisite_subject_id'
    ];
    
    protected $useTimestamps = true;
}

<?php

namespace App\Models;

use CodeIgniter\Model;

class SubjectPrerequisiteModel extends Model
{
    protected $table            = 'subject_prerequisites';
    protected $primaryKey       = 'subject_prerequisites_id';
    protected $allowedFields = 
    [
        'subject_id',
        'prerequisite_subject_id'
    ];
    
    protected $useTimestamps = true;
}

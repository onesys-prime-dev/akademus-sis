<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The catalog of schedulable "parts" a subject can be split into. Never
 * hard-coded — an institution defines exactly the types it needs (Lecture,
 * Laboratory, Clinical/RLE, Individual Instruction, or anything else).
 */
class ComponentTypeModel extends Model
{
    protected $table      = 'component_types';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'code',
        'name',
        'is_active',
    ];

    protected $useTimestamps = true;
}

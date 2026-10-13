<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Per-office sign-off rows for one graduation_clearances case. Thin —
 * GraduationClearanceModel owns the business logic (clearItem/denyItem/
 * isFullyCleared/finalizeGraduation).
 */
class GraduationClearanceItemModel extends Model
{
    protected $table      = 'graduation_clearance_items';
    protected $primaryKey = 'id';

    protected $allowedFields = ['clearance_id', 'requirement_id', 'status', 'cleared_by', 'cleared_at', 'remarks'];
}

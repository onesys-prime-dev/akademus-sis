<?php

namespace App\Models;

use CodeIgniter\Model;

class SwapRequestModel extends Model
{
    protected $table      = 'swap_requests';
    protected $primaryKey = 'swap_id';

    protected $allowedFields = [
        'assignment_id',
        'requested_assignment_id',
        'requester_id',
        'target_id',
        'status',
    ];

    protected $useTimestamps = true;
}

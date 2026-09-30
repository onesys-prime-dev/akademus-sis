<?php

namespace App\Models;

use CodeIgniter\Model;

class TuitionRateModel extends Model
{
    protected $table      = 'tuition_rates';
    protected $primaryKey = 'rate_id';

    protected $allowedFields = [
        'program_id',
        'academic_year',
        'per_unit_rate',
        'misc_fee',
    ];

    protected $useTimestamps = true;

    public function forProgramYear(int $programId, string $academicYear): ?array
    {
        return $this->where('program_id', $programId)->where('academic_year', $academicYear)->first();
    }
}

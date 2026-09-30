<?php

namespace App\Models;

use CodeIgniter\Model;

class ProgramFeeRateModel extends Model
{
    protected $table      = 'program_fee_rates';
    protected $primaryKey = 'program_fee_rate_id';

    protected $allowedFields = [
        'program_id',
        'academic_year',
        'fee_type_id',
        'amount',
    ];

    protected $useTimestamps = true;

    /**
     * Every priced fee for this program/year, joined to the catalog (only
     * active fee types — a deactivated one stops being charged to newly
     * (re)computed assessments even if a rate row still exists for it).
     */
    public function forProgramYear(int $programId, string $academicYear): array
    {
        return $this->db->table('program_fee_rates pfr')
            ->select('pfr.fee_type_id, pfr.amount, ft.category, ft.name, ft.sort_order')
            ->join('fee_types ft', 'ft.fee_type_id = pfr.fee_type_id')
            ->where('pfr.program_id', $programId)
            ->where('pfr.academic_year', $academicYear)
            ->where('ft.is_active', 1)
            ->orderBy('ft.category')
            ->orderBy('ft.sort_order')
            ->get()
            ->getResultArray();
    }

    /** Keyed by fee_type_id, for pre-filling the rate-sheet edit form. */
    public function amountsByFeeType(int $programId, string $academicYear): array
    {
        $rows = $this->where('program_id', $programId)->where('academic_year', $academicYear)->findAll();

        return array_column($rows, 'amount', 'fee_type_id');
    }
}

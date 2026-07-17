<?php

namespace App\Models;

use CodeIgniter\Model;

class CurriculumPresetModel extends Model
{
    protected $table      = 'curriculum_presets';
    protected $primaryKey = 'preset_id';

    protected $allowedFields = [
        'academic_year',
        'program',
        'year_level',
        'subject_codes',
    ];

    protected $useTimestamps = true;

    /**
     * The preset for one AY + program + year level, or null when the
     * chairperson hasn't created one yet.
     */
    public function forContext(string $academicYear, string $program, string $yearLevel): ?array
    {
        return $this->where([
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
        ])->first();
    }

    /**
     * Create or replace the preset for one context.
     *
     * @param string[] $subjectCodes
     */
    public function upsert(string $academicYear, string $program, string $yearLevel, array $subjectCodes): void
    {
        $existing = $this->forContext($academicYear, $program, $yearLevel);

        $row = [
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
            'subject_codes' => json_encode(array_values($subjectCodes)),
        ];

        if ($existing === null) {
            $this->insert($row);
        } else {
            $this->update($existing['preset_id'], $row);
        }
    }
}

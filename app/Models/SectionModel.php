<?php

namespace App\Models;

use CodeIgniter\Model;

class SectionModel extends Model
{
    protected $table      = 'sections';
    protected $primaryKey = 'section_id';

    protected $allowedFields = [
        'academic_year',
        'program',
        'year_level',
        'section',
        'capacity',
        'status',
    ];

    protected $useTimestamps = true;

    public function forContext(string $academicYear, string $program, string $yearLevel, string $section): ?array
    {
        return $this->where([
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
            'section'       => $section,
        ])->first();
    }

    /**
     * The section row for this context, creating it (unlimited capacity)
     * if it doesn't exist yet. This is how a section becomes a real row the
     * first time a chairperson plots a schedule for it.
     */
    public function firstOrCreateForContext(string $academicYear, string $program, string $yearLevel, string $section): array
    {
        $existing = $this->forContext($academicYear, $program, $yearLevel, $section);

        if ($existing !== null) {
            return $existing;
        }

        $this->insert([
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
            'section'       => $section,
        ]);

        return $this->find($this->getInsertID());
    }
}

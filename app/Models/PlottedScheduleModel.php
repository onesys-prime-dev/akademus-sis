<?php

namespace App\Models;

use CodeIgniter\Model;

class PlottedScheduleModel extends Model
{
    protected $table      = 'plotted_schedules';
    protected $primaryKey = 'schedule_id';

    protected $allowedFields = [
        'academic_year',
        'program',
        'year_level',
        'section',
        'day',
        'start_time',
        'end_time',
        'time_label',
        'subject_code',
        'subject_title',
        'prof',
        'room',
        'theme',
        'status',
    ];

    protected $useTimestamps = true;

    /**
     * Blocks plotted for one context (AY + program + year level + section).
     */
    public function forContext(string $academicYear, string $program, string $yearLevel, string $section): array
    {
        return $this->where([
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
            'section'       => $section,
        ])->findAll();
    }

    /**
     * Blocks plotted for every OTHER context in the same academic year.
     * Used to grey out occupied rooms and catch faculty overlaps.
     */
    public function forOtherContexts(string $academicYear, string $program, string $yearLevel, string $section): array
    {
        return $this->where('academic_year', $academicYear)
            ->groupStart()
                ->where('program !=', $program)
                ->orWhere('year_level !=', $yearLevel)
                ->orWhere('section !=', $section)
            ->groupEnd()
            ->findAll();
    }

    /**
     * Replace a context's draft atomically.
     *
     * @param array $rows Fully-prepared row arrays for insertBatch.
     */
    public function replaceDraft(string $academicYear, string $program, string $yearLevel, string $section, array $rows): void
    {
        $this->db->transException(true)->transStart();

        $this->where([
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
            'section'       => $section,
        ])->delete();

        if ($rows !== []) {
            $this->insertBatch($rows);
        }

        $this->db->transComplete();
    }
}

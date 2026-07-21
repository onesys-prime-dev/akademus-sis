<?php

namespace App\Models;

use CodeIgniter\Model;

class AcademicYearModel extends Model
{
    protected $table      = 'academic_years';
    protected $primaryKey = 'ay_id';

    protected $allowedFields = ['label', 'semester', 'is_active'];

    protected $useTimestamps = true;

    public function setActive(int $id): void
    {
        $this->builder()->set('is_active', 0)->update();
        $this->update($id, ['is_active' => 1]);
    }

    /**
     * "2025-2026" -> "AY 2025 - 2026", matching the format already stored on
     * scheduling rows (plotted_schedules.academic_year, faculty_assignments.academic_year).
     */
    public static function format(string $label): string
    {
        return 'AY ' . str_replace('-', ' - ', $label);
    }

    /**
     * The active year, formatted, or null if none is set active.
     */
    public function activeFormatted(): ?string
    {
        $row = $this->where('is_active', 1)->first();

        return $row === null ? null : self::format($row['label']);
    }
}

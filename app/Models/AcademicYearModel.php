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
     * "2025-2026" -> "AY 2025 - 2026", matching the format stored on
     * scheduling rows (plotted_schedules.academic_year, faculty_assignments.academic_year,
     * sections.academic_year, curriculum_presets.academic_year). Pass $semester
     * to fold the term into the same identity string ("AY 2025 - 2026 · 1st
     * Semester") — this is what makes semester a real, distinct scheduling
     * dimension instead of just a display label, since every table above
     * only ever stores this one free-text string, not separate columns.
     */
    public static function format(string $label, ?string $semester = null): string
    {
        $base = 'AY ' . str_replace('-', ' - ', $label);

        return $semester === null ? $base : "{$base} · {$semester} Semester";
    }

    /**
     * The active year (+ semester), formatted, or null if none is set active.
     */
    public function activeFormatted(): ?string
    {
        $row = $this->where('is_active', 1)->first();

        return $row === null ? null : self::format($row['label'], $row['semester']);
    }

    /**
     * The full active-term row (carrying ay_id), or null if none is active.
     * Only one row is ever active at a time (setActive() enforces this), so
     * this is the bridge between the scheduling engine's string-keyed
     * academic_year tuple and the ay_id foreign key used by the new
     * availability/preferences tables.
     */
    public function activeRow(): ?array
    {
        return $this->where('is_active', 1)->first();
    }

    /**
     * The term faculty should be submitting availability/preferences FOR —
     * the most recently added term that isn't the currently active one yet.
     * Distinct from activeFormatted()/is_active, which is the term currently
     * being scheduled/taught, not the one being planned for.
     */
    public function upcomingTerm(): ?array
    {
        return $this->where('is_active', 0)->orderBy('ay_id', 'DESC')->first();
    }
}

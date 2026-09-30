<?php

namespace App\Models;

use CodeIgniter\Model;

class AcademicYearModel extends Model
{
    protected $table      = 'academic_years';
    protected $primaryKey = 'ay_id';

    protected $allowedFields = ['label', 'semester', 'is_active', 'start_date', 'end_date'];

    protected $useTimestamps = true;

    public function setActive(int $id): void
    {
        $this->db->transStart();
        $this->builder()->set('is_active', 0)->update();
        $this->update($id, ['is_active' => 1]);
        $this->db->transComplete();
    }

    /**
     * The row whose start_date/end_date range covers $date (defaults to
     * today), or null if no term's range covers it (a real gap between
     * terms, or dates were never set on the row that should match).
     */
    public function termForDate(?string $date = null): ?array
    {
        $date ??= date('Y-m-d');

        return $this->where('start_date <=', $date)
            ->where('end_date >=', $date)
            ->orderBy('start_date', 'DESC')
            ->first();
    }

    /**
     * Flips is_active to whichever term's date range covers $date, if that
     * differs from the currently active row. Leaves the active row alone
     * when termForDate() finds nothing (a gap) — several callers
     * (SISDevelopmentSeeder, CurriculumImportPresets, dashboards) assume
     * some row is always active, and clearing it to zero would be worse
     * than leaving a stale-but-present one.
     *
     * @return array{changed: bool, from: ?array, to: ?array, reason?: string}
     */
    public function syncActiveToDate(?string $date = null): array
    {
        $target  = $this->termForDate($date);
        $current = $this->activeRow();

        if ($target === null) {
            return ['changed' => false, 'from' => $current, 'to' => null, 'reason' => 'No term\'s date range covers ' . ($date ?? date('Y-m-d')) . '.'];
        }

        if ($current !== null && (int) $current['ay_id'] === (int) $target['ay_id']) {
            return ['changed' => false, 'from' => $current, 'to' => $target];
        }

        $this->setActive((int) $target['ay_id']);

        return ['changed' => true, 'from' => $current, 'to' => $target];
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
     * the soonest term whose start_date is still in the future. Distinct
     * from activeFormatted()/is_active, which is the term currently being
     * scheduled/taught, not the one being planned for.
     *
     * Previously this meant "the most recently added row that isn't
     * active" (highest ay_id, is_active=0) — a proxy that only worked
     * because "active" was a pure manual toggle. Now that a row can be
     * inactive because it's in the PAST as easily as the future, that
     * proxy no longer reliably picks the next chronological term, so this
     * is date-driven instead. Rows with no start_date set are excluded
     * (nothing to compare) rather than sorting unpredictably.
     */
    public function upcomingTerm(): ?array
    {
        return $this->where('start_date >', date('Y-m-d'))
            ->orderBy('start_date', 'ASC')
            ->first();
    }
}

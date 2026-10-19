<?php

namespace App\Models;

use CodeIgniter\Model;

class AcademicYearModel extends Model
{
    protected $table      = 'academic_years';
    protected $primaryKey = 'ay_id';

    protected $allowedFields = [
        'label', 'semester', 'is_active', 'start_date', 'end_date',
        'grading_opens_at', 'grading_closes_at', 'registration_opens_at', 'registration_closes_at',
        'enlistment_opens_at', 'enlistment_closes_at',
    ];

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
     * (SISDevelopmentSeeder, dashboards) assume some row is always active,
     * and clearing it to zero would be worse than leaving a stale-but-present
     * one.
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
     * sections.academic_year). Pass $semester
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
     * Strips a term-scoped string's "· Nth Semester" suffix down to its
     * year-only form — "AY 2026 - 2027 · 1st Semester" -> "AY 2026 - 2027".
     * A value that's already year-only passes through unchanged. This is
     * what `sections.academic_year` means today (year only, no semester);
     * plotted_schedules/faculty_assignments/grades/course_offerings/
     * enlistments/enrollments all keep the full term-scoped string — this
     * helper is for the handful of call sites that need to go from "the
     * term being worked in" to "the section that term's work belongs to."
     */
    public static function yearOnly(string $academicYear): string
    {
        return trim(explode('·', $academicYear)[0]);
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
     * The upcoming term (see upcomingTerm()), formatted, or null if there
     * isn't one yet. This — not activeFormatted() — is what "enlistment"
     * means: a student pre-registers for the NEXT term while the active one
     * is still in progress, not re-shops for classes in the term they're
     * already sitting in. Every enlistment-facing lookup (StudentEnlistment::
     * wizard(), the cart's own academic_year, subject offerings, tuition
     * assessment for what's being enlisted into) is keyed off this.
     */
    public function upcomingFormatted(): ?string
    {
        $row = $this->upcomingTerm();

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

    /**
     * Every term that's still ahead of today, soonest first — unlike
     * upcomingTerm() this isn't collapsed to a single row, because more
     * than one term can legitimately be "upcoming" at once (e.g. 2nd
     * Semester and a Summer term both scheduled after the currently active
     * term). Callers that let a chair/registrar pick which future term
     * they're configuring (enlistment window, offered subjects) need the
     * whole list, not just the nearest one.
     */
    public function upcomingTerms(): array
    {
        return $this->where('start_date >', date('Y-m-d'))
            ->orderBy('start_date', 'ASC')
            ->findAll();
    }

    /**
     * Whether grading is currently open for $ayId (defaults to the active
     * term). A term with no window set (both columns null) is treated as
     * open — the window is an opt-in restriction a registrar deliberately
     * sets, not a default lockout.
     */
    public function isGradingOpen(?int $ayId = null, ?string $now = null): bool
    {
        return $this->isWindowOpen('grading_opens_at', 'grading_closes_at', $ayId, $now);
    }

    /**
     * Whether registration/enrollment is currently open for $ayId (defaults
     * to the active term). Same open-by-default rule as isGradingOpen().
     */
    public function isRegistrationOpen(?int $ayId = null, ?string $now = null): bool
    {
        return $this->isWindowOpen('registration_opens_at', 'registration_closes_at', $ayId, $now);
    }

    /**
     * Whether the online enlistment / pre-registration window is currently
     * open for $ayId (defaults to the active term). While open, students
     * build/submit a cart through the enlistment wizard instead of using
     * the instant-enroll endpoints (see StudentEnrollment::enroll()'s
     * gate).
     *
     * Unlike the other two windows, enlistment defaults CLOSED when no
     * dates are set — a chairperson/registrar must deliberately open it for
     * a term before students can self-enlist, rather than a freshly-created
     * term silently accepting carts nobody meant to allow yet. Registration
     * and grading keep the opposite (open-by-default) rule: those gate
     * staff-driven actions that would otherwise need every term configured
     * by hand before routine work (manual enrollment, grading) could happen
     * at all.
     *
     * Also unlike the other two windows, when $ayId isn't given this
     * defaults to the UPCOMING term (upcomingTerm()), not the active one —
     * enlistment is pre-registration for what's next, checked while the
     * active term is still running. No upcoming term existing yet (nothing
     * to enlist into) is treated as closed.
     */
    public function isEnlistmentOpen(?int $ayId = null, ?string $now = null): bool
    {
        if ($ayId === null) {
            $ayId = $this->upcomingTerm()['ay_id'] ?? null;

            if ($ayId === null) {
                return false;
            }
        }

        return $this->isWindowOpen('enlistment_opens_at', 'enlistment_closes_at', $ayId, $now, false);
    }

    private function isWindowOpen(string $opensColumn, string $closesColumn, ?int $ayId, ?string $now, bool $defaultOpen = true): bool
    {
        $row = $ayId !== null ? $this->find($ayId) : $this->activeRow();

        if ($row === null) {
            // No term resolved at all — nothing to gate against, so don't
            // block the caller over a calendar-configuration gap.
            return true;
        }

        if ($row[$opensColumn] === null && $row[$closesColumn] === null) {
            return $defaultOpen;
        }

        $now ??= date('Y-m-d H:i:s');

        if ($row[$opensColumn] !== null && $now < $row[$opensColumn]) {
            return false;
        }

        if ($row[$closesColumn] !== null && $now > $row[$closesColumn]) {
            return false;
        }

        return true;
    }
}

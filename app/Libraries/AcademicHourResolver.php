<?php

namespace App\Libraries;

/**
 * Derives a subject's weekly contact-hours target. Units and hours are
 * different quantities (a 3-unit lecture subject and a 3-unit laboratory
 * subject are not the same number of scheduled hours), so this resolves the
 * relationship instead of assuming 1 unit = 1 hour.
 *
 * Priority (subject-specific values always win over institutional defaults):
 *   1. Explicit `contact_hours` (hours/meeting) x `meetings_per_week`, when both are set.
 *   2. Explicit `lecture_units`/`laboratory_units` split, rated via Academic.* settings.
 *   3. Plain `units` + `is_laboratory` flag, rated via the same Academic.* settings.
 *
 * Rates default to the commonly documented Philippine HEI convention
 * (1 lecture unit ~= 1 hr/week, 1 laboratory unit ~= 3 hrs/week) but are
 * institution-configurable via Academic.lectureHoursPerUnit /
 * Academic.labHoursPerUnit, since no single formula fits every institution.
 */
class AcademicHourResolver
{
    public const DEFAULT_LECTURE_HOURS_PER_UNIT = 1.0;
    public const DEFAULT_LAB_HOURS_PER_UNIT     = 3.0;

    public function lectureHoursPerUnit(): float
    {
        $value = setting('Academic.lectureHoursPerUnit');

        return $value !== null ? (float) $value : self::DEFAULT_LECTURE_HOURS_PER_UNIT;
    }

    public function labHoursPerUnit(): float
    {
        $value = setting('Academic.labHoursPerUnit');

        return $value !== null ? (float) $value : self::DEFAULT_LAB_HOURS_PER_UNIT;
    }

    /**
     * @param array{
     *   units?: mixed,
     *   contact_hours?: mixed,
     *   is_laboratory?: mixed,
     *   lecture_units?: mixed,
     *   laboratory_units?: mixed,
     *   meetings_per_week?: mixed
     * } $subject
     */
    public function weeklyHoursFor(array $subject): float
    {
        $contactHours     = $this->numeric($subject['contact_hours'] ?? null);
        $meetingsPerWeek   = $this->numeric($subject['meetings_per_week'] ?? null);
        $lectureUnits      = $this->numeric($subject['lecture_units'] ?? null);
        $laboratoryUnits   = $this->numeric($subject['laboratory_units'] ?? null);
        $units             = $this->numeric($subject['units'] ?? null) ?? 0.0;
        $isLaboratory      = ! empty($subject['is_laboratory']);

        // Rule 1: explicit meeting length x explicit meeting count.
        if ($contactHours !== null && $meetingsPerWeek !== null) {
            return round($contactHours * $meetingsPerWeek, 1);
        }

        // Rule 2: explicit lecture/lab unit split.
        if ($lectureUnits !== null || $laboratoryUnits !== null) {
            $hours = ($lectureUnits ?? 0.0) * $this->lectureHoursPerUnit()
                   + ($laboratoryUnits ?? 0.0) * $this->labHoursPerUnit();

            return round($hours, 1);
        }

        // Rule 3: plain units + is_laboratory flag (the common case today).
        $rate = $isLaboratory ? $this->labHoursPerUnit() : $this->lectureHoursPerUnit();

        return round($units * $rate, 1);
    }

    private function numeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}

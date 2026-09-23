<?php

namespace App\Libraries;

/**
 * The catalog-shaped subject array the schedule-plotting UI consumes
 * (same fields as ChairpersonController::catalog()). Shared by the legacy
 * CurriculumPresetModel and the versioned-curriculum path so both always
 * produce an identical shape.
 */
class CatalogEntry
{
    /** Block accent colors, assigned per subject by a stable hash (mirrors ChairpersonController::COLORS). */
    private const COLORS = ['blue', 'purple', 'cyan', 'red', 'green', 'yellow'];

    /**
     * @param array $row   a `subjects` row
     * @param bool  $isLab the lab/lecture classification to show for it
     */
    public static function fromRow(array $row, bool $isLab): array
    {
        $units    = (float) $row['units'];
        $duration = (float) $row['contact_hours'];

        $fmt = static fn (float $n): string => $n == (int) $n ? (string) (int) $n : (string) $n;

        return [
            'subject_id'   => (int) $row['subject_id'],
            'code'         => $row['subject_code'],
            'title'        => $row['subject_name'],
            'units'        => $fmt($units) . ' Units',
            'hours'        => $fmt($duration) . ' hours',
            'duration'     => $duration,
            'weekly_hours' => (float) ($row['weekly_hours'] ?? 0),
            'is_lab'       => $isLab,
            'color'        => self::COLORS[array_sum(array_map('ord', str_split($row['subject_code']))) % count(self::COLORS)],
        ];
    }
}

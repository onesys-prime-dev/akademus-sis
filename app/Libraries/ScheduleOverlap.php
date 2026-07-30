<?php

namespace App\Libraries;

/**
 * Single shared time-overlap check for anything that plots or claims a
 * weekly meeting slot (chairperson schedule plotting, student enrollment
 * conflict checks). Extracted from ChairpersonController so the rule lives
 * in exactly one place.
 */
class ScheduleOverlap
{
    /**
     * True when two meetings on the SAME day overlap in time. Day equality
     * is the caller's responsibility (callers already have a `day` field
     * alongside `start`/`end`, and comparing three fields here would just
     * duplicate that check under a different name).
     *
     * @param array{start: string, end: string} $a 'HH:MM' (or 'HH:MM:SS') strings
     * @param array{start: string, end: string} $b
     */
    public static function overlaps(array $a, array $b): bool
    {
        return $a['start'] < $b['end'] && $b['start'] < $a['end'];
    }

    /**
     * True when two meetings clash: same day AND overlapping time.
     *
     * @param array{day: int, start: string, end: string} $a
     * @param array{day: int, start: string, end: string} $b
     */
    public static function clashes(array $a, array $b): bool
    {
        return $a['day'] === $b['day'] && self::overlaps($a, $b);
    }
}

<?php

namespace App\Libraries;

/**
 * Single shared rulebook for "is this a legal grade value under this scale"
 * and "does this value pass" — used by GradeModel when saving/approving a
 * grade and by any view that needs to render a pass/fail badge, so the rule
 * lives in exactly one place regardless of which of the three scale types
 * (numeric_gwa / percentage / letter) is active. Mirrors how ScheduleOverlap
 * centralizes one rule for both plotting and enrollment.
 */
class GradeScaleEvaluator
{
    /**
     * @param array $scale A row from grading_scales.
     */
    public static function isValidValue(array $scale, string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        if ($scale['type'] === 'letter') {
            return in_array($value, self::letterOptions($scale), true);
        }

        if (! is_numeric($value)) {
            return false;
        }

        $numeric = (float) $value;
        $min     = $scale['min_value'] !== null ? (float) $scale['min_value'] : null;
        $max     = $scale['max_value'] !== null ? (float) $scale['max_value'] : null;

        if ($min !== null && $numeric < $min) {
            return false;
        }

        if ($max !== null && $numeric > $max) {
            return false;
        }

        return true;
    }

    /**
     * Null when the scale has no passing rule configured for its type (e.g.
     * a numeric scale with no passing_value set) — callers should treat that
     * as "unknown," not as failing.
     */
    public static function isPassing(array $scale, string $value): ?bool
    {
        if ($scale['type'] === 'letter') {
            $failing = self::failingLetters($scale);

            return $failing === [] ? null : ! in_array($value, $failing, true);
        }

        if ($scale['passing_value'] === null || ! is_numeric($value)) {
            return null;
        }

        $numeric  = (float) $value;
        $passing  = (float) $scale['passing_value'];
        $isLower  = (bool) $scale['is_lower_better'];

        return $isLower ? $numeric <= $passing : $numeric >= $passing;
    }

    /**
     * @return list<string>
     */
    public static function letterOptions(array $scale): array
    {
        return self::splitList($scale['letter_options'] ?? null);
    }

    /**
     * @return list<string>
     */
    public static function failingLetters(array $scale): array
    {
        return self::splitList($scale['failing_letters'] ?? null);
    }

    /**
     * @return list<string>
     */
    private static function splitList(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn (string $v) => $v !== ''));
    }
}

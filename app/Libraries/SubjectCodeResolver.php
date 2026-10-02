<?php

namespace App\Libraries;

/**
 * Turns a set of `subjects` rows matched by subject_code into a decision,
 * without ever guessing. subjects.subject_code carries a database-level
 * UNIQUE constraint, so in practice a lookup is always exactly-one-or-none —
 * but this still counts explicitly instead of assuming, so the "never
 * silently choose one" rule holds even if that constraint is ever relaxed.
 */
class SubjectCodeResolver
{
    public const MATCHED   = 'matched';
    public const UNMATCHED = 'unmatched';
    public const AMBIGUOUS = 'ambiguous';

    /**
     * @param list<array{subject_id: int|string}> $matches rows from `subjects` filtered by subject_code
     *
     * @return array{status: self::MATCHED|self::UNMATCHED|self::AMBIGUOUS, subject_id: ?int}
     */
    public static function resolve(array $matches): array
    {
        if (count($matches) === 0) {
            return ['status' => self::UNMATCHED, 'subject_id' => null];
        }

        if (count($matches) > 1) {
            return ['status' => self::AMBIGUOUS, 'subject_id' => null];
        }

        return ['status' => self::MATCHED, 'subject_id' => (int) $matches[0]['subject_id']];
    }
}

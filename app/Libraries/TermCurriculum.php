<?php

namespace App\Libraries;

use App\Models\CurriculumModel;
use App\Models\DepartmentModel;

/**
 * Resolves "what does this cohort take this term?" from the program's one
 * (always-live) curriculum — the source of truth — instead of from a
 * hand-edited per-term preset.
 *
 * The plotting screens speak in display strings (academic_year =
 * "AY 2025 - 2026 · 1st Semester", program = "BS in Information Technology",
 * year_level = "1st Year"); the curriculum speaks in ids and integers
 * (program_id, year_level 1-4, semester 1/2/3). This class is the one place
 * that translates between them.
 *
 * forContext() returns null when the program has no curriculum at all yet —
 * the program/term simply offers nothing until one is created.
 */
class TermCurriculum
{
    /** Semester label used in academic_years.semester => curriculum_subjects.semester */
    public const SEMESTERS = ['1st' => 1, '2nd' => 2, 'Summer' => 3];

    /**
     * "AY 2025 - 2026 · 1st Semester" => 1. Null when the string carries no
     * recognisable semester (legacy year-only values).
     */
    public static function semesterOf(string $academicYear): ?int
    {
        if (preg_match('/·\s*(1st|2nd|Summer)\s+Semester\s*$/u', $academicYear, $m) !== 1) {
            return null;
        }

        return self::SEMESTERS[$m[1]];
    }

    /** "3rd Year" => 3; null if unrecognised. */
    public static function yearLevelOf(string $yearLevel): ?int
    {
        return preg_match('/^([1-9])(?:st|nd|rd|th)\s+Year$/i', trim($yearLevel), $m) === 1 ? (int) $m[1] : null;
    }

    /** Display name (as stored on sections/plotted_schedules) => programs.program_id. */
    public static function programIdOf(string $program): ?int
    {
        return DepartmentModel::programIdOfName($program);
    }

    /**
     * The one curriculum for a program display name (auto-created on first
     * use), or null when the program name itself doesn't resolve.
     */
    public function curriculumFor(string $program): ?array
    {
        $programId = self::programIdOf($program);

        return $programId === null ? null : (new CurriculumModel())->liveFor($programId);
    }

    /**
     * @return array{curriculum: array, subjects: list<array>}|null null =
     *         the program name doesn't resolve to a program at all. An
     *         empty `subjects` list is authoritative: the curriculum simply
     *         offers nothing in that term yet.
     */
    public function forContext(string $academicYear, string $program, string $yearLevel): ?array
    {
        $semester = self::semesterOf($academicYear);
        $level    = self::yearLevelOf($yearLevel);

        if ($semester === null || $level === null) {
            return null;
        }

        $curriculum = $this->curriculumFor($program);

        if ($curriculum === null) {
            return null;
        }

        return [
            'curriculum' => $curriculum,
            'subjects'   => (new CurriculumModel())->termSubjects((int) $curriculum['curriculum_id'], $level, $semester),
        ];
    }
}

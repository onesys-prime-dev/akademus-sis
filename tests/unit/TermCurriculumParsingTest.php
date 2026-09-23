<?php

use App\Libraries\TermCurriculum;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class TermCurriculumParsingTest extends CIUnitTestCase
{
    public function testSemesterIsParsedFromTheDisplayString(): void
    {
        $this->assertSame(1, TermCurriculum::semesterOf('AY 2025 - 2026 · 1st Semester'));
        $this->assertSame(2, TermCurriculum::semesterOf('AY 2025 - 2026 · 2nd Semester'));
        $this->assertSame(3, TermCurriculum::semesterOf('AY 2025 - 2026 · Summer Semester'));
    }

    public function testYearOnlyOrUnknownTermsHaveNoSemester(): void
    {
        $this->assertNull(TermCurriculum::semesterOf('AY 2025 - 2026'));
        $this->assertNull(TermCurriculum::semesterOf('AY 2025 - 2026 · 3rd Semester'));
        $this->assertNull(TermCurriculum::semesterOf(''));
    }

    public function testYearLevelIsParsed(): void
    {
        $this->assertSame(1, TermCurriculum::yearLevelOf('1st Year'));
        $this->assertSame(2, TermCurriculum::yearLevelOf('2nd Year'));
        $this->assertSame(3, TermCurriculum::yearLevelOf('3rd Year'));
        $this->assertSame(4, TermCurriculum::yearLevelOf('4th Year'));
        $this->assertNull(TermCurriculum::yearLevelOf('Freshman'));
    }
}

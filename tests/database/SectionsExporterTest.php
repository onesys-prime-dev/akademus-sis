<?php

use App\Libraries\SectionsExporter;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SectionsExporterTest extends CIUnitTestCase
{
    public function testBuildWritesOneRowPerSectionWithBlankCapacityForUnlimited(): void
    {
        $spreadsheet = SectionsExporter::build(
            [
                ['program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'capacity' => 40, 'status' => 'open'],
                ['program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1B', 'capacity' => null, 'status' => 'locked'],
            ],
            []
        );

        $sheet = $spreadsheet->getSheetByName('Sections');
        $this->assertSame(['Program', 'Year Level', 'Section', 'Capacity', 'Status'], $sheet->rangeToArray('A1:E1', null, true, false)[0]);
        $this->assertSame(['BSIT', '1st Year', '1A', 40, 'open'], $sheet->rangeToArray('A2:E2', null, true, false)[0]);
        $this->assertSame(['BSIT', '1st Year', '1B', null, 'locked'], $sheet->rangeToArray('A3:E3', null, true, false)[0]);
    }

    public function testBuildWritesOneRowPerRosterEntryAndHeaderOnlyWhenEmpty(): void
    {
        $spreadsheet = SectionsExporter::build(
            [['program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'capacity' => 40, 'status' => 'open']],
            [['program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'student_number' => 'STU-2026-00001', 'student_name' => 'Jane Dela Cruz', 'email' => 'jane@example.com']]
        );

        $roster = $spreadsheet->getSheetByName('Roster');
        $this->assertSame(['Program', 'Year Level', 'Section', 'Student Number', 'Student Name', 'Email'], $roster->rangeToArray('A1:F1')[0]);
        $this->assertSame(['BSIT', '1st Year', '1A', 'STU-2026-00001', 'Jane Dela Cruz', 'jane@example.com'], $roster->rangeToArray('A2:F2')[0]);

        $empty = SectionsExporter::build([], []);
        $this->assertNull($empty->getSheetByName('Roster')->rangeToArray('A2:F2')[0][0]);
    }

    public function testToXlsxStringProducesReadableWorkbookBytes(): void
    {
        $spreadsheet = SectionsExporter::build([['program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'capacity' => 40, 'status' => 'open']], []);
        $bytes = SectionsExporter::toXlsxString($spreadsheet);

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $bytes);
        $reloaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
        unlink($tmp);

        $this->assertSame('BSIT', $reloaded->getSheetByName('Sections')->getCell('A2')->getValue());
    }
}

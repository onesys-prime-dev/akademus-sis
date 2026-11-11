<?php

use App\Libraries\SectionsImportParser;
use CodeIgniter\Test\CIUnitTestCase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * @internal
 */
final class SectionsImportParserTest extends CIUnitTestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function testParseReturnsRowsFromBothSheets(): void
    {
        $path = $this->fixtureWorkbook(
            sections: [['BSIT', '1st Year', '1A', '40']],
            roster: [['BSIT', '1st Year', '1A', 'STU-2026-00001', 'Kyle', 'D', 'Esta', 'kyle@example.com']]
        );

        $result = SectionsImportParser::parse($path);

        $this->assertTrue($result['ok']);
        $this->assertSame([['row' => 2, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'capacity' => '40']], $result['sections']);
        $this->assertSame([[
            'row'            => 2,
            'program'        => 'BSIT',
            'year_level'     => '1st Year',
            'section'        => '1A',
            'student_number' => 'STU-2026-00001',
            'first_name'     => 'Kyle',
            'middle_name'    => 'D',
            'last_name'      => 'Esta',
            'email'          => 'kyle@example.com',
        ]], $result['roster']);
    }

    public function testParseRejectsAWrongHeaderShape(): void
    {
        $path = $this->fixtureWorkbookWithRawHeader(['Program', 'Section']);
        $result = SectionsImportParser::parse($path);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString("columns don't match", $result['error']);
    }

    public function testParseRejectsAFileThatIsNotARealWorkbook(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'notxlsx');
        $this->tempFiles[] = $path;
        file_put_contents($path, 'this is not a zip file');

        $result = SectionsImportParser::parse($path);

        $this->assertFalse($result['ok']);
    }

    public function testParseRejectsAnEmptyFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'empty') . '.xlsx';
        $this->tempFiles[] = $path;
        file_put_contents($path, '');

        $result = SectionsImportParser::parse($path);

        $this->assertFalse($result['ok']);
    }

    public function testParseKeepsBlankCapacityAsNullNotEmptyString(): void
    {
        $path = $this->fixtureWorkbook(sections: [['BSIT', '1st Year', '1A', '']], roster: []);
        $result = SectionsImportParser::parse($path);
        $this->assertNull($result['sections'][0]['capacity']);
    }

    /**
     * @param list<list<string>> $sections
     * @param list<list<string>> $roster
     */
    private function fixtureWorkbook(array $sections, array $roster): string
    {
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sections');
        $sheet->fromArray(['Program', 'Year Level', 'Section', 'Capacity', 'Status'], null, 'A1');
        $sheet->fromArray($sections, null, 'A2');

        $rosterSheet = $spreadsheet->createSheet();
        $rosterSheet->setTitle('Roster');
        $rosterSheet->fromArray(['Program', 'Year Level', 'Section', 'Student Number', 'First Name', 'Middle Name', 'Last Name', 'Email'], null, 'A1');
        $rosterSheet->fromArray($roster, null, 'A2');

        return $this->saveFixture($spreadsheet);
    }

    /** @param list<string> $header */
    private function fixtureWorkbookWithRawHeader(array $header): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setTitle('Sections');
        $spreadsheet->getActiveSheet()->fromArray($header, null, 'A1');
        $rosterSheet = $spreadsheet->createSheet();
        $rosterSheet->setTitle('Roster');
        $rosterSheet->fromArray(['Program', 'Year Level', 'Section', 'Student Number', 'First Name', 'Middle Name', 'Last Name', 'Email'], null, 'A1');

        return $this->saveFixture($spreadsheet);
    }

    private function saveFixture(Spreadsheet $spreadsheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import_fixture_') . '.xlsx';
        $this->tempFiles[] = $path;
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}

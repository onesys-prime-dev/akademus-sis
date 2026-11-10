<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the two-sheet "Sections" / "Roster" workbook the Sections page's
 * Export button downloads. Pure: takes plain arrays, returns a Spreadsheet
 * (or its serialized bytes) — no database access, no HTTP.
 */
class SectionsExporter
{
    /**
     * @param list<array{program: string, year_level: string, section: string, capacity: ?int, status: string}> $sections
     * @param list<array{program: string, year_level: string, section: string, student_number: string, student_name: string}> $rosterRows
     */
    public static function build(array $sections, array $rosterRows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        $sectionsSheet = $spreadsheet->getActiveSheet();
        $sectionsSheet->setTitle('Sections');
        $sectionsSheet->fromArray(['Program', 'Year Level', 'Section', 'Capacity', 'Status'], null, 'A1');

        $sectionRows = array_map(
            static fn (array $s): array => [$s['program'], $s['year_level'], $s['section'], $s['capacity'], $s['status']],
            $sections
        );
        $sectionsSheet->fromArray($sectionRows, null, 'A2');

        $rosterSheet = $spreadsheet->createSheet();
        $rosterSheet->setTitle('Roster');
        $rosterSheet->fromArray(['Program', 'Year Level', 'Section', 'Student Number', 'Student Name'], null, 'A1');

        $rosterData = array_map(
            static fn (array $r): array => [$r['program'], $r['year_level'], $r['section'], $r['student_number'], $r['student_name']],
            $rosterRows
        );
        $rosterSheet->fromArray($rosterData, null, 'A2');

        return $spreadsheet;
    }

    public static function toXlsxString(Spreadsheet $spreadsheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sections_export_');

        try {
            (new Xlsx($spreadsheet))->save($path);

            return file_get_contents($path);
        } finally {
            unlink($path);
        }
    }
}

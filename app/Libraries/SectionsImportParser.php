<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Turns an uploaded Sections/Roster .xlsx workbook into plain row arrays.
 * Pure: takes a file path, returns arrays — no database access, no HTTP.
 */
class SectionsImportParser
{
    private const SECTIONS_HEADER = ['Program', 'Year Level', 'Section', 'Capacity'];
    private const ROSTER_HEADER   = ['Program', 'Year Level', 'Section', 'Student Number', 'First Name', 'Middle Name', 'Last Name', 'Email'];

    /**
     * @return array{ok: true, sections: list<array{row: int, program: ?string, year_level: ?string, section: ?string, capacity: ?string}>, roster: list<array{row: int, program: ?string, year_level: ?string, section: ?string, student_number: ?string, first_name: ?string, middle_name: ?string, last_name: ?string, email: ?string}>}|array{ok: false, error: string}
     */
    public static function parse(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Could not read that file as an Excel workbook.'];
        }

        $sectionsSheet = $spreadsheet->getSheetByName('Sections');
        $rosterSheet   = $spreadsheet->getSheetByName('Roster');

        if ($sectionsSheet === null || $rosterSheet === null) {
            return ['ok' => false, 'error' => 'Expected a "Sections" sheet and a "Roster" sheet.'];
        }

        if (! self::headerMatches($sectionsSheet, self::SECTIONS_HEADER) || ! self::headerMatches($rosterSheet, self::ROSTER_HEADER)) {
            return ['ok' => false, 'error' => "That sheet's columns don't match the expected template."];
        }

        return [
            'ok'       => true,
            'sections' => self::dataRows($sectionsSheet, ['program', 'year_level', 'section', 'capacity']),
            'roster'   => self::dataRows($rosterSheet, ['program', 'year_level', 'section', 'student_number', 'first_name', 'middle_name', 'last_name', 'email']),
        ];
    }

    /** @param list<string> $expected */
    private static function headerMatches(Worksheet $sheet, array $expected): bool
    {
        $count = count($expected);
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($count);
        $header = $sheet->rangeToArray("A1:{$lastColumn}1", null, true, false)[0];

        foreach ($expected as $i => $label) {
            if (($header[$i] ?? null) !== $label) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $fields
     *
     * @return list<array<string, int|string|null>>
     */
    private static function dataRows(Worksheet $sheet, array $fields): array
    {
        $count = count($fields);
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($count);
        $highestRow = $sheet->getHighestDataRow();

        $rows = [];

        for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
            $values = $sheet->rangeToArray("A{$rowNum}:{$lastColumn}{$rowNum}", null, true, false)[0];

            if (self::isBlankRow($values)) {
                continue;
            }

            $row = ['row' => $rowNum];

            foreach ($fields as $i => $field) {
                $value    = $values[$i] ?? null;
                $row[$field] = ($value === null || $value === '') ? null : (string) $value;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private static function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}

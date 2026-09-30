<?php

namespace App\Database\Seeds;

use App\Models\FeeTypeModel;
use App\Models\ProgramFeeRateModel;
use App\Models\ProgramModel;
use App\Models\TuitionRateModel;
use App\Services\TuitionAssessmentService;
use CodeIgniter\Database\Seeder;

/**
 * DEV-ONLY. Populates a real, testable fee catalog and per-program rate
 * sheets, then (a) runs SISDevelopmentSeeder — idempotent, safe to re-run —
 * to make sure a real, sectioned, enrolled student roster actually exists
 * (an assessment is only ever produced as a side effect of a real
 * enrollment change), and (b) explicitly recomputes every enrolled
 * student's assessment against the rates just seeded, so this seeder's
 * effect is visible immediately rather than only on the student's next
 * enrollment change.
 *
 * Fee names here are generic placeholders, not any real institution's
 * actual configuration — replace them via Admin/Registrar -> Fee Types once
 * real figures are available.
 *
 * Safe to run repeatedly: every write path here is idempotent.
 *
 *   php spark db:seed TuitionDevelopmentSeeder
 */
class TuitionDevelopmentSeeder extends Seeder
{
    /** code => [category, name, sort_order] */
    private const CATALOG = [
        'LIBRARY'      => ['miscellaneous', 'Library Fee', 1],
        'GUIDANCE'     => ['miscellaneous', 'Guidance Office Fee', 2],
        'CULTURAL'     => ['miscellaneous', 'Cultural Affairs Fee', 3],
        'TESTING'      => ['miscellaneous', 'Testing Services Fee', 4],
        'REGISTRATION' => ['miscellaneous', 'Registration Fee', 5],

        'EDTECH'       => ['other', 'Educational Technology Fee', 1],
        'ID_CARD'      => ['other', 'ID Validation Fee', 2],
        'INST_DEV'     => ['other', 'Institutional Development Fee', 3],
        'INSURANCE'    => ['other', 'Insurance Fee', 4],
        'HEALTH'       => ['other', 'Health Services Fee', 5],
        'SECURITY'     => ['other', 'Security Services Fee', 6],
        'STUDENT_DEV'  => ['other', 'Student Development Fee', 7],

        'RETREAT'      => ['additional', 'Retreat / Recollection Fee', 1],
        'DIGITAL_LIB'  => ['additional', 'Digital Library Fee', 2],
        'INTERNET'     => ['additional', 'Internet Fee', 3],
        'ATHLETICS'    => ['additional', 'Athletics Fee', 4],
        'PUBLICATION'  => ['additional', 'Student Publication Fee', 5],
        'DEV_FUND'     => ['additional', 'Program Development Fund', 6],
        'SPIRITUAL'    => ['additional', 'Spiritual Development Fee', 7],
        // Program-specific example — priced only for BSHM below, to show
        // different programs can carry entirely different fee sets.
        'CULINARY_LAB' => ['additional', 'Culinary Laboratory Fee', 8],
    ];

    /** program_code => [per_unit_rate, installment_count] */
    private const PROGRAM_PLANS = [
        'BSCS' => ['per_unit_rate' => 550, 'installments' => 5],
        'BSIT' => ['per_unit_rate' => 500, 'installments' => 5],
        'BSHM' => ['per_unit_rate' => 450, 'installments' => 5],
    ];

    /** code => amount — shared across every program that charges it. */
    private const AMOUNTS = [
        'LIBRARY'      => 900,
        'GUIDANCE'     => 500,
        'CULTURAL'     => 300,
        'TESTING'      => 450,
        'REGISTRATION' => 800,
        'EDTECH'       => 900,
        'ID_CARD'      => 150,
        'INST_DEV'     => 1200,
        'INSURANCE'    => 150,
        'HEALTH'       => 600,
        'SECURITY'     => 500,
        'STUDENT_DEV'  => 1000,
        'RETREAT'      => 1500,
        'DIGITAL_LIB'  => 400,
        'INTERNET'     => 900,
        'ATHLETICS'    => 500,
        'PUBLICATION'  => 300,
        'DEV_FUND'     => 500,
        'SPIRITUAL'    => 200,
        'CULINARY_LAB' => 3500,
    ];

    private const ACADEMIC_YEARS = [
        'AY 2025 - 2026 · 1st Semester',
        'AY 2025 - 2026 · 2nd Semester',
        'AY 2026 - 2027 · 1st Semester',
    ];

    public function run()
    {
        $feeTypeIds = $this->ensureCatalog();
        $this->ensureRateSheets($feeTypeIds);

        echo "\nRunning SISDevelopmentSeeder to make sure a sectioned, enrolled roster exists (idempotent — safe if already seeded)...\n";
        $this->call('SISDevelopmentSeeder');

        echo "\nRecomputing every enrolled student's tuition assessment against the seeded rates...\n";
        $this->recomputeAllEnrolled();

        echo "\nDone. Check Admin/Registrar -> Tuition Rates / Fee Types, and a seeded student's own Tuition & Fees page.\n";
    }

    /** @return array<string,int> fee_type code => fee_type_id */
    private function ensureCatalog(): array
    {
        $feeTypes = new FeeTypeModel();
        $ids      = [];

        foreach (self::CATALOG as $code => [$category, $name, $sortOrder]) {
            $existing = $feeTypes->where('code', $code)->first();

            if ($existing !== null) {
                $ids[$code] = (int) $existing['fee_type_id'];
                continue;
            }

            $feeTypes->insert([
                'category'   => $category,
                'code'       => $code,
                'name'       => $name,
                'sort_order' => $sortOrder,
            ]);
            $ids[$code] = $feeTypes->getInsertID();
            echo "Added fee type: {$name} ({$category}).\n";
        }

        return $ids;
    }

    private function ensureRateSheets(array $feeTypeIds): void
    {
        $programs      = new ProgramModel();
        $tuitionRates  = new TuitionRateModel();
        $programFees   = new ProgramFeeRateModel();

        foreach (self::PROGRAM_PLANS as $programCode => $plan) {
            $program = $programs->where('program_code', $programCode)->first();

            if ($program === null) {
                echo "Program {$programCode} not found — skipping (create it first in Admin -> Programs).\n";
                continue;
            }

            $programId = (int) $program['program_id'];
            $codesForProgram = array_keys(self::AMOUNTS);

            // The example program-specific fee: only BSHM gets it charged.
            if ($programCode !== 'BSHM') {
                $codesForProgram = array_diff($codesForProgram, ['CULINARY_LAB']);
            }

            foreach (self::ACADEMIC_YEARS as $academicYear) {
                $existingRate = $tuitionRates->forProgramYear($programId, $academicYear);

                if ($existingRate === null) {
                    $tuitionRates->insert([
                        'program_id'        => $programId,
                        'academic_year'     => $academicYear,
                        'per_unit_rate'      => $plan['per_unit_rate'],
                        'installment_count' => $plan['installments'],
                    ]);
                    echo "Set tuition rate for {$programCode} / {$academicYear}: {$plan['per_unit_rate']} per unit, {$plan['installments']} installments.\n";
                }

                foreach ($codesForProgram as $code) {
                    $feeTypeId = $feeTypeIds[$code];
                    $existingFee = $programFees->where('program_id', $programId)
                        ->where('academic_year', $academicYear)
                        ->where('fee_type_id', $feeTypeId)
                        ->first();

                    if ($existingFee !== null) {
                        continue;
                    }

                    $programFees->insert([
                        'program_id'    => $programId,
                        'academic_year' => $academicYear,
                        'fee_type_id'   => $feeTypeId,
                        'amount'        => self::AMOUNTS[$code],
                    ]);
                }

                echo "Priced " . count($codesForProgram) . " fee(s) for {$programCode} / {$academicYear}.\n";
            }
        }
    }

    private function recomputeAllEnrolled(): void
    {
        $rows = db_connect()->table('enrollments e')
            ->select('s.student_id, sec.academic_year')
            ->distinct()
            ->join('sections sec', 'sec.section_id = e.section_id')
            ->join('students s', 's.user_id = e.user_id')
            ->where('e.status', 'enrolled')
            ->get()
            ->getResultArray();

        $service = new TuitionAssessmentService();

        foreach ($rows as $row) {
            $result = $service->recompute((int) $row['student_id'], $row['academic_year']);
            $status = $result['assessment']['status'] ?? 'error';
            echo "  student #{$row['student_id']} / {$row['academic_year']}: total " . number_format((float) ($result['assessment']['total_amount'] ?? 0), 2) . " ({$status})\n";
        }
    }
}

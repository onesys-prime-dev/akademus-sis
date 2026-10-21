<?php

use App\Models\CurriculumModel;
use App\Models\ProgramModel;
use App\Models\SectionModel;
use App\Models\StudentModel;
use App\Services\TuitionAssessmentService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * A student with no real enrollment must still see a CURRICULUM-based
 * tuition estimate from day one; real enrollment then supersedes it. The
 * estimate is marked is_estimated, and must never carry an installment
 * schedule built from amounts that are still only preliminary.
 *
 * @internal
 */
final class TuitionEstimateTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private const YEAR    = 'AY 2026 - 2027';
    private const TERM    = self::YEAR . ' · 1st Semester';
    private const USER_ID = 201;
    private const PROGRAM = 'BS IT';

    private int $studentId;
    private int $sectionId;

    protected function setUp(): void
    {
        parent::setUp();

        db_connect()->table('academic_years')->insert([
            'label'      => '2026-2027',
            'semester'   => '1st',
            'start_date' => '2026-08-03',
            'end_date'   => '2026-12-18',
            'is_active'  => 1,
        ]);

        $programs = new ProgramModel();
        $programs->insert(['program_code' => 'BSIT', 'program_name' => self::PROGRAM, 'is_active' => 1]);
        $programId = (int) $programs->getInsertID();

        // The cohort is 2nd Year, while the curriculum below only has
        // 1st-Year subjects — the exact "curriculum still being built out"
        // case the nearest-populated-level fallback exists for.
        $sections = new SectionModel();
        $sections->insert([
            'academic_year' => self::YEAR,
            'program'       => self::PROGRAM,
            'year_level'    => '2nd Year',
            'section'       => 'A',
            'capacity'      => null,
        ]);
        $this->sectionId = (int) $sections->getInsertID();

        $curriculumId = (int) (new CurriculumModel())->liveFor($programId)['curriculum_id'];

        // 2 subjects x 3 units, seeded only at 1st Year / 1st Semester.
        foreach (['IT101', 'IT102'] as $code) {
            db_connect()->table('subjects')->insert([
                'subject_code' => $code,
                'subject_name' => $code . ' Title',
                'units'        => 3,
                'is_active'    => 1,
            ]);

            db_connect()->table('curriculum_subjects')->insert([
                'curriculum_id' => $curriculumId,
                'subject_id'    => db_connect()->insertID(),
                'year_level'    => 1,
                'semester'      => 1,
                'is_required'   => 1,
                'is_active'     => 1,
            ]);
        }

        db_connect()->table('tuition_rates')->insert([
            'program_id'        => $programId,
            'academic_year'     => self::TERM,
            'per_unit_rate'     => 500,
            'installment_count' => 5,
        ]);

        $this->seedUsers(self::USER_ID);
        db_connect()->table('users')->where('id', self::USER_ID)->update(['section_id' => $this->sectionId]);

        $students = new StudentModel();
        $students->insert([
            'user_id'        => self::USER_ID,
            'applicant_id'   => 'TEST-ESTIMATE-1',
            'student_number' => 'STU-2026-00001',
            'program_id'     => $programId,
            'curriculum_id'  => $curriculumId,
            'admission_date' => date('Y-m-d H:i:s'),
            'student_status' => 'active',
        ]);
        $this->studentId = (int) $students->getInsertID();
    }

    public function testNoEnrollmentYieldsCurriculumEstimateWithoutASchedule(): void
    {
        $result = (new TuitionAssessmentService())->recompute($this->studentId, self::TERM);

        $this->assertTrue($result['ok']);
        $assessment = $result['assessment'];

        $this->assertSame(6.0, (float) $assessment['total_units'], 'the empty 2nd-Year level borrows the nearest populated 1st-Year load');
        $this->assertSame(1, (int) $assessment['is_estimated'], 'a curriculum-derived figure must say so');
        $this->assertSame(3000.0, (float) $assessment['tuition_amount']);
        $this->assertSame(3000.0, (float) $assessment['total_amount']);
        $this->assertSame('unpaid', $assessment['status']);

        $this->assertSame(
            0,
            db_connect()->table('payment_schedules')->where('assessment_id', $assessment['assessment_id'])->countAllResults(),
            'a preliminary estimate must not lock in an installment plan'
        );
    }

    public function testRealEnrollmentSupersedesEstimateAndCreatesTheSchedule(): void
    {
        (new TuitionAssessmentService())->recompute($this->studentId, self::TERM);

        db_connect()->table('enrollments')->insert([
            'user_id'       => self::USER_ID,
            'section_id'    => $this->sectionId,
            'academic_year' => self::TERM,
            'offering_id'   => null,
            'subject_code'  => 'IT101',
            'method'        => 'block',
            'status'        => 'enrolled',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $assessment = (new TuitionAssessmentService())->recompute($this->studentId, self::TERM)['assessment'];

        $this->assertSame(3.0, (float) $assessment['total_units']);
        $this->assertSame(0, (int) $assessment['is_estimated'], 'real enrollment must clear the estimate flag');
        $this->assertSame(1500.0, (float) $assessment['tuition_amount']);
        $this->assertSame(
            5,
            db_connect()->table('payment_schedules')->where('assessment_id', $assessment['assessment_id'])->countAllResults(),
            'the first real assessment generates the configured installment plan'
        );

        // A second subject enrolls (block enrollment recomputes after each
        // one): the unpaid plan must be rebuilt so its installments still
        // add up to the final bill, not the first subject's fraction of it.
        db_connect()->table('enrollments')->insert([
            'user_id'       => self::USER_ID,
            'section_id'    => $this->sectionId,
            'academic_year' => self::TERM,
            'offering_id'   => null,
            'subject_code'  => 'IT102',
            'method'        => 'block',
            'status'        => 'enrolled',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $assessment = (new TuitionAssessmentService())->recompute($this->studentId, self::TERM)['assessment'];

        $this->assertSame(6.0, (float) $assessment['total_units']);
        $this->assertSame(3000.0, (float) $assessment['tuition_amount']);
        $this->assertSame(
            3000.0,
            (float) db_connect()->table('payment_schedules')->selectSum('amount', 'total')
                ->where('assessment_id', $assessment['assessment_id'])->get()->getRowArray()['total'],
            'the unpaid plan follows the final multi-subject total'
        );

        // Back to zero enrollment: the estimate returns and the now-stale
        // plan must go with it rather than keep billing the old total.
        db_connect()->table('enrollments')->where('user_id', self::USER_ID)->delete();

        $assessment = (new TuitionAssessmentService())->recompute($this->studentId, self::TERM)['assessment'];

        $this->assertSame(1, (int) $assessment['is_estimated']);
        $this->assertSame(6.0, (float) $assessment['total_units']);
        $this->assertSame(
            0,
            db_connect()->table('payment_schedules')->where('assessment_id', $assessment['assessment_id'])->countAllResults()
        );
    }
}

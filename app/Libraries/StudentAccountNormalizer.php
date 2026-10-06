<?php

namespace App\Libraries;

use App\Models\CurriculumModel;
use App\Models\DepartmentModel;
use App\Models\EnrollmentModel;
use App\Models\SectionModel;
use App\Models\StudentModel;
use App\Models\TuitionAssessmentModel;
use App\Services\TuitionAssessmentService;

/**
 * Brings a pre-existing `users` account (student group) that predates the
 * Admissions -> SIS handoff up to current system behavior, reusing the
 * SAME models/services a real promotion/enrollment/billing flow uses —
 * never hand-computed totals, never a parallel code path.
 *
 * What this does NOT do: create new user accounts, touch anyone outside
 * the `student` Shield group, or fabricate a real Admissions applicant_id.
 * A legacy account has no real applicant record by definition — its
 * students.applicant_id is stamped 'LEGACY-{user_id}', a value that can
 * never collide with (or be mistaken for) a real Admissions ID, so this
 * stays honestly distinguishable rather than pretending to be promoted
 * data. student_number, by contrast, has always been an SIS-generated
 * value (promoteFromApplicant() generates it too, not Admissions) — the
 * exact same STU-{year}-{seq} generator is reused here, so legacy and
 * freshly-promoted students are indistinguishable on that front.
 */
class StudentAccountNormalizer
{
    public const STATUS_OK          = 'ok';
    public const STATUS_REPAIRABLE  = 'repairable';
    public const STATUS_AMBIGUOUS   = 'ambiguous';
    public const STATUS_NOT_STUDENT = 'not_student';

    /**
     * Classifies one user without changing anything.
     *
     * @return array{status: string, reasons: list<string>, user: array}
     */
    public function classify(int $userId): array
    {
        $db   = db_connect();
        $user = $db->table('users')->where('id', $userId)->where('deleted_at', null)->get()->getRowArray();

        if ($user === null) {
            return ['status' => self::STATUS_NOT_STUDENT, 'reasons' => ['User not found or deleted.'], 'user' => []];
        }

        $isStudent = $db->table('auth_groups_users')->where('user_id', $userId)->where('group', 'student')->countAllResults() > 0;

        if (! $isStudent) {
            return ['status' => self::STATUS_NOT_STUDENT, 'reasons' => ['Not in the student group.'], 'user' => $user];
        }

        $reasons = [];
        $student = (new StudentModel())->where('user_id', $userId)->first();

        if ($student === null) {
            if (empty($user['section_id'])) {
                return ['status' => self::STATUS_AMBIGUOUS, 'reasons' => ['No students row, and no section_id to derive a program from.'], 'user' => $user];
            }

            $section = (new SectionModel())->find((int) $user['section_id']);

            if ($section === null) {
                return ['status' => self::STATUS_AMBIGUOUS, 'reasons' => ['section_id points at a section that no longer exists.'], 'user' => $user];
            }

            $programId = DepartmentModel::programIdOfName($section['program']);

            if ($programId === null) {
                return ['status' => self::STATUS_AMBIGUOUS, 'reasons' => ["Section's program \"{$section['program']}\" doesn't resolve to a real programs row."], 'user' => $user];
            }

            $reasons[] = 'Missing students row — derivable from section ' . $section['program'] . ' / ' . $section['year_level'] . ' / ' . $section['section'] . '.';
        } else {
            $section = empty($user['section_id']) ? null : (new SectionModel())->find((int) $user['section_id']);
        }

        if ($section !== null) {
            $missingCount = $this->missingSubjectCodes($userId, $section);

            if ($missingCount !== []) {
                $reasons[] = count($missingCount) . ' plotted subject(s) not enrolled: ' . implode(', ', $missingCount) . '.';
            }

            if ($student !== null) {
                $assessment = (new TuitionAssessmentModel())->forStudentYear((int) $student['student_id'], $section['academic_year']);

                if ($assessment === null) {
                    $reasons[] = 'No tuition assessment for ' . $section['academic_year'] . '.';
                }
            }
        }

        return [
            'status'  => $reasons === [] ? self::STATUS_OK : self::STATUS_REPAIRABLE,
            'reasons' => $reasons,
            'user'    => $user,
        ];
    }

    /**
     * Repairs one user, reusing StudentModel/EnrollmentModel/
     * TuitionAssessmentService exactly as a live promotion/enrollment/
     * payment flow would. Refuses anything classify() didn't call
     * 'repairable' or already-'ok' — an 'ambiguous' account is never
     * touched by this method, by design.
     *
     * @return array{ok: bool, error?: string, steps: list<string>}
     */
    public function normalize(int $userId, bool $commit): array
    {
        $classification = $this->classify($userId);
        $steps = [];

        if (! in_array($classification['status'], [self::STATUS_OK, self::STATUS_REPAIRABLE], true)) {
            return ['ok' => false, 'error' => 'Not repairable: ' . implode(' ', $classification['reasons'] ?: ['unknown']), 'steps' => []];
        }

        $user = $classification['user'];
        $studentModel = new StudentModel();
        $student = $studentModel->where('user_id', $userId)->first();

        if ($student === null) {
            $section = (new SectionModel())->find((int) $user['section_id']);
            $programId = DepartmentModel::programIdOfName($section['program']);
            $curriculum = (new CurriculumModel())->liveFor($programId);

            $steps[] = "Create students row: program_id={$programId}, curriculum_id={$curriculum['curriculum_id']}.";

            if ($commit) {
                [$schoolYear, $semester] = $this->splitAcademicYear($section['academic_year']);

                $studentModel->insert([
                    'user_id'              => $userId,
                    'applicant_id'         => 'LEGACY-' . $userId,
                    'student_number'       => $this->nextStudentNumber(),
                    'program_id'           => $programId,
                    'curriculum_id'        => (int) $curriculum['curriculum_id'],
                    'admission_date'       => date('Y-m-d H:i:s'),
                    'student_status'       => 'active',
                    'intended_school_year' => $schoolYear,
                    'intended_semester'    => $semester,
                ]);

                $student = $studentModel->find($studentModel->getInsertID());
            }
        } else {
            $section = empty($user['section_id']) ? null : (new SectionModel())->find((int) $user['section_id']);
        }

        if ($section !== null) {
            $enrollments = new EnrollmentModel();
            $missing = $this->missingSubjectCodes($userId, $section);

            foreach ($missing as $code) {
                $steps[] = "Enroll in {$code}.";

                if ($commit) {
                    $result = $enrollments->enrollOne($userId, (int) $section['section_id'], $code, 'block');

                    if (! $result['ok']) {
                        $steps[] = "  -> {$code} not enrolled: {$result['error']}";
                    }
                }
            }

            if ($commit && $student !== null) {
                $result = (new TuitionAssessmentService())->recompute((int) $student['student_id'], $section['academic_year']);
                $steps[] = $result['ok']
                    ? 'Tuition recomputed for ' . $section['academic_year'] . ' (status: ' . ($result['assessment']['status'] ?? '?') . ').'
                    : 'Tuition recompute failed: ' . ($result['error'] ?? 'unknown');
            } elseif (! $commit) {
                $steps[] = 'Recompute tuition for ' . $section['academic_year'] . '.';
            }
        }

        return ['ok' => true, 'steps' => $steps];
    }

    /** @return list<string> */
    private function missingSubjectCodes(int $userId, array $section): array
    {
        $plotted = (new EnrollmentModel())->subjectsFor($section);

        if ($plotted === []) {
            return [];
        }

        $enrolled = array_column(
            db_connect()->table('enrollments')
                ->select('subject_code')
                ->where('user_id', $userId)
                ->where('section_id', $section['section_id'])
                ->where('status', 'enrolled')
                ->get()
                ->getResultArray(),
            'subject_code'
        );

        return array_values(array_diff($plotted, $enrolled));
    }

    /** Mirrors StudentModel::nextStudentNumber() — kept private there, so replicated here rather than widening that model's own encapsulation. */
    private function nextStudentNumber(): string
    {
        $db     = db_connect();
        $year   = date('Y');
        $suffix = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';

        $count = $db->query("SELECT COUNT(*) AS cnt FROM students WHERE student_number LIKE ?{$suffix}", ["STU-{$year}-%"])->getRowArray();
        $next  = ((int) ($count['cnt'] ?? 0)) + 1;

        return sprintf('STU-%s-%05d', $year, $next);
    }

    /** "AY 2026 - 2027 · 1st Semester" => ['2026-2027', '1st']; best-effort, never blocks the repair. */
    private function splitAcademicYear(string $academicYear): array
    {
        if (preg_match('/AY\s*([\d]{4}\s*-\s*[\d]{4}).*?(1st|2nd|Summer)/u', $academicYear, $m) === 1) {
            return [str_replace(' ', '', $m[1]), $m[2]];
        }

        return [null, null];
    }
}

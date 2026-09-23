<?php

namespace App\Models;

use App\Libraries\ScheduleOverlap;
use CodeIgniter\Model;

class EnrollmentModel extends Model
{
    protected $table      = 'enrollments';
    protected $primaryKey = 'enrollment_id';

    protected $allowedFields = [
        'user_id',
        'section_id',
        'offering_id',
        'subject_code',
        'method',
        'status',
    ];

    protected $useTimestamps = true;

    /**
     * Enroll one student into one subject of one section, atomically: locks
     * the section row, re-checks time conflicts and capacity inside the same
     * transaction, then inserts. The unique key on
     * (user_id, section_id, subject_code) is the final backstop against a
     * duplicate slipping through a race; the section-row lock is what makes
     * the *capacity* check race-safe (a unique key alone can't cap a count).
     *
     * Every rejection path calls resetTransStatus() right after
     * transRollback(). Without it, an *expected*, handled rejection here
     * (already enrolled, section full, etc.) leaves the connection's
     * transStatus flag poisoned to false for the rest of the request/process
     * — CI4 only auto-clears that flag when transStrict is off (it isn't,
     * here). Any *later* transStart()/transComplete() pair on the same
     * connection would then silently report failure and roll back even
     * though its own queries succeeded. Caught the hard way: a block-enroll
     * loop that hit one duplicate enrollment, followed later in the same
     * process by CourseOfferingModel::createMerge(), made the merge appear
     * to fail with no underlying SQL error at all.
     *
     * @return array{ok: bool, error?: string, enrollment_id?: int}
     */
    public function enrollOne(int $userId, int $sectionId, string $subjectCode, string $method = 'manual'): array
    {
        $db = $this->db;
        $db->transStart();

        $section = $this->lockSection($sectionId);

        if ($section === null) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => 'That section no longer exists.'];
        }

        $meetings = $this->meetingsFor($section, $subjectCode);

        if ($meetings === []) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => "{$subjectCode} is not scheduled for this section."];
        }

        $missing = $this->unmetPrerequisites($userId, $subjectCode);

        if ($missing !== []) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => "Prerequisite not met for {$subjectCode}: " . implode(', ', $missing) . ' must be passed first.'];
        }

        $outsideCurriculum = $this->outsideCurriculum($userId, $subjectCode);

        if ($outsideCurriculum !== null) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => $outsideCurriculum];
        }

        $conflict = $this->findConflict($userId, $section['academic_year'], $meetings, $subjectCode);

        if ($conflict !== null) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => "Schedule conflict: {$subjectCode} overlaps {$conflict}, which you're already enrolled in."];
        }

        // If this (section, subject) has been merged into a course offering
        // (see CourseOfferingModel::createMerge), capacity is checked
        // against the MERGED pool across every attached section, not this
        // section alone — that's the entire point of a merge. Sections not
        // part of any offering behave exactly as before.
        $offering   = (new CourseOfferingModel())->offeringForSection($sectionId, $subjectCode);
        $offeringId = $offering['offering_id'] ?? null;
        $capacity   = $offering !== null ? $offering['capacity'] : $section['capacity'];

        if ($capacity !== null) {
            $memberQuery = $offeringId !== null
                ? ['offering_id' => $offeringId, 'user_id' => $userId, 'status' => 'enrolled']
                : ['section_id' => $sectionId, 'user_id' => $userId, 'status' => 'enrolled'];

            $alreadyMember = $this->where($memberQuery)->countAllResults() > 0;

            $rosterCount = $offeringId !== null
                ? (new CourseOfferingModel())->rosterCount($offeringId)
                : $this->rosterCount($sectionId);

            if (! $alreadyMember && $rosterCount >= (int) $capacity) {
                $db->transRollback();
                $db->resetTransStatus();

                return ['ok' => false, 'error' => $offeringId !== null ? 'This merged class is full.' : 'This section is full.'];
            }
        }

        try {
            $inserted = $this->insert([
                'user_id'      => $userId,
                'section_id'   => $sectionId,
                'offering_id'  => $offeringId,
                'subject_code' => $subjectCode,
                'method'       => $method,
                'status'       => 'enrolled',
            ]);
        } catch (\Exception $e) {
            $inserted = false;
        }

        // Unique key collision: already enrolled in this subject. Depending
        // on DBDebug, this surfaces either as a thrown exception (caught
        // above) or a falsy return from insert() — handle both.
        if ($inserted === false) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => "Already enrolled in {$subjectCode}."];
        }

        $enrollmentId = $this->getInsertID();
        $db->transComplete();

        return ['ok' => true, 'enrollment_id' => $enrollmentId];
    }

    /**
     * Every subject actually plotted for a section — the set block
     * enrollment enrolls a student into.
     *
     * @return string[]
     */
    public function subjectsFor(array $section): array
    {
        return array_column(
            db_connect()->table('plotted_schedules')
                ->select('subject_code')
                ->where('academic_year', $section['academic_year'])
                ->where('program', $section['program'])
                ->where('year_level', $section['year_level'])
                ->where('section', $section['section'])
                ->distinct()
                ->get()
                ->getResultArray(),
            'subject_code'
        );
    }

    /**
     * Every actively-enrolled student in this section for this one subject
     * (not the whole section's roster) — the class list grading needs when
     * the (section, subject) pair is NOT merged into a course offering.
     *
     * @return list<array{enrollment_id: int, user_id: int, username: string}>
     */
    public function listRosterForSectionSubject(int $sectionId, string $subjectCode): array
    {
        return db_connect()->table('enrollments e')
            ->select('e.enrollment_id, e.user_id, u.username')
            ->join('users u', 'u.id = e.user_id')
            ->where('e.section_id', $sectionId)
            ->where('e.subject_code', $subjectCode)
            ->where('e.status', 'enrolled')
            ->orderBy('u.username')
            ->get()
            ->getResultArray();
    }

    /**
     * Roster count = distinct students with an active enrollment in this
     * section, NOT a row count — one student holds several subject rows
     * under the same section without consuming several seats.
     */
    private function rosterCount(int $sectionId): int
    {
        $row = db_connect()->table('enrollments')
            ->select('COUNT(DISTINCT user_id) AS cnt')
            ->where('section_id', $sectionId)
            ->where('status', 'enrolled')
            ->get()
            ->getRowArray();

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Row-locks the section for the duration of the enclosing transaction so
     * concurrent enrollment attempts against it serialize. FOR UPDATE is
     * only valid under MySQL (production); it's a no-op elsewhere (the
     * SQLite test connection is single-process anyway).
     */
    private function lockSection(int $sectionId): ?array
    {
        $db     = $this->db;
        $suffix = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';

        return $db->query("SELECT * FROM sections WHERE section_id = ?{$suffix}", [$sectionId])->getRowArray();
    }

    /**
     * This subject's plotted meetings (day/start/end) for one section.
     */
    private function meetingsFor(array $section, string $subjectCode): array
    {
        $rows = db_connect()->table('plotted_schedules')
            ->select('day, start_time, end_time')
            ->where('academic_year', $section['academic_year'])
            ->where('program', $section['program'])
            ->where('year_level', $section['year_level'])
            ->where('section', $section['section'])
            ->where('subject_code', $subjectCode)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $r) => [
            'day'   => (int) $r['day'],
            'start' => substr($r['start_time'], 0, 5),
            'end'   => substr($r['end_time'], 0, 5),
        ], $rows);
    }

    /**
     * A student pinned to a curriculum version (students.curriculum_id) may
     * only enroll in subjects that belong to that version — the handbook's
     * "per-subject validation against the student's curriculum version".
     * Any year level/semester counts (irregular students legitimately take
     * subjects out of sequence); it is membership that is enforced.
     *
     * Skipped (returns null) when the user isn't a student, has no
     * curriculum assigned yet, or the code isn't a catalog Subject row —
     * those are data gaps, not the student's fault, and blocking on them
     * would freeze enrollment before the backfill is done.
     *
     * @return string|null an error message, or null when enrollment may proceed
     */
    private function outsideCurriculum(int $userId, string $subjectCode): ?string
    {
        $student = $this->db->table('students')
            ->select('curriculum_id')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if ($student === null || $student['curriculum_id'] === null) {
            return null;
        }

        $subject = $this->db->table('subjects')->select('subject_id')->where('subject_code', $subjectCode)->get()->getRowArray();

        if ($subject === null) {
            return null;
        }

        $inCurriculum = $this->db->table('curriculum_subjects')
            ->where('curriculum_id', (int) $student['curriculum_id'])
            ->where('subject_id', (int) $subject['subject_id'])
            ->where('is_active', 1)
            ->countAllResults() > 0;

        return $inCurriculum ? null : "{$subjectCode} is not part of your assigned curriculum.";
    }

    /**
     * Subject codes of every prerequisite $subjectCode has that the student
     * has NOT yet passed (a locked grade with is_passing = 1, from any prior
     * term/section — prerequisite credit doesn't expire). Empty array means
     * clear to enroll. If $subjectCode isn't in the subject catalog at all
     * (e.g. plotted under a code the admin hasn't registered as a Subject
     * row yet), the check is skipped rather than blocking enrollment on a
     * data gap that isn't the student's fault.
     *
     * @return string[]
     */
    private function unmetPrerequisites(int $userId, string $subjectCode): array
    {
        $subject = (new SubjectModel())->where('subject_code', $subjectCode)->first();

        if ($subject === null) {
            return [];
        }

        $prereqIds = array_column(
            (new SubjectPrerequisiteModel())->where('subject_id', $subject['subject_id'])->findAll(),
            'prerequisite_subject_id'
        );

        if ($prereqIds === []) {
            return [];
        }

        $prereqSubjects = (new SubjectModel())->whereIn('subject_id', $prereqIds)->findAll();

        $passedCodes = array_column(
            $this->db->table('grades')
                ->select('subject_code')
                ->where('user_id', $userId)
                ->where('status', 'locked')
                ->where('is_passing', 1)
                ->distinct()
                ->get()
                ->getResultArray(),
            'subject_code'
        );

        $missing = [];

        foreach ($prereqSubjects as $prereq) {
            if (! in_array($prereq['subject_code'], $passedCodes, true)) {
                $missing[] = $prereq['subject_code'];
            }
        }

        return $missing;
    }

    /**
     * The subject_code of the first already-enrolled subject (same academic
     * year) whose meetings clash with $meetings, or null when clear.
     */
    private function findConflict(int $userId, string $academicYear, array $meetings, string $excludeSubject): ?string
    {
        $rows = db_connect()->table('enrollments e')
            ->select('e.subject_code, s.academic_year, s.program, s.year_level, s.section')
            ->join('sections s', 's.section_id = e.section_id')
            ->where('e.user_id', $userId)
            ->where('e.status', 'enrolled')
            ->where('s.academic_year', $academicYear)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            if ($row['subject_code'] === $excludeSubject) {
                continue;
            }

            $otherMeetings = $this->meetingsFor($row, $row['subject_code']);

            foreach ($meetings as $a) {
                foreach ($otherMeetings as $b) {
                    if (ScheduleOverlap::clashes($a, $b)) {
                        return $row['subject_code'];
                    }
                }
            }
        }

        return null;
    }
}

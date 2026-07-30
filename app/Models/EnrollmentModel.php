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
     * @return array{ok: bool, error?: string, enrollment_id?: int}
     */
    public function enrollOne(int $userId, int $sectionId, string $subjectCode, string $method = 'manual'): array
    {
        $db = $this->db;
        $db->transStart();

        $section = $this->lockSection($sectionId);

        if ($section === null) {
            $db->transRollback();

            return ['ok' => false, 'error' => 'That section no longer exists.'];
        }

        $meetings = $this->meetingsFor($section, $subjectCode);

        if ($meetings === []) {
            $db->transRollback();

            return ['ok' => false, 'error' => "{$subjectCode} is not scheduled for this section."];
        }

        $conflict = $this->findConflict($userId, $section['academic_year'], $meetings, $subjectCode);

        if ($conflict !== null) {
            $db->transRollback();

            return ['ok' => false, 'error' => "Schedule conflict: {$subjectCode} overlaps {$conflict}, which you're already enrolled in."];
        }

        if ($section['capacity'] !== null) {
            $alreadyMember = $this->where([
                'section_id' => $sectionId,
                'user_id'    => $userId,
                'status'     => 'enrolled',
            ])->countAllResults() > 0;

            if (! $alreadyMember && $this->rosterCount($sectionId) >= (int) $section['capacity']) {
                $db->transRollback();

                return ['ok' => false, 'error' => 'This section is full.'];
            }
        }

        try {
            $inserted = $this->insert([
                'user_id'      => $userId,
                'section_id'   => $sectionId,
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

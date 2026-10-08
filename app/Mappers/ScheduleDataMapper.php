<?php

namespace App\Mappers;

use RuntimeException;

/**
 * MySQL <-> JSON transformer bridging the CI4 app's relational schema
 * and the FastAPI /api/v1/pipeline/run contract (see api/schemas.py:
 * PipelineDataset / PipelineRunResponse).
 *
 * DISCLOSED SCHEMA SUBSTITUTIONS (this app has no CourseModel or
 * ProfessorModel — those were requested by name but don't exist here):
 *   - "Courses"     -> subjects (catalog) joined to course_offerings
 *                      (the actual per-term scheduled instance of a
 *                      subject — a subject can have zero or many
 *                      offerings across terms, so the offering, not
 *                      the subject, is the schedulable unit).
 *   - "Professors"  -> Shield `users` rows in the 'faculty' auth group,
 *                      with unavailable timeslots derived from
 *                      `faculty_availability` rows where
 *                      is_available = 0.
 *
 * OTHER DISCLOSED CONVENTIONS (the schema has no native equivalent):
 *   - Timeslot catalog: this app plots schedules freely (any day/time),
 *     it has no fixed master timeslot table. DAY_MAP below defines the
 *     Mon-Sat <-> 0-5 integer convention this mapper uses to match
 *     plotted_schedules.day's existing int convention, and a Mon-Fri
 *     08:00-17:00 hourly grid is generated as this mapper's timeslot
 *     catalog for the engine.
 *   - Room "building": rooms has no building column, only `floor` —
 *     mapped as "Floor {N}".
 *   - Corequisite pairs: not modeled anywhere in this schema yet, so
 *     corequisite_pairs is always [] until that concept exists in the
 *     database.
 *   - Synthetic, REVERSIBLE engine-facing IDs: the engine's JSON needs
 *     string IDs, so DB primary keys are prefixed ("OFF12", "ROOM3",
 *     "FAC7") on the way out and parsed back with substr() on the way
 *     back in — see idToOfferingId()/idToRoomId()/idToUserId().
 */
class ScheduleDataMapper
{
    /** Mon-Sat <-> plotted_schedules.day's existing 0-5 int convention. */
    private const DAY_MAP = ['Mon' => 0, 'Tue' => 1, 'Wed' => 2, 'Thu' => 3, 'Fri' => 4, 'Sat' => 5];

    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    // ============================================================ DB -> Engine

    /**
     * Builds a PipelineDataset-shaped array for every course offering,
     * active room, and faculty member relevant to the given academic
     * year, ready to be wrapped as {"dataset": ...} and POSTed to
     * /api/v1/pipeline/run via PythonSchedulerClient::runIntegratedPipeline().
     *
     * @return array<string, mixed>
     */
    public function buildPipelineDataset(string $academicYear): array
    {
        return [
            'metadata' => [
                'description' => "Live pipeline dataset built from the SIS database for academic year {$academicYear}.",
                'notes' => [
                    'Courses are course_offerings joined to their subject; an offering with no assigned faculty is excluded (the engine cannot schedule a course with no professor).',
                    'corequisite_pairs is always empty — this schema does not yet model corequisite relationships between subjects.',
                ],
            ],
            'courses' => $this->buildCourses($academicYear),
            'corequisite_pairs' => [],
            'rooms' => $this->buildRooms(),
            'professors' => $this->buildProfessors(),
            'timeslots' => $this->buildTimeslotGrid(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildCourses(string $academicYear): array
    {
        $rows = $this->db->table('course_offerings co')
            ->select('co.offering_id, co.subject_code, co.capacity, su.subject_name, su.sub_department_id,
                      su.is_laboratory, su.meetings_per_week, sd.name AS department_name')
            ->join('subjects su', 'su.subject_id = co.subject_id', 'left')
            ->join('sub_departments sd', 'sd.sub_dept_id = su.sub_department_id', 'left')
            ->where('co.academic_year', $academicYear)
            ->get()
            ->getResultArray();

        $courses = [];

        foreach ($rows as $row) {
            $offeringId = (int) $row['offering_id'];

            $facultyUserId = $this->db->table('faculty_assignments')
                ->select('user_id')
                ->where('offering_id', $offeringId)
                ->get()
                ->getRow('user_id');

            if ($facultyUserId === null) {
                // No professor assigned yet — the engine cannot schedule
                // this offering, so it's excluded rather than sent with
                // a fabricated professor.
                continue;
            }

            $enrolled = (int) ($this->db->table('enrollments')
                ->selectCount('DISTINCT user_id', 'cnt')
                ->where('offering_id', $offeringId)
                ->where('status', 'enrolled')
                ->get()
                ->getRow('cnt') ?? 0);

            $expectedEnrollment = $enrolled > 0 ? $enrolled : (int) ($row['capacity'] ?? 30);

            $courses[] = [
                'course_id' => $this->offeringIdToEngineId($offeringId),
                'department' => $row['department_name'] ?? 'General',
                'students' => $this->buildStudentRosterForOffering($offeringId, $expectedEnrollment),
                'max_capacity' => (int) ($row['capacity'] ?? max($expectedEnrollment, 1)),
                'required_room_type' => ((int) $row['is_laboratory']) === 1 ? 'Lab' : 'Lecture',
                'assigned_professor' => $this->userIdToEngineId((int) $facultyUserId),
                'sessions_per_week' => (int) ($row['meetings_per_week'] ?? 2),
                'corequisite_course' => null,
            ];
        }

        return $courses;
    }

    /**
     * The engine's section-balancing stage needs an explicit student
     * roster (a list of IDs), not just a headcount. Real enrollments
     * are used when present; otherwise synthetic placeholder IDs are
     * generated so the offering can still be run through the engine
     * (e.g. for a not-yet-enrolled future-term dry run).
     *
     * @return list<string>
     */
    private function buildStudentRosterForOffering(int $offeringId, int $expectedEnrollment): array
    {
        $enrolled = $this->db->table('enrollments')
            ->select('DISTINCT user_id')
            ->where('offering_id', $offeringId)
            ->where('status', 'enrolled')
            ->get()
            ->getResultArray();

        if ($enrolled !== []) {
            return array_map(static fn (array $r): string => 'U' . $r['user_id'], $enrolled);
        }

        $roster = [];
        for ($i = 1; $i <= $expectedEnrollment; $i++) {
            $roster[] = "OFF{$offeringId}-PLACEHOLDER-{$i}";
        }
        return $roster;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRooms(): array
    {
        $rows = $this->db->table('rooms')
            ->select('room_id, name, capacity, is_laboratory, floor')
            ->where('is_active', 1)
            ->get()
            ->getResultArray();

        return array_map(function (array $r): array {
            return [
                'id' => $this->roomIdToEngineId((int) $r['room_id']),
                'name' => $r['name'],
                'type' => ((int) $r['is_laboratory']) === 1 ? 'Lab' : 'Lecture',
                'capacity' => (int) $r['capacity'],
                'building' => 'Floor ' . ($r['floor'] ?? '1'),
            ];
        }, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildProfessors(): array
    {
        $rows = $this->db->table('users u')
            ->select('u.id, u.username, u.first_name, u.last_name')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'faculty')
            ->groupBy('u.id')
            ->get()
            ->getResultArray();

        return array_map(function (array $r): array {
            $userId = (int) $r['id'];
            $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: $r['username'];

            return [
                'id' => $this->userIdToEngineId($userId),
                'name' => $name,
                'department' => null,
                'unavailable_timeslots' => $this->buildUnavailableTimeslots($userId),
            ];
        }, $rows);
    }

    /**
     * NOTE: faculty_availability is not filtered by academic year here
     * — ay_id scoping was left out to keep this mapper's first pass
     * simple; every is_available=0 row for this faculty member is
     * treated as a standing weekly block regardless of term.
     *
     * @return list<string>
     */
    private function buildUnavailableTimeslots(int $userId): array
    {
        $rows = $this->db->table('faculty_availability')
            ->select('day_of_week, start_time')
            ->where('user_id', $userId)
            ->where('is_available', 0)
            ->get()
            ->getResultArray();

        $dayNames = array_flip(self::DAY_MAP);
        $slots = [];

        foreach ($rows as $r) {
            $dayName = $dayNames[(int) $r['day_of_week']] ?? null;
            if ($dayName === null) {
                continue; // outside the Mon-Sat convention this mapper uses — skip rather than guess
            }
            $slots[] = $dayName . '-' . substr((string) $r['start_time'], 0, 5);
        }

        return array_values(array_unique($slots));
    }

    /**
     * Generates a Mon-Fri, 08:00-17:00 hourly timeslot catalog — this
     * schema has no master timeslot table, so this grid is the
     * convention this mapper uses as the engine's schedulable universe.
     *
     * @return list<array<string, string>>
     */
    private function buildTimeslotGrid(): array
    {
        $timeslots = [];
        foreach (array_keys(self::DAY_MAP) as $day) {
            if ($day === 'Sat') {
                continue; // academic scheduling in this dataset is Mon-Fri only
            }
            for ($hour = 8; $hour <= 16; $hour++) {
                $start = sprintf('%02d:00', $hour);
                $end = sprintf('%02d:00', $hour + 1);
                $timeslots[] = [
                    'id' => "{$day}-{$start}",
                    'day' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                ];
            }
        }
        return $timeslots;
    }

    // ============================================================ Engine -> DB

    /**
     * Persists a PipelineRunResponse (the decoded JSON body from
     * POST /api/v1/pipeline/run) into `plotted_schedules`, atomically.
     * Idempotent: re-running against the same engine response updates
     * existing rows (keyed on offering_id + day + start_time) instead
     * of duplicating them.
     *
     * @param array<string, mixed> $engineResponse
     * @return int Number of plotted_schedules rows written.
     * @throws RuntimeException on any write failure (transaction is
     *         rolled back before the exception is thrown).
     */
    public function persistPipelineResult(array $engineResponse, string $academicYear): int
    {
        $this->db->transStart();

        $written = 0;

        try {
            foreach ($engineResponse['schedule'] ?? [] as $section) {
                $offeringId = $this->engineIdToOfferingId($section['course_id']);
                $facultyUserId = $this->engineIdToUserId($section['professor']);

                $subject = $this->db->table('course_offerings co')
                    ->select('co.subject_code, su.subject_name')
                    ->join('subjects su', 'su.subject_id = co.subject_id', 'left')
                    ->where('co.offering_id', $offeringId)
                    ->get()
                    ->getRowArray();

                $professorUsername = $this->db->table('users')
                    ->select('username')
                    ->where('id', $facultyUserId)
                    ->get()
                    ->getRow('username');

                foreach ($section['sessions'] ?? [] as $session) {
                    $dayInt = self::DAY_MAP[$session['day']] ?? null;
                    if ($dayInt === null) {
                        throw new RuntimeException("Unrecognized day '{$session['day']}' in engine response for {$section['section_id']}.");
                    }

                    $roomId = $this->engineIdToRoomId($session['room_id']);
                    $roomName = $this->db->table('rooms')->select('name')->where('room_id', $roomId)->get()->getRow('name');

                    $startTime = $session['start_time'];
                    $endTime = sprintf('%02d:00', ((int) substr($startTime, 0, 2)) + 1);

                    $row = [
                        'offering_id' => $offeringId,
                        'academic_year' => $academicYear,
                        'day' => $dayInt,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'time_label' => $session['day'] . ' ' . $startTime . '-' . $endTime,
                        'subject_code' => $subject['subject_code'] ?? null,
                        'subject_title' => $subject['subject_name'] ?? null,
                        'prof' => $professorUsername,
                        'room' => $roomName,
                        'status' => 'published',
                    ];

                    $existingId = $this->db->table('plotted_schedules')
                        ->select('schedule_id')
                        ->where('offering_id', $offeringId)
                        ->where('day', $dayInt)
                        ->where('start_time', $startTime)
                        ->get()
                        ->getRow('schedule_id');

                    if ($existingId !== null) {
                        $this->db->table('plotted_schedules')->where('schedule_id', $existingId)->update($row);
                    } else {
                        $this->db->table('plotted_schedules')->insert($row);
                    }

                    $written++;
                }
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw new RuntimeException('Failed to persist pipeline result: ' . $e->getMessage(), 0, $e);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new RuntimeException('Transaction failed while persisting pipeline result to plotted_schedules.');
        }

        return $written;
    }

    // ============================================================ synthetic ID helpers

    private function offeringIdToEngineId(int $offeringId): string
    {
        return "OFF{$offeringId}";
    }

    private function engineIdToOfferingId(string $engineId): int
    {
        return (int) substr($engineId, 3);
    }

    private function roomIdToEngineId(int $roomId): string
    {
        return "ROOM{$roomId}";
    }

    private function engineIdToRoomId(string $engineId): int
    {
        return (int) substr($engineId, 4);
    }

    private function userIdToEngineId(int $userId): string
    {
        return "FAC{$userId}";
    }

    private function engineIdToUserId(string $engineId): int
    {
        return (int) substr($engineId, 3);
    }
}

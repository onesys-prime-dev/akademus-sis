<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\FacultyAssignmentModel;
use App\Models\PlottedScheduleModel;
use App\Models\SubjectModel;

class TeachingLoads extends BaseController
{
    /** Weekday labels matching the plotting grid's day indexes (0 = Monday). */
    private const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    /** Plotting themes mapped onto this view's accent palette. */
    private const ACCENTS = [
        'blue'   => 'blue',
        'purple' => 'purple',
        'cyan'   => 'blue',
        'red'    => 'red',
        'green'  => 'green',
        'yellow' => 'gold',
    ];

    /** Maximum teaching load per faculty, in units. */
    private const MAX_UNITS = 24;

    public function index()
    {
        $state = $this->buildState((int) auth()->id());

        $data = [
            'user' => [
                'name' => auth()->user()->username ?? 'Faculty',
                'role' => 'IT Department · Faculty',
            ],

            // Faculty specializations are not modeled yet — placeholder until
            // the faculty management phase.
            'specializations' => ['Programming', 'Database', 'Networking', 'Web-design'],

            'max_units' => self::MAX_UNITS,
            'state'     => $state,
        ];

        return view('teaching_loads/index', $data);
    }

    /**
     * Live board state. Long-polling: when the client passes its `version`,
     * the request is held open (up to ~20s) and answered the moment any
     * schedule or assignment row changes.
     */
    public function state()
    {
        $userId = (int) auth()->id();

        session_write_close();
        set_time_limit(60);

        $clientVersion = (string) $this->request->getGet('version');
        $deadline      = microtime(true) + 20;

        do {
            $version = $this->version();

            if ($clientVersion === '' || $version !== $clientVersion) {
                break;
            }

            usleep(400000);
        } while (microtime(true) < $deadline);

        return $this->response->setJSON($this->buildState($userId) + ['version' => $version]);
    }

    /**
     * Claim a load. First come first served — the unique index on
     * faculty_assignments is the tie-breaker for simultaneous claims.
     */
    public function claim()
    {
        $userId  = (int) auth()->id();
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->fail('Invalid payload.');
        }

        foreach (['academic_year', 'program', 'year_level', 'section', 'code'] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || $payload[$key] === '') {
                return $this->fail("Missing field: {$key}");
            }
        }

        $state = $this->buildState($userId);

        $key  = implode('|', [$payload['academic_year'], $payload['program'], $payload['year_level'], $payload['section'], $payload['code']]);
        $load = null;

        foreach ($state['loads'] as $candidate) {
            if ($candidate['key'] === $key) {
                $load = $candidate;
                break;
            }
        }

        if ($load === null) {
            return $this->fail('This load is no longer available — it may have just been claimed.');
        }

        if ($load['state'] === 'conflict') {
            return $this->fail("Schedule conflict: {$load['code']} overlaps one of your assigned classes.");
        }

        if ($state['units'] + $load['units'] > self::MAX_UNITS) {
            return $this->fail("Claiming {$load['code']} would exceed your " . self::MAX_UNITS . '-unit load limit.');
        }

        try {
            (new FacultyAssignmentModel())->insert([
                'user_id'       => $userId,
                'academic_year' => $payload['academic_year'],
                'program'       => $payload['program'],
                'year_level'    => $payload['year_level'],
                'section'       => $payload['section'],
                'subject_code'  => $payload['code'],
                'method'        => 'claimed',
                'status'        => 'assigned',
            ]);
        } catch (\Exception $e) {
            // Unique key collision: someone else won the race.
            return $this->fail("Too late — {$load['code']} was just claimed by another faculty member.");
        }

        return $this->response->setJSON(['ok' => true, 'claimed' => $load['code']]);
    }

    /**
     * Available loads + this faculty's assignments + unit total.
     */
    private function buildState(int $userId): array
    {
        $groups = $this->loadGroups();

        $assignments = (new FacultyAssignmentModel())->findAll();

        $assignedTo = [];
        foreach ($assignments as $assignment) {
            $assignedKey = implode('|', [
                $assignment['academic_year'],
                $assignment['program'],
                $assignment['year_level'],
                $assignment['section'],
                $assignment['subject_code'],
            ]);
            $assignedTo[$assignedKey] = (int) $assignment['user_id'];
        }

        $mine      = [];
        $available = [];

        foreach ($groups as $key => $group) {
            if (isset($assignedTo[$key])) {
                if ($assignedTo[$key] === $userId) {
                    $group['state'] = 'assigned';
                    $mine[]         = $group;
                }
                // Claimed by someone else: not shown as available.
                continue;
            }
            $available[] = $group;
        }

        $units = 0.0;
        $myMeetings = [];

        foreach ($mine as $load) {
            $units += $load['units'];
            foreach ($load['meetings'] as $meeting) {
                $myMeetings[] = $meeting;
            }
        }

        // Mark available loads that overlap this faculty's own classes.
        foreach ($available as &$load) {
            $load['state'] = 'claim';

            foreach ($load['meetings'] as $meeting) {
                foreach ($myMeetings as $moment) {
                    if ($meeting['day'] === $moment['day']
                        && $meeting['start'] < $moment['end'] && $moment['start'] < $meeting['end']) {
                        $load['state'] = 'conflict';
                        break 2;
                    }
                }
            }
        }
        unset($load);

        return [
            'loads' => array_values($available),
            'mine'  => array_values($mine),
            'units' => $units == (int) $units ? (int) $units : $units,
        ];
    }

    /**
     * Group plotted schedule rows into loads: one per subject per section,
     * with meeting days combined (e.g. "Mon, Wed") and raw meeting times kept
     * for conflict checks.
     */
    private function loadGroups(): array
    {
        $rows = (new PlottedScheduleModel())
            ->orderBy('section')
            ->orderBy('subject_code')
            ->orderBy('day')
            ->findAll();

        $unitsByCode = array_column((new SubjectModel())->findAll(), 'units', 'subject_code');

        $groups = [];

        foreach ($rows as $row) {
            $key = implode('|', [
                $row['academic_year'],
                $row['program'],
                $row['year_level'],
                $row['section'],
                $row['subject_code'],
            ]);

            if (! isset($groups[$key])) {
                $shortYear = str_replace(' Year', ' Yr', $row['year_level']);
                $units     = (float) ($unitsByCode[$row['subject_code']] ?? 3.0);

                $groups[$key] = [
                    'key'      => $key,
                    'code'     => $row['subject_code'],
                    'title'    => $row['subject_title'],
                    'spec'     => $row['section'] . ' · ' . $shortYear,
                    'days'     => [],
                    'day_list' => [],
                    'time'     => $row['time_label'],
                    'room'     => $row['room'],
                    'units'    => $units == (int) $units ? (int) $units : $units,
                    'accent'   => self::ACCENTS[$row['theme']] ?? 'blue',
                    'meetings' => [],
                    'context'  => [
                        'academic_year' => $row['academic_year'],
                        'program'       => $row['program'],
                        'year_level'    => $row['year_level'],
                        'section'       => $row['section'],
                    ],
                    'state' => 'claim',
                ];
            }

            $groups[$key]['days'][(int) $row['day']] = true;
            $groups[$key]['meetings'][] = [
                'day'   => (int) $row['day'],
                'start' => substr($row['start_time'], 0, 5),
                'end'   => substr($row['end_time'], 0, 5),
            ];
        }

        foreach ($groups as &$group) {
            $dayIndexes = array_keys($group['days']);
            sort($dayIndexes);
            $group['day_list'] = $dayIndexes;
            $group['days'] = implode(', ', array_map(
                static fn (int $day) => self::DAY_LABELS[$day] ?? '?',
                $dayIndexes
            ));
        }
        unset($group);

        return $groups;
    }

    /**
     * Cheap change signature over schedules and assignments. Any insert,
     * update, or delete in either table changes it.
     */
    private function version(): string
    {
        $db = db_connect();

        $parts = [];

        foreach (['plotted_schedules' => 'schedule_id', 'faculty_assignments' => 'assignment_id'] as $table => $pk) {
            $row = $db->table($table)
                ->select("COUNT(*) AS n, COALESCE(MAX({$pk}), 0) AS max_id, COALESCE(MAX(updated_at), '') AS updated")
                ->get()
                ->getRowArray();

            $parts[] = "{$row['n']}|{$row['max_id']}|{$row['updated']}";
        }

        return implode('#', $parts);
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

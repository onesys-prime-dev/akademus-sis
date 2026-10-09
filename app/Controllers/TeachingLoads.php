<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\ScheduleOverlap;
use App\Models\AcademicYearModel;
use App\Models\DepartmentModel;
use App\Models\FacultyAssignmentModel;
use App\Models\FacultyDepartmentModel;
use App\Models\FacultyChangeRequestModel;
use App\Models\NotificationModel;
use App\Models\PlottedScheduleModel;
use App\Models\SubjectModel;
use App\Models\SwapRequestModel;

class TeachingLoads extends BaseController
{
    /** Weekday labels matching the plotting grid's day indexes (0 = Monday). */
    private const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /** Plotting themes mapped onto this view's accent palette. */
    private const ACCENTS = [
        'blue'   => 'blue',
        'purple' => 'purple',
        'cyan'   => 'blue',
        'red'    => 'red',
        'green'  => 'green',
        'yellow' => 'gold',
    ];

    /**
     * Maximum teaching load per faculty, in units. Reads the same
     * Academic.maxFacultyUnits setting ChairpersonController::sync() enforces
     * at plot time (Admin\AcademicSettingsController is where it's edited) —
     * previously this was a separate hardcoded 24 here, so a chairperson
     * raising/lowering the cap in Settings silently didn't affect swaps.
     */
    private function maxUnits(): float
    {
        return (float) (setting('Academic.maxFacultyUnits') ?? 24);
    }

    public function index()
    {
        $userId = (int) auth()->id();
        $state  = $this->buildState($userId);

        $dept = (new DepartmentModel())->codeOfUser($userId);

        $data = [
            'user' => [
                'name' => auth()->user()->username ?? 'Faculty',
                'role' => ($dept !== null ? $dept . ' Department · ' : '') . 'Faculty',
            ],
            'no_department' => $dept === null,

            // Faculty specializations are not modeled yet — placeholder until
            // the faculty management phase.
            'specializations' => ['Programming', 'Database', 'Networking', 'Web-design'],

            'max_units' => $this->maxUnits(),
            'state'     => $state,
        ];

        return view('teaching_loads/index', $data);
    }

    /**
     * Live board state. Long-polling: when the client passes its `version`,
     * the request is held open (up to ~20s) and answered the moment any
     * schedule, assignment, or swap row changes.
     */
    public function state()
    {
        $userId = (int) auth()->id();

        session_write_close();
        set_time_limit(60);

        $clientVersion = (string) $this->request->getGet('version');
        $deadline      = microtime(true) + 20;

        do {
            $version = $this->version($userId);

            if ($clientVersion === '' || $version !== $clientVersion) {
                break;
            }

            usleep(400000);
        } while (microtime(true) < $deadline);

        return $this->response->setJSON($this->buildState($userId) + ['version' => $version]);
    }

    // Faculty no longer claim loads (FCFS) — the chairperson assigns faculty
    // directly while plotting (see ChairpersonController::sync()). Loads
    // that show as "unassigned" here are just informational; if a faculty
    // member disagrees with an assignment they already have, they use
    // Request for Change instead (see Faculty\ChangeRequestController).

    /**
     * Create a swap request.
     * Handover: offer one of my assignments to a chosen colleague.
     * Exchange: offer one of mine in return for a colleague's (taken) load.
     * Either way the transfer only happens after the chairperson approves.
     */
    public function requestSwap()
    {
        $userId  = (int) auth()->id();
        $payload = $this->request->getJSON(true);

        $offeredId   = (int) ($payload['offered_assignment_id'] ?? $payload['assignment_id'] ?? 0);
        $requestedId = (int) ($payload['requested_assignment_id'] ?? 0);

        $assignments = new FacultyAssignmentModel();
        $offered     = $assignments->find($offeredId);

        if ($offered === null || (int) $offered['user_id'] !== $userId) {
            return $this->fail('You do not hold the load you are offering.');
        }

        if (! $this->isActiveYear($offered['academic_year'])) {
            return $this->fail('This academic year is not active. Only the active term accepts swap requests.');
        }

        if ($requestedId > 0) {
            // Exchange: the target is whoever holds the requested load.
            $requested = $assignments->find($requestedId);

            if ($requested === null || (int) $requested['user_id'] === $userId) {
                return $this->fail('The requested load is not available for a swap.');
            }

            $targetId = (int) $requested['user_id'];
        } else {
            // Handover: explicit target.
            $requested = null;
            $targetId  = (int) ($payload['target_id'] ?? 0);

            if ($targetId === $userId) {
                return $this->fail('You cannot swap a load with yourself.');
            }

            if (! in_array($targetId, array_map('intval', array_column($this->facultyList($userId), 'id')), true)) {
                return $this->fail('The selected faculty member was not found in your department.');
            }
        }

        $involved = array_filter([$offeredId, $requestedId]);

        $busy = (new SwapRequestModel())
            ->groupStart()
                ->whereIn('assignment_id', $involved)
                ->orWhereIn('requested_assignment_id', $involved)
            ->groupEnd()
            ->whereIn('status', ['pending', 'accepted'])
            ->countAllResults();

        if ($busy > 0) {
            return $this->fail('One of these loads is already part of an open swap request.');
        }

        (new SwapRequestModel())->insert([
            'assignment_id'           => $offeredId,
            'requested_assignment_id' => $requestedId > 0 ? $requestedId : null,
            'requester_id'            => $userId,
            'target_id'               => $targetId,
            'status'                  => 'pending',
        ]);

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Target faculty accepts or declines. Accepting does NOT transfer yet —
     * it moves the request to "awaiting chairperson approval".
     */
    public function respondSwap()
    {
        $userId  = (int) auth()->id();
        $payload = $this->request->getJSON(true);

        $swapId = (int) ($payload['swap_id'] ?? 0);
        $action = (string) ($payload['action'] ?? '');

        if (! in_array($action, ['accept', 'decline'], true)) {
            return $this->fail('Invalid action.');
        }

        $swaps = new SwapRequestModel();
        $swap  = $swaps->find($swapId);

        if ($swap === null || (int) $swap['target_id'] !== $userId || $swap['status'] !== 'pending') {
            return $this->fail('This swap request is no longer open.');
        }

        if ($action === 'decline') {
            $swaps->update($swapId, ['status' => 'declined']);

            return $this->response->setJSON(['ok' => true, 'status' => 'declined']);
        }

        $assignments = new FacultyAssignmentModel();
        $offered     = $assignments->find((int) $swap['assignment_id']);

        if ($offered === null || (int) $offered['user_id'] !== (int) $swap['requester_id']) {
            $swaps->update($swapId, ['status' => 'cancelled']);

            return $this->fail('This swap is no longer valid — the offered load changed hands.');
        }

        if (! $this->isActiveYear($offered['academic_year'])) {
            return $this->fail('This academic year is not active. Only the active term accepts swap transfers.');
        }

        $requested = null;
        if ($swap['requested_assignment_id'] !== null) {
            $requested = $assignments->find((int) $swap['requested_assignment_id']);

            if ($requested === null || (int) $requested['user_id'] !== $userId) {
                $swaps->update($swapId, ['status' => 'cancelled']);

                return $this->fail('This swap is no longer valid — your requested load changed hands.');
            }
        }

        // Pre-check my side so the chairperson isn't asked to approve
        // something impossible (final validation happens again at approval).
        $groups = $this->loadGroups();

        $offeredInfo = $this->assignmentGroupInfo($offered, $groups);
        $excluded    = $requested !== null ? [(int) $requested['assignment_id']] : [];
        $agg         = $this->userAggregate($userId, $excluded, $groups);

        $maxUnits = $this->maxUnits();

        if ($agg['units'] + $offeredInfo['units'] > $maxUnits) {
            return $this->fail('Accepting would exceed your ' . $maxUnits . '-unit load limit.');
        }

        if (ScheduleOverlap::anyClash($offeredInfo['meetings'], $agg['meetings'])) {
            return $this->fail("Schedule conflict: {$offered['subject_code']} overlaps one of your classes.");
        }

        $swaps->update($swapId, ['status' => 'accepted']);

        return $this->response->setJSON(['ok' => true, 'status' => 'accepted']);
    }

    /**
     * Withdraw my own swap request (before the chairperson decides).
     */
    public function cancelSwap()
    {
        $userId  = (int) auth()->id();
        $payload = $this->request->getJSON(true);

        $swaps = new SwapRequestModel();
        $swap  = $swaps->find((int) ($payload['swap_id'] ?? 0));

        if ($swap === null || (int) $swap['requester_id'] !== $userId
            || ! in_array($swap['status'], ['pending', 'accepted'], true)) {
            return $this->fail('This swap request is no longer open.');
        }

        $swaps->update($swap['swap_id'], ['status' => 'cancelled']);

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Available + taken loads, my assignments, swaps, colleagues — scoped to
     * my department when I belong to one.
     */
    private function buildState(int $userId): array
    {
        // Visibility follows SUBJECT ownership (the same rule
        // SchedulingAuthorization::canManageSubject() and the swap-approval
        // flow use), not the program the class happens to be taught under.
        // A subject owned by one department is routinely taught to another
        // program's students (e.g. General-Ed subjects like GEC101/NSTP101
        // appear on every program's schedule but are NOT owned by every
        // program's department) — filtering by the enrolled program's
        // department let faculty see (and previously, try to edit) classes
        // belonging to departments they have no affiliation with at all.
        $deptIds = (new FacultyDepartmentModel())->activeDepartmentIdsForFaculty($userId);

        $subjectDeptByCode = array_column((new SubjectModel())->findAll(), 'sub_department_id', 'subject_code');

        // No department affiliation at all: nothing to scope against —
        // show nothing rather than leaking every department's loads.
        $groups = $deptIds === []
            ? []
            : array_filter(
                $this->loadGroups(),
                static fn (array $g) => in_array((int) ($subjectDeptByCode[$g['code']] ?? 0), $deptIds, true)
            );

        $assignmentRows = db_connect()->table('faculty_assignments fa')
            ->select('fa.*, u.username')
            ->join('users u', 'u.id = fa.user_id')
            ->get()
            ->getResultArray();

        $assignedTo = [];
        foreach ($assignmentRows as $assignment) {
            $assignedKey = implode('|', [
                $assignment['academic_year'],
                $assignment['program'],
                $assignment['year_level'],
                $assignment['section'],
                $assignment['subject_code'],
            ]);
            $assignedTo[$assignedKey] = $assignment;
        }

        // Assignments locked by an open (pending or awaiting-approval) swap.
        $openSwaps = (new SwapRequestModel())->whereIn('status', ['pending', 'accepted'])->findAll();
        $busyIds   = [];
        foreach ($openSwaps as $openSwap) {
            $busyIds[(int) $openSwap['assignment_id']] = true;
            if ($openSwap['requested_assignment_id'] !== null) {
                $busyIds[(int) $openSwap['requested_assignment_id']] = true;
            }
        }

        $openChangeRequestAssignmentIds = array_column(
            (new FacultyChangeRequestModel())->whereIn('status', ['pending', 'under_review'])->findAll(),
            'assignment_id'
        );

        $mine      = [];
        $available = [];
        $taken     = [];

        foreach ($groups as $key => $group) {
            if (isset($assignedTo[$key])) {
                $assignment   = $assignedTo[$key];
                $assignmentId = (int) $assignment['assignment_id'];

                $group['assignment_id']          = $assignmentId;
                $group['swap_pending']           = isset($busyIds[$assignmentId]);
                $group['change_request_pending'] = in_array($assignmentId, $openChangeRequestAssignmentIds, true);

                if ((int) $assignment['user_id'] === $userId) {
                    $group['state'] = 'assigned';
                    $mine[]         = $group;
                } else {
                    $group['state']  = 'taken';
                    $group['holder'] = $assignment['username'];
                    $taken[]         = $group;
                }
                continue;
            }
            $available[] = $group;
        }

        $units      = 0.0;
        $myMeetings = [];

        foreach ($mine as $load) {
            $units += $load['units'];
            foreach ($load['meetings'] as $meeting) {
                $myMeetings[] = $meeting;
            }
        }

        // Mark available loads that overlap this faculty's own classes.
        foreach ($available as &$load) {
            $load['state'] = ScheduleOverlap::anyClash($load['meetings'], $myMeetings) ? 'conflict' : 'unassigned';
        }
        unset($load);

        return [
            'loads'                => array_values($available),
            'taken'                => array_values($taken),
            'mine'                 => array_values($mine),
            'units'                => $units == (int) $units ? (int) $units : $units,
            'swaps'                => $this->swapData($userId, $groups),
            'faculty'              => $this->facultyList($userId),
            'change_requests'      => $this->changeRequestsFor($userId),
            'unread_notifications' => (new NotificationModel())->unreadCount($userId),
        ];
    }

    /**
     * This faculty member's own change requests, most recent first, labeled
     * from the underlying assignment's subject/context (which may no longer
     * be theirs — the request itself is a historical record either way).
     */
    private function changeRequestsFor(int $userId): array
    {
        $rows = db_connect()->table('faculty_change_requests cr')
            ->select('cr.request_id, cr.reason, cr.proposed_alternative, cr.status, cr.created_at,'
                . ' fa.subject_code, fa.section, fa.year_level')
            ->join('faculty_assignments fa', 'fa.assignment_id = cr.assignment_id')
            ->where('cr.faculty_id', $userId)
            ->orderBy('cr.created_at', 'DESC')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): array => [
            'id'       => (int) $row['request_id'],
            'subject'  => $row['subject_code'] . ' · ' . $row['section'] . ' · ' . str_replace(' Year', ' Yr', $row['year_level']),
            'reason'   => $row['reason'],
            'proposed' => $row['proposed_alternative'],
            'status'   => $row['status'],
        ], $rows);
    }

    /**
     * Swap requests relevant to this faculty member: open ones, plus any
     * resolved within the last hour so both parties see the outcome.
     */
    private function swapData(int $userId, array $groups): array
    {
        $rows = db_connect()->table('swap_requests sr')
            ->select('sr.swap_id, sr.requester_id, sr.target_id, sr.status,'
                . ' r.username AS requester, t.username AS target,'
                . ' fa1.academic_year a1_ay, fa1.program a1_prog, fa1.year_level a1_yl, fa1.section a1_sec, fa1.subject_code a1_code,'
                . ' fa2.academic_year a2_ay, fa2.program a2_prog, fa2.year_level a2_yl, fa2.section a2_sec, fa2.subject_code a2_code')
            ->join('faculty_assignments fa1', 'fa1.assignment_id = sr.assignment_id')
            ->join('faculty_assignments fa2', 'fa2.assignment_id = sr.requested_assignment_id', 'left')
            ->join('users r', 'r.id = sr.requester_id')
            ->join('users t', 't.id = sr.target_id')
            ->groupStart()
                ->where('sr.target_id', $userId)
                ->orWhere('sr.requester_id', $userId)
            ->groupEnd()
            ->groupStart()
                ->whereIn('sr.status', ['pending', 'accepted'])
                ->orWhere('sr.updated_at >', date('Y-m-d H:i:s', time() - 3600))
            ->groupEnd()
            ->orderBy('sr.swap_id', 'DESC')
            ->get()
            ->getResultArray();

        $loadInfo = function (array $row, string $prefix) use ($groups): ?array {
            if ($row[$prefix . '_code'] === null) {
                return null;
            }

            $key   = implode('|', [$row[$prefix . '_ay'], $row[$prefix . '_prog'], $row[$prefix . '_yl'], $row[$prefix . '_sec'], $row[$prefix . '_code']]);
            $group = $groups[$key] ?? null;

            return [
                'code'   => $row[$prefix . '_code'],
                'title'  => $group['title'] ?? $row[$prefix . '_code'],
                'spec'   => $group['spec'] ?? ($row[$prefix . '_sec'] . ' · ' . str_replace(' Year', ' Yr', $row[$prefix . '_yl'])),
                'days'   => $group['days'] ?? '',
                'time'   => $group['time'] ?? '',
                'room'   => $group['room'] ?? '',
                'units'  => $group['units'] ?? 3,
                'accent' => $group['accent'] ?? 'blue',
            ];
        };

        return array_map(static function (array $row) use ($userId, $loadInfo): array {
            return [
                'id'        => (int) $row['swap_id'],
                'direction' => (int) $row['target_id'] === $userId ? 'incoming' : 'outgoing',
                'status'    => $row['status'],
                'offered'   => $loadInfo($row, 'a1'),
                'requested' => $loadInfo($row, 'a2'),
                'requester' => $row['requester'],
                'target'    => $row['target'],
            ];
        }, $rows);
    }

    /**
     * Colleagues in my department I can hand a load to.
     */
    private function facultyList(int $userId): array
    {
        $me = db_connect()->table('users')->select('department_id')->where('id', $userId)->get()->getRowArray();

        $builder = db_connect()->table('users u')
            ->select('u.id, u.username')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'faculty')
            ->where('u.deleted_at', null)
            ->where('u.id !=', $userId);

        if (! empty($me['department_id'])) {
            $builder->where('u.department_id', $me['department_id']);
        }

        return $builder->orderBy('u.username')->get()->getResultArray();
    }

    /**
     * Meetings + units of one assignment's load, via the grouped schedules.
     */
    private function assignmentGroupInfo(array $assignment, array $groups): array
    {
        $key = implode('|', [
            $assignment['academic_year'],
            $assignment['program'],
            $assignment['year_level'],
            $assignment['section'],
            $assignment['subject_code'],
        ]);

        $group = $groups[$key] ?? null;

        return [
            'units'    => $group['units'] ?? 3,
            'meetings' => $group['meetings'] ?? [],
        ];
    }

    /**
     * A faculty member's total units + meetings, optionally excluding some
     * assignments (e.g. the one they'd give away in an exchange).
     */
    private function userAggregate(int $userId, array $excludeIds, array $groups): array
    {
        $rows = (new FacultyAssignmentModel())->where('user_id', $userId)->findAll();

        $units    = 0.0;
        $meetings = [];

        foreach ($rows as $row) {
            if (in_array((int) $row['assignment_id'], $excludeIds, true)) {
                continue;
            }
            $info = $this->assignmentGroupInfo($row, $groups);
            $units += $info['units'];
            foreach ($info['meetings'] as $meeting) {
                $meetings[] = $meeting;
            }
        }

        return ['units' => $units, 'meetings' => $meetings];
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
                    'state' => 'unassigned',
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
     * Cheap change signature over schedules, assignments, swaps, and this
     * user's own unread notifications.
     */
    private function version(int $userId): string
    {
        $db = db_connect();

        $parts = [];

        foreach (['plotted_schedules' => 'schedule_id', 'faculty_assignments' => 'assignment_id', 'swap_requests' => 'swap_id', 'faculty_change_requests' => 'request_id'] as $table => $pk) {
            $row = $db->table($table)
                ->select("COUNT(*) AS n, COALESCE(MAX({$pk}), 0) AS max_id, COALESCE(MAX(updated_at), '') AS updated")
                ->get()
                ->getRowArray();

            $parts[] = "{$row['n']}|{$row['max_id']}|{$row['updated']}";
        }

        $row = $db->table('notifications')
            ->select("COUNT(*) AS n, COALESCE(MAX(id), 0) AS max_id")
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->get()
            ->getRowArray();
        $parts[] = "{$row['n']}|{$row['max_id']}";

        return implode('#', $parts);
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }

    /**
     * Only the admin-flagged active academic year accepts new swap
     * transfers. Any other term is read-only from this controller.
     */
    private function isActiveYear(string $academicYear): bool
    {
        return $academicYear === (new AcademicYearModel())->activeFormatted();
    }
}

<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\ScheduleOverlap;
use App\Models\AcademicYearModel;
use App\Models\CurriculumPresetModel;
use App\Models\DepartmentModel;
use App\Models\FacultyAssignmentModel;
use App\Models\FacultyAvailabilityModel;
use App\Models\FacultyChangeRequestModel;
use App\Models\FacultyOverrideModel;
use App\Models\FacultySubjectPreferenceModel;
use App\Models\NotificationModel;
use App\Models\PlottedScheduleModel;
use App\Models\RoomModel;
use App\Models\SectionModel;
use App\Models\SubjectModel;
use App\Models\SwapRequestModel;

class ChairpersonController extends BaseController
{
    private const FILTERS = [
        'year_level' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        'section'    => ['AIT1', 'AIT2', 'BIT1'],
    ];

    /** A subject can meet at most this many times per week. */
    private const MAX_MEETINGS = 3;

    /** Meeting lengths a subject may have, in hours. */
    private const ALLOWED_DURATIONS = [1.0, 1.5, 2.0, 2.5, 3.0];

    /** Block accent colors, assigned per subject by a stable hash. */
    private const COLORS = ['blue', 'purple', 'cyan', 'red', 'green', 'yellow'];

    /**
     * Subject catalog from the subjects table (active rows only), scoped to
     * the logged-in chairperson's own department AND to one specific
     * program ("course"). A subject only ever belongs to one course — browse
     * a different course (even within the same department) and the catalog
     * goes blank rather than leaking another course's subjects. Year level
     * is not filtered here; a course's subjects span every year level unless
     * a curriculum preset narrows them further.
     */
    private function catalog(?string $programName = null): array
    {
        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        $query = (new SubjectModel())->where('is_active', 1);

        // No department, or browsing a course this chairperson doesn't
        // own, means no catalog to show — never fall back to everything.
        if ($subDept === null) {
            $query = $query->where('sub_department_id', -1);
        } else {
            $query = $query->where('sub_department_id', (int) $subDept['sub_dept_id']);

            $programId = $programName === null ? null : DepartmentModel::programIdOfName($programName);

            $query = $programId === null
                ? $query->where('program_id', -1)
                : $query->where('program_id', $programId);
        }

        $rows = $query->orderBy('subject_code')->findAll();

        return array_map(static function (array $row): array {
            $units    = (float) $row['units'];
            $duration = (float) $row['contact_hours'];

            $fmt = static fn (float $n): string => $n == (int) $n ? (string) (int) $n : (string) $n;

            return [
                'subject_id'   => (int) $row['subject_id'],
                'code'         => $row['subject_code'],
                'title'        => $row['subject_name'],
                'units'        => $fmt($units) . ' Units',
                'hours'        => $fmt($duration) . ' hours',
                'duration'     => $duration,
                'weekly_hours' => (float) ($row['weekly_hours'] ?? 0),
                'is_lab'       => (bool) $row['is_laboratory'],
                'color'        => self::COLORS[array_sum(array_map('ord', str_split($row['subject_code']))) % count(self::COLORS)],
            ];
        }, $rows);
    }

    /**
     * The catalog subjects valid for one context, with `is_lab` reflecting
     * THIS curriculum's assignment. An unconfigured (academic_year, program,
     * year_level) — e.g. a semester whose curriculum hasn't been built yet —
     * returns empty rather than falling back to the full program-wide
     * catalog: that fallback previously made an unconfigured semester show
     * subjects that actually only belong to a different semester's
     * curriculum (see the semester-isolation fix on the Curriculum page).
     * Shared by index() (the plotting rail) and sync() (block validation) so
     * both always agree on a subject's curriculum-specific classification.
     */
    private function contextSubjects(string $academicYear, string $program, string $yearLevel): array
    {
        $preset = (new CurriculumPresetModel())->forContext($academicYear, $program, $yearLevel);

        return $preset === null
            ? []
            : (new CurriculumPresetModel())->subjectsForContext($academicYear, $program, $yearLevel);
    }

    /**
     * Rooms grouped by floor, sourced from the admin-managed rooms table
     * (active rooms only) so the plotting grid always reflects what
     * Admin > Facilities currently has on record.
     */
    private function rooms(): array
    {
        $rows = (new RoomModel())
            ->where('is_active', 1)
            ->orderBy('floor')
            ->orderBy('name')
            ->findAll();

        return array_map(static fn (array $row): array => [
            'name'          => $row['name'],
            'floor'         => $row['floor'] . ' Floor',
            'type'          => $row['type'],
            'capacity'      => (int) $row['capacity'],
            'is_laboratory' => (bool) $row['is_laboratory'],
        ], $rows);
    }

    /**
     * Academic year + semester combinations sourced from the admin-managed
     * academic_years table, formatted to match the "AY 2025 - 2026 · 1st
     * Semester" strings stored on scheduling rows. One distinct entry per
     * (label, semester) row — this is what makes semester a real scheduling
     * dimension, not just a display label — with the active term first.
     */
    private function academicYears(): array
    {
        $rows = (new AcademicYearModel())
            ->orderBy('is_active', 'desc')
            ->orderBy('label', 'desc')
            ->orderBy('semester', 'asc')
            ->findAll();

        $formatted = [];

        foreach ($rows as $row) {
            $label = AcademicYearModel::format($row['label'], $row['semester']);

            if (! in_array($label, $formatted, true)) {
                $formatted[] = $label;
            }
        }

        return $formatted;
    }

    /**
     * Distinct academic year labels only (no semester) — backs the
     * "Academic Year" select on plotting/curriculum, paired with a separate
     * fixed Semester select so the two combine into one of the values
     * academicYears() produces.
     */
    private function academicYearLabels(): array
    {
        $rows = (new AcademicYearModel())
            ->orderBy('is_active', 'desc')
            ->orderBy('label', 'desc')
            ->findAll();

        $labels = [];

        foreach ($rows as $row) {
            if (! in_array($row['label'], $labels, true)) {
                $labels[] = $row['label'];
            }
        }

        return $labels;
    }

    /**
     * Only the admin-flagged active academic year accepts new plots, presets,
     * or swap decisions. Any other term is read-only from this controller.
     */
    private function isActiveYear(string $academicYear): bool
    {
        return $academicYear === (new AcademicYearModel())->activeFormatted();
    }

    public function index()
    {
        $context = $this->contextFromRequest();

        $schedules = new PlottedScheduleModel();
        $subDept   = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        [$profMap, $assignments] = $this->assignmentsFor($context['academic_year']);

        $blocks = $this->attachProfs(
            array_map([$this, 'rowToBlock'], $schedules->forContext($context['academic_year'], $context['program'], $context['year_level'], $context['section'])),
            $profMap
        );

        // Classes plotted for other sections in the same AY: shown as ghost
        // blocks and counted for room/faculty availability. Scoped to this
        // chairperson's own department — other departments' plots don't leak in.
        $deptPrograms = $subDept === null ? [] : (new DepartmentModel())->programsOfSubDept((int) $subDept['sub_dept_id']);

        $otherBlocks = $this->attachProfs(
            array_map([$this, 'rowToBlock'], $schedules->forOtherContexts($context['academic_year'], $context['program'], $context['year_level'], $context['section'], $deptPrograms)),
            $profMap
        );

        // Curriculum preset: which catalog subjects belong to this
        // AY + program + year level (shared by all sections).
        $preset = (new CurriculumPresetModel())->forContext(
            $context['academic_year'],
            $context['program'],
            $context['year_level']
        );

        $presetCodes = $preset === null ? null : (json_decode($preset['subject_codes'], true) ?: []);

        // Curriculum-specific subjects (is_lab reflects THIS curriculum's
        // assignment) — empty when this exact (academic_year, program,
        // year_level) hasn't been configured yet, so switching semester
        // never shows another semester's subjects.
        $subjects = $this->contextSubjects($context['academic_year'], $context['program'], $context['year_level']);

        $account = auth()->user();

        $data = [
            'user' => [
                'name' => $account->username ?? 'Chairperson',
                'role' => ($subDept !== null ? $subDept['name'] . ' - ' : '') . 'Chairperson',
            ],

            'filters'        => $this->filters(),
            'selected'       => $context,
            // Plotting is allowed for any term, not just the active one —
            // chairpersons need to build out future/non-active terms ahead
            // of time (same reasoning as Curriculum's is_active_year).
            'is_active_year' => true,

            'subjects'     => $subjects,
            'preset_codes' => $presetCodes,

            // Weekly grid. A recurring weekly template — no dates.
            'week' => [
                ['day' => 'Monday'],
                ['day' => 'Tuesday'],
                ['day' => 'Wednesday'],
                ['day' => 'Thursday'],
                ['day' => 'Friday'],
                ['day' => 'Saturday'],
            ],

            // Grid runs 7:00 AM - 5:30 PM in 30-minute rows.
            'grid_start' => '07:00',
            'grid_end'   => '17:30',

            'rooms' => $this->rooms(),

            'blocks'           => $blocks,
            'other_blocks'     => $otherBlocks,
            'assignments'      => $assignments,
            'swap_approvals'   => $this->swapApprovals(),
            'change_requests'  => $this->changeRequestApprovals(),
            'unread_notifications' => (new NotificationModel())->unreadCount((int) auth()->id()),

            'suggestion' => [
                'title'  => 'Move IT102 to Wed 1:00 PM - 2:30 PM',
                'impact' => 'High impact',
                'body'   => 'Room 302 is available and creates a better flow with no gaps before or after.',
                'alt'    => 'View alternative options (2)',
            ],
        ];

        return view('chairperson/schedule_plotting', $data);
    }

    /**
     * Full board state for the requested context (Google-Docs style sync).
     *
     * Long-polling: when the client passes its current `version`, the request
     * is held open (up to ~20s) and answered the moment any schedule row in
     * the academic year changes, so updates propagate in under a second.
     */
    public function state()
    {
        $context = $this->contextFromRequest();

        // Waiting must not block this user's other requests (saves, page
        // loads) on the session file lock.
        session_write_close();
        set_time_limit(60);

        $schedules = new PlottedScheduleModel();

        $clientVersion = (string) $this->request->getGet('version');
        $deadline      = microtime(true) + 20;

        do {
            $version = $this->scheduleVersion($schedules, $context['academic_year']);

            // First call (no version) or data changed: answer immediately.
            if ($clientVersion === '' || $version !== $clientVersion) {
                break;
            }

            usleep(400000);
        } while (microtime(true) < $deadline);

        [$profMap, $assignments] = $this->assignmentsFor($context['academic_year']);

        $subDept      = (new DepartmentModel())->subDeptOfUser((int) auth()->id());
        $deptPrograms = $subDept === null ? [] : (new DepartmentModel())->programsOfSubDept((int) $subDept['sub_dept_id']);

        $blocks = $this->attachProfs(
            array_map([$this, 'rowToBlock'], $schedules->forContext($context['academic_year'], $context['program'], $context['year_level'], $context['section'])),
            $profMap
        );

        return $this->response->setJSON([
            'version'     => $version,
            'blocks'      => $blocks,
            'others'      => $this->attachProfs(
                array_map([$this, 'rowToBlock'], $schedules->forOtherContexts($context['academic_year'], $context['program'], $context['year_level'], $context['section'], $deptPrograms)),
                $profMap
            ),
            'assignments'      => $assignments,
            'swap_approvals'   => $this->swapApprovals(),
            'change_requests'  => $this->changeRequestApprovals(),
            'unread_notifications' => (new NotificationModel())->unreadCount((int) auth()->id()),
        ]);
    }

    /**
     * Faculty-accepted swaps awaiting this chairperson's decision, scoped to
     * their department's programs.
     */
    private function swapApprovals(): array
    {
        $dept = (new DepartmentModel())->codeOfUser((int) auth()->id());

        $rows = db_connect()->table('swap_requests sr')
            ->select('sr.swap_id, r.username AS requester, t.username AS target,'
                . ' fa1.program a1_prog, fa1.year_level a1_yl, fa1.section a1_sec, fa1.subject_code a1_code,'
                . ' fa2.program a2_prog, fa2.year_level a2_yl, fa2.section a2_sec, fa2.subject_code a2_code')
            ->join('faculty_assignments fa1', 'fa1.assignment_id = sr.assignment_id')
            ->join('faculty_assignments fa2', 'fa2.assignment_id = sr.requested_assignment_id', 'left')
            ->join('users r', 'r.id = sr.requester_id')
            ->join('users t', 't.id = sr.target_id')
            ->where('sr.status', 'accepted')
            ->orderBy('sr.swap_id')
            ->get()
            ->getResultArray();

        $label = static fn (string $code, string $section, string $yearLevel): string =>
            $code . ' · ' . $section . ' · ' . str_replace(' Year', ' Yr', $yearLevel);

        $items = [];

        foreach ($rows as $row) {
            if ($dept !== null && DepartmentModel::programDept($row['a1_prog']) !== $dept) {
                continue;
            }

            $items[] = [
                'id'        => (int) $row['swap_id'],
                'requester' => $row['requester'],
                'target'    => $row['target'],
                'offered'   => $label($row['a1_code'], $row['a1_sec'], $row['a1_yl']),
                'requested' => $row['a2_code'] === null ? null : $label($row['a2_code'], $row['a2_sec'], $row['a2_yl']),
            ];
        }

        return $items;
    }

    /**
     * Approve a faculty-accepted swap: final validation of both parties,
     * then the loads transfer atomically.
     */
    public function approveSwap()
    {
        return $this->decideSwap(true);
    }

    public function rejectSwap()
    {
        return $this->decideSwap(false);
    }

    private function decideSwap(bool $approve)
    {
        $payload = $this->request->getJSON(true);
        $swapId  = (int) ($payload['swap_id'] ?? 0);

        $swaps = new SwapRequestModel();
        $swap  = $swaps->find($swapId);

        if ($swap === null || $swap['status'] !== 'accepted') {
            return $this->fail('This swap is not awaiting approval.');
        }

        $assignments = new FacultyAssignmentModel();
        $offered     = $assignments->find((int) $swap['assignment_id']);

        if ($offered === null) {
            $swaps->update($swapId, ['status' => 'cancelled']);

            return $this->fail('This swap is no longer valid.');
        }

        // Department ownership: only the owning department's chair decides.
        $dept = (new DepartmentModel())->codeOfUser((int) auth()->id());

        if ($dept !== null && DepartmentModel::programDept($offered['program']) !== $dept) {
            return $this->fail('This swap belongs to another department.');
        }

        if (! $approve) {
            $swaps->update($swapId, ['status' => 'rejected']);

            $notifications = new NotificationModel();
            $notifications->notify((int) $swap['requester_id'], 'swap_rejected', "Your swap request for {$offered['subject_code']} was rejected by your chairperson.", site_url('faculty/dashboard'));
            $notifications->notify((int) $swap['target_id'], 'swap_rejected', "The swap for {$offered['subject_code']} you accepted was rejected by the chairperson.", site_url('faculty/dashboard'));

            return $this->response->setJSON(['ok' => true, 'status' => 'rejected']);
        }

        if (! $this->isActiveYear($offered['academic_year'])) {
            return $this->fail('This academic year is not active. Only the active term can be edited.');
        }

        if ((int) $offered['user_id'] !== (int) $swap['requester_id']) {
            $swaps->update($swapId, ['status' => 'cancelled']);

            return $this->fail('This swap is no longer valid — the offered load changed hands.');
        }

        $requested = null;
        if ($swap['requested_assignment_id'] !== null) {
            $requested = $assignments->find((int) $swap['requested_assignment_id']);

            if ($requested === null || (int) $requested['user_id'] !== (int) $swap['target_id']) {
                $swaps->update($swapId, ['status' => 'cancelled']);

                return $this->fail('This swap is no longer valid — the requested load changed hands.');
            }
        }

        // Final fit check for both parties at this moment.
        $offeredInfo = $this->assignmentLoadInfo($offered);

        $targetAgg = $this->facultyAggregate((int) $swap['target_id'], $requested ? [(int) $requested['assignment_id']] : []);
        if ($error = $this->fitError($targetAgg, $offeredInfo, $swap['target_id'], $offered['subject_code'])) {
            return $this->fail('Cannot approve: ' . $error);
        }

        if ($requested !== null) {
            $requestedInfo = $this->assignmentLoadInfo($requested);
            $requesterAgg  = $this->facultyAggregate((int) $swap['requester_id'], [(int) $offered['assignment_id']]);
            if ($error = $this->fitError($requesterAgg, $requestedInfo, $swap['requester_id'], $requested['subject_code'])) {
                return $this->fail('Cannot approve: ' . $error);
            }
        }

        $db = db_connect();
        $db->transException(true)->transStart();

        $assignments->update($offered['assignment_id'], ['user_id' => (int) $swap['target_id'], 'method' => 'swapped']);
        if ($requested !== null) {
            $assignments->update($requested['assignment_id'], ['user_id' => (int) $swap['requester_id'], 'method' => 'swapped']);
        }

        $swaps->update($swapId, ['status' => 'approved']);

        // Any other open swaps touching these assignments are now moot.
        $involved = array_filter([(int) $offered['assignment_id'], $requested ? (int) $requested['assignment_id'] : 0]);
        $swaps->groupStart()
                ->whereIn('assignment_id', $involved)
                ->orWhereIn('requested_assignment_id', $involved)
            ->groupEnd()
            ->whereIn('status', ['pending', 'accepted'])
            ->where('swap_id !=', $swapId)
            ->set('status', 'cancelled')
            ->update();

        $db->transComplete();

        if ($db->transStatus() !== false) {
            $notifications = new NotificationModel();
            $notifications->notify((int) $swap['requester_id'], 'swap_approved', "Your swap for {$offered['subject_code']} was approved — loads transferred.", site_url('faculty/dashboard'));
            $notifications->notify((int) $swap['target_id'], 'swap_approved', "Your swap for {$offered['subject_code']} was approved — loads transferred.", site_url('faculty/dashboard'));
        }

        return $this->response->setJSON(['ok' => true, 'status' => 'approved']);
    }

    /**
     * Open (pending/under_review) change requests against this department's
     * assignments, joined to the requesting faculty and the subject/context
     * they were assigned.
     */
    private function changeRequestApprovals(): array
    {
        $dept = (new DepartmentModel())->codeOfUser((int) auth()->id());

        $rows = db_connect()->table('faculty_change_requests cr')
            ->select('cr.request_id, cr.reason, cr.proposed_alternative, cr.status, cr.created_at,'
                . ' u.username AS faculty, fa.program, fa.year_level, fa.section, fa.subject_code, fa.academic_year')
            ->join('faculty_assignments fa', 'fa.assignment_id = cr.assignment_id')
            ->join('users u', 'u.id = cr.faculty_id')
            ->whereIn('cr.status', ['pending', 'under_review'])
            ->orderBy('cr.created_at')
            ->get()
            ->getResultArray();

        $items = [];

        foreach ($rows as $row) {
            if ($dept !== null && DepartmentModel::programDept($row['program']) !== $dept) {
                continue;
            }

            $items[] = [
                'id'       => (int) $row['request_id'],
                'faculty'  => $row['faculty'],
                'subject'  => $row['subject_code'] . ' · ' . $row['section'] . ' · ' . str_replace(' Year', ' Yr', $row['year_level']),
                'reason'   => $row['reason'],
                'proposed' => $row['proposed_alternative'],
                'status'   => $row['status'],
            ];
        }

        return $items;
    }

    /**
     * Approve/reject a faculty change request. Approving does NOT
     * auto-reassign the subject — it just resolves the request so the
     * chairperson knows to re-plot the subject with a different (or no)
     * faculty member via the normal sync() flow. Keeping this one-way avoids
     * a second, parallel path that changes who's assigned to a subject.
     */
    public function approveChangeRequest()
    {
        return $this->decideChangeRequest('approved');
    }

    public function rejectChangeRequest()
    {
        return $this->decideChangeRequest('rejected');
    }

    public function reviewChangeRequest()
    {
        return $this->decideChangeRequest('under_review');
    }

    private function decideChangeRequest(string $newStatus)
    {
        $payload   = $this->request->getJSON(true);
        $requestId = (int) ($payload['request_id'] ?? 0);

        $requests = new FacultyChangeRequestModel();
        $request  = $requests->find($requestId);

        if ($request === null || ! in_array($request['status'], ['pending', 'under_review'], true)) {
            return $this->fail('This request is not open.');
        }

        $assignment = (new FacultyAssignmentModel())->find((int) $request['assignment_id']);

        if ($assignment === null) {
            $requests->update($requestId, ['status' => 'cancelled']);

            return $this->fail('The underlying assignment no longer exists.');
        }

        $dept = (new DepartmentModel())->codeOfUser((int) auth()->id());

        if ($dept !== null && DepartmentModel::programDept($assignment['program']) !== $dept) {
            return $this->fail('This request belongs to another department.');
        }

        $requests->update($requestId, [
            'status'      => $newStatus,
            'reviewed_by' => (int) auth()->id(),
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        if ($newStatus === 'approved' || $newStatus === 'rejected') {
            (new NotificationModel())->notify(
                (int) $request['faculty_id'],
                'change_request_' . $newStatus,
                "Your change request for {$assignment['subject_code']} was {$newStatus}.",
                site_url('faculty/dashboard')
            );
        }

        return $this->response->setJSON(['ok' => true, 'status' => $newStatus]);
    }

    /**
     * Meetings + units of one assignment's load, straight from the plotted
     * rows and subject catalog.
     */
    private function assignmentLoadInfo(array $assignment): array
    {
        $rows = (new PlottedScheduleModel())->where([
            'academic_year' => $assignment['academic_year'],
            'program'       => $assignment['program'],
            'year_level'    => $assignment['year_level'],
            'section'       => $assignment['section'],
            'subject_code'  => $assignment['subject_code'],
        ])->findAll();

        $meetings = array_map(static fn (array $row): array => [
            'day'   => (int) $row['day'],
            'start' => substr($row['start_time'], 0, 5),
            'end'   => substr($row['end_time'], 0, 5),
        ], $rows);

        $subject = (new SubjectModel())->where('subject_code', $assignment['subject_code'])->first();

        return [
            'units'    => (float) ($subject['units'] ?? 3.0),
            'meetings' => $meetings,
        ];
    }

    private function facultyAggregate(int $userId, array $excludeIds): array
    {
        $rows = (new FacultyAssignmentModel())->where('user_id', $userId)->findAll();

        $units    = 0.0;
        $meetings = [];

        foreach ($rows as $row) {
            if (in_array((int) $row['assignment_id'], $excludeIds, true)) {
                continue;
            }
            $info = $this->assignmentLoadInfo($row);
            $units += $info['units'];
            foreach ($info['meetings'] as $meeting) {
                $meetings[] = $meeting;
            }
        }

        return ['units' => $units, 'meetings' => $meetings];
    }

    private function fitError(array $aggregate, array $gain, int $userId, string $code): ?string
    {
        if ($aggregate['units'] + $gain['units'] > 24) {
            return "{$code} would push the receiving faculty over the 24-unit limit.";
        }

        if (ScheduleOverlap::anyClash($gain['meetings'], $aggregate['meetings'])) {
            return "{$code} would overlap another class of the receiving faculty.";
        }

        return null;
    }

    /**
     * Claims/assignments for one academic year, joined to usernames.
     * Returns [lookup map by program|year|section|code, plain list].
     */
    private function assignmentsFor(string $academicYear): array
    {
        $rows = db_connect()->table('faculty_assignments fa')
            ->select('fa.program, fa.year_level, fa.section, fa.subject_code, fa.user_id, u.username')
            ->join('users u', 'u.id = fa.user_id')
            ->where('fa.academic_year', $academicYear)
            ->get()
            ->getResultArray();

        $map  = [];
        $list = [];

        foreach ($rows as $row) {
            $key = implode('|', [$row['program'], $row['year_level'], $row['section'], $row['subject_code']]);

            // Carries both the display name and the id: the plotting rail
            // needs `faculty_id` so a later meeting of the same subject can
            // carry the assignment over automatically instead of asking
            // again (see attachProfs()).
            $map[$key] = ['username' => $row['username'], 'user_id' => (int) $row['user_id']];
            $list[]    = [
                'key'        => $key,
                'code'       => $row['subject_code'],
                'section'    => $row['section'],
                'year_level' => $row['year_level'],
                'username'   => $row['username'],
            ];
        }

        return [$map, $list];
    }

    /**
     * Overlay claimed professors onto blocks (display only — the claims
     * table stays the single source of truth).
     */
    private function attachProfs(array $blocks, array $profMap): array
    {
        return array_map(static function (array $block) use ($profMap): array {
            $key = implode('|', [$block['program'], $block['year_level'], $block['section'], $block['code']]);

            if (isset($profMap[$key])) {
                $block['prof']       = $profMap[$key]['username'];
                $block['faculty_id'] = $profMap[$key]['user_id'];
            }

            return $block;
        }, $blocks);
    }

    /**
     * Faculty context panel: everything a chairperson needs to see about
     * their department's faculty WHILE plotting, without having to ask them
     * directly — availability, subject preferences, current load, and
     * whether they've submitted anything yet for the upcoming term.
     *
     * Read-only. Availability/preferences are shown for the UPCOMING term
     * (what faculty are submitting for next); current load/schedule are
     * shown for the ACTIVE term (what's actually being plotted right now).
     */
    public function facultyPanel()
    {
        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        if ($subDept === null) {
            return $this->response->setJSON(['faculty' => []]);
        }

        $faculty = db_connect()->table('users u')
            ->select('u.id, u.username, u.employment_status')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'faculty')
            ->where('u.sub_department_id', (int) $subDept['sub_dept_id'])
            ->where('u.deleted_at', null)
            ->orderBy('u.username')
            ->get()
            ->getResultArray();

        if ($faculty === []) {
            return $this->response->setJSON(['faculty' => []]);
        }

        $userIds = array_map(static fn (array $f): int => (int) $f['id'], $faculty);

        // Availability/preferences shown here are for whichever term the
        // caller is actually working with — the plotting modal passes the
        // term it's plotting (`for_academic_year`), so the chairperson isn't
        // shown someone's Summer availability while assigning a 1st
        // Semester class. Falls back to "the upcoming term" (the one
        // faculty are currently allowed to submit for) when the caller
        // doesn't specify one — e.g. the general Faculty drawer, which is
        // about the upcoming submission window, not a specific plot.
        $forAcademicYear = $this->request->getGet('for_academic_year');

        $upcomingTerm = (new AcademicYearModel())->upcomingTerm();
        $activeYear   = (new AcademicYearModel())->activeFormatted();

        if ($forAcademicYear !== null) {
            foreach ((new AcademicYearModel())->findAll() as $row) {
                if (AcademicYearModel::format($row['label'], $row['semester']) === $forAcademicYear) {
                    $upcomingTerm = $row;
                    break;
                }
            }
        }

        $availabilityByUser  = [];
        $preferencesByUser   = [];

        if ($upcomingTerm !== null) {
            $availabilityRows = (new FacultyAvailabilityModel())
                ->whereIn('user_id', $userIds)
                ->where('ay_id', (int) $upcomingTerm['ay_id'])
                ->orderBy('day_of_week')
                ->findAll();

            foreach ($availabilityRows as $row) {
                $availabilityByUser[(int) $row['user_id']][] = $row;
            }

            $preferenceRows = (new FacultySubjectPreferenceModel())
                ->whereIn('user_id', $userIds)
                ->where('ay_id', (int) $upcomingTerm['ay_id'])
                ->findAll();

            $subjectsById = array_column((new SubjectModel())->findAll(), null, 'subject_id');

            foreach ($preferenceRows as $row) {
                $subject = $subjectsById[$row['subject_id']] ?? null;

                $preferencesByUser[(int) $row['user_id']][] = [
                    'code'  => $subject['subject_code'] ?? '?',
                    'title' => $subject['subject_name'] ?? '',
                    'level' => $row['preference_level'],
                ];
            }
        }

        // Current load: assignments in the ACTIVE year, with weekly_hours
        // pulled from each assigned subject's derived value.
        $assignmentRows = $activeYear === null ? [] : db_connect()->table('faculty_assignments')
            ->whereIn('user_id', $userIds)
            ->where('academic_year', $activeYear)
            ->get()
            ->getResultArray();

        $subjectsByCode = array_column((new SubjectModel())->findAll(), null, 'subject_code');

        $loadByUser = [];

        foreach ($assignmentRows as $row) {
            $subject = $subjectsByCode[$row['subject_code']] ?? null;

            $userId = (int) $row['user_id'];
            $loadByUser[$userId]['units'] = ($loadByUser[$userId]['units'] ?? 0.0) + (float) ($subject['units'] ?? 0);
            $loadByUser[$userId]['weekly_hours'] = ($loadByUser[$userId]['weekly_hours'] ?? 0.0) + (float) ($subject['weekly_hours'] ?? 0);
            $loadByUser[$userId]['subjects'][] = [
                'code'    => $row['subject_code'],
                'title'   => $subject['subject_name'] ?? $row['subject_code'],
                'section' => "{$row['program']} {$row['year_level']} {$row['section']}",
            ];

            // Exact meeting times, for client-side conflict checking while
            // picking a faculty member to plot a new block.
            foreach ($this->assignmentLoadInfo($row)['meetings'] as $meeting) {
                $loadByUser[$userId]['meetings'][] = $meeting;
            }
        }

        $panel = array_map(static function (array $f) use ($availabilityByUser, $preferencesByUser, $loadByUser): array {
            $id = (int) $f['id'];

            return [
                'id'                 => $id,
                'name'               => $f['username'],
                'employment_status'  => $f['employment_status'],
                'availability'       => $availabilityByUser[$id] ?? [],
                'has_submitted_availability' => isset($availabilityByUser[$id]),
                'preferences'        => $preferencesByUser[$id] ?? [],
                'units'              => $loadByUser[$id]['units'] ?? 0.0,
                'weekly_hours'       => $loadByUser[$id]['weekly_hours'] ?? 0.0,
                'subjects'           => $loadByUser[$id]['subjects'] ?? [],
                'meetings'           => $loadByUser[$id]['meetings'] ?? [],
            ];
        }, $faculty);

        return $this->response->setJSON([
            'faculty'       => $panel,
            'upcoming_term' => $upcomingTerm ? AcademicYearModel::format($upcomingTerm['label'], $upcomingTerm['semester']) : null,
            'active_year'   => $activeYear,
            'max_units'     => (float) (setting('Academic.maxFacultyUnits') ?? 24),
        ]);
    }

    /**
     * Cheap change signature for one academic year's schedule AND assignment
     * rows: plots, removals, and faculty claims all release the long-poll.
     */
    private function scheduleVersion(PlottedScheduleModel $schedules, string $academicYear): string
    {
        $db    = db_connect();
        $parts = [];

        foreach (['plotted_schedules' => 'schedule_id', 'faculty_assignments' => 'assignment_id'] as $table => $pk) {
            $row = $db->table($table)
                ->select("COUNT(*) AS n, COALESCE(MAX({$pk}), 0) AS max_id, COALESCE(MAX(updated_at), '') AS updated")
                ->where('academic_year', $academicYear)
                ->get()
                ->getRowArray();

            $parts[] = "{$row['n']}|{$row['max_id']}|{$row['updated']}";
        }

        // Swap requests have no AY column; any change should wake the board.
        $row = $db->table('swap_requests')
            ->select("COUNT(*) AS n, COALESCE(MAX(swap_id), 0) AS max_id, COALESCE(MAX(updated_at), '') AS updated")
            ->get()
            ->getRowArray();
        $parts[] = "{$row['n']}|{$row['max_id']}|{$row['updated']}";

        // Same for change requests — no AY column either.
        $row = $db->table('faculty_change_requests')
            ->select("COUNT(*) AS n, COALESCE(MAX(request_id), 0) AS max_id, COALESCE(MAX(updated_at), '') AS updated")
            ->get()
            ->getRowArray();
        $parts[] = "{$row['n']}|{$row['max_id']}|{$row['updated']}";

        // This user's own unread notifications — no AY column, and only
        // this user's rows matter for waking their own board.
        $row = $db->table('notifications')
            ->select("COUNT(*) AS n, COALESCE(MAX(id), 0) AS max_id")
            ->where('user_id', (int) auth()->id())
            ->where('is_read', 0)
            ->get()
            ->getRowArray();
        $parts[] = "{$row['n']}|{$row['max_id']}";

        return implode('#', $parts);
    }

    /**
     * Validate and persist the submitted plot for one context. Called after
     * every change (insta-plot); every rule the UI enforces is re-checked.
     */
    /**
     * Create or replace the curriculum preset for an AY + program + year
     * level. All sections of that year level share it.
     */
    public function savePreset()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || ! isset($payload['context'], $payload['subjects']) || ! is_array($payload['context']) || ! is_array($payload['subjects'])) {
            return $this->fail('Invalid payload.');
        }

        $context = [];

        foreach (['academic_year', 'program', 'year_level'] as $key) {
            if (! isset($payload['context'][$key]) || ! in_array($payload['context'][$key], $this->filters()[$key], true)) {
                return $this->fail("Invalid or missing context: {$key}");
            }
            $context[$key] = $payload['context'][$key];
        }

        $catalog = array_column($this->catalog($context['program']), null, 'code');
        $entries = [];
        $seen    = [];

        foreach ($payload['subjects'] as $item) {
            // Accept either a plain code string or {code, is_laboratory} —
            // the latter is what the curriculum page sends; the former is
            // kept for any other caller that only cares about membership.
            if (is_string($item)) {
                $code = $item;
                $isLab = null;
            } elseif (is_array($item) && isset($item['code']) && is_string($item['code'])) {
                $code  = $item['code'];
                $isLab = isset($item['is_laboratory']) ? (bool) $item['is_laboratory'] : null;
            } else {
                return $this->fail('Invalid subject entry.');
            }

            if (! isset($catalog[$code])) {
                return $this->fail("Unknown subject: {$code}");
            }

            if (isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;

            $entries[] = [
                'subject_id'    => (int) $catalog[$code]['subject_id'],
                'code'          => $code,
                // Falls back to the subject's global default when the
                // client doesn't specify a curriculum-specific value.
                'is_laboratory' => $isLab ?? $catalog[$code]['is_lab'],
            ];
        }

        // Empty is allowed — it's how a chairperson clears a year level's
        // curriculum entirely (e.g. removing the last subject) to start a
        // clean slate, rather than being forced to always keep at least one.
        (new CurriculumPresetModel())->upsertWithSubjects(
            $context['academic_year'],
            $context['program'],
            $context['year_level'],
            $entries
        );

        return $this->response->setJSON(['ok' => true, 'saved' => count($entries)]);
    }

    /**
     * Update a single subject's lab/lecture classification within one
     * curriculum assignment, without resaving the whole subject list.
     * Backs the per-row "Type" control on the Curriculum page.
     */
    public function updateSubjectType()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || ! isset($payload['context'], $payload['code']) || ! is_array($payload['context'])) {
            return $this->fail('Invalid payload.');
        }

        $context = [];

        foreach (['academic_year', 'program', 'year_level'] as $key) {
            if (! isset($payload['context'][$key]) || ! in_array($payload['context'][$key], $this->filters()[$key], true)) {
                return $this->fail("Invalid or missing context: {$key}");
            }
            $context[$key] = $payload['context'][$key];
        }

        $code = (string) $payload['code'];

        $presets = new CurriculumPresetModel();
        $preset  = $presets->forContext($context['academic_year'], $context['program'], $context['year_level']);

        if ($preset === null) {
            return $this->fail('This year level has no curriculum yet.');
        }

        $subject = (new SubjectModel())->where('subject_code', $code)->first();

        if ($subject === null) {
            return $this->fail("Unknown subject: {$code}");
        }

        $presets->updateSubjectLabFlag((int) $preset['preset_id'], (int) $subject['subject_id'], ! empty($payload['is_laboratory']));

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Copy another (academic_year+semester)'s subject list — including its
     * own lab/lecture classifications — into the current one, for the same
     * program + year level. An explicit, one-click action (never automatic)
     * so a semester never silently inherits another semester's curriculum;
     * the source is left completely untouched.
     */
    public function copyCurriculum()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || ! isset($payload['context'], $payload['source_academic_year']) || ! is_array($payload['context'])) {
            return $this->fail('Invalid payload.');
        }

        $context = [];

        foreach (['academic_year', 'program', 'year_level'] as $key) {
            if (! isset($payload['context'][$key]) || ! in_array($payload['context'][$key], $this->filters()[$key], true)) {
                return $this->fail("Invalid or missing context: {$key}");
            }
            $context[$key] = $payload['context'][$key];
        }

        $sourceAcademicYear = (string) $payload['source_academic_year'];

        if (! in_array($sourceAcademicYear, $this->filters()['academic_year'], true)) {
            return $this->fail('Invalid source term.');
        }

        if ($sourceAcademicYear === $context['academic_year']) {
            return $this->fail('Choose a different term to copy from.');
        }

        $presets = new CurriculumPresetModel();
        $source  = $presets->subjectsForContext($sourceAcademicYear, $context['program'], $context['year_level']);

        if ($source === []) {
            return $this->fail('That term has no curriculum set for this program and year level.');
        }

        $entries = array_map(static fn (array $s): array => [
            'subject_id'    => $s['subject_id'],
            'code'          => $s['code'],
            'is_laboratory' => $s['is_lab'],
        ], $source);

        $presets->upsertWithSubjects($context['academic_year'], $context['program'], $context['year_level'], $entries);

        return $this->response->setJSON(['ok' => true, 'copied' => count($entries)]);
    }

    /**
     * Add a subject to the catalog (curricula change; every school teaches
     * different things). Reactivates a previously removed code.
     */
    public function addSubject()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->fail('Invalid payload.');
        }

        $code  = strtoupper(trim((string) ($payload['code'] ?? '')));
        $title = trim((string) ($payload['title'] ?? ''));

        if (! preg_match('/^[A-Z0-9][A-Z0-9 ._-]{1,19}$/', $code)) {
            return $this->fail('Subject code must be 2-20 letters/numbers (e.g. IT106 or ITE 108.1).');
        }

        if ($title === '' || mb_strlen($title) > 200) {
            return $this->fail('Subject title is required (max 200 characters).');
        }

        $units = (float) ($payload['units'] ?? 0);

        if ($units < 0.5 || $units > 6) {
            return $this->fail('Units must be between 0.5 and 6.');
        }

        $duration = (float) ($payload['duration'] ?? 0);

        if (! in_array($duration, self::ALLOWED_DURATIONS, true)) {
            return $this->fail('Duration must be one of: ' . implode(', ', self::ALLOWED_DURATIONS) . ' hours.');
        }

        $isLab = ! empty($payload['is_lab']);

        $programName = (string) ($payload['program'] ?? '');

        if (! in_array($programName, $this->filters()['program'], true)) {
            return $this->fail('Invalid or missing program.');
        }

        $subDept   = (new DepartmentModel())->subDeptOfUser((int) auth()->id());
        $programId = DepartmentModel::programIdOfName($programName);

        if ($subDept === null || $programId === null) {
            return $this->fail('You are not assigned to a department yet.');
        }

        $subjects = new SubjectModel();
        $existing = $subjects->where('subject_code', $code)->first();

        if ($existing !== null && $existing['is_active']) {
            return $this->fail("Subject {$code} already exists in the catalog.");
        }

        $row = [
            'sub_department_id' => (int) $subDept['sub_dept_id'],
            'program_id'        => $programId,
            'subject_code'      => $code,
            'subject_name'      => $title,
            'units'             => $units,
            'contact_hours'     => $duration,
            'is_laboratory'     => $isLab ? 1 : 0,
            'is_active'         => 1,
        ];

        if ($existing === null) {
            $subjects->insert($row);
        } else {
            $subjects->update($existing['subject_id'], $row);
        }

        $subject = current(array_filter($this->catalog($programName), static fn (array $s) => $s['code'] === $code));

        return $this->response->setJSON(['ok' => true, 'subject' => $subject]);
    }

    /**
     * Edit an existing catalog subject. Title and units may always change;
     * code, duration, and the lab flag are locked while the subject is
     * plotted on any schedule, so existing blocks never become invalid.
     */
    public function updateSubject()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->fail('Invalid payload.');
        }

        $originalCode = strtoupper(trim((string) ($payload['original_code'] ?? '')));

        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        $subjects = new SubjectModel();
        $existing = $subjects->where('subject_code', $originalCode)->where('is_active', 1)->first();

        if ($existing === null || $subDept === null || (int) $existing['sub_department_id'] !== (int) $subDept['sub_dept_id']) {
            return $this->fail("Subject {$originalCode} is not in the catalog.");
        }

        $code  = strtoupper(trim((string) ($payload['code'] ?? '')));
        $title = trim((string) ($payload['title'] ?? ''));

        if (! preg_match('/^[A-Z0-9][A-Z0-9 ._-]{1,19}$/', $code)) {
            return $this->fail('Subject code must be 2-20 letters/numbers (e.g. IT106 or ITE 108.1).');
        }

        if ($title === '' || mb_strlen($title) > 200) {
            return $this->fail('Subject title is required (max 200 characters).');
        }

        $units = (float) ($payload['units'] ?? 0);

        if ($units < 0.5 || $units > 6) {
            return $this->fail('Units must be between 0.5 and 6.');
        }

        $duration = (float) ($payload['duration'] ?? 0);

        if (! in_array($duration, self::ALLOWED_DURATIONS, true)) {
            return $this->fail('Duration must be one of: ' . implode(', ', self::ALLOWED_DURATIONS) . ' hours.');
        }

        $isLab = ! empty($payload['is_lab']);

        $plotted = (new PlottedScheduleModel())->where('subject_code', $originalCode)->countAllResults();

        if ($plotted > 0) {
            $classes = $plotted . ' ' . ($plotted === 1 ? 'class' : 'classes');

            if ($code !== $originalCode) {
                return $this->fail("{$originalCode} is plotted in {$classes} — its code cannot change until those are removed.");
            }

            if (abs($duration - (float) $existing['contact_hours']) > 0.01) {
                return $this->fail("{$originalCode} is plotted in {$classes} — its duration cannot change until those are removed.");
            }

            if ($isLab !== (bool) $existing['is_laboratory']) {
                return $this->fail("{$originalCode} is plotted in {$classes} — its laboratory setting cannot change until those are removed.");
            }
        }

        if ($code !== $originalCode
            && $subjects->where('subject_code', $code)->countAllResults() > 0) {
            return $this->fail("Subject code {$code} is already used.");
        }

        $subjects->update($existing['subject_id'], [
            'subject_code'  => $code,
            'subject_name'  => $title,
            'units'         => $units,
            'contact_hours' => $duration,
            'is_laboratory' => $isLab ? 1 : 0,
        ]);

        // Keep the denormalized titles on plotted blocks in sync.
        if ($plotted > 0 && $title !== $existing['subject_name']) {
            (new PlottedScheduleModel())
                ->where('subject_code', $originalCode)
                ->set('subject_title', $title)
                ->update();
        }

        // Renames must follow through to curriculum presets.
        if ($code !== $originalCode) {
            $presets = new CurriculumPresetModel();
            foreach ($presets->findAll() as $preset) {
                $codes = json_decode($preset['subject_codes'], true) ?: [];
                $index = array_search($originalCode, $codes, true);
                if ($index !== false) {
                    $codes[$index] = $code;
                    $presets->update($preset['preset_id'], ['subject_codes' => json_encode(array_values($codes))]);
                }
            }
        }

        $programName = $existing['program_id'] === null ? null : DepartmentModel::programNameById((int) $existing['program_id']);
        $subject     = current(array_filter($this->catalog($programName), static fn (array $s) => $s['code'] === $code));

        return $this->response->setJSON(['ok' => true, 'subject' => $subject]);
    }

    /**
     * Remove a subject from the catalog (soft delete). Blocked while any
     * plotted class still uses it, so schedules never reference ghosts.
     */
    public function deleteSubject()
    {
        $payload = $this->request->getJSON(true);
        $code    = is_array($payload) ? strtoupper(trim((string) ($payload['code'] ?? ''))) : '';

        if ($code === '') {
            return $this->fail('Missing subject code.');
        }

        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        $subjects = new SubjectModel();
        $subject  = $subjects->where('subject_code', $code)->where('is_active', 1)->first();

        if ($subject === null || $subDept === null || (int) $subject['sub_department_id'] !== (int) $subDept['sub_dept_id']) {
            return $this->fail("Subject {$code} is not in the catalog.");
        }

        $plotted = (new PlottedScheduleModel())->where('subject_code', $code)->countAllResults();

        if ($plotted > 0) {
            return $this->fail("{$code} is plotted in {$plotted} " . ($plotted === 1 ? 'class' : 'classes') . ' — remove those from the schedules first.');
        }

        $subjects->update($subject['subject_id'], ['is_active' => 0]);

        return $this->response->setJSON(['ok' => true]);
    }

    public function sync()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || ! isset($payload['blocks'], $payload['context']) || ! is_array($payload['blocks']) || ! is_array($payload['context'])) {
            return $this->fail('Invalid payload.');
        }

        $context = [];

        foreach ($this->filters() as $key => $allowed) {
            if ($key === 'academic_year_labels') {
                continue; // view-only data, not a real context key (see contextFromRequest())
            }

            if (! isset($payload['context'][$key]) || ! in_array($payload['context'][$key], $allowed, true)) {
                return $this->fail("Invalid or missing context: {$key}");
            }
            $context[$key] = $payload['context'][$key];
        }


        $subjectsByCode = array_column($this->contextSubjects($context['academic_year'], $context['program'], $context['year_level']), null, 'code');

        $blocks = [];

        foreach ($payload['blocks'] as $block) {
            if (! is_array($block)) {
                return $this->fail('Invalid block.');
            }

            foreach (['code', 'time_label', 'room', 'start', 'end'] as $key) {
                if (! isset($block[$key]) || ! is_string($block[$key])) {
                    return $this->fail("Missing block field: {$key}");
                }
            }

            if (! isset($block['day']) || ! is_int($block['day']) || $block['day'] < 0 || $block['day'] > 5) {
                return $this->fail('Invalid block day.');
            }

            // The subject must exist in the curriculum; its details are
            // canonicalized from the curriculum, not trusted from the client.
            if (! isset($subjectsByCode[$block['code']])) {
                return $this->fail("Unknown subject: {$block['code']}");
            }

            $subject = $subjectsByCode[$block['code']];

            if (! in_array($block['room'], array_column($this->rooms(), 'name'), true)) {
                return $this->fail("Unknown room: {$block['room']}");
            }

            if (! preg_match('/^\d{2}:\d{2}$/', $block['start']) || ! preg_match('/^\d{2}:\d{2}$/', $block['end'])
                || $block['start'] >= $block['end']) {
                return $this->fail('Invalid block time range.');
            }

            // The plotted length must match the subject's class duration.
            if ($this->toMinutes($block['end']) - $this->toMinutes($block['start']) !== (int) round($subject['duration'] * 60)) {
                return $this->fail("{$subject['code']} must be plotted as a {$subject['hours']} block.");
            }

            // Faculty is chairperson-assigned at plot time (optional — a
            // subject may be plotted before a faculty member is picked).
            $facultyId = $block['faculty_id'] ?? null;

            if ($facultyId !== null && ! is_int($facultyId)) {
                return $this->fail('Invalid faculty_id.');
            }

            $blocks[] = [
                'day'        => $block['day'],
                'code'       => $subject['code'],
                'title'      => $subject['title'],
                'prof'       => null, // resolved below, once faculty_id is validated
                'faculty_id' => $facultyId,
                'room'       => $block['room'],
                'theme'      => $subject['color'],
                'start'      => $block['start'],
                'end'        => $block['end'],
                'time_label' => $block['time_label'],
            ];
        }

        // Faculty is chairperson-assigned per subject, not per meeting — every
        // block for the same code in this save must agree on who's teaching it.
        $facultyByCode = [];

        foreach ($blocks as $b) {
            if ($b['faculty_id'] === null) {
                continue;
            }

            if (isset($facultyByCode[$b['code']]) && $facultyByCode[$b['code']] !== $b['faculty_id']) {
                return $this->fail("{$b['code']} has conflicting faculty selections across its meetings — replot with one faculty member per subject.");
            }

            $facultyByCode[$b['code']] = $b['faculty_id'];
        }

        $usernameById = [];

        if ($facultyByCode !== []) {
            $subDeptForFaculty = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

            $deptFaculty = db_connect()->table('users u')
                ->select('u.id, u.username')
                ->join('auth_groups_users g', 'g.user_id = u.id')
                ->where('g.group', 'faculty')
                ->where('u.sub_department_id', $subDeptForFaculty === null ? -1 : (int) $subDeptForFaculty['sub_dept_id'])
                ->where('u.deleted_at', null)
                ->get()
                ->getResultArray();

            $usernameById = array_column($deptFaculty, 'username', 'id');

            foreach ($facultyByCode as $code => $facultyId) {
                if (! isset($usernameById[$facultyId])) {
                    return $this->fail("Selected faculty for {$code} is not a member of your department.");
                }
            }

            // Faculty unit-load cap: adding these subjects must not push
            // anyone over the configured maximum, counting their OTHER
            // current assignments in this AY — this section's own prior
            // assignment (if any) is excluded, since this save replaces it.
            $cap = (float) (setting('Academic.maxFacultyUnits') ?? 24);

            $subjectUnitsByCode = array_column(
                (new SubjectModel())->whereIn('subject_code', array_keys($facultyByCode))->findAll(),
                'units',
                'subject_code'
            );

            $gainByFaculty = [];

            foreach ($facultyByCode as $code => $facultyId) {
                $gainByFaculty[$facultyId] = ($gainByFaculty[$facultyId] ?? 0.0) + (float) ($subjectUnitsByCode[$code] ?? 0);
            }

            foreach ($gainByFaculty as $facultyId => $gain) {
                $existingUnits = (float) (db_connect()->table('faculty_assignments fa')
                    ->select('COALESCE(SUM(s.units), 0) as total')
                    ->join('subjects s', 's.subject_code = fa.subject_code')
                    ->where('fa.user_id', $facultyId)
                    ->where('fa.academic_year', $context['academic_year'])
                    ->groupStart()
                        ->where('fa.program !=', $context['program'])
                        ->orWhere('fa.year_level !=', $context['year_level'])
                        ->orWhere('fa.section !=', $context['section'])
                    ->groupEnd()
                    ->get()
                    ->getRowArray()['total'] ?? 0);

                if ($existingUnits + $gain > $cap) {
                    return $this->fail("{$usernameById[$facultyId]} would exceed the {$cap}-unit faculty load limit.");
                }
            }

            // Resolve `prof` (display username) now that faculty_id is
            // validated — this also activates the cross-section faculty
            // conflict check below, which compares blocks by `prof`.
            foreach ($blocks as &$b) {
                if ($b['faculty_id'] !== null) {
                    $b['prof'] = $usernameById[$b['faculty_id']] ?? null;
                }
            }
            unset($b);
        }

        // A subject cannot meet more than MAX_MEETINGS times per week.
        $meetings = array_count_values(array_column($blocks, 'code'));

        foreach ($meetings as $code => $count) {
            if ($count > self::MAX_MEETINGS) {
                return $this->fail("{$code} is plotted {$count} times; the maximum is " . self::MAX_MEETINGS . ' meetings per week.');
            }
        }

        // Within the section: no two classes may overlap in time.
        foreach ($blocks as $i => $a) {
            foreach (array_slice($blocks, $i + 1) as $b) {
                if (ScheduleOverlap::clashes($a, $b)) {
                    return $this->fail("Section conflict: {$a['code']} and {$b['code']} overlap on the same day.");
                }
            }
        }

        // Against every other section in the same AY: rooms and professors
        // cannot be in two places at once.
        $schedules = new PlottedScheduleModel();
        $others    = array_map([$this, 'rowToBlock'], $schedules->forOtherContexts($context['academic_year'], $context['program'], $context['year_level'], $context['section']));

        foreach ($blocks as $block) {
            foreach ($others as $other) {
                if (! ScheduleOverlap::clashes($block, $other)) {
                    continue;
                }

                if ($block['room'] === $other['room']) {
                    return $this->fail("Room conflict: {$other['room']} is used by {$other['code']} ({$other['section']}) at {$other['time_label']}.");
                }

                if ($block['prof'] !== null && $block['prof'] !== '' && $block['prof'] === $other['prof']) {
                    return $this->fail("Faculty conflict: {$other['prof']} teaches {$other['code']} ({$other['section']}) at {$other['time_label']}.");
                }
            }
        }

        $rows = array_map(static fn (array $b) => [
            'academic_year' => $context['academic_year'],
            'program'       => $context['program'],
            'year_level'    => $context['year_level'],
            'section'       => $context['section'],
            'day'           => $b['day'],
            'start_time'    => $b['start'],
            'end_time'      => $b['end'],
            'time_label'    => $b['time_label'],
            'subject_code'  => $b['code'],
            'subject_title' => $b['title'],
            'prof'          => $b['prof'],
            'room'          => $b['room'],
            'theme'         => $b['theme'],
            'status'        => 'published',
        ], $blocks);

        // One transaction covers the schedule replace, the section anchor,
        // and any faculty assignment upserts — a plot and its assignment
        // must land together or not at all.
        $db = db_connect();
        $db->transException(true)->transStart();

        $schedules->replaceDraft(
            $context['academic_year'],
            $context['program'],
            $context['year_level'],
            $context['section'],
            $rows
        );

        // A section becomes a real, stable row the moment it's plotted —
        // this is the anchor enrollment attaches to (unlike plotted_schedules
        // rows, which get replaced wholesale on every save).
        (new SectionModel())->firstOrCreateForContext(
            $context['academic_year'],
            $context['program'],
            $context['year_level'],
            $context['section']
        );

        $assignments = new FacultyAssignmentModel();
        $newlyAssigned = [];

        foreach ($facultyByCode as $code => $facultyId) {
            $changed = $assignments->upsertAssignment(
                $context['academic_year'],
                $context['program'],
                $context['year_level'],
                $context['section'],
                $code,
                $facultyId
            );

            if ($changed) {
                $newlyAssigned[] = ['facultyId' => $facultyId, 'code' => $code];
            }
        }

        // A subject removed from the grid entirely (no block left for it in
        // this save) must also drop its faculty assignment — otherwise the
        // faculty stays credited with a subject that's no longer plotted
        // anywhere, and shows up "currently teaching" it forever.
        $plottedCodes = array_values(array_unique(array_column($blocks, 'code')));

        $staleAssignments = $assignments->where([
            'academic_year' => $context['academic_year'],
            'program'       => $context['program'],
            'year_level'    => $context['year_level'],
            'section'       => $context['section'],
        ]);

        if ($plottedCodes !== []) {
            $staleAssignments->whereNotIn('subject_code', $plottedCodes);
        }

        $staleAssignments->delete();

        $db->transComplete();

        if ($db->transStatus() !== false) {
            $notifications = new NotificationModel();

            foreach ($newlyAssigned as $assignment) {
                $notifications->notify(
                    $assignment['facultyId'],
                    'assignment',
                    "You've been assigned to teach {$assignment['code']} ({$context['program']} · {$context['section']}).",
                    site_url('faculty/dashboard')
                );
            }
        }

        return $this->response->setJSON(['ok' => true, 'saved' => count($rows)]);
    }

    /**
     * Audit record for "Proceed Anyway" — the chairperson knowingly assigned
     * a faculty member outside their marked availability (or onto a time
     * that conflicts with another of their classes). Recorded independently
     * of sync() so the override reason is preserved even if the plot is
     * later changed. Never blocks the actual plot; this is bookkeeping only.
     */
    public function recordFacultyOverride()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->fail('Invalid payload.');
        }

        foreach (['faculty_id', 'academic_year', 'program', 'year_level', 'section', 'subject_code', 'day', 'start', 'end', 'conflict_type'] as $key) {
            if (! isset($payload[$key])) {
                return $this->fail("Missing field: {$key}");
            }
        }

        if (! in_array($payload['conflict_type'], ['unavailable', 'schedule_conflict'], true)) {
            return $this->fail('Invalid conflict_type.');
        }

        (new FacultyOverrideModel())->insert([
            'chairperson_id' => (int) auth()->id(),
            'faculty_id'     => (int) $payload['faculty_id'],
            'academic_year'  => (string) $payload['academic_year'],
            'program'        => (string) $payload['program'],
            'year_level'     => (string) $payload['year_level'],
            'section'        => (string) $payload['section'],
            'subject_code'   => (string) $payload['subject_code'],
            'day'            => (int) $payload['day'],
            'start_time'     => (string) $payload['start'],
            'end_time'       => (string) $payload['end'],
            'conflict_type'  => (string) $payload['conflict_type'],
            'reason'         => isset($payload['reason']) && $payload['reason'] !== '' ? mb_substr((string) $payload['reason'], 0, 255) : null,
        ]);

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Set (or clear) a section's enrollment capacity. Null/omitted means
     * unlimited. Scoped the same way sync() scopes writes — the section's
     * program must be one of the chairperson's own department's programs.
     */
    public function setSectionCapacity()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->fail('Invalid payload.');
        }

        foreach (['academic_year', 'program', 'year_level', 'section'] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || $payload[$key] === '') {
                return $this->fail("Missing field: {$key}");
            }
        }

        $filters = $this->filters();

        foreach (['academic_year', 'program', 'year_level', 'section'] as $key) {
            if (! in_array($payload[$key], $filters[$key], true)) {
                return $this->fail("Invalid or missing context: {$key}");
            }
        }

        $capacity = null;

        if (isset($payload['capacity']) && $payload['capacity'] !== '' && $payload['capacity'] !== null) {
            $capacity = (int) $payload['capacity'];

            if ($capacity < 1) {
                return $this->fail('Capacity must be a positive number, or left blank for unlimited.');
            }
        }

        $sections = new SectionModel();
        $section  = $sections->firstOrCreateForContext(
            $payload['academic_year'],
            $payload['program'],
            $payload['year_level'],
            $payload['section']
        );

        $sections->update($section['section_id'], ['capacity' => $capacity]);

        return $this->response->setJSON(['ok' => true, 'capacity' => $capacity]);
    }

    /**
     * Sections page: create sections for the department up front (rather
     * than waiting for one to be plotted), and assign/unassign students to
     * them. Note: a section created here is NOT automatically selectable in
     * the Schedule Plotting grid — that grid's own section list is still the
     * fixed self::FILTERS['section'] set; wiring a dynamic section picker
     * into plotting is a separate follow-up.
     */
    public function sections()
    {
        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        $sections = $subDept === null ? [] : (new SectionModel())->forSubDepartment($subDept['code']);

        $sectionIds = array_column($sections, 'section_id');

        $roster = $sectionIds === [] ? [] : db_connect()->table('users u')
            ->select('u.id, u.username, u.section_id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'student')
            ->where('u.deleted_at', null)
            ->whereIn('u.section_id', $sectionIds)
            ->orderBy('u.username')
            ->get()
            ->getResultArray();

        $rosterBySection = [];
        foreach ($roster as $row) {
            $rosterBySection[(int) $row['section_id']][] = $row;
        }

        $students = db_connect()->table('users u')
            ->select('u.id, u.username, u.section_id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'student')
            ->where('u.deleted_at', null)
            ->orderBy('u.username')
            ->get()
            ->getResultArray();

        return view('chairperson/sections', [
            'pageTitle' => 'Sections',
            'user'      => [
                'name' => auth()->user()->username ?? 'Chairperson',
                'role' => ($subDept !== null ? $subDept['name'] . ' - ' : '') . 'Chairperson',
            ],
            'deptName'        => $subDept['name'] ?? null,
            'programs'        => $subDept === null ? [] : (new DepartmentModel())->programsOfSubDept((int) $subDept['sub_dept_id']),
            'yearLevels'      => self::FILTERS['year_level'],
            'academicYears'   => $this->academicYears(),
            'sections'        => $sections,
            'rosterBySection' => $rosterBySection,
            'students'        => $students,
        ]);
    }

    /**
     * Full-page curriculum builder: which subjects belong to a given
     * academic year + program + year level's curriculum. Replaces the old
     * in-modal editor on the plotting page — same underlying endpoints
     * (subjects/add|update|delete, curriculum-preset), just more room to work.
     */
    public function curriculum()
    {
        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        $context = $this->contextFromRequest();

        $presets     = new CurriculumPresetModel();
        $preset      = $presets->forContext($context['academic_year'], $context['program'], $context['year_level']);
        $presetCodes = $preset === null ? null : (json_decode($preset['subject_codes'], true) ?: []);

        // Where each of this program's catalog subjects is currently
        // assigned — every (academic_year+semester, year_level) it's part
        // of, across the whole program, not just the year level being
        // viewed right now. Lets the catalog panel show "Plotted in: 1st
        // Year · AY 2025-2026 · 1st Semester" instead of leaving it a
        // mystery whether/where a subject is actually in use.
        $usageByCode = [];

        foreach (db_connect()->table('curriculum_preset_subjects cps')
            ->select('s.subject_code, cp.academic_year, cp.year_level')
            ->join('curriculum_presets cp', 'cp.preset_id = cps.preset_id')
            ->join('subjects s', 's.subject_id = cps.subject_id')
            ->where('cp.program', $context['program'])
            ->orderBy('cp.academic_year')
            ->get()
            ->getResultArray() as $row) {
            $usageByCode[$row['subject_code']][] = "{$row['year_level']} · {$row['academic_year']}";
        }

        return view('chairperson/curriculum', [
            'pageTitle' => 'Curriculum',
            'user'      => [
                'name' => auth()->user()->username ?? 'Chairperson',
                'role' => ($subDept !== null ? $subDept['name'] . ' - ' : '') . 'Chairperson',
            ],
            'deptName'          => $subDept['name'] ?? null,
            'filters'           => $this->filters(),
            'selected'          => $context,
            // Curriculum can be encoded ahead of time for any term, not just
            // the currently active one — unlike Schedule Plotting (which
            // touches live rooms/faculty), building a subject list here has
            // no scheduling side effects, so it's never read-only.
            'is_active_year'    => true,
            'catalog'           => $this->catalog($context['program']),
            'preset_codes'      => $presetCodes,
            // Curriculum-specific is_lab per assigned subject — this is
            // THIS year+semester's own classification, not the subject's
            // global default. No preset yet for this exact (academic_year+
            // semester, program, year_level) = genuinely nothing assigned
            // here yet — starts empty, on purpose. It must NOT fall back to
            // the full catalog (that was tried and reverted: it made every
            // unconfigured semester look like it "inherited" whatever
            // subjects existed globally, including ones only ever assigned
            // under a different semester — the opposite of what semester
            // isolation is for).
            'assigned_subjects' => $preset === null ? [] : $presets->subjectsForContext($context['academic_year'], $context['program'], $context['year_level']),
            'usage_by_code'     => $usageByCode,
        ]);
    }

    /**
     * Create a section for the department up front. Free-typed name (not
     * restricted to self::FILTERS['section'] — see sections() docblock).
     */
    public function createSection()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->fail('Invalid payload.');
        }

        foreach (['academic_year', 'program', 'year_level', 'section'] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || trim($payload[$key]) === '') {
                return $this->fail("Missing field: {$key}");
            }
        }

        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        if ($subDept === null) {
            return $this->fail('You are not assigned to a department.');
        }

        $programs = (new DepartmentModel())->programsOfSubDept((int) $subDept['sub_dept_id']);

        if (! in_array($payload['program'], $programs, true)) {
            return $this->fail('Invalid program.');
        }

        if (! in_array($payload['year_level'], self::FILTERS['year_level'], true)) {
            return $this->fail('Invalid year level.');
        }

        if (! in_array($payload['academic_year'], $this->academicYears(), true)) {
            return $this->fail('Invalid academic year.');
        }

        $sectionName = trim($payload['section']);

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9 _-]{0,19}$/', $sectionName)) {
            return $this->fail('Section name must be 1-20 letters/numbers (e.g. 1A, BIT2).');
        }

        $capacity = null;

        if (isset($payload['capacity']) && $payload['capacity'] !== '' && $payload['capacity'] !== null) {
            $capacity = (int) $payload['capacity'];

            if ($capacity < 1) {
                return $this->fail('Capacity must be a positive number, or left blank for unlimited.');
            }
        }

        $sections = new SectionModel();

        if ($sections->forContext($payload['academic_year'], $payload['program'], $payload['year_level'], $sectionName) !== null) {
            return $this->fail('That section already exists.');
        }

        $sections->insert([
            'academic_year' => $payload['academic_year'],
            'program'       => $payload['program'],
            'year_level'    => $payload['year_level'],
            'section'       => $sectionName,
            'capacity'      => $capacity,
        ]);

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Delete a section — only if it belongs to this department AND has no
     * students assigned or enrolled. Blocked rather than cascaded so a
     * roster is never silently wiped out by a delete click.
     */
    public function deleteSection()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['section_id'])) {
            return $this->fail('Missing section.');
        }

        $section = $this->ownSection((int) $payload['section_id']);

        if ($section === null) {
            return $this->fail('That section does not belong to your department.');
        }

        $rosterCount = db_connect()->table('users')->where('section_id', $section['section_id'])->countAllResults();
        $enrolledCount = db_connect()->table('enrollments')->where('section_id', $section['section_id'])->countAllResults();

        if ($rosterCount > 0 || $enrolledCount > 0) {
            return $this->fail('This section still has students assigned or enrolled — remove them first.');
        }

        (new SectionModel())->delete($section['section_id']);

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Assign (or move) a student into one of this department's sections.
     */
    public function assignStudent()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['user_id']) || empty($payload['section_id'])) {
            return $this->fail('Missing student or section.');
        }

        $section = $this->ownSection((int) $payload['section_id']);

        if ($section === null) {
            return $this->fail('That section does not belong to your department.');
        }

        $student = db_connect()->table('users u')
            ->select('u.id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'student')
            ->where('u.id', (int) $payload['user_id'])
            ->get()
            ->getRowArray();

        if ($student === null) {
            return $this->fail('Student not found.');
        }

        db_connect()->table('users')->where('id', $student['id'])->update(['section_id' => $section['section_id']]);

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Remove a student from their section, only if that section belongs to
     * this chairperson's own department.
     */
    public function unassignStudent()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['user_id'])) {
            return $this->fail('Missing student.');
        }

        $current = db_connect()->table('users')->select('section_id')->where('id', (int) $payload['user_id'])->get()->getRowArray();

        if ($current === null || $current['section_id'] === null || $this->ownSection((int) $current['section_id']) === null) {
            return $this->fail('That student is not in one of your sections.');
        }

        db_connect()->table('users')->where('id', (int) $payload['user_id'])->update(['section_id' => null]);

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * The section row, only if it belongs to the logged-in chairperson's
     * own department (via its program).
     */
    private function ownSection(int $sectionId): ?array
    {
        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        if ($subDept === null) {
            return null;
        }

        $section = (new SectionModel())->find($sectionId);

        if ($section === null) {
            return null;
        }

        $programs = (new DepartmentModel())->programsOfSubDept((int) $subDept['sub_dept_id']);

        return in_array($section['program'], $programs, true) ? $section : null;
    }

    /**
     * Filter options, with programs narrowed to the chairperson's own
     * department when they belong to one.
     */
    private function filters(): array
    {
        $filters                         = self::FILTERS;
        $filters['academic_year']        = $this->academicYears();
        // Not a real context key (contextFromRequest() only resolves
        // self::FILTERS + academic_year) — exposed so views can render the
        // Academic Year / Semester pair as two selects instead of one long
        // combined dropdown.
        $filters['academic_year_labels'] = $this->academicYearLabels();
        $filters['semester']             = ['1st', '2nd', 'Summer'];

        $deptModel = new DepartmentModel();
        $subDept   = $deptModel->subDeptOfUser((int) auth()->id());

        // A chairperson only ever schedules for their own department's
        // programs. No department assigned = no programs to schedule.
        $filters['program'] = $subDept === null ? [] : $deptModel->programsOfSubDept((int) $subDept['sub_dept_id']);

        return $filters;
    }

    /**
     * Resolve the plotting context from query params, falling back to the
     * first option of each filter.
     */
    private function contextFromRequest(): array
    {
        // The Academic Year / Semester pair is submitted as two separate
        // selects (friendlier than one long combined dropdown) and combined
        // here into the single `academic_year` value the rest of the app
        // already expects everywhere (plotted_schedules, faculty_assignments,
        // sections, curriculum_presets all just store this one string).
        $ayLabel    = $this->request->getGet('ay_label');
        $aySemester = $this->request->getGet('semester');

        $context               = [];
        $requestedComboMissing = false;

        foreach ($this->filters() as $key => $allowed) {
            if ($key === 'academic_year_labels') {
                continue; // view-only data, not a real context key
            }

            if ($key === 'academic_year' && $ayLabel !== null && $aySemester !== null) {
                $value = AcademicYearModel::format($ayLabel, $aySemester);
            } else {
                $value = $this->request->getGet($key);
            }

            $isValid = in_array($value, $allowed, true);

            // Only academic_year's fallback is worth surfacing: it means the
            // chairperson picked a Year/Semester combination that has no
            // matching row in Admin > Academic Years, so the page silently
            // reverted to a different term instead of showing what they
            // asked for.
            if ($key === 'academic_year' && $ayLabel !== null && $aySemester !== null && !$isValid) {
                $requestedComboMissing = true;
            }

            $context[$key] = $isValid ? $value : ($allowed[0] ?? '');
        }

        $context['missing_term_combo'] = $requestedComboMissing
            ? AcademicYearModel::format($ayLabel, $aySemester)
            : null;

        // Recover the raw label + semester backing the resolved
        // `academic_year` value (whatever it ended up being — explicit or
        // fallback) so the two-select UI shows the term it actually landed
        // on, not the invalid combination that was requested.
        foreach ((new AcademicYearModel())->findAll() as $row) {
            if (AcademicYearModel::format($row['label'], $row['semester']) === $context['academic_year']) {
                $context['ay_label'] = $row['label'];
                $context['semester'] = $row['semester'];
                break;
            }
        }

        return $context;
    }

    private function rowToBlock(array $row): array
    {
        return [
            'day'        => (int) $row['day'],
            'code'       => $row['subject_code'],
            'title'      => $row['subject_title'],
            'prof'       => $row['prof'],
            'room'       => $row['room'],
            'theme'      => $row['theme'],
            'start'      => substr($row['start_time'], 0, 5),
            'end'        => substr($row['end_time'], 0, 5),
            'time_label' => $row['time_label'],
            'section'    => $row['section'],
            'year_level' => $row['year_level'],
            'program'    => $row['program'],
        ];
    }

    private function toMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }

    /**
     * Department overview: quick stats for the chairperson's own department
     * in the active academic year, plus a way back into Schedule Plotting.
     */
    public function overview()
    {
        $account   = auth()->user();
        $deptModel = new DepartmentModel();
        $subDept   = $deptModel->subDeptOfUser((int) auth()->id());

        $activeYear = (new AcademicYearModel())->activeFormatted();
        $programs   = $subDept === null ? [] : $deptModel->programsOfSubDept((int) $subDept['sub_dept_id']);

        $plottedClasses = 0;
        $curriculumSet  = 0;
        $curriculumSlots = count($programs) * count(self::FILTERS['year_level']);

        if ($programs !== [] && $activeYear !== null) {
            $plottedClasses = (new PlottedScheduleModel())
                ->where('academic_year', $activeYear)
                ->whereIn('program', $programs)
                ->countAllResults();

            $presets = new CurriculumPresetModel();

            foreach ($programs as $program) {
                foreach (self::FILTERS['year_level'] as $yearLevel) {
                    if ($presets->forContext($activeYear, $program, $yearLevel) !== null) {
                        $curriculumSet++;
                    }
                }
            }
        }

        $facultyCount = $subDept === null ? 0 : db_connect()->table('users u')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'faculty')
            ->where('u.sub_department_id', (int) $subDept['sub_dept_id'])
            ->where('u.deleted_at', null)
            ->countAllResults();

        return view('chairperson/overview', [
            'pageTitle' => 'Dashboard',
            'user' => [
                'name' => $account->username ?? 'Chairperson',
                'role' => ($subDept !== null ? $subDept['name'] . ' - ' : '') . 'Chairperson',
            ],
            'deptName'    => $subDept['name'] ?? null,
            'activeYear'  => $activeYear,
            'programs'    => $programs,
            'stats' => [
                'plotted_classes'  => $plottedClasses,
                'faculty_count'    => $facultyCount,
                'pending_approvals' => count($this->swapApprovals()),
                'curriculum_set'   => $curriculumSet,
                'curriculum_slots' => $curriculumSlots,
            ],
        ]);
    }

    /**
     * Self-service account page: view/edit display name, view department,
     * change password.
     */
    public function profile()
    {
        $account   = auth()->user();
        $deptModel = new DepartmentModel();
        $subDept   = $deptModel->subDeptOfUser((int) auth()->id());

        return view('chairperson/profile', [
            'pageTitle' => 'Profile',
            'user' => [
                'name' => $account->username ?? 'Chairperson',
                'role' => ($subDept !== null ? $subDept['name'] . ' - ' : '') . 'Chairperson',
            ],
            'account' => [
                'username' => $account->username ?? '',
                'email'    => $account->getEmail() ?? '',
            ],
            'deptName' => $subDept['name'] ?? null,
            'deptCode' => $subDept['code'] ?? null,
        ]);
    }

    public function updateProfile()
    {
        $username = trim((string) $this->request->getPost('username'));

        if ($username === '' || mb_strlen($username) > 100) {
            return redirect()->back()->with('profile_error', 'Name is required (max 100 characters).');
        }

        $users = auth()->getProvider();
        $user  = auth()->user();
        $user->username = $username;
        $users->save($user);

        return redirect()->to('/chairperson/profile')->with('profile_message', 'Profile updated.');
    }

    public function updatePassword()
    {
        $current = (string) $this->request->getPost('current_password');
        $new     = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('confirm_password');

        if ($current === '' || $new === '' || $confirm === '') {
            return redirect()->back()->with('profile_error', 'All password fields are required.');
        }

        if ($new !== $confirm) {
            return redirect()->back()->with('profile_error', 'New password and confirmation do not match.');
        }

        $account = auth()->user();
        $result  = auth()->check(['email' => $account->getEmail(), 'password' => $current]);

        if (! $result->isOK()) {
            return redirect()->back()->with('profile_error', 'Current password is incorrect.');
        }

        if (mb_strlen($new) < 8) {
            return redirect()->back()->with('profile_error', 'New password must be at least 8 characters.');
        }

        $users = auth()->getProvider();
        $user  = auth()->user();
        $user->password = $new;
        $users->save($user);

        return redirect()->to('/chairperson/profile')->with('profile_message', 'Password changed.');
    }
}

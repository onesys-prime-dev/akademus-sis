<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;
use App\Models\CurriculumPresetModel;
use App\Models\DepartmentModel;
use App\Models\FacultyAssignmentModel;
use App\Models\PlottedScheduleModel;
use App\Models\RoomModel;
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

    /** Saturday's index in the weekly grid (labs are Saturday-only). */
    private const SATURDAY = 5;

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
                'code'     => $row['subject_code'],
                'title'    => $row['subject_name'],
                'units'    => $fmt($units) . ' Units',
                'hours'    => $fmt($duration) . ' hours',
                'duration' => $duration,
                'is_lab'   => (bool) $row['is_laboratory'],
                'color'    => self::COLORS[array_sum(array_map('ord', str_split($row['subject_code']))) % count(self::COLORS)],
            ];
        }, $rows);
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
            'name'  => $row['name'],
            'floor' => $row['floor'] . ' Floor',
        ], $rows);
    }

    /**
     * Academic year labels sourced from the admin-managed academic_years
     * table, formatted to match the "AY 2025 - 2026" strings already stored
     * on scheduling rows. Distinct by label (semester isn't part of this
     * filter), with the active year first.
     */
    private function academicYears(): array
    {
        $rows = (new AcademicYearModel())
            ->orderBy('is_active', 'desc')
            ->orderBy('label', 'desc')
            ->findAll();

        $labels = [];

        foreach ($rows as $row) {
            $formatted = AcademicYearModel::format($row['label']);

            if (! in_array($formatted, $labels, true)) {
                $labels[] = $formatted;
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

        $catalog = $this->catalog($context['program']);

        $subjects = $presetCodes === null
            ? $catalog
            : array_values(array_filter($catalog, static fn (array $s) => in_array($s['code'], $presetCodes, true)));

        $account = auth()->user();

        $data = [
            'user' => [
                'name' => $account->username ?? 'Chairperson',
                'role' => ($subDept !== null ? $subDept['name'] . ' - ' : '') . 'Chairperson',
            ],

            'filters'        => $this->filters(),
            'selected'       => $context,
            'is_active_year' => $this->isActiveYear($context['academic_year']),

            'subjects'     => $subjects,
            'catalog'      => $catalog,
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

            'blocks'         => $blocks,
            'other_blocks'   => $otherBlocks,
            'assignments'    => $assignments,
            'swap_approvals' => $this->swapApprovals(),

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

        return $this->response->setJSON([
            'version'     => $version,
            'blocks'      => $this->attachProfs(
                array_map([$this, 'rowToBlock'], $schedules->forContext($context['academic_year'], $context['program'], $context['year_level'], $context['section'])),
                $profMap
            ),
            'others'      => $this->attachProfs(
                array_map([$this, 'rowToBlock'], $schedules->forOtherContexts($context['academic_year'], $context['program'], $context['year_level'], $context['section'], $deptPrograms)),
                $profMap
            ),
            'assignments'    => $assignments,
            'swap_approvals' => $this->swapApprovals(),
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

        return $this->response->setJSON(['ok' => true, 'status' => 'approved']);
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

        foreach ($gain['meetings'] as $meeting) {
            foreach ($aggregate['meetings'] as $moment) {
                if ($meeting['day'] === $moment['day']
                    && $meeting['start'] < $moment['end'] && $moment['start'] < $meeting['end']) {
                    return "{$code} would overlap another class of the receiving faculty.";
                }
            }
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
            ->select('fa.program, fa.year_level, fa.section, fa.subject_code, u.username')
            ->join('users u', 'u.id = fa.user_id')
            ->where('fa.academic_year', $academicYear)
            ->get()
            ->getResultArray();

        $map  = [];
        $list = [];

        foreach ($rows as $row) {
            $key = implode('|', [$row['program'], $row['year_level'], $row['section'], $row['subject_code']]);

            $map[$key] = $row['username'];
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
                $block['prof'] = $profMap[$key];
            }

            return $block;
        }, $blocks);
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

        if (! $this->isActiveYear($context['academic_year'])) {
            return $this->fail('This academic year is not active. Only the active term can be edited.');
        }

        $catalogCodes = array_column($this->catalog($context['program']), 'code');
        $codes        = [];

        foreach ($payload['subjects'] as $code) {
            if (! is_string($code) || ! in_array($code, $catalogCodes, true)) {
                return $this->fail("Unknown subject: " . (is_string($code) ? $code : '?'));
            }
            if (! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        if ($codes === []) {
            return $this->fail('Select at least one subject for the curriculum.');
        }

        (new CurriculumPresetModel())->upsert(
            $context['academic_year'],
            $context['program'],
            $context['year_level'],
            $codes
        );

        return $this->response->setJSON(['ok' => true, 'saved' => count($codes)]);
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

        if (! preg_match('/^[A-Z0-9][A-Z0-9 _-]{1,19}$/', $code)) {
            return $this->fail('Subject code must be 2-20 letters/numbers (e.g. IT106).');
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

        if (! preg_match('/^[A-Z0-9][A-Z0-9 _-]{1,19}$/', $code)) {
            return $this->fail('Subject code must be 2-20 letters/numbers (e.g. IT106).');
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
            if (! isset($payload['context'][$key]) || ! in_array($payload['context'][$key], $allowed, true)) {
                return $this->fail("Invalid or missing context: {$key}");
            }
            $context[$key] = $payload['context'][$key];
        }

        if (! $this->isActiveYear($context['academic_year'])) {
            return $this->fail('This academic year is not active. Only the active term can be scheduled.');
        }

        $subjectsByCode = array_column($this->catalog($context['program']), null, 'code');

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

            // Laboratory subjects are Saturday-only.
            if ($subject['is_lab'] && $block['day'] !== self::SATURDAY) {
                return $this->fail("{$subject['code']} is a laboratory subject and can only be plotted on Saturdays.");
            }

            $blocks[] = [
                'day'        => $block['day'],
                'code'       => $subject['code'],
                'title'      => $subject['title'],
                'prof'       => $subject['prof'] ?? null,
                'room'       => $block['room'],
                'theme'      => $subject['color'],
                'start'      => $block['start'],
                'end'        => $block['end'],
                'time_label' => $block['time_label'],
            ];
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
                if ($a['day'] === $b['day'] && $this->overlaps($a, $b)) {
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
                if ($block['day'] !== $other['day'] || ! $this->overlaps($block, $other)) {
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

        $schedules->replaceDraft(
            $context['academic_year'],
            $context['program'],
            $context['year_level'],
            $context['section'],
            $rows
        );

        return $this->response->setJSON(['ok' => true, 'saved' => count($rows)]);
    }

    /**
     * Filter options, with programs narrowed to the chairperson's own
     * department when they belong to one.
     */
    private function filters(): array
    {
        $filters                  = self::FILTERS;
        $filters['academic_year'] = $this->academicYears();

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
        $context = [];

        foreach ($this->filters() as $key => $allowed) {
            $value = $this->request->getGet($key);
            $context[$key] = in_array($value, $allowed, true) ? $value : ($allowed[0] ?? '');
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

    private function overlaps(array $a, array $b): bool
    {
        return $a['start'] < $b['end'] && $b['start'] < $a['end'];
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

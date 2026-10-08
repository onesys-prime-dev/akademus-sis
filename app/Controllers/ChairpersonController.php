<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\ScheduleOverlap;
use App\Models\AcademicYearModel;
use App\Libraries\TermCurriculum;
use App\Libraries\SchedulingAuthorization;
use App\Libraries\SubjectDemandService;
use App\Models\CourseOfferingModel;
use App\Models\DepartmentModel;
use App\Models\FacultyAssignmentModel;
use App\Models\FacultyAvailabilityModel;
use App\Models\FacultyChangeRequestModel;
use App\Models\FacultyDepartmentModel;
use App\Models\FacultyOverrideModel;
use App\Models\FacultySubjectPreferenceModel;
use App\Models\NotificationModel;
use App\Models\PlottedScheduleModel;
use App\Models\RoomModel;
use App\Libraries\ScheduleSuggester;
use App\Libraries\SectionBalancer;
use App\Models\AuditLogModel;
use App\Models\CapacityPolicyModel;
use App\Models\SectionModel;
use App\Models\SubjectModel;
use App\Models\SwapRequestModel;

class ChairpersonController extends BaseController
{
    private const FILTERS = [
        'year_level' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
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

        return array_map([self::class, 'formatCatalogSubject'], $rows);
    }

    /**
     * Shared row->catalog-item shape, used both by catalog() (this
     * program's own subjects) and subjectByCode() (a cross-program lookup
     * for subjects another department already owns).
     */
    private static function formatCatalogSubject(array $row): array
    {
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
    }

    /**
     * Any ACTIVE subject by code, regardless of which program/department
     * owns its catalog entry — used to find a subject that's genuinely
     * shared across programs (e.g. ITE 101, a core course required by both
     * BSCS and BSIT) so it can be reused rather than duplicated or blocked.
     */
    private function subjectByCode(string $code): ?array
    {
        $row = (new SubjectModel())->where('subject_code', $code)->where('is_active', 1)->first();

        return $row === null ? null : self::formatCatalogSubject($row);
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
     * The published curriculum (`curriculum`/`curriculum_subjects`) is the
     * sole source of truth; a program/term with no published curriculum
     * simply offers nothing yet.
     */
    private function contextSubjects(string $academicYear, string $program, string $yearLevel): array
    {
        $term = (new TermCurriculum())->forContext($academicYear, $program, $yearLevel);

        return $term === null ? [] : $term['subjects'];
    }

    /**
     * Subject codes offered in one context, or null when no published
     * curriculum governs this program/term. An empty list means "offers
     * nothing"; null means "not configured at all".
     *
     * @return list<string>|null
     */
    private function termSubjectCodes(array $context): ?array
    {
        $term = (new TermCurriculum())->forContext($context['academic_year'], $context['program'], $context['year_level']);

        return $term === null ? null : array_column($term['subjects'], 'code');
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
    /**
     * Just the active term + the upcoming one (AcademicYearModel::
     * upcomingTerm() — same "term being planned for" concept faculty
     * availability already uses) — the only terms it ever makes sense to
     * create a NEW section under. academicYears() itself stays the full
     * history for pages that legitimately need it (filtering/browsing
     * existing sections, curriculum).
     */
    private function creatableAcademicYears(): array
    {
        $years = new AcademicYearModel();
        $rows  = array_filter([$years->activeRow(), $years->upcomingTerm()]);

        $formatted = [];

        foreach ($rows as $row) {
            $label = AcademicYearModel::format($row['label'], $row['semester']);

            if (! in_array($label, $formatted, true)) {
                $formatted[] = $label;
            }
        }

        return $formatted;
    }

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
        // chairperson's own programs PLUS any borrowing program using one of
        // their owned subjects (see borrowingProgramNames()) — otherwise a
        // subject owner viewing a borrowed section's grid would see no
        // context for that section's other classes at all.
        $deptPrograms = array_merge(
            $subDept === null ? [] : (new DepartmentModel())->programsOfSubDept((int) $subDept['sub_dept_id']),
            $this->borrowingProgramNames()
        );

        $otherBlocks = $this->attachProfs(
            array_map([$this, 'rowToBlock'], $schedules->forOtherContexts($context['academic_year'], $context['program'], $context['year_level'], $context['section'], $deptPrograms)),
            $profMap
        );

        // Which catalog subjects belong to this AY + program + year level
        // (shared by all sections) — from the published curriculum when the
        // program has one, else the legacy preset.
        $presetCodes = $this->termSubjectCodes($context);

        // Curriculum-specific subjects (is_lab reflects THIS curriculum's
        // assignment) — empty when this exact (academic_year, program,
        // year_level) hasn't been configured yet, so switching semester
        // never shows another semester's subjects.
        //
        // Filtered to subjects THIS department owns — sync() already
        // refuses to save a plot for a subject it doesn't own, so showing
        // one here (as draggable, suggestible, "plot this") would just
        // invite a failed save. A subject the curriculum needs but this
        // department doesn't own is handled via Shared Subject Coverage /
        // Requests instead, not from this catalog rail.
        $contextSubjects  = $this->contextSubjects($context['academic_year'], $context['program'], $context['year_level']);
        $ownedSubjectCodes = $contextSubjects === [] ? [] : array_column(
            (new SubjectModel())
                ->whereIn('subject_code', array_column($contextSubjects, 'code'))
                ->whereIn('sub_department_id', (new SchedulingAuthorization())->authorizedSubDeptIds((int) auth()->id()))
                ->findAll(),
            'subject_code'
        );
        $subjects = array_values(array_filter(
            $contextSubjects,
            static fn (array $s) => in_array($s['code'], $ownedSubjectCodes, true)
        ));

        $account = auth()->user();

        // filters()/sectionOptions() independently re-derive program/year
        // level from the raw query string, which on a bare first-login
        // (no query string at all) can't see the smart-jumped year level
        // $context already resolved above via contextFromRequest() — so
        // the section list it returns could be scoped to the WRONG year
        // level (e.g. the default 1st Year) even while the page displays
        // the smart-jumped 2nd Year as selected. Overriding it here with a
        // direct, authoritative query against $context's own already-
        // resolved program/year_level/academic_year guarantees the section
        // list shown always matches what's actually selected.
        $filters = $this->filters();
        $filters['section'] = array_values(array_unique(array_filter(array_map(
            'trim',
            array_column(
                db_connect()->table('sections')
                    ->select('section')
                    ->where('program', $context['program'])
                    ->where('year_level', $context['year_level'])
                    ->where('academic_year', $context['academic_year'])
                    ->get()
                    ->getResultArray(),
                'section'
            )
        ), static fn ($n) => $n !== '')));

        $data = [
            'user' => [
                'name' => $account->username ?? 'Chairperson',
                'role' => ($subDept !== null ? $subDept['name'] . ' - ' : '') . 'Chairperson',
            ],

            'filters'        => $filters,
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

            // Which Year Level options (for the currently selected program
            // + term) still have a department-owned subject pending —
            // annotated in the dropdown so switching to the lowest one
            // (see bestYearLevelForBorrowedSubject()) doesn't hide that
            // another year level also needs attention.
            'year_levels_needing_attention' => $this->yearLevelsNeedingAttention($context['program'], $context['academic_year']),
        ];

        return view('chairperson/schedule_plotting', $data);
    }

    /**
     * For the given program + term, how many of this chairperson's
     * authorized department's required subjects are NOT YET plotted
     * anywhere for that year level — keyed by year level label, used to
     * badge the Year Level dropdown (e.g. "2nd Year — 2 to plot") so a
     * pending requirement in a year level OTHER than the one just jumped
     * to isn't missed. Unlike bestYearLevelForBorrowedSubject(), this
     * covers ALL year levels, not just the lowest, and applies to the
     * chairperson's own program too (not just borrowed ones) since "still
     * unplotted" is a useful signal either way. A year level with
     * everything already plotted is omitted entirely (count 0).
     *
     * @return array<string, int> e.g. ['1st Year' => 3, '2nd Year' => 1]
     */
    private function yearLevelsNeedingAttention(string $program, string $academicYear): array
    {
        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds((int) auth()->id());
        if ($subDeptIds === []) {
            return [];
        }

        $programId = DepartmentModel::programIdOfName($program);
        if ($programId === null) {
            return [];
        }

        $semester = TermCurriculum::semesterOf($academicYear);
        if ($semester === null) {
            return [];
        }

        $required = db_connect()->table('curriculum_subjects cs')
            ->select('cs.year_level, s.subject_code')
            ->distinct()
            ->join('curriculum cu', 'cu.curriculum_id = cs.curriculum_id')
            ->join('subjects s', 's.subject_id = cs.subject_id')
            ->where('cu.program_id', $programId)
            ->where('cs.semester', $semester)
            ->where('cs.is_active', 1)
            ->where('s.is_active', 1)
            ->whereIn('s.sub_department_id', $subDeptIds)
            ->get()
            ->getResultArray();

        if ($required === []) {
            return [];
        }

        $labels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

        $plottedSubjectsByYear = [];
        foreach (array_unique(array_column($required, 'year_level')) as $yl) {
            $plottedSubjectsByYear[$yl] = array_column(
                db_connect()->table('plotted_schedules')
                    ->select('subject_code')
                    ->distinct()
                    ->where('program', $program)
                    ->where('year_level', $labels[((int) $yl) - 1] ?? '')
                    ->where('academic_year', $academicYear)
                    ->get()
                    ->getResultArray(),
                'subject_code'
            );
        }

        $counts = [];
        foreach ($required as $row) {
            $yl    = (int) $row['year_level'];
            $label = $labels[$yl - 1] ?? null;
            if ($label === null) {
                continue;
            }
            if (in_array($row['subject_code'], $plottedSubjectsByYear[$yl] ?? [], true)) {
                continue; // already plotted somewhere for this year level — not pending
            }
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        return $counts;
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
        $deptPrograms = array_merge(
            $subDept === null ? [] : (new DepartmentModel())->programsOfSubDept((int) $subDept['sub_dept_id']),
            $this->borrowingProgramNames()
        );

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
     * The scheduling assistant: conflict-free placements for whatever this
     * section still has left to plot, ranked and explained. Read-only —
     * nothing is saved until the chairperson applies a suggestion, and every
     * applied block still goes through sync()'s validation.
     */
    public function suggest()
    {
        $context = $this->contextFromRequest();

        session_write_close();

        $schedules = new PlottedScheduleModel();

        [$profMap] = $this->assignmentsFor($context['academic_year']);

        $blocks = $this->attachProfs(
            array_map([$this, 'rowToBlock'], $schedules->forContext($context['academic_year'], $context['program'], $context['year_level'], $context['section'])),
            $profMap
        );

        // School-wide, like sync(): rooms and professors are shared across departments.
        $others = $this->attachProfs(
            array_map([$this, 'rowToBlock'], $schedules->forOtherContexts($context['academic_year'], $context['program'], $context['year_level'], $context['section'])),
            $profMap
        );

        $section   = (new SectionModel())->forContext($context['academic_year'], $context['program'], $context['year_level'], $context['section']);
        $headcount = $section === null ? 0 : (int) db_connect()->table('users u')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'student')->where('u.deleted_at', null)->where('u.section_id', $section['section_id'])
            ->countAllResults();

        // No students in the section yet: plan for a typical class rather than
        // ignoring room size altogether.
        $known = $headcount > 0;

        if (! $known) {
            $headcount = (new CapacityPolicyModel())->resolve(['program' => $context['program'], 'year_level' => $context['year_level']])['target'];
        }

        // The Assistant must never suggest (or offer to plot) a subject this
        // department doesn't own — same rule sync() enforces on save (see
        // its ownedSubjectCodes check). Filtering it out of the suggestion
        // pool here, rather than only rejecting it at save time, keeps the
        // Assistant from ever showing a "Plot this" the chairperson isn't
        // actually allowed to use.
        $contextSubjects = $this->contextSubjects($context['academic_year'], $context['program'], $context['year_level']);

        $ownedSubjectCodes = $contextSubjects === [] ? [] : array_column(
            (new SubjectModel())
                ->whereIn('subject_code', array_column($contextSubjects, 'code'))
                ->whereIn('sub_department_id', (new SchedulingAuthorization())->authorizedSubDeptIds((int) auth()->id()))
                ->findAll(),
            'subject_code'
        );

        $subjects = array_map(static fn (array $s) => [
            'code' => $s['code'], 'title' => $s['title'], 'units' => (float) $s['units'], 'duration' => (float) $s['duration'],
            'weekly_hours' => (float) $s['weekly_hours'], 'is_lab' => (bool) $s['is_lab'], 'color' => $s['color'],
        ], array_values(array_filter($contextSubjects, static fn (array $s) => in_array($s['code'], $ownedSubjectCodes, true))));

        $opts = [
            'headcount'       => $headcount,
            'headcount_known' => $known,
            'grid_start'      => $this->toMinutes('07:00'),
            'grid_end'        => $this->toMinutes('17:30'),
            'faculty'         => $this->facultyForSuggestions($context),
            'max_units'       => (float) (setting('Academic.maxFacultyUnits') ?? 24),
            'rules'           => $this->assistantRules(),
        ];

        $engine = new ScheduleSuggester();
        $rooms  = $this->rooms();

        return $this->response->setJSON([
            'ok'              => true,
            'headcount'       => $headcount,
            'headcount_known' => $known,
            'rules'           => $engine->normalizeRules($opts['rules']),
            'defaults'        => ScheduleSuggester::DEFAULT_RULES,
            'improvements'    => $engine->improve($subjects, $blocks, $others, $rooms, $opts),
        ] + $engine->suggest($subjects, $blocks, $others, $rooms, $opts));
    }

    /**
     * The scheduling rules for this chairperson's department (stored per
     * department in the settings table, so every chairperson of the
     * department sees the same assistant). Falls back to the defaults.
     */
    private function assistantRules(): array
    {
        $engine  = new ScheduleSuggester();
        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        if ($subDept === null) {
            return $engine->normalizeRules([]);
        }

        try {
            $raw   = service('settings')->get('Scheduling.assistantRules', 'dept:' . (int) $subDept['sub_dept_id']);
            $saved = is_string($raw) ? json_decode($raw, true) : null;
        } catch (\Throwable $e) {
            $saved = null;
        }

        return $engine->normalizeRules(is_array($saved) ? $saved : []);
    }

    /**
     * Save the department's assistant rules. Anything invalid is clamped to
     * a safe value by the engine, never stored as-is. Audited.
     */
    public function saveAssistantRules()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->fail('Invalid payload.');
        }

        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        if ($subDept === null) {
            return $this->fail('You are not assigned to a department.');
        }

        $before = $this->assistantRules();
        $rules  = (new ScheduleSuggester())->normalizeRules(! empty($payload['reset']) ? [] : $payload);

        service('settings')->set('Scheduling.assistantRules', json_encode($rules), 'dept:' . (int) $subDept['sub_dept_id']);

        $this->audit('scheduling.assistant_rules', 'sub_department', $subDept['sub_dept_id'], $before, $rules);

        return $this->response->setJSON(['ok' => true, 'rules' => $rules]);
    }

    /**
     * This department's faculty as the assistant needs them: availability
     * and subject preferences for the term being plotted (falling back to the
     * upcoming term), and current unit load in that term EXCLUDING the
     * section being plotted — the same accounting sync() uses for the cap.
     *
     * @return list<array>
     */
    private function facultyForSuggestions(array $context): array
    {
        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        if ($subDept === null) {
            return [];
        }

        $db = db_connect();

        // Affiliated faculty (primary or secondary), not just this
        // department's own primary roster — a faculty member whose home
        // department is elsewhere but who's also affiliated here is a
        // legitimate assistant-suggestion candidate.
        $affiliatedIds = (new FacultyDepartmentModel())->userIdsForDepartment((int) $subDept['sub_dept_id']);

        if ($affiliatedIds === []) {
            return [];
        }

        $faculty = $db->table('users u')
            ->select('u.id, u.username, u.employment_status')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'faculty')
            ->whereIn('u.id', $affiliatedIds)
            ->where('u.deleted_at', null)
            ->orderBy('u.username')
            ->get()->getResultArray();

        if ($faculty === []) {
            return [];
        }

        $ids  = array_map(static fn (array $f) => (int) $f['id'], $faculty);
        $term = null;

        foreach ((new AcademicYearModel())->findAll() as $row) {
            if (AcademicYearModel::format($row['label'], $row['semester']) === $context['academic_year']) {
                $term = $row;
                break;
            }
        }

        $term ??= (new AcademicYearModel())->upcomingTerm();

        $availability = [];
        $preferences  = [];

        if ($term !== null) {
            foreach ((new FacultyAvailabilityModel())->whereIn('user_id', $ids)->where('ay_id', (int) $term['ay_id'])->findAll() as $row) {
                $availability[(int) $row['user_id']][] = $row;
            }

            $prefRows = $db->table('faculty_subject_preferences p')
                ->select('p.user_id, p.preference_level, s.subject_code')
                ->join('subjects s', 's.subject_id = p.subject_id')
                ->whereIn('p.user_id', $ids)->where('p.ay_id', (int) $term['ay_id'])
                ->get()->getResultArray();

            foreach ($prefRows as $row) {
                $preferences[(int) $row['user_id']][$row['subject_code']] = $row['preference_level'];
            }
        }

        $unitRows = $db->table('faculty_assignments fa')
            ->select('fa.user_id, COALESCE(SUM(s.units), 0) AS total')
            ->join('subjects s', 's.subject_code = fa.subject_code')
            ->whereIn('fa.user_id', $ids)
            ->where('fa.academic_year', $context['academic_year'])
            ->groupStart()
                ->where('fa.program !=', $context['program'])
                ->orWhere('fa.year_level !=', $context['year_level'])
                ->orWhere('fa.section !=', $context['section'])
            ->groupEnd()
            ->groupBy('fa.user_id')
            ->get()->getResultArray();

        $units = array_column($unitRows, 'total', 'user_id');

        return array_map(static fn (array $f) => [
            'id'                => (int) $f['id'],
            'name'              => $f['username'],
            'employment_status' => $f['employment_status'],
            'availability'      => $availability[(int) $f['id']] ?? [],
            'preferences'       => $preferences[(int) $f['id']] ?? [],
            'units'             => (float) ($units[$f['id']] ?? 0),
        ], $faculty);
    }

    /**
     * Faculty-accepted swaps awaiting this chairperson's decision, scoped to
     * their department's programs.
     */
    private function swapApprovals(): array
    {
        $subDept   = (new DepartmentModel())->subDeptOfUser((int) auth()->id());
        $subDeptId = $subDept['sub_dept_id'] ?? null;

        $rows = db_connect()->table('swap_requests sr')
            ->select('sr.swap_id, r.username AS requester, t.username AS target,'
                . ' fa1.program a1_prog, fa1.year_level a1_yl, fa1.section a1_sec, fa1.subject_code a1_code,'
                . ' fa2.program a2_prog, fa2.year_level a2_yl, fa2.section a2_sec, fa2.subject_code a2_code,'
                . ' su1.sub_department_id a1_subdept')
            ->join('faculty_assignments fa1', 'fa1.assignment_id = sr.assignment_id')
            ->join('faculty_assignments fa2', 'fa2.assignment_id = sr.requested_assignment_id', 'left')
            ->join('subjects su1', 'su1.subject_code = fa1.subject_code', 'left')
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
            // Approval authority follows SUBJECT ownership (the same rule
            // SchedulingAuthorization::canManageSubject() applies
            // everywhere else), not the program the class happens to be
            // taught under. A subject owned by one department is
            // routinely taken by another program's students (e.g.
            // ITE101/ITE102 are IT-owned subjects taught to BSCS sections
            // too) — filtering by the enrolled program's department hid
            // the swap from the chairperson who actually owns the
            // subject, and would have shown it to whichever department
            // merely happens to take it instead.
            if ($subDeptId !== null && (int) ($row['a1_subdept'] ?? 0) !== (int) $subDeptId) {
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

        // Department ownership: only the chair who owns the SUBJECT being
        // swapped decides — not whichever department the class's own
        // program belongs to (see swapApprovals()'s docblock for why;
        // same bug, same fix, needed here too since this is the actual
        // approve/reject action, not just the list).
        $subDept   = (new DepartmentModel())->subDeptOfUser((int) auth()->id());
        $subDeptId = $subDept['sub_dept_id'] ?? null;
        $subject   = (new SubjectModel())->where('subject_code', $offered['subject_code'])->first();

        if ($subDeptId !== null && (int) ($subject['sub_department_id'] ?? 0) !== (int) $subDeptId) {
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

        // Affiliated faculty (primary or secondary) — see
        // facultyForSuggestions() for the same reasoning.
        $affiliatedIds = (new FacultyDepartmentModel())->userIdsForDepartment((int) $subDept['sub_dept_id']);

        if ($affiliatedIds === []) {
            return $this->response->setJSON(['faculty' => []]);
        }

        $faculty = db_connect()->table('users u')
            ->select('u.id, u.username, u.employment_status')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'faculty')
            ->whereIn('u.id', $affiliatedIds)
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
                $row['day_of_week'] = (int) $row['day_of_week'];
                $row['is_available'] = (int) $row['is_available'];
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
     * Live search for the "Add a New Subject" modal — any ACTIVE subject
     * system-wide whose code starts with the typed prefix, regardless of
     * which program owns it. Lets a chairperson discover and reuse an
     * existing shared subject (e.g. ITE 101) before typing out a full
     * duplicate, rather than finding out only after submitting.
     */
    public function searchSubjects()
    {
        $query = strtoupper(trim((string) ($this->request->getGet('q') ?? '')));

        if ($query === '') {
            return $this->response->setJSON(['ok' => true, 'subjects' => []]);
        }

        $rows = (new SubjectModel())
            ->where('is_active', 1)
            ->like('subject_code', $query, 'after')
            ->orderBy('subject_code')
            ->findAll(8);

        return $this->response->setJSON(['ok' => true, 'subjects' => array_map([self::class, 'formatCatalogSubject'], $rows)]);
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

            // 'section' depends on program + year_level, which a generic,
            // GET-query-string-based $filters['section'] can't know for a
            // JSON POST (no query string at all here) — sectionOptions()
            // has to guess a default in that case, and the save's actual
            // program/year_level don't necessarily match that guess (e.g.
            // a borrowing chairperson's own program isn't first in the
            // list). Validate directly against sections for THIS payload's
            // already-resolved program/year_level/academic_year instead of
            // the precomputed, guessed list.
            if ($key === 'section') {
                $allowed = array_values(array_unique(array_filter(array_map(
                    'trim',
                    array_column(
                        db_connect()->table('sections')
                            ->select('section')
                            ->where('program', $context['program'])
                            ->where('year_level', $context['year_level'])
                            ->where('academic_year', $context['academic_year'])
                            ->get()
                            ->getResultArray(),
                        'section'
                    )
                ), static fn ($n) => $n !== '')));
            }

            if (! isset($payload['context'][$key]) || ! in_array($payload['context'][$key], $allowed, true)) {
                // 'section' failing with nothing valid to offer almost
                // always means exactly one thing: no section exists yet
                // for this program/year level/term — a setup gap, not a
                // bug. Say that plainly instead of a bare "invalid
                // context" toast that gives no clue what to actually do.
                if ($key === 'section' && $allowed === []) {
                    // Only the program's OWNING department may create a
                    // section for it (see createSection()'s own
                    // programsOfSubDept() check) — a borrowing
                    // chairperson plotting a subject their department
                    // owns, for a program they don't, can't be told to
                    // "create one" themselves; they'd just hit a second
                    // rejection. Route them to the right workflow instead.
                    $subDept     = (new DepartmentModel())->subDeptOfUser((int) auth()->id());
                    $ownPrograms = $subDept === null ? [] : (new DepartmentModel())->programsOfSubDept((int) $subDept['sub_dept_id']);
                    $owns        = in_array($context['program'], $ownPrograms, true);

                    $message = "No section exists yet for {$context['program']}, {$context['year_level']} "
                        . "({$context['academic_year']}). ";
                    $message .= $owns
                        ? 'Create one on the Sections page before plotting.'
                        : "That program's own chairperson needs to create it — submit a request via Shared Subject Requests.";

                    return $this->fail($message);
                }
                return $this->fail("Invalid or missing context: {$key}");
            }
            $context[$key] = $payload['context'][$key];
        }


        $subjectsByCode = array_column($this->contextSubjects($context['academic_year'], $context['program'], $context['year_level']), null, 'code');

        // Program ownership generates demand; subject ownership controls
        // delivery and scheduling (see SchedulingAuthorization::
        // canManageSubject()). A program may only PLOT a subject directly
        // here when its own department owns that subject — a subject
        // borrowed from another department's catalog must go through a
        // Shared Subject Request instead, so the owning department (who
        // actually knows its own faculty's availability/load) decides the
        // placement. Rows already plotted before this rule existed are
        // grandfathered (left editable/removable) rather than hard-blocked.
        $ownedSubjectCodes = $subjectsByCode === [] ? [] : array_column(
            (new SubjectModel())
                ->whereIn('subject_code', array_keys($subjectsByCode))
                ->whereIn('sub_department_id', (new SchedulingAuthorization())->authorizedSubDeptIds((int) auth()->id()))
                ->findAll(),
            'subject_code'
        );

        // Subjects this section already has plotted. If the curriculum has since
        // moved on (a new published version, a subject moved to another term),
        // these rows become "stale": still stored, but no longer valid. They
        // must not make every future save fail — that would leave the section
        // impossible to edit or clean up — so a save drops them and says so.
        $alreadyPlotted = array_column(
            db_connect()->table('plotted_schedules')
                ->select('subject_code')
                ->where([
                    'academic_year' => $context['academic_year'],
                    'program'       => $context['program'],
                    'year_level'    => $context['year_level'],
                    'section'       => $context['section'],
                ])
                ->distinct()
                ->get()
                ->getResultArray(),
            'subject_code'
        );
        $dropped = [];

        // A room can be retired (deactivated or deleted, e.g. a whole floor is
        // closed) while classes are still plotted in it. Those classes must
        // stay editable and removable, so a block that is unchanged from what
        // is already stored keeps its retired room. Choosing a retired room
        // for anything new is still rejected below.
        $activeRoomNames = array_column($this->rooms(), 'name');
        $storedPlacements = [];
        // Faculty already assigned to a subject this department does NOT
        // own (grandfathered/plotted by the actual owner) — preserved
        // as-is below rather than re-validated against the acting
        // chairperson's own faculty roster, since it was never theirs to
        // assign in the first place. See the ownedSubjectCodes guard above.
        $storedProfByCode = [];

        foreach (db_connect()->table('plotted_schedules')
            ->select('subject_code, day, start_time, room, prof')
            ->where([
                'academic_year' => $context['academic_year'],
                'program'       => $context['program'],
                'year_level'    => $context['year_level'],
                'section'       => $context['section'],
            ])
            ->get()
            ->getResultArray() as $stored) {
            $storedPlacements[$stored['subject_code'] . '|' . $stored['day'] . '|' . substr($stored['start_time'], 0, 5) . '|' . $stored['room']] = true;
            $storedProfByCode[$stored['subject_code']] = $stored['prof'];
        }

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
                // Stale (already stored for this section) → drop it. A subject
                // that was never plotted here is still rejected as invalid.
                if (in_array($block['code'], $alreadyPlotted, true)) {
                    $dropped[$block['code']] = true;

                    continue;
                }

                return $this->fail("Unknown subject: {$block['code']}");
            }

            $subject = $subjectsByCode[$block['code']];

            if (! in_array($block['code'], $ownedSubjectCodes, true) && ! in_array($block['code'], $alreadyPlotted, true)) {
                return $this->fail("{$block['code']} is owned by another department — request it through Shared Subject Requests so that department can assign faculty and plot it.");
            }

            $unchangedInRetiredRoom = isset($storedPlacements["{$block['code']}|{$block['day']}|{$block['start']}|{$block['room']}"]);

            if (! in_array($block['room'], $activeRoomNames, true) && ! $unchangedInRetiredRoom) {
                return $this->fail("{$block['room']} is no longer an available room. Choose another room for {$block['code']}.");
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

            $isOwnedCode = in_array($subject['code'], $ownedSubjectCodes, true);

            $blocks[] = [
                'day'        => $block['day'],
                'code'       => $subject['code'],
                'title'      => $subject['title'],
                // Owned: resolved below once faculty_id is validated against
                // this department's roster. Grandfathered/not-owned: never
                // this chairperson's to (re)assign — keep whatever the
                // actual owner already has on file, untouched.
                'prof'       => $isOwnedCode ? null : ($storedProfByCode[$subject['code']] ?? null),
                'faculty_id' => $isOwnedCode ? $facultyId : null,
                'room'       => $block['room'],
                'theme'      => $subject['color'],
                'start'      => $block['start'],
                'end'        => $block['end'],
                'time_label' => $block['time_label'],
            ];
        }

        // Faculty is chairperson-assigned per subject, not per meeting — every
        // block for the same code in this save must agree on who's teaching it.
        // Only ever built from subjects this department owns (see above) — a
        // grandfathered block's faculty_id is dropped, never validated
        // against this department's own roster.
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
            $facultyIds = array_values(array_unique($facultyByCode));

            $facultyRows = db_connect()->table('users u')
                ->select('u.id, u.username')
                ->join('auth_groups_users g', 'g.user_id = u.id')
                ->where('g.group', 'faculty')
                ->whereIn('u.id', $facultyIds)
                ->where('u.deleted_at', null)
                ->get()
                ->getResultArray();

            $usernameById = array_column($facultyRows, 'username', 'id');

            // Faculty eligibility is now affiliation-based (faculty_departments:
            // primary OR secondary), not "must be this chairperson's own home
            // department" — a faculty member whose primary department is
            // elsewhere can still be assigned here as long as they're
            // affiliated with the department that actually OWNS this subject.
            // $ownedSubjectCodes (above) already proved the chairperson
            // themselves is authorized to manage that subject; this only
            // answers "is this specific faculty member eligible to teach it."
            $ownerDeptBySubject = array_column(
                (new SubjectModel())->whereIn('subject_code', array_keys($facultyByCode))->findAll(),
                'sub_department_id',
                'subject_code'
            );

            $affiliations = new FacultyDepartmentModel();

            foreach ($facultyByCode as $code => $facultyId) {
                if (! isset($usernameById[$facultyId])) {
                    return $this->fail("Selected faculty for {$code} is not a valid faculty member.");
                }

                $ownerDeptId = $ownerDeptBySubject[$code] ?? null;

                if ($ownerDeptId !== null && ! $affiliations->isAffiliatedWith((int) $facultyId, (int) $ownerDeptId)) {
                    return $this->fail("{$usernameById[$facultyId]} is not affiliated with the department that owns {$code}.");
                }
            }

            // Faculty unit-load cap: adding these subjects must not push
            // anyone over the configured maximum, counting their OTHER
            // current assignments in this AY — this section's own prior
            // assignment (if any) is excluded, since this save replaces it.
            // totalLoadUnits() counts BOTH legacy section-tuple assignments
            // AND offering-based ones, so a faculty member already loaded up
            // via the offering/component scheduling path (OfferingController)
            // can't be silently pushed over the cap from this path too.
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

            $assignments = new FacultyAssignmentModel();

            foreach ($gainByFaculty as $facultyId => $gain) {
                $existingUnits = $assignments->totalLoadUnits((int) $facultyId, $context['academic_year'], [
                    'program'    => $context['program'],
                    'year_level' => $context['year_level'],
                    'section'    => $context['section'],
                ]);

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

        // replaceDraft() below replaces this section's ENTIRE stored set with
        // whatever is submitted — if the client simply omits a block (e.g.
        // its "Remove from schedule" control was clicked, or it silently
        // dropped out of a stale local copy of the grid), that block
        // disappears. For a subject this department doesn't own, that
        // would let a borrowing chairperson un-plot another department's
        // class merely by leaving it out of their save — the same
        // delivery decision sync()'s per-block guard above already refuses
        // to let them MAKE. Any already-stored, not-owned subject missing
        // from this submission is restored here, unchanged, before saving.
        $plottedCodesInSave = array_column($blocks, 'code');
        $missingNonOwnedCodes = array_values(array_diff($alreadyPlotted, $ownedSubjectCodes, $plottedCodesInSave));

        $preservedRows = $missingNonOwnedCodes === [] ? [] : db_connect()->table('plotted_schedules')
            ->select('day, start_time, end_time, time_label, subject_code, subject_title, prof, room, theme, status')
            ->where([
                'academic_year' => $context['academic_year'],
                'program'       => $context['program'],
                'year_level'    => $context['year_level'],
                'section'       => $context['section'],
            ])
            ->whereIn('subject_code', $missingNonOwnedCodes)
            ->get()
            ->getResultArray();

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

        foreach ($preservedRows as $row) {
            $rows[] = [
                'academic_year' => $context['academic_year'],
                'program'       => $context['program'],
                'year_level'    => $context['year_level'],
                'section'       => $context['section'],
                'day'           => (int) $row['day'],
                'start_time'    => $row['start_time'],
                'end_time'      => $row['end_time'],
                'time_label'    => $row['time_label'],
                'subject_code'  => $row['subject_code'],
                'subject_title' => $row['subject_title'],
                'prof'          => $row['prof'],
                'room'          => $row['room'],
                'theme'         => $row['theme'],
                'status'        => $row['status'],
            ];
        }

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

        return $this->response->setJSON([
            'ok'      => true,
            'saved'   => count($rows),
            // Subjects removed because they're no longer in this term's curriculum.
            'dropped' => array_keys($dropped),
        ]);
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

        $overrideSubjectCode = (string) $payload['subject_code'];

        $overrideSubject = (new SubjectModel())->where('subject_code', $overrideSubjectCode)->where('is_active', 1)->first();

        if ($overrideSubject === null) {
            return $this->fail("{$overrideSubjectCode} is not a recognized subject. Please contact the registrar.");
        }

        // Assigning faculty against an availability/schedule conflict is a
        // delivery decision — only the subject's owning department (or an
        // admin/registrar override) may record it.
        $auth = (new SchedulingAuthorization())->authorizeManageSubject((int) auth()->id(), (int) $overrideSubject['subject_id']);

        if (! $auth['ok']) {
            return $this->fail("{$overrideSubjectCode} is owned by another department — only that department may assign its faculty.");
        }

        (new FacultyOverrideModel())->insert([
            'chairperson_id' => (int) auth()->id(),
            'faculty_id'     => (int) $payload['faculty_id'],
            'academic_year'  => (string) $payload['academic_year'],
            'program'        => (string) $payload['program'],
            'year_level'     => (string) $payload['year_level'],
            'section'        => (string) $payload['section'],
            'subject_code'   => $overrideSubjectCode,
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
            // Same reasoning as sync(): $filters['section'] is a generic,
            // GET-query-string-guessed list that doesn't necessarily match
            // THIS payload's actual program/year_level for a JSON POST —
            // validate it directly against the real sections for the
            // payload's own program/year_level/academic_year instead.
            $allowed = $key === 'section'
                ? array_values(array_unique(array_filter(array_map(
                    'trim',
                    array_column(
                        db_connect()->table('sections')
                            ->select('section')
                            ->where('program', $payload['program'])
                            ->where('year_level', $payload['year_level'])
                            ->where('academic_year', $payload['academic_year'])
                            ->get()
                            ->getResultArray(),
                        'section'
                    )
                ), static fn ($n) => $n !== '')))
                : $filters[$key];

            if (! in_array($payload[$key], $allowed, true)) {
                if ($key === 'section' && $allowed === []) {
                    return $this->fail(
                        "No section exists yet for {$payload['program']}, {$payload['year_level']} "
                        . "({$payload['academic_year']})."
                    );
                }
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

        $this->audit('sections.capacity', 'section', $section['section_id'], ['capacity' => $section['capacity']], ['capacity' => $capacity], $payload['reason'] ?? null);

        return $this->response->setJSON(['ok' => true, 'capacity' => $capacity]);
    }

    /**
     * Sections page: create sections for the department up front (rather
     * than waiting for one to be plotted), and assign/unassign students to
     * them. A section created here IS automatically selectable in the
     * Schedule Plotting grid — its section list is read live from the
     * `sections` table (see sectionOptions()), never a fixed/hardcoded set.
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

        // Subject enrollments can exist for students who are not on the
        // roster (for example irregular students). They block deleting the
        // section, so the page must be able to show them.
        $enrollmentsBySection = [];

        if ($sectionIds !== []) {
            foreach (db_connect()->table('enrollments')
                ->select('section_id, COUNT(DISTINCT user_id) AS students, GROUP_CONCAT(DISTINCT subject_code ORDER BY subject_code SEPARATOR ", ") AS subjects')
                ->whereIn('section_id', $sectionIds)
                ->groupBy('section_id')
                ->get()
                ->getResultArray() as $row) {
                $enrollmentsBySection[(int) $row['section_id']] = ['students' => (int) $row['students'], 'subjects' => $row['subjects']];
            }
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
            'academicYears'   => $this->creatableAcademicYears(),
            'activeAcademicYear' => (new AcademicYearModel())->activeFormatted(),
            'sections'        => $sections,
            'rosterBySection' => $rosterBySection,
            'enrollmentsBySection' => $enrollmentsBySection,
            'students'        => $students,
        ]);
    }

    /**
     * Create a section for the department up front. Free-typed name — the
     * Schedule Plotting grid's section list is read live from this table,
     * not restricted to any fixed/hardcoded set (see sectionOptions()).
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

        if (! in_array($payload['academic_year'], $this->creatableAcademicYears(), true)) {
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
            'program_id'    => DepartmentModel::programIdOfName($payload['program']),
            'year_level'    => $payload['year_level'],
            'section'       => $sectionName,
            'capacity'      => $capacity,
            'origin'        => 'manual',
        ]);

        $this->audit('sections.create', 'section', $sections->getInsertID(), null, $sections->find($sections->getInsertID()));

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

        if ($rosterCount > 0) {
            return $this->fail("{$section['section']} still has {$rosterCount} student" . ($rosterCount === 1 ? '' : 's') . ' on its roster. Remove them first.');
        }

        $enrolled = db_connect()->table('enrollments')
            ->select('COUNT(DISTINCT user_id) AS students, GROUP_CONCAT(DISTINCT subject_code ORDER BY subject_code SEPARATOR ", ") AS subjects')
            ->where('section_id', $section['section_id'])
            ->get()
            ->getRowArray();

        if ((int) ($enrolled['students'] ?? 0) > 0) {
            $students = (int) $enrolled['students'];

            return $this->fail("{$section['section']} can't be deleted yet: {$students} student" . ($students === 1 ? ' is' : 's are')
                . " still enrolled in its subjects ({$enrolled['subjects']}). Drop those enrollments first.");
        }

        (new SectionModel())->delete($section['section_id']);

        $this->audit('sections.delete', 'section', $section['section_id'], $section, null);

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

        $result = (new SectionBalancer())->assignStudent(
            (int) $student['id'],
            (int) $section['section_id'],
            (int) auth()->id(),
            'chairperson',
            isset($payload['override_reason']) && is_string($payload['override_reason']) ? $payload['override_reason'] : null
        );

        if (! $result['ok']) {
            return $this->response->setStatusCode(400)->setJSON([
                'ok'            => false,
                'error'         => $result['error'],
                'over_capacity' => $result['over_capacity'] ?? false,
            ]);
        }

        return $this->response->setJSON(['ok' => true, 'over_capacity' => $result['over_capacity'] ?? false]);
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

        $this->audit('sections.unassign', 'section', $current['section_id'], ['user_id' => (int) $payload['user_id'], 'section_id' => (int) $current['section_id']], ['section_id' => null]);

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Read-only preview of what "Rebalance Sections" would do for one
     * program + year level + term: current vs proposed distribution, how
     * many students move, and the reason — nothing is written.
     */
    public function rebalancePreview()
    {
        $group = $this->ownGroup($this->request->getJSON(true));

        if (is_string($group)) {
            return $this->fail($group);
        }

        $analysis = (new SectionBalancer())->analyze(...$group);

        return $this->response->setJSON(['ok' => true, 'preview' => $this->presentAnalysis($analysis)]);
    }

    /**
     * Applies the rebalance. Requires a reason and an explicit confirmation
     * flag, so it can never happen as a side effect of viewing the page.
     */
    public function rebalanceApply()
    {
        $payload = $this->request->getJSON(true);
        $group   = $this->ownGroup($payload);

        if (is_string($group)) {
            return $this->fail($group);
        }

        if (empty($payload['confirm'])) {
            return $this->fail('Confirm the rebalance first.');
        }

        if (! $this->isActiveYear($group[0])) {
            return $this->fail('Only the active academic year can be rebalanced.');
        }

        $result = (new SectionBalancer())->apply($group[0], $group[1], $group[2], (int) auth()->id(), 'chairperson', (string) ($payload['reason'] ?? ''));

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON([
            'ok'      => true,
            'moved'   => $result['moved'],
            'created' => $result['created'],
            'closed'  => $result['closed'],
        ]);
    }

    /**
     * Manual lifecycle control for one section: proposed / open / finalized /
     * locked / closed, and the auto-balance switch. Audited with a reason.
     */
    public function setSectionState()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['section_id'])) {
            return $this->fail('Missing section.');
        }

        $section = $this->ownSection((int) $payload['section_id']);

        if ($section === null) {
            return $this->fail('That section does not belong to your department.');
        }

        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($reason === '') {
            return $this->fail('A reason is required.');
        }

        $update = [];

        if (isset($payload['status'])) {
            if (! in_array($payload['status'], ['proposed', 'open', 'finalized', 'locked', 'closed'], true)) {
                return $this->fail('Invalid status.');
            }

            if ($payload['status'] === 'closed'
                && db_connect()->table('users')->where('section_id', $section['section_id'])->countAllResults() > 0) {
                return $this->fail("Move this section's students out before closing it.");
            }

            $update['status'] = $payload['status'];
        }

        if (array_key_exists('auto_balance', $payload)) {
            $update['auto_balance'] = $payload['auto_balance'] ? 1 : 0;
        }

        if ($update === []) {
            return $this->fail('Nothing to change.');
        }

        (new SectionModel())->update($section['section_id'], $update);

        $this->audit('sections.state', 'section', $section['section_id'], array_intersect_key($section, $update), $update, $reason);

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Validates {academic_year, program, year_level} against this
     * chairperson's department. Returns the triple, or an error string.
     *
     * @return list<string>|string
     */
    private function ownGroup($payload)
    {
        if (! is_array($payload)) {
            return 'Invalid payload.';
        }

        foreach (['academic_year', 'program', 'year_level'] as $key) {
            if (empty($payload[$key]) || ! is_string($payload[$key])) {
                return "Missing field: {$key}";
            }
        }

        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        if ($subDept === null || ! in_array($payload['program'], (new DepartmentModel())->programsOfSubDept((int) $subDept['sub_dept_id']), true)) {
            return 'Invalid program.';
        }

        if (! in_array($payload['year_level'], self::FILTERS['year_level'], true)) {
            return 'Invalid year level.';
        }

        return [$payload['academic_year'], $payload['program'], $payload['year_level']];
    }

    private function presentAnalysis(array $a): array
    {
        return [
            'policy'            => $a['policy'],
            'sections'          => array_map(static fn ($s) => [
                'section_id' => (int) $s['section_id'], 'section' => $s['section'], 'status' => $s['status'],
                'count' => $s['count'], 'can_rebalance' => $s['can_rebalance'], 'block_reason' => $s['block_reason'],
            ], $a['sections']),
            'free_sections'     => array_map(static fn ($s) => $s['section'], $a['free_sections']),
            'current_sizes'     => $a['current_sizes'],
            'proposed_sizes'    => $a['plan']['sizes'],
            'students_affected' => $a['students_affected'],
            'has_changes'       => $a['has_changes'],
            'warnings'          => $a['plan']['warnings'],
            'explanation'       => $a['explanation'],
            'locked_count'      => $a['locked_count'],
        ];
    }

    private function audit(string $action, string $entity, $entityId, ?array $before, ?array $after, ?string $reason = null): void
    {
        (new AuditLogModel())->record($action, $entity, $entityId, $before, $after, $reason, (int) auth()->id(), 'chairperson');
    }

    /**
     * The section row, only if it belongs to a sub-department the
     * logged-in user is authorized to schedule for (their own, or one
     * they've been explicitly delegated). See SchedulingAuthorization.
     */
    private function ownSection(int $sectionId): ?array
    {
        return (new SchedulingAuthorization())->ownSection((int) auth()->id(), $sectionId);
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

        // A chairperson schedules for their own department's programs...
        $ownPrograms = $subDept === null ? [] : $deptModel->programsOfSubDept((int) $subDept['sub_dept_id']);

        // ...PLUS any other program whose curriculum needs a subject this
        // department owns — e.g. General Subjects (no programs of its own)
        // still needs BSIT to show up here, since it's the only place its
        // RED101/GEC101/etc. classes can actually get plotted. The Classes
        // rail itself (see index()'s $subjects filter) still only ever
        // offers the subjects this department owns, even on a borrowed
        // program — sync() enforces the same rule on save.
        $borrowingPrograms = $this->borrowingProgramNames();

        $filters['program'] = array_values(array_unique(array_merge($ownPrograms, $borrowingPrograms)));

        // Sections come from what the department actually has, not a fixed
        // list, so a section created on the Sections page is plottable at once.
        $filters['section'] = $this->sectionOptions($filters['program']);

        return $filters;
    }

    /**
     * Every program (by display name) whose curriculum includes at least
     * one subject this chairperson's department(s) own, besides their own
     * programs — the "someone else needs my subject" side of scheduling.
     * Live, computed from current curriculum_subjects every call, never
     * cached.
     *
     * @return list<string>
     */
    private function borrowingProgramNames(): array
    {
        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds((int) auth()->id());

        if ($subDeptIds === []) {
            return [];
        }

        $programIds = array_column(
            db_connect()->table('curriculum_subjects cs')
                ->select('c.program_id')
                ->distinct()
                ->join('curriculum c', 'c.curriculum_id = cs.curriculum_id')
                ->join('subjects s', 's.subject_id = cs.subject_id')
                ->whereIn('s.sub_department_id', $subDeptIds)
                ->where('cs.is_active', 1)
                ->where('s.is_active', 1)
                ->get()
                ->getResultArray(),
            'program_id'
        );

        $names = [];

        foreach ($programIds as $programId) {
            $name = DepartmentModel::programNameById((int) $programId);

            if ($name !== null) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Section names for the plotting selector: the sections that actually
     * exist (Sections page) for the program, year level and term being
     * viewed, in natural order (BIT2 before BIT10). A section that only
     * appears in old plotted rows is not offered.
     *
     * When nothing exists for that exact context (say, a year level with no
     * sections yet) it offers that same PROGRAM's section names from other
     * year levels, so a new cohort can still be started — never another
     * program's sections, even one this chairperson also schedules for.
     * Only when the requested program has no sections anywhere (or the
     * request carries no program context at all, e.g. a JSON save) does it
     * fall back to the full department-wide list. A department with no
     * programs, or no sections at all yet, genuinely has nothing to offer —
     * this returns an empty list rather than any placeholder/fake names, so
     * the UI shows an honest empty state instead of fabricated options.
     *
     * @param list<string> $programs
     *
     * @return list<string>
     */
    private function sectionOptions(array $programs): array
    {
        if ($programs === []) {
            return [];
        }

        $names = static function ($query): array {
            $rows = array_column($query->select('section')->distinct()->get()->getResultArray(), 'section');
            $rows = array_values(array_unique(array_filter(array_map('trim', $rows), static fn ($n) => $n !== '')));
            natcasesort($rows);

            return array_values($rows);
        };

        $db       = db_connect();
        $program  = $this->request->getGet('program');
        $year     = $this->request->getGet('year_level');
        $ayLabel  = $this->request->getGet('ay_label');
        $semester = $this->request->getGet('semester');
        $academicYear = (is_string($ayLabel) && is_string($semester))
            ? AcademicYearModel::format($ayLabel, $semester)
            : null;

        // The year_level param is absent exactly when contextFromRequest()
        // is about to smart-jump to a borrowed-subject year level (see
        // bestYearLevelForBorrowedSubject() — the Program switch's client
        // side deliberately strips year_level/section so that jump can
        // happen). Without this, sectionOptions() wouldn't know which year
        // level contextFromRequest() is about to land on, and would
        // broaden to every year level's sections instead (e.g. leaking
        // IT2/IT3/IT4 into a 1st-Year-only view).
        if ($year === null && is_string($program) && $academicYear !== null) {
            $year = $this->bestYearLevelForBorrowedSubject($program, $academicYear);
        }

        // Narrow to what is being viewed when the request says so (page
        // loads); JSON saves carry no query string and get the department-wide list.
        if (is_string($program) && in_array($program, $programs, true)) {
            if (is_string($year) && in_array($year, self::FILTERS['year_level'], true)) {
                $scoped = $db->table('sections')->where('program', $program)->where('year_level', $year);

                if ($academicYear !== null) {
                    $scoped->where('academic_year', $academicYear);
                }

                // A specific year level IS known/targeted here — whatever
                // sections exist for it (even none) is the honest answer.
                // Previously this fell through to other year levels' names
                // when empty, silently presenting a DIFFERENT year's
                // section as if it were valid for this one — the chair
                // would pick it, and the save would then correctly reject
                // it as invalid, with no clue why. An empty list instead
                // lets the view show a plain "No section created yet" —
                // the real, immediate problem — not a disguised one.
                return $names($scoped);
            }

            // Year level itself isn't known/resolved at all — nothing
            // honest to scope to, so offer this PROGRAM's section names
            // from any year level purely as a starting reference point
            // (never another program's).
            $found = $names($db->table('sections')->where('program', $program));

            if ($found !== []) {
                return $found;
            }
        }

        // No program in the query string at all (e.g. a bare first-login
        // page load, before any filter has been explicitly picked) — scope
        // to just the program AND year level that will actually end up as
        // the default (the same $allowed[0] fallback contextFromRequest()
        // uses for 'program', and self::FILTERS['year_level'][0] for
        // 'year_level'), not every program/year the department owns.
        // Leaving year_level unfiltered here leaked every year level's
        // section names together (e.g. IT1-IT4 all showing under a
        // 1st-Year-only view) the same way leaving program unfiltered
        // leaked another program's section name across programs.
        $defaultProgram   = $programs[0];
        $defaultYearLevel = self::FILTERS['year_level'][0] ?? null;

        if ($defaultYearLevel !== null) {
            $found = $names($db->table('sections')->where('program', $defaultProgram)->where('year_level', $defaultYearLevel));
            if ($found !== []) {
                return $found;
            }
        }

        return $names($db->table('sections')->where('program', $defaultProgram));
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
        // sections all just store this one string).
        $ayLabel    = $this->request->getGet('ay_label');
        $aySemester = $this->request->getGet('semester');
        $yearLevelRequested = $this->request->getGet('year_level');

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

        // Smart default for a BORROWED program (one that needs this
        // department's subject but isn't owned by it — see
        // borrowingProgramNames()): when the chairperson didn't explicitly
        // pick a year level, jump straight to whichever year level actually
        // has this department's subject in that program's curriculum this
        // semester, instead of defaulting to 1st Year and making them click
        // through every year level hoping to find where the work is.
        if ($yearLevelRequested === null) {
            $smartYearLevel = $this->bestYearLevelForBorrowedSubject($context['program'], $context['academic_year']);
            if ($smartYearLevel !== null) {
                $context['year_level'] = $smartYearLevel;
            }
        }

        return $context;
    }

    /**
     * The lowest year level, for the given program and term, whose
     * curriculum requires a subject owned by this chairperson's
     * authorized department(s). Null if the program isn't a borrowing
     * program, or has no such requirement this semester.
     */
    private function bestYearLevelForBorrowedSubject(string $program, string $academicYear): ?string
    {
        $deptModel = new DepartmentModel();
        $subDept   = $deptModel->subDeptOfUser((int) auth()->id());

        // Only apply to a BORROWED program (not owned by this chairperson's
        // own department) — their own program keeps the normal "1st Year"
        // default, since jumping around would be surprising for the
        // day-to-day case this wasn't designed for.
        $ownPrograms = $subDept === null ? [] : $deptModel->programsOfSubDept((int) $subDept['sub_dept_id']);
        if (in_array($program, $ownPrograms, true)) {
            return null;
        }

        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds((int) auth()->id());
        if ($subDeptIds === []) {
            return null;
        }

        $programId = DepartmentModel::programIdOfName($program);
        if ($programId === null) {
            return null;
        }

        $semester = TermCurriculum::semesterOf($academicYear);
        if ($semester === null) {
            return null;
        }

        $yearLevel = db_connect()->table('curriculum_subjects cs')
            ->select('cs.year_level')
            ->join('curriculum cu', 'cu.curriculum_id = cs.curriculum_id')
            ->join('subjects s', 's.subject_id = cs.subject_id')
            ->where('cu.program_id', $programId)
            ->where('cs.semester', $semester)
            ->where('cs.is_active', 1)
            ->where('s.is_active', 1)
            ->whereIn('s.sub_department_id', $subDeptIds)
            ->orderBy('cs.year_level', 'ASC')
            ->get(1)
            ->getRow('year_level');

        if ($yearLevel === null) {
            return null;
        }

        $labels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        return $labels[((int) $yearLevel) - 1] ?? null;
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

            foreach ($programs as $program) {
                foreach (self::FILTERS['year_level'] as $yearLevel) {
                    if ($this->termSubjectCodes(['academic_year' => $activeYear, 'program' => $program, 'year_level' => $yearLevel]) !== null) {
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

        // Live count of classes (any department's section) that need one of
        // MY owned subjects plotted — mirrors
        // Chairperson\OfferingController::needsPlottingList(), computed
        // fresh from current demand every time this page loads, never
        // cached. See Merged Classes -> "Needs Plotting" to act on them.
        $needsPlottingCount = 0;

        if ($activeYear !== null) {
            $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds((int) auth()->id());

            if ($subDeptIds !== []) {
                $ownedSubjects = db_connect()->table('subjects')
                    ->select('subject_id, subject_code')
                    ->whereIn('sub_department_id', $subDeptIds)
                    ->where('is_active', 1)
                    ->get()
                    ->getResultArray();

                if ($ownedSubjects !== []) {
                    $demandService = new SubjectDemandService();
                    $offeringModel = new CourseOfferingModel();

                    foreach ($ownedSubjects as $subject) {
                        $summary = $demandService->summary((int) $subject['subject_id'], $activeYear);

                        foreach ($summary['by_section'] as $bySection) {
                            if ($bySection['expected_count'] <= 0 || $bySection['section_id'] === null) {
                                continue;
                            }

                            if ($offeringModel->offeringForSection((int) $bySection['section_id'], $subject['subject_code']) === null) {
                                $needsPlottingCount++;
                            }
                        }
                    }
                }
            }
        }

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
                'plotted_classes'     => $plottedClasses,
                'faculty_count'       => $facultyCount,
                'pending_approvals'   => count($this->swapApprovals()),
                'curriculum_set'      => $curriculumSet,
                'curriculum_slots'    => $curriculumSlots,
                'needs_plotting_count' => $needsPlottingCount,
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

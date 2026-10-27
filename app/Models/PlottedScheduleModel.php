<?php

namespace App\Models;

use App\Libraries\ScheduleOverlap;
use App\Libraries\SchedulingAuthorization;
use App\Libraries\SchedulingConflictService;
use CodeIgniter\Model;
use App\Models\OfferingComponentModel;

/**
 * A plotted_schedules row has exactly one of two identities, never both and
 * never mixed:
 *
 *  - offering_id IS NULL  -> legacy identity: the
 *    (academic_year, program, year_level, section) tuple is canonical.
 *  - offering_id IS NOT NULL -> offering identity: course_offerings is
 *    canonical (subject via offering.subject_id, attached sections via
 *    offering_sections). program/year_level/section are NULL on these rows
 *    and MUST NEVER be read as a wildcard, a fallback, or "university-wide" —
 *    they're simply not the identity for this kind of row.
 */
class PlottedScheduleModel extends Model
{
    protected $table      = 'plotted_schedules';
    protected $primaryKey = 'schedule_id';

    protected $allowedFields = [
        'offering_id',
        'offering_component_id',
        'academic_year',
        'program',
        'year_level',
        'section',
        'day',
        'start_time',
        'end_time',
        'time_label',
        'subject_code',
        'subject_title',
        'prof',
        'room',
        'theme',
        'status',
    ];

    protected $useTimestamps = true;

    /**
     * Blocks plotted for one legacy context (AY + program + year level +
     * section), PLUS any offering-linked blocks reachable because that
     * section is attached to an offering. The legacy query is unchanged
     * from before offering_id existed — SQL's own NULL semantics already
     * keep offering-linked rows (whose program/year_level/section are NULL)
     * out of the equality match, so this stays correct without any extra
     * guard.
     */
    public function forContext(string $academicYear, string $program, string $yearLevel, string $section): array
    {
        $legacy = $this->where([
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
            'section'       => $section,
        ])->findAll();

        // $academicYear here is the term-scoped string plotted_schedules is
        // keyed by ("AY2026-2027 · 1st Semester"), but sections.academic_year
        // is year-scoped (every write path uses $context['section_year'] /
        // AcademicYearModel::yearOnly()). Passing the term form straight
        // through never matched a real section row, which silently dropped
        // every offering-linked block from a section's own grid. The raw
        // string is still tried as a fallback for sections rows written
        // before identity became year-scoped.
        $sectionModel = new SectionModel();
        $yearOnly     = AcademicYearModel::yearOnly($academicYear);

        $sectionRow = $sectionModel->forContext($yearOnly, $program, $yearLevel, $section);

        if ($sectionRow === null && $yearOnly !== $academicYear) {
            $sectionRow = $sectionModel->forContext($academicYear, $program, $yearLevel, $section);
        }

        if ($sectionRow === null) {
            return $legacy;
        }

        $offeringIds = $this->offeringIdsForSection((int) $sectionRow['section_id']);

        if ($offeringIds === []) {
            return $legacy;
        }

        $offeringRows = $this->whereIn('offering_id', $offeringIds)->findAll();

        return array_merge($legacy, $offeringRows);
    }

    /**
     * Legacy rows (offering_id IS NULL) for exactly one section's subject —
     * the schedule a section already has plotted before it is merged into an
     * offering. Read-only; used to carry that schedule forward into a new
     * merge instead of starting blank. Offering-linked rows never match: their
     * program/year_level/section are NULL, so the equality match excludes them.
     *
     * @return list<array<string, mixed>>
     */
    public function legacyRowsFor(string $academicYear, string $program, string $yearLevel, string $section, string $subjectCode): array
    {
        return $this->where([
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
            'section'       => $section,
            'subject_code'  => $subjectCode,
        ])->where('offering_id IS NULL')->findAll();
    }

    /**
     * Blocks plotted for every OTHER context in the same academic year.
     *
     * Pass $deptPrograms to restrict results to that list of program display
     * names (used when displaying ghost blocks, so another department's
     * plots never leak into a chairperson's view). Leave it null for
     * school-wide room/faculty conflict checks, where every department's
     * rows must still be considered — in that case, offering-linked rows
     * school-wide are also included, closing the gap where a legacy save
     * could otherwise double-book a room/faculty member already committed
     * to a shared offering elsewhere. (With $deptPrograms set, offering rows
     * are left out of this "ghost block" display path — they don't resolve
     * to a single program the way legacy rows do, and getting that half
     * right isn't required for conflict safety; conflict safety is the
     * $deptPrograms === null path.)
     */
    public function forOtherContexts(string $academicYear, string $program, string $yearLevel, string $section, ?array $deptPrograms = null): array
    {
        if ($deptPrograms === []) {
            return [];
        }

        $query = $this->where('academic_year', $academicYear);

        if ($deptPrograms !== null) {
            $query = $query->whereIn('program', $deptPrograms);
        }

        $legacyOthers = $query
            ->groupStart()
                ->where('program !=', $program)
                ->orWhere('year_level !=', $yearLevel)
                ->orWhere('section !=', $section)
            ->groupEnd()
            ->findAll();

        if ($deptPrograms !== null) {
            return $legacyOthers;
        }

        $offeringRows = $this->where('academic_year', $academicYear)->where('offering_id IS NOT NULL')->findAll();

        return array_merge($legacyOthers, $offeringRows);
    }

    /**
     * Every row currently plotted for one offering.
     */
    public function forOffering(int $offeringId): array
    {
        return $this->where('offering_id', $offeringId)->findAll();
    }

    /**
     * Every row currently plotted for one offering COMPONENT only.
     */
    public function forComponent(int $offeringComponentId): array
    {
        return $this->where('offering_component_id', $offeringComponentId)->findAll();
    }

    /**
     * @return list<int>
     */
    private function offeringIdsForSection(int $sectionId): array
    {
        return array_map('intval', array_column(
            $this->db->table('offering_sections')->select('offering_id')->where('section_id', $sectionId)->get()->getResultArray(),
            'offering_id'
        ));
    }

    /**
     * Replace a context's draft atomically.
     *
     * @param array $rows Fully-prepared row arrays for insertBatch.
     */
    public function replaceDraft(string $academicYear, string $program, string $yearLevel, string $section, array $rows): void
    {
        $this->db->transException(true)->transStart();

        $this->where([
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
            'section'       => $section,
        ])->delete();

        if ($rows !== []) {
            $this->insertBatch($rows);
        }

        $this->db->transComplete();
    }

    /**
     * Replaces an offering's entire schedule (every meeting block), the
     * offering-linked equivalent of replaceDraft(). Validates authorization,
     * subject identity (always server-derived from the offering, never
     * trusted from the caller), within-save overlaps, cohort conflicts
     * across every attached section, and school-wide room/faculty
     * conflicts — all excluding the offering's OWN current rows, so a valid
     * edit never reports a conflict with itself.
     *
     * @param list<array{day: int, start: string, end: string, time_label: string, room: string, faculty_id: ?int}> $blocks
     *
     * @return array{ok: bool, error?: string, conflicts?: array}
     */
    public function syncOfferingSchedule(int $offeringId, int $actorId, array $blocks): array
    {
        $offerings = new CourseOfferingModel();
        $offering  = $offerings->find($offeringId);

        if ($offering === null) {
            return ['ok' => false, 'error' => 'Offering not found.'];
        }

        if ($offering['subject_id'] === null) {
            return ['ok' => false, 'error' => 'This offering has no linked subject and cannot be scheduled.'];
        }

        $subjectId = (int) $offering['subject_id'];

        $auth = (new SchedulingAuthorization())->authorizeManageSubject($actorId, $subjectId);

        if (! $auth['ok']) {
            return ['ok' => false, 'error' => 'You are not authorized to schedule this offering.'];
        }

        $subject = db_connect()->table('subjects')->select('subject_code, subject_name, units')->where('subject_id', $subjectId)->get()->getRowArray();

        if ($subject === null) {
            return ['ok' => false, 'error' => 'Linked subject not found.'];
        }

        if ($blockError = $this->validateBlockShapes($blocks)) {
            return ['ok' => false, 'error' => $blockError];
        }

        $existingIds = array_map('intval', array_column($this->forOffering($offeringId), 'schedule_id'));

        $conflictService = new SchedulingConflictService();
        $preparedBlocks   = $this->attachFacultyUsernames($blocks);

        $cohort = $conflictService->cohortConflicts($offeringId, (string) $offering['academic_year'], $preparedBlocks, $existingIds);

        if (! $cohort['ok']) {
            return ['ok' => false, 'error' => 'This time conflicts with a participating section\'s existing schedule.', 'conflicts' => $cohort['conflicts']];
        }

        $roomFaculty = $conflictService->roomAndFacultyConflicts((string) $offering['academic_year'], $preparedBlocks, $offeringId, $existingIds);

        if (! $roomFaculty['ok']) {
            $first = $roomFaculty['conflicts'][0];

            return ['ok' => false, 'error' => ucfirst($first['type']) . " conflict: {$first['detail']}", 'conflicts' => $roomFaculty['conflicts']];
        }

        $loadError = $this->checkFacultyLoadCap($preparedBlocks, (string) $offering['academic_year'], $offeringId, (float) $subject['units']);

        if ($loadError !== null) {
            return ['ok' => false, 'error' => $loadError];
        }

        $rows = array_map(static fn (array $b) => [
            'offering_id'           => $offeringId,
            'offering_component_id' => null,
            'academic_year'         => $offering['academic_year'],
            'program'               => null,
            'year_level'            => null,
            'section'               => null,
            'day'                   => $b['day'],
            'start_time'            => $b['start'],
            'end_time'              => $b['end'],
            'time_label'            => $b['time_label'],
            'subject_code'          => $subject['subject_code'],
            'subject_title'         => $subject['subject_name'],
            'prof'                  => $b['prof'],
            'room'                  => $b['room'],
            'theme'                 => 'blue',
            'status'                => 'published',
        ], $preparedBlocks);

        $this->db->transException(true)->transStart();

        $this->where('offering_id', $offeringId)->where('offering_component_id IS NULL')->delete();

        if ($rows !== []) {
            $this->insertBatch($rows);
        }

        $this->syncFacultyAssignments($offeringId, null, (string) $offering['academic_year'], $subject['subject_code'], $preparedBlocks);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not save the schedule. Please retry.'];
        }

        return ['ok' => true, 'saved' => count($rows), 'override' => $auth['override']];
    }

    /**
     * Step 9: replaces one offering COMPONENT's schedule (e.g. "Laboratory
     * Group A"), independent of its sibling components and independent of
     * any whole-offering-level meetings. Conflict checking branches on the
     * component's roster_mode:
     *
     *  - 'inherited': preserves the existing full-parent-roster behavior —
     *    the same cohortConflicts() section-level expansion used for a
     *    plain Step 5 offering, since an inherited component's membership
     *    IS every student in the attached sections.
     *  - 'explicit': uses SchedulingConflictService::studentConflicts()
     *    against the component's ACTUAL member user_ids — never reduced to
     *    home-section identity, so two disjoint groups sharing a home
     *    section may legitimately share a time slot.
     *
     * @param list<array{day: int, start: string, end: string, time_label: string, room: string, faculty_id: ?int}> $blocks
     *
     * @return array{ok: bool, error?: string, conflicts?: array}
     */
    public function syncComponentSchedule(int $offeringComponentId, int $actorId, array $blocks): array
    {
        $components = new OfferingComponentModel();
        $component  = $components->find($offeringComponentId);

        if ($component === null) {
            return ['ok' => false, 'error' => 'Component not found.'];
        }

        if ((int) $component['is_active'] !== 1) {
            return ['ok' => false, 'error' => 'This component is inactive and cannot be scheduled.'];
        }

        $offerings = new CourseOfferingModel();
        $offering  = $offerings->find((int) $component['offering_id']);

        if ($offering === null || $offering['subject_id'] === null) {
            return ['ok' => false, 'error' => 'This component has no linked subject and cannot be scheduled.'];
        }

        $subjectId = (int) $offering['subject_id'];

        $auth = (new SchedulingAuthorization())->authorizeManageSubject($actorId, $subjectId);

        if (! $auth['ok']) {
            return ['ok' => false, 'error' => 'You are not authorized to schedule this component.'];
        }

        $subject = db_connect()->table('subjects')->select('subject_code, subject_name, units')->where('subject_id', $subjectId)->get()->getRowArray();

        if ($subject === null) {
            return ['ok' => false, 'error' => 'Linked subject not found.'];
        }

        if ($blockError = $this->validateBlockShapes($blocks)) {
            return ['ok' => false, 'error' => $blockError];
        }

        $existingIds = array_map('intval', array_column($this->forComponent($offeringComponentId), 'schedule_id'));

        $conflictService = new SchedulingConflictService();
        $preparedBlocks   = $this->attachFacultyUsernames($blocks);

        if ($component['roster_mode'] === 'explicit') {
            $memberIds = $components->explicitMemberIds($offeringComponentId);
            $memberCheck = $conflictService->studentConflicts($memberIds, (string) $offering['academic_year'], $preparedBlocks, $existingIds);

            if (! $memberCheck['ok']) {
                return ['ok' => false, 'error' => 'This time conflicts with an existing class for one or more students assigned to this component.', 'conflicts' => $memberCheck['conflicts']];
            }
        } else {
            $cohort = $conflictService->cohortConflicts((int) $component['offering_id'], (string) $offering['academic_year'], $preparedBlocks, $existingIds);

            if (! $cohort['ok']) {
                return ['ok' => false, 'error' => 'This time conflicts with a participating section\'s existing schedule.', 'conflicts' => $cohort['conflicts']];
            }
        }

        $roomFaculty = $conflictService->roomAndFacultyConflicts((string) $offering['academic_year'], $preparedBlocks, (int) $component['offering_id'], $existingIds);

        if (! $roomFaculty['ok']) {
            $first = $roomFaculty['conflicts'][0];

            return ['ok' => false, 'error' => ucfirst($first['type']) . " conflict: {$first['detail']}", 'conflicts' => $roomFaculty['conflicts']];
        }

        $loadError = $this->checkFacultyLoadCap($preparedBlocks, (string) $offering['academic_year'], (int) $component['offering_id'], (float) $subject['units']);

        if ($loadError !== null) {
            return ['ok' => false, 'error' => $loadError];
        }

        $titleSuffix = $component['name'] !== null && $component['name'] !== '' ? ' · ' . $component['name'] : '';

        $rows = array_map(static fn (array $b) => [
            'offering_id'           => (int) $component['offering_id'],
            'offering_component_id' => $offeringComponentId,
            'academic_year'         => $offering['academic_year'],
            'program'               => null,
            'year_level'            => null,
            'section'               => null,
            'day'                   => $b['day'],
            'start_time'            => $b['start'],
            'end_time'              => $b['end'],
            'time_label'            => $b['time_label'],
            'subject_code'          => $subject['subject_code'],
            'subject_title'         => $subject['subject_name'] . $titleSuffix,
            'prof'                  => $b['prof'],
            'room'                  => $b['room'],
            'theme'                 => 'blue',
            'status'                => 'published',
        ], $preparedBlocks);

        $this->db->transException(true)->transStart();

        $this->where('offering_component_id', $offeringComponentId)->delete();

        if ($rows !== []) {
            $this->insertBatch($rows);
        }

        $this->syncFacultyAssignments((int) $component['offering_id'], $offeringComponentId, (string) $offering['academic_year'], $subject['subject_code'], $preparedBlocks);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not save the schedule. Please retry.'];
        }

        return ['ok' => true, 'saved' => count($rows), 'override' => $auth['override']];
    }

    /**
     * Shape/range validation shared by syncOfferingSchedule() and
     * syncComponentSchedule() — returns the first error message, or null
     * when every block is well-formed (including no overlap among the
     * blocks being saved together).
     */
    private function validateBlockShapes(array $blocks): ?string
    {
        foreach ($blocks as $block) {
            foreach (['day', 'start', 'end', 'time_label', 'room'] as $key) {
                if (! isset($block[$key])) {
                    return "Missing block field: {$key}";
                }
            }

            if (! is_int($block['day']) || $block['day'] < 0 || $block['day'] > 6) {
                return 'Invalid block day.';
            }

            if (! preg_match('/^\d{2}:\d{2}$/', $block['start']) || ! preg_match('/^\d{2}:\d{2}$/', $block['end']) || $block['start'] >= $block['end']) {
                return 'Invalid block time range.';
            }
        }

        foreach ($blocks as $i => $a) {
            foreach (array_slice($blocks, $i + 1) as $b) {
                if (ScheduleOverlap::clashes($a, $b)) {
                    return 'These two meeting blocks overlap on the same day.';
                }
            }
        }

        return null;
    }

    /**
     * Resolves each block's faculty_id into the stored 'prof' username,
     * shared by both sync methods.
     */
    private function attachFacultyUsernames(array $blocks): array
    {
        $usernameById = [];
        $facultyIds   = array_filter(array_unique(array_column($blocks, 'faculty_id')));

        if ($facultyIds !== []) {
            $usernameById = array_column(
                db_connect()->table('users')->select('id, username')->whereIn('id', $facultyIds)->get()->getResultArray(),
                'username',
                'id'
            );
        }

        return array_map(static function (array $b) use ($usernameById) {
            $b['prof'] = $b['faculty_id'] !== null ? ($usernameById[$b['faculty_id']] ?? null) : null;

            return $b;
        }, $blocks);
    }

    /**
     * Faculty unit-load cap, mirroring the check ChairpersonController::
     * sync() runs for legacy section plotting — previously this offering
     * path had no cap check at all, so a chairperson could plot a faculty
     * member here past the limit the legacy plotter would have refused.
     * Every distinct faculty member in $blocks gains this offering's whole
     * subject once (not per block — a 3-unit offering taught across 3
     * meetings is still 3 units), on top of their total load from
     * everything else (FacultyAssignmentModel::totalLoadUnits(), which
     * already excludes this same offering so a re-save of its own schedule
     * doesn't double-count the load it's about to replace).
     *
     * @param list<array{faculty_id: ?int, prof: ?string}> $preparedBlocks
     */
    private function checkFacultyLoadCap(array $preparedBlocks, string $academicYear, int $offeringId, float $subjectUnits): ?string
    {
        $facultyIds = array_values(array_unique(array_filter(array_column($preparedBlocks, 'faculty_id'))));

        if ($facultyIds === []) {
            return null;
        }

        $cap         = (float) (setting('Academic.maxFacultyUnits') ?? 24);
        $assignments = new FacultyAssignmentModel();

        foreach ($facultyIds as $facultyId) {
            $base = $assignments->totalLoadUnits((int) $facultyId, $academicYear, null, $offeringId);

            if ($base + $subjectUnits > $cap) {
                $username = db_connect()->table('users')->select('username')->where('id', $facultyId)->get()->getRowArray()['username'] ?? "faculty #{$facultyId}";

                return "{$username} would exceed the {$cap}-unit faculty load limit.";
            }
        }

        return null;
    }

    /**
     * Keeps faculty_assignments in sync with a just-saved offering/component
     * schedule — previously this offering path only wrote `prof` (a display
     * username) into plotted_schedules and never touched faculty_assignments
     * at all, so offering-plotted faculty never showed up in teaching-load
     * totals or the faculty-facing load board. Mirrors
     * ChairpersonController::sync()'s upsert-then-drop-stale pattern: every
     * faculty member still present in the saved blocks gets an upserted row,
     * and anyone no longer present (reassigned or unassigned) has their row
     * for this exact offering/component removed.
     *
     * @param list<array{faculty_id: ?int}> $preparedBlocks
     */
    private function syncFacultyAssignments(int $offeringId, ?int $offeringComponentId, string $academicYear, string $subjectCode, array $preparedBlocks): void
    {
        $assignments = new FacultyAssignmentModel();
        $facultyIds  = array_values(array_unique(array_filter(array_column($preparedBlocks, 'faculty_id'))));

        foreach ($facultyIds as $facultyId) {
            $assignments->upsertForOffering($offeringId, $offeringComponentId, $academicYear, $subjectCode, (int) $facultyId);
        }

        $stale = $assignments->where('offering_id', $offeringId);
        $stale = $offeringComponentId === null ? $stale->where('offering_component_id IS NULL') : $stale->where('offering_component_id', $offeringComponentId);

        if ($facultyIds !== []) {
            $stale->whereNotIn('user_id', $facultyIds);
        }

        $stale->delete();
    }
}

<?php

namespace App\Models;

use App\Libraries\SchedulingConflictService;
use App\Libraries\SubjectCodeResolver;
use CodeIgniter\Model;
use App\Models\OfferingComponentModel;

/**
 * A course offering is the actual class instance a schedule/roster attaches
 * to — decoupled from `sections` (the administrative cohort). Merging two
 * sections' rosters for a shared subject is: both sections' rows in
 * `offering_sections` point at the same offering.
 *
 * subject_id is the canonical link to the subject catalog (subject_code is
 * kept alongside it for backward compatibility with existing reads).
 * Offerings created via createMerge() always resolve and save a real
 * subject_id — a subject_code with no matching catalog entry is rejected,
 * not silently allowed. subject_id can still be null on rows that predate
 * this (backfilled-but-unmatched legacy offerings); nothing in this step
 * deletes or rewrites those.
 */
class CourseOfferingModel extends Model
{
    protected $table      = 'course_offerings';
    protected $primaryKey = 'offering_id';

    protected $allowedFields = [
        'academic_year',
        'subject_code',
        'subject_id',
        'capacity',
        'status',
    ];

    protected $useTimestamps = true;

    /**
     * The offering this (section, subject) pair belongs to, if any — the
     * thing EnrollmentModel needs to know whether to check capacity against
     * this section alone or against the merged pool.
     */
    public function offeringForSection(int $sectionId, string $subjectCode): ?array
    {
        return $this->db->table('course_offerings co')
            ->select('co.*')
            ->join('offering_sections os', 'os.offering_id = co.offering_id')
            ->where('os.section_id', $sectionId)
            ->where('os.subject_code', $subjectCode)
            ->get()
            ->getRowArray();
    }

    /**
     * Every section_id currently attached to an offering.
     */
    public function sectionIdsFor(int $offeringId): array
    {
        return array_map('intval', array_column(
            $this->db->table('offering_sections')->select('section_id')->where('offering_id', $offeringId)->get()->getResultArray(),
            'section_id'
        ));
    }

    /**
     * Distinct enrolled students across every section merged into this
     * offering — the merged roster count EnrollmentModel checks against
     * offering.capacity instead of any single section's own capacity.
     */
    public function rosterCount(int $offeringId): int
    {
        $row = $this->db->table('enrollments')
            ->select('COUNT(DISTINCT user_id) AS cnt')
            ->where('offering_id', $offeringId)
            ->where('status', 'enrolled')
            ->get()
            ->getRowArray();

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Batched rosterCount() — one grouped query for many offerings instead
     * of one query per offering (dashboard use). Offerings with zero
     * enrolled students are omitted from the result; callers should treat a
     * missing key as 0.
     *
     * @param list<int> $offeringIds
     *
     * @return array<int, int> keyed by offering_id
     */
    public function rosterCountsFor(array $offeringIds): array
    {
        if ($offeringIds === []) {
            return [];
        }

        $rows = $this->db->table('enrollments')
            ->select('offering_id, COUNT(DISTINCT user_id) AS cnt')
            ->whereIn('offering_id', $offeringIds)
            ->where('status', 'enrolled')
            ->groupBy('offering_id')
            ->get()
            ->getResultArray();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['offering_id']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Every actively-enrolled student across every section merged into this
     * offering — the class list grading needs for a merged (section,
     * subject) pair, in place of any single section's own roster.
     *
     * @return list<array{enrollment_id: int, user_id: int, username: string}>
     */
    public function listRoster(int $offeringId): array
    {
        return $this->db->table('enrollments e')
            ->select('e.enrollment_id, e.user_id, u.username')
            ->join('users u', 'u.id = e.user_id')
            ->where('e.offering_id', $offeringId)
            ->where('e.status', 'enrolled')
            ->orderBy('u.username')
            ->get()
            ->getResultArray();
    }

    /**
     * Creates an offering for $subjectCode across $sectionIds, then
     * backfills offering_id onto every already-enrolled student's existing
     * row for that subject in those sections, so an offering created after
     * enrollment has already started still produces one correct roster
     * immediately.
     *
     * $minSections defaults to 2 (the "Merge Selected" UI's own meaning —
     * combining multiple existing sections). The subject-owner "needs
     * plotting" picker (OfferingController::plotDemand()) passes 1, since a
     * single borrowing section with no merge partner still needs its own
     * offering before it can be scheduled — the resulting row is identical
     * either way, just with one offering_sections entry instead of several.
     *
     * Refuses to merge a section that's already merged into a DIFFERENT
     * offering for the same subject — a section can only belong to one
     * offering per subject at a time (enforced by the unique key on
     * offering_sections too; this is the friendly pre-check).
     *
     * Requires $subjectCode to match a real `subjects` catalog entry — a new
     * offering is never created for a subject code that doesn't exist. (A
     * null subject_id on an offering can only happen on legacy rows that
     * predate this check, backfilled-but-unmatched by migration.)
     *
     * @return array{ok: bool, error?: string, offering_id?: int, roster_count?: int, over_capacity?: bool}
     */
    public function createMerge(string $academicYear, string $subjectCode, array $sectionIds, ?int $capacity, int $minSections = 2): array
    {
        $sectionIds = array_values(array_unique(array_map('intval', $sectionIds)));

        if (count($sectionIds) < $minSections) {
            return ['ok' => false, 'error' => $minSections > 1 ? 'Select at least two sections to merge.' : 'Select a section.'];
        }

        $subjectMatches = $this->db->table('subjects')
            ->select('subject_id')
            ->where('subject_code', $subjectCode)
            ->get()
            ->getResultArray();

        $resolved = SubjectCodeResolver::resolve($subjectMatches);

        if ($resolved['status'] !== SubjectCodeResolver::MATCHED) {
            return ['ok' => false, 'error' => "No subject found in the catalog for code {$subjectCode}. Add it to the subject catalog before creating an offering for it."];
        }

        $subjectId = $resolved['subject_id'];

        $db = $this->db;
        $db->transStart();

        foreach ($sectionIds as $sectionId) {
            $existing = $this->offeringForSection($sectionId, $subjectCode);

            if ($existing !== null) {
                $db->transRollback();
                $db->resetTransStatus();

                return ['ok' => false, 'error' => "Section #{$sectionId} is already merged into another offering for {$subjectCode}."];
            }
        }

        $this->insert([
            'academic_year' => $academicYear,
            'subject_code'  => $subjectCode,
            'subject_id'    => $subjectId,
            'capacity'      => $capacity,
        ]);

        $offeringId = $this->getInsertID();

        $rows = array_map(static fn (int $sid) => [
            'offering_id'  => $offeringId,
            'section_id'   => $sid,
            'subject_code' => $subjectCode,
            'created_at'   => date('Y-m-d H:i:s'),
        ], $sectionIds);

        $db->table('offering_sections')->insertBatch($rows);

        // Backfill: any student already enrolled in this subject through one
        // of these sections now counts toward the merged roster.
        $db->table('enrollments')
            ->where('subject_code', $subjectCode)
            ->whereIn('section_id', $sectionIds)
            ->update(['offering_id' => $offeringId]);

        // Step 9: snapshot this subject's active component templates (if
        // any) into offering_components rows for the new offering. A
        // subject with no templates produces none — the offering stays on
        // the plain Step 5 path, unchanged.
        (new OfferingComponentModel())->instantiateForOffering($offeringId, $subjectId);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not create the merge. Please retry.'];
        }

        $rosterCount = $this->rosterCount($offeringId);

        return [
            'ok'            => true,
            'offering_id'   => $offeringId,
            'roster_count'  => $rosterCount,
            // Surfaced, never auto-enforced retroactively — merging never
            // drops an already-enrolled student (see roadmap Q&A on merging).
            'over_capacity' => $capacity !== null && $rosterCount > $capacity,
        ];
    }

    /**
     * createMerge(), but auto-carrying each section's already-plotted
     * legacy schedule into the new merged offering instead of leaving the
     * merge unscheduled.
     *
     * "Carrying" only ever happens when every section's legacy schedule for
     * this subject agrees — same (day, start_time, end_time, room, prof)
     * tuple, exactly (spec §4.2). Anything else (nothing plotted anywhere,
     * or two sections plotted at genuinely different times/rooms/faculty)
     * is left for a human to resolve on the new offering afterward; the
     * merge itself still succeeds either way — a schedule-carry problem is
     * never a reason to refuse the merge.
     *
     * $academicYear is passed straight through to both createMerge() AND
     * the legacy lookup below — it MUST be the term-scoped string
     * plotted_schedules rows are keyed by (e.g. "AY2026-2027 · 1st
     * Semester"), not a section's own (year-only) academic_year column; see
     * AcademicYearModel::yearOnly() for why those two are never
     * interchangeable.
     *
     * @param list<int> $sectionIds
     *
     * @return array{ok: bool, error?: string, offering_id?: int, roster_count?: int, over_capacity?: bool, schedule_carried?: bool, schedule_carry_skipped_reason?: string}
     */
    public function createMergeCarryingSchedule(string $academicYear, string $subjectCode, array $sectionIds, ?int $capacity, int $minSections, int $actorId): array
    {
        $result = $this->createMerge($academicYear, $subjectCode, $sectionIds, $capacity, $minSections);

        if (! $result['ok']) {
            // No side effects on failure — nothing below has run yet.
            return $result;
        }

        $sections = new SectionModel();
        $plotted  = new PlottedScheduleModel();

        $legacyRows = [];

        foreach (array_values(array_unique(array_map('intval', $sectionIds))) as $sectionId) {
            $section = $sections->find($sectionId);

            if ($section === null) {
                continue;
            }

            foreach ($plotted->legacyRowsFor($academicYear, $section['program'], $section['year_level'], $section['section'], $subjectCode) as $row) {
                $legacyRows[] = $row;
            }
        }

        if ($legacyRows === []) {
            // Nothing plotted anywhere yet — not a conflict, just nothing to carry.
            return $result + ['schedule_carried' => false];
        }

        // "Identical" per spec §4.2: every field in this tuple matches,
        // including prof — same time/room with a different faculty member
        // is a genuine conflict, not a duplicate.
        $signatures = [];

        foreach ($legacyRows as $row) {
            $key              = implode('|', [$row['day'], $row['start_time'], $row['end_time'], $row['room'], $row['prof'] ?? '']);
            $signatures[$key] = $row;
        }

        if (count($signatures) > 1) {
            return $result + ['schedule_carried' => false, 'schedule_carry_skipped_reason' => 'conflicting_existing_schedules'];
        }

        $signature = array_values($signatures)[0];

        $faculty = ($signature['prof'] ?? null) !== null && $signature['prof'] !== ''
            ? $this->db->table('users')->select('id')->where('username', $signature['prof'])->get()->getRowArray()
            : null;

        $block = [
            'day'        => (int) $signature['day'],
            'start'      => substr((string) $signature['start_time'], 0, 5),
            'end'        => substr((string) $signature['end_time'], 0, 5),
            'time_label' => $signature['time_label'],
            'room'       => $signature['room'],
            'faculty_id' => $faculty !== null ? (int) $faculty['id'] : null,
        ];

        $db = $this->db;
        $db->transStart();

        $plotted->whereIn('schedule_id', array_map('intval', array_column($legacyRows, 'schedule_id')))->delete();

        $sync = (new PlottedScheduleModel())->syncOfferingSchedule($result['offering_id'], $actorId, [$block]);

        if (! $sync['ok']) {
            // The merge itself must never fail because the schedule-carry
            // step couldn't go through (e.g. an authorization or conflict
            // check inside syncOfferingSchedule() rejected it) — undo only
            // the legacy-row deletion above and report "not carried" rather
            // than propagating syncOfferingSchedule()'s own error.
            $db->transRollback();
            $db->resetTransStatus();

            return $result + ['schedule_carried' => false];
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $result + ['schedule_carried' => false];
        }

        return $result + ['schedule_carried' => true];
    }

    /**
     * The sub_dept_id of the academic unit that owns this offering's
     * subject, derived (never duplicated) via subject_id -> subjects ->
     * sub_department_id. Null when the offering has no linked subject_id
     * (legacy/unmatched rows) or the subject itself has no owning unit set.
     */
    public function owningSubDeptId(int $offeringId): ?int
    {
        $row = $this->db->table('course_offerings co')
            ->select('s.sub_department_id')
            ->join('subjects s', 's.subject_id = co.subject_id')
            ->where('co.offering_id', $offeringId)
            ->get()
            ->getRowArray();

        return $row !== null && $row['sub_department_id'] !== null ? (int) $row['sub_department_id'] : null;
    }

    /**
     * Attaches ONE more section to an ALREADY-EXISTING offering — unlike
     * createMerge(), which only builds a brand-new offering from 2+ unmerged
     * sections. Used by the placement-request fulfillment flow (a section
     * joining an offering another unit already owns/manages).
     *
     * Unlike createMerge() (which never blocks on capacity, matching its
     * existing, backward-compatible behavior for direct owning-scheduler
     * merges), this requires an explicit $allowOverCapacity=true to proceed
     * when attaching would exceed the offering's capacity — the caller
     * (OfferingPlacementRequestModel) is responsible for only setting that
     * once an authorized actor has explicitly confirmed the override.
     *
     * Also checks the new section's own existing schedule commitments
     * against the offering's existing meetings (if any) BEFORE attaching —
     * a section with a conflicting class elsewhere is never silently
     * folded into an already-scheduled offering. Hard block, no override:
     * unlike capacity, nothing in this step asked for a way to force a
     * known timetable clash through.
     *
     * @return array{ok: bool, error?: string, requires_override?: bool, prospective_roster?: int, capacity?: ?int, over_capacity?: bool, schedule_conflicts?: array}
     */
    public function attachSection(int $offeringId, int $sectionId, string $subjectCode, bool $allowOverCapacity = false): array
    {
        // Every check below (duplicate-attach, schedule conflict, capacity)
        // must be re-verified AFTER the offering row is locked — two
        // concurrent attachSection() calls for the same offering could
        // otherwise both read a stale roster/attachment state, both pass,
        // and both write, over-filling the offering or double-attaching a
        // section. Locking first, then checking, inside one transaction
        // closes that gap (same pattern as EnrollmentModel::lockSection()).
        $db = $this->db;
        $db->transStart();

        $offering = $this->lockForUpdate($offeringId);

        if ($offering === null) {
            $db->transComplete();

            return ['ok' => false, 'error' => 'Offering not found.'];
        }

        if ($this->offeringForSection($sectionId, $subjectCode) !== null) {
            $db->transComplete();

            return ['ok' => false, 'error' => "Section #{$sectionId} is already attached to an offering for {$subjectCode}."];
        }

        $scheduleCheck = (new SchedulingConflictService())->attachConflicts($offeringId, (string) $offering['academic_year'], $sectionId);

        if (! $scheduleCheck['ok']) {
            $db->transComplete();

            return [
                'ok'                 => false,
                'error'              => 'This section has a schedule conflict with the offering\'s existing class time.',
                'schedule_conflicts' => $scheduleCheck['conflicts'],
            ];
        }

        $incoming = $db->table('enrollments')
            ->where('subject_code', $subjectCode)
            ->where('section_id', $sectionId)
            ->where('status', 'enrolled')
            ->countAllResults();

        $prospectiveRoster = $this->rosterCount($offeringId) + $incoming;
        $capacity          = $offering['capacity'] !== null ? (int) $offering['capacity'] : null;
        $wouldExceed        = $capacity !== null && $prospectiveRoster > $capacity;

        if ($wouldExceed && ! $allowOverCapacity) {
            $db->transComplete();

            return [
                'ok'                  => false,
                'error'               => 'Attaching this section would exceed the offering\'s capacity.',
                'requires_override'   => true,
                'prospective_roster'  => $prospectiveRoster,
                'capacity'            => $capacity,
            ];
        }

        $db->table('offering_sections')->insert([
            'offering_id'  => $offeringId,
            'section_id'   => $sectionId,
            'subject_code' => $subjectCode,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $db->table('enrollments')
            ->where('subject_code', $subjectCode)
            ->where('section_id', $sectionId)
            ->update(['offering_id' => $offeringId]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not attach the section. Please retry.'];
        }

        return [
            'ok'            => true,
            'over_capacity' => $wouldExceed,
        ];
    }

    /**
     * Row-locks the offering for the duration of the enclosing transaction
     * so concurrent attachSection() calls against it serialize. FOR UPDATE
     * is only valid under MySQL (production); it's a no-op elsewhere (the
     * SQLite test connection is single-process anyway).
     */
    private function lockForUpdate(int $offeringId): ?array
    {
        $db     = $this->db;
        $suffix = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';

        return $db->query("SELECT * FROM course_offerings WHERE offering_id = ?{$suffix}", [$offeringId])->getRowArray();
    }

    /**
     * Detaches one section from an offering. Existing enrollments that came
     * through that section are un-tagged (offering_id -> null) — they
     * revert to being counted against their own section again, never
     * silently dropped from the roster. If this was the last attached
     * section, the now-empty offering is deleted outright — UNLESS it still
     * has a plotted schedule, in which case it's left intact (sectionless
     * but still scheduled) rather than auto-deleted. Letting the delete
     * proceed would either strand plotted_schedules rows an application
     * guard has to catch anyway, or hit the FK's RESTRICT at the DB level —
     * application-level prevention here is the one place both get avoided
     * cleanly, per the Step 5 guidance to prefer that over cascade deletion.
     */
    public function unmergeSection(int $offeringId, int $sectionId): array
    {
        $offering = $this->find($offeringId);

        if ($offering === null) {
            return ['ok' => false, 'error' => 'Offering not found.'];
        }

        $db = $this->db;
        $db->transStart();

        $db->table('offering_sections')
            ->where('offering_id', $offeringId)
            ->where('section_id', $sectionId)
            ->delete();

        $db->table('enrollments')
            ->where('offering_id', $offeringId)
            ->where('section_id', $sectionId)
            ->update(['offering_id' => null]);

        $remaining = $this->sectionIdsFor($offeringId);

        if ($remaining === []) {
            $hasSchedule   = $db->table('plotted_schedules')->where('offering_id', $offeringId)->countAllResults() > 0;
            $hasComponents = $db->table('offering_components')->where('offering_id', $offeringId)->countAllResults() > 0;

            if (! $hasSchedule && ! $hasComponents) {
                $db->table('enrollments')->where('offering_id', $offeringId)->update(['offering_id' => null]);
                $this->delete($offeringId);
            }
        }

        $db->transComplete();

        return ['ok' => $db->transStatus() !== false];
    }
}

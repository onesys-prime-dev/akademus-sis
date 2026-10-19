<?php

namespace App\Libraries;

use App\Models\SectionModel;

/**
 * Centralizes "does this proposed meeting time conflict with what's already
 * committed?" for the offering-linked scheduling work (Step 5). Extracted
 * because the exact same expansion — offering -> attached sections -> each
 * section's existing commitments -> overlap check — is needed in two
 * otherwise-unrelated places: plotting/editing an offering's own schedule,
 * and attaching a new section to an already-scheduled offering
 * (CourseOfferingModel::attachSection()). Reuses ScheduleOverlap::clashes()
 * for the actual overlap math — this class only adds the "which rows are
 * even in scope" plumbing, never reimplements overlap comparison.
 *
 * Explicit identity rule (never mixed): a `plotted_schedules` row with
 * offering_id IS NOT NULL is reached ONLY through offering_sections; a row
 * with offering_id IS NULL is reached ONLY through its legacy
 * (academic_year, program, year_level, section) tuple. Null legacy columns
 * on an offering-linked row are never treated as a wildcard or a fallback
 * identity.
 */
class SchedulingConflictService
{
    /**
     * Every section_id currently attached to an offering.
     *
     * @return list<int>
     */
    public function attachedSectionIds(int $offeringId): array
    {
        return array_map('intval', array_column(
            db_connect()->table('offering_sections')->select('section_id')->where('offering_id', $offeringId)->get()->getResultArray(),
            'section_id'
        ));
    }

    /**
     * Every plotted_schedules row that counts as "this section's existing
     * commitment" — its own legacy rows, plus any OTHER offering's rows it's
     * attached to. $excludeOfferingId leaves out one specific offering's
     * rows (used when checking a proposed edit to THAT offering's own
     * schedule against its attached sections, so it never conflicts with
     * itself). $excludeScheduleIds additionally excludes specific rows by id
     * (the exact rows currently being replaced).
     *
     * $termAcademicYear is the term-scoped academic year (e.g. "AY 2026 -
     * 2027 · 1st Semester") whose legacy commitments count — a section is
     * year-scoped now, so which semester's plot to check is no longer
     * implied by the section itself and has to be told explicitly.
     *
     * @return list<array<string, mixed>>
     */
    public function meetingsForSection(int $sectionId, string $termAcademicYear, ?int $excludeOfferingId = null, array $excludeScheduleIds = []): array
    {
        $section = (new SectionModel())->find($sectionId);

        if ($section === null) {
            return [];
        }

        $db = db_connect();

        $legacy = $db->table('plotted_schedules')
            ->where('offering_id IS NULL')
            ->where('academic_year', $termAcademicYear)
            ->where('program', $section['program'])
            ->where('year_level', $section['year_level'])
            ->where('section', $section['section'])
            ->get()
            ->getResultArray();

        $offeringIds = array_map('intval', array_column(
            $db->table('offering_sections')->select('offering_id')->where('section_id', $sectionId)->get()->getResultArray(),
            'offering_id'
        ));

        if ($excludeOfferingId !== null) {
            $offeringIds = array_values(array_diff($offeringIds, [$excludeOfferingId]));
        }

        $offeringRows = $offeringIds === [] ? [] : $db->table('plotted_schedules')
            ->whereIn('offering_id', $offeringIds)
            ->get()
            ->getResultArray();

        $all = array_merge($legacy, $offeringRows);

        if ($excludeScheduleIds !== []) {
            $all = array_values(array_filter(
                $all,
                static fn (array $row) => ! in_array((int) $row['schedule_id'], $excludeScheduleIds, true)
            ));
        }

        return $all;
    }

    /**
     * Checks a proposed set of meeting blocks for an offering against every
     * attached section's OTHER existing commitments. The offering's own
     * current rows (its soon-to-be-replaced schedule) are excluded via
     * $excludeOfferingId so a valid edit never conflicts with itself —
     * $excludeScheduleIds is an additional, more surgical exclusion for the
     * same purpose.
     *
     * A conflicting row is reported ONCE even if multiple attached sections
     * all reach it (e.g. two sections sharing a second offering) — grouped
     * by schedule_id, carrying every affected section_id, never duplicated
     * per section.
     *
     * @param list<array{day:int,start:string,end:string}> $proposedBlocks
     *
     * @return array{ok: bool, conflicts: list<array{schedule_id: int, subject_code: string, time_label: string, section_ids: list<int>}>}
     */
    public function cohortConflicts(int $offeringId, string $termAcademicYear, array $proposedBlocks, array $excludeScheduleIds = []): array
    {
        $sectionIds = $this->attachedSectionIds($offeringId);
        $conflicts  = [];

        foreach ($sectionIds as $sectionId) {
            $commitments = $this->meetingsForSection($sectionId, $termAcademicYear, $offeringId, $excludeScheduleIds);

            foreach ($commitments as $row) {
                foreach ($proposedBlocks as $block) {
                    if (! ScheduleOverlap::clashes($block, $this->rowToMeeting($row))) {
                        continue;
                    }

                    $key = (int) $row['schedule_id'];

                    if (! isset($conflicts[$key])) {
                        $conflicts[$key] = [
                            'schedule_id'  => $key,
                            'subject_code' => $row['subject_code'],
                            'time_label'   => $row['time_label'],
                            'section_ids'  => [],
                        ];
                    }

                    if (! in_array($sectionId, $conflicts[$key]['section_ids'], true)) {
                        $conflicts[$key]['section_ids'][] = $sectionId;
                    }
                }
            }
        }

        return ['ok' => $conflicts === [], 'conflicts' => array_values($conflicts)];
    }

    /**
     * Checks a section that is NOT YET attached to $offeringId against that
     * offering's existing meetings — used before attachSection() lets a new
     * section join an already-scheduled offering.
     *
     * @return array{ok: bool, conflicts: list<array{schedule_id: int, subject_code: string, time_label: string}>}
     */
    public function attachConflicts(int $offeringId, string $termAcademicYear, int $newSectionId): array
    {
        $offeringMeetings = db_connect()->table('plotted_schedules')->where('offering_id', $offeringId)->get()->getResultArray();

        if ($offeringMeetings === []) {
            return ['ok' => true, 'conflicts' => []];
        }

        $sectionCommitments = $this->meetingsForSection($newSectionId, $termAcademicYear);
        $conflicts          = [];

        foreach ($sectionCommitments as $row) {
            foreach ($offeringMeetings as $om) {
                if (! ScheduleOverlap::clashes($this->rowToMeeting($row), $this->rowToMeeting($om))) {
                    continue;
                }

                $conflicts[(int) $row['schedule_id']] = [
                    'schedule_id'  => (int) $row['schedule_id'],
                    'subject_code' => $row['subject_code'],
                    'time_label'   => $row['time_label'],
                ];
            }
        }

        return ['ok' => $conflicts === [], 'conflicts' => array_values($conflicts)];
    }

    /**
     * School-wide room/faculty conflict check for a proposed set of blocks
     * (the offering-schedule equivalent of ChairpersonController::sync()'s
     * cross-section room/faculty check) — same room or same non-null prof,
     * overlapping time, anywhere in the academic year. Excludes the
     * offering's own current rows the same way cohortConflicts() does.
     *
     * @param list<array{day:int,start:string,end:string,room:string,prof:?string}> $proposedBlocks
     *
     * @return array{ok: bool, conflicts: list<array{schedule_id: int, type: string, detail: string}>}
     */
    public function roomAndFacultyConflicts(string $academicYear, array $proposedBlocks, ?int $excludeOfferingId = null, array $excludeScheduleIds = []): array
    {
        $db = db_connect();

        $query = $db->table('plotted_schedules')->where('academic_year', $academicYear);

        if ($excludeOfferingId !== null) {
            $query->groupStart()
                ->where('offering_id !=', $excludeOfferingId)
                ->orWhere('offering_id IS NULL')
                ->groupEnd();
        }

        $others = $query->get()->getResultArray();

        if ($excludeScheduleIds !== []) {
            $others = array_values(array_filter(
                $others,
                static fn (array $row) => ! in_array((int) $row['schedule_id'], $excludeScheduleIds, true)
            ));
        }

        $conflicts = [];

        foreach ($proposedBlocks as $block) {
            foreach ($others as $other) {
                $otherMeeting = $this->rowToMeeting($other);

                if (! ScheduleOverlap::clashes($block, $otherMeeting)) {
                    continue;
                }

                if ($block['room'] === $other['room']) {
                    $conflicts[] = ['schedule_id' => (int) $other['schedule_id'], 'type' => 'room', 'detail' => "{$other['room']} is used by {$other['subject_code']} at {$other['time_label']}."];
                } elseif (($block['prof'] ?? null) !== null && $block['prof'] !== '' && $block['prof'] === $other['prof']) {
                    $conflicts[] = ['schedule_id' => (int) $other['schedule_id'], 'type' => 'faculty', 'detail' => "{$other['prof']} teaches {$other['subject_code']} at {$other['time_label']}."];
                }
            }
        }

        return ['ok' => $conflicts === [], 'conflicts' => $conflicts];
    }

    /**
     * Step 9: every real schedule commitment for a batch of students,
     * resolved from their actual active ENROLLMENTS (never a home-section
     * proxy), in a small fixed number of queries regardless of how many
     * students are passed in.
     *
     *  - A legacy enrollment (offering_id IS NULL) contributes that
     *    enrollment's own section's legacy plotted_schedules rows.
     *    enrollments.section_id is the source of truth — a student
     *    irregularly enrolled under a DIFFERENT section than their
     *    users.section_id is resolved via that enrollment's own section,
     *    never their home section. users.section_id is never consulted:
     *    enrollments.section_id has been NOT NULL since the table was
     *    created, so every enrollment row already carries a real section
     *    identity with nothing to fall back to.
     *  - An offering-backed enrollment with NO offering_components rows
     *    contributes every one of that offering's plotted_schedules rows
     *    (the Step 5 simple-offering case — unchanged).
     *  - An offering-backed enrollment where the offering HAS components
     *    contributes, per active component: every one of its schedule rows
     *    when roster_mode is 'inherited' (everyone enrolled counts), or
     *    only when this exact user_id has an offering_component_students
     *    row for that component when roster_mode is 'explicit'. A
     *    component-enabled offering's own whole-offering meetings (rows
     *    with offering_component_id IS NULL) apply to every enrolled
     *    student regardless of component membership.
     *
     * $termAcademicYear narrows which term's legacy (non-offering)
     * commitments count — a student's home section is year-scoped now, so
     * the term has to be told explicitly rather than read off the section.
     *
     * @param list<int> $userIds
     *
     * @return array<int, list<array<string, mixed>>> keyed by user_id
     */
    public function commitmentsForStudents(array $userIds, string $termAcademicYear): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if ($userIds === []) {
            return [];
        }

        $db = db_connect();
        $result = array_fill_keys($userIds, []);

        // 1: active enrollments for the target students.
        $enrollments = $db->table('enrollments')
            ->whereIn('user_id', $userIds)
            ->where('status', 'enrolled')
            ->get()
            ->getResultArray();

        // 2/3: distinct legacy section ids -> their sections -> their legacy
        // plotted_schedules rows, batched once (not per student).
        $legacySectionIds = array_values(array_unique(array_filter(array_map(
            static fn (array $e) => $e['offering_id'] === null && $e['section_id'] !== null ? (int) $e['section_id'] : null,
            $enrollments
        ))));

        $sectionById = [];

        if ($legacySectionIds !== []) {
            $sectionRows = (new SectionModel())->whereIn('section_id', $legacySectionIds)->findAll();
            $sectionById = array_column($sectionRows, null, 'section_id');
        }

        $legacyMeetingsByKey = [];

        if ($sectionById !== []) {
            $builder = $db->table('plotted_schedules')->where('offering_id IS NULL')->where('academic_year', $termAcademicYear)->groupStart();

            foreach ($sectionById as $sec) {
                $builder->orGroupStart()
                    ->where('program', $sec['program'])
                    ->where('year_level', $sec['year_level'])
                    ->where('section', $sec['section'])
                ->groupEnd();
            }

            $builder->groupEnd();

            foreach ($builder->get()->getResultArray() as $row) {
                $key = implode('|', [$row['program'], $row['year_level'], $row['section']]);
                $legacyMeetingsByKey[$key][] = $row;
            }
        }

        // 4/5/6: distinct offering ids -> their active components + their
        // plotted_schedules rows, batched once.
        $offeringIds = array_values(array_unique(array_filter(array_map(
            static fn (array $e) => $e['offering_id'] !== null ? (int) $e['offering_id'] : null,
            $enrollments
        ))));

        $componentsByOffering = [];
        $offeringMeetingsByOffering = [];

        if ($offeringIds !== []) {
            foreach ($db->table('offering_components')->whereIn('offering_id', $offeringIds)->where('is_active', 1)->get()->getResultArray() as $c) {
                $componentsByOffering[(int) $c['offering_id']][] = $c;
            }

            foreach ($db->table('plotted_schedules')->whereIn('offering_id', $offeringIds)->get()->getResultArray() as $row) {
                $offeringMeetingsByOffering[(int) $row['offering_id']][] = $row;
            }
        }

        // 7: explicit component membership for the target students only,
        // limited to the components reachable above.
        $allComponentIds = [];

        foreach ($componentsByOffering as $comps) {
            foreach ($comps as $c) {
                $allComponentIds[] = (int) $c['id'];
            }
        }

        $explicitMemberSet = [];

        if ($allComponentIds !== []) {
            foreach ($db->table('offering_component_students')->whereIn('offering_component_id', $allComponentIds)->whereIn('user_id', $userIds)->get()->getResultArray() as $m) {
                $explicitMemberSet[(int) $m['offering_component_id'] . '|' . (int) $m['user_id']] = true;
            }
        }

        // Resolve, per enrollment row, which meetings actually apply.
        foreach ($enrollments as $e) {
            $uid = (int) $e['user_id'];

            if ($e['offering_id'] === null) {
                $sid = $e['section_id'] !== null ? (int) $e['section_id'] : null;
                $sec = $sid !== null ? ($sectionById[$sid] ?? null) : null;

                if ($sec === null) {
                    continue;
                }

                $key = implode('|', [$sec['program'], $sec['year_level'], $sec['section']]);

                foreach ($legacyMeetingsByKey[$key] ?? [] as $row) {
                    $result[$uid][] = $row;
                }

                continue;
            }

            $offeringId = (int) $e['offering_id'];
            $components = $componentsByOffering[$offeringId] ?? [];
            $meetings   = $offeringMeetingsByOffering[$offeringId] ?? [];

            if ($components === []) {
                // Step 5 simple offering: every meeting of the offering applies.
                foreach ($meetings as $row) {
                    $result[$uid][] = $row;
                }

                continue;
            }

            foreach ($components as $comp) {
                $applies = $comp['roster_mode'] === 'inherited' || isset($explicitMemberSet[$comp['id'] . '|' . $uid]);

                if (! $applies) {
                    continue;
                }

                foreach ($meetings as $row) {
                    if ((int) ($row['offering_component_id'] ?? 0) === (int) $comp['id']) {
                        $result[$uid][] = $row;
                    }
                }
            }

            // Whole-offering meetings not tied to any specific component
            // (offering_component_id IS NULL) apply to every enrolled
            // student regardless of component membership.
            foreach ($meetings as $row) {
                if ($row['offering_component_id'] === null) {
                    $result[$uid][] = $row;
                }
            }
        }

        return $result;
    }

    /**
     * Step 9 explicit-component conflict check: does ANY of these specific
     * students already have an overlapping commitment? Never reduced to
     * home-section identity — two disjoint groups drawn from the same home
     * section with zero actual student overlap are correctly allowed to
     * share a time slot, because this resolves real per-student
     * commitments via commitmentsForStudents(), not section membership.
     *
     * A conflicting row is reported once per schedule_id, carrying every
     * affected user_id (never duplicated per student lookup).
     *
     * @param list<int>                                           $userIds
     * @param list<array{day:int,start:string,end:string}>        $proposedBlocks
     * @param list<int>                                           $excludeScheduleIds
     *
     * @return array{ok: bool, conflicts: list<array{schedule_id: int, subject_code: string, time_label: string, user_ids: list<int>}>}
     */
    public function studentConflicts(array $userIds, string $termAcademicYear, array $proposedBlocks, array $excludeScheduleIds = []): array
    {
        $commitments = $this->commitmentsForStudents($userIds, $termAcademicYear);
        $conflicts   = [];

        foreach ($commitments as $uid => $rows) {
            foreach ($rows as $row) {
                if (in_array((int) $row['schedule_id'], $excludeScheduleIds, true)) {
                    continue;
                }

                foreach ($proposedBlocks as $block) {
                    if (! ScheduleOverlap::clashes($block, $this->rowToMeeting($row))) {
                        continue;
                    }

                    $key = (int) $row['schedule_id'];

                    if (! isset($conflicts[$key])) {
                        $conflicts[$key] = [
                            'schedule_id'  => $key,
                            'subject_code' => $row['subject_code'],
                            'time_label'   => $row['time_label'],
                            'user_ids'     => [],
                        ];
                    }

                    if (! in_array($uid, $conflicts[$key]['user_ids'], true)) {
                        $conflicts[$key]['user_ids'][] = $uid;
                    }
                }
            }
        }

        return ['ok' => $conflicts === [], 'conflicts' => array_values($conflicts)];
    }

    /**
     * @return array{day: int, start: string, end: string}
     */
    private function rowToMeeting(array $row): array
    {
        return [
            'day'   => (int) $row['day'],
            'start' => substr((string) $row['start_time'], 0, 5),
            'end'   => substr((string) $row['end_time'], 0, 5),
        ];
    }
}

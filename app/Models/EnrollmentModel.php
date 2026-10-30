<?php

namespace App\Models;

use App\Libraries\ScheduleOverlap;
use App\Libraries\SubjectRequirementService;
use App\Services\TuitionAssessmentService;
use CodeIgniter\Model;

class EnrollmentModel extends Model
{
    protected $table      = 'enrollments';
    protected $primaryKey = 'enrollment_id';

    protected $allowedFields = [
        'user_id',
        'section_id',
        'offering_id',
        'subject_code',
        'method',
        'status',
        'academic_year',
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
     * Every rejection path calls resetTransStatus() right after
     * transRollback(). Without it, an *expected*, handled rejection here
     * (already enrolled, section full, etc.) leaves the connection's
     * transStatus flag poisoned to false for the rest of the request/process
     * — CI4 only auto-clears that flag when transStrict is off (it isn't,
     * here). Any *later* transStart()/transComplete() pair on the same
     * connection would then silently report failure and roll back even
     * though its own queries succeeded. Caught the hard way: a block-enroll
     * loop that hit one duplicate enrollment, followed later in the same
     * process by CourseOfferingModel::createMerge(), made the merge appear
     * to fail with no underlying SQL error at all.
     *
     * $prerequisiteOverride ({reason, by}) lets a registrar/admin enroll a
     * student despite an UNMET PREREQUISITE — and only that. The handbook
     * makes prerequisites "automatic; overridable with adviser approval",
     * while schedule conflicts, duplicates and curriculum membership stay
     * hard rules. A used override is written to enrollment_logs with the
     * missing subjects, the reason and the approver.
     *
     * $holdOverride bypasses an active financial hold (finance.override
     * only) — checked here, never against raw balance, since a normal
     * unpaid installment balance must never block routine enrollment; only
     * an explicit Finance-placed hold does.
     *
     * @param array{reason: string, by: int}|null $prerequisiteOverride
     * @return array{ok: bool, error?: string, enrollment_id?: int}
     */
    public function enrollOne(int $userId, int $sectionId, string $subjectCode, string $method = 'manual', ?array $prerequisiteOverride = null, bool $windowOverride = false, ?array $capacityOverride = null, bool $holdOverride = false): array
    {
        if (! $windowOverride && ! (new AcademicYearModel())->isRegistrationOpen()) {
            return ['ok' => false, 'error' => 'Registration is closed for the current term.'];
        }

        // Sections are year-scoped now (no semester identity of their
        // own), so "which term's meetings/conflicts/tuition" has to come
        // from somewhere else — the active term, same term
        // isRegistrationOpen() above just gated on. This is also the value
        // stamped onto the new enrollments.academic_year column below.
        $termAcademicYear = (new AcademicYearModel())->activeFormatted();

        if (! $holdOverride) {
            $student = (new StudentModel())->where('user_id', $userId)->first();

            if ($student !== null && (new FinancialHoldModel())->isOnHold((int) $student['student_id'])) {
                return ['ok' => false, 'error' => 'This student has an active financial hold. Contact the Finance office.'];
            }
        }

        $db = $this->db;
        $db->transStart();

        $section = $this->lockSection($sectionId);

        if ($section === null) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => 'That section no longer exists.'];
        }

        $meetings = $this->meetingsFor($section, $subjectCode, $termAcademicYear);

        if ($meetings === []) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => "{$subjectCode} is not scheduled for this section."];
        }

        // A subject_code being scheduled somewhere is not the same as it
        // being a real catalog subject — plotted_schedules/faculty_assignments
        // carry no subject_id FK, so a typo'd or never-registered code could
        // otherwise slip all the way into a real enrollment. This only gates
        // NEW enrollments; outsideCurriculum() and every other read path that
        // evaluates EXISTING historical rows is untouched.
        if (! $this->subjectExistsInCatalog($subjectCode)) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => "{$subjectCode} is not a recognized subject. Please contact the registrar."];
        }

        $missing = $this->unmetPrerequisites($userId, $subjectCode);

        $overrideNote = null;
        $overrideBy   = $prerequisiteOverride['by'] ?? $capacityOverride['by'] ?? $userId;

        if ($missing !== []) {
            if ($prerequisiteOverride === null || trim($prerequisiteOverride['reason']) === '') {
                $db->transRollback();
                $db->resetTransStatus();

                return ['ok' => false, 'error' => "Prerequisite not met for {$subjectCode}: " . implode(', ', $missing) . ' must be passed first.'];
            }

            $overrideNote = 'Prerequisite override (unmet: ' . implode(', ', $missing) . '): ' . trim($prerequisiteOverride['reason']);
        }

        $outsideCurriculum = $this->outsideCurriculum($userId, $subjectCode);

        if ($outsideCurriculum !== null) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => $outsideCurriculum];
        }

        $conflict = $this->findConflict($userId, $termAcademicYear, $meetings, $subjectCode);

        if ($conflict !== null) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => "Schedule conflict: {$subjectCode} overlaps {$conflict}, which you're already enrolled in."];
        }

        // If this (section, subject) has been merged into a course offering
        // (see CourseOfferingModel::createMerge), capacity is checked
        // against the MERGED pool across every attached section, not this
        // section alone — that's the entire point of a merge. Sections not
        // part of any offering behave exactly as before.
        $offering   = (new CourseOfferingModel())->offeringForSection($sectionId, $subjectCode);
        $offeringId = $offering['offering_id'] ?? null;
        $capacity   = $offering !== null ? $offering['capacity'] : $section['capacity'];

        if ($capacity !== null) {
            $memberQuery = $offeringId !== null
                ? ['offering_id' => $offeringId, 'user_id' => $userId, 'status' => 'enrolled']
                : ['section_id' => $sectionId, 'user_id' => $userId, 'status' => 'enrolled'];

            $alreadyMember = $this->where($memberQuery)->countAllResults() > 0;

            $rosterCount = $offeringId !== null
                ? (new CourseOfferingModel())->rosterCount($offeringId)
                : $this->rosterCount($sectionId);

            if (! $alreadyMember && $rosterCount >= (int) $capacity) {
                if ($capacityOverride === null || trim($capacityOverride['reason']) === '') {
                    $db->transRollback();
                    $db->resetTransStatus();

                    return ['ok' => false, 'error' => $offeringId !== null ? 'This merged class is full.' : 'This section is full.'];
                }

                $overrideNote = ($overrideNote !== null ? $overrideNote . '; ' : '')
                    . 'Capacity override (roster ' . $rosterCount . '/' . (int) $capacity . '): ' . trim($capacityOverride['reason']);
            }
        }

        // A previously DROPPED enrollment in this same subject still holds
        // the (user, section, subject) unique key, so re-enrolling
        // reactivates that row (and logs it) instead of inserting a second.
        $previous = $this->where(['user_id' => $userId, 'section_id' => $sectionId, 'subject_code' => $subjectCode])->first();

        if ($previous !== null && $previous['status'] !== 'enrolled') {
            $this->update((int) $previous['enrollment_id'], [
                'offering_id'   => $offeringId,
                'method'        => $method,
                'status'        => 'enrolled',
                'academic_year' => $termAcademicYear,
            ]);
            $this->logStatusChange((int) $previous['enrollment_id'], $previous['status'], 'enrolled', 'Re-enrolled' . ($overrideNote !== null ? ' — ' . $overrideNote : ''), $overrideBy);
            $db->transComplete();

            $this->recomputeTuition($userId, $termAcademicYear);

            return ['ok' => true, 'enrollment_id' => (int) $previous['enrollment_id']];
        }

        try {
            $inserted = $this->insert([
                'user_id'       => $userId,
                'section_id'    => $sectionId,
                'offering_id'   => $offeringId,
                'subject_code'  => $subjectCode,
                'method'        => $method,
                'status'        => 'enrolled',
                'academic_year' => $termAcademicYear,
            ]);
        } catch (\Exception $e) {
            $inserted = false;
        }

        // Unique key collision: already enrolled in this subject. Depending
        // on DBDebug, this surfaces either as a thrown exception (caught
        // above) or a falsy return from insert() — handle both.
        if ($inserted === false) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => "Already enrolled in {$subjectCode}."];
        }

        $enrollmentId = $this->getInsertID();

        if ($overrideNote !== null) {
            $this->logStatusChange((int) $enrollmentId, null, 'enrolled', $overrideNote, $overrideBy);
        }

        $db->transComplete();

        $this->recomputeTuition($userId, $termAcademicYear);

        return ['ok' => true, 'enrollment_id' => $enrollmentId];
    }

    /**
     * Read-only preview of whether $userId could be enrolled into
     * (section, subject) right now — runs the SAME checks enrollOne() does
     * (catalog membership, prerequisites, curriculum membership, schedule
     * conflict, capacity) without locking or inserting anything. Powers the
     * enlistment cart's live "can I add this?" feedback (StudentEnlistmentModel::
     * addItem()) so that rule set is never duplicated. Unlike enrollOne(),
     * there is no override path here — an unmet prerequisite or a full
     * section simply can't be added to a cart; overrides stay a registrar/
     * admin capability exercised only at conversion time.
     *
     * $termAcademicYear is the term-scoped academic year (e.g. "AY 2026 -
     * 2027 · 2nd Semester") being checked against — sections no longer
     * carry their own semester identity, so unlike a direct enrollOne()
     * call (always the active term) this has to come from the caller.
     * StudentEnlistmentModel::addItem() passes the enlistment's own term,
     * which is the UPCOMING term, not the active one.
     *
     * @return array{ok: bool, error?: string, units?: float}
     */
    public function checkEligibility(int $userId, int $sectionId, string $subjectCode, string $termAcademicYear): array
    {
        $section = db_connect()->table('sections')->where('section_id', $sectionId)->get()->getRowArray();

        if ($section === null) {
            return ['ok' => false, 'error' => 'That section no longer exists.'];
        }

        $meetings = $this->meetingsFor($section, $subjectCode, $termAcademicYear);

        if ($meetings === []) {
            return ['ok' => false, 'error' => "{$subjectCode} is not scheduled for this section."];
        }

        if (! $this->subjectExistsInCatalog($subjectCode)) {
            return ['ok' => false, 'error' => "{$subjectCode} is not a recognized subject. Please contact the registrar."];
        }

        $missing = $this->unmetPrerequisites($userId, $subjectCode);

        if ($missing !== []) {
            // A staff-granted EnlistmentOverride (Student Progress ->
            // "clear an irregular/catch-up student") is exactly what this
            // gate exists to defer to — the wizard already shows this
            // subject as selectable once an override exists (see
            // StudentEnlistment::wizard()'s dueSubjects filter); without
            // this check, addItem() would still silently reject it,
            // contradicting what the UI just told the student they could
            // do.
            $student = (new StudentModel())->where('user_id', $userId)->first();
            $overridden = $student !== null
                && (new EnlistmentOverrideModel())->hasOverride((int) $student['student_id'], $subjectCode, $termAcademicYear);

            if (! $overridden) {
                return ['ok' => false, 'error' => "Prerequisite not met for {$subjectCode}: " . implode(', ', $missing) . ' must be passed first.'];
            }
        }

        $outsideCurriculum = $this->outsideCurriculum($userId, $subjectCode);

        if ($outsideCurriculum !== null) {
            return ['ok' => false, 'error' => $outsideCurriculum];
        }

        $conflict = $this->findConflict($userId, $termAcademicYear, $meetings, $subjectCode);

        if ($conflict !== null) {
            return ['ok' => false, 'error' => "Schedule conflict: {$subjectCode} overlaps {$conflict}, which you're already enrolled in."];
        }

        $offering   = (new CourseOfferingModel())->offeringForSection($sectionId, $subjectCode);
        $offeringId = $offering['offering_id'] ?? null;
        $capacity   = $offering !== null ? $offering['capacity'] : $section['capacity'];

        if ($capacity !== null) {
            $memberQuery = $offeringId !== null
                ? ['offering_id' => $offeringId, 'user_id' => $userId, 'status' => 'enrolled']
                : ['section_id' => $sectionId, 'user_id' => $userId, 'status' => 'enrolled'];

            $alreadyMember = $this->where($memberQuery)->countAllResults() > 0;

            $rosterCount = $offeringId !== null
                ? (new CourseOfferingModel())->rosterCount($offeringId)
                : $this->rosterCount($sectionId);

            if (! $alreadyMember && $rosterCount >= (int) $capacity) {
                return ['ok' => false, 'error' => $offeringId !== null ? 'This merged class is full.' : 'This section is full.'];
            }
        }

        $subject = (new SubjectModel())->where('subject_code', $subjectCode)->first();

        return ['ok' => true, 'units' => $subject !== null ? (float) $subject['units'] : 0.0];
    }

    /**
     * The (section, subject) classes a registrar can enroll a student into:
     * everything plotted in the given term for the given program. Each entry
     * is one option for the "Enroll in a subject" form.
     *
     * @return list<array{section_id: int, subject_code: string, subject_title: string, year_level: string, section: string}>
     */
    public function classOptions(string $programName, string $academicYear): array
    {
        return array_map(static function (array $r): array {
            $r['section_id'] = (int) $r['section_id'];

            return $r;
        }, $this->db->table('plotted_schedules p')
            ->select('sec.section_id, p.subject_code, MAX(p.subject_title) AS subject_title, p.year_level, p.section')
            ->join('sections sec', 'sec.program = p.program AND sec.year_level = p.year_level AND sec.section = p.section')
            // Sections are year-scoped now — matched on the school year
            // only, as a separate condition rather than in the join (the
            // join itself no longer has a term-scoped column to match on).
            ->where('sec.academic_year', AcademicYearModel::yearOnly($academicYear))
            ->where('p.academic_year', $academicYear)
            ->where('p.program', $programName)
            ->groupBy('sec.section_id, p.subject_code, p.year_level, p.section')
            ->orderBy('p.year_level')
            ->orderBy('p.section')
            ->orderBy('p.subject_code')
            ->get()
            ->getResultArray());
    }

    /**
     * Every place $subjectCode is actually scheduled this term within
     * $programName — the subject-keyed counterpart to classOptions(), for
     * the student enlistment screen's curriculum-driven subject list (a
     * subject the student's curriculum says they need, not a section they
     * happened to browse). Meetings are never collapsed (a subject can have
     * more than one meeting block) and capacity/roster resolution follows
     * the exact same offering-vs-section branch checkEligibility() uses, so
     * the numbers shown here always match what will actually be enforced
     * when the student tries to add the subject to their cart.
     *
     * @return list<array{
     *     section_id: int, section_label: string, offering_id: ?int,
     *     capacity: ?int, roster_count: int, available: ?int,
     *     meetings: list<array{day: int, start: string, end: string, room: ?string, prof: ?string}>
     * }>
     */
    public function subjectOfferings(string $subjectCode, string $programName, string $academicYear): array
    {
        $sectionModel = new SectionModel();
        $offerings    = [];

        // Legacy-identity rows: this subject plotted directly against one of
        // this program's sections (offering_id IS NULL on the row).
        $legacyRows = $this->db->table('plotted_schedules')
            ->where('offering_id', null)
            ->where('academic_year', $academicYear)
            ->where('program', $programName)
            ->where('subject_code', $subjectCode)
            ->get()
            ->getResultArray();

        $legacyByTuple = [];

        foreach ($legacyRows as $row) {
            $key = $row['year_level'] . '|' . $row['section'];
            $legacyByTuple[$key]['year_level'] ??= $row['year_level'];
            $legacyByTuple[$key]['section'] ??= $row['section'];
            $legacyByTuple[$key]['meetings'][] = [
                'day'   => (int) $row['day'],
                'start' => substr($row['start_time'], 0, 5),
                'end'   => substr($row['end_time'], 0, 5),
                'room'  => $row['room'] ?: null,
                'prof'  => $row['prof'] ?: null,
            ];
        }

        foreach ($legacyByTuple as $tuple) {
            $section = $sectionModel->forContext(AcademicYearModel::yearOnly($academicYear), $programName, $tuple['year_level'], $tuple['section']);

            if ($section === null) {
                continue;
            }

            $sectionId   = (int) $section['section_id'];
            $capacity    = $section['capacity'];
            $rosterCount = $this->rosterCount($sectionId);

            usort($tuple['meetings'], static fn (array $a, array $b): int => [$a['day'], $a['start']] <=> [$b['day'], $b['start']]);

            $offerings[] = [
                'section_id'    => $sectionId,
                'section_label' => $tuple['year_level'] . ' · ' . $tuple['section'],
                'offering_id'   => null,
                'capacity'      => $capacity !== null ? (int) $capacity : null,
                'roster_count'  => $rosterCount,
                'available'     => $capacity !== null ? (int) $capacity - $rosterCount : null,
                'meetings'      => $tuple['meetings'],
            ];
        }

        // Offering-identity rows: this subject merged into a course_offering
        // reachable through at least one of this program's sections.
        $courseOfferings = (new CourseOfferingModel())
            ->where('subject_code', $subjectCode)
            ->where('academic_year', $academicYear)
            ->findAll();

        $offeringModel = new CourseOfferingModel();

        foreach ($courseOfferings as $offering) {
            $offeringId      = (int) $offering['offering_id'];
            $linkedSectionIds = $offeringModel->sectionIdsFor($offeringId);

            if ($linkedSectionIds === []) {
                continue;
            }

            $linkedSections = $sectionModel->whereIn('section_id', $linkedSectionIds)->where('program', $programName)->findAll();

            if ($linkedSections === []) {
                continue;
            }

            $representative = $linkedSections[0];
            $label = count($linkedSections) === 1
                ? $representative['year_level'] . ' · ' . $representative['section']
                : implode(', ', array_map(static fn (array $s): string => $s['year_level'] . ' · ' . $s['section'], $linkedSections));

            $meetings = array_map(static fn (array $m): array => [
                'day'   => (int) $m['day'],
                'start' => substr($m['start_time'], 0, 5),
                'end'   => substr($m['end_time'], 0, 5),
                'room'  => $m['room'] ?: null,
                'prof'  => $m['prof'] ?: null,
            ], (new PlottedScheduleModel())->forOffering($offeringId));

            usort($meetings, static fn (array $a, array $b): int => [$a['day'], $a['start']] <=> [$b['day'], $b['start']]);

            $capacity    = $offering['capacity'];
            $rosterCount = $offeringModel->rosterCount($offeringId);

            $offerings[] = [
                'section_id'    => (int) $representative['section_id'],
                'section_label' => $label,
                'offering_id'   => $offeringId,
                'capacity'      => $capacity !== null ? (int) $capacity : null,
                'roster_count'  => $rosterCount,
                'available'     => $capacity !== null ? (int) $capacity - $rosterCount : null,
                'meetings'      => $meetings,
            ];
        }

        return $offerings;
    }

    /**
     * Drops one enrollment: status -> 'dropped', row kept as history, change
     * logged. Never a hard delete.
     *
     * Refused when the enrollment isn't 'enrolled' anymore, or when a grade
     * has already been recorded for it (any state — drop/withdraw of a
     * graded class is a registrar decision, not a self-service one).
     * $ownerUserId, when given, must be the enrollment's student — a student
     * can only ever drop their own enrollment (IDOR guard); the row is
     * reported as "not found" otherwise so ids can't be probed.
     *
     * Freeing the seat needs no extra work: capacity/roster counts only
     * ever count status = 'enrolled'.
     *
     * $holdOverride, same shape and rationale as enrollOne()'s.
     *
     * @return array{ok: bool, error?: string, enrollment?: array}
     */
    public function drop(int $enrollmentId, string $reason, ?int $changedBy, ?int $ownerUserId = null, bool $holdOverride = false): array
    {
        $db     = $this->db;
        $suffix = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';

        if (! $holdOverride) {
            $owner = $this->find($enrollmentId);

            if ($owner !== null) {
                $student = (new StudentModel())->where('user_id', (int) $owner['user_id'])->first();

                if ($student !== null && (new FinancialHoldModel())->isOnHold((int) $student['student_id'])) {
                    return ['ok' => false, 'error' => 'This student has an active financial hold. Contact the Finance office.'];
                }
            }
        }

        $db->transStart();

        // e.* already carries the correct (term-scoped) academic_year —
        // selecting sec.academic_year too would silently overwrite it with
        // the section's year-only value in the result array (same column
        // name), which is exactly wrong for recomputeTuition() below.
        $row = $db->query(
            "SELECT e.* FROM enrollments e
             JOIN sections sec ON sec.section_id = e.section_id
             WHERE e.enrollment_id = ?{$suffix}",
            [$enrollmentId]
        )->getRowArray();

        if ($row === null || ($ownerUserId !== null && (int) $row['user_id'] !== $ownerUserId)) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => 'Enrollment not found.'];
        }

        if ($row['status'] !== 'enrolled') {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => "That subject is already {$row['status']}."];
        }

        if ($db->table('grades')->where('enrollment_id', $enrollmentId)->countAllResults() > 0) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => 'A grade has already been recorded for this subject, so it can no longer be dropped here. Contact the registrar.'];
        }

        $this->update($enrollmentId, ['status' => 'dropped']);
        $this->logStatusChange($enrollmentId, 'enrolled', 'dropped', $reason, $changedBy);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not drop the subject. Please retry.'];
        }

        $this->recomputeTuition((int) $row['user_id'], $row['academic_year']);

        return ['ok' => true, 'enrollment' => array_merge($row, ['status' => 'dropped'])];
    }

    /**
     * A student's current enrollments with what the "My Enrolled Subjects"
     * card needs: the section, and whether it can still be dropped.
     *
     * @return list<array>
     */
    public function activeForStudent(int $userId): array
    {
        return $this->db->table('enrollments e')
            ->select('e.enrollment_id, e.subject_code, e.method, e.academic_year, sec.program, sec.year_level, sec.section,
                      (SELECT COUNT(*) FROM grades g WHERE g.enrollment_id = e.enrollment_id) AS grade_count')
            ->join('sections sec', 'sec.section_id = e.section_id')
            ->where('e.user_id', $userId)
            ->where('e.status', 'enrolled')
            ->orderBy('e.academic_year', 'DESC')
            ->orderBy('e.subject_code')
            ->get()
            ->getResultArray();
    }

    private function logStatusChange(int $enrollmentId, ?string $from, string $to, ?string $reason, ?int $changedBy): void
    {
        $this->db->table('enrollment_logs')->insert([
            'enrollment_id' => $enrollmentId,
            'from_status'   => $from,
            'to_status'     => $to,
            'reason'        => $reason,
            'changed_by'    => $changedBy,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Every subject actually plotted for a section — the set block
     * enrollment enrolls a student into. $termAcademicYear defaults to the
     * active term: sections no longer carry their own semester identity,
     * so "which term's plot" has to be told rather than read off the
     * section, and every current caller (self-service/admin block enroll)
     * means the active term.
     *
     * @return string[]
     */
    public function subjectsFor(array $section, ?string $termAcademicYear = null): array
    {
        $termAcademicYear ??= (new AcademicYearModel())->activeFormatted();

        return array_column(
            db_connect()->table('plotted_schedules')
                ->select('subject_code')
                ->where('academic_year', $termAcademicYear)
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
     * Enrolls $userId into every subject plotted for $sectionId — the
     * shared core behind both the student's own self-service block-enroll
     * action (StudentEnrollment::enrollBlock(), which adds the "only the
     * active year, and not while enlistment is open" guards on top) and
     * admin account creation (Admin\UserController::store()) — a student
     * assigned straight to a section there should actually end up
     * enrolled, not just carry an unused section_id. Tuition is assessed
     * for free: enrollOne() recomputes it on every successful item.
     *
     * @return array{ok: bool, error?: string, enrolled?: list<string>, failed?: list<array{subject_code: string, error: string}>}
     */
    public function enrollBlock(int $userId, int $sectionId): array
    {
        $section = (new SectionModel())->find($sectionId);

        if ($section === null) {
            return ['ok' => false, 'error' => 'That section no longer exists.'];
        }

        $subjects = $this->subjectsFor($section);

        if ($subjects === []) {
            return ['ok' => false, 'error' => 'No subjects are scheduled for this section yet.'];
        }

        $enrolled = [];
        $failed   = [];

        foreach ($subjects as $subjectCode) {
            $result = $this->enrollOne($userId, $sectionId, $subjectCode, 'block');

            if ($result['ok']) {
                $enrolled[] = $subjectCode;
            } else {
                $failed[] = ['subject_code' => $subjectCode, 'error' => $result['error']];
            }
        }

        return ['ok' => true, 'enrolled' => $enrolled, 'failed' => $failed];
    }

    /**
     * Every actively-enrolled student in this section for this one subject
     * AND term (not the whole section's roster) — the class list grading
     * needs when the (section, subject) pair is NOT merged into a course
     * offering. $termAcademicYear filters on enrollments.academic_year,
     * not the section's (now year-only) one — a section spans every
     * semester of its school year, so the term has to be named explicitly.
     *
     * @return list<array{enrollment_id: int, user_id: int, username: string}>
     */
    public function listRosterForSectionSubject(int $sectionId, string $subjectCode, string $termAcademicYear): array
    {
        return db_connect()->table('enrollments e')
            ->select('e.enrollment_id, e.user_id, u.username')
            ->join('users u', 'u.id = e.user_id')
            ->where('e.section_id', $sectionId)
            ->where('e.subject_code', $subjectCode)
            ->where('e.academic_year', $termAcademicYear)
            ->where('e.status', 'enrolled')
            ->orderBy('u.username')
            ->get()
            ->getResultArray();
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
     * This subject's plotted meetings (day/start/end) for one section, for
     * the given TERM — sections are year-scoped now, so which semester's
     * plot to read is no longer implied by $section itself.
     */
    private function meetingsFor(array $section, string $subjectCode, string $termAcademicYear): array
    {
        $rows = db_connect()->table('plotted_schedules')
            ->select('day, start_time, end_time')
            ->where('academic_year', $termAcademicYear)
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
     * A student pinned to a curriculum version (students.curriculum_id) may
     * only enroll in subjects that belong to that version — the handbook's
     * "per-subject validation against the student's curriculum version".
     * Any year level/semester counts (irregular students legitimately take
     * subjects out of sequence); it is membership that is enforced.
     *
     * Skipped (returns null) when the user isn't a student, has no
     * curriculum assigned yet, or the code isn't a catalog Subject row —
     * those are data gaps, not the student's fault, and blocking on them
     * would freeze enrollment before the backfill is done.
     *
     * @return string|null an error message, or null when enrollment may proceed
     */
    private function outsideCurriculum(int $userId, string $subjectCode): ?string
    {
        $student = $this->db->table('students')
            ->select('curriculum_id')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if ($student === null || $student['curriculum_id'] === null) {
            return null;
        }

        $subject = $this->db->table('subjects')->select('subject_id')->where('subject_code', $subjectCode)->get()->getRowArray();

        if ($subject === null) {
            return null;
        }

        $inCurriculum = $this->db->table('curriculum_subjects')
            ->where('curriculum_id', (int) $student['curriculum_id'])
            ->where('subject_id', (int) $subject['subject_id'])
            ->where('is_active', 1)
            ->countAllResults() > 0;

        return $inCurriculum ? null : "{$subjectCode} is not part of your assigned curriculum.";
    }

    /**
     * Whether $subjectCode resolves to a real, active row in the central
     * subjects catalog. Used only to gate NEW enrollments (enrollOne()) —
     * never called from a read/history path, so it has no effect on how
     * existing enrollment records are evaluated or displayed.
     */
    private function subjectExistsInCatalog(string $subjectCode): bool
    {
        return $this->db->table('subjects')
            ->where('subject_code', $subjectCode)
            ->where('is_active', 1)
            ->countAllResults() > 0;
    }

    /**
     * Subject codes of every prerequisite $subjectCode has that the student
     * has NOT yet passed (a locked grade with is_passing = 1, from any prior
     * term/section — prerequisite credit doesn't expire). A prerequisite is
     * also satisfied by an explicitly configured active substitute (Step 8
     * — App\Libraries\SubjectRequirementService; the same centralized rule
     * demand aggregation uses, not a second copy of the logic). Empty array
     * means clear to enroll. If $subjectCode isn't in the subject catalog at
     * all (e.g. plotted under a code the admin hasn't registered as a
     * Subject row yet), the check is skipped rather than blocking enrollment
     * on a data gap that isn't the student's fault.
     *
     * Public: also used by App\Libraries\StudentProgressService to surface
     * "why can't this student start this subject yet" on the chairperson's
     * student-progress detail view, reusing this exact rule rather than
     * re-deriving it.
     *
     * @return string[]
     */
    public function unmetPrerequisites(int $userId, string $subjectCode): array
    {
        $subject = (new SubjectModel())->where('subject_code', $subjectCode)->first();

        if ($subject === null) {
            return [];
        }

        $prereqIds = array_column(
            (new SubjectPrerequisiteModel())->where('subject_id', $subject['subject_id'])->findAll(),
            'prerequisite_subject_id'
        );

        if ($prereqIds === []) {
            return [];
        }

        $prereqSubjects = (new SubjectModel())->whereIn('subject_id', $prereqIds)->findAll();

        $passedCodes = array_column(
            $this->db->table('grades')
                ->select('subject_code')
                ->where('user_id', $userId)
                ->where('status', 'locked')
                ->where('is_passing', 1)
                ->distinct()
                ->get()
                ->getResultArray(),
            'subject_code'
        );

        $requirementService = new SubjectRequirementService();
        $missing = [];

        foreach ($prereqSubjects as $prereq) {
            $acceptableIds = $requirementService->satisfyingSubjectIds((int) $prereq['subject_id']);
            $acceptableCodes = array_column(
                (new SubjectModel())->whereIn('subject_id', $acceptableIds)->findAll(),
                'subject_code'
            );

            if (array_intersect($acceptableCodes, $passedCodes) === []) {
                $missing[] = $prereq['subject_code'];
            }
        }

        return $missing;
    }

    /**
     * Corequisites of $subjectCode that are NOT yet satisfied for this
     * student+term. Unlike unmetPrerequisites(), satisfaction is about
     * CONCURRENCY, not prior completion: a corequisite is satisfied if it's
     * already passed, already actively enrolled this academic year, or
     * already sitting in the student's own open enlistment cart for this
     * term (so either half of a pair can be added first — see
     * StudentEnlistmentModel::submit()'s corequisite gate, the only place
     * this is actually enforced; the enlistment subject list only displays
     * it informationally, never excludes on it, to avoid a chicken-and-egg
     * deadlock for subjects whose corequisite is also not yet started).
     *
     * @return string[]
     */
    public function unmetCorequisites(int $userId, string $subjectCode, string $academicYear): array
    {
        $subject = (new SubjectModel())->where('subject_code', $subjectCode)->first();

        if ($subject === null) {
            return [];
        }

        $coreqIds = array_column(
            (new SubjectCorequisiteModel())->where('subject_id', $subject['subject_id'])->findAll(),
            'corequisite_subject_id'
        );

        if ($coreqIds === []) {
            return [];
        }

        $coreqSubjects = (new SubjectModel())->whereIn('subject_id', $coreqIds)->findAll();

        $passedCodes = array_column(
            $this->db->table('grades')
                ->select('subject_code')
                ->where('user_id', $userId)
                ->where('status', 'locked')
                ->where('is_passing', 1)
                ->distinct()
                ->get()
                ->getResultArray(),
            'subject_code'
        );

        $enrolledCodes = array_column(
            array_filter($this->activeForStudent($userId), static fn (array $e): bool => $e['academic_year'] === $academicYear),
            'subject_code'
        );

        $student = (new StudentModel())->where('user_id', $userId)->first();
        $cartCodes = $student !== null
            ? (new StudentEnlistmentModel())->cartSubjectCodes((int) $student['student_id'], $academicYear)
            : [];

        $satisfiedCodes = array_merge($passedCodes, $enrolledCodes, $cartCodes);

        $missing = [];

        foreach ($coreqSubjects as $coreq) {
            if (! in_array($coreq['subject_code'], $satisfiedCodes, true)) {
                $missing[] = $coreq['subject_code'];
            }
        }

        return $missing;
    }

    /**
     * The subject_code of the first already-enrolled subject (same TERM —
     * not just the same school year, which sections no longer distinguish)
     * whose meetings clash with $meetings, or null when clear. Filters on
     * enrollments.academic_year (the term-scoped identity an enrollment
     * carries independently of its now year-scoped section), not
     * sections.academic_year.
     */
    private function findConflict(int $userId, string $academicYear, array $meetings, string $excludeSubject): ?string
    {
        $rows = db_connect()->table('enrollments e')
            ->select('e.subject_code, s.program, s.year_level, s.section')
            ->join('sections s', 's.section_id = e.section_id')
            ->where('e.user_id', $userId)
            ->where('e.status', 'enrolled')
            ->where('e.academic_year', $academicYear)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            if ($row['subject_code'] === $excludeSubject) {
                continue;
            }

            $otherMeetings = $this->meetingsFor($row, $row['subject_code'], $academicYear);

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

    /**
     * Keeps the student's tuition assessment in sync with their roster.
     * Runs after the enrollment change's own transaction has already
     * committed — a billing recompute must never be able to roll back an
     * otherwise-successful enroll/drop, so failures here are logged, not
     * surfaced to the caller.
     */
    private function recomputeTuition(int $userId, string $academicYear): void
    {
        $student = $this->db->table('students')->select('student_id')->where('user_id', $userId)->get()->getRowArray();

        if ($student === null) {
            return;
        }

        try {
            (new TuitionAssessmentService())->recompute((int) $student['student_id'], $academicYear);
        } catch (\Throwable $e) {
            log_message('error', '[Tuition] recompute failed for student ' . $student['student_id'] . ': ' . $e->getMessage());
        }
    }
}

<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * One schedulable instance of a subject's component split (e.g. "Lecture",
 * "Laboratory Group A") belonging to one course_offering. Owns:
 *
 *  - instantiation from subject_component_templates at offering-creation
 *    time (a one-time snapshot, never retroactively synced to later
 *    template edits);
 *  - explicit-roster membership (assignStudent/removeStudent), including
 *    'one_of' mutual-exclusivity enforcement AT ASSIGNMENT TIME rather than
 *    only detecting duplicates after the fact;
 *  - soft deactivation/reactivation;
 *  - the single centralized coverage/completeness calculation — the
 *    dashboard and scheduler UI consume completeness() directly and never
 *    reimplement this logic.
 */
class OfferingComponentModel extends Model
{
    protected $table      = 'offering_components';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'offering_id',
        'subject_component_template_id',
        'component_type_id',
        'name',
        'is_required',
        'required_hours',
        'capacity',
        'preferred_room_type',
        'roster_mode',
        'is_active',
    ];

    protected $useTimestamps = true;

    public const ROSTER_MODES = ['inherited', 'explicit'];

    /**
     * Snapshots every active subject_component_templates row for $subjectId
     * into one offering_components row each, for a newly created offering.
     * A subject with zero active templates produces zero offering_components
     * rows — the offering stays on the plain Step 5 path, byte-for-byte,
     * with offering_component_id left NULL everywhere.
     */
    public function instantiateForOffering(int $offeringId, int $subjectId): void
    {
        $templates = (new SubjectComponentTemplateModel())->forSubject($subjectId, true);

        if ($templates === []) {
            return;
        }

        $rows = array_map(static fn (array $t): array => [
            'offering_id'                    => $offeringId,
            'subject_component_template_id'  => (int) $t['id'],
            'component_type_id'              => (int) $t['component_type_id'],
            'name'                           => $t['name'],
            'is_required'                    => (int) $t['is_required'],
            'required_hours'                 => $t['required_hours'],
            'capacity'                       => $t['default_capacity'],
            'preferred_room_type'            => $t['preferred_room_type'],
            'roster_mode'                    => 'inherited',
            'is_active'                      => 1,
            'created_at'                     => date('Y-m-d H:i:s'),
            'updated_at'                     => date('Y-m-d H:i:s'),
        ], $templates);

        $this->insertBatch($rows);
    }

    /**
     * Every active component instance for one offering, with its template's
     * assignment_rule attached (null for a component created without a
     * template, which is always its own singleton group).
     */
    public function activeForOffering(int $offeringId): array
    {
        return $this->db->table('offering_components oc')
            ->select('oc.*, sct.assignment_rule')
            ->join('subject_component_templates sct', 'sct.id = oc.subject_component_template_id', 'left')
            ->where('oc.offering_id', $offeringId)
            ->where('oc.is_active', 1)
            ->orderBy('oc.id')
            ->get()
            ->getResultArray();
    }

    /**
     * Current explicit members of one component (user_ids), regardless of
     * roster_mode — callers decide whether that's meaningful.
     *
     * @return list<int>
     */
    public function explicitMemberIds(int $offeringComponentId): array
    {
        return array_map('intval', array_column(
            $this->db->table('offering_component_students')->select('user_id')->where('offering_component_id', $offeringComponentId)->get()->getResultArray(),
            'user_id'
        ));
    }

    /**
     * The actual student population a component's schedule applies to:
     * every currently-enrolled parent-offering student for 'inherited',
     * or exactly the explicit membership rows for 'explicit'.
     *
     * @return list<int>
     */
    public function affectedUserIds(array $component): array
    {
        if ($component['roster_mode'] === 'explicit') {
            return $this->explicitMemberIds((int) $component['id']);
        }

        return array_map('intval', array_column((new CourseOfferingModel())->listRoster((int) $component['offering_id']), 'user_id'));
    }

    /**
     * Adds one student to an EXPLICIT component. Enforces, in order:
     *  - the component exists, is active, and is explicit-mode (inherited
     *    components never take explicit members — membership there is
     *    automatic, by definition);
     *  - the student currently belongs to the PARENT OFFERING'S real
     *    enrollment roster (never a home-section proxy);
     *  - capacity, when set;
     *  - for a 'one_of' template, the student is not already an active
     *    member of ANY OTHER active instance of the same template — this
     *    is what prevents the duplicate-assignment problem at its source,
     *    rather than only reporting it later in completeness().
     *
     * @return array{ok: bool, error?: string}
     */
    public function assignStudent(int $offeringComponentId, int $userId): array
    {
        $component = $this->find($offeringComponentId);

        if ($component === null) {
            return ['ok' => false, 'error' => 'Component not found.'];
        }

        if ((int) $component['is_active'] !== 1) {
            return ['ok' => false, 'error' => 'This component is inactive and cannot receive new roster assignments.'];
        }

        if ($component['roster_mode'] !== 'explicit') {
            return ['ok' => false, 'error' => 'Only explicit-roster components accept direct student assignment.'];
        }

        $parentRoster = array_map('intval', array_column((new CourseOfferingModel())->listRoster((int) $component['offering_id']), 'user_id'));

        if (! in_array($userId, $parentRoster, true)) {
            return ['ok' => false, 'error' => 'This student is not currently enrolled in the parent offering.'];
        }

        // Capacity and the 'one_of' sibling check both reason about every
        // component under this offering, so two concurrent assignStudent()
        // calls for the same offering (even against different sibling
        // components) must serialize on the offering row — otherwise both
        // can read "capacity available" / "no sibling membership" before
        // either inserts, and both commit past the limit.
        $db = $this->db;
        $db->transStart();

        $suffix = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';
        $db->query("SELECT offering_id FROM course_offerings WHERE offering_id = ?{$suffix}", [(int) $component['offering_id']]);

        $currentCount = $db->table('offering_component_students')->where('offering_component_id', $offeringComponentId)->countAllResults();

        if ($component['capacity'] !== null && $currentCount >= (int) $component['capacity']) {
            $db->transComplete();

            return ['ok' => false, 'error' => 'This component is at capacity.'];
        }

        if ($component['subject_component_template_id'] !== null) {
            $templateId = (int) $component['subject_component_template_id'];
            $template   = (new SubjectComponentTemplateModel())->find($templateId);

            if ($template !== null && $template['assignment_rule'] === 'one_of') {
                $siblingIds = array_map('intval', array_column(
                    $this->where('offering_id', $component['offering_id'])
                        ->where('subject_component_template_id', $templateId)
                        ->where('is_active', 1)
                        ->where('id !=', $offeringComponentId)
                        ->findAll(),
                    'id'
                ));

                if ($siblingIds !== []) {
                    $already = $db->table('offering_component_students')
                        ->whereIn('offering_component_id', $siblingIds)
                        ->where('user_id', $userId)
                        ->countAllResults() > 0;

                    if ($already) {
                        $db->transComplete();

                        return ['ok' => false, 'error' => 'This student already belongs to another instance of this required component — each student may belong to only one.'];
                    }
                }
            }
        }

        if ($db->table('offering_component_students')->where('offering_component_id', $offeringComponentId)->where('user_id', $userId)->countAllResults() > 0) {
            $db->transComplete();

            return ['ok' => false, 'error' => 'This student is already a member of this component.'];
        }

        $db->table('offering_component_students')->insert([
            'offering_component_id' => $offeringComponentId,
            'user_id'                => $userId,
            'created_at'             => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not assign this student. Please retry.'];
        }

        return ['ok' => true];
    }

    public function removeStudent(int $offeringComponentId, int $userId): array
    {
        $this->db->table('offering_component_students')
            ->where('offering_component_id', $offeringComponentId)
            ->where('user_id', $userId)
            ->delete();

        return ['ok' => true];
    }

    /**
     * Soft-deactivates a component. Historical schedule/roster/faculty rows
     * are left untouched — they simply stop participating in current
     * coverage, completeness, and conflict checking (see completeness() and
     * SchedulingConflictService::commitmentsForStudents(), which both filter
     * on is_active = 1).
     */
    public function deactivate(int $id, int $actorId): array
    {
        $before = $this->find($id);

        if ($before === null) {
            return ['ok' => false, 'error' => 'Component not found.'];
        }

        $this->update($id, ['is_active' => 0]);

        (new AuditLogModel())->record('offering_component.deactivated', 'offering_component', $id, $before, null, null, $actorId, null);

        return ['ok' => true];
    }

    public function reactivate(int $id, int $actorId): array
    {
        $before = $this->find($id);

        if ($before === null) {
            return ['ok' => false, 'error' => 'Component not found.'];
        }

        $this->update($id, ['is_active' => 1]);

        (new AuditLogModel())->record('offering_component.reactivated', 'offering_component', $id, $before, null, null, $actorId, null);

        return ['ok' => true];
    }

    /**
     * The single centralized coverage/completeness result for one offering.
     * Consumed by SharedSubjectDashboardService and the scheduler UI — never
     * reimplemented elsewhere.
     *
     * Grouping: active components sharing the same
     * subject_component_template_id are evaluated TOGETHER (e.g. Lab Group A
     * + Lab Group B); a component with no template is always its own
     * singleton group. Only ~5 queries total, independent of how many
     * components/students exist.
     *
     * @return array{offering_id: int, complete: bool, templates: list<array>}
     */
    public function completeness(int $offeringId): array
    {
        $components = $this->activeForOffering($offeringId);

        if ($components === []) {
            // Simple Step 5 offering — nothing to evaluate here at all; the
            // caller (dashboard) uses its own existing schedule-only check.
            return ['offering_id' => $offeringId, 'complete' => true, 'templates' => []];
        }

        $groups = [];

        foreach ($components as $c) {
            $groupKey = $c['subject_component_template_id'] !== null
                ? 'tpl_' . $c['subject_component_template_id']
                : 'standalone_' . $c['id'];

            $groups[$groupKey]['template_id']     = $c['subject_component_template_id'] !== null ? (int) $c['subject_component_template_id'] : null;
            $groups[$groupKey]['assignment_rule']  = $c['assignment_rule'] ?? 'shared';
            $groups[$groupKey]['required']         = $groups[$groupKey]['required'] ?? false;
            $groups[$groupKey]['required']         = $groups[$groupKey]['required'] || (int) $c['is_required'] === 1;
            $groups[$groupKey]['components'][]     = $c;
        }

        $allComponentIds = array_map(static fn (array $c) => (int) $c['id'], $components);

        $scheduleRows = $this->db->table('plotted_schedules')
            ->select('offering_component_id, day, start_time, end_time')
            ->whereIn('offering_component_id', $allComponentIds)
            ->get()
            ->getResultArray();

        $scheduledHoursByComponent = [];

        foreach ($scheduleRows as $row) {
            $cid      = (int) $row['offering_component_id'];
            $duration = (strtotime($row['end_time']) - strtotime($row['start_time'])) / 3600;
            $scheduledHoursByComponent[$cid] = ($scheduledHoursByComponent[$cid] ?? 0) + $duration;
        }

        $explicitComponentIds = array_map(static fn (array $c) => (int) $c['id'], array_filter($components, static fn (array $c) => $c['roster_mode'] === 'explicit'));

        $membershipByComponent = [];

        if ($explicitComponentIds !== []) {
            $memberRows = $this->db->table('offering_component_students')
                ->select('offering_component_id, user_id')
                ->whereIn('offering_component_id', $explicitComponentIds)
                ->get()
                ->getResultArray();

            foreach ($memberRows as $m) {
                $membershipByComponent[(int) $m['offering_component_id']][] = (int) $m['user_id'];
            }
        }

        $parentRoster = array_map('intval', array_column((new CourseOfferingModel())->listRoster($offeringId), 'user_id'));

        $templateResults = [];
        $offeringComplete = true;

        foreach ($groups as $group) {
            $required       = $group['required'];
            $assignmentRule = $group['assignment_rule'] ?? 'shared';
            $groupComponents = $group['components'];

            $hasActiveInherited = array_reduce($groupComponents, static fn ($carry, $c) => $carry || $c['roster_mode'] === 'inherited', false);

            $uncoveredCount  = 0;
            $duplicateCount  = 0;
            $rosterValid     = true;

            if ($required) {
                if ($hasActiveInherited) {
                    // An active inherited instance automatically covers
                    // every current parent-roster student — the explicit
                    // count below is skipped entirely for this group.
                    $uncoveredCount = 0;
                    $duplicateCount = 0;
                    $rosterValid    = true;
                } else {
                    $membershipCountByUser = array_fill_keys($parentRoster, 0);

                    foreach ($groupComponents as $c) {
                        foreach ($membershipByComponent[(int) $c['id']] ?? [] as $uid) {
                            if (array_key_exists($uid, $membershipCountByUser)) {
                                $membershipCountByUser[$uid]++;
                            }
                        }
                    }

                    foreach ($membershipCountByUser as $count) {
                        if ($count === 0) {
                            $uncoveredCount++;
                        } elseif ($count >= 2) {
                            $duplicateCount++;
                        }
                    }

                    if ($assignmentRule === 'one_of') {
                        $rosterValid = $uncoveredCount === 0 && $duplicateCount === 0;
                    } else {
                        // shared: multiple memberships are legal, never invalid.
                        $duplicateCount = 0;
                        $rosterValid    = $uncoveredCount === 0;
                    }
                }
            }

            $scheduleComplete = true;

            foreach ($groupComponents as $c) {
                if ($c['required_hours'] === null) {
                    continue;
                }

                $scheduled = $scheduledHoursByComponent[(int) $c['id']] ?? 0.0;

                if ($scheduled + 0.001 < (float) $c['required_hours']) {
                    $scheduleComplete = false;
                }
            }

            $complete = $required ? ($rosterValid && $scheduleComplete) : true;

            if ($required && ! $complete) {
                $offeringComplete = false;
            }

            $templateResults[] = [
                'template_id'                 => $group['template_id'],
                'required'                    => $required,
                'assignment_rule'             => $assignmentRule,
                'uncovered_count'              => $uncoveredCount,
                'duplicate_assignment_count'   => $duplicateCount,
                'roster_valid'                 => $rosterValid,
                'schedule_complete'            => $scheduleComplete,
                'complete'                     => $complete,
            ];
        }

        return ['offering_id' => $offeringId, 'complete' => $offeringComplete, 'templates' => $templateResults];
    }
}

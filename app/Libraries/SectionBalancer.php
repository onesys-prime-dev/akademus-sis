<?php

namespace App\Libraries;

use App\Models\AuditLogModel;
use App\Models\CapacityPolicyModel;
use App\Models\SectionModel;

/**
 * The database side of dynamic sectioning: reads a program + year level +
 * term's sections and headcounts, asks SectionAllocationService what the
 * distribution should be, and — only on an explicit, confirmed request —
 * applies it inside one transaction.
 *
 * Only the home-cohort roster (users.section_id) is redistributed, and only
 * for sections that are still safe to touch: not locked/finalized/closed,
 * auto-balance not switched off, and with no schedule, faculty, grades or
 * enrollments attached. Anything else is left exactly as it is and reported
 * back as the reason nothing moved.
 */
class SectionBalancer
{
    private const UNSAFE_STATUSES = ['finalized', 'locked', 'closed', 'merged'];

    private SectionAllocationService $svc;

    public function __construct(?SectionAllocationService $svc = null)
    {
        $this->svc = $svc ?? new SectionAllocationService();
    }

    /**
     * Read-only view: current distribution, proposal, who is affected, and why.
     */
    public function analyze(string $academicYear, string $program, string $yearLevel): array
    {
        $sections = (new SectionModel())->where([
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
        ])->orderBy('section')->findAll();

        return $this->analyzeSections($academicYear, $program, $yearLevel, $sections);
    }

    /**
     * Applies the proposal atomically. $reason is mandatory (audit).
     *
     * @return array{ok: bool, error?: string, analysis?: array, moved?: int, created?: list<string>, closed?: list<string>}
     */
    public function apply(string $academicYear, string $program, string $yearLevel, ?int $actorId, ?string $actorRole, string $reason): array
    {
        if (trim($reason) === '') {
            return ['ok' => false, 'error' => 'A reason is required to rebalance sections.'];
        }

        $db = db_connect();
        $db->transStart();

        // Serialize concurrent rebalances/assignments for this cohort group.
        $suffix   = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';
        $sections = $db->query(
            "SELECT * FROM sections WHERE academic_year = ? AND program = ? AND year_level = ? ORDER BY section{$suffix}",
            [$academicYear, $program, $yearLevel]
        )->getResultArray();

        $analysis = $this->analyzeSections($academicYear, $program, $yearLevel, $sections);
        $plan     = $analysis['plan'];

        if ($analysis['free_sections'] === []) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => $analysis['blocked_reason'] ?? 'Nothing can be rebalanced.', 'analysis' => $analysis];
        }

        $free     = $analysis['free_sections'];               // [{section_id, count, ...}] largest first
        $sizes    = $plan['sizes'];                           // largest first
        $targets  = [];
        $existing = array_column($sections, 'section');

        foreach ($free as $i => $s) {
            $targets[(int) $s['section_id']] = $sizes[$i] ?? 0;
        }

        $created = [];
        $model   = new SectionModel();

        // Extra sizes beyond the existing free sections need new sections.
        for ($i = count($free); $i < count($sizes); $i++) {
            $id = $this->createAutoSection($model, $academicYear, $program, $yearLevel, $existing);

            if ($id === null) {
                $db->transRollback();
                $db->resetTransStatus();

                return ['ok' => false, 'error' => 'Could not allocate a new section name. Please retry.'];
            }

            $existing[]         = $id['name'];
            $created[]          = $id['name'];
            $targets[$id['id']] = $sizes[$i];
        }

        // Move the surplus of over-full sections into under-full ones.
        $students = $this->studentsBySection(array_keys($targets));
        $surplus  = [];

        foreach ($targets as $sectionId => $size) {
            $have = $students[$sectionId] ?? [];

            if (count($have) > $size) {
                // Highest user ids move first: deterministic.
                $movers = array_slice($have, $size);
                $surplus = array_merge($surplus, $movers);
            }
        }

        $moved = 0;
        $log   = [];

        foreach ($targets as $sectionId => $size) {
            $have = count($students[$sectionId] ?? []);

            while ($have < $size && $surplus !== []) {
                $userId = array_shift($surplus);
                $db->table('users')->where('id', $userId)->update(['section_id' => $sectionId]);
                $log[] = ['user_id' => $userId, 'to_section_id' => $sectionId];
                $have++;
                $moved++;
            }
        }

        // Auto-created sections left empty by a shrink are closed, not deleted.
        $closed = [];

        foreach ($free as $s) {
            $sid = (int) $s['section_id'];

            if (($targets[$sid] ?? 0) === 0 && $s['origin'] === 'auto') {
                $db->table('sections')->where('section_id', $sid)->update(['status' => 'closed']);
                $closed[] = $s['section'];
            }
        }

        (new AuditLogModel())->record(
            'sections.rebalance',
            'section_group',
            "{$academicYear}|{$program}|{$yearLevel}",
            ['sizes' => $analysis['current_sizes']],
            ['sizes' => $plan['sizes'], 'created' => $created, 'closed' => $closed, 'moves' => $log],
            $reason,
            $actorId,
            $actorRole
        );

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Rebalance failed and was rolled back. Please retry.'];
        }

        return ['ok' => true, 'analysis' => $analysis, 'moved' => $moved, 'created' => $created, 'closed' => $closed];
    }

    /**
     * Assigns a student to a section's home roster, enforcing the resolved
     * ceiling (max + overflow) under a row lock. A chairperson may pass
     * $override with a reason to exceed it; the override is audited.
     *
     * @return array{ok: bool, error?: string, over_capacity?: bool}
     */
    public function assignStudent(int $userId, int $sectionId, ?int $actorId, ?string $actorRole, ?string $overrideReason = null): array
    {
        $db = db_connect();
        $db->transStart();

        $suffix  = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';
        $section = $db->query("SELECT * FROM sections WHERE section_id = ?{$suffix}", [$sectionId])->getRowArray();

        if ($section === null) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => 'That section no longer exists.'];
        }

        if (in_array($section['status'], ['closed', 'merged'], true)) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'error' => "That section is {$section['status']} and cannot take students."];
        }

        $current = $db->table('users')->select('section_id')->where('id', $userId)->get()->getRowArray();

        if ($current !== null && (int) $current['section_id'] === $sectionId) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => true];
        }

        $policy  = $this->policyFor($section);
        $ceiling = $this->svc->hardCeiling($policy);
        $count   = (int) $db->table('users u')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'student')->where('u.deleted_at', null)->where('u.section_id', $sectionId)
            ->countAllResults();

        $over = $count >= $ceiling;

        if ($over && ($overrideReason === null || trim($overrideReason) === '')) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['ok' => false, 'over_capacity' => true, 'error' => "Section {$section['section']} already has {$count} students (limit {$ceiling}). Provide an override reason to add another."];
        }

        $db->table('users')->where('id', $userId)->update(['section_id' => $sectionId]);

        (new AuditLogModel())->record(
            $over ? 'sections.assign_override' : 'sections.assign',
            'section',
            $sectionId,
            ['user_id' => $userId, 'section_id' => $current['section_id'] ?? null],
            ['user_id' => $userId, 'section_id' => $sectionId, 'roster' => $count + 1, 'ceiling' => $ceiling],
            $over ? $overrideReason : null,
            $actorId,
            $actorRole
        );

        $db->transComplete();

        return $db->transStatus() === false
            ? ['ok' => false, 'error' => 'Could not assign the student. Please retry.']
            : ['ok' => true, 'over_capacity' => $over];
    }

    /** Effective policy for one section row. */
    public function policyFor(array $section): array
    {
        return (new CapacityPolicyModel())->resolve([
            'program'      => $section['program'],
            'year_level'   => $section['year_level'],
            'capacity'     => $section['capacity'] ?? null,
            'min_capacity' => $section['min_capacity'] ?? null,
        ]);
    }

    // ------------------------------------------------------------------

    private function analyzeSections(string $academicYear, string $program, string $yearLevel, array $sections): array
    {
        $db     = db_connect();
        $policy = (new CapacityPolicyModel())->resolve(['program' => $program, 'year_level' => $yearLevel]);

        $ids    = array_map(static fn ($s) => (int) $s['section_id'], $sections);
        $counts = $ids === [] ? [] : array_column(
            $db->table('users u')
                ->select('u.section_id, COUNT(*) AS cnt')
                ->join('auth_groups_users g', 'g.user_id = u.id')
                ->where('g.group', 'student')->where('u.deleted_at', null)->whereIn('u.section_id', $ids)
                ->groupBy('u.section_id')->get()->getResultArray(),
            'cnt',
            'section_id'
        );

        $rows = [];

        foreach ($sections as $s) {
            $sid = (int) $s['section_id'];
            $ctx = ['academic_year' => $academicYear, 'program' => $program, 'year_level' => $yearLevel, 'section' => $s['section']];

            $flags = [
                'status'          => $s['status'],
                'auto_balance'    => (bool) ($s['auto_balance'] ?? 1),
                'has_schedule'    => $db->table('plotted_schedules')->where($ctx)->countAllResults() > 0,
                'has_faculty'     => $db->table('faculty_assignments')->where($ctx)->countAllResults() > 0,
                'has_grades'      => $db->table('grades')->where('section_id', $sid)->countAllResults() > 0,
                'has_enrollments' => $db->table('enrollments')->where('section_id', $sid)->where('status', 'enrolled')->countAllResults() > 0,
            ];

            $verdict = $this->svc->canAutomaticallyRebalance($flags);

            $rows[] = array_merge($s, [
                'count'         => (int) ($counts[$sid] ?? 0),
                'can_rebalance' => $verdict['allowed'] && ! in_array($s['status'], self::UNSAFE_STATUSES, true),
                'block_reason'  => $verdict['allowed'] ? null : $verdict['reason'],
                'flags'         => $flags,
            ]);
        }

        // Only live sections take part; closed/merged ones hold no roster.
        $live = array_values(array_filter($rows, static fn ($r) => ! in_array($r['status'], ['closed', 'merged'], true)));

        $free = array_values(array_filter($live, static fn ($r) => $r['can_rebalance']));
        usort($free, static fn ($a, $b) => [$b['count'], $a['section']] <=> [$a['count'], $b['section']]);

        $lockedLive   = count($live) - count($free);
        $freeStudents = array_sum(array_column($free, 'count'));

        $plan = $free === []
            ? ['students' => 0, 'sections' => 0, 'sizes' => [], 'action' => 'none', 'reason' => '', 'warnings' => []]
            : $this->svc->plan($freeStudents, $policy, count($free), 0);

        $blocked = null;

        if ($live === []) {
            $blocked = 'No sections exist yet for this program and year level.';
        } elseif ($free === []) {
            $blocked = 'Automatic rebalancing was blocked: ' . implode(' ', array_unique(array_filter(array_column($live, 'block_reason')))) ;
        }

        $currentSizes = array_column($free, 'count');
        $moves        = 0;

        foreach ($plan['sizes'] as $i => $size) {
            $moves += max(0, ($currentSizes[$i] ?? 0) - $size);
        }

        return [
            'academic_year'  => $academicYear,
            'program'        => $program,
            'year_level'     => $yearLevel,
            'policy'         => $policy,
            'sections'       => $rows,
            'free_sections'  => $free,
            'locked_count'   => $lockedLive,
            'current_sizes'  => $currentSizes,
            'plan'           => $plan,
            'students_affected' => $moves,
            'has_changes'    => $free !== [] && ($plan['sizes'] !== $currentSizes),
            'blocked_reason' => $blocked,
            'explanation'    => $blocked ?? $plan['reason'],
        ];
    }

    /**
     * @param list<int> $sectionIds
     * @return array<int, list<int>> section_id => user ids (ascending)
     */
    private function studentsBySection(array $sectionIds): array
    {
        if ($sectionIds === []) {
            return [];
        }

        $rows = db_connect()->table('users u')
            ->select('u.id, u.section_id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'student')->where('u.deleted_at', null)->whereIn('u.section_id', $sectionIds)
            ->orderBy('u.id')->get()->getResultArray();

        $out = [];

        foreach ($rows as $r) {
            $out[(int) $r['section_id']][] = (int) $r['id'];
        }

        return $out;
    }

    /**
     * Inserts the next free section name. The unique key on
     * (academic_year, program, year_level, section) is the real guard
     * against two requests picking the same name; on a collision the next
     * free name is tried.
     *
     * @param list<string> $existing
     * @return array{id: int, name: string}|null
     */
    private function createAutoSection(SectionModel $model, string $academicYear, string $program, string $yearLevel, array $existing): ?array
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $name = $this->svc->generateNextSectionCode($yearLevel, $existing);

            try {
                $ok = $model->insert([
                    'academic_year' => $academicYear,
                    'program'       => $program,
                    'year_level'    => $yearLevel,
                    'section'       => $name,
                    'status'        => 'proposed',
                    'origin'        => 'auto',
                    'auto_balance'  => 1,
                ]);
            } catch (\Throwable $e) {
                $ok = false;
            }

            if ($ok !== false) {
                return ['id' => (int) $model->getInsertID(), 'name' => $name];
            }

            $existing[] = $name;
        }

        return null;
    }
}

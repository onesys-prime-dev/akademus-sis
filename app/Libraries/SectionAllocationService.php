<?php

namespace App\Libraries;

/**
 * Pure section-allocation rules: no database, no request, no clock — just
 * numbers in, a plan (and the reason for it) out. That keeps the algorithm
 * deterministic and unit-testable; SectionBalancer is what reads counts from
 * the database and applies a plan.
 *
 * Policy = {min, target, max, overflow}:
 *   min      smallest size a section should have once a split is justified
 *   target   the size we aim for; only used to flag sections that run over it
 *   max      normal ceiling for a section
 *   overflow extra seats a section may absorb before another one is opened
 *
 * The rule: with S = max + overflow, a further section is opened each time
 * the headcount reaches a multiple of S — so 16 students stay in one section
 * (splitting would leave two sections of 8, under the minimum of 10), 20
 * students become 10/10, and 31 become 16/15 rather than 15/15/1. With no
 * overflow the ceiling is simply `max` (opens at max + 1). Students are spread
 * as evenly as possible (sizes differ by at most 1).
 *
 * Hysteresis: growing is immediate, shrinking is not. A set of sections that
 * already exists is only reduced once the headcount has fallen a buffer below
 * the level that opened the last one, and never below the number of sections
 * that are locked. That is what stops 19 → 20 → 19 → 20 from creating and
 * deleting a section on every change.
 */
class SectionAllocationService
{
    public const DEFAULT_POLICY = [
        'min'        => 10,
        'target'     => 15,
        'max'        => 15,
        'overflow'   => 5,
        'hysteresis' => 2,
    ];

    /**
     * Fills defaults and rejects an incoherent policy.
     *
     * @param array<string, int|null> $policy
     * @return array{min: int, target: int, max: int, overflow: int, hysteresis: int}
     */
    public function normalizePolicy(array $policy): array
    {
        $p = array_merge(self::DEFAULT_POLICY, array_filter($policy, static fn ($v) => $v !== null));

        foreach ($p as $key => $value) {
            $p[$key] = (int) $value;
        }

        if ($p['min'] < 1 || $p['max'] < 1 || $p['overflow'] < 0 || $p['hysteresis'] < 0) {
            throw new \InvalidArgumentException('Capacity values must be positive (overflow and hysteresis may be 0).');
        }

        if ($p['min'] > $p['target'] || $p['target'] > $p['max']) {
            throw new \InvalidArgumentException('Capacity must satisfy minimum <= target <= maximum.');
        }

        return $p;
    }

    /** Extra seats allowed on top of `max` before a new section opens. */
    public function calculateOverflowAllowance(array $policy): int
    {
        return $this->normalizePolicy($policy)['overflow'];
    }

    /** The largest a section may be once overflow is used. */
    public function hardCeiling(array $policy): int
    {
        $p = $this->normalizePolicy($policy);

        return $p['max'] + $p['overflow'];
    }

    /**
     * Sections needed for $students, ignoring what already exists. Always at
     * least one — the base section is never removed.
     */
    public function calculateRequiredSections(int $students, array $policy): int
    {
        $p = $this->normalizePolicy($policy);

        if ($students <= 0) {
            return 1;
        }

        if ($p['overflow'] > 0) {
            return intdiv($students, $p['max'] + $p['overflow']) + 1;
        }

        return max(1, (int) ceil($students / $p['max']));
    }

    /** Smallest headcount that calls for $sections sections. */
    public function openThreshold(int $sections, array $policy): int
    {
        $p = $this->normalizePolicy($policy);

        if ($sections <= 1) {
            return 0;
        }

        return $p['overflow'] > 0
            ? ($sections - 1) * ($p['max'] + $p['overflow'])
            : ($sections - 1) * $p['max'] + 1;
    }

    /**
     * Even split of $students over $sections, largest sections first.
     *
     * @return list<int>
     */
    public function calculateBalancedDistribution(int $students, int $sections): array
    {
        $sections = max(1, $sections);
        $students = max(0, $students);
        $base     = intdiv($students, $sections);
        $extra    = $students % $sections;
        $sizes    = [];

        for ($i = 0; $i < $sections; $i++) {
            $sizes[] = $base + ($i < $extra ? 1 : 0);
        }

        return $sizes;
    }

    /**
     * How many sections there should be given how many already exist.
     * Applies the hysteresis buffer and the locked-section floor.
     *
     * @return array{sections: int, action: string, reason: string}
     *         action: none | open | close
     */
    public function recommendSectionCount(int $students, int $currentSections, int $lockedSections, array $policy): array
    {
        $p        = $this->normalizePolicy($policy);
        $current  = max(1, $currentSections);
        $required = $this->calculateRequiredSections($students, $p);

        if ($required > $current) {
            $sizes = $this->calculateBalancedDistribution($students, $required);

            return [
                'sections' => $required,
                'action'   => 'open',
                'reason'   => sprintf(
                    '%d enrolled students exceed what %d section(s) can hold (%d each incl. %d overflow); %d sections give %s.',
                    $students,
                    $current,
                    $p['max'] + $p['overflow'],
                    $p['overflow'],
                    $required,
                    implode('/', $sizes)
                ),
            ];
        }

        if ($required < $current) {
            $floor = max($required, $lockedSections);

            if ($floor >= $current) {
                return [
                    'sections' => $current,
                    'action'   => 'none',
                    'reason'   => 'Fewer sections would be enough, but the existing ones are locked or finalized, so none are closed automatically.',
                ];
            }

            // Shrink only once the headcount is a buffer below the level that
            // opened the last section.
            $closeBelow = $this->openThreshold($current, $p) - $p['hysteresis'];

            if ($students < $closeBelow) {
                $target = max($floor, $required);

                return [
                    'sections' => $target,
                    'action'   => $target < $current ? 'close' : 'none',
                    'reason'   => sprintf(
                        'Enrollment (%d) fell below %d, the buffered threshold for %d sections; %d section(s) are enough.',
                        $students,
                        $closeBelow,
                        $current,
                        $target
                    ),
                ];
            }

            return [
                'sections' => $current,
                'action'   => 'none',
                'reason'   => sprintf(
                    'Kept %d sections: enrollment (%d) is within the %d-student stabilization buffer of the opening threshold, so sections are not closed yet.',
                    $current,
                    $students,
                    $p['hysteresis']
                ),
            ];
        }

        return [
            'sections' => $current,
            'action'   => 'none',
            'reason'   => $this->explainKept($students, $current, $p),
        ];
    }

    public function shouldCreateNewSection(int $students, int $currentSections, array $policy): bool
    {
        return $this->recommendSectionCount($students, $currentSections, 0, $policy)['action'] === 'open';
    }

    public function shouldMergeSections(int $students, int $currentSections, int $lockedSections, array $policy): bool
    {
        return $this->recommendSectionCount($students, $currentSections, $lockedSections, $policy)['action'] === 'close';
    }

    /**
     * May the balancer move students around this section by itself? Only
     * while it is still a draft/proposal with nothing operational attached.
     * Anything else needs an authorized user to run the explicit rebalance.
     *
     * @param array{status?: string, auto_balance?: bool|int, has_schedule?: bool, has_faculty?: bool,
     *              has_grades?: bool, has_attendance?: bool, has_billing?: bool, has_enrollments?: bool} $section
     * @return array{allowed: bool, reason: string}
     */
    public function canAutomaticallyRebalance(array $section): array
    {
        $status = $section['status'] ?? 'open';

        if (array_key_exists('auto_balance', $section) && ! $section['auto_balance']) {
            return ['allowed' => false, 'reason' => 'Automatic balancing is switched off for this section.'];
        }

        if (in_array($status, ['finalized', 'locked', 'active', 'closed', 'merged'], true)) {
            return ['allowed' => false, 'reason' => "Automatic rebalancing was blocked because the section is {$status}."];
        }

        $blockers = [];

        foreach (['has_schedule' => 'a schedule', 'has_faculty' => 'a faculty assignment', 'has_grades' => 'grades',
            'has_attendance' => 'attendance records', 'has_billing' => 'billing records', 'has_enrollments' => 'enrollment records'] as $flag => $label) {
            if (! empty($section[$flag])) {
                $blockers[] = $label;
            }
        }

        if ($blockers !== []) {
            return ['allowed' => false, 'reason' => 'Automatic rebalancing was blocked because the section already has ' . implode(', ', $blockers) . '.'];
        }

        return ['allowed' => true, 'reason' => 'Section is unfinalized with no operational data.'];
    }

    /**
     * The next free section name for a year level: "1A", "1B", … "1Z",
     * "1AA", … skipping any name already taken (case-insensitive).
     *
     * @param list<string> $existingNames
     */
    public function generateNextSectionCode(string $yearLevel, array $existingNames): string
    {
        $prefix = preg_match('/\d+/', $yearLevel, $m) ? $m[0] : '';
        $taken  = array_map('strtoupper', $existingNames);

        for ($i = 1; $i < 100000; $i++) {
            $code = $prefix . $this->letters($i);

            if (! in_array(strtoupper($code), $taken, true)) {
                return $code;
            }
        }

        throw new \RuntimeException('No free section code.');
    }

    /**
     * Problems with a set of sizes against the policy (empty = valid).
     *
     * @param list<int> $sizes
     * @return list<string>
     */
    public function validateDistribution(array $sizes, array $policy, ?int $expectedTotal = null): array
    {
        $p        = $this->normalizePolicy($policy);
        $problems = [];

        if ($expectedTotal !== null && array_sum($sizes) !== $expectedTotal) {
            $problems[] = sprintf('Distribution holds %d students but %d are enrolled.', array_sum($sizes), $expectedTotal);
        }

        foreach ($sizes as $i => $size) {
            $label = 'Section ' . ($i + 1);

            if ($size > $p['max'] + $p['overflow']) {
                $problems[] = "{$label} has {$size} students, above the ceiling of " . ($p['max'] + $p['overflow']) . '.';
            }

            if ($size < $p['min'] && count($sizes) > 1) {
                $problems[] = "{$label} has {$size} students, below the minimum of {$p['min']}.";
            }
        }

        if ($sizes !== [] && max($sizes) - min($sizes) > 1) {
            $problems[] = 'Section sizes differ by more than 1.';
        }

        return $problems;
    }

    /**
     * Full plan for a headcount: how many sections, their sizes, warnings and
     * an explanation.
     *
     * @return array{students: int, sections: int, sizes: list<int>, action: string, reason: string, warnings: list<string>}
     */
    public function plan(int $students, array $policy, int $currentSections = 1, int $lockedSections = 0): array
    {
        $p    = $this->normalizePolicy($policy);
        $rec  = $this->recommendSectionCount($students, $currentSections, $lockedSections, $p);
        $size = $this->calculateBalancedDistribution($students, $rec['sections']);

        $warnings = [];

        if ($students > 0 && $rec['sections'] === 1 && $students < $p['min']) {
            $warnings[] = "Only {$students} student(s): below the minimum of {$p['min']}. Consider merging with another section.";
        }

        if (max($size) > $p['target']) {
            $warnings[] = 'Largest section (' . max($size) . ") is over the target of {$p['target']} (using overflow).";
        }

        return [
            'students' => $students,
            'sections' => $rec['sections'],
            'sizes'    => $size,
            'action'   => $rec['action'],
            'reason'   => $rec['reason'],
            'warnings' => $warnings,
        ];
    }

    /**
     * Before/after view for the explicit "Rebalance Sections" action.
     * $currentSizes are the sections' present sizes (locked ones flagged by
     * $lockedIndexes are left exactly as they are).
     *
     * @param list<int> $currentSizes
     * @param list<int> $lockedIndexes indexes into $currentSizes that must not change
     * @return array{current: list<int>, proposed: list<int>, moves: int, students: int, action: string, reason: string, warnings: list<string>}
     */
    public function previewRebalance(array $currentSizes, array $policy, array $lockedIndexes = []): array
    {
        $p        = $this->normalizePolicy($policy);
        $lockedSz = 0;
        $free     = [];

        foreach ($currentSizes as $i => $size) {
            if (in_array($i, $lockedIndexes, true)) {
                $lockedSz += $size;
            } else {
                $free[$i] = $size;
            }
        }

        $students = array_sum($free);
        $count    = count($free);

        if ($count === 0) {
            return [
                'current' => $currentSizes, 'proposed' => $currentSizes, 'moves' => 0, 'students' => array_sum($currentSizes),
                'action' => 'none', 'reason' => 'Every section is locked, so nothing can be rebalanced.', 'warnings' => [],
            ];
        }

        $plan     = $this->plan($students, $p, $count, 0);
        $proposed = $plan['sizes'];

        // Pair the largest current section with the largest proposed one so
        // the number of students who actually move is minimal.
        $cur = array_values($free);
        rsort($cur);
        $moves = 0;

        foreach ($proposed as $i => $size) {
            $moves += max(0, ($cur[$i] ?? 0) - $size);
        }

        $out = $currentSizes;
        $k   = 0;

        foreach (array_keys($free) as $idx) {
            $out[$idx] = $proposed[$k++] ?? 0;
        }

        foreach (array_slice($proposed, $count) as $extra) {
            $out[] = $extra;
        }

        return [
            'current'  => $currentSizes,
            'proposed' => $out,
            'moves'    => $moves,
            'students' => array_sum($currentSizes),
            'action'   => $plan['action'],
            'reason'   => $plan['reason'] . ($lockedSz > 0 ? " {$lockedSz} student(s) in locked sections were left in place." : ''),
            'warnings' => $plan['warnings'],
        ];
    }

    private function explainKept(int $students, int $sections, array $p): string
    {
        $sizes = $this->calculateBalancedDistribution($students, $sections);

        if ($sections === 1) {
            $needed = $p['max'] + $p['overflow'];

            if ($students > $p['max']) {
                $split = $this->calculateBalancedDistribution($students, 2);

                if (min($split) < $p['min']) {
                    return sprintf('Section B was not created: two sections would have %s students, below the configured minimum of %d. Kept together using overflow.', implode(' and ', $split), $p['min']);
                }

                return sprintf('Kept one section of %d using overflow (a second opens at %d students).', $students, $needed);
            }

            return sprintf('One section of %d fits within the maximum of %d.', $students, $p['max']);
        }

        return sprintf('%d sections of %s is the balanced fit for %d students.', $sections, implode('/', $sizes), $students);
    }

    private function letters(int $n): string
    {
        $s = '';

        while ($n > 0) {
            $n--;
            $s = chr(65 + $n % 26) . $s;
            $n = intdiv($n, 26);
        }

        return $s;
    }
}

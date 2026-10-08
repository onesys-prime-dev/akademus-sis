<?php

namespace App\Libraries;

/**
 * Rule-based scheduling assistant (the SIS handbook's "assisted, not fully
 * automatic" baseline): it enforces the same hard constraints the plotting
 * save enforces, then ranks what is left with soft preferences, and a
 * chairperson decides whether to apply anything.
 *
 * Pure: no database or request. ChairpersonController::suggest() feeds it the
 * current grid and applies nothing. Three jobs:
 *
 *   suggest()  place whatever is still unplotted — day, time, room AND the
 *              professor who should teach it
 *   improve()  review what is already plotted and propose fixes: a class in
 *              the wrong kind of room, a room too small, a professor who is
 *              not available then, a class that would sit much better
 *              elsewhere, a subject with nobody assigned
 *
 * Hard constraints (a slot that breaks one is never suggested):
 *   - inside the grid, on a 30-minute boundary, exactly the subject's meeting length
 *   - no overlap with any of this section's classes (existing or already suggested)
 *   - the room is free (this section's and every other section's classes)
 *   - laboratory subjects only go in laboratory rooms
 *   - the room seats the section's headcount
 *   - the professor is not teaching elsewhere and is inside their submitted
 *     availability window
 *   - the professor stays within the faculty unit cap
 *   - at most MAX_MEETINGS per subject, one meeting per subject per day
 *
 * Soft preferences (score, higher is better): compact days with no long
 * gaps, avoiding the lunch hour, an evenly spread week, normal teaching
 * hours, reusing the subject's room, the tightest room that still fits, and
 * — for professors — preferred over willing over qualified, lighter load
 * first, submitted availability over unknown.
 */
class ScheduleSuggester
{
    public const MAX_MEETINGS = 3;

    /** A move must beat the current slot by this much to be worth proposing. */
    private const IMPROVE_THRESHOLD = 15.0;

    /**
     * The department's scheduling rules (edited on the plotting page). Times
     * are 'HH:MM'. `spread`: how strongly to steer away from periods when
     * the rest of the school is already busy (off | balanced | strong).
     * `day_pattern`: 'spaced' prefers meeting days like Mon/Wed or Tue/Thu.
     */
    public const DEFAULT_RULES = [
        'lunch_enabled'   => true,
        'lunch_start'     => '12:00',
        'lunch_end'       => '13:00',
        'earliest_start'  => '07:00',
        'latest_end'      => '17:30',
        'allow_saturday'  => true,
        'max_hours_day'   => 8.0,
        'day_pattern'     => 'spaced',
        'spread'          => 'balanced',
    ];

    private const SPREAD_WEIGHT = ['off' => 0.0, 'balanced' => 1.0, 'strong' => 2.0];

    private const DAY_NAMES  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    private const PREF_WEIGHT = ['preferred' => 30, 'willing' => 15, 'qualified' => 8];

    /**
     * @param list<array> $subjects  code, title, units, duration (h), weekly_hours, is_lab, color
     * @param list<array> $blocks    this section: day, code, start, end, room, prof, faculty_id
     * @param list<array> $others    other sections: day, start, end, room, prof
     * @param list<array> $rooms     name, capacity, is_laboratory
     * @param array{headcount?: int, grid_start: int, grid_end: int, alternatives?: int,
     *              faculty?: list<array>, max_units?: float} $opts minutes from midnight
     * @return array{suggestions: list<array>, unplaceable: list<array>, complete: bool}
     */
    public function suggest(array $subjects, array $blocks, array $others, array $rooms, array $opts): array
    {
        $ctx  = $this->context($opts);
        $ctx['room_count'] = max(1, count($rooms));
        $mine = array_map(fn ($b) => $this->norm($b), $blocks);
        $all  = array_map(fn ($b) => $this->norm($b), $others);

        $suggestions = [];
        $unplaceable = [];
        $unitsAdded  = [];

        // Hardest first: labs (few rooms), then longest, then most meetings needed.
        $todo = [];

        foreach ($subjects as $s) {
            $need = $this->meetingsNeeded($s, $mine);

            if ($need > 0) {
                $todo[] = ['subject' => $s, 'need' => $need];
            }
        }

        usort($todo, static fn ($a, $b) => [(int) $b['subject']['is_lab'], $b['subject']['duration'], $b['need'], $a['subject']['code']]
            <=> [(int) $a['subject']['is_lab'], $a['subject']['duration'], $a['need'], $b['subject']['code']]);

        foreach ($todo as $item) {
            $s        = $item['subject'];
            $existing = array_values(array_filter($mine, static fn ($b) => $b['code'] === $s['code']));
            $assigned = $this->assignedProf($existing);
            $note     = null;

            if ($assigned !== null) {
                $fac  = ['name' => $assigned['prof'], 'id' => $assigned['faculty_id'], 'profile' => $this->profileByName($ctx['faculty'], $assigned['prof']), 'chosen' => false];
                $plan = $this->planSubject($s, $item['need'], $mine, $all, $rooms, $fac, $ctx);
            } else {
                [$fac, $plan, $note] = $this->planWithBestFaculty($s, $item['need'], $mine, $all, $rooms, $ctx, $unitsAdded);
            }

            if ($plan === null) {
                $unplaceable[] = ['code' => $s['code'], 'title' => $s['title'], 'reason' => $this->whyNot($s, $rooms, $ctx)];
                continue;
            }

            // Every professor who could teach this plan as laid out (the
            // chosen one first) so the chairperson can swap in someone else.
            $options = [];

            if ($fac !== null && $fac['chosen']) {
                $meetings = array_map(static fn ($step) => [
                    'day' => $step['candidates'][0]['day'], 'code' => $s['code'], 'start' => $step['candidates'][0]['start'],
                    'end' => $step['candidates'][0]['end'], 'room' => $step['candidates'][0]['room'],
                ], $plan);

                $options = array_map(
                    fn ($f) => $this->facultyPayload($f),
                    $this->rankFacultyForMeetings($s, $meetings, $mine, $all, $ctx, $unitsAdded)
                );

                $unitsAdded[$fac['id']] = ($unitsAdded[$fac['id']] ?? 0.0) + (float) ($s['units'] ?? 0);
            }

            $total = count($existing) + count($plan);

            foreach ($plan as $i => $step) {
                $best = $step['candidates'][0];

                $suggestions[] = [
                    'code'         => $s['code'],
                    'title'        => $s['title'],
                    'theme'        => $s['color'] ?? null,
                    'meeting'      => count($existing) + $i + 1,
                    'of'           => $total,
                    'prof'         => $fac['name'] ?? null,
                    'faculty_id'   => $fac['id'] ?? null,
                    'faculty'      => $fac !== null ? $this->presentFaculty($fac, $s, $ctx, $unitsAdded) : null,
                    'faculty_note' => $note,
                    'faculty_options' => $options,
                    'best'         => $this->present($best),
                    'alternatives' => array_map(fn ($c) => $this->present($c), array_slice($step['candidates'], 1, $ctx['alternatives'])),
                ];

                $mine[] = $this->norm([
                    'day' => $best['day'], 'code' => $s['code'], 'start' => $best['start'], 'end' => $best['end'],
                    'room' => $best['room'], 'prof' => $fac['name'] ?? null, 'faculty_id' => $fac['id'] ?? null,
                ]);
            }
        }

        return [
            'suggestions' => $suggestions,
            'unplaceable' => $unplaceable,
            'complete'    => $unplaceable === [],
        ];
    }

    /**
     * Review what is already plotted. Each item is one proposed fix:
     *   type 'move'           re-place one meeting (block identifies which)
     *   type 'assign_faculty' put a recommended professor on a subject that has none
     *
     * @return list<array>
     */
    public function improve(array $subjects, array $blocks, array $others, array $rooms, array $opts): array
    {
        $ctx   = $this->context($opts);
        $ctx['room_count'] = max(1, count($rooms));
        $mine  = array_map(fn ($b) => $this->norm($b), $blocks);
        $all   = array_map(fn ($b) => $this->norm($b), $others);
        $ctx['_others'] = $all;
        $byCode = array_column($subjects, null, 'code');
        $items = [];

        foreach ($mine as $idx => $b) {
            $s = $byCode[$b['code']] ?? null;

            if ($s === null) {
                continue;
            }

            $rest    = $mine;
            unset($rest[$idx]);
            $rest    = array_values($rest);
            $fac     = $b['prof'] !== null && $b['prof'] !== ''
                ? ['name' => $b['prof'], 'id' => $b['faculty_id'], 'profile' => $this->profileByName($ctx['faculty'], $b['prof']), 'chosen' => false]
                : null;

            // What is wrong right now?
            $problems = [];
            $room     = $this->roomByName($rooms, $b['room']);

            if ($s['is_lab'] && ($room === null || empty($room['is_laboratory']))) {
                $problems[] = "{$s['code']} is a laboratory subject but {$b['room']} is not a laboratory room.";
            }

            if ($ctx['headcount_known'] && $room !== null && (int) $room['capacity'] < $ctx['headcount']) {
                $problems[] = "{$b['room']} seats {$room['capacity']} but the section has {$ctx['headcount']} students.";
            }

            if ($fac !== null && $fac['profile'] !== null && $this->availability($fac['profile'], $b['day'], $b['start'], $b['end']) === 'no') {
                $problems[] = "{$fac['name']} is not available " . self::DAY_NAMES[$b['day']] . ' ' . $this->to12h($b['start']) . '–' . $this->to12h($b['end']) . '.';
            }

            $cands = $this->candidates($s, $rest, $all, $rooms, $fac, $ctx);

            $currentScore = null;

            foreach ($cands as $c) {
                if ($c['day'] === $b['day'] && $c['start'] === $b['start']) {
                    $currentScore = $c['score'];
                    break;
                }
            }

            $best = $cands[0] ?? null;

            if ($best === null) {
                if ($problems !== []) {
                    $items[] = $this->improvementCard('move', 'high', $s, $b, implode(' ', $problems) . ' No conflict-free alternative was found.', [], null);
                }
                continue;
            }

            $sameSlot = $best['day'] === $b['day'] && $best['start'] === $b['start'] && $best['room'] === $b['room'];

            if ($sameSlot) {
                continue;
            }

            // Hard problem, or a clearly better slot.
            $hard = $problems !== [] || $currentScore === null;
            $gain = $currentScore === null ? null : $best['score'] - $currentScore;

            if (! $hard && ($gain === null || $gain < self::IMPROVE_THRESHOLD)) {
                continue;
            }

            $reasons = $problems !== [] ? $problems : [];

            if ($problems === [] && $currentScore === null) {
                $reasons[] = 'The current slot no longer satisfies the scheduling rules.';
            }

            $impact = $hard ? 'high' : ($gain >= 30 ? 'medium' : 'low');

            if ($problems === []) {
                $reasons = $this->explainGain($b, $best, $ctx);
            }

            $items[] = $this->improvementCard('move', $impact, $s, $b, implode(' ', $reasons), array_merge(['Result: ' . implode(', ', $best['reasons'])], []), $this->present($best), array_map(fn ($c) => $this->present($c), array_slice($cands, 1, 2)));
        }

        // Subjects with no professor at all.
        foreach ($subjects as $s) {
            $sub = array_values(array_filter($mine, static fn ($b) => $b['code'] === $s['code']));

            if ($sub === [] || $this->assignedProf($sub) !== null) {
                continue;
            }

            $ranked = $this->rankFacultyForMeetings($s, $sub, $mine, $all, $ctx, []);

            if ($ranked === []) {
                $items[] = [
                    'type' => 'assign_faculty', 'impact' => 'medium', 'code' => $s['code'], 'title' => $s['title'],
                    'summary' => "{$s['code']} has no professor and no one in the department is free for all its meetings.",
                    'reasons' => [], 'faculty' => null, 'alternatives' => [],
                ];
                continue;
            }

            $top = $ranked[0];

            $items[] = [
                'type'         => 'assign_faculty',
                'impact'       => 'medium',
                'code'         => $s['code'],
                'title'        => $s['title'],
                'summary'      => "Assign {$top['name']} to {$s['code']}",
                'reasons'      => $top['reasons'],
                'faculty'      => $this->facultyPayload($top),
                // Everyone else who could take it — including professors who
                // have not set their availability or subjects yet.
                'alternatives' => array_map(fn ($f) => $this->facultyPayload($f), array_slice($ranked, 1, 8)),
            ];
        }

        $order = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($items, static fn ($a, $b) => [$order[$a['impact']], $a['code']] <=> [$order[$b['impact']], $b['code']]);

        return $items;
    }

    /** Meetings still to plot for a subject: remaining weekly hours / meeting length. */
    public function meetingsNeeded(array $subject, array $mine): int
    {
        $weekly   = (float) ($subject['weekly_hours'] ?? 0);
        $duration = (float) ($subject['duration'] ?? 0);

        if ($weekly <= 0 || $duration <= 0) {
            return 0;
        }

        $meetings = 0;
        $hours    = 0.0;

        foreach ($mine as $b) {
            if ($b['code'] === $subject['code']) {
                $meetings++;
                $hours += ($b['end'] - $b['start']) / 60;
            }
        }

        $remaining = $weekly - $hours;

        if ($remaining <= 0.001) {
            return 0;
        }

        return max(0, min((int) ceil($remaining / $duration - 0.001), self::MAX_MEETINGS - $meetings));
    }

    // ------------------------------------------------------------------
    // Planning
    // ------------------------------------------------------------------

    /**
     * Fill defaults, clamp and validate the department rules. Anything
     * malformed falls back to its default, so bad stored data can never
     * break the assistant.
     *
     * @return array{lunch_enabled: bool, lunch_start: string, lunch_end: string, earliest_start: string,
     *               latest_end: string, allow_saturday: bool, max_hours_day: float, day_pattern: string, spread: string}
     */
    public function normalizeRules(array $rules): array
    {
        $d    = self::DEFAULT_RULES;
        $time = static fn ($v, $fallback) => is_string($v) && preg_match('/^([01]\d|2[0-3]):(00|30)$/', $v) ? $v : $fallback;

        $out = [
            'lunch_enabled'  => array_key_exists('lunch_enabled', $rules) ? (bool) $rules['lunch_enabled'] : $d['lunch_enabled'],
            'lunch_start'    => $time($rules['lunch_start'] ?? null, $d['lunch_start']),
            'lunch_end'      => $time($rules['lunch_end'] ?? null, $d['lunch_end']),
            'earliest_start' => $time($rules['earliest_start'] ?? null, $d['earliest_start']),
            'latest_end'     => $time($rules['latest_end'] ?? null, $d['latest_end']),
            'allow_saturday' => array_key_exists('allow_saturday', $rules) ? (bool) $rules['allow_saturday'] : $d['allow_saturday'],
            'max_hours_day'  => isset($rules['max_hours_day']) && is_numeric($rules['max_hours_day'])
                ? max(1.0, min(12.0, (float) $rules['max_hours_day'])) : $d['max_hours_day'],
            'day_pattern'    => in_array($rules['day_pattern'] ?? null, ['any', 'spaced'], true) ? $rules['day_pattern'] : $d['day_pattern'],
            'spread'         => in_array($rules['spread'] ?? null, array_keys(self::SPREAD_WEIGHT), true) ? $rules['spread'] : $d['spread'],
        ];

        if ($this->minutes($out['lunch_end']) <= $this->minutes($out['lunch_start'])) {
            $out['lunch_start'] = $d['lunch_start'];
            $out['lunch_end']   = $d['lunch_end'];
        }

        if ($this->minutes($out['latest_end']) - $this->minutes($out['earliest_start']) < 60) {
            $out['earliest_start'] = $d['earliest_start'];
            $out['latest_end']     = $d['latest_end'];
        }

        return $out;
    }

    private function context(array $opts): array
    {
        $rules = $this->normalizeRules($opts['rules'] ?? []);

        return [
            'rules'           => $rules,
            'lunch_start'     => $this->minutes($rules['lunch_start']),
            'lunch_end'       => $this->minutes($rules['lunch_end']),
            'spread_weight'   => self::SPREAD_WEIGHT[$rules['spread']],
            'headcount'       => (int) ($opts['headcount'] ?? 0),
            'headcount_known' => ! empty($opts['headcount_known']),
            'room_count'      => max(1, (int) ($opts['room_count'] ?? 1)),
            'grid_start'      => max($opts['grid_start'], $this->minutes($rules['earliest_start'])),
            'grid_end'        => min($opts['grid_end'], $this->minutes($rules['latest_end'])),
            'alternatives'    => $opts['alternatives'] ?? 3,
            'faculty'         => $this->normalizeFaculty($opts['faculty'] ?? []),
            'max_units'       => (float) ($opts['max_units'] ?? 24),
        ];
    }

    /**
     * Plans every needed meeting of one subject for a fixed professor (or none).
     *
     * @return list<array{candidates: list<array>}>|null null when a meeting cannot be placed
     */
    private function planSubject(array $s, int $need, array $mine, array $all, array $rooms, ?array $fac, array $ctx): ?array
    {
        $plan = [];

        for ($m = 0; $m < $need; $m++) {
            $cands = $this->candidates($s, $mine, $all, $rooms, $fac, $ctx);

            if ($cands === []) {
                return null;
            }

            $plan[] = ['candidates' => $cands];
            $best   = $cands[0];

            $mine[] = $this->norm([
                'day' => $best['day'], 'code' => $s['code'], 'start' => $best['start'], 'end' => $best['end'],
                'room' => $best['room'], 'prof' => $fac['name'] ?? null, 'faculty_id' => $fac['id'] ?? null,
            ]);
        }

        return $plan;
    }

    /**
     * No professor yet: try department faculty best-first and keep the first
     * one for whom every meeting fits. Falls back to an unassigned plan (with
     * an explanation) when nobody fits.
     *
     * @return array{0: ?array, 1: ?array, 2: ?string} [faculty, plan, note]
     */
    private function planWithBestFaculty(array $s, int $need, array $mine, array $all, array $rooms, array $ctx, array $unitsAdded): array
    {
        if ($ctx['faculty'] === []) {
            return [null, $this->planSubject($s, $need, $mine, $all, $rooms, null, $ctx), null];
        }

        $ranked = $this->rankFaculty($s, $ctx, $unitsAdded);

        foreach ($ranked as $f) {
            $fac  = ['name' => $f['name'], 'id' => $f['id'], 'profile' => $f, 'chosen' => true];
            $plan = $this->planSubject($s, $need, $mine, $all, $rooms, $fac, $ctx);

            if ($plan !== null) {
                return [$fac, $plan, null];
            }
        }

        $note = $ranked === []
            ? 'No professor in the department has enough unit capacity left for this subject.'
            : 'No professor is both free and available for all of this subject\'s meetings — placed without one.';

        return [null, $this->planSubject($s, $need, $mine, $all, $rooms, null, $ctx), $note];
    }

    /**
     * Department faculty ordered by fit for a subject: preference, then
     * lighter load, then having submitted availability. Excludes anyone who
     * would exceed the unit cap.
     *
     * @return list<array>
     */
    private function rankFaculty(array $s, array $ctx, array $unitsAdded): array
    {
        $out = [];

        foreach ($ctx['faculty'] as $f) {
            $units = $f['units'] + ($unitsAdded[$f['id']] ?? 0.0);

            if ($units + (float) ($s['units'] ?? 0) > $ctx['max_units']) {
                continue;
            }

            $level   = $f['preferences'][$s['code']] ?? null;
            $score   = ($level !== null ? self::PREF_WEIGHT[$level] ?? 0 : 0) - $units * 0.8 + ($f['has_availability'] ? 4 : 0);
            $reasons = [];

            $reasons[] = $level !== null
                ? ucfirst($level) . ' subject for them'
                : ($f['has_preferences'] ? 'Not on their preferred subject list' : 'Has not set their subjects yet');
            $reasons[] = sprintf('%s of %s units currently', $this->num($units), $this->num($ctx['max_units']));
            $reasons[] = $f['has_availability'] ? 'Availability submitted' : 'Has not set their availability yet';

            $out[] = $f + [
                'score' => $score, 'level' => $level, 'units_now' => $units, 'reasons' => $reasons,
                'setup_pending' => ! $f['has_availability'] || ! $f['has_preferences'],
            ];
        }

        usort($out, static fn ($a, $b) => [$b['score'], $a['name']] <=> [$a['score'], $b['name']]);

        return $out;
    }

    /**
     * Rank faculty for a subject whose meetings are already fixed on the grid.
     *
     * @return list<array>
     */
    private function rankFacultyForMeetings(array $s, array $meetings, array $mine, array $all, array $ctx, array $unitsAdded): array
    {
        $everything = array_merge($mine, $all);
        $ok         = [];

        foreach ($this->rankFaculty($s, $ctx, $unitsAdded) as $f) {
            $fits = true;
            $unknown = false;

            foreach ($meetings as $m) {
                if ($this->profBusyExcluding($everything, $f['name'], $m)) {
                    $fits = false;
                    break;
                }

                $av = $this->availability($f, $m['day'], $m['start'], $m['end']);

                if ($av === 'no') {
                    $fits = false;
                    break;
                }

                $unknown = $unknown || $av === 'unknown';
            }

            if ($fits) {
                $f['reasons'][] = 'Free for all ' . count($meetings) . ' meeting' . (count($meetings) === 1 ? '' : 's') . ($unknown ? ' (availability unknown)' : ' and within their availability');
                $ok[] = $f;
            }
        }

        return $ok;
    }

    /**
     * Every legal (day, start, room) for one meeting of $s, best first.
     *
     * @return list<array{day: int, start: int, end: int, room: string, score: float, reasons: list<string>}>
     */
    private function candidates(array $s, array $mine, array $others, array $rooms, ?array $fac, array $ctx): array
    {
        $len = (int) round($s['duration'] * 60);

        $sameSubjectDays = [];
        $sameSubjectRoom = null;

        foreach ($mine as $b) {
            if ($b['code'] === $s['code']) {
                $sameSubjectDays[$b['day']] = true;
                $sameSubjectRoom            = $sameSubjectRoom ?? $b['room'];
            }
        }

        // A lab subject needs a lab room; a non-lab (lecture) subject needs
        // a NON-lab room — it must not be left unrestricted, or lecture
        // subjects end up competing for (and frequently winning) the same
        // scarce lab rooms a real lab subject needs, instead of using the
        // much larger pool of plain lecture rooms meant for them.
        $eligible = array_values(array_filter($rooms, static fn ($r) => $s['is_lab'] ? ! empty($r['is_laboratory']) : empty($r['is_laboratory'])));

        $roomPool = $ctx['headcount'] > 0
            ? array_values(array_filter($eligible, static fn ($r) => (int) $r['capacity'] >= $ctx['headcount']))
            : $eligible;

        if ($roomPool === []) {
            return [];
        }

        $everything = array_merge($mine, $others);
        $roomBlocks = [];

        foreach ($everything as $b) {
            $roomBlocks[$b['room']][] = $b;
        }

        // How many of THIS SECTION's own already-plotted classes (across
        // every subject, not just this one) already sit in each room.
        // Without this, same-capacity rooms score identically (nothing
        // else differentiates them when headcount is low/unknown), so the
        // sort always lands on the same one — every subject piles into a
        // single room instead of spreading across the available pool.
        $myRoomUsage = [];
        foreach ($mine as $b) {
            $myRoomUsage[$b['room']] = ($myRoomUsage[$b['room']] ?? 0) + 1;
        }

        $dayLoad = array_fill(0, 6, 0);

        foreach ($mine as $b) {
            $dayLoad[$b['day']] += ($b['end'] - $b['start']);
        }

        $out = [];

        $rules   = $ctx['rules'];
        $maxDay  = (int) round($rules['max_hours_day'] * 60);
        $lastDay = $rules['allow_saturday'] ? 5 : 4;

        for ($day = 0; $day <= $lastDay; $day++) {
            if (isset($sameSubjectDays[$day])) {
                continue;
            }

            // Hard: the section's total teaching hours for the day.
            if ($dayLoad[$day] + $len > $maxDay) {
                continue;
            }

            // Soft: an "every other day" rhythm (Mon+Wed, Tue+Thu, Wed+Fri,
            // Thu+Sat, ...) reads as a normal weekly pattern AND spreads
            // subjects across the full week instead of clustering everyone
            // onto just Mon-Thu — any genuine 2-day gap counts, not only
            // the two most common examples.
            $dayBonus   = 0.0;
            $dayReasons = [];

            if ($rules['day_pattern'] === 'spaced' && $sameSubjectDays !== []) {
                $distances = array_map(static fn ($d) => abs($d - $day), array_keys($sameSubjectDays));

                if (in_array(2, $distances, true)) {
                    $dayBonus     = 8.0;
                    $dayReasons[] = 'spaced from its other meeting (' . self::DAY_NAMES[$day] . ' pairs well)';
                } elseif (in_array(1, $distances, true)) {
                    $dayBonus = -6.0;
                }
            }

            for ($start = $ctx['grid_start']; $start + $len <= $ctx['grid_end']; $start += 30) {
                $end = $start + $len;

                if ($this->overlapsAny($mine, $day, $start, $end)) {
                    continue;
                }

                $reasonsBase = $dayReasons;
                $score       = 100.0 + $dayBonus;

                // Steer away from periods when the rest of the school is
                // already busy, so every section doesn't pile into 8-10:30.
                if ($ctx['spread_weight'] > 0) {
                    $concurrent = $this->concurrent($others, $day, $start, $end);
                    $ratio      = $concurrent / $ctx['room_count'];

                    $score -= min(30.0, $ratio * 24 * $ctx['spread_weight']);

                    if ($ratio >= 0.6) {
                        $reasonsBase[] = "busy period ({$concurrent} other classes then)";
                    } elseif ($ratio <= 0.2) {
                        $reasonsBase[] = $concurrent === 0 ? 'quiet period, no other classes then' : "quiet period ({$concurrent} other classes)";
                    }
                }

                if ($fac !== null) {
                    if ($this->profBusy($everything, $fac['name'], $day, $start, $end)) {
                        continue;
                    }

                    if ($fac['profile'] !== null) {
                        $av = $this->availability($fac['profile'], $day, $start, $end);

                        if ($av === 'no') {
                            continue;
                        }

                        if ($av === 'unknown') {
                            $score -= 4;
                        } else {
                            $reasonsBase[] = "{$fac['name']} is available then";
                        }
                    }
                }

                if ($rules['lunch_enabled']) {
                    $lunchOverlap = max(0, min($end, $ctx['lunch_end']) - max($start, $ctx['lunch_start']));

                    // Hard: the lunch break stays free.
                    if ($lunchOverlap > 0) {
                        continue;
                    }
                }

                if ($start < 480) {
                    $score -= 6;
                } elseif ($end > 1020) {
                    $score -= 8;
                } else {
                    $reasonsBase[] = 'regular hours';
                }

                $gap = $this->nearestGap($mine, $day, $start, $end);

                if ($gap === null) {
                    $score -= 4;
                } elseif ($gap === 0) {
                    $score += 14;
                    $reasonsBase[] = 'back-to-back with another class, no gap';
                } elseif ($gap <= 60) {
                    $score += 6;
                    $reasonsBase[] = 'only a short break from the next class';
                } else {
                    $score -= min(24, ($gap - 60) / 15 * 3);
                    $reasonsBase[] = 'leaves a ' . round($gap / 60, 1) . 'h gap';
                }

                // Prefer a day the student is ALREADY on campus for (this
                // section's other classes already plotted there), packing
                // more into fewer days instead of spreading classes across
                // more distinct days than necessary — fewer, fuller days
                // beats more, thinner ones, so campus time isn't wasted.
                // Capped so a genuinely better day for other reasons (room
                // fit, faculty availability) can still win.
                $score += min(10, $dayLoad[$day] / 60 * 3);

                foreach ($roomPool as $room) {
                    if ($this->overlapsAny($roomBlocks[$room['name']] ?? [], $day, $start, $end)) {
                        continue;
                    }

                    $roomScore = $score;
                    $reasons   = $reasonsBase;

                    if ($sameSubjectRoom !== null && $room['name'] === $sameSubjectRoom) {
                        $roomScore += 8;
                        $reasons[]  = 'same room as its other meeting';
                    } elseif (($myRoomUsage[$room['name']] ?? 0) > 0) {
                        // Mild, escalating nudge toward spreading DIFFERENT
                        // subjects across different rooms — never strong
                        // enough to override a genuinely better slot/room,
                        // just enough to break ties that would otherwise
                        // always resolve the same way.
                        $roomScore -= min(6, $myRoomUsage[$room['name']] * 2);
                        $reasons[]  = "already used by {$myRoomUsage[$room['name']]} other class(es) for this section";
                    }

                    if ($ctx['headcount'] > 0) {
                        $slack      = (int) $room['capacity'] - $ctx['headcount'];
                        $roomScore -= min(10, $slack / 10);
                        $reasons[]  = "{$room['name']} seats {$room['capacity']} for {$ctx['headcount']} students" . ($ctx['headcount_known'] ? '' : ' (estimated)');
                    } else {
                        $reasons[] = "{$room['name']} is free";
                    }

                    if ($s['is_lab']) {
                        $reasons[] = 'laboratory room';
                    }

                    $out[] = [
                        'day' => $day, 'start' => $start, 'end' => $end, 'room' => $room['name'],
                        'score' => round($roomScore, 2), 'reasons' => $reasons,
                    ];
                }
            }
        }

        usort($out, static fn ($a, $b) => [$b['score'], $a['day'], $a['start'], $a['room']] <=> [$a['score'], $b['day'], $b['start'], $b['room']]);

        // One entry per (day, start) — its best room — so alternatives differ meaningfully.
        $seen   = [];
        $unique = [];

        foreach ($out as $c) {
            $key = $c['day'] . '@' . $c['start'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[]   = $c;
        }

        return $unique;
    }

    // ------------------------------------------------------------------
    // Explanations & presentation
    // ------------------------------------------------------------------

    private function whyNot(array $s, array $rooms, array $ctx): string
    {
        $labRooms = array_filter($rooms, static fn ($r) => ! empty($r['is_laboratory']));

        if ($s['is_lab'] && $labRooms === []) {
            return 'No laboratory room exists to hold a laboratory subject.';
        }

        $pool = $s['is_lab'] ? $labRooms : array_filter($rooms, static fn ($r) => empty($r['is_laboratory']));

        if ($ctx['headcount'] > 0 && array_filter($pool, static fn ($r) => (int) $r['capacity'] >= $ctx['headcount']) === []) {
            return "No suitable room seats this section's {$ctx['headcount']} students.";
        }

        return 'No conflict-free slot remains in the week (rooms, section time or professor are all taken).';
    }

    private function explainGain(array $current, array $best, array $ctx): array
    {
        $why = [];

        $lunch = $ctx['rules']['lunch_enabled']
            ? max(0, min($current['end'], $ctx['lunch_end']) - max($current['start'], $ctx['lunch_start']))
            : 0;

        if ($lunch > 0) {
            $why[] = 'It currently runs through the lunch hour.';
        }

        if ($ctx['spread_weight'] > 0 && $this->concurrent($ctx['_others'] ?? [], $current['day'], $current['start'], $current['end']) / $ctx['room_count'] >= 0.6) {
            $why[] = 'It sits in one of the busiest periods of the week.';
        }

        if ($current['start'] < 480 || $current['end'] > 1020) {
            $why[] = 'It sits outside regular teaching hours (8 AM–5 PM).';
        }

        $why[] = 'A better slot keeps the section\'s day more compact.';

        return $why;
    }

    private function improvementCard(string $type, string $impact, array $s, array $b, string $summary, array $reasons, ?array $best, array $alts = []): array
    {
        return [
            'type'         => $type,
            'impact'       => $impact,
            'code'         => $s['code'],
            'title'        => $s['title'],
            'summary'      => $summary,
            'reasons'      => $reasons,
            'block'        => [
                'day' => $b['day'], 'start' => $this->hhmm($b['start']), 'end' => $this->hhmm($b['end']), 'room' => $b['room'],
                'label' => self::DAY_NAMES[$b['day']] . ' ' . $this->to12h($b['start']) . ' - ' . $this->to12h($b['end']) . ' · ' . $b['room'],
            ],
            'best'         => $best,
            'alternatives' => $alts,
        ];
    }

    private function presentFaculty(array $fac, array $s, array $ctx, array $unitsAdded): array
    {
        $p = $fac['profile'] ?? null;

        if ($p === null) {
            return ['id' => $fac['id'], 'name' => $fac['name'], 'level' => null, 'reasons' => ['Already assigned to this subject']];
        }

        $level = $p['preferences'][$s['code']] ?? null;

        return [
            'id'      => $p['id'],
            'name'    => $p['name'],
            'level'   => $level,
            'setup_pending' => ! $p['has_availability'] || ! $p['has_preferences'],
            'reasons' => [
                $level !== null
                    ? ucfirst($level) . ' subject for them'
                    : ($p['has_preferences'] ? 'Not on their preferred subject list' : 'Has not set their subjects yet'),
                sprintf('%s of %s units after this', $this->num($p['units'] + ($unitsAdded[$p['id']] ?? 0.0)), $this->num($ctx['max_units'])),
                $p['has_availability'] ? 'Within their submitted availability' : 'Has not set their availability yet',
            ],
        ];
    }

    private function facultyPayload(array $f): array
    {
        return [
            'id' => $f['id'], 'name' => $f['name'], 'level' => $f['level'], 'reasons' => $f['reasons'],
            'setup_pending' => $f['setup_pending'] ?? false,
        ];
    }

    private function present(array $c): array
    {
        return [
            'day'        => $c['day'],
            'day_name'   => self::DAY_NAMES[$c['day']],
            'start'      => $this->hhmm($c['start']),
            'end'        => $this->hhmm($c['end']),
            'time_label' => $this->to12h($c['start']) . ' - ' . $this->to12h($c['end']),
            'room'       => $c['room'],
            'score'      => $c['score'],
            'reasons'    => $c['reasons'],
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @return list<array> */
    private function normalizeFaculty(array $faculty): array
    {
        return array_map(static function ($f) {
            $avail = [];

            foreach ($f['availability'] ?? [] as $a) {
                $toMin = static function ($t) {
                    if ($t === null || $t === '') {
                        return null;
                    }

                    [$h, $m] = array_map('intval', explode(':', $t));

                    return $h * 60 + $m;
                };

                $avail[(int) $a['day_of_week']] = [
                    'available' => (int) $a['is_available'] === 1,
                    'start'     => $toMin($a['start_time'] ?? null),
                    'end'       => $toMin($a['end_time'] ?? null),
                ];
            }

            return [
                'id'               => (int) $f['id'],
                'name'             => $f['name'],
                'employment'       => $f['employment_status'] ?? null,
                'availability'     => $avail,
                'has_availability' => $avail !== [],
                'has_preferences'  => ($f['preferences'] ?? []) !== [],
                'preferences'      => $f['preferences'] ?? [],
                'units'            => (float) ($f['units'] ?? 0),
            ];
        }, $faculty);
    }

    /** 'yes' inside their window, 'no' outside it, 'unknown' when nothing was submitted. */
    private function availability(array $f, int $day, int $start, int $end): string
    {
        if (! $f['has_availability']) {
            return 'unknown';
        }

        $a = $f['availability'][$day] ?? null;

        if ($a === null || ! $a['available']) {
            return 'no';
        }

        if (($a['start'] !== null && $a['start'] > $start) || ($a['end'] !== null && $a['end'] < $end)) {
            return 'no';
        }

        return 'yes';
    }

    private function profileByName(array $faculty, ?string $name): ?array
    {
        foreach ($faculty as $f) {
            if ($f['name'] === $name) {
                return $f;
            }
        }

        return null;
    }

    private function roomByName(array $rooms, string $name): ?array
    {
        foreach ($rooms as $r) {
            if ($r['name'] === $name) {
                return $r;
            }
        }

        return null;
    }

    /** @return array{prof: string, faculty_id: ?int}|null */
    private function assignedProf(array $blocks): ?array
    {
        foreach ($blocks as $b) {
            if (! empty($b['prof'])) {
                return ['prof' => $b['prof'], 'faculty_id' => $b['faculty_id'] ?? null];
            }
        }

        return null;
    }

    private function num(float $n): string
    {
        return $n == (int) $n ? (string) (int) $n : (string) round($n, 1);
    }

    private function norm(array $b): array
    {
        return [
            'day'        => (int) $b['day'],
            'code'       => $b['code'] ?? '',
            'start'      => is_int($b['start']) ? $b['start'] : $this->minutes($b['start']),
            'end'        => is_int($b['end']) ? $b['end'] : $this->minutes($b['end']),
            'room'       => $b['room'] ?? '',
            'prof'       => $b['prof'] ?? null,
            'faculty_id' => $b['faculty_id'] ?? null,
        ];
    }

    /** How many classes (in other sections) overlap this slot. */
    private function concurrent(array $others, int $day, int $start, int $end): int
    {
        $n = 0;

        foreach ($others as $b) {
            if ($b['day'] === $day && $start < $b['end'] && $b['start'] < $end) {
                $n++;
            }
        }

        return $n;
    }

    private function overlapsAny(iterable $blocks, int $day, int $start, int $end): bool
    {
        foreach ($blocks as $b) {
            if ($b['day'] === $day && $start < $b['end'] && $b['start'] < $end) {
                return true;
            }
        }

        return false;
    }

    private function profBusy(array $blocks, string $prof, int $day, int $start, int $end): bool
    {
        foreach ($blocks as $b) {
            if (($b['prof'] ?? null) === $prof && $b['day'] === $day && $start < $b['end'] && $b['start'] < $end) {
                return true;
            }
        }

        return false;
    }

    /** Is the professor teaching something else during this fixed meeting? (the meeting itself is ignored) */
    private function profBusyExcluding(array $blocks, string $prof, array $meeting): bool
    {
        foreach ($blocks as $b) {
            if (($b['prof'] ?? null) !== $prof || $b['day'] !== $meeting['day']) {
                continue;
            }

            if ($b['code'] === $meeting['code'] && $b['start'] === $meeting['start'] && $b['end'] === $meeting['end'] && $b['room'] === $meeting['room']) {
                continue;
            }

            if ($meeting['start'] < $b['end'] && $b['start'] < $meeting['end']) {
                return true;
            }
        }

        return false;
    }

    /** Minutes between this slot and the closest class of the section that day; null if none. */
    private function nearestGap(array $mine, int $day, int $start, int $end): ?int
    {
        $best = null;

        foreach ($mine as $b) {
            if ($b['day'] !== $day) {
                continue;
            }

            $gap  = $b['end'] <= $start ? $start - $b['end'] : $b['start'] - $end;
            $best = $best === null ? $gap : min($best, $gap);
        }

        return $best;
    }

    private function minutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    private function hhmm(int $mins): string
    {
        return sprintf('%02d:%02d', intdiv($mins, 60), $mins % 60);
    }

    private function to12h(int $mins): string
    {
        $h    = intdiv($mins, 60);
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $h    = $h % 12 ?: 12;

        return sprintf('%d:%02d %s', $h, $mins % 60, $ampm);
    }
}

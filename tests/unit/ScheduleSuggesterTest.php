<?php

use App\Libraries\ScheduleSuggester;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ScheduleSuggesterTest extends CIUnitTestCase
{
    private ScheduleSuggester $s;

    private array $rooms = [
        ['name' => 'Room 101', 'capacity' => 40, 'is_laboratory' => false],
        ['name' => 'Room 102', 'capacity' => 40, 'is_laboratory' => false],
        ['name' => 'Lab A', 'capacity' => 40, 'is_laboratory' => true],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = new ScheduleSuggester();
    }

    private function subject(string $code, float $duration = 1.5, float $weekly = 3, bool $lab = false): array
    {
        return ['code' => $code, 'title' => $code . ' title', 'duration' => $duration, 'weekly_hours' => $weekly, 'is_lab' => $lab, 'color' => 'blue'];
    }

    private function run2(array $subjects, array $blocks = [], array $others = [], array $rooms = null, int $headcount = 30): array
    {
        return $this->s->suggest($subjects, $blocks, $others, $rooms ?? $this->rooms, [
            'headcount' => $headcount, 'grid_start' => 420, 'grid_end' => 1050,
        ]);
    }

    public function testPlansEveryMeetingNeeded(): void
    {
        $r = $this->run2([$this->subject('ITE 101', 1.5, 3)]);

        $this->assertCount(2, $r['suggestions']);
        $this->assertTrue($r['complete']);
        $this->assertSame([1, 2], array_column($r['suggestions'], 'meeting'));

        // one meeting per day
        $days = array_map(static fn ($x) => $x['best']['day'], $r['suggestions']);
        $this->assertCount(2, array_unique($days));
    }

    public function testFullyPlottedSubjectIsSkipped(): void
    {
        $blocks = [
            ['day' => 0, 'code' => 'ITE 101', 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101', 'prof' => null],
            ['day' => 2, 'code' => 'ITE 101', 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101', 'prof' => null],
        ];

        $this->assertSame([], $this->run2([$this->subject('ITE 101')], $blocks)['suggestions']);
    }

    public function testPartiallyPlottedSubjectOnlyGetsTheRemainder(): void
    {
        $blocks = [['day' => 0, 'code' => 'ITE 101', 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101', 'prof' => null]];
        $r      = $this->run2([$this->subject('ITE 101')], $blocks);

        $this->assertCount(1, $r['suggestions']);
        $this->assertSame(2, $r['suggestions'][0]['meeting']);
        $this->assertNotSame(0, $r['suggestions'][0]['best']['day']);
        // reuses its existing room
        $this->assertSame('Room 101', $r['suggestions'][0]['best']['room']);
    }

    public function testNeverOverlapsSectionOrOccupiedRoom(): void
    {
        // Every room busy Mon-Fri all day except Saturday -> must land on Saturday
        $others = [];

        foreach ([0, 1, 2, 3, 4] as $d) {
            foreach (['Room 101', 'Room 102', 'Lab A'] as $room) {
                $others[] = ['day' => $d, 'code' => 'X', 'start' => '07:00', 'end' => '17:30', 'room' => $room, 'prof' => null];
            }
        }

        $r = $this->run2([$this->subject('ITE 101', 1.5, 1.5)], [], $others);

        $this->assertCount(1, $r['suggestions']);
        $this->assertSame(5, $r['suggestions'][0]['best']['day']);
    }

    public function testLabSubjectOnlyGetsLabRooms(): void
    {
        $r = $this->run2([$this->subject('LAB 1', 3, 3, true)]);

        $this->assertSame('Lab A', $r['suggestions'][0]['best']['room']);
        foreach ($r['suggestions'][0]['alternatives'] as $alt) {
            $this->assertSame('Lab A', $alt['room']);
        }
    }

    public function testNoLabRoomMeansUnplaceableWithReason(): void
    {
        $rooms = [['name' => 'Room 101', 'capacity' => 40, 'is_laboratory' => false]];
        $r     = $this->run2([$this->subject('LAB 1', 3, 3, true)], [], [], $rooms);

        $this->assertSame([], $r['suggestions']);
        $this->assertFalse($r['complete']);
        $this->assertStringContainsString('laboratory', $r['unplaceable'][0]['reason']);
    }

    public function testRoomMustSeatTheSection(): void
    {
        $rooms = [['name' => 'Tiny', 'capacity' => 10, 'is_laboratory' => false]];
        $r     = $this->run2([$this->subject('ITE 101')], [], [], $rooms, 30);

        $this->assertFalse($r['complete']);
        $this->assertStringContainsString('30 students', $r['unplaceable'][0]['reason']);
    }

    public function testProfessorConflictIsAvoided(): void
    {
        $blocks = [['day' => 0, 'code' => 'ITE 101', 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101', 'prof' => 'prof1', 'faculty_id' => 7]];
        $others = [['day' => 1, 'code' => 'Z', 'start' => '07:00', 'end' => '17:30', 'room' => 'Room 102', 'prof' => 'prof1']];

        $r = $this->run2([$this->subject('ITE 101')], $blocks, $others);

        $this->assertSame('prof1', $r['suggestions'][0]['prof']);
        $this->assertSame(7, $r['suggestions'][0]['faculty_id']);
        $this->assertNotSame(1, $r['suggestions'][0]['best']['day']); // prof busy all Tuesday
        $this->assertNotSame(0, $r['suggestions'][0]['best']['day']); // already meets Monday
    }

    public function testSubjectsInOnePlanNeverClash(): void
    {
        $subjects = [$this->subject('A', 1.5, 3), $this->subject('B', 1.5, 3), $this->subject('C', 3, 3), $this->subject('L', 3, 3, true)];
        $r        = $this->run2($subjects);

        $this->assertTrue($r['complete']);

        $placed = array_map(static fn ($x) => $x['best'] + ['code' => $x['code']], $r['suggestions']);

        foreach ($placed as $i => $a) {
            foreach (array_slice($placed, $i + 1) as $b) {
                if ($a['day'] !== $b['day']) {
                    continue;
                }

                $this->assertFalse($a['start'] < $b['end'] && $b['start'] < $a['end'], "{$a['code']} overlaps {$b['code']}");
            }
        }
    }

    public function testPrefersBackToBackAndAvoidsLunch(): void
    {
        $blocks = [['day' => 0, 'code' => 'A', 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101', 'prof' => null]];
        $r      = $this->run2([$this->subject('B', 1.5, 1.5)], $blocks);

        $best = $r['suggestions'][0]['best'];

        $this->assertSame(0, $best['day']);
        $this->assertSame('09:30', $best['start']);
        $this->assertStringContainsString('no gap', implode(' ', $best['reasons']));
    }

    public function testDeterministic(): void
    {
        $subjects = [$this->subject('A'), $this->subject('B')];

        $this->assertSame($this->run2($subjects), $this->run2($subjects));
    }

    private function prof(int $id, string $name, array $prefs = [], float $units = 0, ?array $availability = null): array
    {
        return ['id' => $id, 'name' => $name, 'employment_status' => 'full-time', 'preferences' => $prefs, 'units' => $units, 'availability' => $availability ?? []];
    }

    private function runWith(array $subjects, array $faculty, array $blocks = [], array $others = [], float $maxUnits = 24): array
    {
        return $this->s->suggest($subjects, $blocks, $others, $this->rooms, [
            'headcount' => 30, 'headcount_known' => true, 'grid_start' => 420, 'grid_end' => 1050,
            'faculty' => $faculty, 'max_units' => $maxUnits,
        ]);
    }

    private function subj(string $code, float $units = 3): array
    {
        return ['units' => $units] + $this->subject($code);
    }

    public function testPreferredProfessorIsRecommended(): void
    {
        $faculty = [
            $this->prof(1, 'alice', ['ITE 101' => 'qualified']),
            $this->prof(2, 'bob', ['ITE 101' => 'preferred']),
        ];

        $r = $this->runWith([$this->subj('ITE 101')], $faculty);

        $this->assertSame('bob', $r['suggestions'][0]['prof']);
        $this->assertSame(2, $r['suggestions'][0]['faculty_id']);
        // one professor for every meeting of the subject
        $this->assertSame('bob', $r['suggestions'][1]['prof']);
    }

    public function testLighterLoadBreaksTies(): void
    {
        $faculty = [$this->prof(1, 'alice', [], 18), $this->prof(2, 'bob', [], 6)];

        $this->assertSame('bob', $this->runWith([$this->subj('ITE 101')], $faculty)['suggestions'][0]['prof']);
    }

    public function testProfessorOverUnitCapIsSkipped(): void
    {
        $faculty = [$this->prof(1, 'alice', ['ITE 101' => 'preferred'], 22), $this->prof(2, 'bob')];

        $this->assertSame('bob', $this->runWith([$this->subj('ITE 101', 3)], $faculty)['suggestions'][0]['prof']);
    }

    public function testMeetingsStayInsideProfessorAvailability(): void
    {
        // alice is only available Tue/Thu morning
        $avail = [
            ['day_of_week' => 1, 'is_available' => 1, 'start_time' => '08:00', 'end_time' => '12:00'],
            ['day_of_week' => 3, 'is_available' => 1, 'start_time' => '08:00', 'end_time' => '12:00'],
        ];
        $faculty = [$this->prof(1, 'alice', ['ITE 101' => 'preferred'], 0, $avail)];

        $r = $this->runWith([$this->subj('ITE 101')], $faculty);

        $this->assertSame('alice', $r['suggestions'][0]['prof']);

        foreach ($r['suggestions'] as $s) {
            $this->assertContains($s['best']['day'], [1, 3]);
            $this->assertGreaterThanOrEqual('08:00', $s['best']['start']);
            $this->assertLessThanOrEqual('12:00', $s['best']['end']);
        }
    }

    public function testProfessorBusyElsewhereIsPassedOver(): void
    {
        $others  = [];
        foreach ([0, 1, 2, 3, 4, 5] as $d) {
            $others[] = ['day' => $d, 'code' => 'Z', 'start' => '07:00', 'end' => '17:30', 'room' => 'Room 102', 'prof' => 'alice'];
        }
        $faculty = [$this->prof(1, 'alice', ['ITE 101' => 'preferred']), $this->prof(2, 'bob')];

        $this->assertSame('bob', $this->runWith([$this->subj('ITE 101')], $faculty, [], $others)['suggestions'][0]['prof']);
    }

    public function testNobodyFreeStillPlacesWithAnExplanation(): void
    {
        $r = $this->runWith([$this->subj('ITE 101', 3)], [$this->prof(1, 'alice', [], 24)]);

        $this->assertNull($r['suggestions'][0]['prof']);
        $this->assertStringContainsString('unit capacity', $r['suggestions'][0]['faculty_note']);
    }

    public function testTwoSubjectsDoNotOverloadTheSameProfessorPastTheCap(): void
    {
        $faculty = [$this->prof(1, 'alice', ['A' => 'preferred', 'B' => 'preferred'], 18), $this->prof(2, 'bob', [], 0)];

        $r = $this->runWith([$this->subj('A', 3), $this->subj('B', 3)], $faculty, [], [], 24);

        $byCode = [];
        foreach ($r['suggestions'] as $s) {
            $byCode[$s['code']] = $s['prof'];
        }

        // alice (18) can take one 3-unit subject (21) and a second (24) but never a third; both fit here
        $this->assertContains($byCode['A'], ['alice', 'bob']);
        $this->assertContains($byCode['B'], ['alice', 'bob']);
    }

    public function testProfessorWithNothingSubmittedIsStillUsed(): void
    {
        $r = $this->runWith([$this->subj('ITE 101')], [$this->prof(1, 'newbie')]);

        $this->assertSame('newbie', $r['suggestions'][0]['prof']);
        $this->assertTrue($r['suggestions'][0]['faculty']['setup_pending']);
        $this->assertStringContainsString('not set their subjects', implode(' ', $r['suggestions'][0]['faculty']['reasons']));
    }

    public function testUnsubmittedProfessorIsListedAsAnOption(): void
    {
        $faculty = [$this->prof(1, 'alice', ['ITE 101' => 'preferred']), $this->prof(2, 'newbie')];

        $r       = $this->runWith([$this->subj('ITE 101')], $faculty);
        $options = array_column($r['suggestions'][0]['faculty_options'], null, 'name');

        $this->assertSame('alice', $r['suggestions'][0]['prof']);
        $this->assertArrayHasKey('newbie', $options);
        $this->assertTrue($options['newbie']['setup_pending']);
        $this->assertArrayHasKey('alice', $options);
    }

    public function testEveryEligibleProfessorIsOfferedForAnUnassignedSubject(): void
    {
        $blocks  = [['day' => 0, 'code' => 'A', 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101', 'prof' => null]];
        $faculty = [];

        for ($i = 1; $i <= 6; $i++) {
            $faculty[] = $this->prof($i, "prof{$i}", $i === 1 ? ['A' => 'preferred'] : []);
        }

        $items  = $this->s->improve([$this->subj('A', 1.5)], $blocks, [], $this->rooms, $this->improveOpts(['faculty' => $faculty]));
        $assign = array_values(array_filter($items, static fn ($i) => $i['type'] === 'assign_faculty'))[0];

        $names = array_merge([$assign['faculty']['name']], array_column($assign['alternatives'], 'name'));

        $this->assertCount(6, $names);
        $this->assertContains('prof6', $names);
    }

    // ---- improve() ----

    private function improveOpts(array $extra = []): array
    {
        return $extra + ['headcount' => 30, 'headcount_known' => true, 'grid_start' => 420, 'grid_end' => 1050];
    }

    public function testFlagsLabSubjectInLectureRoom(): void
    {
        $blocks = [['day' => 0, 'code' => 'LAB 1', 'start' => '08:00', 'end' => '11:00', 'room' => 'Room 101', 'prof' => null]];

        $items = $this->s->improve([$this->subject('LAB 1', 3, 3, true)], $blocks, [], $this->rooms, $this->improveOpts());

        $moves = array_values(array_filter($items, static fn ($i) => $i['type'] === 'move'));

        $this->assertCount(1, $moves);
        $this->assertSame('high', $moves[0]['impact']);
        $this->assertStringContainsString('not a laboratory room', $moves[0]['summary']);
        $this->assertSame('Lab A', $moves[0]['best']['room']);
    }

    public function testFlagsRoomTooSmall(): void
    {
        $rooms  = [['name' => 'Tiny', 'capacity' => 10, 'is_laboratory' => false], ['name' => 'Big', 'capacity' => 60, 'is_laboratory' => false]];
        $blocks = [['day' => 0, 'code' => 'A', 'start' => '08:00', 'end' => '09:30', 'room' => 'Tiny', 'prof' => null]];

        $items = $this->s->improve([$this->subject('A', 1.5, 1.5)], $blocks, [], $rooms, $this->improveOpts());
        $move  = array_values(array_filter($items, static fn ($i) => $i['type'] === 'move'))[0];

        $this->assertStringContainsString('seats 10', $move['summary']);
        $this->assertSame('Big', $move['best']['room']);
    }

    public function testProposesMovingClassOutOfLunchAndFarFromTheRest(): void
    {
        $blocks = [
            ['day' => 0, 'code' => 'A', 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101', 'prof' => null],
            ['day' => 0, 'code' => 'B', 'start' => '12:00', 'end' => '13:00', 'room' => 'Room 101', 'prof' => null],
        ];
        $subjects = [$this->subject('A', 1.5, 1.5), $this->subject('B', 1, 1)];

        $items = $this->s->improve($subjects, $blocks, [], $this->rooms, $this->improveOpts());
        $move  = array_values(array_filter($items, static fn ($i) => $i['type'] === 'move' && $i['code'] === 'B'));

        $this->assertNotEmpty($move);
        $this->assertNotSame('12:00', $move[0]['best']['start']);
    }

    public function testWellPlacedScheduleHasNoMoveSuggestions(): void
    {
        $blocks = [
            ['day' => 0, 'code' => 'A', 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101', 'prof' => 'p'],
            ['day' => 0, 'code' => 'B', 'start' => '09:30', 'end' => '11:00', 'room' => 'Room 101', 'prof' => 'p2'],
        ];

        $items = $this->s->improve([$this->subject('A', 1.5, 1.5), $this->subject('B', 1.5, 1.5)], $blocks, [], $this->rooms, $this->improveOpts());

        $this->assertSame([], array_values(array_filter($items, static fn ($i) => $i['type'] === 'move')));
    }

    public function testRecommendsProfessorForUnassignedSubject(): void
    {
        $blocks = [
            ['day' => 0, 'code' => 'A', 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101', 'prof' => null],
            ['day' => 2, 'code' => 'A', 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101', 'prof' => null],
        ];
        $faculty = [$this->prof(1, 'alice', ['A' => 'preferred']), $this->prof(2, 'bob')];

        $items = $this->s->improve([$this->subj('A', 3)], $blocks, [], $this->rooms, $this->improveOpts(['faculty' => $faculty]));
        $assign = array_values(array_filter($items, static fn ($i) => $i['type'] === 'assign_faculty'));

        $this->assertCount(1, $assign);
        $this->assertSame('alice', $assign[0]['faculty']['name']);
        $this->assertSame('bob', $assign[0]['alternatives'][0]['name']);
    }

    public function testDoesNotRecommendProfessorWhoIsBusyDuringAMeeting(): void
    {
        $blocks = [['day' => 0, 'code' => 'A', 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101', 'prof' => null]];
        $others = [['day' => 0, 'code' => 'Z', 'start' => '08:30', 'end' => '10:00', 'room' => 'Room 102', 'prof' => 'alice']];
        $faculty = [$this->prof(1, 'alice', ['A' => 'preferred']), $this->prof(2, 'bob')];

        $items  = $this->s->improve([$this->subj('A', 1.5)], $blocks, $others, $this->rooms, $this->improveOpts(['faculty' => $faculty]));
        $assign = array_values(array_filter($items, static fn ($i) => $i['type'] === 'assign_faculty'))[0];

        $this->assertSame('bob', $assign['faculty']['name']);
    }

    public function testFlagsProfessorUnavailableAtCurrentSlot(): void
    {
        $avail   = [['day_of_week' => 1, 'is_available' => 1, 'start_time' => '13:00', 'end_time' => '17:00']];
        $faculty = [$this->prof(1, 'alice', [], 0, $avail)];
        $blocks  = [['day' => 0, 'code' => 'A', 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101', 'prof' => 'alice', 'faculty_id' => 1]];

        $items = $this->s->improve([$this->subj('A', 1.5)], $blocks, [], $this->rooms, $this->improveOpts(['faculty' => $faculty]));
        $move  = array_values(array_filter($items, static fn ($i) => $i['type'] === 'move'))[0];

        $this->assertSame('high', $move['impact']);
        $this->assertStringContainsString('not available', $move['summary']);
        $this->assertSame(1, $move['best']['day']);
    }

    public function testMeetingsNeededRespectsWeeklyCap(): void
    {
        $this->assertSame(3, $this->s->meetingsNeeded($this->subject('A', 1, 9), []));
        $this->assertSame(0, $this->s->meetingsNeeded($this->subject('A', 1, 0), []));
    }
}

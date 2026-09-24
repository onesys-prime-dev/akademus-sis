<?php

use App\Libraries\SectionAllocationService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SectionAllocationServiceTest extends CIUnitTestCase
{
    private SectionAllocationService $svc;

    /** min 10, target 15, max 15, overflow 5 */
    private array $policy = ['min' => 10, 'target' => 15, 'max' => 15, 'overflow' => 5];

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new SectionAllocationService();
    }

    /** @return array<string, array{int, list<int>}> */
    public static function headcounts(): array
    {
        return [
            '5'  => [5, [5]],
            '9'  => [9, [9]],
            '10' => [10, [10]],
            '14' => [14, [14]],
            '15' => [15, [15]],
            '16' => [16, [16]],
            '19' => [19, [19]],
            '20' => [20, [10, 10]],
            '21' => [21, [11, 10]],
            '24' => [24, [12, 12]],
            '25' => [25, [13, 12]],
            '28' => [28, [14, 14]],
            '29' => [29, [15, 14]],
            '30' => [30, [15, 15]],
            '31' => [31, [16, 15]],
            '35' => [35, [18, 17]],
            '39' => [39, [20, 19]],
            '40' => [40, [14, 13, 13]],
            '44' => [44, [15, 15, 14]],
            '45' => [45, [15, 15, 15]],
            '46' => [46, [16, 15, 15]],
            '60' => [60, [15, 15, 15, 15]],
            '61' => [61, [16, 15, 15, 15]],
        ];
    }

    /**
     * @dataProvider headcounts
     */
    public function testDistribution(int $students, array $expected): void
    {
        $plan = $this->svc->plan($students, $this->policy);

        $this->assertSame($expected, $plan['sizes']);
        $this->assertSame(count($expected), $plan['sections']);

        // no student lost or duplicated
        $this->assertSame($students, array_sum($plan['sizes']));

        // balanced, within the ceiling, and (once a split is justified) at or above the minimum
        $this->assertLessThanOrEqual(1, max($plan['sizes']) - min($plan['sizes']));
        $this->assertLessThanOrEqual(20, max($plan['sizes']));

        if ($students >= 20) {
            $this->assertGreaterThanOrEqual(10, min($plan['sizes']));
        }

        $this->assertSame([], $this->svc->validateDistribution($plan['sizes'], $this->policy, $students));

        // deterministic
        $this->assertSame($plan, $this->svc->plan($students, $this->policy));
    }

    public function testEdgeCounts(): void
    {
        $this->assertSame([0], $this->svc->plan(0, $this->policy)['sizes']);
        $this->assertSame([1], $this->svc->plan(1, $this->policy)['sizes']);
        $this->assertNotEmpty($this->svc->plan(1, $this->policy)['warnings']);
    }

    public function testNoOverflowUsesMaximumAsCeiling(): void
    {
        $policy = ['min' => 10, 'target' => 15, 'max' => 15, 'overflow' => 0];

        $this->assertSame([15], $this->svc->plan(15, $policy)['sizes']);
        $this->assertSame([8, 8], $this->svc->plan(16, $policy)['sizes']);
    }

    public function testInvalidPolicyRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->svc->plan(10, ['min' => 20, 'target' => 15, 'max' => 15]);
    }

    public function testSecondSectionExplanationMentionsMinimum(): void
    {
        $reason = $this->svc->plan(16, $this->policy)['reason'];

        $this->assertStringContainsString('below the configured minimum of 10', $reason);
    }

    public function testNoFlappingAroundTheThreshold(): void
    {
        // 20 opens a second section...
        $this->assertSame('open', $this->svc->recommendSectionCount(20, 1, 0, $this->policy)['action']);

        // ...and 19 does not close it again (hysteresis buffer is 2)...
        $r = $this->svc->recommendSectionCount(19, 2, 0, $this->policy);
        $this->assertSame(2, $r['sections']);
        $this->assertSame('none', $r['action']);

        // ...nor does 18, but 17 does.
        $this->assertSame(2, $this->svc->recommendSectionCount(18, 2, 0, $this->policy)['sections']);
        $this->assertSame('close', $this->svc->recommendSectionCount(17, 2, 0, $this->policy)['action']);
    }

    public function testLockedSectionsAreNeverClosed(): void
    {
        $r = $this->svc->recommendSectionCount(5, 2, 2, $this->policy);

        $this->assertSame(2, $r['sections']);
        $this->assertSame('none', $r['action']);
        $this->assertFalse($this->svc->shouldMergeSections(5, 2, 2, $this->policy));
        $this->assertTrue($this->svc->shouldMergeSections(5, 2, 0, $this->policy));
    }

    public function testShouldCreateNewSection(): void
    {
        $this->assertFalse($this->svc->shouldCreateNewSection(19, 1, $this->policy));
        $this->assertTrue($this->svc->shouldCreateNewSection(20, 1, $this->policy));
        $this->assertFalse($this->svc->shouldCreateNewSection(31, 2, $this->policy));
        $this->assertTrue($this->svc->shouldCreateNewSection(40, 2, $this->policy));
    }

    public function testAutomaticRebalanceGuards(): void
    {
        $this->assertTrue($this->svc->canAutomaticallyRebalance(['status' => 'proposed'])['allowed']);

        $blocked = $this->svc->canAutomaticallyRebalance(['status' => 'open', 'has_schedule' => true]);
        $this->assertFalse($blocked['allowed']);
        $this->assertStringContainsString('schedule', $blocked['reason']);

        $this->assertFalse($this->svc->canAutomaticallyRebalance(['status' => 'finalized'])['allowed']);
        $this->assertFalse($this->svc->canAutomaticallyRebalance(['status' => 'proposed', 'auto_balance' => false])['allowed']);
        $this->assertFalse($this->svc->canAutomaticallyRebalance(['status' => 'open', 'has_grades' => true])['allowed']);
    }

    public function testNextSectionCode(): void
    {
        $this->assertSame('1A', $this->svc->generateNextSectionCode('1st Year', []));
        $this->assertSame('1C', $this->svc->generateNextSectionCode('1st Year', ['1A', '1B']));
        $this->assertSame('2B', $this->svc->generateNextSectionCode('2nd Year', ['2a']));
        $this->assertSame('1B', $this->svc->generateNextSectionCode('1st Year', ['1A', 'AIT1']));

        $all = [];
        foreach (range('A', 'Z') as $l) {
            $all[] = '3' . $l;
        }
        $this->assertSame('3AA', $this->svc->generateNextSectionCode('3rd Year', $all));
    }

    public function testPreviewRebalance(): void
    {
        $preview = $this->svc->previewRebalance([18, 7], $this->policy);

        $this->assertSame([13, 12], $preview['proposed']);
        $this->assertSame(5, $preview['moves']);
        $this->assertSame(25, $preview['students']);
    }

    public function testPreviewLeavesLockedSectionsAlone(): void
    {
        $preview = $this->svc->previewRebalance([18, 7, 9], $this->policy, [0]);

        $this->assertSame(18, $preview['proposed'][0]);
        $this->assertSame(18 + 16, array_sum($preview['proposed']));
        $this->assertStringContainsString('locked', $preview['reason']);
    }

    public function testPreviewAllLocked(): void
    {
        $preview = $this->svc->previewRebalance([18, 7], $this->policy, [0, 1]);

        $this->assertSame([18, 7], $preview['proposed']);
        $this->assertSame(0, $preview['moves']);
    }
}

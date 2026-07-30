<?php

use App\Libraries\ScheduleOverlap;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ScheduleOverlapTest extends CIUnitTestCase
{
    public function testOverlappingTimesOnSameDayClash(): void
    {
        $a = ['day' => 0, 'start' => '08:00', 'end' => '10:00'];
        $b = ['day' => 0, 'start' => '09:00', 'end' => '11:00'];

        $this->assertTrue(ScheduleOverlap::clashes($a, $b));
    }

    public function testNonOverlappingTimesOnSameDayDoNotClash(): void
    {
        $a = ['day' => 0, 'start' => '08:00', 'end' => '10:00'];
        $b = ['day' => 0, 'start' => '10:00', 'end' => '12:00'];

        $this->assertFalse(ScheduleOverlap::clashes($a, $b));
    }

    public function testSameTimeDifferentDayDoesNotClash(): void
    {
        $a = ['day' => 0, 'start' => '08:00', 'end' => '10:00'];
        $b = ['day' => 1, 'start' => '08:00', 'end' => '10:00'];

        $this->assertFalse(ScheduleOverlap::clashes($a, $b));
    }

    public function testTouchingBoundaryDoesNotClash(): void
    {
        // 08:00-09:00 followed immediately by 09:00-10:00 is back-to-back,
        // not overlapping.
        $a = ['day' => 2, 'start' => '08:00', 'end' => '09:00'];
        $b = ['day' => 2, 'start' => '09:00', 'end' => '10:00'];

        $this->assertFalse(ScheduleOverlap::clashes($a, $b));
    }

    public function testOneMeetingFullyInsideAnotherClashes(): void
    {
        $a = ['day' => 3, 'start' => '08:00', 'end' => '12:00'];
        $b = ['day' => 3, 'start' => '09:00', 'end' => '10:00'];

        $this->assertTrue(ScheduleOverlap::clashes($a, $b));
    }
}

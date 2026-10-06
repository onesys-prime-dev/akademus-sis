<?php

use App\Libraries\StudentDashboardSchedule;
use CodeIgniter\Test\CIUnitTestCase;

final class StudentDashboardScheduleTest extends CIUnitTestCase
{
    private function meeting(int $day, string $start, string $end, string $room = 'Lab A'): array
    {
        return ['day' => $day, 'start_time' => $start, 'end_time' => $end, 'subject_code' => 'ITE 101', 'subject_title' => 'Computing', 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => 'IT1', 'room' => $room, 'prof' => 'Instructor'];
    }

    public function testContiguousMeetingsMergeButDifferentRoomsRemainSeparate(): void
    {
        $result = StudentDashboardSchedule::summarize([
            $this->meeting(0, '08:00:00', '09:30:00'),
            $this->meeting(0, '09:30:00', '11:00:00'),
            $this->meeting(0, '11:00:00', '12:00:00', 'Lab B'),
        ], new DateTimeImmutable('2026-10-05 09:45:00', new DateTimeZone('Asia/Manila')));
        $this->assertCount(2, $result['meetings']);
        $this->assertSame('11:00:00', $result['next']['end_time']);
        $this->assertTrue($result['next']['ongoing']);
        $this->assertSame('In progress', $result['today'][0]['state']);
        $this->assertEquals(4, $result['hours']);
    }

    public function testEndBoundaryAdvancesToTheNextMeeting(): void
    {
        $result = StudentDashboardSchedule::summarize([
            $this->meeting(0, '08:00:00', '09:30:00'),
            $this->meeting(0, '13:00:00', '14:30:00'),
        ], new DateTimeImmutable('2026-10-05 09:30:00', new DateTimeZone('Asia/Manila')));
        $this->assertSame('Finished', $result['today'][0]['state']);
        $this->assertSame('13:00:00', $result['next']['start_time']);
        $this->assertFalse($result['next']['ongoing']);
    }

    public function testTimezoneAndWeekRolloverUsePhilippineTime(): void
    {
        $rows = [$this->meeting(0, '08:00:00', '09:30:00')];
        // Still Sunday in UTC, already Monday in Manila.
        $result = StudentDashboardSchedule::summarize($rows, new DateTimeImmutable('2026-10-04 23:00:00 UTC'));
        $this->assertCount(1, $result['today']);
        $this->assertSame('2026-10-05 08:00', $result['next']['starts_at']->format('Y-m-d H:i'));
        $after = StudentDashboardSchedule::summarize($rows, new DateTimeImmutable('2026-10-05 10:00:00 Asia/Manila'));
        $this->assertSame('2026-10-12 08:00', $after['next']['starts_at']->format('Y-m-d H:i'));
    }

    public function testEmptyScheduleHasNoInventedNextClass(): void
    {
        $result = StudentDashboardSchedule::summarize([], new DateTimeImmutable('2026-10-06 Asia/Manila'));
        $this->assertNull($result['next']);
        $this->assertSame([], $result['today']);
        $this->assertSame(0, $result['days']);
    }
}

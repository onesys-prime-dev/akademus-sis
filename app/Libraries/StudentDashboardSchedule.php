<?php

namespace App\Libraries;

use DateTimeImmutable;
use DateTimeZone;

/** Recurring timetable summaries; all day boundaries use Philippine time. */
final class StudentDashboardSchedule
{
    public static function summarize(array $rows, DateTimeImmutable $now): array
    {
        $now = $now->setTimezone(new DateTimeZone('Asia/Manila'));
        $groups = [];
        foreach ($rows as $row) {
            $key = json_encode([$row['day'], $row['subject_code'], $row['program'], $row['year_level'], $row['section'], $row['room'], $row['prof']]);
            $groups[$key][] = $row;
        }
        $meetings = [];
        foreach ($groups as $group) {
            usort($group, static fn ($a, $b) => strcmp($a['start_time'], $b['start_time']));
            $merged = [];
            foreach ($group as $row) {
                $last = count($merged) - 1;
                if ($last >= 0 && $merged[$last]['end_time'] === $row['start_time']) {
                    $merged[$last]['end_time'] = $row['end_time'];
                } else {
                    $merged[] = $row;
                }
            }
            array_push($meetings, ...$merged);
        }
        usort($meetings, static fn ($a, $b) => (int) $a['day'] <=> (int) $b['day'] ?: strcmp($a['start_time'], $b['start_time']));
        $day = (int) $now->format('N') - 1;
        $clock = $now->format('H:i:s');
        $today = array_values(array_filter($meetings, static fn ($m) => (int) $m['day'] === $day));
        $next = null;
        $weeklyMinutes = 0;
        foreach ($meetings as $meeting) {
            [$sh, $sm] = array_map('intval', explode(':', $meeting['start_time']));
            [$eh, $em] = array_map('intval', explode(':', $meeting['end_time']));
            $weeklyMinutes += max(0, ($eh * 60 + $em) - ($sh * 60 + $sm));
            $offset = ((int) $meeting['day'] - $day + 7) % 7;
            $date = $now->setTime(0, 0)->modify("+{$offset} days");
            $start = $date->setTime($sh, $sm);
            $end = $date->setTime($eh, $em);
            if ($end <= $now) { $start = $start->modify('+7 days'); $end = $end->modify('+7 days'); }
            if ($next === null || $start < $next['starts_at']) {
                $next = $meeting + ['starts_at' => $start, 'ends_at' => $end, 'ongoing' => $start <= $now && $end > $now];
            }
        }
        foreach ($today as &$meeting) {
            $start = substr($meeting['start_time'], 0, 5) . ':00';
            $end = substr($meeting['end_time'], 0, 5) . ':00';
            $meeting['state'] = $clock >= $end ? 'Finished' : ($clock >= $start ? 'In progress' : 'Upcoming');
        }
        unset($meeting);
        return ['meetings' => $meetings, 'today' => $today, 'next' => $next, 'hours' => round($weeklyMinutes / 60, 1), 'days' => count(array_unique(array_column($meetings, 'day')))];
    }
}

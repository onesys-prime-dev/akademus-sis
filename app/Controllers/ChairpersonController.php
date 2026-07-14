<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\PlottedScheduleModel;

class ChairpersonController extends BaseController
{
    private const FILTERS = [
        'academic_year' => ['AY 2025 - 2026', 'AY 2024 - 2025'],
        'program'       => ['BS in Information Technology', 'BS in Computer Science'],
        'year_level'    => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
        'section'       => ['AIT1', 'AIT2', 'BIT1'],
    ];

    private const ROOMS = ['Lab A', 'Lab B', 'Lab C', 'Room 105', 'Room 203', 'Room 302'];

    /** A subject can meet at most this many times per week. */
    private const MAX_MEETINGS = 3;

    /** Saturday's index in the weekly grid (labs are Saturday-only). */
    private const SATURDAY = 5;

    /**
     * Curriculum subjects (left rail). duration is in hours; is_lab subjects
     * may only be plotted on Saturdays. color = accent used in the UI.
     */
    private const SUBJECTS = [
        ['code' => 'IT101',    'units' => '3 Units', 'title' => 'Introduction to Computing',   'prof' => 'Prof. Rose Perreras',    'hours' => '1.5 hours', 'duration' => 1.5, 'is_lab' => false, 'color' => 'blue'],
        ['code' => 'IT102',    'units' => '3 Units', 'title' => 'Computer Programming 1',      'prof' => 'Prof. Liza Natividad',   'hours' => '1.5 hours', 'duration' => 1.5, 'is_lab' => false, 'color' => 'purple'],
        ['code' => 'IT103',    'units' => '3 Units', 'title' => 'Web Systems & Technology',    'prof' => 'Prof. Paul Ocampo',      'hours' => '1.5 hours', 'duration' => 1.5, 'is_lab' => false, 'color' => 'cyan'],
        ['code' => 'IT104',    'units' => '3 Units', 'title' => 'Data Structures',             'prof' => 'Prof. Edward Bustillos', 'hours' => '3 hours',   'duration' => 3.0, 'is_lab' => false, 'color' => 'red'],
        ['code' => 'IT105',    'units' => '3 Units', 'title' => 'Networking 1',                'prof' => 'Prof. Liza Natividad',   'hours' => '1.5 hours', 'duration' => 1.5, 'is_lab' => false, 'color' => 'blue'],
        ['code' => 'GE01',     'units' => '3 Units', 'title' => 'Math in Modern World',        'prof' => 'Prof. Macapagal',        'hours' => '1.5 hours', 'duration' => 1.5, 'is_lab' => false, 'color' => 'green'],
        ['code' => 'PATHFIT1', 'units' => '2 Units', 'title' => 'Physical Education',          'prof' => 'Prof. Axel',             'hours' => '1.5 hours', 'duration' => 1.5, 'is_lab' => false, 'color' => 'red'],
        ['code' => 'OOP1',     'units' => '3 Units', 'title' => 'Object Oriented Programming', 'prof' => 'Prof. Melchor Jose',     'hours' => '1.5 hours', 'duration' => 1.5, 'is_lab' => false, 'color' => 'purple'],
        ['code' => 'CSLAB',    'units' => '2 Units', 'title' => 'Programming Laboratory',      'prof' => 'Prof. Melchor Jose',     'hours' => '3 hours',   'duration' => 3.0, 'is_lab' => true,  'color' => 'yellow'],
    ];

    public function index()
    {
        $context = $this->contextFromRequest();

        $schedules = new PlottedScheduleModel();

        $ownRows = $schedules->forContext(...array_values($context));
        $blocks  = array_map([$this, 'rowToBlock'], $ownRows);

        // Published only when the context has rows and none are still drafts.
        $isPublished = $ownRows !== [] && ! in_array('draft', array_column($ownRows, 'status'), true);

        // Classes plotted for other sections in the same AY: shown as ghost
        // blocks and counted for room/faculty availability.
        $otherBlocks = array_map(
            [$this, 'rowToBlock'],
            $schedules->forOtherContexts(...array_values($context))
        );

        $data = [
            'user' => [
                'name' => 'Rosemarie Perreras',
                'role' => 'IT Department - Chairperson',
            ],

            'filters'  => self::FILTERS,
            'selected' => $context,

            'subjects' => self::SUBJECTS,

            // Weekly grid. Days are Monday..Saturday.
            'week' => [
                ['day' => 'Monday',    'date' => 'August 7'],
                ['day' => 'Tuesday',   'date' => 'August 8'],
                ['day' => 'Wednesday', 'date' => 'August 9'],
                ['day' => 'Thursday',  'date' => 'August 10'],
                ['day' => 'Friday',    'date' => 'August 11'],
                ['day' => 'Saturday',  'date' => 'August 12'],
            ],

            // Grid runs 7:00 AM - 5:30 PM in 30-minute rows.
            'grid_start' => '07:00',
            'grid_end'   => '17:30',

            'rooms' => self::ROOMS,

            'blocks'       => $blocks,
            'other_blocks' => $otherBlocks,
            'is_published' => $isPublished,

            'suggestion' => [
                'title'  => 'Move IT102 to Wed 1:00 PM - 2:30 PM',
                'impact' => 'High impact',
                'body'   => 'Room 302 is available and creates a better flow with no gaps before or after.',
                'alt'    => 'View alternative options (2)',
            ],
        ];

        return view('chairperson/schedule_plotting', $data);
    }

    /**
     * Other sections' blocks for the requested context, polled by the UI to
     * keep ghost blocks and room availability current.
     */
    public function others()
    {
        $context = $this->contextFromRequest();

        $schedules = new PlottedScheduleModel();

        return $this->response->setJSON([
            'others' => array_map(
                [$this, 'rowToBlock'],
                $schedules->forOtherContexts(...array_values($context))
            ),
        ]);
    }

    public function saveDraft()
    {
        return $this->persistSchedule('draft');
    }

    public function publish()
    {
        return $this->persistSchedule('published');
    }

    /**
     * Validate and persist the submitted plot for one context.
     * Every rule the UI enforces is re-checked here.
     */
    private function persistSchedule(string $status)
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || ! isset($payload['blocks'], $payload['context']) || ! is_array($payload['blocks']) || ! is_array($payload['context'])) {
            return $this->fail('Invalid payload.');
        }

        $context = [];

        foreach (self::FILTERS as $key => $allowed) {
            if (! isset($payload['context'][$key]) || ! in_array($payload['context'][$key], $allowed, true)) {
                return $this->fail("Invalid or missing context: {$key}");
            }
            $context[$key] = $payload['context'][$key];
        }

        if ($status === 'published' && $payload['blocks'] === []) {
            return $this->fail('Nothing to publish: the schedule is empty.');
        }

        $subjectsByCode = array_column(self::SUBJECTS, null, 'code');

        $blocks = [];

        foreach ($payload['blocks'] as $block) {
            if (! is_array($block)) {
                return $this->fail('Invalid block.');
            }

            foreach (['code', 'time_label', 'room', 'start', 'end'] as $key) {
                if (! isset($block[$key]) || ! is_string($block[$key])) {
                    return $this->fail("Missing block field: {$key}");
                }
            }

            if (! isset($block['day']) || ! is_int($block['day']) || $block['day'] < 0 || $block['day'] > 5) {
                return $this->fail('Invalid block day.');
            }

            // The subject must exist in the curriculum; its details are
            // canonicalized from the curriculum, not trusted from the client.
            if (! isset($subjectsByCode[$block['code']])) {
                return $this->fail("Unknown subject: {$block['code']}");
            }

            $subject = $subjectsByCode[$block['code']];

            if (! in_array($block['room'], self::ROOMS, true)) {
                return $this->fail("Unknown room: {$block['room']}");
            }

            if (! preg_match('/^\d{2}:\d{2}$/', $block['start']) || ! preg_match('/^\d{2}:\d{2}$/', $block['end'])
                || $block['start'] >= $block['end']) {
                return $this->fail('Invalid block time range.');
            }

            // The plotted length must match the subject's class duration.
            if ($this->toMinutes($block['end']) - $this->toMinutes($block['start']) !== (int) round($subject['duration'] * 60)) {
                return $this->fail("{$subject['code']} must be plotted as a {$subject['hours']} block.");
            }

            // Laboratory subjects are Saturday-only.
            if ($subject['is_lab'] && $block['day'] !== self::SATURDAY) {
                return $this->fail("{$subject['code']} is a laboratory subject and can only be plotted on Saturdays.");
            }

            $blocks[] = [
                'day'        => $block['day'],
                'code'       => $subject['code'],
                'title'      => $subject['title'],
                'prof'       => $subject['prof'],
                'room'       => $block['room'],
                'theme'      => $subject['color'],
                'start'      => $block['start'],
                'end'        => $block['end'],
                'time_label' => $block['time_label'],
            ];
        }

        // A subject cannot meet more than MAX_MEETINGS times per week.
        $meetings = array_count_values(array_column($blocks, 'code'));

        foreach ($meetings as $code => $count) {
            if ($count > self::MAX_MEETINGS) {
                return $this->fail("{$code} is plotted {$count} times; the maximum is " . self::MAX_MEETINGS . ' meetings per week.');
            }
        }

        // Within the section: no two classes may overlap in time.
        foreach ($blocks as $i => $a) {
            foreach (array_slice($blocks, $i + 1) as $b) {
                if ($a['day'] === $b['day'] && $this->overlaps($a, $b)) {
                    return $this->fail("Section conflict: {$a['code']} and {$b['code']} overlap on the same day.");
                }
            }
        }

        // Against every other section in the same AY: rooms and professors
        // cannot be in two places at once.
        $schedules = new PlottedScheduleModel();
        $others    = array_map([$this, 'rowToBlock'], $schedules->forOtherContexts(...array_values($context)));

        foreach ($blocks as $block) {
            foreach ($others as $other) {
                if ($block['day'] !== $other['day'] || ! $this->overlaps($block, $other)) {
                    continue;
                }

                if ($block['room'] === $other['room']) {
                    return $this->fail("Room conflict: {$other['room']} is used by {$other['code']} ({$other['section']}) at {$other['time_label']}.");
                }

                if ($block['prof'] !== null && $block['prof'] !== '' && $block['prof'] === $other['prof']) {
                    return $this->fail("Faculty conflict: {$other['prof']} teaches {$other['code']} ({$other['section']}) at {$other['time_label']}.");
                }
            }
        }

        $rows = array_map(static fn (array $b) => [
            'academic_year' => $context['academic_year'],
            'program'       => $context['program'],
            'year_level'    => $context['year_level'],
            'section'       => $context['section'],
            'day'           => $b['day'],
            'start_time'    => $b['start'],
            'end_time'      => $b['end'],
            'time_label'    => $b['time_label'],
            'subject_code'  => $b['code'],
            'subject_title' => $b['title'],
            'prof'          => $b['prof'],
            'room'          => $b['room'],
            'theme'         => $b['theme'],
            'status'        => $status,
        ], $blocks);

        $schedules->replaceDraft(
            $context['academic_year'],
            $context['program'],
            $context['year_level'],
            $context['section'],
            $rows
        );

        return $this->response->setJSON(['ok' => true, 'saved' => count($rows), 'status' => $status]);
    }

    /**
     * Resolve the plotting context from query params, falling back to the
     * first option of each filter.
     */
    private function contextFromRequest(): array
    {
        $context = [];

        foreach (self::FILTERS as $key => $allowed) {
            $value = $this->request->getGet($key);
            $context[$key] = in_array($value, $allowed, true) ? $value : $allowed[0];
        }

        return $context;
    }

    private function rowToBlock(array $row): array
    {
        return [
            'day'        => (int) $row['day'],
            'code'       => $row['subject_code'],
            'title'      => $row['subject_title'],
            'prof'       => $row['prof'],
            'room'       => $row['room'],
            'theme'      => $row['theme'],
            'start'      => substr($row['start_time'], 0, 5),
            'end'        => substr($row['end_time'], 0, 5),
            'time_label' => $row['time_label'],
            'section'    => $row['section'],
        ];
    }

    private function toMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    private function overlaps(array $a, array $b): bool
    {
        return $a['start'] < $b['end'] && $b['start'] < $a['end'];
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

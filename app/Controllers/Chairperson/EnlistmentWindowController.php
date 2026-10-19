<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Libraries\SchedulingAuthorization;
use App\Models\AcademicYearModel;
use App\Models\CurriculumModel;
use App\Models\DepartmentModel;
use App\Models\EnrollmentModel;
use App\Models\SectionModel;

/**
 * A chairperson's narrow slice of academic-calendar control: only the
 * enlistment window, not the full grading/registration scope Admin\
 * AcademicYearController::updateWindow() exposes to admin/registrar.
 * Satisfies the spec's "Department Chairs or Registrars may open/close the
 * enlistment window" without granting chairs calendar.manage.
 *
 * Targets the UPCOMING term (AcademicYearModel::upcomingTerm()), not the
 * currently-active one — enlistment is pre-registration for what's next,
 * done while the active term is still running, so the window a chair
 * configures here has to live on that next term's row, not the one
 * students already have live classes in.
 *
 * Also hosts "Offered Subjects": a curriculum-driven shortcut for making a
 * term's subjects visible to students on the Enlistment page, WITHOUT
 * forcing the chair through the full Schedule Plotting tool first. Every
 * curriculum subject for the chosen program/year/semester is pre-checked
 * (autofill) — the chair's job is to uncheck the ones NOT being offered
 * this term, then save. Each checked subject gets one placeholder weekly
 * meeting (room/instructor "to be assigned") so it shows up immediately in
 * EnrollmentModel::subjectOfferings(); exact day/time/room can still be
 * refined later from Schedule Plotting, same underlying plotted_schedules
 * rows either way.
 */
class EnlistmentWindowController extends BaseController
{
    private const YEAR_LEVEL_LABELS = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'];
    private const SEMESTER_CODES    = ['1st' => '1', '2nd' => '2', 'Summer' => '3'];

    // A handful of non-overlapping weekly slots to auto-assign, cycling
    // through them in order as subjects are offered — good enough for "the
    // subject exists and is selectable," refined for real later.
    private const TIME_SLOTS = [
        ['08:00:00', '09:30:00'],
        ['09:30:00', '11:00:00'],
        ['11:00:00', '12:30:00'],
        ['13:00:00', '14:30:00'],
        ['14:30:00', '16:00:00'],
    ];
    private const DAY_PAIRS = [[0, 2], [1, 3]]; // Mon+Wed, Tue+Thu, alternating per subject

    public function edit()
    {
        $userId   = (int) auth()->id();
        $ayModel  = new AcademicYearModel();
        $terms    = $ayModel->upcomingTerms();
        $ay       = $this->resolveTerm($terms, (int) ($this->request->getGet('ay_id') ?? 0));
        $programs = $this->authorizedPrograms($userId);

        $programId = (int) ($this->request->getGet('program_id') ?? 0);
        $yearLevel = (int) ($this->request->getGet('year_level') ?? 0);
        $section   = trim((string) ($this->request->getGet('section') ?? ''));

        $programIds = array_column($programs, 'program_id');

        if ($programId === 0 || ! in_array($programId, $programIds, true)) {
            $programId = $programIds[0] ?? 0;
        }

        $sections = [];

        if ($ay !== null && $programId !== 0 && $yearLevel >= 1 && $yearLevel <= 4) {
            $sections = $this->sectionsFor($programId, self::YEAR_LEVEL_LABELS[$yearLevel], $ay);
        }

        $subjects = [];

        if ($ay !== null && $programId !== 0 && $yearLevel >= 1 && $yearLevel <= 4 && $section !== '') {
            $subjects = $this->offerableSubjects($programId, $yearLevel, $ay, $section);
        }

        return view('chairperson/enlistment_window', [
            'pageTitle'       => 'Enlistment Window',
            'user'            => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
            'terms'           => $terms,
            'ay'              => $ay,
            'isOpen'          => $ay !== null && $ayModel->isEnlistmentOpen((int) $ay['ay_id']),
            'programs'        => $programs,
            'selectedProgram' => $programId,
            'yearLevels'      => self::YEAR_LEVEL_LABELS,
            'selectedYear'    => $yearLevel,
            'sections'        => $sections,
            'selectedSection' => $section,
            'subjects'        => $subjects,
        ]);
    }

    public function update()
    {
        $ayModel = new AcademicYearModel();
        $ay      = $this->resolveTerm($ayModel->upcomingTerms(), (int) $this->request->getPost('ay_id'));

        if ($ay === null) {
            return redirect()->to('/chairperson/enlistment-window')->with('errors', ['No upcoming academic term exists yet — create one under Admin > Academic Years before opening enlistment for it.']);
        }

        $rules = [
            'enlistment_opens_at'  => 'permit_empty|valid_date',
            'enlistment_closes_at' => 'permit_empty|valid_date',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $ayModel->update((int) $ay['ay_id'], [
            'enlistment_opens_at'  => $this->request->getPost('enlistment_opens_at') ?: null,
            'enlistment_closes_at' => $this->request->getPost('enlistment_closes_at') ?: null,
        ]);

        return redirect()->to('/chairperson/enlistment-window?ay_id=' . (int) $ay['ay_id'])->with('message', "{$ay['label']} ({$ay['semester']}) enlistment window updated.");
    }

    /**
     * Saves the autofill-minus-unchecked selection: offers every checked
     * subject not already plotted, and un-offers (removes) every unchecked
     * subject that has no enrollments against it yet. The authoritative
     * subject list is re-derived server-side from the curriculum — never
     * trusted from the client — so a tampered request can't plot/remove a
     * subject outside the chair's own program.
     */
    public function saveOfferings()
    {
        $userId    = (int) auth()->id();
        $programId = (int) $this->request->getPost('program_id');
        $yearLevel = (int) $this->request->getPost('year_level');
        $section   = trim((string) $this->request->getPost('section'));
        $checked   = (array) $this->request->getPost('subjects');

        $programIds = array_column($this->authorizedPrograms($userId), 'program_id');

        if (! in_array($programId, $programIds, true)) {
            return redirect()->back()->with('errors', ['That program is not yours to manage.']);
        }

        if ($yearLevel < 1 || $yearLevel > 4 || $section === '') {
            return redirect()->back()->with('errors', ['Choose a year level and section first.']);
        }

        $ay = $this->resolveTerm((new AcademicYearModel())->upcomingTerms(), (int) $this->request->getPost('ay_id'));

        if ($ay === null) {
            return redirect()->back()->with('errors', ['No upcoming academic term exists yet.']);
        }

        $academicYear = AcademicYearModel::format($ay['label'], $ay['semester']);
        $programName  = DepartmentModel::programNameById($programId);

        if ($programName === null) {
            return redirect()->back()->with('errors', ['Unknown program.']);
        }

        $sectionRow = (new SectionModel())->firstOrCreateForContext(AcademicYearModel::format($ay['label']), $programName, self::YEAR_LEVEL_LABELS[$yearLevel], $section);

        $subjects = $this->offerableSubjects($programId, $yearLevel, $ay, $section);

        $offered = [];
        $removed = [];
        $skipped = [];
        $slot    = 0;

        $db = db_connect();

        foreach ($subjects as $subject) {
            $isChecked = in_array($subject['code'], $checked, true);

            if ($isChecked && ! $subject['is_offered']) {
                $this->plotPlaceholder($academicYear, $programName, self::YEAR_LEVEL_LABELS[$yearLevel], $section, $subject['code'], $subject['title'], $slot);
                $slot++;
                $offered[] = $subject['code'];
            } elseif (! $isChecked && $subject['is_offered']) {
                $hasEnrollments = $db->table('enrollments e')
                    ->where('e.section_id', (int) $sectionRow['section_id'])
                    ->where('e.subject_code', $subject['code'])
                    ->where('e.status', 'enrolled')
                    ->countAllResults() > 0;

                if ($hasEnrollments) {
                    $skipped[] = $subject['code'];
                    continue;
                }

                $db->table('plotted_schedules')
                    ->where('academic_year', $academicYear)
                    ->where('program', $programName)
                    ->where('year_level', self::YEAR_LEVEL_LABELS[$yearLevel])
                    ->where('section', $section)
                    ->where('subject_code', $subject['code'])
                    ->delete();

                $removed[] = $subject['code'];
            }
        }

        $parts = [];
        if ($offered !== []) $parts[] = 'Offered: ' . implode(', ', $offered);
        if ($removed !== []) $parts[] = 'Removed: ' . implode(', ', $removed);
        if ($skipped !== []) $parts[] = 'Could not remove (students already enrolled): ' . implode(', ', $skipped);

        $redirectUrl = '/chairperson/enlistment-window?' . http_build_query([
            'ay_id'      => (int) $ay['ay_id'],
            'program_id' => $programId,
            'year_level' => $yearLevel,
            'section'    => $section,
        ]);

        return redirect()->to($redirectUrl)->with('message', $parts === [] ? 'No changes.' : implode('. ', $parts) . '.');
    }

    /**
     * Every curriculum subject for this program/year-level/semester, each
     * flagged with whether it's already plotted for the given section —
     * the single source of truth both edit() (for display) and
     * saveOfferings() (for what's allowed to change) resolve independently
     * rather than trusting anything the client sends back.
     *
     * @return list<array{code: string, title: string, units: float, is_offered: bool}>
     */
    private function offerableSubjects(int $programId, int $yearLevel, array $ay, string $section): array
    {
        $curriculum = (new CurriculumModel())->liveFor($programId);
        $semCode    = self::SEMESTER_CODES[$ay['semester']] ?? null;

        if ($semCode === null) {
            return [];
        }

        $rows = db_connect()->table('curriculum_subjects cs')
            ->select('s.subject_code, s.subject_name, s.units')
            ->join('subjects s', 's.subject_id = cs.subject_id')
            ->where('cs.curriculum_id', $curriculum['curriculum_id'])
            ->where('cs.year_level', $yearLevel)
            ->where('cs.semester', $semCode)
            ->where('cs.is_active', 1)
            ->orderBy('s.subject_code')
            ->get()
            ->getResultArray();

        if ($rows === []) {
            return [];
        }

        $academicYear = AcademicYearModel::format($ay['label'], $ay['semester']);
        $programName  = DepartmentModel::programNameById($programId);

        $offeredCodes = array_column(
            db_connect()->table('plotted_schedules')
                ->select('subject_code')
                ->where('academic_year', $academicYear)
                ->where('program', $programName)
                ->where('year_level', self::YEAR_LEVEL_LABELS[$yearLevel])
                ->where('section', $section)
                ->distinct()
                ->get()
                ->getResultArray(),
            'subject_code'
        );

        return array_map(static fn (array $r): array => [
            'code'       => $r['subject_code'],
            'title'      => $r['subject_name'],
            'units'      => (float) $r['units'],
            'is_offered' => in_array($r['subject_code'], $offeredCodes, true),
        ], $rows);
    }

    /**
     * One auto-assigned weekly meeting pair (same day-of-week twice,
     * cycling through TIME_SLOTS/DAY_PAIRS by $slotIndex) — room and
     * instructor left unset ("to be assigned"), exactly like any other
     * freshly-plotted class; nothing about this marks it as different from
     * a row the chair plotted by hand via Schedule Plotting.
     */
    private function plotPlaceholder(string $academicYear, string $program, string $yearLevel, string $section, string $subjectCode, string $subjectTitle, int $slotIndex): void
    {
        $timeSlot = self::TIME_SLOTS[$slotIndex % count(self::TIME_SLOTS)];
        $days     = self::DAY_PAIRS[intdiv($slotIndex, count(self::TIME_SLOTS)) % count(self::DAY_PAIRS)];
        $label    = $this->formatTimeLabel($timeSlot[0], $timeSlot[1]);

        $db = db_connect();

        foreach ($days as $day) {
            $db->table('plotted_schedules')->insert([
                'academic_year' => $academicYear,
                'program'       => $program,
                'year_level'    => $yearLevel,
                'section'       => $section,
                'day'           => $day,
                'start_time'    => $timeSlot[0],
                'end_time'      => $timeSlot[1],
                'time_label'    => $label,
                'subject_code'  => $subjectCode,
                'subject_title' => $subjectTitle,
                'prof'          => null,
                'room'          => null,
                'theme'         => 'blue',
                'status'        => 'published',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function formatTimeLabel(string $start, string $end): string
    {
        return date('g:i A', strtotime($start)) . ' - ' . date('g:i A', strtotime($end));
    }

    /**
     * Section names that already exist for this program/year-level/term —
     * what the Section dropdown lists. New sections are created from
     * Schedule Plotting (SectionModel::firstOrCreateForContext()'s other
     * caller), not here — this tool only toggles offerings for sections
     * that already exist.
     *
     * @return list<string>
     */
    private function sectionsFor(int $programId, string $yearLevel, array $ay): array
    {
        return array_column(
            db_connect()->table('sections')
                ->select('section')
                ->where('academic_year', AcademicYearModel::format($ay['label']))
                ->where('program_id', $programId)
                ->where('year_level', $yearLevel)
                ->orderBy('section')
                ->get()
                ->getResultArray(),
            'section'
        );
    }

    /**
     * Picks $ayId out of $terms (the chair's explicit choice), falling back
     * to the soonest term when nothing matches — so a stale/missing ay_id
     * in the query string degrades to "the nearest upcoming term" instead
     * of silently resolving to nothing.
     */
    private function resolveTerm(array $terms, int $ayId): ?array
    {
        foreach ($terms as $term) {
            if ((int) $term['ay_id'] === $ayId) {
                return $term;
            }
        }

        return $terms[0] ?? null;
    }

    /**
     * @return list<array{program_id: int, program_code: string, program_name: string}>
     */
    private function authorizedPrograms(int $userId): array
    {
        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds($userId);

        if ($subDeptIds === []) {
            return [];
        }

        $rows = db_connect()->table('programs')
            ->select('program_id, program_code, program_name')
            ->whereIn('sub_department_id', $subDeptIds)
            ->where('is_active', 1)
            ->orderBy('program_name')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['program_id'] = (int) $row['program_id'];
        }
        unset($row);

        return $rows;
    }
}

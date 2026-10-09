<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Libraries\SchedulingAuthorization;
use App\Libraries\SubjectDemandService;
use App\Models\AcademicYearModel;
use App\Models\CourseOfferingModel;
use App\Models\OfferingComponentModel;
use App\Models\PlottedScheduleModel;
use App\Models\RoomModel;
use App\Models\SectionModel;

/**
 * Section Merging (see the SIS development roadmap's "Sectioning &
 * Merging" deep-dive). Kept as its own controller — ChairpersonController
 * is already 2256 lines and flagged in the roadmap as the highest-risk file
 * for regressions; new functionality goes here instead of growing it further.
 */
class OfferingController extends BaseController
{
    public function index()
    {
        // Every department this chairperson is authorized for (home +
        // delegated). subDeptOfUser() alone would silently drop a delegated
        // department's sections/merge-candidates from this whole page.
        $subDeptIds    = (new SchedulingAuthorization())->authorizedSubDeptIds((int) auth()->id());
        $needsPlotting = $this->needsPlottingList();

        if ($subDeptIds === []) {
            return view('chairperson/offerings', [
                'pageTitle'     => 'Merged Classes',
                'user'          => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
                'deptName'      => null,
                'candidates'    => [],
                'offerings'     => [],
                'rooms'         => [],
                'needsPlotting' => $needsPlotting,
            ]);
        }

        $deptRows  = db_connect()->table('sub_departments')->whereIn('sub_dept_id', $subDeptIds)->orderBy('name')->get()->getResultArray();
        $deptNames = array_column($deptRows, 'name');

        $sectionModel = new SectionModel();
        $sections     = [];

        foreach (array_column($deptRows, 'code') as $code) {
            $sections = array_merge($sections, $sectionModel->forSubDepartment($code));
        }

        $sections  = array_values(array_column($sections, null, 'section_id'));
        $offeringM = new CourseOfferingModel();

        // Mergeable candidates: a subject_code plotted for 2+ of this
        // department's sections in the same academic year, where none of
        // those sections is already merged for that subject.
        $bySubject = [];
        foreach ($sections as $section) {
            $plottedCodes = array_column(
                db_connect()->table('plotted_schedules')
                    ->select('subject_code')
                    ->where('academic_year', $section['academic_year'])
                    ->where('program', $section['program'])
                    ->where('year_level', $section['year_level'])
                    ->where('section', $section['section'])
                    ->distinct()
                    ->get()
                    ->getResultArray(),
                'subject_code'
            );

            foreach ($plottedCodes as $code) {
                $bySubject[$section['academic_year']][$code][] = $section;
            }
        }

        $candidates = [];

        foreach ($bySubject as $academicYear => $subjects) {
            foreach ($subjects as $code => $secList) {
                if (count($secList) < 2) {
                    continue;
                }

                // Merging is the subject OWNER's call (see
                // authorizeSubjectCode()) — a department only ever sees
                // merge candidates for subjects it actually owns, never for
                // a borrowed subject it merely plots sections against.
                if (! $this->authorizeSubjectCode($code)) {
                    continue;
                }

                $unmerged = array_values(array_filter(
                    $secList,
                    static fn (array $s) => $offeringM->offeringForSection((int) $s['section_id'], $code) === null
                ));

                if (count($unmerged) >= 2) {
                    $candidates[] = [
                        'academic_year' => $academicYear,
                        'subject_code'  => $code,
                        'sections'      => $unmerged,
                    ];
                }
            }
        }

        // Existing offerings for this department's sections, with roster
        // counts and their attached section list.
        $sectionIds  = array_column($sections, 'section_id');
        $offeringIds = $sectionIds === [] ? [] : array_unique(array_column(
            db_connect()->table('offering_sections')->select('offering_id')->whereIn('section_id', $sectionIds)->get()->getResultArray(),
            'offering_id'
        ));

        $offerings      = [];
        $componentModel = new OfferingComponentModel();

        foreach ($offeringIds as $offeringId) {
            $offering                  = $offeringM->find((int) $offeringId);
            $offering['section_ids']   = $offeringM->sectionIdsFor((int) $offeringId);
            $offering['sections']      = array_values(array_filter($sections, static fn (array $s) => in_array((int) $s['section_id'], $offering['section_ids'], true)));
            $offering['roster_count']  = $offeringM->rosterCount((int) $offeringId);
            $offering['meetings']      = (new PlottedScheduleModel())->where('offering_id', (int) $offeringId)->where('offering_component_id IS NULL')->findAll();

            $components = $componentModel->activeForOffering((int) $offeringId);

            foreach ($components as &$c) {
                $c['meetings'] = (new PlottedScheduleModel())->forComponent((int) $c['id']);
                $c['members']  = $c['roster_mode'] === 'explicit' ? $componentModel->explicitMemberIds((int) $c['id']) : [];
            }
            unset($c);

            $offering['components']   = $components;
            $offering['completeness'] = $components !== [] ? $componentModel->completeness((int) $offeringId) : null;

            // A borrowing department sees this offering (its own section is
            // attached) but may only manage it when it also owns the
            // subject — see authorizeOffering(). The view uses this to show
            // the merge/schedule/roster controls as read-only otherwise.
            $offering['can_manage']     = $this->authorizeOffering((int) $offeringId);
            $offering['owner_dept_name'] = null;

            if (! $offering['can_manage'] && $offering['subject_id'] !== null) {
                $ownerSubDeptId = db_connect()->table('subjects')->select('sub_department_id')->where('subject_id', $offering['subject_id'])->get()->getRowArray()['sub_department_id'] ?? null;
                $offering['owner_dept_name'] = $ownerSubDeptId === null ? null : (db_connect()->table('sub_departments')->select('name')->where('sub_dept_id', $ownerSubDeptId)->get()->getRowArray()['name'] ?? null);
            }

            $offerings[] = $offering;
        }

        $rooms = (new RoomModel())->where('is_active', 1)->orderBy('name')->findAll();

        return view('chairperson/offerings', [
            'pageTitle'     => 'Merged Classes',
            'user'          => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => (count($deptNames) === 1 ? $deptNames[0] . ' - ' : '') . 'Chairperson'],
            'deptName'      => count($deptNames) === 1 ? $deptNames[0] : implode(', ', $deptNames),
            'candidates'    => $candidates,
            'offerings'     => $offerings,
            'rooms'         => $rooms,
            'needsPlotting' => $needsPlotting,
        ]);
    }

    /**
     * Every (subject, section) pair that needs ONE of my owned subjects
     * plotted, but has no offering yet — computed live from current demand
     * (SubjectDemandService, itself computed live from students/curriculum/
     * enrollments, nothing cached) for the active academic year. This is
     * the subject-owner's real worklist: a borrowing department's section
     * shows up here the moment its curriculum requires my subject, with no
     * placement request needed first.
     *
     * @return list<array{subject_id: int, subject_code: string, subject_name: string, section_id: int, section_label: string, expected_count: int}>
     */
    private function needsPlottingList(): array
    {
        $activeYear = (new AcademicYearModel())->activeFormatted();

        if ($activeYear === null) {
            return [];
        }

        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds((int) auth()->id());

        if ($subDeptIds === []) {
            return [];
        }

        $ownedSubjects = db_connect()->table('subjects')
            ->select('subject_id, subject_code, subject_name')
            ->whereIn('sub_department_id', $subDeptIds)
            ->where('is_active', 1)
            ->orderBy('subject_code')
            ->get()
            ->getResultArray();

        if ($ownedSubjects === []) {
            return [];
        }

        $demandService = new SubjectDemandService();
        $offeringModel = new CourseOfferingModel();
        $needsPlotting = [];

        foreach ($ownedSubjects as $subject) {
            $summary = $demandService->summary((int) $subject['subject_id'], $activeYear);

            foreach ($summary['by_section'] as $bySection) {
                if ($bySection['expected_count'] <= 0 || $bySection['section_id'] === null) {
                    continue;
                }

                // Already has an offering for this subject — either already
                // scheduled or waiting in the "existing offerings" list
                // below, not "needs plotting" from scratch.
                if ($offeringModel->offeringForSection((int) $bySection['section_id'], $subject['subject_code']) !== null) {
                    continue;
                }

                $needsPlotting[] = [
                    'academic_year'  => $activeYear,
                    'subject_id'     => (int) $subject['subject_id'],
                    'subject_code'   => $subject['subject_code'],
                    'subject_name'   => $subject['subject_name'],
                    'section_id'     => (int) $bySection['section_id'],
                    'section_label'  => $bySection['section_label'],
                    'expected_count' => (int) $bySection['expected_count'],
                ];
            }
        }

        return $needsPlotting;
    }

    /**
     * Creates a brand-new, single-section offering directly from the
     * "needs plotting" picker — the single-section counterpart to create()
     * (which requires 2+ sections, the "Merge Selected" UI's own meaning).
     * Subject ownership is still required (authorizeSubjectCode()); unlike
     * create(), there is no section-ownership check, since the whole point
     * is plotting another department's borrowing section.
     */
    public function plotDemand()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['academic_year']) || empty($payload['subject_code']) || empty($payload['section_id'])) {
            return $this->fail('Missing academic_year, subject_code, or section_id.');
        }

        if (! $this->authorizeSubjectCode((string) $payload['subject_code'])) {
            return $this->fail("{$payload['subject_code']} is owned by another department.");
        }

        $result = (new CourseOfferingModel())->createMerge(
            (string) $payload['academic_year'],
            (string) $payload['subject_code'],
            [(int) $payload['section_id']],
            null,
            1
        );

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON(['ok' => true, 'offering_id' => $result['offering_id']]);
    }

    public function create()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['academic_year']) || empty($payload['subject_code']) || empty($payload['section_ids']) || ! is_array($payload['section_ids'])) {
            return $this->fail('Missing academic_year, subject_code, or section_ids.');
        }

        $sectionIds = array_map('intval', $payload['section_ids']);

        foreach ($sectionIds as $sid) {
            if ($this->ownSection($sid) === null) {
                return $this->fail('One of the selected sections does not belong to your department.');
            }
        }

        // Merging sections into an offering is a delivery decision (it's
        // what the subject's owning department plots against) — only that
        // department may bring the offering into existence, even though the
        // merged sections themselves belong to borrowing departments.
        if (! $this->authorizeSubjectCode((string) $payload['subject_code'])) {
            return $this->fail("{$payload['subject_code']} is owned by another department — only that department can create or merge offerings for it. Submit a Shared Subject Request instead.");
        }

        $capacity = (isset($payload['capacity']) && $payload['capacity'] !== '' && $payload['capacity'] !== null)
            ? (int) $payload['capacity']
            : null;

        $result = (new CourseOfferingModel())->createMerge($payload['academic_year'], $payload['subject_code'], $sectionIds, $capacity);

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON([
            'ok'            => true,
            'offering_id'   => $result['offering_id'],
            'roster_count'  => $result['roster_count'],
            'over_capacity' => $result['over_capacity'],
        ]);
    }

    public function unmergeSection()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['offering_id']) || empty($payload['section_id'])) {
            return $this->fail('Missing offering or section.');
        }

        if ($this->ownSection((int) $payload['section_id']) === null) {
            return $this->fail('That section does not belong to your department.');
        }

        if (! $this->authorizeOffering((int) $payload['offering_id'])) {
            return $this->fail('Only the subject\'s owning department can change this offering\'s section membership.');
        }

        $result = (new CourseOfferingModel())->unmergeSection((int) $payload['offering_id'], (int) $payload['section_id']);

        if (! $result['ok']) {
            return $this->fail($result['error'] ?? 'Could not unmerge.');
        }

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Current schedule state for one offering: its meetings, attached
     * sections (for display), subject, and roster/capacity.
     */
    public function scheduleState()
    {
        $offeringId = (int) ($this->request->getGet('offering_id') ?? 0);

        $offerings = new CourseOfferingModel();
        $offering  = $offerings->find($offeringId);

        if ($offering === null) {
            return $this->fail('Offering not found.');
        }

        $sectionIds = $offerings->sectionIdsFor($offeringId);

        // Viewable by the subject's owning department (who plots it), or by
        // any department with a section attached (a borrower, who needs to
        // see the resulting schedule to know their own students'
        // constraints) — but by no one else.
        $canManageSubject = $offering['subject_id'] !== null
            && (new SchedulingAuthorization())->authorizeManageSubject((int) auth()->id(), (int) $offering['subject_id'])['ok'];
        $ownsAnySection = array_reduce($sectionIds, fn (bool $carry, $sid) => $carry || $this->ownSection((int) $sid) !== null, false);

        if (! $canManageSubject && ! $ownsAnySection) {
            return $this->fail('Not found.');
        }

        $sections = $sectionIds === [] ? [] : (new SectionModel())->whereIn('section_id', $sectionIds)->findAll();

        $subject = $offering['subject_id'] === null ? null : db_connect()->table('subjects')->where('subject_id', $offering['subject_id'])->get()->getRowArray();

        $componentModel = new OfferingComponentModel();
        $components     = $componentModel->activeForOffering($offeringId);

        foreach ($components as &$c) {
            $c['meetings'] = (new PlottedScheduleModel())->forComponent((int) $c['id']);
            $c['members']  = $c['roster_mode'] === 'explicit' ? $componentModel->explicitMemberIds((int) $c['id']) : null;
        }
        unset($c);

        return $this->response->setJSON([
            'ok'           => true,
            'offering'     => $offering,
            'subject'      => $subject,
            'sections'     => $sections,
            'roster_count' => $offerings->rosterCount($offeringId),
            'meetings'     => (new PlottedScheduleModel())->forOffering($offeringId),
            'components'   => $components,
            'completeness' => $componentModel->completeness($offeringId),
        ]);
    }

    /**
     * Replaces one offering component's schedule — see
     * PlottedScheduleModel::syncComponentSchedule() for the student-level
     * conflict-checking details.
     */
    public function syncComponentSchedule()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['offering_component_id']) || ! isset($payload['blocks']) || ! is_array($payload['blocks'])) {
            return $this->fail('Missing offering_component_id or blocks.');
        }

        $blocks = array_map(static function (array $b) {
            return [
                'day'        => (int) ($b['day'] ?? -1),
                'start'      => (string) ($b['start'] ?? ''),
                'end'        => (string) ($b['end'] ?? ''),
                'time_label' => (string) ($b['time_label'] ?? ''),
                'room'       => (string) ($b['room'] ?? ''),
                'faculty_id' => isset($b['faculty_id']) ? (int) $b['faculty_id'] : null,
            ];
        }, $payload['blocks']);

        $result = (new PlottedScheduleModel())->syncComponentSchedule((int) $payload['offering_component_id'], (int) auth()->id(), $blocks);

        if (! $result['ok']) {
            return $this->response->setStatusCode(400)->setJSON(array_merge(['ok' => false], $result));
        }

        return $this->response->setJSON($result);
    }

    /**
     * Adds one student (by canonical users.id) to an explicit-roster
     * component — e.g. placing a student into "Laboratory Group A".
     */
    public function assignComponentStudent()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['offering_component_id']) || empty($payload['user_id'])) {
            return $this->fail('Missing offering_component_id or user_id.');
        }

        if (! $this->authorizeComponent((int) $payload['offering_component_id'])) {
            return $this->fail('Only the subject\'s owning department can manage this component\'s roster.');
        }

        $result = (new OfferingComponentModel())->assignStudent((int) $payload['offering_component_id'], (int) $payload['user_id']);

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    public function removeComponentStudent()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['offering_component_id']) || empty($payload['user_id'])) {
            return $this->fail('Missing offering_component_id or user_id.');
        }

        if (! $this->authorizeComponent((int) $payload['offering_component_id'])) {
            return $this->fail('Only the subject\'s owning department can manage this component\'s roster.');
        }

        (new OfferingComponentModel())->removeStudent((int) $payload['offering_component_id'], (int) $payload['user_id']);

        return $this->response->setJSON(['ok' => true]);
    }

    public function deactivateComponent()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['offering_component_id'])) {
            return $this->fail('Missing offering_component_id.');
        }

        if (! $this->authorizeComponent((int) $payload['offering_component_id'])) {
            return $this->fail('Only the subject\'s owning department can manage this component.');
        }

        $result = (new OfferingComponentModel())->deactivate((int) $payload['offering_component_id'], (int) auth()->id());

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    public function reactivateComponent()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['offering_component_id'])) {
            return $this->fail('Missing offering_component_id.');
        }

        if (! $this->authorizeComponent((int) $payload['offering_component_id'])) {
            return $this->fail('Only the subject\'s owning department can manage this component.');
        }

        $result = (new OfferingComponentModel())->reactivate((int) $payload['offering_component_id'], (int) auth()->id());

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Replaces an offering's entire schedule — see
     * PlottedScheduleModel::syncOfferingSchedule() for validation/conflict
     * details. Authorization is resolved from the offering's subject, not
     * from any of the attached sections or the caller's own department.
     */
    public function syncSchedule()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['offering_id']) || ! isset($payload['blocks']) || ! is_array($payload['blocks'])) {
            return $this->fail('Missing offering_id or blocks.');
        }

        $blocks = array_map(static function (array $b) {
            return [
                'day'        => (int) ($b['day'] ?? -1),
                'start'      => (string) ($b['start'] ?? ''),
                'end'        => (string) ($b['end'] ?? ''),
                'time_label' => (string) ($b['time_label'] ?? ''),
                'room'       => (string) ($b['room'] ?? ''),
                'faculty_id' => isset($b['faculty_id']) ? (int) $b['faculty_id'] : null,
            ];
        }, $payload['blocks']);

        $result = (new PlottedScheduleModel())->syncOfferingSchedule((int) $payload['offering_id'], (int) auth()->id(), $blocks);

        if (! $result['ok']) {
            return $this->response->setStatusCode(400)->setJSON(array_merge(['ok' => false], $result));
        }

        return $this->response->setJSON($result);
    }

    /**
     * The section row, only if it belongs to a sub-department the
     * logged-in user is authorized to schedule for (their own, or one
     * they've been explicitly delegated). Shared with ChairpersonController
     * via SchedulingAuthorization.
     */
    private function ownSection(int $sectionId): ?array
    {
        return (new SchedulingAuthorization())->ownSection((int) auth()->id(), $sectionId);
    }

    /**
     * Whether the logged-in user is authorized to manage DELIVERY of a
     * subject identified by code — i.e. they belong to its owning
     * department (or hold a scheduling override). See
     * SchedulingAuthorization::authorizeManageSubject(); section ownership
     * is a separate, insufficient check for these actions (merging,
     * component rosters, etc. are the owning department's call).
     */
    private function authorizeSubjectCode(string $subjectCode): bool
    {
        $subject = db_connect()->table('subjects')->select('subject_id')->where('subject_code', $subjectCode)->get()->getRowArray();

        if ($subject === null) {
            return false;
        }

        return (new SchedulingAuthorization())->authorizeManageSubject((int) auth()->id(), (int) $subject['subject_id'])['ok'];
    }

    /** Same as authorizeSubjectCode(), resolved from an offering_id. */
    private function authorizeOffering(int $offeringId): bool
    {
        $offering = (new CourseOfferingModel())->find($offeringId);

        if ($offering === null || $offering['subject_id'] === null) {
            return false;
        }

        return (new SchedulingAuthorization())->authorizeManageSubject((int) auth()->id(), (int) $offering['subject_id'])['ok'];
    }

    /** Same as authorizeSubjectCode(), resolved from an offering_component_id. */
    private function authorizeComponent(int $offeringComponentId): bool
    {
        $component = (new OfferingComponentModel())->find($offeringComponentId);

        if ($component === null) {
            return false;
        }

        return $this->authorizeOffering((int) $component['offering_id']);
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

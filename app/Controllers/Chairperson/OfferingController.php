<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Libraries\SchedulingAuthorization;
use App\Models\CourseOfferingModel;
use App\Models\DepartmentModel;
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
        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        if ($subDept === null) {
            return view('chairperson/offerings', [
                'pageTitle'  => 'Merged Classes',
                'user'       => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
                'deptName'   => null,
                'candidates' => [],
                'offerings'  => [],
                'rooms'      => [],
            ]);
        }

        $sections   = (new SectionModel())->forSubDepartment($subDept['code']);
        $offeringM  = new CourseOfferingModel();

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

            $offerings[] = $offering;
        }

        $rooms = (new RoomModel())->where('is_active', 1)->orderBy('name')->findAll();

        return view('chairperson/offerings', [
            'pageTitle'  => 'Merged Classes',
            'user'       => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => $subDept['name'] . ' - Chairperson'],
            'deptName'   => $subDept['name'],
            'candidates' => $candidates,
            'offerings'  => $offerings,
            'rooms'      => $rooms,
        ]);
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
        $sections   = $sectionIds === [] ? [] : (new SectionModel())->whereIn('section_id', $sectionIds)->findAll();

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

        (new OfferingComponentModel())->removeStudent((int) $payload['offering_component_id'], (int) $payload['user_id']);

        return $this->response->setJSON(['ok' => true]);
    }

    public function deactivateComponent()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['offering_component_id'])) {
            return $this->fail('Missing offering_component_id.');
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

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

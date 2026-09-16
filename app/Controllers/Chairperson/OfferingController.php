<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Models\CourseOfferingModel;
use App\Models\DepartmentModel;
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

        $offerings = [];

        foreach ($offeringIds as $offeringId) {
            $offering                  = $offeringM->find((int) $offeringId);
            $offering['section_ids']   = $offeringM->sectionIdsFor((int) $offeringId);
            $offering['sections']      = array_values(array_filter($sections, static fn (array $s) => in_array((int) $s['section_id'], $offering['section_ids'], true)));
            $offering['roster_count']  = $offeringM->rosterCount((int) $offeringId);
            $offerings[]                = $offering;
        }

        return view('chairperson/offerings', [
            'pageTitle'  => 'Merged Classes',
            'user'       => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => $subDept['name'] . ' - Chairperson'],
            'deptName'   => $subDept['name'],
            'candidates' => $candidates,
            'offerings'  => $offerings,
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
     * The section row, only if it belongs to the logged-in chairperson's
     * own department — mirrors ChairpersonController::ownSection() (kept
     * duplicated rather than extracted, since that method is private on a
     * controller this one deliberately doesn't depend on).
     */
    private function ownSection(int $sectionId): ?array
    {
        $subDept = (new DepartmentModel())->subDeptOfUser((int) auth()->id());

        if ($subDept === null) {
            return null;
        }

        $section = (new SectionModel())->find($sectionId);

        if ($section === null) {
            return null;
        }

        $programs = (new DepartmentModel())->programsOfSubDept((int) $subDept['sub_dept_id']);

        return in_array($section['program'], $programs, true) ? $section : null;
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

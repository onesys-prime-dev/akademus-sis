<?php

namespace App\Controllers\Dean;

use App\Controllers\BaseController;
use App\Libraries\DeanPortal;
use App\Libraries\SchedulingAuthorization;
use App\Models\GradeChangeRequestModel;
use App\Models\GradeModel;

/**
 * Dean's step in the 5-step grade correction chain — chair_recommended ->
 * dean_recommended (or rejected). Mirrors
 * Chairperson\GradeApprovalController's change-request methods, scoped by
 * college instead of sub-department.
 */
class GradeChangeApprovalController extends BaseController
{
    public function index()
    {
        $collegeIds = (new SchedulingAuthorization())->authorizedDepartmentIds((int) auth()->id());
        $programs   = DeanController::programsOfColleges($collegeIds);

        return view('dean/grade_change_requests', [
            'pageTitle' => 'Grade Correction Requests',
            'requests'  => $programs === [] ? [] : (new GradeChangeRequestModel())->pendingDeanRecommendation($programs),
        ] + DeanPortal::shell('grade-change-requests'));
    }

    public function recommend()
    {
        return $this->decide('recommend');
    }

    public function reject()
    {
        return $this->decide('reject');
    }

    private function decide(string $decision)
    {
        $deanId  = (int) auth()->id();
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['request_id'])) {
            return $this->fail('Missing request.');
        }

        $requestId = (int) $payload['request_id'];
        $request   = (new GradeChangeRequestModel())->find($requestId);

        if ($request === null) {
            return $this->fail('Request not found.');
        }

        $grade = (new GradeModel())->find((int) $request['grade_id']);

        if ($grade === null || $this->ownSection((int) $grade['section_id']) === null) {
            return $this->fail('That request does not belong to your college.');
        }

        $notes  = isset($payload['notes']) ? (string) $payload['notes'] : null;
        $result = (new GradeChangeRequestModel())->deanRecommend($requestId, $deanId, $decision, $notes);

        if (! $result['ok']) {
            return $this->fail($result['error'] ?? 'Could not process the request.');
        }

        return $this->response->setJSON(['ok' => true]);
    }

    private function ownSection(int $sectionId): ?array
    {
        $section = (new \App\Models\SectionModel())->find($sectionId);

        if ($section === null) {
            return null;
        }

        $collegeIds = (new SchedulingAuthorization())->authorizedDepartmentIds((int) auth()->id());
        $programs   = DeanController::programsOfColleges($collegeIds);

        return in_array($section['program'], $programs, true) ? $section : null;
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

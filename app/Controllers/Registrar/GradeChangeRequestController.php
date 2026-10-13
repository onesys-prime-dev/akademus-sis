<?php

namespace App\Controllers\Registrar;

use App\Controllers\BaseController;
use App\Libraries\RegistrarPortal;
use App\Models\GradeChangeRequestModel;
use App\Models\GradeModel;

/**
 * Registrar's two jobs in the 5-step grade correction chain: open a new
 * correction window against a locked grade (issue), and give final
 * approval once the dean has recommended (finalApprove) — the only step
 * that actually mutates the locked grade's value.
 */
class GradeChangeRequestController extends BaseController
{
    public function index()
    {
        return view('registrar/grade_change_requests', [
            'pageTitle' => 'Grade Correction Requests',
            'requests'  => (new GradeChangeRequestModel())->pendingRegistrarFinal(),
        ] + RegistrarPortal::shell('grade-change-requests'));
    }

    /**
     * Type-ahead search over locked grades, backing the "issue a
     * correction form" search box.
     */
    public function search()
    {
        $query = (string) $this->request->getGet('q');

        return $this->response->setJSON(['ok' => true, 'results' => (new GradeModel())->searchLockedGrades($query)]);
    }

    public function issue()
    {
        $registrarId = (int) auth()->id();
        $payload     = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['grade_id'])) {
            return $this->fail('Select a grade to issue a correction form against.');
        }

        $result = (new GradeChangeRequestModel())->issueForm((int) $payload['grade_id'], $registrarId);

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON(['ok' => true, 'request_id' => $result['request_id']]);
    }

    public function finalApprove()
    {
        $registrarId = (int) auth()->id();
        $payload     = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['request_id'])) {
            return $this->fail('Missing request.');
        }

        $notes  = isset($payload['notes']) ? (string) $payload['notes'] : null;
        $result = (new GradeChangeRequestModel())->finalApprove((int) $payload['request_id'], $registrarId, $notes);

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

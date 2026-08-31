<?php

namespace App\Controllers\Faculty;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;
use App\Models\FacultyAssignmentModel;
use App\Models\FacultyChangeRequestModel;
use App\Models\NotificationModel;

/**
 * A faculty member's "Request for Change" on an official assignment —
 * availability conflicts, schedule conflicts, or other valid reasons they
 * can't accept a chairperson-plotted subject. One-directional: unlike a
 * swap, there's no counterpart faculty member who has to agree.
 */
class ChangeRequestController extends BaseController
{
    public function store()
    {
        $userId  = (int) auth()->id();
        $payload = $this->request->getJSON(true);

        $assignmentId = (int) ($payload['assignment_id'] ?? 0);
        $reason       = trim((string) ($payload['reason'] ?? ''));
        $proposed     = trim((string) ($payload['proposed_alternative'] ?? ''));

        if ($reason === '' || mb_strlen($reason) < 5) {
            return $this->fail('Please explain why you cannot accept this assignment.');
        }

        $assignments = new FacultyAssignmentModel();
        $assignment  = $assignments->find($assignmentId);

        if ($assignment === null || (int) $assignment['user_id'] !== $userId) {
            return $this->fail('You do not hold this assignment.');
        }

        $requests = new FacultyChangeRequestModel();

        if ($requests->hasOpenRequest($assignmentId)) {
            return $this->fail('You already have an open change request for this assignment.');
        }

        $requests->insert([
            'assignment_id'         => $assignmentId,
            'faculty_id'            => $userId,
            'reason'                => $reason,
            'proposed_alternative'  => $proposed !== '' ? $proposed : null,
            'status'                => 'pending',
        ]);

        $this->notifyChairpersons($userId, $assignment['subject_code']);

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Notify every chairperson of the requesting faculty's own department —
     * normally just one, but nothing here assumes exactly one.
     */
    private function notifyChairpersons(int $facultyId, string $subjectCode): void
    {
        $subDept = (new DepartmentModel())->subDeptOfUser($facultyId);

        if ($subDept === null) {
            return;
        }

        $chairpersonIds = db_connect()->table('users u')
            ->select('u.id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'chairperson')
            ->where('u.sub_department_id', $subDept['sub_dept_id'])
            ->where('u.deleted_at', null)
            ->get()
            ->getResultArray();

        $notifications = new NotificationModel();
        $facultyName   = auth()->user()->username ?? 'A faculty member';

        foreach ($chairpersonIds as $row) {
            $notifications->notify(
                (int) $row['id'],
                'change_request_submitted',
                "{$facultyName} submitted a change request for {$subjectCode}.",
                site_url('chairperson/dashboard')
            );
        }
    }

    /**
     * Withdraw my own request while it's still open (chairperson hasn't
     * decided yet).
     */
    public function cancel()
    {
        $userId  = (int) auth()->id();
        $payload = $this->request->getJSON(true);

        $requestId = (int) ($payload['request_id'] ?? 0);

        $requests = new FacultyChangeRequestModel();
        $request  = $requests->find($requestId);

        if ($request === null || (int) $request['faculty_id'] !== $userId) {
            return $this->fail('Request not found.');
        }

        if (! in_array($request['status'], ['pending', 'under_review'], true)) {
            return $this->fail('This request has already been decided.');
        }

        $requests->update($requestId, ['status' => 'cancelled']);

        return $this->response->setJSON(['ok' => true]);
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}

<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Libraries\SchedulingAuthorization;
use App\Models\NotificationModel;
use App\Models\OfferingPlacementRequestModel;
use App\Models\SubDepartmentSchedulerModel;

/**
 * The shared-subject request/placement workflow (see the SIS shared-subject
 * architecture work, Step 3). A non-owning scheduler requests placement of
 * their section into a subject another unit owns; the owning unit's
 * scheduler decides. Kept as its own controller, matching
 * Chairperson\OfferingController's precedent.
 *
 * Business logic, authorization, and audit logging all live on
 * OfferingPlacementRequestModel — this controller only turns payloads into
 * model calls, and model results into notifications/responses.
 */
class PlacementRequestController extends BaseController
{
    public function index()
    {
        $userId = (int) auth()->id();
        $auth   = new SchedulingAuthorization();

        // Joined so the view can show real subject/section/department names
        // instead of raw IDs — left joins so an orphaned reference (an
        // edge case, never expected in practice) still renders the request
        // row instead of silently dropping it.
        $mine = db_connect()->table('offering_placement_requests opr')
            ->select('opr.*, s.subject_code, s.subject_name, sec.program AS section_program, sec.year_level AS section_year_level, sec.section AS section_label')
            ->join('subjects s', 's.subject_id = opr.subject_id', 'left')
            ->join('sections sec', 'sec.section_id = opr.section_id', 'left')
            ->where('opr.requested_by', $userId)
            ->orderBy('opr.created_at', 'DESC')
            ->get()
            ->getResultArray();

        $manageableSubDeptIds = $auth->authorizedSubDeptIds($userId);

        $toReview = $manageableSubDeptIds === [] ? [] : db_connect()->table('offering_placement_requests opr')
            ->select('opr.*, s.subject_code, s.subject_name, sec.program AS section_program, sec.year_level AS section_year_level, sec.section AS section_label, sd.name AS requesting_dept_name')
            ->join('subjects s', 's.subject_id = opr.subject_id', 'left')
            ->join('sections sec', 'sec.section_id = opr.section_id', 'left')
            ->join('sub_departments sd', 'sd.sub_dept_id = opr.requesting_sub_dept_id', 'left')
            ->whereIn('s.sub_department_id', $manageableSubDeptIds)
            ->where('opr.status', 'pending')
            ->orderBy('opr.created_at', 'ASC')
            ->get()
            ->getResultArray();

        return view('chairperson/placement_requests', [
            'pageTitle' => 'Shared Subject Requests',
            'user'      => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
            'mine'      => $mine,
            'toReview'  => $toReview,
        ]);
    }

    public function create()
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->fail('Invalid request body.');
        }

        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id'         => (int) ($payload['subject_id'] ?? 0),
            'section_id'         => (int) ($payload['section_id'] ?? 0),
            'requested_by'       => (int) auth()->id(),
            'request_type'       => $payload['request_type'] ?? '',
            'target_offering_id' => $payload['target_offering_id'] ?? null,
            'notes'              => $payload['notes'] ?? null,
        ]);

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        $this->notifyOwningSchedulers(
            (int) $payload['subject_id'],
            'A new shared-subject placement request was submitted.',
            'placement_request_submitted'
        );

        return $this->response->setJSON(['ok' => true, 'id' => $result['id']]);
    }

    public function cancel()
    {
        $payload = $this->request->getJSON(true);
        $id      = (int) ($payload['id'] ?? 0);

        $result = (new OfferingPlacementRequestModel())->cancel($id, (int) auth()->id());

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    public function approve()
    {
        return $this->decide('approve');
    }

    public function deny()
    {
        return $this->decide('deny');
    }

    public function fulfill()
    {
        $payload    = $this->request->getJSON(true);
        $id         = (int) ($payload['id'] ?? 0);
        $reviewerId = (int) auth()->id();

        $requests = new OfferingPlacementRequestModel();
        $request  = $requests->find($id);

        if ($request === null) {
            return $this->fail('Request not found.');
        }

        if ($request['request_type'] === OfferingPlacementRequestModel::TYPE_PLACEMENT) {
            $result = $requests->fulfillPlacement(
                $id,
                $reviewerId,
                isset($payload['target_offering_id']) ? (int) $payload['target_offering_id'] : null,
                (bool) ($payload['override_capacity'] ?? false),
                $payload['override_reason'] ?? null,
                $payload['review_notes'] ?? null
            );
        } else {
            $result = $requests->fulfillNonPlacement($id, $reviewerId, $payload['review_notes'] ?? null);
        }

        if (! $result['ok']) {
            return $this->fail($result['error'] ?? 'Could not fulfill the request.', $result);
        }

        $this->notifyRequester($request, 'fulfilled');

        return $this->response->setJSON(array_merge(['ok' => true], $result));
    }

    private function decide(string $action)
    {
        $payload    = $this->request->getJSON(true);
        $id         = (int) ($payload['id'] ?? 0);
        $reviewerId = (int) auth()->id();

        $requests = new OfferingPlacementRequestModel();
        $request  = $requests->find($id);

        if ($request === null) {
            return $this->fail('Request not found.');
        }

        $result = $action === 'approve'
            ? $requests->approve($id, $reviewerId, $payload['review_notes'] ?? null)
            : $requests->deny($id, $reviewerId, $payload['review_notes'] ?? null);

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        $status = $action === 'approve' ? 'approved' : 'denied';

        $this->notifyRequester($request, $status);

        return $this->response->setJSON(['ok' => true, 'status' => $status]);
    }

    /**
     * Every chairperson belonging to the subject's owning sub-department,
     * plus any scheduler explicitly delegated that sub-department — mirrors
     * Faculty\ChangeRequestController::notifyChairpersons().
     */
    private function notifyOwningSchedulers(int $subjectId, string $message, string $type): void
    {
        $subject   = db_connect()->table('subjects')->select('sub_department_id')->where('subject_id', $subjectId)->get()->getRowArray();
        $subDeptId = $subject['sub_department_id'] ?? null;

        if ($subDeptId === null) {
            return;
        }

        $delegatedIds = array_column((new SubDepartmentSchedulerModel())->where('sub_dept_id', $subDeptId)->findAll(), 'user_id');

        $chairpersonIds = array_column(
            db_connect()->table('users u')
                ->select('u.id')
                ->join('auth_groups_users g', 'g.user_id = u.id')
                ->where('g.group', 'chairperson')
                ->where('u.sub_department_id', $subDeptId)
                ->where('u.deleted_at', null)
                ->get()
                ->getResultArray(),
            'id'
        );

        $notifications = new NotificationModel();

        foreach (array_unique(array_merge($chairpersonIds, $delegatedIds)) as $userId) {
            $notifications->notify((int) $userId, $type, $message, site_url('chairperson/placement-requests'));
        }
    }

    private function notifyRequester(array $request, string $status): void
    {
        (new NotificationModel())->notify(
            (int) $request['requested_by'],
            'placement_request_' . $status,
            "Your shared-subject request was {$status}.",
            site_url('chairperson/placement-requests')
        );
    }

    private function fail(string $message, array $extra = [])
    {
        return $this->response->setStatusCode(400)->setJSON(array_merge(['ok' => false, 'error' => $message], $extra));
    }
}

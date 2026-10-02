<?php

namespace App\Models;

use App\Libraries\SchedulingAuthorization;
use CodeIgniter\Model;

/**
 * The shared-subject placement/request workflow: a non-owning scheduler asks
 * the subject's owning unit to place their section into an offering (or
 * flags they need an additional offering, or reports a scheduling
 * conflict) — they never get direct write access to course_offerings for a
 * subject they don't own. The owning unit's scheduler (or an authorized
 * override, via SchedulingAuthorization::canOverrideScheduling()) decides.
 *
 * Business logic lives here (not in the controller), matching the
 * CourseOfferingModel convention this feature directly extends, rather than
 * the older swap_requests/faculty_change_requests convention of logic living
 * in the controller.
 */
class OfferingPlacementRequestModel extends Model
{
    protected $table      = 'offering_placement_requests';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'subject_id',
        'requesting_sub_dept_id',
        'requested_by',
        'section_id',
        'request_type',
        'target_offering_id',
        'status',
        'notes',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $useTimestamps = true;

    public const TYPE_PLACEMENT           = 'placement';
    public const TYPE_ADDITIONAL_OFFERING = 'additional_offering';
    public const TYPE_CONFLICT_REPORT     = 'conflict_report';

    private const VALID_TYPES = [self::TYPE_PLACEMENT, self::TYPE_ADDITIONAL_OFFERING, self::TYPE_CONFLICT_REPORT];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_DENIED    = 'denied';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Whether an open (pending) request already exists for this exact
     * (subject, section, type) — app-level duplicate prevention, not a DB
     * unique constraint, so a new request is still possible after a prior
     * one was denied/fulfilled/cancelled. Mirrors
     * FacultyChangeRequestModel::hasOpenRequest().
     */
    public function hasPendingRequest(int $subjectId, int $sectionId, string $requestType): bool
    {
        return $this->where('subject_id', $subjectId)
            ->where('section_id', $sectionId)
            ->where('request_type', $requestType)
            ->where('status', self::STATUS_PENDING)
            ->countAllResults() > 0;
    }

    /**
     * Submits a new request. $requestedBy must be authorized (own or
     * delegated) for $sectionId — checked via SchedulingAuthorization,
     * never recreated here.
     *
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function create(array $data): array
    {
        $requestType = $data['request_type'] ?? '';

        if (! in_array($requestType, self::VALID_TYPES, true)) {
            return ['ok' => false, 'error' => 'Invalid request type.'];
        }

        $subjectId = (int) ($data['subject_id'] ?? 0);
        $sectionId = (int) ($data['section_id'] ?? 0);
        $requestedBy = (int) ($data['requested_by'] ?? 0);

        $subject = $this->db->table('subjects')->select('subject_id')->where('subject_id', $subjectId)->get()->getRowArray();

        if ($subject === null) {
            return ['ok' => false, 'error' => 'Subject not found.'];
        }

        $subDeptId = (new SchedulingAuthorization())->subDeptForSection($requestedBy, $sectionId);

        if ($subDeptId === null) {
            return ['ok' => false, 'error' => 'You are not authorized to request on behalf of that section.'];
        }

        $targetOfferingId = isset($data['target_offering_id']) && $data['target_offering_id'] !== null && $data['target_offering_id'] !== ''
            ? (int) $data['target_offering_id']
            : null;

        if ($targetOfferingId !== null) {
            $offering = (new CourseOfferingModel())->find($targetOfferingId);

            if ($offering === null) {
                return ['ok' => false, 'error' => 'Target offering not found.'];
            }

            if ((int) ($offering['subject_id'] ?? 0) !== $subjectId) {
                return ['ok' => false, 'error' => 'The target offering is for a different subject.'];
            }
        }

        if ($this->hasPendingRequest($subjectId, $sectionId, $requestType)) {
            return ['ok' => false, 'error' => 'A pending request already exists for this subject, section, and request type.'];
        }

        $this->insert([
            'subject_id'              => $subjectId,
            'requesting_sub_dept_id'  => $subDeptId,
            'requested_by'            => $requestedBy,
            'section_id'              => $sectionId,
            'request_type'            => $requestType,
            'target_offering_id'      => $targetOfferingId,
            'status'                  => self::STATUS_PENDING,
            'notes'                   => $data['notes'] ?? null,
        ]);

        $id = $this->getInsertID();

        $this->audit('placement_request.created', $id, $requestedBy, null, $this->find($id));

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Cancels the requester's own pending request. Only while pending, and
     * only by the original requester or a user currently authorized for
     * that section (covers a delegated scheduler acting on their behalf).
     *
     * @return array{ok: bool, error?: string}
     */
    public function cancel(int $id, int $userId): array
    {
        $request = $this->find($id);

        if ($request === null) {
            return ['ok' => false, 'error' => 'Request not found.'];
        }

        if ($request['status'] !== self::STATUS_PENDING) {
            return ['ok' => false, 'error' => 'Only a pending request can be cancelled.'];
        }

        $isRequester = (int) $request['requested_by'] === $userId;
        $isSectionScheduler = (new SchedulingAuthorization())->ownSection($userId, (int) $request['section_id']) !== null;

        if (! $isRequester && ! $isSectionScheduler) {
            return ['ok' => false, 'error' => 'You are not authorized to cancel this request.'];
        }

        $this->update($id, ['status' => self::STATUS_CANCELLED]);

        $this->audit('placement_request.cancelled', $id, $userId, $request, $this->find($id));

        return ['ok' => true];
    }

    /**
     * Whether $reviewerId may review (approve/deny/fulfill) $request, and
     * whether that's via normal subject ownership or an explicit override.
     * The single place this decision is made — never recreated inline.
     *
     * @return array{ok: bool, override: bool}
     */
    public function authorizeReview(array $request, int $reviewerId): array
    {
        return (new SchedulingAuthorization())->authorizeManageSubject($reviewerId, (int) $request['subject_id']);
    }

    /**
     * Accepts a request in principle — does NOT attach anything. For
     * placement requests, fulfillPlacement() is the step that actually acts.
     *
     * @return array{ok: bool, error?: string, override?: bool}
     */
    public function approve(int $id, int $reviewerId, ?string $reviewNotes = null): array
    {
        $request = $this->find($id);

        if ($request === null) {
            return ['ok' => false, 'error' => 'Request not found.'];
        }

        if ($request['status'] !== self::STATUS_PENDING) {
            return ['ok' => false, 'error' => 'This request is not pending.'];
        }

        $auth = $this->authorizeReview($request, $reviewerId);

        if (! $auth['ok']) {
            return ['ok' => false, 'error' => 'You are not authorized to review this request.'];
        }

        $this->update($id, [
            'status'       => self::STATUS_APPROVED,
            'reviewed_by'  => $reviewerId,
            'reviewed_at'  => date('Y-m-d H:i:s'),
            'review_notes' => $reviewNotes,
        ]);

        $this->audit('placement_request.approved', $id, $reviewerId, $request, $this->find($id), $auth['override'] ? 'Reviewed via Registrar/Admin override.' : null);

        return ['ok' => true, 'override' => $auth['override']];
    }

    /**
     * @return array{ok: bool, error?: string, override?: bool}
     */
    public function deny(int $id, int $reviewerId, ?string $reviewNotes = null): array
    {
        $request = $this->find($id);

        if ($request === null) {
            return ['ok' => false, 'error' => 'Request not found.'];
        }

        if (! in_array($request['status'], [self::STATUS_PENDING, self::STATUS_APPROVED], true)) {
            return ['ok' => false, 'error' => 'This request is not open.'];
        }

        $auth = $this->authorizeReview($request, $reviewerId);

        if (! $auth['ok']) {
            return ['ok' => false, 'error' => 'You are not authorized to review this request.'];
        }

        $this->update($id, [
            'status'       => self::STATUS_DENIED,
            'reviewed_by'  => $reviewerId,
            'reviewed_at'  => date('Y-m-d H:i:s'),
            'review_notes' => $reviewNotes,
        ]);

        $this->audit('placement_request.denied', $id, $reviewerId, $request, $this->find($id), $auth['override'] ? 'Reviewed via Registrar/Admin override.' : null);

        return ['ok' => true, 'override' => $auth['override']];
    }

    /**
     * Fulfills an `additional_offering` or `conflict_report` request — a
     * manual acknowledgement that the owning scheduler has addressed it
     * outside this system (created an offering, resolved the conflict).
     * Never triggers any automatic action.
     *
     * @return array{ok: bool, error?: string, override?: bool}
     */
    public function fulfillNonPlacement(int $id, int $reviewerId, ?string $reviewNotes = null): array
    {
        $request = $this->find($id);

        if ($request === null) {
            return ['ok' => false, 'error' => 'Request not found.'];
        }

        if ($request['request_type'] === self::TYPE_PLACEMENT) {
            return ['ok' => false, 'error' => 'Placement requests must be fulfilled via fulfillPlacement().'];
        }

        if (! in_array($request['status'], [self::STATUS_PENDING, self::STATUS_APPROVED], true)) {
            return ['ok' => false, 'error' => 'This request is not open.'];
        }

        $auth = $this->authorizeReview($request, $reviewerId);

        if (! $auth['ok']) {
            return ['ok' => false, 'error' => 'You are not authorized to review this request.'];
        }

        $this->update($id, [
            'status'       => self::STATUS_FULFILLED,
            'reviewed_by'  => $reviewerId,
            'reviewed_at'  => date('Y-m-d H:i:s'),
            'review_notes' => $reviewNotes,
        ]);

        $this->audit('placement_request.fulfilled', $id, $reviewerId, $request, $this->find($id), $auth['override'] ? 'Reviewed via Registrar/Admin override.' : null);

        return ['ok' => true, 'override' => $auth['override']];
    }

    /**
     * Fulfills a `placement` request: attaches the requester's section to
     * $targetOfferingId (or the offering already on the request) via
     * CourseOfferingModel::attachSection(), in one transaction with the
     * request's status update — both succeed or both roll back.
     *
     * Capacity overflow is never silently allowed: if attaching would
     * exceed the offering's capacity, this returns requires_override=true
     * and makes no change, unless $overrideCapacity=true AND the reviewer
     * is authorized (owning scheduler or override actor — the same
     * authorizeReview() check already gates the whole method) AND a
     * non-empty $overrideReason is given, for the audit trail.
     *
     * @return array{ok: bool, error?: string, override?: bool, capacity_override?: bool, over_capacity?: bool, offering_id?: int, requires_override?: bool, prospective_roster?: int, capacity?: ?int}
     */
    public function fulfillPlacement(
        int $id,
        int $reviewerId,
        ?int $targetOfferingId = null,
        bool $overrideCapacity = false,
        ?string $overrideReason = null,
        ?string $reviewNotes = null
    ): array {
        $request = $this->find($id);

        if ($request === null) {
            return ['ok' => false, 'error' => 'Request not found.'];
        }

        if ($request['request_type'] !== self::TYPE_PLACEMENT) {
            return ['ok' => false, 'error' => 'Only placement requests can be fulfilled this way.'];
        }

        if (! in_array($request['status'], [self::STATUS_PENDING, self::STATUS_APPROVED], true)) {
            return ['ok' => false, 'error' => 'This request is not open for fulfillment.'];
        }

        $auth = $this->authorizeReview($request, $reviewerId);

        if (! $auth['ok']) {
            return ['ok' => false, 'error' => 'You are not authorized to review this request.'];
        }

        if ($overrideCapacity && trim((string) $overrideReason) === '') {
            return ['ok' => false, 'error' => 'A reason is required to override capacity.'];
        }

        $offeringId = $targetOfferingId ?? ($request['target_offering_id'] !== null ? (int) $request['target_offering_id'] : null);

        if ($offeringId === null) {
            return ['ok' => false, 'error' => 'A target offering must be specified.'];
        }

        $offerings = new CourseOfferingModel();
        $offering  = $offerings->find($offeringId);

        if ($offering === null) {
            return ['ok' => false, 'error' => 'Target offering not found.'];
        }

        if ((int) ($offering['subject_id'] ?? 0) !== (int) $request['subject_id']) {
            return ['ok' => false, 'error' => 'The target offering is for a different subject.'];
        }

        $subject = $this->db->table('subjects')->select('subject_code')->where('subject_id', $request['subject_id'])->get()->getRowArray();

        if ($subject === null) {
            return ['ok' => false, 'error' => 'Subject not found.'];
        }

        $db = $this->db;
        $db->transStart();

        $attach = $offerings->attachSection($offeringId, (int) $request['section_id'], $subject['subject_code'], $overrideCapacity);

        if (! $attach['ok']) {
            $db->transRollback();
            $db->resetTransStatus();

            return array_merge(['ok' => false], $attach);
        }

        $this->update($id, [
            'status'              => self::STATUS_FULFILLED,
            'reviewed_by'         => $reviewerId,
            'reviewed_at'         => date('Y-m-d H:i:s'),
            'review_notes'        => $reviewNotes,
            'target_offering_id'  => $offeringId,
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not fulfill the request. Please retry.'];
        }

        $after = $this->find($id);

        $reasonParts = [];
        if ($auth['override']) {
            $reasonParts[] = 'Reviewed via Registrar/Admin override.';
        }
        if ($overrideCapacity) {
            $reasonParts[] = 'Capacity override: ' . $overrideReason;
        }

        $this->audit('placement_request.fulfilled', $id, $reviewerId, $request, $after, $reasonParts === [] ? null : implode(' ', $reasonParts));

        if ($overrideCapacity) {
            $this->audit('placement_request.capacity_override', $id, $reviewerId, null, [
                'offering_id' => $offeringId,
                'section_id'  => (int) $request['section_id'],
            ], $overrideReason);
        }

        return [
            'ok'                => true,
            'override'          => $auth['override'],
            'capacity_override' => $overrideCapacity,
            'over_capacity'     => $attach['over_capacity'] ?? false,
            'offering_id'       => $offeringId,
        ];
    }

    private function audit(string $action, int $requestId, int $actorId, ?array $before, ?array $after, ?string $reason = null): void
    {
        (new AuditLogModel())->record($action, 'offering_placement_request', $requestId, $before, $after, $reason, $actorId, $this->actorRole($actorId));
    }

    private function actorRole(int $userId): ?string
    {
        $row = $this->db->table('auth_groups_users')->select('group')->where('user_id', $userId)->get()->getRowArray();

        return $row['group'] ?? null;
    }
}

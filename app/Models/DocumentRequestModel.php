<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Request/queue header for official student documents — see
 * CreateDocumentRequests migration.
 */
class DocumentRequestModel extends Model
{
    protected $table      = 'document_requests';
    protected $primaryKey = 'id';

    protected $allowedFields = ['student_id', 'document_type', 'status', 'requested_by', 'purpose', 'released_at', 'released_by'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    public const TYPES = ['tor', 'certificate_enrollment', 'certificate_graduation', 'good_moral', 'certificate_candidacy', 'cor'];

    public const TYPE_LABELS = [
        'tor'                    => 'Transcript of Records',
        'certificate_enrollment' => 'Certificate of Enrollment',
        'certificate_graduation' => 'Certificate of Graduation',
        'good_moral'             => 'Certificate of Good Moral Character',
        'certificate_candidacy'  => 'Certificate of Candidacy for Graduation',
        'cor'                    => 'Certificate of Registration',
    ];

    /**
     * Student-initiated request for any document type (today only exercised
     * for 'cor' — see StudentDocuments::requestCor()). Refuses a duplicate
     * pending request of the same type for the same student, same
     * one-open-case shape as GraduationClearanceModel::initiate().
     *
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function request(int $studentId, string $type, ?string $purpose, int $requestedBy): array
    {
        if (! in_array($type, self::TYPES, true)) {
            return ['ok' => false, 'error' => 'Invalid document type.'];
        }

        $hasPending = $this->where('student_id', $studentId)
            ->where('document_type', $type)
            ->where('status', 'pending')
            ->countAllResults() > 0;

        if ($hasPending) {
            return ['ok' => false, 'error' => 'You already have a pending request for this document.'];
        }

        $this->insert([
            'student_id'    => $studentId,
            'document_type' => $type,
            'status'        => 'pending',
            'requested_by'  => $requestedBy,
            'purpose'       => $purpose,
        ]);

        return ['ok' => true, 'id' => $this->getInsertID()];
    }

    public function pending(): array
    {
        return $this->db->table('document_requests r')
            ->select('r.id, r.document_type, r.status, r.purpose, r.created_at, s.student_id, s.student_number, u.username')
            ->join('students s', 's.student_id = r.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->where('r.status', 'pending')
            ->orderBy('r.created_at', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function forStudent(int $studentId): array
    {
        return $this->where('student_id', $studentId)->orderBy('created_at', 'DESC')->findAll();
    }

    public function markReleased(int $id, int $releasedBy): void
    {
        $this->update($id, [
            'status'      => 'released',
            'released_at' => date('Y-m-d H:i:s'),
            'released_by' => $releasedBy,
        ]);
    }
}

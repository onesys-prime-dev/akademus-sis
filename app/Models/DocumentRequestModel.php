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

    public const TYPES = ['tor', 'certificate_enrollment', 'certificate_graduation', 'good_moral', 'certificate_candidacy'];

    public const TYPE_LABELS = [
        'tor'                    => 'Transcript of Records',
        'certificate_enrollment' => 'Certificate of Enrollment',
        'certificate_graduation' => 'Certificate of Graduation',
        'good_moral'             => 'Certificate of Good Moral Character',
        'certificate_candidacy'  => 'Certificate of Candidacy for Graduation',
    ];

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

    public function markReleased(int $id, int $releasedBy): void
    {
        $this->update($id, [
            'status'      => 'released',
            'released_at' => date('Y-m-d H:i:s'),
            'released_by' => $releasedBy,
        ]);
    }
}

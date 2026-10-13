<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * File evidence attached to a grade change request's faculty response.
 * Storage follows StudentController::uploadProof()'s convention exactly:
 * WRITEPATH . 'uploads/{domain}/{id}/' + a random stored filename, with
 * this row keeping the metadata (original name, mime, size).
 */
class GradeChangeAttachmentModel extends Model
{
    protected $table      = 'grade_change_attachments';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'request_id',
        'uploaded_by',
        'original_filename',
        'stored_filename',
        'mime_type',
        'size_bytes',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    /**
     * Moves an already-validated uploaded file into storage and records it.
     *
     * @param \CodeIgniter\HTTP\Files\UploadedFile $file
     */
    public function storeUploaded(int $requestId, $file, int $uploadedBy): array
    {
        if (! $file->isValid()) {
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
        }

        $targetDir  = WRITEPATH . 'uploads/grade_change_attachments/' . $requestId;
        $storedName = $file->getRandomName();

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $originalName = $file->getClientName();
        $mimeType     = $file->getClientMimeType();
        $sizeBytes    = $file->getSize();

        $file->move($targetDir, $storedName);

        $this->insert([
            'request_id'         => $requestId,
            'uploaded_by'        => $uploadedBy,
            'original_filename'  => $originalName,
            'stored_filename'    => $storedName,
            'mime_type'          => $mimeType,
            'size_bytes'         => $sizeBytes,
        ]);

        return ['ok' => true, 'id' => $this->getInsertID()];
    }

    /**
     * @return list<array>
     */
    public function forRequest(int $requestId): array
    {
        return $this->where('request_id', $requestId)->orderBy('created_at', 'ASC')->findAll();
    }
}

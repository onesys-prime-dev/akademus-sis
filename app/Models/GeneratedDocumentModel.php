<?php

namespace App\Models;

use CodeIgniter\Model;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;

/**
 * The immutable generated PDF artifact + its QR verification code — see
 * CreateGeneratedDocuments migration. Renders a plain-HTML view per
 * document type through dompdf, embeds an endroid/qr-code QR (as a data
 * URI, so no second file to manage) encoding the public verify URL.
 */
class GeneratedDocumentModel extends Model
{
    protected $table      = 'generated_documents';
    protected $primaryKey = 'id';

    protected $allowedFields = ['request_id', 'student_id', 'document_type', 'stored_filename', 'verification_code', 'generated_by', 'generated_at'];

    protected $useTimestamps = false;

    /**
     * $holdOverride (finance.override only) bypasses both the financial
     * hold AND the outstanding-balance check below — unlike enrollment,
     * document release checks EITHER condition, since a document is a
     * one-time release point where "fully paid" is a reasonable bar
     * (ongoing enrollment, which tolerates a normal installment balance,
     * is a different gate — see EnrollmentModel::enrollOne()).
     *
     * @return array{ok: bool, error?: string, id?: int, path?: string}
     */
    public function generate(int $studentId, string $type, int $generatedBy, ?int $requestId = null, bool $holdOverride = false): array
    {
        if (! in_array($type, DocumentRequestModel::TYPES, true)) {
            return ['ok' => false, 'error' => 'Unknown document type.'];
        }

        $student = (new StudentModel())->findDetailed($studentId);

        if ($student === null) {
            return ['ok' => false, 'error' => 'Student not found.'];
        }

        if (! $holdOverride) {
            if ((new FinancialHoldModel())->isOnHold($studentId)) {
                return ['ok' => false, 'error' => 'This student has an active financial hold. Contact the Finance office.'];
            }

            $activeYear = (new AcademicYearModel())->activeFormatted();
            $assessment = $activeYear !== null ? (new TuitionAssessmentModel())->forStudentYear($studentId, $activeYear) : null;

            if ($assessment !== null && (float) $assessment['balance'] > 0) {
                return ['ok' => false, 'error' => 'This student has an outstanding balance for the current term. Settle it with the Finance office before releasing this document.'];
            }
        }

        $verificationCode = bin2hex(random_bytes(16));

        helper('url');
        $qr = (new Builder(
            data: site_url('verify/' . $verificationCode),
            size: 160,
            margin: 8
        ))->build();

        $viewName = $type === 'tor' ? 'documents/tor_pdf' : 'documents/certificate_pdf';

        $html = view($viewName, [
            'student'           => $student,
            'type'              => $type,
            'typeLabel'         => DocumentRequestModel::TYPE_LABELS[$type],
            'grades'            => $type === 'tor' ? (new GradeModel())->forStudent((int) $student['user_id']) : [],
            'verificationCode'  => $verificationCode,
            'qrDataUri'         => $qr->getDataUri(),
            'issuedAt'          => date('F j, Y'),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $targetDir = WRITEPATH . 'uploads/generated_documents';

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $storedFilename = $type . '_' . $studentId . '_' . $verificationCode . '.pdf';
        $fullPath       = $targetDir . '/' . $storedFilename;

        file_put_contents($fullPath, $dompdf->output());

        $this->insert([
            'request_id'        => $requestId,
            'student_id'        => $studentId,
            'document_type'     => $type,
            'stored_filename'   => $storedFilename,
            'verification_code' => $verificationCode,
            'generated_by'      => $generatedBy,
            'generated_at'      => date('Y-m-d H:i:s'),
        ]);

        $id = $this->getInsertID();

        (new AuditLogModel())->record(
            'document.generated',
            'generated_document',
            $id,
            null,
            ['student_id' => $studentId, 'document_type' => $type],
            null,
            $generatedBy,
            null
        );

        helper('url');
        (new NotificationModel())->notify(
            (int) $student['user_id'],
            'document_ready',
            DocumentRequestModel::TYPE_LABELS[$type] . ' has been generated and is ready.',
            site_url('student/dashboard')
        );

        return ['ok' => true, 'id' => $id, 'path' => $fullPath];
    }

    /**
     * Public verification lookup — only ever returns the minimal fields a
     * third party scanning the QR should see, never full student data.
     */
    public function verify(string $code): ?array
    {
        $row = $this->db->table('generated_documents g')
            ->select('g.document_type, g.generated_at, s.student_number')
            ->join('students s', 's.student_id = g.student_id')
            ->where('g.verification_code', $code)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return null;
        }

        return [
            'document_type' => DocumentRequestModel::TYPE_LABELS[$row['document_type']] ?? $row['document_type'],
            'issued_at'      => $row['generated_at'],
        ];
    }

    public function forStudent(int $studentId): array
    {
        return $this->where('student_id', $studentId)->orderBy('generated_at', 'DESC')->findAll();
    }

    public function filePath(array $row): string
    {
        return WRITEPATH . 'uploads/generated_documents/' . $row['stored_filename'];
    }
}

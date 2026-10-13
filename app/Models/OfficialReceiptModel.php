<?php

namespace App\Models;

use CodeIgniter\Model;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * One receipt per student_payments row, 1:1 — see CreateOfficialReceipts
 * migration. issueFor() is the only way a receipt is created, called from
 * inside TuitionAssessmentService::recordPayment()'s own transaction so a
 * payment is never left without one.
 */
class OfficialReceiptModel extends Model
{
    protected $table      = 'official_receipts';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'payment_id',
        'or_number',
        'student_id',
        'amount',
        'status',
        'issued_by',
        'issued_at',
        'voided_by',
        'voided_at',
        'void_reason',
        'stored_filename',
    ];

    protected $useTimestamps = false;

    /**
     * Generates the next OR number for the current year (locked,
     * race-free — see CreateReceiptSequences migration docblock), renders
     * the receipt PDF, and inserts the row. Must be called from within the
     * caller's own transaction (TuitionAssessmentService::recordPayment())
     * so a payment row is never left without a matching receipt.
     *
     * @return array{ok: bool, error?: string, id?: int, or_number?: string}
     */
    public function issueFor(int $paymentId, int $issuedBy): array
    {
        $payment = (new StudentPaymentModel())->find($paymentId);

        if ($payment === null) {
            return ['ok' => false, 'error' => 'Payment not found.'];
        }

        if ($this->where('payment_id', $paymentId)->countAllResults() > 0) {
            return ['ok' => false, 'error' => 'A receipt already exists for this payment.'];
        }

        $student = (new StudentModel())->findDetailed((int) $payment['student_id']);

        if ($student === null) {
            return ['ok' => false, 'error' => 'Student not found.'];
        }

        $orNumber = $this->nextOrNumber();

        $html = view('finance/receipt_pdf', [
            'student'   => $student,
            'payment'   => $payment,
            'orNumber'  => $orNumber,
            'issuedAt'  => date('F j, Y g:i A'),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, 396, 576], 'portrait'); // half-letter, receipt-sized
        $dompdf->render();

        $targetDir = WRITEPATH . 'uploads/official_receipts';

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $storedFilename = $orNumber . '.pdf';
        file_put_contents($targetDir . '/' . $storedFilename, $dompdf->output());

        $this->insert([
            'payment_id'      => $paymentId,
            'or_number'       => $orNumber,
            'student_id'      => (int) $payment['student_id'],
            'amount'          => $payment['amount'],
            'status'          => 'issued',
            'issued_by'       => $issuedBy,
            'issued_at'       => date('Y-m-d H:i:s'),
            'stored_filename' => $storedFilename,
        ]);

        return ['ok' => true, 'id' => $this->getInsertID(), 'or_number' => $orNumber];
    }

    /**
     * Locks this year's row in receipt_sequences (SELECT ... FOR UPDATE),
     * reads+increments next_number, and returns the formatted OR number —
     * safe under concurrent cashiers because the lock is held until the
     * enclosing transaction commits (recordPayment() wraps this call).
     * Seeds the year's row on first use.
     */
    private function nextOrNumber(): string
    {
        $db   = $this->db;
        $year = date('Y');

        // Seed this year's row on first use. INSERT IGNORE (not
        // Builder::insert()) so an existing row — the normal case, and a
        // possible race on the very first receipt of a new year — is
        // silently skipped rather than throwing a duplicate-key error.
        $db->query('INSERT IGNORE INTO receipt_sequences (year, next_number) VALUES (?, 1)', [$year]);

        $suffix = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';
        $row    = $db->query("SELECT next_number FROM receipt_sequences WHERE year = ?{$suffix}", [$year])->getRowArray();
        $number = (int) ($row['next_number'] ?? 1);

        $db->table('receipt_sequences')->where('year', $year)->update(['next_number' => $number + 1]);

        return sprintf('OR-%s-%06d', $year, $number);
    }

    public function void(int $id, string $reason, int $actorId): array
    {
        $receipt = $this->find($id);

        if ($receipt === null) {
            return ['ok' => false, 'error' => 'Receipt not found.'];
        }

        if ($receipt['status'] === 'voided') {
            return ['ok' => false, 'error' => 'This receipt is already voided.'];
        }

        $this->update($id, [
            'status'      => 'voided',
            'voided_by'   => $actorId,
            'voided_at'   => date('Y-m-d H:i:s'),
            'void_reason' => $reason,
        ]);

        return ['ok' => true];
    }

    public function forStudent(int $studentId): array
    {
        return $this->where('student_id', $studentId)->orderBy('issued_at', 'DESC')->findAll();
    }

    public function filePath(array $row): string
    {
        return WRITEPATH . 'uploads/official_receipts/' . $row['stored_filename'];
    }
}

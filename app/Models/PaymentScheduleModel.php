<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentScheduleModel extends Model
{
    protected $table      = 'payment_schedules';
    protected $primaryKey = 'schedule_id';

    protected $allowedFields = [
        'assessment_id',
        'label',
        'due_date',
        'amount',
        'sort_order',
    ];

    protected $useTimestamps = true;

    public function forAssessment(int $assessmentId): array
    {
        return $this->where('assessment_id', $assessmentId)->orderBy('sort_order')->findAll();
    }

    /**
     * Each row's remaining balance, computed by applying $totalPaid as a
     * FIFO waterfall against the rows in order (earliest/first installment
     * paid off first) — never stored, so a rate correction to total_amount
     * doesn't require rewriting historical schedule rows.
     *
     * @return list<array> each row plus 'paid' and 'balance'
     */
    public function withBalances(int $assessmentId, float $totalPaid): array
    {
        $rows = $this->forAssessment($assessmentId);
        $remaining = $totalPaid;

        foreach ($rows as &$row) {
            $amount = (float) $row['amount'];
            $paid   = max(0, min($amount, $remaining));
            $remaining -= $paid;

            $row['paid']    = $paid;
            $row['balance'] = $amount - $paid;
        }

        return $rows;
    }
}

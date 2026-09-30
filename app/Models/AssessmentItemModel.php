<?php

namespace App\Models;

use CodeIgniter\Model;

class AssessmentItemModel extends Model
{
    protected $table      = 'assessment_items';
    protected $primaryKey = 'assessment_item_id';

    protected $allowedFields = [
        'assessment_id',
        'fee_type_id',
        'category',
        'label',
        'amount',
    ];

    protected $useTimestamps = true;

    /** Grouped by category, in catalog order — what the Assessment table renders. */
    public function forAssessment(int $assessmentId): array
    {
        $rows = $this->where('assessment_id', $assessmentId)->orderBy('category')->orderBy('label')->findAll();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['category']][] = $row;
        }

        return $grouped;
    }

    public function replaceFor(int $assessmentId, array $items): void
    {
        $this->where('assessment_id', $assessmentId)->delete();

        foreach ($items as $item) {
            $this->insert([
                'assessment_id' => $assessmentId,
                'fee_type_id'   => $item['fee_type_id'],
                'category'      => $item['category'],
                'label'         => $item['name'],
                'amount'        => $item['amount'],
            ]);
        }
    }
}

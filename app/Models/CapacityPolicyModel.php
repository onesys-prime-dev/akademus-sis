<?php

namespace App\Models;

use App\Libraries\SectionAllocationService;
use CodeIgniter\Model;

/**
 * Resolves the effective {min, target, max, overflow} for a section by
 * falling back through the scopes, most specific first:
 *
 *   section override (sections.capacity / min_capacity)
 *   → offering → subject → program + year level → program → system → built-in defaults
 *
 * Each field is resolved independently, so a program row may set only `max`
 * and still inherit `min` from the system row.
 */
class CapacityPolicyModel extends Model
{
    protected $table      = 'capacity_policies';
    protected $primaryKey = 'policy_id';

    protected $allowedFields = [
        'scope_type', 'scope_id', 'year_level',
        'min_capacity', 'target_capacity', 'max_capacity', 'overflow_tolerance',
    ];

    protected $useTimestamps = true;

    /**
     * @param array{program?: string, year_level?: string, subject_code?: string, offering_id?: int|string,
     *              capacity?: int|null, min_capacity?: int|null} $context
     * @return array{min: int, target: int, max: int, overflow: int, hysteresis: int}
     */
    public function resolve(array $context): array
    {
        $chain = [];

        if (! empty($context['offering_id'])) {
            $chain[] = ['offering', (string) $context['offering_id'], ''];
        }

        if (! empty($context['subject_code'])) {
            $chain[] = ['subject', $context['subject_code'], ''];
        }

        if (! empty($context['program'])) {
            if (! empty($context['year_level'])) {
                $chain[] = ['program', $context['program'], $context['year_level']];
            }

            $chain[] = ['program', $context['program'], ''];
        }

        $chain[] = ['system', '', ''];

        $rows = [];

        foreach ($chain as [$type, $id, $year]) {
            $row = $this->where(['scope_type' => $type, 'scope_id' => $id, 'year_level' => $year])->first();

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        $pick = static function (string $field) use ($rows) {
            foreach ($rows as $row) {
                if ($row[$field] !== null) {
                    return (int) $row[$field];
                }
            }

            return null;
        };

        $policy = [
            'min'      => $pick('min_capacity'),
            'target'   => $pick('target_capacity'),
            'max'      => $pick('max_capacity'),
            'overflow' => $pick('overflow_tolerance'),
        ];

        // The section's own values sit above every scope.
        if (! empty($context['capacity'])) {
            $policy['max'] = (int) $context['capacity'];
        }

        if (! empty($context['min_capacity'])) {
            $policy['min'] = (int) $context['min_capacity'];
        }

        $svc = new SectionAllocationService();

        // Keep min <= target <= max when only some fields were overridden
        // (e.g. a section capacity of 12 under a target of 15).
        $merged = array_merge(SectionAllocationService::DEFAULT_POLICY, array_filter($policy, static fn ($v) => $v !== null));

        if ($policy['max'] !== null) {
            $merged['target'] = min($policy['target'] ?? $merged['target'], $merged['max']);
        }

        $merged['min'] = min($merged['min'], $merged['target']);

        return $svc->normalizePolicy($merged);
    }
}

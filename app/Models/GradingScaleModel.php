<?php

namespace App\Models;

use CodeIgniter\Model;

class GradingScaleModel extends Model
{
    protected $table      = 'grading_scales';
    protected $primaryKey = 'scale_id';

    protected $allowedFields = [
        'name',
        'type',
        'min_value',
        'max_value',
        'passing_value',
        'is_lower_better',
        'letter_options',
        'failing_letters',
        'is_active',
    ];

    protected $useTimestamps = true;

    public const TYPES = ['numeric_gwa', 'percentage', 'letter'];

    public function setActive(int $id): void
    {
        $this->builder()->set('is_active', 0)->update();
        $this->update($id, ['is_active' => 1]);
    }

    /**
     * The scale currently in effect for new grade entry, or null if the
     * admin has somehow left none active (fails closed — callers must not
     * silently fall back to guessing a scale).
     */
    public function activeScale(): ?array
    {
        return $this->where('is_active', 1)->first();
    }
}

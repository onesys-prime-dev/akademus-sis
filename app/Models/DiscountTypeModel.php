<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Configurable catalog behind student_discounts — see CreateDiscountTypes
 * migration.
 */
class DiscountTypeModel extends Model
{
    protected $table      = 'discount_types';
    protected $primaryKey = 'id';

    protected $allowedFields = ['name', 'code', 'value_type', 'default_value', 'is_active', 'sort_order'];

    protected $useTimestamps = true;

    public const VALUE_TYPES = ['percentage', 'fixed'];

    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('sort_order')->orderBy('name')->findAll();
    }
}

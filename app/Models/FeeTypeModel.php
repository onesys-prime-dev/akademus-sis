<?php

namespace App\Models;

use CodeIgniter\Model;

class FeeTypeModel extends Model
{
    protected $table      = 'fee_types';
    protected $primaryKey = 'fee_type_id';

    protected $allowedFields = [
        'category',
        'code',
        'name',
        'is_active',
        'sort_order',
    ];

    protected $useTimestamps = true;

    public const CATEGORIES = ['miscellaneous', 'other', 'additional', 'course'];

    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('category')->orderBy('sort_order')->orderBy('name')->findAll();
    }
}

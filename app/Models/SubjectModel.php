<?php

namespace App\Models;

use App\Libraries\AcademicHourResolver;
use CodeIgniter\Model;

class SubjectModel extends Model
{
    protected $table = 'subjects';
    protected $primaryKey = 'subject_id';

    protected $allowedFields = [
        'sub_department_id',
        'program_id',
        'subject_code',
        'subject_name',
        'units',
        'contact_hours',
        'is_laboratory',
        'pair_subject_id',
        'lecture_units',
        'laboratory_units',
        'meetings_per_week',
        'weekly_hours',
        'description',
        'is_active'
    ];

    protected $returnType = 'array';

    protected $useTimestamps = true;

    protected $beforeInsert = ['deriveWeeklyHours'];
    protected $beforeUpdate = ['deriveWeeklyHours'];

    /**
     * Recomputes `weekly_hours` from the subject's own configuration on every
     * save, per AcademicHourResolver's priority rules. Never hand-entered.
     */
    protected function deriveWeeklyHours(array $data): array
    {
        if (! isset($data['data'])) {
            return $data;
        }

        // Merge against the existing row (for partial updates) so the
        // resolver always sees the full picture, not just changed fields.
        $existing = [];
        if (! empty($data['id'])) {
            $id = is_array($data['id']) ? ($data['id'][0] ?? null) : $data['id'];
            $existing = $id ? ($this->find($id) ?? []) : [];
        }

        $subject = array_merge($existing, $data['data']);

        $resolver = new AcademicHourResolver();
        $data['data']['weekly_hours'] = $resolver->weeklyHoursFor($subject);

        return $data;
    }
}

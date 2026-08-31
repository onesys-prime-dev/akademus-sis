<?php

namespace App\Models;

use CodeIgniter\Model;

class FacultyAvailabilityModel extends Model
{
    protected $table      = 'faculty_availability';
    protected $primaryKey = 'availability_id';

    protected $allowedFields = [
        'user_id',
        'ay_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_available',
        'notes',
    ];

    protected $useTimestamps = true;

    public function forTerm(int $userId, int $ayId): array
    {
        return $this->where(['user_id' => $userId, 'ay_id' => $ayId])
            ->orderBy('day_of_week')
            ->findAll();
    }

    /**
     * Replace a faculty member's whole availability submission for one term
     * atomically, mirroring PlottedScheduleModel::replaceDraft()'s
     * delete-then-insert pattern.
     *
     * @param array $rows Fully-prepared row arrays for insertBatch.
     */
    public function replaceForTerm(int $userId, int $ayId, array $rows): void
    {
        $this->db->transException(true)->transStart();

        $this->where(['user_id' => $userId, 'ay_id' => $ayId])->delete();

        if ($rows !== []) {
            $this->insertBatch($rows);
        }

        $this->db->transComplete();
    }
}

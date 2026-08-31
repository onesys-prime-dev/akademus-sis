<?php

namespace App\Models;

use CodeIgniter\Model;

class FacultySubjectPreferenceModel extends Model
{
    protected $table      = 'faculty_subject_preferences';
    protected $primaryKey = 'preference_id';

    protected $allowedFields = [
        'user_id',
        'ay_id',
        'subject_id',
        'preference_level',
        'notes',
    ];

    protected $useTimestamps = true;

    public function forTerm(int $userId, int $ayId): array
    {
        return $this->where(['user_id' => $userId, 'ay_id' => $ayId])->findAll();
    }

    /**
     * Replace a faculty member's whole preference submission for one term
     * atomically, mirroring FacultyAvailabilityModel::replaceForTerm().
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

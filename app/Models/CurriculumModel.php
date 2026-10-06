<?php

namespace App\Models;

use App\Libraries\CatalogEntry;
use CodeIgniter\Model;

class CurriculumModel extends Model
{
    protected $table            = 'curriculum';
    protected $primaryKey       = 'curriculum_id';

    protected $allowedFields = [
        'program_id',
        'curriculum_code',
        'curriculum_name',
        'academic_year',
        'description',
        'is_active',
        'version_number',
        'base_curriculum_id',
        'status',
        'is_current',
        'published_at',
    ];

    protected $useTimestamps = true;

    /**
     * One program has exactly one curriculum, always live and directly
     * editable — no draft/publish/version workflow. This is the single
     * place that resolves "the" curriculum for a program, creating it on
     * first use. The legacy version_number/base_curriculum_id/status/
     * is_current/published_at columns are left on the table for any old
     * data, but are no longer meaningfully read or written — every row
     * this creates is simply flagged status='published', is_current=1 so
     * older queries that haven't been updated yet still see it as "live".
     */
    public function liveFor(int $programId): array
    {
        $existing = $this->where('program_id', $programId)
            ->orderBy('is_current', 'DESC')
            ->orderBy('curriculum_id', 'DESC')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $program = db_connect()->table('programs')->where('program_id', $programId)->get()->getRowArray();
        $code    = ($program['program_code'] ?? 'PROG') . '-' . date('Y');
        $n       = 1;

        while ($this->where('curriculum_code', $code)->countAllResults() > 0) {
            $code = ($program['program_code'] ?? 'PROG') . '-' . date('Y') . '-' . (++$n);
        }

        $this->insert([
            'program_id'      => $programId,
            'curriculum_code' => $code,
            'curriculum_name' => ($program['program_name'] ?? 'Program') . ' Curriculum',
            'academic_year'   => date('Y') . '-' . (date('Y') + 1),
            'description'     => '',
            'is_active'       => 1,
            'version_number'  => 1,
            'status'          => 'published',
            'is_current'      => 1,
        ]);

        return $this->find($this->getInsertID());
    }

    /**
     * The subjects a curriculum offers in one term (year level + semester),
     * catalog-shaped for the schedule-plotting UI (see CatalogEntry).
     * `is_lab` comes from this curriculum's own classification on
     * curriculum_subjects, falling back to the subject's global default
     * when the curriculum doesn't specify one.
     *
     * @param int $semester 1 = 1st, 2 = 2nd, 3 = Summer
     */
    public function termSubjects(int $curriculumId, int $yearLevel, int $semester): array
    {
        $rows = $this->db->table('curriculum_subjects cs')
            ->select('s.*, cs.is_laboratory AS assignment_is_laboratory')
            ->join('subjects s', 's.subject_id = cs.subject_id')
            ->where('cs.curriculum_id', $curriculumId)
            ->where('cs.year_level', $yearLevel)
            ->where('cs.semester', $semester)
            ->where('cs.is_active', 1)
            ->where('s.is_active', 1)
            ->orderBy('s.subject_code')
            ->get()
            ->getResultArray();

        return array_map(static function (array $row): array {
            $isLab = $row['assignment_is_laboratory'] !== null
                ? (bool) $row['assignment_is_laboratory']
                : (bool) $row['is_laboratory'];

            return CatalogEntry::fromRow($row, $isLab);
        }, $rows);
    }
}

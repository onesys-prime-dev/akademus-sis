<?php

namespace App\Models;

use App\Libraries\CatalogEntry;
use CodeIgniter\Model;

class CurriculumPresetModel extends Model
{
    protected $table      = 'curriculum_presets';
    protected $primaryKey = 'preset_id';

    protected $allowedFields = [
        'academic_year',
        'program',
        'year_level',
        'subject_codes',
    ];

    protected $useTimestamps = true;

    /**
     * The preset for one AY + program + year level, or null when the
     * chairperson hasn't created one yet.
     */
    public function forContext(string $academicYear, string $program, string $yearLevel): ?array
    {
        return $this->where([
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
        ])->first();
    }

    /**
     * Create or replace the preset for one context, including each
     * subject's curriculum-specific lab/lecture assignment. One transaction
     * covers the preset row (and its `subject_codes` convenience cache) and
     * the `curriculum_preset_subjects` assignment rows — mirrors
     * PlottedScheduleModel::replaceDraft()'s delete-then-insert pattern.
     *
     * @param list<array{subject_id: int, code: string, is_laboratory: bool}> $entries
     */
    public function upsertWithSubjects(string $academicYear, string $program, string $yearLevel, array $entries): void
    {
        $this->db->transException(true)->transStart();

        $existing = $this->forContext($academicYear, $program, $yearLevel);

        $row = [
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
            'subject_codes' => json_encode(array_values(array_column($entries, 'code'))),
        ];

        if ($existing === null) {
            $this->insert($row);
            $presetId = $this->getInsertID();
        } else {
            $this->update($existing['preset_id'], $row);
            $presetId = (int) $existing['preset_id'];
        }

        $subjects = db_connect()->table('curriculum_preset_subjects');
        $subjects->where('preset_id', $presetId)->delete();

        if ($entries !== []) {
            $subjects->insertBatch(array_map(static fn (array $e): array => [
                'preset_id'     => $presetId,
                'subject_id'    => $e['subject_id'],
                'is_laboratory' => $e['is_laboratory'] ? 1 : 0,
            ], $entries));
        }

        $this->db->transComplete();
    }

    /**
     * Update a single subject's lab/lecture assignment within one preset,
     * without touching the rest of the assignment list.
     */
    public function updateSubjectLabFlag(int $presetId, int $subjectId, bool $isLaboratory): void
    {
        db_connect()->table('curriculum_preset_subjects')
            ->where('preset_id', $presetId)
            ->where('subject_id', $subjectId)
            ->update(['is_laboratory' => $isLaboratory ? 1 : 0]);
    }

    /**
     * This context's assigned subjects, catalog-shaped (same fields as
     * ChairpersonController::catalog()) but with `is_lab` sourced from THIS
     * curriculum's assignment row, not the subject's global default —
     * the same subject can be lecture in one curriculum and laboratory in
     * another. Falls back to the subject's own global `is_laboratory` only
     * if no assignment row exists yet (defensive; not expected in practice
     * since every save writes one).
     */
    public function subjectsForContext(string $academicYear, string $program, string $yearLevel): array
    {
        $preset = $this->forContext($academicYear, $program, $yearLevel);

        if ($preset === null) {
            return [];
        }

        $rows = db_connect()->table('curriculum_preset_subjects cps')
            ->select('s.*, cps.is_laboratory AS assignment_is_laboratory')
            ->join('subjects s', 's.subject_id = cps.subject_id')
            ->where('cps.preset_id', $preset['preset_id'])
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

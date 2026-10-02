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

    public const STATUSES = ['draft', 'published', 'archived'];

    /**
     * Published curricula are immutable snapshots — subjects may only be
     * added/edited/removed on a curriculum, and the curriculum's own core
     * fields only changed, while it is still 'draft'.
     */
    public function isEditable(array $curriculum): bool
    {
        return ($curriculum['status'] ?? 'draft') === 'draft';
    }

    /**
     * The version new promotions/assignments should default to for a given
     * program: the published version currently flagged as current.
     */
    public function currentPublishedFor(int $programId): ?array
    {
        return $this->where('program_id', $programId)
            ->where('status', 'published')
            ->where('is_current', 1)
            ->orderBy('version_number', 'DESC')
            ->first();
    }

    /**
     * The subjects one curriculum version offers in one term (year level +
     * semester), catalog-shaped for the schedule-plotting UI (see
     * CatalogEntry). `is_lab` comes from the version's own classification
     * on curriculum_subjects, falling back to the subject's global default
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

    /**
     * Every version sharing this curriculum's lineage (its base_curriculum_id),
     * newest first — the full version history for display.
     */
    public function versionsOf(int $curriculumId): array
    {
        $row = $this->find($curriculumId);

        if ($row === null) {
            return [];
        }

        $baseId = $row['base_curriculum_id'] ?? $curriculumId;

        return $this->where('base_curriculum_id', $baseId)
            ->orderBy('version_number', 'DESC')
            ->findAll();
    }

    /**
     * Creates a new draft version of $curriculumId: a fresh row (new
     * curriculum_id, version_number + 1, same lineage) plus a copy of every
     * curriculum_subjects row the source version has, so editing can start
     * from the prior version's subject list rather than from empty. The
     * source curriculum is left completely untouched — this is the "never
     * edit an existing version in place" rule from the handbook, made
     * structurally true rather than just a policy.
     *
     * @return array{ok: bool, error?: string, curriculum?: array}
     */
    public function createNewVersion(int $curriculumId): array
    {
        $source = $this->find($curriculumId);

        if ($source === null) {
            return ['ok' => false, 'error' => 'Curriculum not found.'];
        }

        $baseId = $source['base_curriculum_id'] ?? $curriculumId;

        $db = $this->db;
        $db->transStart();

        $nextVersion = (int) ($this->where('base_curriculum_id', $baseId)
            ->selectMax('version_number', 'max_version')
            ->first()['max_version'] ?? $source['version_number']) + 1;

        $newCode = $source['curriculum_code'] . '-v' . $nextVersion;

        $this->insert([
            'program_id'          => $source['program_id'],
            'curriculum_code'     => $newCode,
            'curriculum_name'     => $source['curriculum_name'],
            'academic_year'       => $source['academic_year'],
            'description'         => $source['description'],
            'is_active'           => 1,
            'version_number'      => $nextVersion,
            'base_curriculum_id'  => $baseId,
            'status'              => 'draft',
            'is_current'          => 0,
        ]);

        $newId = $this->getInsertID();

        $sourceSubjects = $db->table('curriculum_subjects')
            ->where('curriculum_id', $curriculumId)
            ->get()
            ->getResultArray();

        foreach ($sourceSubjects as $cs) {
            $db->table('curriculum_subjects')->insert([
                'curriculum_id' => $newId,
                'subject_id'    => $cs['subject_id'],
                'year_level'    => $cs['year_level'],
                'semester'      => $cs['semester'],
                'is_required'   => $cs['is_required'],
                'is_active'     => $cs['is_active'],
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not create the new version. Please retry.'];
        }

        return ['ok' => true, 'curriculum' => $this->find($newId)];
    }

    /**
     * Publishes a draft version: locks it (status -> published), and — since
     * a program should have exactly one "current" version that new
     * admissions/assignments default to — clears is_current on every sibling
     * version before setting it on this one. Older published versions are
     * left published (still valid for the students already on them) and are
     * simply no longer "current".
     *
     * @return array{ok: bool, error?: string}
     */
    public function publish(int $curriculumId): array
    {
        $curriculum = $this->find($curriculumId);

        if ($curriculum === null) {
            return ['ok' => false, 'error' => 'Curriculum not found.'];
        }

        if ($curriculum['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Only a draft curriculum can be published.'];
        }

        $baseId = $curriculum['base_curriculum_id'] ?? $curriculumId;

        $db = $this->db;
        $db->transStart();

        $this->where('base_curriculum_id', $baseId)->set('is_current', 0)->update();

        $this->update($curriculumId, [
            'status'       => 'published',
            'is_current'   => 1,
            'published_at' => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        return ['ok' => $db->transStatus() !== false];
    }

    /**
     * Archives a published version (e.g. no longer offered) without
     * deleting it — historical students on it must keep resolving correctly.
     */
    public function archive(int $curriculumId): array
    {
        $curriculum = $this->find($curriculumId);

        if ($curriculum === null) {
            return ['ok' => false, 'error' => 'Curriculum not found.'];
        }

        if ($curriculum['status'] !== 'published') {
            return ['ok' => false, 'error' => 'Only a published curriculum can be archived.'];
        }

        $this->update($curriculumId, ['status' => 'archived', 'is_current' => 0]);

        return ['ok' => true];
    }
}

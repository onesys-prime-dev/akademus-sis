<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * A course offering is the actual class instance a schedule/roster attaches
 * to — decoupled from `sections` (the administrative cohort). Merging two
 * sections' rosters for a shared subject is: both sections' rows in
 * `offering_sections` point at the same offering.
 */
class CourseOfferingModel extends Model
{
    protected $table      = 'course_offerings';
    protected $primaryKey = 'offering_id';

    protected $allowedFields = [
        'academic_year',
        'subject_code',
        'capacity',
        'status',
    ];

    protected $useTimestamps = true;

    /**
     * The offering this (section, subject) pair belongs to, if any — the
     * thing EnrollmentModel needs to know whether to check capacity against
     * this section alone or against the merged pool.
     */
    public function offeringForSection(int $sectionId, string $subjectCode): ?array
    {
        return $this->db->table('course_offerings co')
            ->select('co.*')
            ->join('offering_sections os', 'os.offering_id = co.offering_id')
            ->where('os.section_id', $sectionId)
            ->where('os.subject_code', $subjectCode)
            ->get()
            ->getRowArray();
    }

    /**
     * Every section_id currently attached to an offering.
     */
    public function sectionIdsFor(int $offeringId): array
    {
        return array_map('intval', array_column(
            $this->db->table('offering_sections')->select('section_id')->where('offering_id', $offeringId)->get()->getResultArray(),
            'section_id'
        ));
    }

    /**
     * Distinct enrolled students across every section merged into this
     * offering — the merged roster count EnrollmentModel checks against
     * offering.capacity instead of any single section's own capacity.
     */
    public function rosterCount(int $offeringId): int
    {
        $row = $this->db->table('enrollments')
            ->select('COUNT(DISTINCT user_id) AS cnt')
            ->where('offering_id', $offeringId)
            ->where('status', 'enrolled')
            ->get()
            ->getRowArray();

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Every actively-enrolled student across every section merged into this
     * offering — the class list grading needs for a merged (section,
     * subject) pair, in place of any single section's own roster.
     *
     * @return list<array{enrollment_id: int, user_id: int, username: string}>
     */
    public function listRoster(int $offeringId): array
    {
        return $this->db->table('enrollments e')
            ->select('e.enrollment_id, e.user_id, u.username')
            ->join('users u', 'u.id = e.user_id')
            ->where('e.offering_id', $offeringId)
            ->where('e.status', 'enrolled')
            ->orderBy('u.username')
            ->get()
            ->getResultArray();
    }

    /**
     * Creates a merged offering for $subjectCode across $sectionIds (2 or
     * more), then backfills offering_id onto every already-enrolled
     * student's existing row for that subject in those sections, so a
     * merge performed after enrollment has already started still produces
     * one correct combined roster immediately.
     *
     * Refuses to merge a section that's already merged into a DIFFERENT
     * offering for the same subject — a section can only belong to one
     * offering per subject at a time (enforced by the unique key on
     * offering_sections too; this is the friendly pre-check).
     *
     * @return array{ok: bool, error?: string, offering_id?: int, roster_count?: int, over_capacity?: bool}
     */
    public function createMerge(string $academicYear, string $subjectCode, array $sectionIds, ?int $capacity): array
    {
        $sectionIds = array_values(array_unique(array_map('intval', $sectionIds)));

        if (count($sectionIds) < 2) {
            return ['ok' => false, 'error' => 'Select at least two sections to merge.'];
        }

        $db = $this->db;
        $db->transStart();

        foreach ($sectionIds as $sectionId) {
            $existing = $this->offeringForSection($sectionId, $subjectCode);

            if ($existing !== null) {
                $db->transRollback();

                return ['ok' => false, 'error' => "Section #{$sectionId} is already merged into another offering for {$subjectCode}."];
            }
        }

        $this->insert([
            'academic_year' => $academicYear,
            'subject_code'  => $subjectCode,
            'capacity'      => $capacity,
        ]);

        $offeringId = $this->getInsertID();

        $rows = array_map(static fn (int $sid) => [
            'offering_id'  => $offeringId,
            'section_id'   => $sid,
            'subject_code' => $subjectCode,
            'created_at'   => date('Y-m-d H:i:s'),
        ], $sectionIds);

        $db->table('offering_sections')->insertBatch($rows);

        // Backfill: any student already enrolled in this subject through one
        // of these sections now counts toward the merged roster.
        $db->table('enrollments')
            ->where('subject_code', $subjectCode)
            ->whereIn('section_id', $sectionIds)
            ->update(['offering_id' => $offeringId]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not create the merge. Please retry.'];
        }

        $rosterCount = $this->rosterCount($offeringId);

        return [
            'ok'            => true,
            'offering_id'   => $offeringId,
            'roster_count'  => $rosterCount,
            // Surfaced, never auto-enforced retroactively — merging never
            // drops an already-enrolled student (see roadmap Q&A on merging).
            'over_capacity' => $capacity !== null && $rosterCount > $capacity,
        ];
    }

    /**
     * Detaches one section from an offering. Existing enrollments that came
     * through that section are un-tagged (offering_id -> null) — they
     * revert to being counted against their own section again, never
     * silently dropped from the roster. If this was the last attached
     * section, the now-empty offering is deleted outright.
     */
    public function unmergeSection(int $offeringId, int $sectionId): array
    {
        $offering = $this->find($offeringId);

        if ($offering === null) {
            return ['ok' => false, 'error' => 'Offering not found.'];
        }

        $db = $this->db;
        $db->transStart();

        $db->table('offering_sections')
            ->where('offering_id', $offeringId)
            ->where('section_id', $sectionId)
            ->delete();

        $db->table('enrollments')
            ->where('offering_id', $offeringId)
            ->where('section_id', $sectionId)
            ->update(['offering_id' => null]);

        $remaining = $this->sectionIdsFor($offeringId);

        if ($remaining === []) {
            $db->table('enrollments')->where('offering_id', $offeringId)->update(['offering_id' => null]);
            $this->delete($offeringId);
        }

        $db->transComplete();

        return ['ok' => $db->transStatus() !== false];
    }
}

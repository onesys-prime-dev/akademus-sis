<?php

namespace App\Libraries;

/**
 * The ONE place that answers "does this completed subject satisfy this
 * required subject's academic requirement?" (Step 8). Every feature that
 * needs this answer — demand aggregation, prerequisite checks — asks this
 * service; none of them re-derive the rule themselves.
 *
 * Rule, deliberately minimal:
 *  - A subject always satisfies its own requirement (exact match).
 *  - An explicit, active row in `subject_substitutions` where
 *    substitute_subject_id = the completed subject and required_subject_id
 *    = the required subject also satisfies it.
 *  - Nothing else. No title/code similarity, no prefix matching, no
 *    transitivity (A satisfies B and B satisfies C does NOT imply A
 *    satisfies C), no multi-hop traversal of any kind — only the one direct
 *    relationship a human explicitly configured.
 *
 * Evaluated against the CURRENT active rule set at call time — see the
 * class-level note on SubjectSubstitutionModel and the Step 8 limitations
 * this carries: deactivating/reactivating a rule changes future evaluations
 * only; it is not a time-travel/versioned rule history.
 */
class SubjectRequirementService
{
    public function satisfies(int $completedSubjectId, int $requiredSubjectId): bool
    {
        if ($completedSubjectId === $requiredSubjectId) {
            return true;
        }

        return db_connect()->table('subject_substitutions')
            ->where('required_subject_id', $requiredSubjectId)
            ->where('substitute_subject_id', $completedSubjectId)
            ->where('is_active', 1)
            ->countAllResults() > 0;
    }

    /**
     * Every subject_id that satisfies $requiredSubjectId — itself, plus
     * every active configured substitute. One-hop only.
     *
     * @return list<int>
     */
    public function satisfyingSubjectIds(int $requiredSubjectId): array
    {
        return $this->acceptableSubjectIdsFor([$requiredSubjectId])[$requiredSubjectId] ?? [$requiredSubjectId];
    }

    /**
     * Batched equivalent — one query for the whole set instead of one per
     * subject, for demand aggregation over many subjects at once.
     *
     * @param list<int> $requiredSubjectIds
     *
     * @return array<int, list<int>> keyed by required_subject_id, each value
     *                               including the subject's own id
     */
    public function acceptableSubjectIdsFor(array $requiredSubjectIds): array
    {
        $requiredSubjectIds = array_values(array_unique(array_map('intval', $requiredSubjectIds)));

        $result = [];

        foreach ($requiredSubjectIds as $id) {
            $result[$id] = [$id];
        }

        if ($requiredSubjectIds === []) {
            return $result;
        }

        $rows = db_connect()->table('subject_substitutions')
            ->select('required_subject_id, substitute_subject_id')
            ->whereIn('required_subject_id', $requiredSubjectIds)
            ->where('is_active', 1)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $result[(int) $row['required_subject_id']][] = (int) $row['substitute_subject_id'];
        }

        return $result;
    }
}

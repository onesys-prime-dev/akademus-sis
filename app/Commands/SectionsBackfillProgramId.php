<?php

namespace App\Commands;

use App\Models\DepartmentModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Backfills sections.program_id from the legacy free-text sections.program
 * column (see Database\Migrations\...AddProgramIdToSections). Dry-run by
 * default — pass --commit to actually write.
 *
 * Resolution, strongest match first, NEVER guessing on ambiguity:
 *  1. Legacy display-alias match (DepartmentModel::LEGACY_PROGRAM_NAMES,
 *     e.g. "BS in Computer Science" -> program_code BSCS) — program_code is
 *     globally unique (enforced at creation), so this always resolves to
 *     exactly one program when it matches at all.
 *  2. Exact programs.program_name match — resolved only if EXACTLY one
 *     program has that name; two or more active programs sharing a name
 *     (no uniqueness has ever been enforced on program_name) is reported
 *     as AMBIGUOUS and left NULL, never silently picked.
 *  3. No match at all is reported as UNRESOLVED and left NULL.
 *
 * Safe to re-run: only touches rows where program_id IS NULL, so an already
 *-backfilled section is never re-evaluated.
 */
class SectionsBackfillProgramId extends BaseCommand
{
    protected $group       = 'Custom';
    protected $name        = 'sections:backfill-program-id';
    protected $description = 'Backfills sections.program_id from the legacy program text column (dry-run unless --commit).';
    protected $options     = ['--commit' => 'Actually write. Without it, only the report is printed.'];

    public function run(array $params)
    {
        // CLI::getOption() reads CLI's own parsed-argv state, which is only
        // reliably populated for a real `php spark ...` process invocation
        // — a second in-process command() call within the same PHP process
        // (as happens once per test method in a PHPUnit run) does not
        // consistently repopulate it. array_key_exists() against $params
        // (which both the real spark dispatcher and the command() helper
        // populate identically — see Common.php's command() — stripping the
        // leading "--") is the reliable signal either way.
        $commit = CLI::getOption('commit') !== null || array_key_exists('commit', $params);
        $db     = db_connect();

        $sections = $db->table('sections')->where('program_id', null)->get()->getResultArray();

        $total      = count($sections);
        $matched    = 0;
        $ambiguous  = [];
        $unresolved = [];

        foreach ($sections as $section) {
            $resolution = $this->resolve($section['program']);

            if ($resolution['status'] === 'matched') {
                $matched++;

                if ($commit) {
                    $db->table('sections')->where('section_id', $section['section_id'])->update(['program_id' => $resolution['program_id']]);
                }
            } elseif ($resolution['status'] === 'ambiguous') {
                $ambiguous[] = $section['section_id'] . ' (' . $section['program'] . ')';
            } else {
                $unresolved[] = $section['section_id'] . ' (' . $section['program'] . ')';
            }
        }

        CLI::write($commit ? '=== COMMIT MODE ===' : '=== DRY RUN (pass --commit to write) ===', 'yellow');
        CLI::write("Sections needing backfill: {$total}");
        CLI::write("Matched: {$matched}", 'green');
        CLI::write('Ambiguous (' . count($ambiguous) . '): ' . ($ambiguous === [] ? 'none' : ''), count($ambiguous) > 0 ? 'red' : 'green');
        foreach ($ambiguous as $row) {
            CLI::write("  - {$row}", 'red');
        }
        CLI::write('Unresolved (' . count($unresolved) . '): ' . ($unresolved === [] ? 'none' : ''), count($unresolved) > 0 ? 'yellow' : 'green');
        foreach ($unresolved as $row) {
            CLI::write("  - {$row}", 'yellow');
        }

        if (! $commit && $total > 0) {
            CLI::write('Dry run only — nothing was written. Pass --commit to apply.', 'yellow');
        }
    }

    /**
     * @return array{status: 'matched'|'ambiguous'|'unresolved', program_id?: int}
     */
    private function resolve(string $programText): array
    {
        $code = array_search($programText, DepartmentModel::legacyProgramNames(), true);

        $builder = db_connect()->table('programs');

        $rows = $code !== false
            ? $builder->where('program_code', $code)->get()->getResultArray()
            : $builder->where('program_name', $programText)->get()->getResultArray();

        if (count($rows) === 1) {
            return ['status' => 'matched', 'program_id' => (int) $rows[0]['program_id']];
        }

        if (count($rows) === 0) {
            return ['status' => 'unresolved'];
        }

        return ['status' => 'ambiguous'];
    }
}

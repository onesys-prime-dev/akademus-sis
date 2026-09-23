<?php

namespace App\Commands;

use App\Libraries\TermCurriculum;
use App\Models\CurriculumModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * One-time cutover helper: turns the hand-built per-term presets
 * (curriculum_presets) of one academic year into a DRAFT versioned
 * curriculum per program, so the versioned curriculum can become the source
 * of truth (see App\Libraries\TermCurriculum).
 *
 * Dry-run by default. --commit writes drafts only; publishing stays an
 * explicit human step (Admin -> Curriculum -> Publish) because a published
 * curriculum is immutable.
 */
class CurriculumImportPresets extends BaseCommand
{
    protected $group       = 'Curriculum';
    protected $name        = 'curriculum:import-presets';
    protected $description = 'Builds draft curriculum versions from one academic year\'s term presets (dry-run unless --commit).';
    protected $usage       = 'curriculum:import-presets [--ay "AY 2025 - 2026"] [--commit]';
    protected $options     = [
        '--ay'     => 'Academic-year label to import, as stored on presets. Defaults to the active academic year.',
        '--commit' => 'Actually write the draft curricula. Without it nothing is changed.',
    ];

    public function run(array $params)
    {
        $db     = Database::connect();
        $commit = CLI::getOption('commit') !== null;
        $ay     = CLI::getOption('ay');

        if (! is_string($ay) || $ay === '') {
            $active = $db->table('academic_years')->where('is_active', 1)->get()->getRowArray();

            if ($active === null) {
                CLI::error('No --ay given and no academic year is marked active.');

                return EXIT_ERROR;
            }

            $ay = 'AY ' . preg_replace('/^(\d{4})-(\d{4})$/', '$1 - $2', $active['label']);
        }

        if (preg_match('/^AY (\d{4}) - (\d{4})$/', $ay, $m) !== 1) {
            CLI::error("--ay must look like \"AY 2025 - 2026\" (got \"{$ay}\").");

            return EXIT_ERROR;
        }

        [$startYear, $ayLabel] = [$m[1], "{$m[1]}-{$m[2]}"];

        CLI::write(($commit ? 'COMMIT' : 'DRY RUN') . " — importing presets for {$ay}", 'yellow');

        $presets = $db->table('curriculum_presets')->like('academic_year', $ay . ' ·', 'after')->orderBy('preset_id')->get()->getResultArray();

        if ($presets === []) {
            CLI::error("No presets with a semester found for {$ay}.");

            return EXIT_ERROR;
        }

        // program display name => list of [year, semester, subject rows]
        $byProgram = [];
        $problems  = [];

        foreach ($presets as $preset) {
            $semester = TermCurriculum::semesterOf($preset['academic_year']);
            $year     = TermCurriculum::yearLevelOf($preset['year_level']);
            $label    = "{$preset['program']} / {$preset['year_level']} / {$preset['academic_year']}";

            if ($semester === null || $year === null) {
                $problems[] = "SKIPPED preset {$preset['preset_id']} ({$label}): unrecognised semester or year level.";
                continue;
            }

            $rows = $db->table('curriculum_preset_subjects cps')
                ->select('s.subject_id, s.subject_code, cps.is_laboratory')
                ->join('subjects s', 's.subject_id = cps.subject_id')
                ->where('cps.preset_id', $preset['preset_id'])
                ->orderBy('s.subject_code')
                ->get()
                ->getResultArray();

            // The relational rows are authoritative; the JSON subject_codes
            // cache can drift (e.g. a stray invalid code). Report any code it
            // lists that has no matching assignment row.
            $rowCodes = array_column($rows, 'subject_code');
            foreach ((json_decode($preset['subject_codes'], true) ?: []) as $code) {
                if (! in_array($code, $rowCodes, true)) {
                    $problems[] = "preset {$preset['preset_id']} ({$label}): listed code \"{$code}\" has no matching subject assignment — not imported.";
                }
            }

            $byProgram[$preset['program']][] = ['year' => $year, 'semester' => $semester, 'rows' => $rows, 'preset_id' => (int) $preset['preset_id']];
        }

        $curricula = new CurriculumModel();
        $created   = 0;

        foreach ($byProgram as $programName => $terms) {
            $programId = TermCurriculum::programIdOf($programName);

            if ($programId === null) {
                $problems[] = "SKIPPED program \"{$programName}\": no matching row in programs.";
                continue;
            }

            $program = $db->table('programs')->where('program_id', $programId)->get()->getRowArray();
            $code    = "{$program['program_code']}-{$startYear}";

            if ($curricula->where('curriculum_code', $code)->countAllResults() > 0) {
                $problems[] = "SKIPPED {$programName}: a curriculum coded {$code} already exists.";
                continue;
            }

            usort($terms, static fn (array $a, array $b) => [$a['year'], $a['semester']] <=> [$b['year'], $b['semester']]);

            $entries = [];
            $seen    = [];

            foreach ($terms as $term) {
                foreach ($term['rows'] as $row) {
                    if (isset($seen[$row['subject_id']])) {
                        $problems[] = "{$programName}: {$row['subject_code']} appears in more than one term — kept in its first term ({$seen[$row['subject_id']]}).";
                        continue;
                    }

                    $seen[$row['subject_id']] = "Y{$term['year']} S{$term['semester']}";
                    $entries[]                = [
                        'subject_id'    => (int) $row['subject_id'],
                        'year_level'    => $term['year'],
                        'semester'      => $term['semester'],
                        'is_laboratory' => $row['is_laboratory'] === null ? null : (int) $row['is_laboratory'],
                        'code'          => $row['subject_code'],
                    ];
                }
            }

            CLI::write("\n{$code} — {$program['program_name']}  ({$programId}), " . count($entries) . ' subjects', 'green');

            foreach ($entries as $e) {
                CLI::write(sprintf('  Year %d  Sem %d  %s%s', $e['year_level'], $e['semester'], $e['code'], $e['is_laboratory'] ? '  [lab]' : ''));
            }

            if (! $commit) {
                continue;
            }

            $db->transStart();

            $curricula->insert([
                'program_id'         => $programId,
                'curriculum_code'    => $code,
                'curriculum_name'    => "{$program['program_name']} — imported from {$ay} term presets",
                'academic_year'      => $ayLabel,
                'description'        => "Draft generated by curriculum:import-presets from {$ay} presets. Review, then publish.",
                'is_active'          => 1,
                'version_number'     => 1,
                'status'             => 'draft',
                'is_current'         => 0,
            ]);
            $curriculumId = (int) $curricula->getInsertID();
            $curricula->update($curriculumId, ['base_curriculum_id' => $curriculumId]);

            $now = date('Y-m-d H:i:s');
            foreach ($entries as $e) {
                $db->table('curriculum_subjects')->insert([
                    'curriculum_id' => $curriculumId,
                    'subject_id'    => $e['subject_id'],
                    'year_level'    => $e['year_level'],
                    'semester'      => $e['semester'],
                    'is_required'   => 1,
                    'is_laboratory' => $e['is_laboratory'],
                    'is_active'     => 1,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                $problems[] = "FAILED to write {$code}; nothing was saved for it.";
                continue;
            }

            $created++;
            CLI::write("  -> draft created (curriculum_id {$curriculumId}).", 'green');
        }

        if ($problems !== []) {
            CLI::write("\nNotes:", 'yellow');
            foreach ($problems as $p) {
                CLI::write('  - ' . $p);
            }
        }

        CLI::write($commit
            ? "\nDone: {$created} draft curriculum(s) created. Review under Admin -> Curriculum, then Publish."
            : "\nDry run only — nothing written. Re-run with --commit to create the drafts.", 'yellow');

        return EXIT_SUCCESS;
    }
}

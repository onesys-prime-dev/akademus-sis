<?php

namespace App\Commands;

use App\Models\CurriculumModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * CLI twin of Admin -> Curriculum -> Publish: calls the same
 * CurriculumModel::publish(), so a draft becomes an immutable published
 * version and the program's "current" one. Dry-run by default.
 */
class CurriculumPublish extends BaseCommand
{
    protected $group       = 'Curriculum';
    protected $name        = 'curriculum:publish';
    protected $description = 'Publishes a draft curriculum version — it becomes immutable and current for its program (dry-run unless --commit).';
    protected $usage       = 'curriculum:publish <curriculum_id> [--commit]';
    protected $arguments   = ['curriculum_id' => 'The draft curriculum version to publish.'];
    protected $options     = ['--commit' => 'Actually publish. Without it nothing is changed.'];

    public function run(array $params)
    {
        $id         = (int) ($params[0] ?? 0);
        $commit     = CLI::getOption('commit') !== null;
        $curricula  = new CurriculumModel();
        $curriculum = $curricula->find($id);

        if ($curriculum === null) {
            CLI::error('Curriculum not found. Usage: ' . $this->usage);

            return EXIT_ERROR;
        }

        $subjects = Database::connect()->table('curriculum_subjects')->where('curriculum_id', $id)->where('is_active', 1)->countAllResults();

        CLI::write(($commit ? 'COMMIT' : 'DRY RUN') . " — {$curriculum['curriculum_code']} v{$curriculum['version_number']} is '{$curriculum['status']}' with {$subjects} active subject(s).", 'yellow');

        if ($curriculum['status'] !== 'draft') {
            CLI::error('Only a draft can be published.');

            return EXIT_ERROR;
        }

        if (! $commit) {
            CLI::write('Dry run only — re-run with --commit to publish. Publishing is irreversible: changes afterwards need a new version.', 'yellow');

            return EXIT_SUCCESS;
        }

        $result = $curricula->publish($id);

        if (! $result['ok']) {
            CLI::error($result['error'] ?? 'Could not publish.');

            return EXIT_ERROR;
        }

        CLI::write("Published {$curriculum['curriculum_code']} — it is now the current curriculum for its program.", 'green');

        return EXIT_SUCCESS;
    }
}

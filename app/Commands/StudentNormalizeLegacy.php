<?php

namespace App\Commands;

use App\Libraries\StudentAccountNormalizer;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Audits every account in the `student` Shield group against current SIS
 * behavior (students row, program/curriculum, enrollment, tuition) and
 * repairs whatever is unambiguously derivable — see
 * App\Libraries\StudentAccountNormalizer for exactly what "derivable"
 * means and what it refuses to touch. Dry-run by default.
 */
class StudentNormalizeLegacy extends BaseCommand
{
    protected $group       = 'Students';
    protected $name        = 'student:normalize-legacy';
    protected $description = 'Audits and repairs legacy student accounts against current SIS behavior (dry-run unless --commit).';
    protected $usage       = 'student:normalize-legacy [user_id] [--commit]';
    protected $arguments   = ['user_id' => 'Limit to one user_id. Omit to audit every student-group account.'];
    protected $options     = ['--commit' => 'Actually repair. Without it, only the plan is printed.'];

    public function run(array $params)
    {
        $commit = CLI::getOption('commit') !== null;
        $only   = isset($params[0]) ? (int) $params[0] : null;

        $userIds = $only !== null
            ? [$only]
            : array_map('intval', array_column(
                db_connect()->table('auth_groups_users')->select('user_id')->where('group', 'student')->get()->getResultArray(),
                'user_id'
            ));

        $normalizer = new StudentAccountNormalizer();
        $tally = ['ok' => 0, 'repairable' => 0, 'ambiguous' => 0, 'not_student' => 0];

        foreach ($userIds as $userId) {
            $classification = $normalizer->classify($userId);
            $tally[$classification['status']] = ($tally[$classification['status']] ?? 0) + 1;
            $username = $classification['user']['username'] ?? "#{$userId}";

            CLI::write("--- {$username} (#{$userId}): {$classification['status']} ---", $this->colorFor($classification['status']));

            foreach ($classification['reasons'] as $reason) {
                CLI::write('  ' . $reason);
            }

            if ($classification['status'] !== StudentAccountNormalizer::STATUS_REPAIRABLE) {
                continue;
            }

            $result = $normalizer->normalize($userId, $commit);

            if (! $result['ok']) {
                CLI::write('  REFUSED: ' . $result['error'], 'red');

                continue;
            }

            foreach ($result['steps'] as $step) {
                CLI::write(($commit ? '  + ' : '  (dry run) would: ') . $step, $commit ? 'green' : 'yellow');
            }
        }

        CLI::newLine();
        CLI::write("Summary — ok: {$tally['ok']}, repairable: {$tally['repairable']}, ambiguous: {$tally['ambiguous']}, not student: {$tally['not_student']}", 'cyan');

        if (! $commit && $tally['repairable'] > 0) {
            CLI::write('Dry run only — re-run with --commit to apply.', 'yellow');
        }

        return EXIT_SUCCESS;
    }

    private function colorFor(string $status): string
    {
        return match ($status) {
            StudentAccountNormalizer::STATUS_OK => 'green',
            StudentAccountNormalizer::STATUS_REPAIRABLE => 'yellow',
            StudentAccountNormalizer::STATUS_AMBIGUOUS => 'red',
            default => 'white',
        };
    }
}

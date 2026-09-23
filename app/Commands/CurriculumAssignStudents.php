<?php

namespace App\Commands;

use App\Models\CurriculumModel;
use App\Models\StudentModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Pins every not-yet-assigned student of the curriculum's program to that
 * (published) curriculum version, through StudentModel::assignCurriculum()
 * so each assignment is written to student_curriculum_logs like a manual one.
 * Dry-run by default.
 */
class CurriculumAssignStudents extends BaseCommand
{
    protected $group       = 'Curriculum';
    protected $name        = 'curriculum:assign-students';
    protected $description = 'Assigns a published curriculum version to the program\'s students that have none (dry-run unless --commit).';
    protected $usage       = 'curriculum:assign-students <curriculum_id> [--commit]';
    protected $arguments   = ['curriculum_id' => 'The published curriculum version to assign.'];
    protected $options     = ['--commit' => 'Actually assign. Without it nothing is changed.'];

    public function run(array $params)
    {
        $id     = (int) ($params[0] ?? 0);
        $commit = CLI::getOption('commit') !== null;
        $db     = Database::connect();

        $curriculum = (new CurriculumModel())->find($id);

        if ($curriculum === null) {
            CLI::error('Curriculum not found. Usage: ' . $this->usage);

            return EXIT_ERROR;
        }

        if ($curriculum['status'] !== 'published') {
            CLI::error("Curriculum {$curriculum['curriculum_code']} is '{$curriculum['status']}' — publish it first.");

            return EXIT_ERROR;
        }

        $students = $db->table('students')
            ->where('program_id', $curriculum['program_id'])
            ->where('curriculum_id', null)
            ->where('student_status', 'active')
            ->get()
            ->getResultArray();

        CLI::write(($commit ? 'COMMIT' : 'DRY RUN') . " — {$curriculum['curriculum_code']}: " . count($students) . ' active student(s) without a curriculum.', 'yellow');

        $model = new StudentModel();
        $done  = 0;

        foreach ($students as $s) {
            // Enrollments the new "must be in your curriculum" rule would
            // not have allowed — informational, nothing is dropped.
            $outside = $db->table('enrollments e')
                ->select('e.subject_code')
                ->join('subjects sub', 'sub.subject_code = e.subject_code')
                ->join('curriculum_subjects cs', 'cs.subject_id = sub.subject_id AND cs.curriculum_id = ' . (int) $id . ' AND cs.is_active = 1', 'left')
                ->where('e.user_id', $s['user_id'])
                ->where('e.status', 'enrolled')
                ->where('cs.curriculum_subject_id', null)
                ->get()
                ->getResultArray();

            $note = $outside === [] ? '' : '  (already enrolled outside it: ' . implode(', ', array_column($outside, 'subject_code')) . ')';
            CLI::write("  {$s['student_number']}{$note}");

            if (! $commit) {
                continue;
            }

            $r = $model->assignCurriculum((int) $s['student_id'], $id, 'Initial curriculum assignment (backfill via curriculum:assign-students)', null);

            if ($r['ok']) {
                $done++;
            } else {
                CLI::error("    failed: " . ($r['error'] ?? 'unknown error'));
            }
        }

        CLI::write($commit ? "\nAssigned {$done} student(s)." : "\nDry run only — re-run with --commit to assign.", 'yellow');

        return EXIT_SUCCESS;
    }
}

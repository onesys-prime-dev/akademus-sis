<?php

namespace App\Commands;

use App\Mappers\ScheduleDataMapper;
use App\Services\Exceptions\PythonEngineException;
use App\Services\PythonSchedulerClient;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * `php spark engine:test` — smoke-tests the PHP <-> FastAPI bridge
 * end-to-end: builds a real dataset from MySQL via ScheduleDataMapper,
 * checks /health, runs it through /api/v1/pipeline/run via
 * PythonSchedulerClient, and prints the solved schedule's metrics.
 *
 * This is a read-only diagnostic: it never calls
 * ScheduleDataMapper::persistPipelineResult(), so running it does not
 * write anything back to the database.
 */
class TestEngineBridge extends BaseCommand
{
    protected $group       = 'Custom';
    protected $name        = 'engine:test';
    protected $description = 'Smoke-tests the PHP <-> Python scheduling engine bridge against real DB data (read-only).';
    protected $usage       = 'engine:test [--academic-year=<value>]';
    protected $options     = [
        '--academic-year' => 'Academic year value to pull course_offerings for. Defaults to the most recent one found in the database.',
    ];

    public function run(array $params)
    {
        $academicYear = $params['academic-year'] ?? CLI::getOption('academic-year');

        CLI::write('=== Engine Bridge Smoke Test ===', 'yellow');

        // ---------------------------------------------------- 1. health check
        $client = new PythonSchedulerClient();
        CLI::write('Checking engine health...');
        if (! $client->checkHealth()) {
            CLI::error('Python scheduling engine is unreachable or unhealthy. Is `uvicorn api.main:app` running?');
            return;
        }
        CLI::write('Engine is healthy.', 'green');

        // ---------------------------------------------------- 2. pick an academic year
        $db = db_connect();
        if ($academicYear === null) {
            $academicYear = $db->table('course_offerings')
                ->select('academic_year')
                ->orderBy('offering_id', 'DESC')
                ->get(1)
                ->getRow('academic_year');
        }

        if ($academicYear === null) {
            CLI::error('No course_offerings rows exist to build a sample dataset from. Seed some offerings first.');
            return;
        }

        CLI::write("Using academic_year = {$academicYear}");

        // ---------------------------------------------------- 3. build dataset from MySQL
        $mapper = new ScheduleDataMapper();
        $dataset = $mapper->buildPipelineDataset((string) $academicYear);

        $courseCount = count($dataset['courses']);
        $roomCount = count($dataset['rooms']);
        $profCount = count($dataset['professors']);

        CLI::write("Built dataset: {$courseCount} course(s), {$roomCount} room(s), {$profCount} professor(s).");

        if ($courseCount === 0) {
            CLI::error('No schedulable courses found for this academic year (no offerings with an assigned professor). Nothing to send to the engine.');
            return;
        }

        // ---------------------------------------------------- 4. call the engine
        CLI::write('Submitting to POST /api/v1/pipeline/run...');

        try {
            $response = $client->runIntegratedPipeline(['dataset' => $dataset]);
        } catch (PythonEngineException $e) {
            CLI::error('Engine call failed: ' . $e->getMessage());
            if ($e->getEngineResponse() !== null) {
                CLI::write(json_encode($e->getEngineResponse(), JSON_PRETTY_PRINT));
            }
            return;
        }

        // ---------------------------------------------------- 5. report metrics
        $sections = $response['schedule'] ?? [];
        $totalSessions = array_sum(array_map(static fn ($s) => count($s['sessions'] ?? []), $sections));

        CLI::write('');
        CLI::write('=== Result ===', 'yellow');
        CLI::write('Stage 1 (sectioning): ' . ($response['stage1_passed'] ? CLI::color('PASS', 'green') : CLI::color('FAIL', 'red')));
        CLI::write('Stage 2 (scheduling): ' . ($response['stage2_passed'] ? CLI::color('PASS', 'green') : CLI::color('FAIL', 'red')));
        CLI::write('Overall:              ' . ($response['overall_passed'] ? CLI::color('PASS', 'green') : CLI::color('FAIL', 'red')));
        CLI::write("Sections produced:    {$courseCount} course(s) -> " . count($sections) . ' section(s)');
        CLI::write("Sessions scheduled:   {$totalSessions}");
        CLI::write('');
        CLI::write('--- Validator report ---');
        CLI::write($response['validation_report'] ?? '(none)');
    }
}

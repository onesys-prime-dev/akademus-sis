<?php

namespace App\Services;

use App\Services\Exceptions\PythonEngineException;
use CodeIgniter\HTTP\CURLRequest;
use Config\Services;
use Throwable;

/**
 * Typed HTTP client for the standalone FastAPI scheduling engine
 * (api/main.py — see PYTHON_ENGINE_URL in .env). This class is the
 * ONLY place in the PHP app that knows the engine's URL or JSON
 * envelope shapes; callers get back plain PHP arrays or a bool, never
 * an HTTP response object.
 */
class PythonSchedulerClient
{
    private string $baseUrl;
    private int $timeoutSeconds;
    private CURLRequest $client;

    public function __construct(?string $baseUrl = null, int $timeoutSeconds = 30)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string) env('PYTHON_ENGINE_URL', 'http://localhost:8000'), '/');
        $this->timeoutSeconds = $timeoutSeconds;

        $this->client = Services::curlrequest([
            'baseURI' => $this->baseUrl . '/',
            'timeout' => $this->timeoutSeconds,
            'http_errors' => false, // we inspect the status code ourselves below
        ]);
    }

    /**
     * POST /api/v1/scheduler/solve
     *
     * @param array<string, mixed> $payload {"dataset": {...SchedulerDataset...}}
     * @return array<string, mixed> SchedulerSolveResponse
     * @throws PythonEngineException
     */
    public function solveSchedule(array $payload): array
    {
        return $this->postJson('api/v1/scheduler/solve', $payload);
    }

    /**
     * POST /api/v1/sections/balance
     *
     * @param array<string, mixed> $payload {"dataset": {...BalancingDataset...}}
     * @return array<string, mixed> SectionsBalanceResponse
     * @throws PythonEngineException
     */
    public function balanceSections(array $payload): array
    {
        return $this->postJson('api/v1/sections/balance', $payload);
    }

    /**
     * POST /api/v1/pipeline/run
     *
     * @param array<string, mixed> $payload {"dataset": {...PipelineDataset...}}
     * @return array<string, mixed> PipelineRunResponse
     * @throws PythonEngineException
     */
    public function runIntegratedPipeline(array $payload): array
    {
        return $this->postJson('api/v1/pipeline/run', $payload);
    }

    /**
     * POST /api/v1/students/audit
     *
     * @param array<string, mixed> $payload {"manifest": {...StudentManifest...}}
     * @return array<string, mixed> StudentAuditResponse
     * @throws PythonEngineException
     */
    public function auditStudentConflicts(array $payload): array
    {
        return $this->postJson('api/v1/students/audit', $payload);
    }

    /**
     * GET /health — never throws; a down/unreachable engine simply
     * reports as unhealthy rather than raising an exception, since
     * callers typically want to branch on this (e.g. feature-flag a
     * "scheduling engine offline" banner) rather than handle a throw.
     */
    public function checkHealth(): bool
    {
        try {
            $response = $this->client->get('health', ['timeout' => min($this->timeoutSeconds, 5)]);
        } catch (Throwable $e) {
            log_message('warning', 'PythonSchedulerClient::checkHealth() failed to reach engine: {msg}', ['msg' => $e->getMessage()]);
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $body = json_decode($response->getBody(), true);
        return is_array($body) && ($body['status'] ?? null) === 'ok';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws PythonEngineException
     */
    private function postJson(string $path, array $payload): array
    {
        try {
            $response = $this->client->post($path, [
                'json' => $payload,
                'timeout' => $this->timeoutSeconds,
            ]);
        } catch (Throwable $e) {
            throw new PythonEngineException(
                "Could not reach Python scheduling engine at {$this->baseUrl}/{$path}: {$e->getMessage()}",
                null,
                0,
                $e
            );
        }

        $status = $response->getStatusCode();
        $decoded = json_decode($response->getBody(), true);

        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) && isset($decoded['detail'])
                ? (is_string($decoded['detail']) ? $decoded['detail'] : json_encode($decoded['detail']))
                : "Engine returned HTTP {$status}";

            throw new PythonEngineException(
                "Python scheduling engine request to {$path} failed: {$message}",
                is_array($decoded) ? $decoded : null,
                $status
            );
        }

        if (! is_array($decoded)) {
            throw new PythonEngineException("Python scheduling engine returned a non-JSON or malformed response from {$path}.");
        }

        return $decoded;
    }
}

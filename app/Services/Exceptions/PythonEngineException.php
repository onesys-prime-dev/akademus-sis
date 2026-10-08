<?php

namespace App\Services\Exceptions;

use RuntimeException;

/**
 * Thrown by PythonSchedulerClient whenever the FastAPI scheduling
 * engine is unreachable, times out, or returns a non-2xx response.
 * Kept as its own type (rather than a bare RuntimeException) so
 * callers can catch engine-specific failures distinctly from other
 * runtime errors.
 */
class PythonEngineException extends RuntimeException
{
    /** @var array<string, mixed>|null Decoded JSON error body from the engine, if any. */
    private ?array $engineResponse;

    public function __construct(string $message, ?array $engineResponse = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->engineResponse = $engineResponse;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEngineResponse(): ?array
    {
        return $this->engineResponse;
    }
}

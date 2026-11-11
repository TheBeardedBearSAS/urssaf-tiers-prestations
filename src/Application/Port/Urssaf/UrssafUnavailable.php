<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

use RuntimeException;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RetryableException;
use Throwable;

/**
 * Transient failure: network error, timeout, HTTP 429 (quota), 500 (ERREUR_TECHNIQUE), 503. Retryable.
 *
 * {@see self::mayHaveReachedUrssaf()} tells whether URSSAF may have processed the call anyway: true for transport
 * errors (e.g. a timeout once the request was sent) and HTTP 5xx answers of the API call; false for HTTP 429 (refused
 * before processing) and for every failure of the token request (the API call itself was never sent). A write
 * (methods 010 / 050) that may have reached URSSAF must never be resent blindly.
 */
final class UrssafUnavailable extends RuntimeException implements RetryableException
{
    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly ?int $httpStatus,
        private readonly ?int $retryAfterSeconds,
        private readonly bool $mayHaveReachedUrssaf,
        ?Throwable $previous,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /**
     * The API call answered a transient HTTP status (429, 5xx).
     *
     * @param int|null $retryAfterSeconds value of the Retry-After header when provided
     */
    public static function withStatus(Operation $operation, int $httpStatus, ?int $retryAfterSeconds): self
    {
        return new self(
            \sprintf('URSSAF method %s is temporarily unavailable (HTTP %d).', $operation->methodNumber(), $httpStatus),
            $operation,
            $httpStatus,
            $retryAfterSeconds,
            $httpStatus >= 500,
            null,
        );
    }

    /**
     * The API call failed without an HTTP answer (e.g. connection error, timeout).
     */
    public static function dueToTransportError(Operation $operation, Throwable $previous): self
    {
        return new self(
            \sprintf('URSSAF method %s could not be reached.', $operation->methodNumber()),
            $operation,
            null,
            null,
            true,
            $previous,
        );
    }

    /**
     * The access token needed by the call could not be obtained (transient HTTP status, or transport error when
     * $httpStatus is null): the API call itself was not sent.
     */
    public static function whileAuthenticating(
        Operation $operation,
        ?int $httpStatus,
        ?int $retryAfterSeconds,
        ?Throwable $previous,
    ): self {
        return new self(
            \sprintf('URSSAF authentication for method %s is temporarily unavailable.', $operation->methodNumber()),
            $operation,
            $httpStatus,
            $retryAfterSeconds,
            false,
            $previous,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    /**
     * Null for transport errors (no HTTP response).
     */
    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    public function isQuotaExceeded(): bool
    {
        return $this->httpStatus === 429;
    }

    /**
     * True when URSSAF may have processed the call despite the failure (see the class documentation).
     */
    public function mayHaveReachedUrssaf(): bool
    {
        return $this->mayHaveReachedUrssaf;
    }
}

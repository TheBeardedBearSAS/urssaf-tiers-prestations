<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

use RuntimeException;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\UrssafException;

/**
 * URSSAF refused the whole call (HTTP 400 validation or business error, e.g. ERREUR_VALIDATION,
 * ERR_DGFIP_R2P_INCONNU, ERR_RECHERCHE_PARTICULIER). Not retryable as is. The gateway classifies the errors
 * ({@see RefusalReason}): use {@see self::reason()} rather than comparing error codes.
 *
 * Note: method 050 per-item business errors are NOT thrown; they are reported through {@see SubmissionOutcome}.
 */
final class UrssafRequestRejected extends RuntimeException implements UrssafException
{
    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly int $httpStatus,
        private readonly ApiErrors $errors,
        private readonly RefusalReason $reason,
    ) {
        parent::__construct($message, $httpStatus);
    }

    public static function withErrors(
        Operation $operation,
        int $httpStatus,
        ApiErrors $errors,
        RefusalReason $reason = RefusalReason::Generic,
    ): self {
        return new self(
            \sprintf(
                'URSSAF method %s rejected the request (HTTP %d): %s.',
                $operation->methodNumber(),
                $httpStatus,
                $errors->isEmpty() ? 'no error code' : implode(', ', $errors->codes()),
            ),
            $operation,
            $httpStatus,
            $errors,
            $reason,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function errors(): ApiErrors
    {
        return $this->errors;
    }

    public function hasErrorCode(string $code): bool
    {
        return $this->errors->hasCode($code);
    }

    public function reason(): RefusalReason
    {
        return $this->reason;
    }

    /**
     * Method 020: the customer has no active registration with this provider any more.
     */
    public function isNoActiveRegistration(): bool
    {
        return $this->reason === RefusalReason::NoActiveRegistration;
    }

    /**
     * Method 010: the customer is already registered with this provider.
     */
    public function isAlreadyRegistered(): bool
    {
        return $this->reason === RefusalReason::AlreadyRegistered;
    }
}

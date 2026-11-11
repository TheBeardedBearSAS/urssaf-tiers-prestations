<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Exception;

use RuntimeException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RetryableException;

/**
 * Thrown by the submission handlers after a temporary URSSAF failure (unavailable, quota exceeded, transport error):
 * the affected records were moved back to pending (or left for reconciliation) and the message must be retried later.
 * The original failure (with its retry-after delay when known) is the previous exception.
 */
final class RetryableUrssafFailure extends RuntimeException implements RetryableException
{
    public static function causedBy(RetryableException $previous): self
    {
        return new self('URSSAF is temporarily unavailable: the submission must be retried later.', 0, $previous);
    }
}

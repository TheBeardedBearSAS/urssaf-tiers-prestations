<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception;

use RuntimeException;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\UrssafException;
use Throwable;

/**
 * URSSAF answered with something the contract does not describe (unknown HTTP status, invalid JSON,
 * missing field, unparseable value). Not retryable: the response must be investigated.
 */
final class UnexpectedUrssafResponse extends RuntimeException implements UrssafException
{
    public static function invalidPayload(string $reason): self
    {
        return new self(\sprintf('Unexpected URSSAF response: %s.', $reason));
    }

    public static function causedBy(string $reason, Throwable $previous): self
    {
        return new self(\sprintf('Unexpected URSSAF response: %s.', $reason), 0, $previous);
    }

    public static function unexpectedStatus(Operation $operation, int $httpStatus): self
    {
        return new self(
            \sprintf('Unexpected URSSAF response for method %s: HTTP %d.', $operation->methodNumber(), $httpStatus),
            $httpStatus,
        );
    }
}

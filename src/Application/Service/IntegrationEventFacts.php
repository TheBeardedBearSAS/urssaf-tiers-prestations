<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Service;

use DateTimeImmutable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Common values of the integration events: the event id is derived from the fact itself (the same fact always gets the
 * same id, so that the host can deduplicate), the instant is ISO 8601 UTC.
 */
final class IntegrationEventFacts
{
    /**
     * @param list<?string> $facts what distinguishes the fact from the other facts of the same type and record
     */
    public static function id(string $type, string $aggregateId, DateTimeImmutable $occurredAt, array $facts): string
    {
        $hash = hash('sha256', serialize([$type, $aggregateId, self::instant($occurredAt), $facts]));

        return \sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
    }

    public static function instant(DateTimeImmutable $occurredAt): string
    {
        return Instant::fromDateTime($occurredAt)->toIso8601();
    }
}

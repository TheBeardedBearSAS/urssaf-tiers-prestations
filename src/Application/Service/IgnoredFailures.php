<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Service;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\FailureReporter;
use Throwable;

/**
 * Default {@see FailureReporter} of the {@see UnitOfWork}: the failures are dropped. Replace it with one that logs
 * (the framework bridges provide a PSR-3 one).
 */
final readonly class IgnoredFailures implements FailureReporter
{
    public function eventPublicationFailed(Throwable $failure, array $events): void {}
}

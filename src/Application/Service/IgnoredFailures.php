<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

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

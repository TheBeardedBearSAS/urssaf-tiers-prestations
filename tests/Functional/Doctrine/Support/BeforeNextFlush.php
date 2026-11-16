<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;

/**
 * Runs an action once, right before the next flush of an entity manager: the window in which a concurrent writer can
 * win the race against a uniqueness check that has already passed.
 */
final class BeforeNextFlush
{
    private bool $fired = false;

    /**
     * @param Closure(): void $action
     */
    private function __construct(private readonly Closure $action) {}

    /**
     * @param Closure(): void $action
     * @param-immediately-invoked-callable $action
     */
    public static function run(EntityManagerInterface $entityManager, Closure $action): void
    {
        $entityManager->getEventManager()->addEventListener(Events::preFlush, new self($action));
    }

    public function preFlush(): void
    {
        if ($this->fired) {
            return;
        }
        $this->fired = true;
        ($this->action)();
    }
}

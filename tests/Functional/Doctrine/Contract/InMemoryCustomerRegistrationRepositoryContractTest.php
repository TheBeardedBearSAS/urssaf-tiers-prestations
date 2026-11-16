<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryCustomerRegistrationRepository;

/**
 * Proves the contract itself: the reference in-memory repository satisfies the same scenarios as the Doctrine one.
 */
final class InMemoryCustomerRegistrationRepositoryContractTest extends TestCase
{
    use CustomerRegistrationRepositoryContract;

    protected function createRepository(): CustomerRegistrationRepository
    {
        return new InMemoryCustomerRegistrationRepository();
    }
}

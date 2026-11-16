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
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryPaymentRequestRepository;

/**
 * Proves the contract itself: the reference in-memory repository satisfies the same scenarios as the Doctrine one.
 */
final class InMemoryPaymentRequestRepositoryContractTest extends TestCase
{
    use PaymentRequestRepositoryContract;

    protected function createRepository(): PaymentRequestRepository
    {
        return new InMemoryPaymentRequestRepository();
    }
}

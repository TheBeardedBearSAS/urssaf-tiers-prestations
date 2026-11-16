<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrinePaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PollingWindow;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\StatementParameterRecorder;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\TestDatabase;

/**
 * The raw URSSAF status codes are bound as text (the column is a string), derived from the domain enum.
 */
final class DoctrinePollableParametersTest extends TestCase
{
    public function testFinalAndSettledStatusCodesAreBoundAsStrings(): void
    {
        $recorder = new StatementParameterRecorder();
        $repository = new DoctrinePaymentRequestRepository(TestDatabase::entityManager([$recorder->middleware()]));

        $repository->findPollable(
            TenantId::fromString('tenant-1'),
            Instant::fromIso8601('2022-04-01T00:00:00Z'),
            new PollingWindow(),
            10,
        );

        $statusCodes = array_map(static fn(PaymentRequestStatus $status): int => $status->value, PaymentRequestStatus::cases());
        $bound = array_values(array_filter(
            $recorder->lastBoundValues(),
            static fn(bool|float|int|string|null $value): bool => is_numeric($value) && \in_array((int) $value, $statusCodes, true),
        ));

        static::assertNotSame([], $bound);
        static::assertSame(array_map(strval(...), $bound), $bound);
    }
}

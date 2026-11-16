<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllPaymentRequestStatuses;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllPaymentRequestStatusesHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;
use Throwable;

final class SyncAllPaymentRequestStatusesHandlerTest extends TestCase
{
    public function testSyncsEveryTenantAndReportsRefusedCredentials(): void
    {
        $harness = StatusScenario::harness(2);
        $harness->paymentRequest(3, SubmissionState::Submitted, Harness::OTHER_TENANT);
        $harness->paymentRequest(4, SubmissionState::Submitted, 'tenant-3');
        $harness->paymentRequest(5, SubmissionState::Submitted, 'tenant-3');
        $harness->paymentRequest(6, SubmissionState::Pending, 'tenant-4');
        $harness->gateway->willFindWith(static fn(PaymentRequestSearch $search): array|Throwable => $search->paymentRequestIds()[0]->value() === 'urssaf-3'
            ? UrssafAuthenticationFailed::withStatus(403)
            : []);
        $handler = new SyncAllPaymentRequestStatusesHandler($harness->paymentRequests, StatusScenario::handler($harness), $harness->clock, $harness->policy);

        $report = $handler(new SyncAllPaymentRequestStatuses(1));

        static::assertEquals([
            new BatchItem('pr-1', ItemOutcome::NotFound),
            new BatchItem('tenant-2', ItemOutcome::InvalidCredentials),
            new BatchItem('pr-4', ItemOutcome::NotFound),
        ], $report->items());
        static::assertSame(['tenant-1', 'tenant-2', 'tenant-3'], $harness->gateway->tenants());
    }
}

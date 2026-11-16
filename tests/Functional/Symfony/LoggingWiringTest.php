<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\FailureReporter;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRequested;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Logging\PsrLoggerFailureReporter;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Scenario;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\ThrowingListener;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;

final class LoggingWiringTest extends TestCase
{
    public function testTheFailureReporterIsThePsrLoggerOne(): void
    {
        $harness = Harness::boot();

        static::assertInstanceOf(PsrLoggerFailureReporter::class, $harness->privateService(FailureReporter::class, FailureReporter::class));
    }

    public function testAnEventPublicationFailureAfterCommitIsLoggedAndTheStateIsKept(): void
    {
        $harness = Harness::boot();
        $harness->privateService('event_dispatcher', EventDispatcherInterface::class)->addListener(
            CustomerRegistrationRequested::class,
            new ThrowingListener(),
        );

        $id = Scenario::pendingRegistration($harness);

        static::assertNotSame('', $id->value());
        $records = $harness->privateService('logger', CapturingLogger::class)->records();
        static::assertCount(1, $records);
        static::assertSame('error', $records[0]['level']);
        static::assertSame('Domain event publication failed after commit.', $records[0]['message']);
        static::assertSame(
            ['exception' => LogicException::class, 'message' => 'listener exploded', 'events' => [CustomerRegistrationRequested::class]],
            $records[0]['context'],
        );
    }

    public function testTheGatewayLogsThroughThePackageLogger(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_SUBMIT, 200, '[]');
        Scenario::pendingPaymentRequest($harness);
        $logger = $harness->privateService('logger', CapturingLogger::class);
        $logger->clear();

        $harness->command('urssaf:payment-requests:submit')->execute([]);

        $errors = array_values(array_filter($logger->records(), static fn(array $record): bool => $record['level'] === 'error'));
        static::assertCount(1, $errors);
        static::assertStringContainsString('different number of outcomes', $errors[0]['message']);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\PaymentRequestView;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Scenario;

final class SubmitPendingPaymentRequestsCommandTest extends TestCase
{
    private const SUBMIT = 'urssaf:payment-requests:submit-pending';

    public function testSubmitDryRunListsThePendingPaymentRequests(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingPaymentRequest($harness);

        $tester = $harness->command(self::SUBMIT);

        static::assertSame(0, $tester->execute(['--dry-run' => true]));
        static::assertSame([['Tenant', 'Record'], ['tenant-1', $id->value()]], Output::rows($tester->getDisplay()));
        static::assertStringContainsString('Dry run: 1 record(s)', $tester->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_SUBMIT));
        static::assertSame('pending', self::state($harness, $id));
    }

    public function testSubmitSendsThePendingPaymentRequestsOfTheGivenTenant(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingPaymentRequest($harness);

        $tester = $harness->command(self::SUBMIT);

        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-2']));
        static::assertSame('pending', self::state($harness, $id));
        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-1', '--limit' => '3']));
        static::assertStringContainsString('1 record(s) processed (accepted: 1).', $tester->getDisplay());
        static::assertSame('submitted', self::state($harness, $id));
    }

    public function testSubmitDryRunRespectsTheTenantOption(): void
    {
        $harness = Harness::boot();
        Scenario::pendingPaymentRequest($harness);

        $tester = $harness->command(self::SUBMIT);

        static::assertSame(0, $tester->execute(['--dry-run' => true, '--tenant' => 'tenant-2']));
        static::assertStringContainsString('Dry run: 0 record(s)', $tester->getDisplay());
    }

    public function testATemporaryFailureBeforeUrssafProcessedTheCallFailsTheCommandAndRequeuesTheRequest(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingPaymentRequest($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_SUBMIT, 429, '{}');

        $tester = $harness->command(self::SUBMIT);

        static::assertSame(1, $tester->execute([]));
        static::assertStringContainsString('| tenant-1', $tester->getDisplay());
        static::assertStringContainsString('urssaf.unavailable', $tester->getDisplay());
        static::assertSame('pending', self::state($harness, $id));
    }

    public function testAServerErrorMayHaveReachedUrssafSoTheRequestIsNeverResentBlindly(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingPaymentRequest($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_SUBMIT, 503, '{}');

        $tester = $harness->command(self::SUBMIT);

        static::assertSame(1, $tester->execute([]));
        static::assertStringContainsString('urssaf.unavailable', $tester->getDisplay());
        static::assertSame('submitting', self::state($harness, $id));
        $calls = $harness->urssaf->calls(HttpScript::ROUTE_SUBMIT);
        static::assertSame(0, $tester->execute([]));
        static::assertSame($calls, $harness->urssaf->calls(HttpScript::ROUTE_SUBMIT));
    }

    public function testSubmitNeedsATenantWhenNoneIsConfigured(): void
    {
        $tester = Harness::boot(['credentials' => null, 'credentials_provider' => 'test.credentials'])->command(self::SUBMIT);

        static::assertSame(1, $tester->execute([]));
        static::assertStringContainsString('No tenant to process', $tester->getDisplay());
    }

    private static function state(Harness $harness, LocalPaymentRequestId $id): string
    {
        return self::view($harness, $id)->state;
    }

    private static function view(Harness $harness, LocalPaymentRequestId $id): PaymentRequestView
    {
        return $harness->get(FindPaymentRequestsHandler::class)(FindPaymentRequests::byIds(TenantId::fromString('tenant-1'), $id))[0];
    }
}

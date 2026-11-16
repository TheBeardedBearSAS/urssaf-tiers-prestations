<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;

final class RegistrationEligibilityTest extends TestCase
{
    private static function period(string $start): EmploymentPeriod
    {
        return EmploymentPeriod::between(
            CustomerFixtures::at($start),
            CustomerFixtures::at('2026-11-10T18:00:00Z'),
            new DateTimeZone('Europe/Paris'),
        );
    }

    /**
     * @return iterable<string, array{RegistrationState}>
     */
    public static function provideStates(): iterable
    {
        foreach (RegistrationState::cases() as $state) {
            yield $state->value => [$state];
        }
    }

    #[DataProvider('provideStates')]
    public function testOnlyRegisteredCustomersCanReceivePaymentRequests(RegistrationState $state): void
    {
        $status = CustomerFixtures::registrationIn($state)->status();
        static::assertSame($state, $status->state());
        static::assertSame($state === RegistrationState::Registered, $status->canReceivePaymentRequests());

        if ($state === RegistrationState::Registered) {
            $status->ensureCanReceivePaymentRequests();
            $status->assertEmploymentPeriodAllowed(self::period(CustomerFixtures::ACCEPTED_AT));

            return;
        }
        $expected = InvariantViolation::because(
            'customer_registration.not_registered',
            \sprintf('Payment requests can only be sent to a registered customer (current state: "%s").', $state->value),
            ['state' => $state->value],
        );
        $exception = CustomerFixtures::assertViolationCode('customer_registration.not_registered', $status->ensureCanReceivePaymentRequests(...));
        static::assertSame($expected->getMessage(), $exception->getMessage());
        static::assertSame($expected->params(), $exception->params());
        $periodException = CustomerFixtures::assertViolationCode(
            'customer_registration.not_registered',
            static fn() => $status->assertEmploymentPeriodAllowed(self::period('2026-11-05T00:00:00Z')),
        );
        static::assertSame($expected->params(), $periodException->params());
    }

    public function testPeriodMustNotStartBeforeThePairingAcceptance(): void
    {
        $status = CustomerFixtures::registrationIn(RegistrationState::Registered)->status();
        $status->assertEmploymentPeriodAllowed(self::period('2026-11-01T08:30:00Z'));
        $status->assertEmploymentPeriodAllowed(self::period('2026-11-01T08:30:01Z'));

        $this->expectExceptionObject(InvariantViolation::because(
            'employment_period.before_pairing',
            'The employment period must not start before the pairing acceptance (2026-11-01T08:30:00Z).',
        ));

        $status->assertEmploymentPeriodAllowed(self::period('2026-11-01T08:29:59Z'));
    }

    public function testBeforePairingParameters(): void
    {
        $status = CustomerFixtures::registrationIn(RegistrationState::Registered)->status();

        $exception = CustomerFixtures::assertViolationCode(
            'employment_period.before_pairing',
            static fn() => $status->assertEmploymentPeriodAllowed(self::period('2026-11-01T00:00:00Z')),
        );
        static::assertSame(['pairing_accepted_at' => '2026-11-01T08:30:00Z'], $exception->params());
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RegistrationLocator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

final class RegistrationLocatorTest extends TestCase
{
    public function testLocatesByIdOrExternalReference(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::Pending);
        $harness->registration(2, RegistrationState::Registered);
        $locator = new RegistrationLocator($harness->registrations);

        static::assertSame('reg-2', $locator->locate(Harness::tenant(), CustomerSelector::byRegistrationId(RegistrationId::fromString('reg-2')))->id()->value());
        static::assertSame('reg-1', $locator->locate(Harness::tenant(), CustomerSelector::byExternalReference(ExternalReference::fromString('customer-1')))->id()->value());
    }

    public function testUnknownExternalReference(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::Pending, Harness::OTHER_TENANT);
        $locator = new RegistrationLocator($harness->registrations);

        $exception = self::notFound(static fn(): CustomerRegistration => $locator->locate(Harness::tenant(), CustomerSelector::byExternalReference(ExternalReference::fromString('customer-1'))));

        static::assertSame('customer_registration.not_found', $exception->violationCode());
        static::assertSame('customer-1', $exception->identifier());
    }

    public function testRegistrationOfAnotherTenant(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::Pending, Harness::OTHER_TENANT);
        $locator = new RegistrationLocator($harness->registrations);

        $exception = self::notFound(static fn(): CustomerRegistration => $locator->locate(Harness::tenant(), CustomerSelector::byRegistrationId(RegistrationId::fromString('reg-1'))));

        static::assertSame('customer_registration.not_found', $exception->violationCode());
        static::assertSame('reg-1', $exception->identifier());
    }

    /**
     * @param callable(): mixed $action
     *
     * @param-immediately-invoked-callable $action
     */
    private static function notFound(callable $action): RecordNotFound
    {
        try {
            $action();
        } catch (RecordNotFound $exception) {
            return $exception;
        }
        static::fail('Expected a RecordNotFound exception.');
    }
}

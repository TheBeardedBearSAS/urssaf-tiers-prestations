<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;

final class RegistrationBuilder
{
    private function __construct() {}

    /**
     * Pending registration created at the given instant (its last update).
     */
    public static function pending(string $id, string $tenant, string $reference, string $at): CustomerRegistration
    {
        return CustomerRegistration::request(
            RegistrationId::fromString($id),
            TenantId::fromString($tenant),
            ExternalReference::fromString($reference),
            CustomerFixtures::customer(),
            CustomerFixtures::at($at),
        );
    }

    /**
     * Registration awaiting pairing, last updated at the given instant. Events are released.
     */
    public static function awaiting(string $id, string $tenant, string $reference, string $urssafId, string $at): CustomerRegistration
    {
        $registration = self::pending($id, $tenant, $reference, '2026-11-01T00:00:00Z');
        $registration->startSubmission(CustomerFixtures::at('2026-11-01T00:00:01Z'));
        $registration->markSubmitted(UrssafCustomerId::fromString($urssafId), CustomerFixtures::at($at));
        $registration->releaseEvents();

        return $registration;
    }

    /**
     * Registration submitting since the given instant (start of its first attempt). Events are released.
     */
    public static function submitting(string $id, string $tenant, string $reference, string $startedAt): CustomerRegistration
    {
        $registration = self::pending($id, $tenant, $reference, '2026-11-01T00:00:00Z');
        $registration->startSubmission(CustomerFixtures::at($startedAt));
        $registration->releaseEvents();

        return $registration;
    }

    /**
     * Registration known by URSSAF (external reference "ref-<id>") whose last pairing status (method 020) was applied at
     * the given instant.
     * Events are released.
     */
    public static function paired(string $id, string $tenant, string $urssafId, PairingStatus $status, string $at): CustomerRegistration
    {
        $registration = self::awaiting($id, $tenant, 'ref-' . $id, $urssafId, '2026-11-01T00:00:02Z');
        $registration->applyPairingStatus($status, CustomerFixtures::at(CustomerFixtures::ACCEPTED_AT), CustomerFixtures::at($at));
        $registration->releaseEvents();

        return $registration;
    }

    /**
     * Registration rejected by URSSAF at the given instant. Events are released.
     */
    public static function rejected(string $id, string $tenant, string $reference, string $at): CustomerRegistration
    {
        $registration = self::submitting($id, $tenant, $reference, '2026-11-01T00:00:01Z');
        $registration->markRejected([CustomerFixtures::cause()], CustomerFixtures::at($at));
        $registration->releaseEvents();

        return $registration;
    }
}

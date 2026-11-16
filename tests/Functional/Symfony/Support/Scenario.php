<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support;

use TheBeardedBear\UrssafTiersPrestations\Application\Command\CreatePaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\CreatePaymentRequestHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomer;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomerHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncCustomerPairing;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncCustomerPairingHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PaymentRequestDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Input\DraftFixtures;

/**
 * Drives an application through the use cases to a given state (the official examples of the API reference).
 */
final class Scenario
{
    public const TENANT = 'tenant-1';
    public const EXTERNAL_REFERENCE = 'customer-1';

    private function __construct() {}

    /**
     * Registration in state "pending".
     */
    public static function pendingRegistration(Harness $harness, string $tenant = self::TENANT, string $reference = self::EXTERNAL_REFERENCE): RegistrationId
    {
        return $harness->get(RegisterCustomerHandler::class)(
            new RegisterCustomer(TenantId::fromString($tenant), ExternalReference::fromString($reference), CustomerFixtures::officialDraft()),
        );
    }

    /**
     * Registration in state "awaiting_pairing".
     */
    public static function submittedRegistration(Harness $harness, string $tenant = self::TENANT, string $reference = self::EXTERNAL_REFERENCE): RegistrationId
    {
        $id = self::pendingRegistration($harness, $tenant, $reference);
        if ($reference !== self::EXTERNAL_REFERENCE) {
            // Another customer, hence another URSSAF identifier.
            $harness->urssaf->respond(HttpScript::ROUTE_REGISTER, 200, \sprintf('{"idClient":"00000000-0000-4000-8000-%012d"}', crc32($reference)));
        }
        $harness->get(SubmitCustomerRegistrationHandler::class)(new SubmitCustomerRegistration($id));

        return $id;
    }

    /**
     * Registration in state "registered" (pairing valid since 2022-01-15T10:00:00Z).
     */
    public static function registeredCustomer(Harness $harness, string $tenant = self::TENANT, string $reference = self::EXTERNAL_REFERENCE): RegistrationId
    {
        $id = self::submittedRegistration($harness, $tenant, $reference);
        $harness->get(SyncCustomerPairingHandler::class)(new SyncCustomerPairing(TenantId::fromString($tenant), null));

        return $id;
    }

    /**
     * Payment request in state "pending" (official example, invoice 2022-AZ-00001).
     */
    public static function pendingPaymentRequest(Harness $harness, string $tenant = self::TENANT, string $reference = self::EXTERNAL_REFERENCE): LocalPaymentRequestId
    {
        self::registeredCustomer($harness, $tenant, $reference);

        return self::createPaymentRequest($harness, $tenant, $reference);
    }

    /**
     * Creates the official payment request for an already registered customer.
     */
    public static function createPaymentRequest(Harness $harness, string $tenant = self::TENANT, string $reference = self::EXTERNAL_REFERENCE): LocalPaymentRequestId
    {
        $official = DraftFixtures::official();
        $draft = $reference === self::EXTERNAL_REFERENCE ? $official : new PaymentRequestDraft(
            'invoice-' . $reference,
            '2022-AZ-' . $reference,
            $official->invoiceDate,
            $official->employmentPeriod,
            $official->totalInclTax,
            $official->totalExclTax,
            $official->lines,
            $official->deposit,
        );

        return $harness->get(CreatePaymentRequestHandler::class)(new CreatePaymentRequest(
            TenantId::fromString($tenant),
            CustomerSelector::byExternalReference(ExternalReference::fromString($reference)),
            $draft,
        ));
    }
}

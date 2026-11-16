<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Contract;

use DateTimeImmutable;
use DateTimeZone;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\AddressDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BankAccountDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BirthPlaceDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\BillingThirdPartyId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\LineAmountsDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PaymentRequestDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PeriodDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\ServiceLineDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\InvoicedCustomer;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestContext;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Fictitious, unique-per-run sandbox data (the sandbox requires unique email/phone pairs and invoice numbers).
 */
final class SandboxData
{
    public static function registration(TenantId $tenantId): CustomerRegistration
    {
        $draft = new CustomerDraft(
            '1',
            'Durand',
            'Martin',
            'Eric-Antoine',
            '1980-03-29',
            new BirthPlaceDraft('99100', '069', '001', 'Lyon'),
            // The sandbox requires a unique email/phone pair.
            '06' . str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT),
            \sprintf('contract.%s@example.com', bin2hex(random_bytes(6))),
            new AddressDraft('8', 'B', 'R', 'du Soleil', 'Batiment A', 'Le Beyssat', 'LYON 01', '69101', '69001', '99100'),
            new BankAccountDraft('FR7630006000011234567890189', 'BNPAFRPPXXX', 'Mme Jeanne Martin'),
        );

        return CustomerRegistration::request(
            RegistrationId::fromString('contract-' . bin2hex(random_bytes(6))),
            $tenantId,
            ExternalReference::fromString('contract-customer-' . bin2hex(random_bytes(6))),
            (new CustomerFactory())->fromDraft($draft),
            self::now(),
        );
    }

    public static function paymentRequest(TenantId $tenantId, UrssafCustomerId $customerId, string $intervenant, string $invoiceNumber): PaymentRequest
    {
        $now = self::now();
        $birthDate = SandboxGateway::env('URSSAF_SANDBOX_CUSTOMER_BIRTH_DATE') ?? '1980-03-29';
        $utc = new DateTimeZone('UTC');
        $start = $now->toDateTimeImmutable()->setTimezone($utc)->modify('-1 day')->setTime(10, 0);
        $end = $start->setTime(11, 0);
        $period = new PeriodDraft($start->format('Y-m-d\TH:i:s\Z'), $end->format('Y-m-d\TH:i:s\Z'));

        $draft = new PaymentRequestDraft(
            'contract-payment-' . bin2hex(random_bytes(6)),
            $invoiceNumber,
            $start->format('Y-m-d'),
            $period,
            '5.51',
            '5.01',
            [new ServiceLineDraft('60', null, 'HEURE', '1', '5.51', new LineAmountsDraft('5.51', '5.01', '0.5'), $intervenant, $period, null)],
            null,
        );
        $context = new PaymentRequestContext(
            $tenantId,
            InvoicedCustomer::of($customerId, CalendarDate::fromString($birthDate)),
            BillingThirdPartyId::fromString(SandboxGateway::env('URSSAF_SANDBOX_BILLING_ID') ?? 'menage.fr'),
            Instant::fromDateTime($now->toDateTimeImmutable()->modify('-90 days')),
            $now,
            new DateTimeZone('Europe/Paris'),
        );

        return (new PaymentRequestFactory())->fromDraft(
            LocalPaymentRequestId::fromString('contract-' . bin2hex(random_bytes(6))),
            $draft,
            $context,
        );
    }

    public static function uniqueInvoiceNumber(): string
    {
        return \sprintf('CT-%s-%s', (new DateTimeImmutable())->format('Ymd'), strtoupper(bin2hex(random_bytes(5))));
    }

    private static function now(): Instant
    {
        return Instant::fromDateTime(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }
}

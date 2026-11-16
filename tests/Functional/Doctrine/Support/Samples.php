<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\AddressDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BirthPlaceDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Rejection;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\TransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Input\DraftFixtures;

/**
 * Fully populated aggregates built through the domain factories from the official examples (api-reference 2.1 and
 * 2.3), in the states the mapping has to round-trip. Domain events are released.
 */
final class Samples
{
    private function __construct() {}

    public const MINIMAL = 'minimal';

    /**
     * A registration by case name: a {@see RegistrationState} value (the 2.1 customer in that state) or
     * {@see self::MINIMAL}.
     */
    public static function registrationCase(string $case): CustomerRegistration
    {
        return $case === self::MINIMAL ? self::minimalRegistration() : self::registration(RegistrationState::from($case));
    }

    /**
     * A payment request by case name: official, followed, rejected or polled-without-status.
     */
    public static function paymentRequestCase(string $case): PaymentRequest
    {
        return match ($case) {
            'followed' => self::followedPaymentRequest(),
            'rejected' => self::rejectedPaymentRequest(),
            'polled-without-status' => self::polledWithoutStatusPaymentRequest(),
            default => self::officialPaymentRequest(),
        };
    }

    /**
     * The 2.1 customer, in the given state.
     */
    public static function registration(RegistrationState $state): CustomerRegistration
    {
        return CustomerFixtures::registrationIn($state);
    }

    /**
     * Customer born abroad, with an address reduced to its locality (the other street parts are null).
     */
    public static function minimalRegistration(): CustomerRegistration
    {
        $official = CustomerFixtures::officialDraft();
        $draft = new CustomerDraft(
            '2',
            'Durand',
            null,
            'Jeanne',
            '1980-03-29',
            new BirthPlaceDraft('99134', null, null, null),
            $official->mobilePhone,
            $official->email,
            new AddressDraft(null, null, null, null, null, 'Le Beyssat', 'LYON 01', '69101', '69001', '99100'),
            $official->bankAccount,
        );
        $registration = CustomerRegistration::request(
            RegistrationId::fromString('reg-minimal'),
            TenantId::fromString('tenant-1'),
            ExternalReference::fromString('customer-minimal'),
            (new CustomerFactory())->fromDraft($draft),
            CustomerFixtures::at('2026-11-10T09:00:00Z'),
        );
        $registration->releaseEvents();

        return $registration;
    }

    /**
     * The 2.3 request: two lines (period, activity, complement), deposit, pending.
     */
    public static function officialPaymentRequest(): PaymentRequest
    {
        $request = (new PaymentRequestFactory())->fromDraft(
            LocalPaymentRequestId::fromString('local-official'),
            DraftFixtures::official(),
            Fixtures::context(Fixtures::NOW, Fixtures::PAIRING),
        );
        $request->releaseEvents();

        return $request;
    }

    /**
     * The 2.3 request, submitted, then followed through three statuses with transfer and rejection information.
     */
    public static function followedPaymentRequest(): PaymentRequest
    {
        $request = self::officialPaymentRequest();
        $request->startSubmission(Fixtures::at('2022-03-01T00:00:10Z'));
        $request->markAccepted(PaymentRequestId::fromString('urssaf-official'), '10', Fixtures::at('2022-03-01T00:00:20Z'));
        $request->applyUrssafStatus('20', null, null, Fixtures::at('2022-03-02T00:00:00Z'));
        $request->applyUrssafStatus(
            '70',
            TransferInfo::of(Money::fromCents(1102), CalendarDate::fromString('2022-03-10')),
            Rejection::of('SOME_CODE', 'Free text, with accents: é à'),
            Fixtures::at('2022-03-05T00:00:00Z'),
        );
        $request->releaseEvents();

        return $request;
    }

    /**
     * The 2.3 request, rejected by URSSAF with two errors.
     */
    public static function rejectedPaymentRequest(): PaymentRequest
    {
        $request = self::officialPaymentRequest();
        $request->startSubmission(Fixtures::at('2022-03-01T00:00:10Z'));
        $request->markRejected(
            [SubmissionError::of('ERR_FACTURE_DOUBLON', 'Duplicate', 'Already integrated'), SubmissionError::of('ERR_X', '', '')],
            'ERR_FACTURE_DOUBLON',
            Fixtures::at('2022-03-01T00:00:20Z'),
        );
        $request->releaseEvents();

        return $request;
    }

    /**
     * The 2.3 request, submitted with a blank status (unknown URSSAF status) and polled once.
     */
    public static function polledWithoutStatusPaymentRequest(): PaymentRequest
    {
        $request = self::officialPaymentRequest();
        $request->startSubmission(Fixtures::at('2022-03-01T00:00:10Z'));
        $request->markAccepted(PaymentRequestId::fromString('urssaf-blank'), '', Fixtures::at('2022-03-01T00:00:20Z'));
        $request->applyUrssafStatus('', null, Rejection::of('R', ''), Fixtures::at('2022-03-02T00:00:00Z'));
        $request->releaseEvents();

        return $request;
    }
}

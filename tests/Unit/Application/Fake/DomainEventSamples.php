<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerPairingStatusChanged;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerPairingValidated;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerSubmissionStarted;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerSubmittedToUrssaf;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestRejectedByUrssaf;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestStatusChanged;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestSubmitted;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\UrssafStatusChange;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Domain events built directly, to test what is derived from them: one factory per event.
 *
 * @mago-expect lint:too-many-methods
 */
final class DomainEventSamples
{
    public const AT = '2022-03-01T10:00:00Z';
    public const URSSAF_CUSTOMER_ID = 'af0be430-908a-11eb-a8b3-0242ac130003';

    private function __construct() {}

    public static function customerSubmitted(string $registrationId = 'reg-1'): CustomerSubmittedToUrssaf
    {
        return new CustomerSubmittedToUrssaf(RegistrationId::fromString($registrationId), self::tenant(), self::reference('customer-1'), UrssafCustomerId::fromString(self::URSSAF_CUSTOMER_ID), self::at());
    }

    public static function pairingValidated(): CustomerPairingValidated
    {
        return new CustomerPairingValidated(RegistrationId::fromString('reg-1'), self::tenant(), self::reference('customer-1'), Instant::fromIso8601('2022-02-28T09:00:00Z'), self::at());
    }

    public static function pairingChanged(PairingStatus $status, RegistrationState $newState): CustomerPairingStatusChanged
    {
        return new CustomerPairingStatusChanged(RegistrationId::fromString('reg-1'), self::tenant(), self::reference('customer-1'), $status, RegistrationState::AwaitingPairing, $newState, self::at());
    }

    public static function customerRejected(): CustomerRegistrationRejected
    {
        return new CustomerRegistrationRejected(RegistrationId::fromString('reg-1'), self::tenant(), self::reference('customer-1'), [
            RejectionCause::of('ERR_DGFIP_R2P_INCONNU', 'Unknown.'),
            RejectionCause::of('ERR_PARTICULIER_INCONNU', 'Unknown customer.'),
        ], self::at());
    }

    public static function submissionStarted(): CustomerSubmissionStarted
    {
        return new CustomerSubmissionStarted(RegistrationId::fromString('reg-1'), self::tenant(), self::at());
    }

    public static function paymentRequestSubmitted(string $localId = 'local-1'): PaymentRequestSubmitted
    {
        return new PaymentRequestSubmitted(LocalPaymentRequestId::fromString($localId), self::tenant(), self::reference('invoice-42'), PaymentRequestId::fromString('urssaf-1'), self::at());
    }

    public static function paymentRequestRejected(): PaymentRequestRejectedByUrssaf
    {
        return new PaymentRequestRejectedByUrssaf(LocalPaymentRequestId::fromString('local-1'), self::tenant(), self::reference('invoice-42'), [
            SubmissionError::of('ERR_DATE_FUTUR', 'Future.', 'The date is in the future.'),
        ], self::at());
    }

    public static function statusChanged(?string $previous, PaymentRequestStatus|string $current, string $localId = 'local-1'): PaymentRequestStatusChanged
    {
        return new PaymentRequestStatusChanged(
            LocalPaymentRequestId::fromString($localId),
            self::tenant(),
            self::reference('invoice-42'),
            $previous === null ? null : UrssafStatusChange::observed($previous, Instant::fromIso8601('2022-02-28T09:00:00Z')),
            UrssafStatusChange::observed($current, self::at()),
        );
    }

    private static function tenant(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    private static function reference(string $value): ExternalReference
    {
        return ExternalReference::fromString($value);
    }

    private static function at(): Instant
    {
        return Instant::fromIso8601(self::AT);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Service;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Moves the registration of the customer of a payment request refused by method 050 when the refusal tells that it
 * cannot receive payment requests any more, so that its pairing is polled again (method 020):
 * - customer account blocked -> blocked;
 * - mandate expired -> mandate_expired;
 * - customer / provider link missing -> registered goes back to awaiting_pairing.
 * Other rejections (or no rejection), unknown customers and registrations whose pairing is not followed are left
 * untouched.
 */
final class RegistrationFollowUp
{
    public static function afterRejection(
        CustomerRegistrationRepository $registrations,
        PaymentRequest $request,
        ?RejectionKind $kind,
        Instant $now,
        RecordedEvents $events,
    ): void {
        $pairingStatus = match ($kind) {
            RejectionKind::CustomerBlocked => PairingStatus::CustomerBlocked,
            RejectionKind::MandateExpired => PairingStatus::MandateExpired,
            RejectionKind::Final, RejectionKind::Retryable, RejectionKind::PairingMissing, RejectionKind::Duplicate, null => null,
        };
        if (!$pairingStatus instanceof PairingStatus && $kind !== RejectionKind::PairingMissing) {
            return;
        }
        $registration = $registrations->findByUrssafCustomerId($request->tenantId(), $request->urssafCustomerId());
        if (!$registration instanceof CustomerRegistration || !$registration->status()->state()->followsPairingStatus()) {
            return;
        }
        if ($pairingStatus instanceof PairingStatus) {
            $registration->applyPairingStatus($pairingStatus, null, $now);
        }
        if ($kind === RejectionKind::PairingMissing) {
            $registration->requestPairingRecheck($now);
        }
        $registrations->save($registration);
        $events->record(...$registration->releaseEvents());
    }
}

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

use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PaymentRequestDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\InvoicedCustomer;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestContext;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Builds a pending payment request for a registered customer: the context (URSSAF customer id, birth date, pairing
 * acceptance, billing third party, now, business time zone) comes from the registration, the clock and the policy.
 */
final readonly class PaymentRequestBuilder
{
    public function __construct(
        private Clock $clock,
        private WorkflowPolicy $policy,
        private PaymentRequestFactory $factory = new PaymentRequestFactory(),
    ) {}

    /**
     * @throws InvariantViolation "customer_registration.not_registered" when the customer cannot receive requests
     * @throws ValidationFailed   with every violation of the draft
     */
    public function build(LocalPaymentRequestId $id, CustomerRegistration $registration, PaymentRequestDraft $draft): PaymentRequest
    {
        $status = $registration->status();
        $status->ensureCanReceivePaymentRequests();
        // Both are guaranteed by the invariants of the "registered" state.
        /** @var UrssafCustomerId $urssafCustomerId */
        $urssafCustomerId = $status->urssafCustomerId();
        /** @var Instant $pairingAcceptedAt */
        $pairingAcceptedAt = $status->pairingAcceptedAt();
        $context = new PaymentRequestContext(
            $registration->tenantId(),
            InvoicedCustomer::of($urssafCustomerId, $registration->customer()->civilStatus()->birthDate()),
            $this->policy->billingThirdPartyId(),
            $pairingAcceptedAt,
            Instant::fromDateTime($this->clock->now()),
            $this->policy->businessTimeZone(),
        );

        return $this->factory->fromDraft($id, $draft, $context);
    }
}

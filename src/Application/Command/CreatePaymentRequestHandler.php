<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Application\Exception\DuplicateInvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\PaymentRequestBuilder;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RegistrationLocator;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;

/**
 * Loads the customer registration, checks that it can receive payment requests, builds the request from the draft
 * (local and time-dependent rules) and stores it as pending.
 *
 * Invoice numbers are unique per tenant. When a request already exists for the invoice number:
 * - it was rejected by URSSAF (never integrated) -> it is superseded: deleted and replaced by the new request (new id),
 *   in one transaction, so that a corrected invoice (or the same one, once the customer account is fixed) can be sent;
 * - same external reference and same invoice (every field equal) -> idempotent: the existing id is returned;
 * - otherwise -> {@see DuplicateInvoiceNumber}.
 */
final readonly class CreatePaymentRequestHandler
{
    public function __construct(
        private PaymentRequestRepository $paymentRequests,
        private RegistrationLocator $registrations,
        private IdGenerator $ids,
        private PaymentRequestBuilder $builder,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @throws RecordNotFound         "customer_registration.not_found"
     * @throws InvariantViolation     "customer_registration.not_registered"
     * @throws ValidationFailed       with every violation of the draft
     * @throws DuplicateInvoiceNumber
     */
    public function __invoke(CreatePaymentRequest $command): LocalPaymentRequestId
    {
        $registration = $this->registrations->locate($command->tenantId, $command->customer);
        $request = $this->builder->build(
            LocalPaymentRequestId::fromString($this->ids->generate()),
            $registration,
            $command->paymentRequest,
        );
        $existing = $this->paymentRequests->findByInvoiceNumber($command->tenantId, $request->invoiceNumber());
        if ($existing instanceof PaymentRequest && $existing->state() !== SubmissionState::Rejected) {
            return $this->sameRequest($existing, $request);
        }
        $superseded = $existing?->id();

        return $this->unitOfWork->run(function (RecordedEvents $events) use ($request, $superseded): LocalPaymentRequestId {
            if ($superseded instanceof LocalPaymentRequestId) {
                $this->paymentRequests->delete($superseded);
            }
            $this->paymentRequests->save($request);
            $events->record(...$request->releaseEvents());

            return $request->id();
        });
    }

    /**
     * @throws DuplicateInvoiceNumber unless both have the same external reference and the same invoice
     */
    private function sameRequest(PaymentRequest $existing, PaymentRequest $request): LocalPaymentRequestId
    {
        if (!$existing->externalReference()->equals($request->externalReference()) || !$existing->invoice()->equals($request->invoice())) {
            throw DuplicateInvoiceNumber::of($request->invoiceNumber(), $existing->id()->value());
        }

        return $existing->id();
    }
}

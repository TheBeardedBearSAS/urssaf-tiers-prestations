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

use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\BillingThirdPartyId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Invoice;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\InvoicedCustomer;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLines;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures;

/**
 * Wires the fakes together and builds aggregates in a given state. Time: Fixtures::NOW (2022-03-01T00:00:00Z).
 */
final readonly class Harness
{
    public const TENANT = 'tenant-1';
    public const OTHER_TENANT = 'tenant-2';
    public const NOW = Fixtures::NOW;
    public const PAIRING = Fixtures::PAIRING;

    public Journal $journal;
    public FixedClock $clock;
    public SequentialIdGenerator $ids;
    public RecordingEventPublisher $events;
    public RecordingTransactionManager $transactions;
    public UnitOfWork $unitOfWork;
    public InMemoryCustomerRegistrations $registrations;
    public InMemoryPaymentRequests $paymentRequests;
    public FakeUrssafGateway $gateway;

    public function __construct(public WorkflowPolicy $policy = new WorkflowPolicy())
    {
        $this->journal = new Journal();
        $this->clock = new FixedClock(self::NOW);
        $this->ids = new SequentialIdGenerator();
        $this->events = new RecordingEventPublisher($this->journal);
        $this->transactions = new RecordingTransactionManager($this->journal);
        $this->unitOfWork = new UnitOfWork($this->transactions, $this->events);
        $this->registrations = new InMemoryCustomerRegistrations($this->journal);
        $this->paymentRequests = new InMemoryPaymentRequests($this->journal);
        $this->gateway = new FakeUrssafGateway($this->journal);
    }

    public static function tenant(string $tenant = self::TENANT): TenantId
    {
        return TenantId::fromString($tenant);
    }

    public static function at(string $iso8601): Instant
    {
        return Instant::fromIso8601($iso8601);
    }

    public static function urssafCustomerId(int $number): UrssafCustomerId
    {
        return UrssafCustomerId::fromString(\sprintf('00000000-0000-4000-8000-%012d', $number));
    }

    /**
     * Registration "reg-<number>" (external reference "customer-<number>", URSSAF id urssafCustomerId(number) once
     * submitted) driven to the given state, stored, events released.
     */
    public function registration(int $number, RegistrationState $state, string $tenant = self::TENANT): CustomerRegistration
    {
        $registration = CustomerRegistration::request(
            RegistrationId::fromString('reg-' . $number),
            self::tenant($tenant),
            ExternalReference::fromString('customer-' . $number),
            CustomerFixtures::customer(),
            self::at('2022-01-01T00:00:00Z'),
        );
        $now = self::at('2022-01-02T00:00:00Z');
        $steps = match ($state) {
            RegistrationState::Pending => [],
            RegistrationState::Submitting => ['start'],
            RegistrationState::Rejected => ['start', 'rejected'],
            RegistrationState::Error => ['start', 'failed'],
            RegistrationState::InvalidCredentials => ['start', 'credentials'],
            RegistrationState::AwaitingPairing => ['start', 'submitted'],
            RegistrationState::Registered => ['start', 'submitted', PairingStatus::Validated],
            RegistrationState::Blocked => ['start', 'submitted', PairingStatus::Validated, PairingStatus::CustomerBlocked],
            RegistrationState::MandateExpired => ['start', 'submitted', PairingStatus::Validated, PairingStatus::MandateExpired],
        };
        foreach ($steps as $step) {
            self::step($registration, $step, $number, $now);
        }
        $registration->releaseEvents();
        $this->registrations->add($registration);

        return $registration;
    }

    /**
     * Payment request "pr-<number>" (invoice "INV-<number>", external reference "invoice-<number>"; URSSAF id
     * "urssaf-<number>" with status 10 once submitted) driven to the given state at NOW, stored, events released.
     */
    public function paymentRequest(int $number, SubmissionState $state, string $tenant = self::TENANT): PaymentRequest
    {
        $request = PaymentRequest::create(
            LocalPaymentRequestId::fromString('pr-' . $number),
            self::tenant($tenant),
            ExternalReference::fromString('invoice-' . $number),
            self::invoice('INV-' . $number),
            self::at(self::NOW),
        );
        $now = self::at(self::NOW);
        if ($state !== SubmissionState::Pending) {
            $request->startSubmission($now);
        }
        if ($state === SubmissionState::Submitted) {
            $request->markAccepted(PaymentRequestId::fromString('urssaf-' . $number), '10', $now);
        }
        if ($state === SubmissionState::Rejected) {
            $request->markRejected([], 'ERR_X', $now);
        }
        if ($state === SubmissionState::Error) {
            $request->markFailed([], $now);
        }
        if ($state === SubmissionState::InvalidCredentials) {
            $request->markInvalidCredentials($now);
        }
        $request->releaseEvents();
        $this->paymentRequests->add($request);

        return $request;
    }

    private static function step(CustomerRegistration $registration, string|PairingStatus $step, int $number, Instant $now): void
    {
        if ($step instanceof PairingStatus) {
            $registration->applyPairingStatus($step, self::at(self::PAIRING), $now);

            return;
        }
        if ($step === 'start') {
            $registration->startSubmission($now);
        }
        if ($step === 'submitted') {
            $registration->markSubmitted(self::urssafCustomerId($number), $now);
        }
        if ($step === 'rejected') {
            $registration->markRejected([RejectionCause::of('ERR_X', 'x')], $now);
        }
        if ($step === 'failed') {
            $registration->markFailed([RejectionCause::of('ERR_X', 'x')], $now);
        }
        if ($step === 'credentials') {
            $registration->markInvalidCredentials($now);
        }
    }

    public static function invoice(string $number): Invoice
    {
        $line = Fixtures::line('1', '5.51', '5.51', '5.01', '0.50');

        return new Invoice(
            InvoicedCustomer::of(self::urssafCustomerId(1), CalendarDate::fromString('1980-03-29')),
            BillingThirdPartyId::fromString('menage.fr'),
            InvoiceNumber::fromString($number),
            CalendarDate::fromString('2022-02-01'),
            Fixtures::period(Fixtures::START, Fixtures::END),
            Money::fromDecimalString('11.02'),
            Money::fromDecimalString('10.02'),
            ServiceLines::of($line, $line),
            null,
        );
    }
}

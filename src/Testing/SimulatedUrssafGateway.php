<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Testing;

use DateTimeZone;
use LogicException;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\CustomerTransmissionStatus;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSnapshot;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SearchCriterion;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\StatusInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SubmissionOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\TransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafGateway;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\ApiErrorCode;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\ErrorClassifier;
use Throwable;

/**
 * An in-memory URSSAF platform for the tests of a host application: it keeps the registered customers and the
 * submitted payment requests per tenant, and answers the four methods like the real API would, without any network.
 *
 * Default behaviour (every step can be scripted):
 * - method 010: the customer is accepted and gets a new URSSAF customer id;
 * - method 020: each poll returns the next status of the pairing sequence (default: APPAREILLAGE_VALIDE at once), the
 *   last one is repeated; an unknown customer is refused as "no active registration";
 * - method 050: every request is accepted with a new URSSAF id and the first status of the payment request sequence;
 *   an invoice number already submitted by the tenant is rejected as a duplicate (ERR_FACTURE_DOUBLON);
 * - method 070: each search returns the next status of every request found (default sequence 10, 20, 30, 70), the
 *   last one is repeated; a settled request carries a transfer of its total, a refused one a rejection.
 *
 * Never use it outside tests: nothing is sent to URSSAF.
 *
 * Implements the four methods plus the scripting and inspection API, hence the method count and complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final class SimulatedUrssafGateway implements UrssafGateway
{
    private const MAX_SEARCH_RESULTS = 10;
    private const SIMULATED_REJECTION = 'SIMULATED';

    /** @var array<string, list<Throwable>> failures to throw, by operation */
    private array $failures = [];

    /** @var list<ApiErrors> errors of the next registrations to refuse */
    private array $registrationRefusals = [];

    private PairingStatus $firstPairingStatus = PairingStatus::Validated;

    /** @var array<PairingStatus> */
    private array $nextPairingStatuses = [];

    private PaymentRequestStatus $firstPaymentRequestStatus = PaymentRequestStatus::Integrated;

    /** @var array<PaymentRequestStatus> */
    private array $nextPaymentRequestStatuses = [
        PaymentRequestStatus::AwaitingValidation,
        PaymentRequestStatus::Validated,
        PaymentRequestStatus::Paid,
    ];

    /** @var array<string, ApiErrors> errors by invoice number of the next submissions to reject */
    private array $invoiceRejections = [];

    /** @var array<string, SimulatedCustomer> by URSSAF customer id */
    private array $customers = [];

    /** @var array<string, SimulatedPaymentRequest> by URSSAF payment request id */
    private array $paymentRequests = [];

    /** @var list<Operation> */
    private array $calls = [];

    public function __construct(
        private readonly Clock $clock,
        private readonly IdGenerator $ids,
        private readonly DateTimeZone $businessTimeZone = new DateTimeZone('Europe/Paris'),
    ) {}

    /**
     * The next call of the operation throws the failure (for example `UrssafUnavailable::withStatus(...)`,
     * `UrssafAuthenticationFailed::withStatus(401)`); failures queued for the same operation are thrown in order.
     */
    public function failNext(Operation $operation, Throwable $failure): void
    {
        $this->failures[$operation->value][] = $failure;
    }

    /**
     * The next registration (method 010) is refused with these URSSAF error codes (HTTP 400), for example
     * "ERR_DGFIP_R2P_INCONNU".
     */
    public function refuseNextRegistration(string $errorCode, string ...$moreErrorCodes): void
    {
        $this->registrationRefusals[] = self::errors($errorCode, ...$moreErrorCodes);
    }

    /**
     * Pairing statuses returned by the successive polls (method 020) of the customers registered from now on.
     */
    public function pairCustomersThrough(PairingStatus $first, PairingStatus ...$next): void
    {
        $this->firstPairingStatus = $first;
        $this->nextPairingStatuses = $next;
    }

    /**
     * Forces the pairing status returned from now on for a registered customer.
     */
    public function setPairingStatus(UrssafCustomerId $customerId, PairingStatus $status): void
    {
        $this->customer($customerId)->force($status);
    }

    /**
     * Statuses of the payment requests submitted from now on: the first one is returned by the submission (method
     * 050), the next ones by the successive searches (method 070).
     */
    public function progressPaymentRequestsThrough(PaymentRequestStatus $first, PaymentRequestStatus ...$next): void
    {
        $this->firstPaymentRequestStatus = $first;
        $this->nextPaymentRequestStatuses = $next;
    }

    /**
     * Forces the status returned from now on for a submitted payment request.
     */
    public function setPaymentRequestStatus(PaymentRequestId $paymentRequestId, PaymentRequestStatus $status): void
    {
        $this->paymentRequest($paymentRequestId)->force($status);
    }

    /**
     * The next submission of this invoice number (method 050) is rejected with these URSSAF error codes; the kind of
     * rejection follows from the codes, as with the real API (ERR_FACTURE_DOUBLON: duplicate, ERR_CMPT_BLOQUE: customer
     * blocked, technical codes only: retryable, anything else: final).
     */
    public function rejectNextSubmissionOf(string $invoiceNumber, string $errorCode, string ...$moreErrorCodes): void
    {
        $this->invoiceRejections[$invoiceNumber] = self::errors($errorCode, ...$moreErrorCodes);
    }

    /**
     * @return list<Operation> every call received, in order (failed ones included)
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return list<UrssafCustomerId> the registered customers, in registration order
     */
    public function customerIds(): array
    {
        return array_map(static fn(SimulatedCustomer $customer): UrssafCustomerId => $customer->id, array_values($this->customers));
    }

    /**
     * @return list<PaymentRequestId> the accepted payment requests, in submission order
     */
    public function paymentRequestIds(): array
    {
        return array_map(static fn(SimulatedPaymentRequest $request): PaymentRequestId => $request->id, array_values($this->paymentRequests));
    }

    public function register(TenantId $tenantId, CustomerRegistration $registration): UrssafCustomerId
    {
        $this->receive(Operation::RegisterCustomer);
        $refusal = array_shift($this->registrationRefusals);
        if ($refusal instanceof ApiErrors) {
            throw self::refused(Operation::RegisterCustomer, $refusal);
        }
        $id = UrssafCustomerId::fromString($this->ids->generate());
        $this->customers[$id->value()] = new SimulatedCustomer($id, $tenantId, $this->firstPairingStatus, $this->nextPairingStatuses);

        return $id;
    }

    public function transmissionStatus(TenantId $tenantId, UrssafCustomerId $customerId): CustomerTransmissionStatus
    {
        $this->receive(Operation::GetCustomerStatus);
        $customer = $this->customers[$customerId->value()] ?? null;
        if ($customer === null || !$customer->tenantId->equals($tenantId)) {
            throw self::refused(Operation::GetCustomerStatus, self::errors(ApiErrorCode::NoActiveRegistration->value));
        }
        $status = $customer->poll();
        if (!$status->allowsTransmission()) {
            return CustomerTransmissionStatus::blocked($customerId, $status->value, 'Simulated pairing status.');
        }

        return CustomerTransmissionStatus::allowed(
            $customerId,
            $status->value,
            'Simulated pairing status.',
            Instant::fromDateTime($this->clock->now()),
        );
    }

    public function submit(TenantId $tenantId, PaymentRequest ...$requests): array
    {
        $this->receive(Operation::SubmitPaymentRequests);
        if ($requests === [] || \count($requests) > self::MAX_REQUESTS_PER_CALL) {
            throw InvariantViolation::because(
                'payment_request_submission.too_many_items',
                \sprintf('Between 1 and %d payment requests can be submitted at once.', self::MAX_REQUESTS_PER_CALL),
            );
        }

        return array_map(fn(PaymentRequest $request): SubmissionOutcome => $this->submitOne($tenantId, $request), array_values($requests));
    }

    public function search(TenantId $tenantId, PaymentRequestSearch $search): array
    {
        $this->receive(Operation::SearchPaymentRequests);
        $found = array_values(array_filter(
            $this->paymentRequests,
            static fn(SimulatedPaymentRequest $request): bool => $request->tenantId->equals($tenantId) && self::matches($request, $search),
        ));
        if (\count($found) > self::MAX_SEARCH_RESULTS) {
            throw self::refused(Operation::SearchPaymentRequests, self::errors(ApiErrorCode::TooManySearchResults->value));
        }

        return array_map(fn(SimulatedPaymentRequest $request): PaymentRequestSnapshot => $this->snapshot($request, $request->poll()), $found);
    }

    private function submitOne(TenantId $tenantId, PaymentRequest $request): SubmissionOutcome
    {
        $invoiceNumber = $request->invoiceNumber()->value();
        $errors = $this->rejectionErrors($tenantId, $invoiceNumber);
        if (!$errors->isEmpty()) {
            return SubmissionOutcome::rejected($invoiceNumber, self::SIMULATED_REJECTION, $errors, ErrorClassifier::rejectionKind($errors));
        }
        $simulated = new SimulatedPaymentRequest(
            PaymentRequestId::fromString($this->ids->generate()),
            $tenantId,
            $invoiceNumber,
            $request->invoice()->date(),
            $request->invoice()->totalInclTax(),
            $this->firstPaymentRequestStatus,
            $this->nextPaymentRequestStatuses,
        );
        $this->paymentRequests[$simulated->id->value()] = $simulated;

        return SubmissionOutcome::accepted($invoiceNumber, $simulated->id, (string) $simulated->current()->value);
    }

    /**
     * The scripted rejection of the invoice (consumed), else a duplicate error when the tenant already submitted it.
     */
    private function rejectionErrors(TenantId $tenantId, string $invoiceNumber): ApiErrors
    {
        $scripted = $this->invoiceRejections[$invoiceNumber] ?? null;
        unset($this->invoiceRejections[$invoiceNumber]);
        if ($scripted instanceof ApiErrors) {
            return $scripted;
        }

        return $this->isSubmitted($tenantId, $invoiceNumber) ? self::errors(ApiErrorCode::DuplicateInvoice->value) : ApiErrors::none();
    }

    private function isSubmitted(TenantId $tenantId, string $invoiceNumber): bool
    {
        foreach ($this->paymentRequests as $request) {
            if ($request->tenantId->equals($tenantId) && $request->invoiceNumber === $invoiceNumber) {
                return true;
            }
        }

        return false;
    }

    private static function matches(SimulatedPaymentRequest $request, PaymentRequestSearch $search): bool
    {
        return match ($search->criterion()) {
            SearchCriterion::PaymentRequestIds => \in_array($request->id->value(), array_map(
                static fn(PaymentRequestId $id): string => $id->value(),
                $search->paymentRequestIds(),
            ), true),
            SearchCriterion::InvoiceNumbers => \in_array($request->invoiceNumber, array_map(strval(...), $search->invoiceNumbers()), true),
            // A period search always has both bounds: the date itself stands for a missing one.
            SearchCriterion::InvoicePeriod => !$request->invoiceDate->isBefore($search->from() ?? $request->invoiceDate)
                && !$request->invoiceDate->isAfter($search->to() ?? $request->invoiceDate),
        };
    }

    private function snapshot(SimulatedPaymentRequest $request, PaymentRequestStatus $status): PaymentRequestSnapshot
    {
        $refused = $status === PaymentRequestStatus::Refused || $status === PaymentRequestStatus::DebitRefused;

        return PaymentRequestSnapshot::of(
            $request->id,
            $request->invoiceNumber,
            StatusInfo::of((string) $status->value, $status->label()),
            $refused ? RejectionInfo::of(self::SIMULATED_REJECTION, 'Simulated refusal.') : null,
            $status->isSettled() ? TransferInfo::of($request->total, Instant::fromDateTime($this->clock->now())->calendarDate($this->businessTimeZone)) : null,
        );
    }

    private function receive(Operation $operation): void
    {
        $this->calls[] = $operation;
        $queue = $this->failures[$operation->value] ?? [];
        $failure = array_shift($queue);
        $this->failures[$operation->value] = $queue;
        if ($failure !== null) {
            throw $failure;
        }
    }

    private function customer(UrssafCustomerId $customerId): SimulatedCustomer
    {
        return $this->customers[$customerId->value()]
            ?? throw new LogicException(\sprintf('The customer "%s" is not registered with the simulator.', $customerId->value()));
    }

    private function paymentRequest(PaymentRequestId $paymentRequestId): SimulatedPaymentRequest
    {
        return $this->paymentRequests[$paymentRequestId->value()]
            ?? throw new LogicException(\sprintf('The payment request "%s" was not submitted to the simulator.', $paymentRequestId->value()));
    }

    private static function refused(Operation $operation, ApiErrors $errors): UrssafRequestRejected
    {
        return UrssafRequestRejected::withErrors($operation, 400, $errors, ErrorClassifier::refusalReason($errors));
    }

    private static function errors(string ...$codes): ApiErrors
    {
        return ApiErrors::of(...array_map(static fn(string $code): ApiError => ApiError::of($code, 'Simulated error.', 'Simulated by the test gateway.'), $codes));
    }
}

<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PollingWindow;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Stores copies (as a database would): changes are only visible after save(). Insertion order.
 *
 * Implements the whole repository port plus test helpers, hence the method count and complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final class InMemoryPaymentRequests implements PaymentRequestRepository
{
    /** @var array<string, PaymentRequest> */
    private array $stored = [];

    /** @var list<string> */
    private array $refusedClaims = [];

    /** @var list<array{string, int}> */
    private array $staleQueries = [];

    public function __construct(private readonly Journal $journal = new Journal()) {}

    public function add(PaymentRequest ...$requests): void
    {
        foreach ($requests as $request) {
            $this->store($request);
        }
    }

    public function refuseClaim(LocalPaymentRequestId $id): void
    {
        $this->refusedClaims[] = $id->value();
    }

    public function save(PaymentRequest $paymentRequest): void
    {
        $this->journal->add('save:' . $paymentRequest->id()->value());
        $this->store($paymentRequest);
    }

    public function get(LocalPaymentRequestId $id): PaymentRequest
    {
        return clone ($this->stored[$id->value()] ?? throw RecordNotFound::withIdentifier('payment_request', $id->value()));
    }

    public function findByInvoiceNumber(TenantId $tenantId, InvoiceNumber $invoiceNumber): ?PaymentRequest
    {
        return $this->filter(static fn(PaymentRequest $r): bool => $r->tenantId()->equals($tenantId)
            && $r->invoiceNumber()->equals($invoiceNumber))[0] ?? null;
    }

    public function findByUrssafIds(TenantId $tenantId, array $paymentRequestIds): array
    {
        return $this->filter(static function (PaymentRequest $r) use ($tenantId, $paymentRequestIds): bool {
            foreach ($paymentRequestIds as $id) {
                if ($r->tenantId()->equals($tenantId) && $r->paymentRequestId()?->equals($id) === true) {
                    return true;
                }
            }

            return false;
        });
    }

    public function findPollable(TenantId $tenantId, Instant $now, PollingWindow $window, int $limit): array
    {
        $pollable = $this->filter(static fn(PaymentRequest $r): bool => $r->tenantId()->equals($tenantId)
            && $r->isPollable($now, $window));
        usort($pollable, static fn(PaymentRequest $a, PaymentRequest $b): int => ($a->lastPolledAt()?->toIso8601() ?? '') <=> ($b->lastPolledAt()?->toIso8601() ?? ''));

        return \array_slice($pollable, 0, $limit);
    }

    public function tenantsWithPollable(Instant $now, PollingWindow $window): array
    {
        $tenants = [];
        foreach ($this->stored as $request) {
            if (!$request->isPollable($now, $window)) {
                continue;
            }
            $tenants[$request->tenantId()->value()] = $request->tenantId();
        }

        return array_values($tenants);
    }

    public function findStaleSubmissions(Instant $olderThan, int $limit): array
    {
        $this->staleQueries[] = [$olderThan->toIso8601(), $limit];

        return \array_slice($this->filter(static fn(PaymentRequest $r): bool => $r->state() === SubmissionState::Submitting
            && $r->timeline()->submissionStartedAt()?->isAfter($olderThan) === false), 0, $limit);
    }

    public function findByState(TenantId $tenantId, SubmissionState $state, int $limit): array
    {
        return \array_slice($this->filter(static fn(PaymentRequest $r): bool => $r->tenantId()->equals($tenantId)
            && $r->state() === $state), 0, $limit);
    }

    public function delete(LocalPaymentRequestId $id): void
    {
        $this->journal->add('delete:' . $id->value());
        unset($this->stored[$id->value()]);
    }

    /**
     * @return list<string> ids of the stored requests, in insertion order
     */
    public function ids(): array
    {
        return array_keys($this->stored);
    }

    public function claimForSubmission(LocalPaymentRequestId $id, Instant $now): bool
    {
        $stored = $this->stored[$id->value()] ?? null;
        if ($stored === null || \in_array($id->value(), $this->refusedClaims, true) || $stored->state() !== SubmissionState::Pending) {
            return false;
        }
        $this->journal->add('claim:' . $id->value());
        $stored->startSubmission($now);
        $stored->releaseEvents();

        return true;
    }

    public function stored(string $id): PaymentRequest
    {
        return $this->stored[$id] ?? throw RecordNotFound::withIdentifier('payment_request', $id);
    }

    /**
     * @return list<array{string, int}> olderThan (ISO) and limit of each findStaleSubmissions() call
     */
    public function staleQueries(): array
    {
        return $this->staleQueries;
    }

    private function store(PaymentRequest $request): void
    {
        $copy = clone $request;
        $copy->releaseEvents();
        $this->stored[$request->id()->value()] = $copy;
    }

    /**
     * @param callable(PaymentRequest): bool $predicate
     *
     * @return list<PaymentRequest>
     */
    private function filter(callable $predicate): array
    {
        return array_values(array_map(
            static fn(PaymentRequest $r): PaymentRequest => clone $r,
            array_filter($this->stored, $predicate),
        ));
    }
}

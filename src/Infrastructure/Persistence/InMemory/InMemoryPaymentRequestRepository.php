<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory;

use DateInterval;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PollingWindow;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Process-local {@see PaymentRequestRepository} for tests and demos.
 *
 * Identity semantics: like a database, it stores a snapshot. {@see self::save()} copies the aggregate (without its
 * pending domain events, which are never persisted) and every read returns a new copy, so a change is only visible
 * once saved. Because the stored copy is private, {@see self::claimForSubmission()} applies
 * {@see PaymentRequest::startSubmission()} to it (which records no event), exactly like the port's compare-and-set.
 *
 * Lists are ordered by creation (oldest first) unless the port states otherwise; requests created at the same instant
 * keep their insertion order.
 *
 * Implements the whole repository port (one method per query), hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class InMemoryPaymentRequestRepository implements PaymentRequestRepository
{
    private const NO_AGE = 'PT0S';

    /** @var array<string, PaymentRequest> stored snapshots by local id, in insertion order */
    private array $requests = [];

    /**
     * Copies of every stored record (monitoring, tests).
     *
     * @return list<PaymentRequest>
     */
    public function all(): array
    {
        return $this->selection()->copies();
    }

    /**
     * @throws InvariantViolation "payment_request.duplicate_invoice_number"
     */
    public function save(PaymentRequest $paymentRequest): void
    {
        foreach ($this->requests as $stored) {
            if (
                !$stored->id()->equals($paymentRequest->id())
                && $stored->tenantId()->equals($paymentRequest->tenantId())
                && $stored->invoiceNumber()->equals($paymentRequest->invoiceNumber())
            ) {
                throw InvariantViolation::because(
                    'payment_request.duplicate_invoice_number',
                    'A payment request already exists for this invoice number.',
                    ['invoice_number' => $paymentRequest->invoiceNumber()->value()],
                );
            }
        }

        $snapshot = clone $paymentRequest;
        $snapshot->releaseEvents();
        $this->requests[$paymentRequest->id()->value()] = $snapshot;
    }

    public function get(LocalPaymentRequestId $id): PaymentRequest
    {
        $stored = $this->requests[$id->value()] ?? throw RecordNotFound::withIdentifier('payment_request', $id->value());

        return clone $stored;
    }

    public function findByInvoiceNumber(TenantId $tenantId, InvoiceNumber $invoiceNumber): ?PaymentRequest
    {
        return $this->selection()
            ->where(
                static fn(PaymentRequest $request): bool => $request->tenantId()->equals($tenantId)
                    && $request->invoiceNumber()->equals($invoiceNumber),
            )
            ->copies()[0] ?? null;
    }

    public function findByUrssafIds(TenantId $tenantId, array $paymentRequestIds): array
    {
        $wanted = array_map(static fn(PaymentRequestId $id): string => $id->value(), $paymentRequestIds);

        return $this->selection()
            ->where(
                static fn(PaymentRequest $request): bool => $request->tenantId()->equals($tenantId)
                    && \in_array($request->paymentRequestId()?->value(), $wanted, true),
            )
            ->copies();
    }

    public function findPollable(TenantId $tenantId, Instant $now, PollingWindow $window, int $limit): array
    {
        // Never polled first, then least recently polled; stable, so ties keep the creation order.
        return $this->selection()
            ->where(
                static fn(PaymentRequest $request): bool => $request->tenantId()->equals($tenantId)
                    && $request->isPollable($now, $window),
            )
            ->sortedBy(
                static fn(PaymentRequest $a, PaymentRequest $b): int => Selection::compareInstants(
                    $a->lastPolledAt(),
                    $b->lastPolledAt(),
                ),
            )
            ->limit($limit)
            ->copies();
    }

    public function tenantsWithPollable(Instant $now, PollingWindow $window): array
    {
        return array_map(
            static fn(PaymentRequest $request): TenantId => $request->tenantId(),
            $this->selection()
                ->where(static fn(PaymentRequest $request): bool => $request->isPollable($now, $window))
                ->distinctBy(static fn(PaymentRequest $request): string => $request->tenantId()->value())
                ->all(),
        );
    }

    public function findStaleSubmissions(Instant $olderThan, int $limit): array
    {
        return $this->selection()
            ->where(
                static fn(PaymentRequest $request): bool => $request->isStaleSubmission($olderThan, new DateInterval(self::NO_AGE)),
            )
            ->sortedBy(
                static fn(PaymentRequest $a, PaymentRequest $b): int => Selection::compareInstants(
                    $a->timeline()->submissionStartedAt(),
                    $b->timeline()->submissionStartedAt(),
                ),
            )
            ->limit($limit)
            ->copies();
    }

    public function findByState(TenantId $tenantId, SubmissionState $state, int $limit): array
    {
        return $this->selection()
            ->where(
                static fn(PaymentRequest $request): bool => $request->tenantId()->equals($tenantId) && $request->state() === $state,
            )
            ->limit($limit)
            ->copies();
    }

    public function delete(LocalPaymentRequestId $id): void
    {
        unset($this->requests[$id->value()]);
    }

    public function claimForSubmission(LocalPaymentRequestId $id, Instant $now): bool
    {
        $stored = $this->requests[$id->value()] ?? null;
        if ($stored === null || $stored->state() !== SubmissionState::Pending) {
            return false;
        }

        $stored->startSubmission($now);

        return true;
    }

    /**
     * Stored requests in creation order (oldest first, insertion order on ties).
     *
     * @return Selection<PaymentRequest>
     */
    private function selection(): Selection
    {
        return Selection::of($this->requests)->sortedBy(
            static fn(PaymentRequest $a, PaymentRequest $b): int => Selection::compareInstants(
                $a->timeline()->createdAt(),
                $b->timeline()->createdAt(),
            ),
        );
    }
}

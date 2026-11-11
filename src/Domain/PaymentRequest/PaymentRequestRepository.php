<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Persistence port of the {@see PaymentRequest} aggregate. Lists are ordered by creation (oldest first) unless stated.
 */
interface PaymentRequestRepository
{
    public function save(PaymentRequest $paymentRequest): void;

    /**
     * @throws RecordNotFound "payment_request.not_found"
     */
    public function get(LocalPaymentRequestId $id): PaymentRequest;

    /**
     * Invoice numbers are unique per tenant.
     */
    public function findByInvoiceNumber(TenantId $tenantId, InvoiceNumber $invoiceNumber): ?PaymentRequest;

    /**
     * Requests of the tenant whose URSSAF id is one of the given ids (unknown ids are ignored).
     *
     * @param list<PaymentRequestId> $paymentRequestIds
     *
     * @return list<PaymentRequest>
     */
    public function findByUrssafIds(TenantId $tenantId, array $paymentRequestIds): array;

    /**
     * Requests matching {@see PaymentRequest::isPollable()}, least recently polled first (never polled first).
     *
     * @param positive-int $limit
     *
     * @return list<PaymentRequest>
     */
    public function findPollable(TenantId $tenantId, Instant $now, PollingWindow $window, int $limit): array;

    /**
     * Tenants having at least one request matching {@see PaymentRequest::isPollable()}.
     *
     * @return list<TenantId>
     */
    public function tenantsWithPollable(Instant $now, PollingWindow $window): array;

    /**
     * Requests of every tenant in state submitting whose submission started at or before olderThan (i.e.
     * {@see PaymentRequest::isStaleSubmission()} with olderThan = now - staleAfter), oldest submission first.
     *
     * @param positive-int $limit
     *
     * @return list<PaymentRequest>
     */
    public function findStaleSubmissions(Instant $olderThan, int $limit): array;

    /**
     * @param positive-int $limit
     *
     * @return list<PaymentRequest>
     */
    public function findByState(TenantId $tenantId, SubmissionState $state, int $limit): array;

    /**
     * Removes the request; nothing happens when it does not exist. Used to supersede a rejected request by a new one
     * with the same invoice number (no domain event is recorded).
     */
    public function delete(LocalPaymentRequestId $id): void;

    /**
     * Atomically moves the request from pending to submitting (compare-and-set on the stored state), setting the
     * submission start and update instants to now and counting one more attempt, exactly like {@see PaymentRequest::startSubmission()}.
     * Returns false when the request is not pending any more (claimed by another worker). Reload the aggregate after a
     * successful claim instead of calling startSubmission() again.
     */
    public function claimForSubmission(LocalPaymentRequestId $id, Instant $now): bool;
}

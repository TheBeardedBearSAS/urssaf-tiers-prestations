<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Entity;

use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\LocalPaymentRequestIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\PaymentRequestIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Query-side row of a payment request (table 1:1 with the aggregate's, same id): the columns the polling and
 * reconciliation queries filter on.
 *
 * The aggregate keeps its tracking as one JSON document ({@see \TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\UrssafTrackingType}), which SQL
 * cannot filter on. The polling queries need the current raw URSSAF status, its observation instant, the poll instant,
 * the URSSAF id and the state as real, indexed columns. The repository derives this row from the aggregate and writes it
 * with DBAL on every save; DQL joins read it back (Doctrine hydrates it by reflection).
 *
 * Neither final nor a readonly class only because Doctrine generates lazy-loading proxies by extending the mapped
 * entities (and a proxy cannot extend a readonly class before PHP 8.3): final by convention, never extend it. For the
 * same reason the identifier is neither readonly nor private (Doctrine assigns it again when a proxy is loaded).
 *
 * @final
 */
class PaymentRequestProgress
{
    /**
     * DBAL types of the columns of {@see self::row()} (the others are plain strings).
     */
    public const TYPES = [
        'id' => LocalPaymentRequestIdType::NAME,
        'tenant_id' => TenantIdType::NAME,
        'urssaf_payment_request_id' => PaymentRequestIdType::NAME,
        'last_status_change_at' => InstantType::NAME,
        'last_polled_at' => InstantType::NAME,
    ];

    private function __construct(
        protected LocalPaymentRequestId $id,
        private readonly TenantId $tenantId,
        private readonly SubmissionState $state,
        private readonly ?PaymentRequestId $paymentRequestId,
        private readonly ?string $urssafStatus,
        private readonly ?Instant $lastStatusChangeAt,
        private readonly ?Instant $lastPolledAt,
    ) {}

    public static function of(PaymentRequest $paymentRequest): self
    {
        return new self(
            $paymentRequest->id(),
            $paymentRequest->tenantId(),
            $paymentRequest->state(),
            $paymentRequest->paymentRequestId(),
            $paymentRequest->rawUrssafStatus(),
            $paymentRequest->lastStatusChangeAt(),
            $paymentRequest->lastPolledAt(),
        );
    }

    /**
     * The columns of the row, keyed by column name (see {@see self::TYPES}).
     *
     * @return array{id: LocalPaymentRequestId, tenant_id: TenantId, state: string, urssaf_payment_request_id: PaymentRequestId|null, urssaf_status: string|null, last_status_change_at: Instant|null, last_polled_at: Instant|null}
     */
    public function row(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'state' => $this->state->value,
            'urssaf_payment_request_id' => $this->paymentRequestId,
            'urssaf_status' => $this->urssafStatus,
            'last_status_change_at' => $this->lastStatusChangeAt,
            'last_polled_at' => $this->lastPolledAt,
        ];
    }
}

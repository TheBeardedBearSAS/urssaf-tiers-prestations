<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;

/**
 * What is known about the payment request on the URSSAF side: its URSSAF id, its current status (with the history of
 * every distinct status observed), the transfer and rejection information, and the last poll instant.
 *
 * Immutable: every change returns a new instance; observing the current raw code again or a blank code is a no-op.
 */
final readonly class UrssafTracking
{
    /**
     * @param list<UrssafStatusChange> $history
     */
    private function __construct(
        private ?PaymentRequestId $paymentRequestId,
        private ?UrssafStatusChange $current,
        private array $history,
        private ?TransferInfo $transfer,
        private ?Rejection $rejection,
        private ?Instant $lastPolledAt,
    ) {}

    public static function none(): self
    {
        return new self(null, null, [], null, null, null);
    }

    public function identifiedAs(PaymentRequestId $paymentRequestId, PaymentRequestStatus|string $status, Instant $at): self
    {
        $observed = $this->observe($status, $at);

        return new self(
            $paymentRequestId,
            $observed->current,
            $observed->history,
            $observed->transfer,
            $observed->rejection,
            $observed->lastPolledAt,
        );
    }

    public function observe(PaymentRequestStatus|string $status, Instant $at): self
    {
        $rawCode = $status instanceof PaymentRequestStatus ? (string) $status->value : $status;
        if (trim($rawCode) === '' || $rawCode === $this->current?->rawCode()) {
            return $this;
        }
        $change = UrssafStatusChange::observed($rawCode, $at);

        return new self(
            $this->paymentRequestId,
            $change,
            [...$this->history, $change],
            $this->transfer,
            $this->rejection,
            $this->lastPolledAt,
        );
    }

    /**
     * Result of a status poll (method 070): observes the status, replaces transfer and rejection information and
     * records the poll instant.
     */
    public function polled(
        PaymentRequestStatus|string $status,
        ?TransferInfo $transfer,
        ?Rejection $rejection,
        Instant $at,
    ): self {
        $observed = $this->observe($status, $at);

        return new self($observed->paymentRequestId, $observed->current, $observed->history, $transfer, $rejection, $at);
    }

    /**
     * True while the status may still change (see {@see UrssafStatusChange::isStillFollowed()}); always true while no
     * status is known yet.
     */
    public function isStillFollowed(Instant $now, PollingWindow $window): bool
    {
        return $this->current?->isStillFollowed($now, $window) ?? true;
    }

    /**
     * URSSAF id (idDemandePaiement), known once the request is integrated.
     */
    public function paymentRequestId(): ?PaymentRequestId
    {
        return $this->paymentRequestId;
    }

    public function current(): ?UrssafStatusChange
    {
        return $this->current;
    }

    /**
     * @return list<UrssafStatusChange> oldest first
     */
    public function history(): array
    {
        return $this->history;
    }

    public function transfer(): ?TransferInfo
    {
        return $this->transfer;
    }

    public function rejection(): ?Rejection
    {
        return $this->rejection;
    }

    public function lastPolledAt(): ?Instant
    {
        return $this->lastPolledAt;
    }
}

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

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * A status value observed at URSSAF at a given instant (statut of methods 050 / 070).
 *
 * The raw code is always kept (method 050 may return an error code such as "ERR_CMPT_BLOQUE" instead of a status,
 * and URSSAF may add codes); {@see self::status()} is null when the code is not a known status.
 */
final readonly class UrssafStatusChange
{
    private function __construct(
        private string $rawCode,
        private ?PaymentRequestStatus $status,
        private Instant $observedAt,
    ) {}

    /**
     * @throws InvalidValue "urssaf_status.empty" for a blank raw code
     */
    public static function observed(PaymentRequestStatus|string $status, Instant $observedAt): self
    {
        $rawCode = $status instanceof PaymentRequestStatus ? (string) $status->value : $status;
        if (trim($rawCode) === '') {
            throw InvalidValue::empty('urssaf_status');
        }

        return new self($rawCode, PaymentRequestStatus::fromCode($rawCode), $observedAt);
    }

    public function rawCode(): string
    {
        return $this->rawCode;
    }

    public function status(): ?PaymentRequestStatus
    {
        return $this->status;
    }

    public function observedAt(): Instant
    {
        return $this->observedAt;
    }

    /**
     * Final statuses are never followed; settled statuses (paid, recovered) only while observedAt + settledMaxAge is
     * strictly after now; codes unknown to this package version only while observedAt + unknownStatusMaxAge is
     * strictly after now; other statuses always.
     */
    public function isStillFollowed(Instant $now, PollingWindow $window): bool
    {
        if (!$this->status instanceof PaymentRequestStatus) {
            return PollingWindow::isWithin($this->observedAt, $window->unknownStatusMaxAge(), $now);
        }
        if ($this->status->isFinal()) {
            return false;
        }

        return !$this->status->isSettled() || PollingWindow::isWithin($this->observedAt, $window->settledMaxAge(), $now);
    }
}

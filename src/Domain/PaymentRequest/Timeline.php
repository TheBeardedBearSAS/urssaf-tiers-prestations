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

use DateInterval;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Local timestamps of a payment request. Every change also moves updatedAt.
 */
final readonly class Timeline
{
    private function __construct(
        private Instant $createdAt,
        private Instant $updatedAt,
        private ?Instant $submissionStartedAt,
        private ?Instant $submittedAt,
    ) {}

    public static function startedAt(Instant $createdAt): self
    {
        return new self($createdAt, $createdAt, null, null);
    }

    public function touched(Instant $at): self
    {
        return new self($this->createdAt, $at, $this->submissionStartedAt, $this->submittedAt);
    }

    public function submissionStarted(Instant $at): self
    {
        return new self($this->createdAt, $at, $at, $this->submittedAt);
    }

    public function submitted(Instant $at): self
    {
        return new self($this->createdAt, $at, $this->submissionStartedAt, $at);
    }

    /**
     * True when the last submission attempt started at or before now - age (false when never started).
     */
    public function isSubmissionStartedBefore(Instant $now, DateInterval $age): bool
    {
        return $this->submissionStartedAt instanceof Instant
            && !Instant::fromDateTime($this->submissionStartedAt->toDateTimeImmutable()->add($age))->isAfter($now);
    }

    /**
     * True when integrated by URSSAF and submittedAt + age is strictly after now.
     */
    public function isSubmittedWithin(Instant $now, DateInterval $age): bool
    {
        return $this->submittedAt instanceof Instant && PollingWindow::isWithin($this->submittedAt, $age, $now);
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }

    /**
     * Start of the last submission attempt (pending -> submitting).
     */
    public function submissionStartedAt(): ?Instant
    {
        return $this->submissionStartedAt;
    }

    /**
     * Integration by URSSAF (accepted or reconciled).
     */
    public function submittedAt(): ?Instant
    {
        return $this->submittedAt;
    }
}

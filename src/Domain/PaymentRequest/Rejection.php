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

/**
 * Regularisation, contestation or cancellation information (infoRejet of method 070).
 *
 * The raw code is always kept so that codes unknown to this package are not lost; {@see self::reason()} maps the known
 * ones. The comment is URSSAF/customer free text, kept verbatim.
 */
final readonly class Rejection
{
    private function __construct(private string $code, private string $comment) {}

    /**
     * @throws InvalidValue "rejection_code.empty"
     */
    public static function of(string $code, string $comment): self
    {
        if (trim($code) === '') {
            throw InvalidValue::empty('rejection_code');
        }

        return new self($code, $comment);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function comment(): string
    {
        return $this->comment;
    }

    /**
     * Null for a code unknown to this package.
     */
    public function reason(): ?RejectionReason
    {
        return RejectionReason::tryFrom($this->code);
    }
}

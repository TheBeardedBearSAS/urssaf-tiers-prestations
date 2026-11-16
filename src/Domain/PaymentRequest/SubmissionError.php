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
 * Error reported for a submission: URSSAF business error (e.g. ERR_FACTURE_DOUBLON) or technical failure.
 * Code, message and description are kept verbatim.
 */
final readonly class SubmissionError
{
    private function __construct(private string $code, private string $message, private string $description) {}

    /**
     * @throws InvalidValue "submission_error_code.empty"
     */
    public static function of(string $code, string $message, string $description): self
    {
        if (trim($code) === '') {
            throw InvalidValue::empty('submission_error_code');
        }

        return new self($code, $message, $description);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function description(): string
    {
        return $this->description;
    }
}

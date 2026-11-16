<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input;

/**
 * Raw bank details input. IBAN and BIC may contain spaces and lower-case letters.
 */
final readonly class BankAccountDraft
{
    public function __construct(
        public ?string $iban,
        public ?string $bic,
        public ?string $holder,
    ) {}
}

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
 * Raw customer input, as typed by a user or read from the host application, before validation.
 *
 * Every field is nullable so that {@see CustomerFactory::validate()} can report every missing or invalid value at
 * once (an empty string counts as missing). Dates use "Y-m-d"; civility is "1" (male) or "2" (female).
 */
final readonly class CustomerDraft
{
    public function __construct(
        public ?string $civility,
        public ?string $birthName,
        public ?string $usageName,
        public ?string $firstNames,
        public ?string $birthDate,
        public ?BirthPlaceDraft $birthPlace,
        public ?string $mobilePhone,
        public ?string $email,
        public ?AddressDraft $address,
        public ?BankAccountDraft $bankAccount,
    ) {}
}

<?php

declare(strict_types=1);

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

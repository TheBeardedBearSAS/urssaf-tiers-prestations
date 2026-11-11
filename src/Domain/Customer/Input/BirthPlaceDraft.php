<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input;

/**
 * Raw birth place input. For a birth in France (country "99100") give the 3-character department code and the
 * 3-digit municipality code with its label; leave them empty for a birth abroad.
 */
final readonly class BirthPlaceDraft
{
    public function __construct(
        public ?string $countryCode,
        public ?string $departmentCode,
        public ?string $municipalityCode,
        public ?string $municipalityName,
    ) {}
}

<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;

/**
 * Civil status checked by the tax administration (DGFiP): civility, names, birth date and birth place.
 *
 * A usage name equal to the birth name is dropped (URSSAF expects it only when different).
 * The public constructor is the factory (more than 5 parts).
 */
final readonly class CivilStatus
{
    private ?PersonName $usageName;

    public function __construct(
        private Civility $civility,
        private PersonName $birthName,
        ?PersonName $usageName,
        private PersonName $firstNames,
        private CalendarDate $birthDate,
        private BirthPlace $birthPlace,
    ) {
        $this->usageName = $usageName?->equals($birthName) === false ? $usageName : null;
    }

    public function civility(): Civility
    {
        return $this->civility;
    }

    public function birthName(): PersonName
    {
        return $this->birthName;
    }

    /**
     * Null when absent or equal to the birth name.
     */
    public function usageName(): ?PersonName
    {
        return $this->usageName;
    }

    public function firstNames(): PersonName
    {
        return $this->firstNames;
    }

    public function birthDate(): CalendarDate
    {
        return $this->birthDate;
    }

    public function birthPlace(): BirthPlace
    {
        return $this->birthPlace;
    }

    public function equals(self $other): bool
    {
        return $this->civility === $other->civility
            && $this->birthName->equals($other->birthName)
            && $this->usageName?->value() === $other->usageName?->value()
            && $this->firstNames->equals($other->firstNames)
            && $this->birthDate->equals($other->birthDate)
            && $this->birthPlace->equals($other->birthPlace);
    }
}

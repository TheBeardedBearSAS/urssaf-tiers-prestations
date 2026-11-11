<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Shared;

use Stringable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Non-negative unit price incl. tax (mntUnitaireTTC) with at most 3 decimals, stored in thousandths of euro.
 */
final readonly class UnitPrice implements Stringable
{
    private const SUBJECT = 'unit_price';
    private const DECIMALS = 3;
    private const MAX_INTEGER_DIGITS = 12;

    /** thousandths of euro x thousandths of unit = millionths of euro; 10 000 millionths = 1 cent. */
    private const MILLIONTHS_PER_CENT = 10_000;

    private function __construct(private int $thousandths) {}

    /**
     * @throws InvalidValue
     */
    public static function fromThousandths(int $thousandths): self
    {
        if ($thousandths < 0) {
            throw InvalidValue::negative(self::SUBJECT);
        }

        return new self($thousandths);
    }

    /**
     * Strict parsing of "5", "5.5", "5.51" or "5.515" (at most 3 decimals, "." separator, no sign).
     *
     * @throws InvalidValue
     */
    public static function fromDecimalString(string $price): self
    {
        return new self(Decimal::parse($price, self::SUBJECT, self::DECIMALS, self::MAX_INTEGER_DIGITS));
    }

    public static function fromMoney(Money $money): self
    {
        return new self(Decimal::multiply($money->cents(), 10, self::SUBJECT));
    }

    public function thousandths(): int
    {
        return $this->thousandths;
    }

    /**
     * At least 2 decimals, 3 when needed: "5.50", "5.515".
     */
    public function toDecimalString(): string
    {
        return Decimal::format($this->thousandths, self::DECIMALS, 2);
    }

    /**
     * Line amount = unit price x quantity, computed exactly then rounded half up to the cent
     * (e.g. 22.335 x 1.75 = 39.08625 => 39.09). URSSAF accepts ± 0.05 between this value and mntPrestationTTC.
     *
     * @throws InvalidValue on overflow
     */
    public function times(Quantity $quantity): Money
    {
        return Money::fromCents(Decimal::divideRoundingHalfUp(
            Decimal::multiply($this->thousandths, $quantity->thousandths(), self::SUBJECT),
            self::MILLIONTHS_PER_CENT,
        ));
    }

    public function equals(self $other): bool
    {
        return $this->thousandths === $other->thousandths;
    }

    public function __toString(): string
    {
        return $this->toDecimalString();
    }
}

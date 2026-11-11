<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Shared;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Non-negative amount in euro, stored as an integer number of cents (URSSAF amounts have at most 2 decimals).
 *
 * Every operation that would produce a negative amount or overflow the integer range throws {@see InvalidValue}.
 */
final readonly class Money
{
    private const SUBJECT = 'money';
    private const DECIMALS = 2;
    private const MAX_INTEGER_DIGITS = 15;

    /** Rounding tolerance applied by URSSAF to line and invoice totals: ± 0.05 EUR. */
    public const URSSAF_TOLERANCE_CENTS = 5;

    private function __construct(private int $cents) {}

    /**
     * @throws InvalidValue
     */
    public static function fromCents(int $cents): self
    {
        if ($cents < 0) {
            throw InvalidValue::negative(self::SUBJECT);
        }

        return new self($cents);
    }

    /**
     * Strict parsing of "12", "12.5" or "12.50" (at most 2 decimals, "." separator, no sign).
     *
     * @throws InvalidValue
     */
    public static function fromDecimalString(string $amount): self
    {
        return new self(Decimal::parse($amount, self::SUBJECT, self::DECIMALS, self::MAX_INTEGER_DIGITS));
    }

    public function cents(): int
    {
        return $this->cents;
    }

    /**
     * Always 2 decimals: "12.50", "0.05".
     */
    public function toDecimalString(): string
    {
        return Decimal::format($this->cents, self::DECIMALS, self::DECIMALS);
    }

    /**
     * @throws InvalidValue on overflow
     */
    public function add(self $other): self
    {
        if ($this->cents > PHP_INT_MAX - $other->cents) {
            throw InvalidValue::tooLarge(self::SUBJECT);
        }

        return new self($this->cents + $other->cents);
    }

    /**
     * @throws InvalidValue when the result would be negative
     */
    public function subtract(self $other): self
    {
        return self::fromCents($this->cents - $other->cents);
    }

    /**
     * Multiplies by a quantity; the exact result (in thousandths of a cent) is rounded half up to the cent.
     *
     * @throws InvalidValue on overflow
     */
    public function multipliedBy(Quantity $quantity): self
    {
        return new self(Decimal::divideRoundingHalfUp(
            Decimal::multiply($this->cents, $quantity->thousandths(), self::SUBJECT),
            1000,
        ));
    }

    /**
     * True when |this - other| <= tolerance (bounds included).
     */
    public function isWithinTolerance(self $other, self $tolerance): bool
    {
        return abs($this->cents - $other->cents) <= $tolerance->cents;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }
}

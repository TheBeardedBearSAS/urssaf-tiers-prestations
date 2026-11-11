<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Shared;

use Stringable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Strictly positive quantity (hours or flat-rate units) with at most 3 decimals, stored in thousandths.
 */
final readonly class Quantity implements Stringable
{
    private const SUBJECT = 'quantity';
    private const DECIMALS = 3;
    private const MIN_DECIMALS = 0;
    private const MAX_INTEGER_DIGITS = 12;
    private const SCALE = 1000;

    private function __construct(private int $thousandths)
    {
        if ($thousandths <= 0) {
            throw InvalidValue::notPositive(self::SUBJECT);
        }
    }

    /**
     * @throws InvalidValue
     */
    public static function fromThousandths(int $thousandths): self
    {
        return new self($thousandths);
    }

    /**
     * @throws InvalidValue
     */
    public static function fromInt(int $units): self
    {
        if ($units < intdiv(PHP_INT_MIN, self::SCALE) || $units > intdiv(PHP_INT_MAX, self::SCALE)) {
            throw InvalidValue::tooLarge(self::SUBJECT);
        }

        return new self($units * self::SCALE);
    }

    /**
     * Strict parsing of "2", "1.75" or "0.125" (at most 3 decimals, "." separator, no sign).
     *
     * @throws InvalidValue
     */
    public static function fromDecimalString(string $quantity): self
    {
        return new self(Decimal::parse($quantity, self::SUBJECT, self::DECIMALS, self::MAX_INTEGER_DIGITS));
    }

    public function thousandths(): int
    {
        return $this->thousandths;
    }

    /**
     * Shortest representation: "2", "1.75", "0.125".
     */
    public function toDecimalString(): string
    {
        return Decimal::format($this->thousandths, self::DECIMALS, self::MIN_DECIMALS);
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

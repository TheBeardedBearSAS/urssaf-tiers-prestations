<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Street number (numeroVoie): 1 to 4 digits, not only zeros ("0", "00", "000" and "0000" are rejected). Kept verbatim.
 */
final readonly class StreetNumber
{
    private const SUBJECT = 'street_number';
    private const PATTERN = '/^\d{1,4}$/D';
    private const ZERO_PATTERN = '/^0+$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "street_number.invalid_format" or "street_number.zero"
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, '1 to 4 digits');
        }
        if (preg_match(self::ZERO_PATTERN, $value) === 1) {
            throw InvalidValue::because(self::SUBJECT . '.zero', 'Invalid street_number: the value must not be zero.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Personal data: masked in debug output (var_dump, print_r, dumpers). There is deliberately no __toString().
     *
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}

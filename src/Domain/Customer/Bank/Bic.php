<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Bank identifier code (bic): 8 or 11 characters matching the official pattern. Normalised: spaces removed, upper case.
 */
final readonly class Bic
{
    private const SUBJECT = 'bic';
    private const PATTERN = '/^[A-Z]{6}[0-9A-Z]{2}(?:[0-9A-Z]{3})?$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "bic.invalid_format"
     */
    public static function fromString(string $value): self
    {
        $normalised = strtoupper(str_replace(' ', '', $value));
        if (preg_match(self::PATTERN, $normalised) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, '8 or 11 characters');
        }

        return new self($normalised);
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

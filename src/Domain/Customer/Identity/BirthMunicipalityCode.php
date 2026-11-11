<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Birth municipality code (communeNaissance.codeCommune): exactly 3 digits, i.e. the last 3 characters of the
 * INSEE municipality code ("0" + last 2 characters for overseas departments).
 *
 * @see MunicipalityCode::birthMunicipalityCode() to derive it from a 5-character INSEE code
 */
final readonly class BirthMunicipalityCode
{
    private const SUBJECT = 'birth_municipality_code';
    private const PATTERN = '/^\d{3}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "birth_municipality_code.invalid_format"
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, '3 digits');
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

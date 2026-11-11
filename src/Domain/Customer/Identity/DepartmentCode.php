<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Birth department (departementNaissance): 3 characters, e.g. "069", "02A", "974". Normalised to upper case.
 */
final readonly class DepartmentCode
{
    private const SUBJECT = 'department_code';
    private const PATTERN = '/^[09][0-9][0-9AB]$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "department_code.invalid_format"
     */
    public static function fromString(string $value): self
    {
        $normalised = strtoupper($value);
        if (preg_match(self::PATTERN, $normalised) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, '3 characters such as 001, 02A, 02B or 974');
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

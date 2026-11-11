<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Shared;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * E-mail address validated with the official URSSAF pattern (adresseMail). Kept verbatim (no case folding).
 */
final readonly class EmailAddress
{
    private const SUBJECT = 'email_address';
    private const PATTERN = '/^[a-zA-Z0-9%+_\\-]+(\\.[a-zA-Z0-9%+_\\-]+)*@([a-zA-Z0-9\\-]*[a-zA-Z0-9]\\.)+[a-zA-Z0-9][a-zA-Z0-9\\-]*[a-zA-Z0-9]$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'official URSSAF e-mail pattern');
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

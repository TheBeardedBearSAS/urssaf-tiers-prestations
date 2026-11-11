<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;

/**
 * A decimal number emitted verbatim in the JSON document (never going through a float), so 11.02 stays 11.02.
 */
final readonly class JsonDecimal
{
    private const PATTERN = '/^-?(0|[1-9][0-9]*)(\.[0-9]+)?$/D';

    private function __construct(private string $literal) {}

    /**
     * @throws InvariantViolation "json_decimal.invalid" when the literal is not a valid JSON number
     */
    public static function fromString(string $literal): self
    {
        if (preg_match(self::PATTERN, $literal) !== 1) {
            throw InvariantViolation::because('json_decimal.invalid', 'The value is not a valid JSON number literal.');
        }

        return new self($literal);
    }

    public function literal(): string
    {
        return $this->literal;
    }
}

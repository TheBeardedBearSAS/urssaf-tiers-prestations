<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Shared;

use Stringable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Reference of a record in the host application (customer id, invoice id...), used for idempotency and correlation.
 */
final readonly class ExternalReference implements Stringable
{
    private const SUBJECT = 'external_reference';
    public const MAX_LENGTH = 191;

    private function __construct(private string $value) {}

    /**
     * Rejects blank values and values longer than {@see self::MAX_LENGTH} characters. The value is kept verbatim.
     *
     * @throws InvalidValue
     */
    public static function fromString(string $value): self
    {
        if (trim($value) === '') {
            throw InvalidValue::empty(self::SUBJECT);
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong(self::SUBJECT, self::MAX_LENGTH);
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

    public function __toString(): string
    {
        return $this->value;
    }
}

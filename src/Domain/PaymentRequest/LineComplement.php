<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

use Stringable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Free text attached to a service line (complement1). The specification gives no maximum length: the package bounds it
 * to 255 characters to stay on the safe side.
 */
final readonly class LineComplement implements Stringable
{
    private const SUBJECT = 'line_complement';
    public const MAX_LENGTH = 255;

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

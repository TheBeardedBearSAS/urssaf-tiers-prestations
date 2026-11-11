<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Shared;

use Stringable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Customer identifier assigned by URSSAF on registration (idClient): a 36-character UUID, normalised to lower case (method 020 is case sensitive). The UUID version is not checked: URSSAF ids are not always RFC 4122 compliant.
 */
final readonly class UrssafCustomerId implements Stringable
{
    private const SUBJECT = 'urssaf_customer_id';
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'UUID');
        }

        return new self(strtolower($value));
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

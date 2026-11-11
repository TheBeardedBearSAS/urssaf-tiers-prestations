<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port;

use DateInterval;
use DateTimeImmutable;
use SensitiveParameter;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * OAuth2 bearer token with its absolute expiry. The value is masked in var_dump()/print_r() output.
 */
final readonly class AccessToken
{
    private const MASK = '***';

    private function __construct(
        #[SensitiveParameter]
        private string $value,
        private DateTimeImmutable $expiresAt,
    ) {}

    /**
     * @throws InvalidValue "access_token.empty"
     */
    public static function of(#[SensitiveParameter] string $value, DateTimeImmutable $expiresAt): self
    {
        if (trim($value) === '') {
            throw InvalidValue::empty('access_token');
        }

        return new self($value, $expiresAt);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * True when the token is expired, or will be within $marginSeconds from now (refresh ahead of expiry).
     *
     * @throws InvalidValue "access_token.negative_margin"
     */
    public function isExpired(DateTimeImmutable $now, int $marginSeconds): bool
    {
        if ($marginSeconds < 0) {
            throw InvalidValue::because('access_token.negative_margin', 'The expiry margin must not be negative.');
        }

        return $now->add(new DateInterval('PT' . $marginSeconds . 'S')) >= $this->expiresAt;
    }

    /**
     * @return array{value: string, expiresAt: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => self::MASK, 'expiresAt' => $this->expiresAt->format(DATE_ATOM)];
    }
}

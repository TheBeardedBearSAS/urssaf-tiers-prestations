<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Port;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\AccessToken;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

final class AccessTokenTest extends TestCase
{
    public function testAccessors(): void
    {
        $expiresAt = new DateTimeImmutable('2026-01-01T10:00:00Z');
        $token = AccessToken::of('tok3n', $expiresAt);

        static::assertSame('tok3n', $token->value());
        static::assertSame($expiresAt, $token->expiresAt());
    }

    public function testBlankValueIsRejected(): void
    {
        $this->expectExceptionObject(InvalidValue::empty('access_token'));

        AccessToken::of(' ', new DateTimeImmutable());
    }

    public function testIsExpired(): void
    {
        $token = AccessToken::of('tok3n', new DateTimeImmutable('2026-01-01T10:00:00Z'));

        static::assertFalse($token->isExpired(new DateTimeImmutable('2026-01-01T09:59:59Z'), 0));
        static::assertTrue($token->isExpired(new DateTimeImmutable('2026-01-01T10:00:00Z'), 0));
        static::assertTrue($token->isExpired(new DateTimeImmutable('2026-01-01T10:00:01Z'), 0));
        static::assertFalse($token->isExpired(new DateTimeImmutable('2026-01-01T09:58:59Z'), 60));
        static::assertTrue($token->isExpired(new DateTimeImmutable('2026-01-01T09:59:00Z'), 60));
    }

    public function testNegativeMarginIsRejected(): void
    {
        $this->expectExceptionObject(
            InvalidValue::because('access_token.negative_margin', 'The expiry margin must not be negative.'),
        );

        AccessToken::of('tok3n', new DateTimeImmutable())->isExpired(new DateTimeImmutable(), -1);
    }

    public function testDebugOutputMasksTheValue(): void
    {
        $token = AccessToken::of('tok3n', new DateTimeImmutable('2026-01-01T10:00:00Z'));

        static::assertSame(['value' => '***', 'expiresAt' => '2026-01-01T10:00:00+00:00'], $token->__debugInfo());
        static::assertStringNotContainsString('tok3n', print_r($token, true));
    }
}

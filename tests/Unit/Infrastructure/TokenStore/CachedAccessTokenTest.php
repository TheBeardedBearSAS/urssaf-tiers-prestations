<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\TokenStore;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\AccessToken;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\TokenStore\CachedAccessToken;

final class CachedAccessTokenTest extends TestCase
{
    public function testSerializesValueAndExpiryTimestamp(): void
    {
        $token = AccessToken::of('tok3n', new DateTimeImmutable('2100-01-01T00:00:00+00:00'));

        static::assertSame(['value' => 'tok3n', 'expiresAt' => 4_102_444_800], CachedAccessToken::toArray($token));
    }

    public function testRestoresAValidToken(): void
    {
        $token = CachedAccessToken::fromArray(['value' => 'tok3n', 'expiresAt' => 4_102_444_800]);

        static::assertInstanceOf(AccessToken::class, $token);
        static::assertSame('tok3n', $token->value());
        static::assertSame(4_102_444_800, $token->expiresAt()->getTimestamp());
        static::assertSame('+00:00', $token->expiresAt()->getTimezone()->getName());
    }

    public function testRoundTripKeepsTheToken(): void
    {
        $original = AccessToken::of('tok3n', new DateTimeImmutable('2100-01-01T00:00:00+00:00'));

        $restored = CachedAccessToken::fromArray(CachedAccessToken::toArray($original));

        static::assertNotNull($restored);
        static::assertSame($original->value(), $restored->value());
        static::assertSame($original->expiresAt()->getTimestamp(), $restored->expiresAt()->getTimestamp());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function corruptedData(): iterable
    {
        yield 'null' => [null];
        yield 'string' => ['garbage'];
        yield 'missing value' => [['expiresAt' => 4_102_444_800]];
        yield 'missing expiry' => [['value' => 'tok3n']];
        yield 'null value' => [['value' => null, 'expiresAt' => 4_102_444_800]];
        yield 'integer value' => [['value' => 123, 'expiresAt' => 4_102_444_800]];
        yield 'string expiry' => [['value' => 'tok3n', 'expiresAt' => '4_102_444_800']];
        yield 'float expiry' => [['value' => 'tok3n', 'expiresAt' => 4_102_444_800.0]];
        yield 'blank value' => [['value' => '   ', 'expiresAt' => 4_102_444_800]];
    }

    #[DataProvider('corruptedData')]
    public function testCorruptedDataIsNotRestored(mixed $data): void
    {
        static::assertNull(CachedAccessToken::fromArray($data));
    }
}

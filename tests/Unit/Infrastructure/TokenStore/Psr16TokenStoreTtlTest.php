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
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\AccessToken;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\TokenStore\CacheKey;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\TokenStore\Psr16TokenStore;

/**
 * The entry lifetime is checked against the expiry stored by the cache pool (real time), the clock being frozen.
 */
final class Psr16TokenStoreTtlTest extends TestCase
{
    private const NOW = '2026-01-01T10:00:00+00:00';

    public function testEntryLivesForTheTimeLeftUntilExpiry(): void
    {
        $pool = new ArrayAdapter();
        $store = new Psr16TokenStore(new Psr16Cache($pool), new FrozenClock(new DateTimeImmutable(self::NOW)));

        $store->save('tenant-a', AccessToken::of('tok3n', new DateTimeImmutable('2026-01-01T10:05:00+00:00')));

        static::assertEqualsWithDelta(300.0, self::remainingLifetime($pool, 'tenant-a'), 1.0);
    }

    public function testTtlIsComputedFromTheInjectedClock(): void
    {
        $pool = new ArrayAdapter();
        $clock = new FrozenClock(new DateTimeImmutable('2026-01-01T10:04:00+00:00'));
        $store = new Psr16TokenStore(new Psr16Cache($pool), $clock);

        $store->save('tenant-a', AccessToken::of('tok3n', new DateTimeImmutable('2026-01-01T10:05:00+00:00')));

        static::assertEqualsWithDelta(60.0, self::remainingLifetime($pool, 'tenant-a'), 1.0);
    }

    public function testShortestLifetimeIsOneSecond(): void
    {
        $pool = new ArrayAdapter();
        $store = new Psr16TokenStore(new Psr16Cache($pool), new FrozenClock(new DateTimeImmutable(self::NOW)));

        $store->save('tenant-a', AccessToken::of('tok3n', new DateTimeImmutable('2026-01-01T10:00:01+00:00')));

        static::assertNotNull($store->get('tenant-a'));
        static::assertEqualsWithDelta(1.0, self::remainingLifetime($pool, 'tenant-a'), 1.0);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function alreadyExpiredInstants(): iterable
    {
        yield 'expires now' => ['2026-01-01T10:00:00+00:00'];
        yield 'expired before now' => ['2026-01-01T09:59:59+00:00'];
    }

    #[DataProvider('alreadyExpiredInstants')]
    public function testExpiredTokenIsNotKeptAndRemovesThePreviousEntry(string $expiresAt): void
    {
        $pool = new ArrayAdapter();
        $store = new Psr16TokenStore(new Psr16Cache($pool), new FrozenClock(new DateTimeImmutable(self::NOW)));
        $store->save('tenant-a', AccessToken::of('live', new DateTimeImmutable('2026-01-01T10:05:00+00:00')));

        $store->save('tenant-a', AccessToken::of('expired', new DateTimeImmutable($expiresAt)));

        static::assertNull($store->get('tenant-a'));
        static::assertFalse($pool->hasItem(CacheKey::of('tenant-a')));
    }

    #[DataProvider('alreadyExpiredInstants')]
    public function testExpiredTokenOnlyDeletesTheEntryAndNeverWritesIt(string $expiresAt): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('delete')->with(CacheKey::of('tenant-a'));
        $cache->expects($this->never())->method('set');
        $store = new Psr16TokenStore($cache, new FrozenClock(new DateTimeImmutable(self::NOW)));

        $store->save('tenant-a', AccessToken::of('expired', new DateTimeImmutable($expiresAt)));
    }

    /**
     * Remaining lifetime, in seconds, of the entry stored under the key.
     */
    private static function remainingLifetime(ArrayAdapter $pool, string $key): float
    {
        $metadata = $pool->getItem(CacheKey::of($key))->getMetadata();
        if (!\is_float($metadata['expiry'])) {
            static::fail('The cache entry has no float expiry.');
        }

        return $metadata['expiry'] - microtime(true);
    }
}

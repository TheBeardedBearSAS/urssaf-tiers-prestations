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

final class Psr16TokenStoreTest extends TestCase
{
    private const NOW = '2026-01-01T10:00:00+00:00';

    public function testMissReturnsNull(): void
    {
        $store = self::store(new Psr16Cache(new ArrayAdapter()));

        static::assertNull($store->get('tenant-a'));
    }

    public function testSavedTokenIsRestored(): void
    {
        $store = self::store(new Psr16Cache(new ArrayAdapter()));

        $store->save('tenant-a', self::token('2026-01-01T10:05:00+00:00', 'tok3n'));

        $restored = $store->get('tenant-a');
        static::assertInstanceOf(AccessToken::class, $restored);
        static::assertSame('tok3n', $restored->value());
        static::assertSame('2026-01-01T10:05:00+00:00', $restored->expiresAt()->format(DATE_ATOM));
    }

    public function testSaveReplacesThePreviousToken(): void
    {
        $store = self::store(new Psr16Cache(new ArrayAdapter()));

        $store->save('tenant-a', self::token('2026-01-01T10:05:00+00:00', 'initial'));
        $store->save('tenant-a', self::token('2026-01-01T10:10:00+00:00', 'renewed'));

        $restored = $store->get('tenant-a');
        static::assertNotNull($restored);
        static::assertSame('renewed', $restored->value());
    }

    public function testDeleteRemovesTheToken(): void
    {
        $store = self::store(new Psr16Cache(new ArrayAdapter()));
        $store->save('tenant-a', self::token('2026-01-01T10:05:00+00:00', 'tok3n'));

        $store->delete('tenant-a');

        static::assertNull($store->get('tenant-a'));
    }

    public function testKeysWithReservedCharactersRoundTrip(): void
    {
        $store = self::store(new Psr16Cache(new ArrayAdapter()));

        $store->save('tenant:prod/{a}', self::token('2026-01-01T10:05:00+00:00', 'tok3n'));

        $restored = $store->get('tenant:prod/{a}');
        static::assertNotNull($restored);
        static::assertSame('tok3n', $restored->value());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function corruptedPayloads(): iterable
    {
        yield 'not an array' => ['garbage'];
        yield 'missing expiry' => [['value' => 'tok3n']];
        yield 'blank value' => [['value' => ' ', 'expiresAt' => 4_102_444_800]];
    }

    #[DataProvider('corruptedPayloads')]
    public function testCorruptedEntryIsTreatedAsAMissAndDeleted(mixed $payload): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $cache->set(CacheKey::of('tenant-a'), $payload);
        $store = self::store($cache);

        static::assertNull($store->get('tenant-a'));
        static::assertFalse($cache->has(CacheKey::of('tenant-a')));
    }

    private static function store(CacheInterface $cache): Psr16TokenStore
    {
        return new Psr16TokenStore($cache, new FrozenClock(new DateTimeImmutable(self::NOW)));
    }

    private static function token(string $expiresAt, string $value): AccessToken
    {
        return AccessToken::of($value, new DateTimeImmutable($expiresAt));
    }
}

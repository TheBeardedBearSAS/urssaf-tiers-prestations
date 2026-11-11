<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\TokenStore;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\TokenStore\CacheKey;

final class CacheKeyTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function verbatimKeys(): iterable
    {
        yield 'letters digits underscore dot hyphen' => ['tenant_A-1.prod'];
        yield 'maximum length' => [str_repeat('k', 64)];
    }

    #[DataProvider('verbatimKeys')]
    public function testSafeKeysAreKeptVerbatim(string $key): void
    {
        static::assertSame($key, CacheKey::of($key));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hashedKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'colon' => ['tenant:prod'];
        yield 'slash' => ['tenant/prod'];
        yield 'backslash' => ['tenant\\prod'];
        yield 'at sign' => ['tenant@prod'];
        yield 'braces' => ['tenant{prod}'];
        yield 'parentheses' => ['tenant(prod)'];
        yield 'space' => ['tenant prod'];
        yield 'one over the maximum length' => [str_repeat('k', 65)];
    }

    #[DataProvider('hashedKeys')]
    public function testUnsafeKeysAreReplacedByAPrefixedDigest(string $key): void
    {
        $safe = CacheKey::of($key);

        static::assertSame(71, \strlen($safe));
        static::assertMatchesRegularExpression('/^hashed_[0-9a-f]{64}$/', $safe);
        static::assertSame('hashed_' . hash('sha256', $key), $safe);
    }

    public function testDistinctUnsafeKeysGetDistinctDigests(): void
    {
        static::assertNotSame(CacheKey::of('tenant:a'), CacheKey::of('tenant:b'));
    }

    public function testDigestIsNeverKeptVerbatim(): void
    {
        $digestKey = CacheKey::of('tenant:a');

        static::assertNotSame($digestKey, CacheKey::of($digestKey));
        static::assertSame('hashed_' . hash('sha256', $digestKey), CacheKey::of($digestKey));
    }
}

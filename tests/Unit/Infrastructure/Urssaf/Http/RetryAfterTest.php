<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Http;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\RetryAfter;

final class RetryAfterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function headers(): iterable
    {
        yield 'seconds' => ['30', 30];
        yield 'zero' => ['0', 0];
        yield 'absent' => ['', null];
        yield 'http date' => ['Wed, 21 Oct 2026 07:28:00 GMT', null];
        yield 'negative' => ['-5', null];
        yield 'decimal' => ['1.5', null];
        yield 'too long' => ['1234567890', null];
        yield 'garbage' => ['abc', null];
    }

    #[DataProvider('headers')]
    public function testItParsesTheDelayInSeconds(string $header, ?int $expected): void
    {
        $response = new Response(429, $header === '' ? [] : ['Retry-After' => $header]);

        static::assertSame($expected, RetryAfter::seconds($response));
    }

    public function testItIgnoresSurroundingWhitespaceOfTheHeaderLine(): void
    {
        $response = self::createStub(ResponseInterface::class);
        $response->method('getHeaderLine')->willReturn(" \t12\n");

        static::assertSame(12, RetryAfter::seconds($response));
    }
}

<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonDecimal;

final class JsonDecimalTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validLiterals(): iterable
    {
        yield 'zero' => ['0'];
        yield 'integer' => ['2000'];
        yield 'decimal' => ['11.02'];
        yield 'trailing zero' => ['0.30'];
        yield 'negative' => ['-1.5'];
    }

    #[DataProvider('validLiterals')]
    public function testItKeepsValidLiteralsVerbatim(string $literal): void
    {
        static::assertSame($literal, JsonDecimal::fromString($literal)->literal());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLiterals(): iterable
    {
        yield 'empty' => [''];
        yield 'leading zero' => ['01'];
        yield 'exponent' => ['1e3'];
        yield 'comma' => ['1,5'];
        yield 'trailing dot' => ['1.'];
        yield 'text' => ['abc'];
        yield 'trailing newline' => ["1\n"];
        yield 'injection' => ['1,"x":2'];
    }

    #[DataProvider('invalidLiterals')]
    public function testItRejectsAnythingThatIsNotAJsonNumber(string $literal): void
    {
        $this->expectException(InvariantViolation::class);

        JsonDecimal::fromString($literal);
    }
}

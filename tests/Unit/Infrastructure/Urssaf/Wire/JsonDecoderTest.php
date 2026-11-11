<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Wire;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonDecoder;

final class JsonDecoderTest extends TestCase
{
    public function testItDecodesObjectsAndArrays(): void
    {
        static::assertSame('v', JsonDecoder::decode('{"k":"v"}')->string('k'));
        static::assertCount(1, JsonDecoder::decode('[{"k":"v"}]')->entries());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'empty body' => [''];
        yield 'truncated' => ['{"k":'];
        yield 'html' => ['<html></html>'];
        yield 'scalar string' => ['"text"'];
        yield 'scalar number' => ['12'];
        yield 'null' => ['null'];
    }

    #[DataProvider('invalidBodies')]
    public function testItRejectsAnythingButAnObjectOrArray(string $body): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        JsonDecoder::decode($body);
    }

    public function testInvalidJsonKeepsTheDecodingErrorAsPrevious(): void
    {
        try {
            JsonDecoder::decode('{');
            static::fail('Expected an exception.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }

    public function testBigIntegersAreKeptAsStrings(): void
    {
        static::assertSame('12345678901234567890', JsonDecoder::decode('{"id":12345678901234567890}')->string('id'));
    }

    public function testItAcceptsADocumentNestedAtTheMaximumDepth(): void
    {
        $document = str_repeat('[', 511) . str_repeat(']', 511);

        static::assertTrue(JsonDecoder::decode($document)->isList());
    }

    public function testItRejectsADocumentNestedBeyondTheMaximumDepth(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('body is not valid JSON');

        JsonDecoder::decode(str_repeat('[', 512) . str_repeat(']', 512));
    }
}

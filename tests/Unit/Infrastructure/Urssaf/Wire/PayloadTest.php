<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonDecoder;

final class PayloadTest extends TestCase
{
    public function testItDistinguishesListsFromObjects(): void
    {
        static::assertTrue(JsonDecoder::decode('[{"a":1}]')->isList());
        static::assertTrue(JsonDecoder::decode('[]')->isList());
        static::assertFalse(JsonDecoder::decode('{"a":1}')->isList());
    }

    public function testStringReturnsOnlyStrings(): void
    {
        $payload = JsonDecoder::decode('{"s":"x","i":1,"n":null,"a":[],"b":true}');

        static::assertSame('x', $payload->string('s'));
        static::assertNull($payload->string('i'));
        static::assertNull($payload->string('n'));
        static::assertNull($payload->string('a'));
        static::assertNull($payload->string('b'));
        static::assertNull($payload->string('missing'));
    }

    public function testCodeReadsStringsAndIntegers(): void
    {
        $payload = JsonDecoder::decode('{"s":"x","i":12,"f":1.5,"n":null,"b":true}');

        static::assertSame('x', $payload->code('s'));
        static::assertSame('12', $payload->code('i'));
        static::assertNull($payload->code('f'));
        static::assertNull($payload->code('n'));
        static::assertNull($payload->code('b'));
        static::assertNull($payload->code('missing'));
    }

    public function testRequireStringReturnsANonEmptyString(): void
    {
        static::assertSame('x', JsonDecoder::decode('{"s":"x"}')->requireString('s'));
    }

    public function testRequireStringRejectsMissingEmptyAndNonStringValues(): void
    {
        foreach (['{}', '{"s":""}', '{"s":1}'] as $json) {
            try {
                JsonDecoder::decode($json)->requireString('s');
                static::fail('Expected an UnexpectedUrssafResponse for ' . $json);
            } catch (UnexpectedUrssafResponse $exception) {
                static::assertStringContainsString('field "s" is missing or empty', $exception->getMessage());
            }
        }
    }

    public function testNumberReturnsIntegersAndFloatsAndNullWhenAbsent(): void
    {
        $payload = JsonDecoder::decode('{"i":2000,"f":11.02,"n":null}');

        static::assertSame(2000, $payload->number('i'));
        static::assertSame(11.02, $payload->number('f'));
        static::assertNull($payload->number('n'));
        static::assertNull($payload->number('missing'));
    }

    public function testNumberRejectsOtherTypes(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('field "s" is not a number');

        JsonDecoder::decode('{"s":"12"}')->number('s');
    }
}

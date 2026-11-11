<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\Payload;

final class PayloadStructureTest extends TestCase
{
    public function testObjectReturnsANestedPayloadOrNull(): void
    {
        $payload = JsonDecoder::decode('{"o":{"a":"b"},"empty":{}}');

        static::assertSame('b', $payload->object('o')?->string('a'));
        static::assertInstanceOf(Payload::class, $payload->object('empty'));
        static::assertNull($payload->object('missing'));
    }

    public function testObjectRejectsScalars(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('field "o" is not an object');

        JsonDecoder::decode('{"o":"text"}')->object('o');
    }

    public function testItemsReturnsAListOfObjectsOrNothing(): void
    {
        $payload = JsonDecoder::decode('{"l":[{"a":"1"},{"a":"2"}],"e":[]}');

        static::assertSame(['1', '2'], array_map(static fn(Payload $item): ?string => $item->string('a'), $payload->items('l')));
        static::assertSame([], $payload->items('e'));
        static::assertSame([], $payload->items('missing'));
    }

    public function testItemsRejectsAnObjectInPlaceOfAList(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('expected a JSON array');

        JsonDecoder::decode('{"l":{"a":"1"}}')->items('l');
    }

    public function testEntriesRejectsAnObjectOfObjects(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('expected a JSON array');

        JsonDecoder::decode('{"a":{"x":1},"b":{"y":2}}')->entries();
    }

    public function testEntriesRejectsListsOfScalars(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('expected a JSON array of objects');

        JsonDecoder::decode('[1,2]')->entries();
    }

    public function testEntriesOfAListOfObjects(): void
    {
        static::assertCount(2, JsonDecoder::decode('[{"a":1},{"b":2}]')->entries());
    }
}

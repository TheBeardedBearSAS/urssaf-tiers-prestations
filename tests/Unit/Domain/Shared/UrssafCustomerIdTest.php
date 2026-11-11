<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

final class UrssafCustomerIdTest extends TestCase
{
    public function testCustomerIdIsNormalisedToLowerCase(): void
    {
        $id = UrssafCustomerId::fromString('AF0BE430-908A-11EB-A8B3-0242AC130003');

        static::assertSame('af0be430-908a-11eb-a8b3-0242ac130003', $id->value());
        static::assertSame('af0be430-908a-11eb-a8b3-0242ac130003', (string) $id);
        static::assertSame('6a56a628-b09e-8707-787e-10f218a2d550', UrssafCustomerId::fromString('6a56a628-b09e-8707-787e-10f218a2d550')->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidCustomerIds(): iterable
    {
        yield 'empty' => [''];
        yield 'numeric' => ['11000000000104'];
        yield 'no hyphens' => ['af0be430908a11eba8b30242ac130003'];
        yield 'non hex' => ['gf0be430-908a-11eb-a8b3-0242ac130003'];
        yield 'prefix' => ['xaf0be430-908a-11eb-a8b3-0242ac130003'];
        yield 'suffix' => ['af0be430-908a-11eb-a8b3-0242ac130003x'];
        yield 'trailing newline' => ["af0be430-908a-11eb-a8b3-0242ac130003\n"];
    }

    #[DataProvider('provideInvalidCustomerIds')]
    public function testInvalidCustomerIds(string $id): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('urssaf_customer_id', 'UUID'));

        UrssafCustomerId::fromString($id);
    }

    public function testCustomerIdEquality(): void
    {
        static::assertTrue(UrssafCustomerId::fromString('af0be430-908a-11eb-a8b3-0242ac130003')
            ->equals(UrssafCustomerId::fromString('AF0BE430-908A-11EB-A8B3-0242AC130003')));
        static::assertFalse(UrssafCustomerId::fromString('af0be430-908a-11eb-a8b3-0242ac130003')
            ->equals(UrssafCustomerId::fromString('af0be430-908a-11eb-a8b3-0242ac130004')));
    }
}

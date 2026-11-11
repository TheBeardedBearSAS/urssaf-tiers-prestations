<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Address;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

final class MunicipalityCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function provideCodes(): iterable
    {
        yield 'Lyon 1st' => ['69101', '69101', '069', '101'];
        yield 'Paris city' => ['75056', '75056', '075', '056'];
        yield 'Paris 1st' => ['75101', '75101', '075', '101'];
        yield 'Corsica A' => ['2A004', '2A004', '02A', '004'];
        yield 'Corsica b lower case' => ['2b072', '2B072', '02B', '072'];
        yield 'Martinique' => ['97209', '97209', '972', '009'];
        yield 'Reunion' => ['97411', '97411', '974', '011'];
        yield 'starts with 9 not overseas' => ['95127', '95127', '095', '127'];
        yield 'contains 97 later' => ['19702', '19702', '019', '702'];
    }

    #[DataProvider('provideCodes')]
    public function testCodes(string $input, string $value, string $department, string $municipality): void
    {
        $code = MunicipalityCode::fromString($input);

        static::assertSame($value, $code->value());
        static::assertSame(['value' => '***'], $code->__debugInfo());
        static::assertSame($department, $code->birthDepartmentCode()->value());
        static::assertSame($municipality, $code->birthMunicipalityCode()->value());
    }

    public function testEquality(): void
    {
        static::assertTrue(MunicipalityCode::fromString('2b072')->equals(MunicipalityCode::fromString('2B072')));
        static::assertFalse(MunicipalityCode::fromString('69101')->equals(MunicipalityCode::fromString('69102')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidCodes(): iterable
    {
        yield 'four characters' => ['6910'];
        yield 'six characters' => ['691011'];
        yield 'letter C' => ['2C072'];
        yield 'letter first' => ['A2072'];
        yield 'letter at the end' => ['2B07A'];
        yield 'prefixed' => ['x69101'];
        yield 'trailing newline' => ["69101\n"];
    }

    #[DataProvider('provideInvalidCodes')]
    public function testInvalidCodes(string $input): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('municipality_code', '5-character INSEE code such as 69101 or 2B072'));

        MunicipalityCode::fromString($input);
    }
}

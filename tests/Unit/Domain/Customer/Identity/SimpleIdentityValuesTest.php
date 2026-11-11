<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Identity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthMunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\Civility;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\DepartmentCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

final class SimpleIdentityValuesTest extends TestCase
{
    public function testCivility(): void
    {
        static::assertSame(Civility::Male, Civility::fromCode('1'));
        static::assertSame(Civility::Female, Civility::fromCode('2'));
        static::assertSame('1', Civility::Male->value);
        static::assertSame('2', Civility::Female->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidCivilities(): iterable
    {
        yield 'zero' => ['0'];
        yield 'three' => ['3'];
        yield 'letter' => ['M'];
        yield 'empty' => [''];
    }

    #[DataProvider('provideInvalidCivilities')]
    public function testInvalidCivility(string $code): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('civility', '"1" (male) or "2" (female)'));

        Civility::fromCode($code);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideValidDepartments(): iterable
    {
        yield 'Ain' => ['001', '001'];
        yield 'Rhone' => ['069', '069'];
        yield 'Val-d Oise' => ['095', '095'];
        yield 'Corse-du-Sud' => ['02A', '02A'];
        yield 'Haute-Corse lower case' => ['02b', '02B'];
        yield 'Guadeloupe' => ['971', '971'];
        yield 'Mayotte' => ['976', '976'];
    }

    #[DataProvider('provideValidDepartments')]
    public function testValidDepartment(string $input, string $expected): void
    {
        $department = DepartmentCode::fromString($input);

        static::assertSame($expected, $department->value());
        static::assertSame(['value' => '***'], $department->__debugInfo());
        static::assertTrue($department->equals(DepartmentCode::fromString($expected)));
        static::assertFalse($department->equals(DepartmentCode::fromString('075')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidDepartments(): iterable
    {
        yield 'two characters' => ['69'];
        yield 'four characters' => ['0690'];
        yield 'starts with 1' => ['169'];
        yield 'letter C' => ['02C'];
        yield 'letter in second position' => ['0A1'];
        yield 'prefixed' => ['x069'];
        yield 'trailing newline' => ["069\n"];
    }

    #[DataProvider('provideInvalidDepartments')]
    public function testInvalidDepartment(string $input): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('department_code', '3 characters such as 001, 02A, 02B or 974'));

        DepartmentCode::fromString($input);
    }

    public function testBirthMunicipalityCode(): void
    {
        $code = BirthMunicipalityCode::fromString('001');

        static::assertSame('001', $code->value());
        static::assertSame(['value' => '***'], $code->__debugInfo());
        static::assertTrue($code->equals(BirthMunicipalityCode::fromString('001')));
        static::assertFalse($code->equals(BirthMunicipalityCode::fromString('101')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidBirthMunicipalityCodes(): iterable
    {
        yield 'two digits' => ['01'];
        yield 'four digits' => ['0011'];
        yield 'letter' => ['0A1'];
        yield 'prefixed' => ['x001'];
        yield 'trailing newline' => ["001\n"];
    }

    #[DataProvider('provideInvalidBirthMunicipalityCodes')]
    public function testInvalidBirthMunicipalityCode(string $input): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('birth_municipality_code', '3 digits'));

        BirthMunicipalityCode::fromString($input);
    }
}

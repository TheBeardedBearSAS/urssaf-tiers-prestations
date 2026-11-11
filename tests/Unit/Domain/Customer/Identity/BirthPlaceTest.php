<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Identity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthMunicipality;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthMunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthPlace;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\DepartmentCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CountryCode;

final class BirthPlaceTest extends TestCase
{
    public function testBornInFrance(): void
    {
        $place = BirthPlace::inFrance(
            DepartmentCode::fromString('069'),
            BirthMunicipality::of(BirthMunicipalityCode::fromString('001'), MunicipalityName::ofBirthPlace('Lyon')),
        );

        static::assertTrue($place->isInFrance());
        static::assertSame('99100', $place->country()->value());
        static::assertSame('069', $place->department()?->value());
        $municipality = $place->municipality();
        static::assertNotNull($municipality);
        static::assertSame('001', $municipality->code()->value());
        static::assertSame('Lyon', $municipality->name()->value());
    }

    public function testBornAbroad(): void
    {
        $place = BirthPlace::abroad(CountryCode::fromString('99134'));

        static::assertFalse($place->isInFrance());
        static::assertSame('99134', $place->country()->value());
        static::assertNull($place->department());
        static::assertNull($place->municipality());
    }

    public function testAbroadRejectsFrance(): void
    {
        $this->expectExceptionObject(InvalidValue::because(
            'birth_place.department_required',
            'Invalid birth_place: a birth in France requires a department and a municipality.',
        ));

        BirthPlace::abroad(CountryCode::france());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideInseeCodes(): iterable
    {
        yield 'Lyon 1st arrondissement' => ['69101', '069', '101'];
        yield 'Paris 1st arrondissement' => ['75101', '075', '101'];
        yield 'Marseille 16th arrondissement' => ['13216', '013', '216'];
        yield 'Ain' => ['01001', '001', '001'];
        yield 'Corse-du-Sud' => ['2A004', '02A', '004'];
        yield 'Haute-Corse lower case' => ['2b072', '02B', '072'];
        yield 'Guadeloupe' => ['97105', '971', '005'];
        yield 'Reunion' => ['97411', '974', '011'];
        yield 'Mayotte' => ['97611', '976', '011'];
        yield 'Paris city code of another department' => ['76056', '076', '056'];
        yield 'Lyon city code in the Paris department' => ['75123', '075', '123'];
    }

    #[DataProvider('provideInseeCodes')]
    public function testFromInseeMunicipality(string $inseeCode, string $department, string $municipality): void
    {
        $place = BirthPlace::fromInseeMunicipality($inseeCode, 'Somewhere');

        static::assertTrue($place->isInFrance());
        static::assertSame($department, $place->department()?->value());
        $birthMunicipality = $place->municipality();
        static::assertNotNull($birthMunicipality);
        static::assertSame($municipality, $birthMunicipality->code()->value());
        static::assertSame('Somewhere', $birthMunicipality->name()->value());
        static::assertSame($municipality, BirthMunicipality::fromInseeCode($inseeCode, 'Somewhere')->code()->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideCitiesWithArrondissements(): iterable
    {
        yield 'Paris' => ['75056'];
        yield 'Lyon' => ['69123'];
        yield 'Marseille' => ['13055'];
    }

    #[DataProvider('provideCitiesWithArrondissements')]
    public function testWholeCityIsRejected(string $inseeCode): void
    {
        $this->expectExceptionObject(InvalidValue::because(
            'birth_place.arrondissement_required',
            'Invalid birth_place: for Paris, Lyon and Marseille give the arrondissement, not the whole city.',
        ));

        BirthPlace::fromInseeMunicipality($inseeCode, 'City');
    }

    public function testBirthMunicipalityFromInseeCodeKeepsTheWholeCityCode(): void
    {
        $municipality = BirthMunicipality::fromInseeCode('75056', 'Paris');

        static::assertSame('056', $municipality->code()->value());
        static::assertSame('Paris', $municipality->name()->value());
    }

    public function testInvalidInseeCode(): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('municipality_code', '5-character INSEE code such as 69101 or 2B072'));

        BirthPlace::fromInseeMunicipality('6910', 'Lyon');
    }

    public function testInvalidBirthMunicipalityName(): void
    {
        $this->expectExceptionObject(InvalidValue::empty('birth_municipality_name'));

        BirthMunicipality::fromInseeCode('69101', ' ');
    }
}

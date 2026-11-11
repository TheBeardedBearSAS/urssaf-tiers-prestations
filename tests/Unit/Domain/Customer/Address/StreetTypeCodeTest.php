<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Address;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetTypeCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

final class StreetTypeCodeTest extends TestCase
{
    private const OFFICIAL_LIST = 'ALL AV BAT BD C CAN CAS CAV CEI CEL CEN CGE CH CHA CHE CHN CHP CHS CHT CHV CI CIM CIN CL CLB CLI '
        . 'CLL CLO CN CO COE COG COL COM COR COT COU CRO CRX CTE CUR DEG DEP DES DIG DOM DON ECA ECL ECO ECV EGL EMP EN ENC '
        . 'ENT ESC ESP ETA ETG FAC FCU FER FG FOL FON FOR FOS FOY FRI FT GA GAL GDI GED GEN GGE GLA GPE GR GRI GRN GRP GSC '
        . 'GUE HA HAL HAM HCE HIP HLM HOP HOT HUT ILE ILO IMB IMP INS ISP JAR JET JUS LD LEV LOT LYC MA MAG MAI MAN MAR MAS '
        . 'MAT MET MF MG ML MOU MRI MTE MUS NTE OBS OCT PA PAE PAL PAR PAS PAT PAV PBY PCE PER PHA PIE PIM PKG PL PLA PLC PLE '
        . 'PLT PLY PO POI POR POT POU PPA PR PRA PRE PRO PRQ PRT PRU PT PTE Q QU R RAC RAM REM RES RNT ROC ROQ ROT ROU RP RTE '
        . 'RUL SAN SAP SAS SCE SEC SEM SEN SNT SOC SQ STA STN STO TE TEA TEN TER THE TOU TP TRA TRG TRI TUI USI VAL VCH VEL '
        . 'VEN VGE VIA VIL VLA VLE VNO VO VON VOY ZAC ZI ZUP';

    public function testTheOfficialListIsComplete(): void
    {
        static::assertSame(explode(' ', self::OFFICIAL_LIST), StreetTypeCode::all());
        static::assertCount(216, StreetTypeCode::all());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideOfficialCodes(): iterable
    {
        foreach (explode(' ', self::OFFICIAL_LIST) as $code) {
            yield $code => [$code];
        }
    }

    #[DataProvider('provideOfficialCodes')]
    public function testOfficialCodes(string $code): void
    {
        static::assertSame($code, StreetTypeCode::fromString($code)->value());
        static::assertSame($code, (string) StreetTypeCode::fromString(strtolower($code)));
    }

    public function testEquality(): void
    {
        static::assertTrue(StreetTypeCode::fromString('av')->equals(StreetTypeCode::fromString('AV')));
        static::assertFalse(StreetTypeCode::fromString('R')->equals(StreetTypeCode::fromString('AV')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown' => ['RUE'];
        yield 'padded' => [' R'];
        yield 'numeric' => ['0'];
    }

    #[DataProvider('provideUnknownCodes')]
    public function testUnknownCodes(string $code): void
    {
        $this->expectExceptionObject(InvalidValue::because(
            'street_type_code.unknown',
            'Invalid street_type_code: the value is not one of the official street type codes.',
        ));

        StreetTypeCode::fromString($code);
    }
}

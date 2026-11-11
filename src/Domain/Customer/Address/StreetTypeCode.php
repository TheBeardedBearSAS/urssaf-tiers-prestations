<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address;

use Stringable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Street type (codeTypeVoie): one of the 216 official codes, e.g. "R" (street), "AV" (avenue), "BD" (boulevard).
 * Normalised to upper case.
 */
final readonly class StreetTypeCode implements Stringable
{
    private const SUBJECT = 'street_type_code';

    /**
     * Official list (specification v1.30.19, codeTypeVoie).
     *
     * @var array<string, true>
     */
    private const CODES = [
        'ALL' => true, 'AV' => true, 'BAT' => true, 'BD' => true, 'C' => true, 'CAN' => true, 'CAS' => true,
        'CAV' => true, 'CEI' => true, 'CEL' => true, 'CEN' => true, 'CGE' => true, 'CH' => true, 'CHA' => true,
        'CHE' => true, 'CHN' => true, 'CHP' => true, 'CHS' => true, 'CHT' => true, 'CHV' => true, 'CI' => true,
        'CIM' => true, 'CIN' => true, 'CL' => true, 'CLB' => true, 'CLI' => true, 'CLL' => true, 'CLO' => true,
        'CN' => true, 'CO' => true, 'COE' => true, 'COG' => true, 'COL' => true, 'COM' => true, 'COR' => true,
        'COT' => true, 'COU' => true, 'CRO' => true, 'CRX' => true, 'CTE' => true, 'CUR' => true, 'DEG' => true,
        'DEP' => true, 'DES' => true, 'DIG' => true, 'DOM' => true, 'DON' => true, 'ECA' => true, 'ECL' => true,
        'ECO' => true, 'ECV' => true, 'EGL' => true, 'EMP' => true, 'EN' => true, 'ENC' => true, 'ENT' => true,
        'ESC' => true, 'ESP' => true, 'ETA' => true, 'ETG' => true, 'FAC' => true, 'FCU' => true, 'FER' => true,
        'FG' => true, 'FOL' => true, 'FON' => true, 'FOR' => true, 'FOS' => true, 'FOY' => true, 'FRI' => true,
        'FT' => true, 'GA' => true, 'GAL' => true, 'GDI' => true, 'GED' => true, 'GEN' => true, 'GGE' => true,
        'GLA' => true, 'GPE' => true, 'GR' => true, 'GRI' => true, 'GRN' => true, 'GRP' => true, 'GSC' => true,
        'GUE' => true, 'HA' => true, 'HAL' => true, 'HAM' => true, 'HCE' => true, 'HIP' => true, 'HLM' => true,
        'HOP' => true, 'HOT' => true, 'HUT' => true, 'ILE' => true, 'ILO' => true, 'IMB' => true, 'IMP' => true,
        'INS' => true, 'ISP' => true, 'JAR' => true, 'JET' => true, 'JUS' => true, 'LD' => true, 'LEV' => true,
        'LOT' => true, 'LYC' => true, 'MA' => true, 'MAG' => true, 'MAI' => true, 'MAN' => true, 'MAR' => true,
        'MAS' => true, 'MAT' => true, 'MET' => true, 'MF' => true, 'MG' => true, 'ML' => true, 'MOU' => true,
        'MRI' => true, 'MTE' => true, 'MUS' => true, 'NTE' => true, 'OBS' => true, 'OCT' => true, 'PA' => true,
        'PAE' => true, 'PAL' => true, 'PAR' => true, 'PAS' => true, 'PAT' => true, 'PAV' => true, 'PBY' => true,
        'PCE' => true, 'PER' => true, 'PHA' => true, 'PIE' => true, 'PIM' => true, 'PKG' => true, 'PL' => true,
        'PLA' => true, 'PLC' => true, 'PLE' => true, 'PLT' => true, 'PLY' => true, 'PO' => true, 'POI' => true,
        'POR' => true, 'POT' => true, 'POU' => true, 'PPA' => true, 'PR' => true, 'PRA' => true, 'PRE' => true,
        'PRO' => true, 'PRQ' => true, 'PRT' => true, 'PRU' => true, 'PT' => true, 'PTE' => true, 'Q' => true,
        'QU' => true, 'R' => true, 'RAC' => true, 'RAM' => true, 'REM' => true, 'RES' => true, 'RNT' => true,
        'ROC' => true, 'ROQ' => true, 'ROT' => true, 'ROU' => true, 'RP' => true, 'RTE' => true, 'RUL' => true,
        'SAN' => true, 'SAP' => true, 'SAS' => true, 'SCE' => true, 'SEC' => true, 'SEM' => true, 'SEN' => true,
        'SNT' => true, 'SOC' => true, 'SQ' => true, 'STA' => true, 'STN' => true, 'STO' => true, 'TE' => true,
        'TEA' => true, 'TEN' => true, 'TER' => true, 'THE' => true, 'TOU' => true, 'TP' => true, 'TRA' => true,
        'TRG' => true, 'TRI' => true, 'TUI' => true, 'USI' => true, 'VAL' => true, 'VCH' => true, 'VEL' => true,
        'VEN' => true, 'VGE' => true, 'VIA' => true, 'VIL' => true, 'VLA' => true, 'VLE' => true, 'VNO' => true,
        'VO' => true, 'VON' => true, 'VOY' => true, 'ZAC' => true, 'ZI' => true, 'ZUP' => true,
    ];

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "street_type_code.unknown"
     */
    public static function fromString(string $value): self
    {
        $normalised = strtoupper($value);
        if (!\array_key_exists($normalised, self::CODES)) {
            throw InvalidValue::because(
                self::SUBJECT . '.unknown',
                'Invalid street_type_code: the value is not one of the official street type codes.',
            );
        }

        return new self($normalised);
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::CODES);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

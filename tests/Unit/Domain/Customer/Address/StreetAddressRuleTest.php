<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Address;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetAddressRule;

final class StreetAddressRuleTest extends TestCase
{
    private const FIELDS = ['streetNumber', 'streetLetter', 'streetTypeCode', 'streetName', 'complement', 'locality'];

    /**
     * Every combination of filled fields (2^6) with the rules it must break.
     *
     * @return iterable<string, array{array{streetNumber: bool, streetLetter: bool, streetTypeCode: bool, streetName: bool, complement: bool, locality: bool}, list<StreetAddressRule>}>
     */
    public static function provideCombinations(): iterable
    {
        for ($mask = 0; $mask < 64; ++$mask) {
            $presence = [
                'streetNumber' => ($mask & 1) !== 0,
                'streetLetter' => ($mask & 2) !== 0,
                'streetTypeCode' => ($mask & 4) !== 0,
                'streetName' => ($mask & 8) !== 0,
                'complement' => ($mask & 16) !== 0,
                'locality' => ($mask & 32) !== 0,
            ];
            $expected = [];
            if ($presence['streetLetter'] && !$presence['streetNumber']) {
                $expected[] = StreetAddressRule::LetterRequiresNumber;
            }
            if ($presence['streetNumber'] && !$presence['streetName']) {
                $expected[] = StreetAddressRule::NumberRequiresStreetName;
            }
            if ($presence['streetName'] && !$presence['streetTypeCode']) {
                $expected[] = StreetAddressRule::StreetNameRequiresStreetType;
            }
            if ($presence['streetTypeCode'] && !$presence['streetName']) {
                $expected[] = StreetAddressRule::StreetTypeRequiresStreetName;
            }
            if (!$presence['streetName'] && !$presence['complement'] && !$presence['locality']) {
                $expected[] = StreetAddressRule::DeliveryPointRequired;
            }
            $label = $mask === 0 ? 'nothing' : implode('+', array_keys(array_filter($presence)));

            yield $label => [$presence, $expected];
        }
    }

    /**
     * @param array{streetNumber: bool, streetLetter: bool, streetTypeCode: bool, streetName: bool, complement: bool, locality: bool} $presence
     * @param list<StreetAddressRule>                                                                                                 $expected
     */
    #[DataProvider('provideCombinations')]
    public function testCombinations(array $presence, array $expected): void
    {
        static::assertSame($expected, StreetAddressRule::violatedBy($presence));
        foreach (StreetAddressRule::cases() as $rule) {
            static::assertSame(\in_array($rule, $expected, true), $rule->isViolatedBy($presence), $rule->name);
        }
    }

    public function testDescriptions(): void
    {
        $descriptions = [];
        foreach (StreetAddressRule::cases() as $rule) {
            $descriptions[$rule->name] = [$rule->path(), $rule->code(), $rule->message()];
        }

        static::assertSame([
            'LetterRequiresNumber' => [
                'streetNumber',
                'street_number.required_with_street_letter',
                'A street number is required when a street letter is given.',
            ],
            'NumberRequiresStreetName' => [
                'streetName',
                'street_name.required_with_street_number',
                'A street name is required when a street number is given.',
            ],
            'StreetNameRequiresStreetType' => [
                'streetTypeCode',
                'street_type_code.required_with_street_name',
                'A street type code is required when a street name is given.',
            ],
            'StreetTypeRequiresStreetName' => [
                'streetName',
                'street_name.required_with_street_type_code',
                'A street name is required when a street type code is given.',
            ],
            'DeliveryPointRequired' => [
                '',
                'postal_address.delivery_point_required',
                'At least one of the street name, the complement or the locality is required.',
            ],
        ], $descriptions);
        static::assertCount(6, self::FIELDS);
    }
}

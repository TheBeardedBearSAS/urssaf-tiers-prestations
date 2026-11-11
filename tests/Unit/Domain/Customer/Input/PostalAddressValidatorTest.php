<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\AddressDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\PostalAddressValidator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\ViolationCollector;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

final class PostalAddressValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{AddressDraft, list<string>}>
     */
    public static function provideDrafts(): iterable
    {
        yield 'official example' => [new AddressDraft('8', 'B', 'R', 'du Soleil', 'Batiment A', 'Le Beyssat', 'LYON 01', '69101', '69001', '99100'), []];
        yield 'street only' => [new AddressDraft(null, null, 'AV', 'Foch', null, null, 'PARIS 16', '75116', '75016', '99100'), []];
        yield 'complement only' => [new AddressDraft('', '', '', '', 'Batiment A', '', 'LYON 01', '69101', '69001', '99100'), []];
        yield 'locality only' => [new AddressDraft(null, null, null, null, null, 'Le Beyssat', 'LYON 01', '69101', '69001', '99100'), []];
        yield 'nothing' => [new AddressDraft(null, null, null, null, null, null, null, null, null, null), [
            ': postal_address.delivery_point_required',
            'municipalityName: municipality_name.required',
            'municipalityCode: municipality_code.required',
            'postalCode: postal_code.required',
            'countryCode: country_code.required',
        ]];
        yield 'municipality name only invalid' => [new AddressDraft('8', 'B', 'R', 'du Soleil', null, null, 'LYON;01', '69101', '69001', '99100'), [
            'municipalityName: municipality_name.invalid_characters',
        ]];
        yield 'municipality code only invalid' => [new AddressDraft('8', 'B', 'R', 'du Soleil', null, null, 'LYON 01', '6910', '69001', '99100'), [
            'municipalityCode: municipality_code.invalid_format',
        ]];
        yield 'letter without number' => [new AddressDraft(null, 'B', 'R', 'du Soleil', null, null, 'LYON 01', '69101', '69001', '99100'), [
            'streetNumber: street_number.required_with_street_letter',
        ]];
        yield 'number without street name' => [new AddressDraft('8', null, null, null, 'Batiment A', null, 'LYON 01', '69101', '69001', '99100'), [
            'streetName: street_name.required_with_street_number',
        ]];
        yield 'street name without type' => [new AddressDraft(null, null, null, 'du Soleil', null, null, 'LYON 01', '69101', '69001', '99100'), [
            'streetTypeCode: street_type_code.required_with_street_name',
        ]];
        yield 'type without street name' => [new AddressDraft(null, null, 'R', null, null, 'Le Beyssat', 'LYON 01', '69101', '69001', '99100'), [
            'streetName: street_name.required_with_street_type_code',
        ]];
        yield 'invalid street fields still count as filled' => [new AddressDraft('0', 'BB', 'RUE', str_repeat('a', 29), str_repeat('a', 39), str_repeat('a', 39), 'LYON;01', '6910', 'x', '9'), [
            'streetNumber: street_number.zero',
            'streetLetter: street_letter.invalid_format',
            'streetTypeCode: street_type_code.unknown',
            'streetName: street_name.too_long',
            'complement: address_complement.too_long',
            'locality: locality.too_long',
            'municipalityName: municipality_name.invalid_characters',
            'municipalityCode: municipality_code.invalid_format',
            'postalCode: postal_code.invalid_format',
            'countryCode: country_code.invalid_format',
        ]];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideDrafts')]
    public function testDrafts(AddressDraft $draft, array $expected): void
    {
        $validator = new PostalAddressValidator();
        static::assertSame($expected, ViolationSummary::of($validator->validate($draft)));

        $collector = new ViolationCollector();
        static::assertSame($expected === [], $validator->parse($draft, $collector) instanceof PostalAddress);
        static::assertSame($expected, ViolationSummary::of($collector->result()));
    }

    /**
     * A violation reported before the street part must not prevent building it.
     */
    public function testStreetIsParsedIndependentlyOfPreviousViolations(): void
    {
        $collector = new ViolationCollector();
        $collector->add(Violation::error('other', 'other.required', 'Required.'));
        $address = (new PostalAddressValidator())->parse(
            new AddressDraft('8', 'b', 'r', 'du Soleil', null, null, 'LYON 01', '2b072', '69001', '99100'),
            $collector,
        );

        static::assertNotNull($address);
        static::assertSame('B', $address->street()->letter()?->value());
        static::assertSame('R', $address->street()->typeCode()?->value());
        static::assertSame('2B072', $address->municipalityCode()->value());
        static::assertCount(1, $collector->result()->all());
    }

    public function testRuleMessage(): void
    {
        $violation = (new PostalAddressValidator())->validate(new AddressDraft(null, 'B', null, null, null, 'X', 'LYON 01', '69101', '69001', '99100'))->all()[0];

        static::assertSame('A street number is required when a street letter is given.', $violation->message());
    }
}

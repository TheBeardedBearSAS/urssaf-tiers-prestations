<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthPlace;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BirthPlaceDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BirthPlaceValidator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\ViolationCollector;

final class BirthPlaceValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{BirthPlaceDraft, list<string>}>
     */
    public static function provideDrafts(): iterable
    {
        yield 'France, complete' => [new BirthPlaceDraft('99100', '069', '001', 'Lyon'), []];
        yield 'abroad, complete' => [new BirthPlaceDraft('99134', null, null, null), []];
        yield 'abroad, empty strings' => [new BirthPlaceDraft('99134', '', '', ''), []];
        yield 'nothing' => [new BirthPlaceDraft(null, null, null, null), ['countryCode: country_code.required']];
        yield 'invalid country, other fields checked' => [
            new BirthPlaceDraft('FR', '69', '001', 'Lyon'),
            ['countryCode: country_code.invalid_format', 'departmentCode: department_code.invalid_format'],
        ];
        yield 'France, nothing else' => [new BirthPlaceDraft('99100', null, '', null), [
            'departmentCode: birth_place.department_required',
            'municipalityCode: birth_place.municipality_code_required',
            'municipalityName: birth_place.municipality_name_required',
        ]];
        yield 'France, department missing' => [new BirthPlaceDraft('99100', '', '001', 'Lyon'), ['departmentCode: birth_place.department_required']];
        yield 'France, code missing' => [new BirthPlaceDraft('99100', '069', null, 'Lyon'), ['municipalityCode: birth_place.municipality_code_required']];
        yield 'France, name missing' => [new BirthPlaceDraft('99100', '069', '001', ''), ['municipalityName: birth_place.municipality_name_required']];
        yield 'France, all invalid' => [new BirthPlaceDraft('99100', '69', '1', 'Ly;on'), [
            'departmentCode: department_code.invalid_format',
            'municipalityCode: birth_municipality_code.invalid_format',
            'municipalityName: birth_municipality_name.invalid_characters',
        ]];
        yield 'France, whole city of Paris' => [new BirthPlaceDraft('99100', '075', '056', 'Paris'), ['municipalityCode: birth_place.arrondissement_required']];
        yield 'abroad, everything given' => [new BirthPlaceDraft('99134', '069', 'bad', 'Lyon'), [
            'departmentCode: birth_place.department_forbidden',
            'municipalityCode: birth_place.municipality_code_forbidden',
            'municipalityName: birth_place.municipality_name_forbidden',
        ]];
        yield 'abroad, department given' => [new BirthPlaceDraft('99134', '069', null, ''), ['departmentCode: birth_place.department_forbidden']];
        yield 'abroad, name given' => [new BirthPlaceDraft('99134', null, '', 'Madrid'), ['municipalityName: birth_place.municipality_name_forbidden']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideDrafts')]
    public function testDrafts(BirthPlaceDraft $draft, array $expected): void
    {
        $validator = new BirthPlaceValidator();
        static::assertSame($expected, ViolationSummary::of($validator->validate($draft)));

        $collector = new ViolationCollector();
        $place = $validator->parse($draft, $collector);
        static::assertSame($expected === [], $place instanceof BirthPlace);
        static::assertSame($expected, ViolationSummary::of($collector->result()));
    }

    public function testParsedValues(): void
    {
        $france = (new BirthPlaceValidator())->parse(new BirthPlaceDraft('99100', '02b', '072', 'Bastia'), new ViolationCollector());
        static::assertNotNull($france);
        $municipality = $france->municipality();
        static::assertNotNull($municipality);
        static::assertSame('02B', $france->department()?->value());
        static::assertSame('072', $municipality->code()->value());
        static::assertSame('Bastia', $municipality->name()->value());

        $abroad = (new BirthPlaceValidator())->parse(new BirthPlaceDraft('99134', null, null, null), new ViolationCollector());
        static::assertSame('99134', $abroad?->country()->value());
    }

    public function testMessages(): void
    {
        $required = (new BirthPlaceValidator())->validate(new BirthPlaceDraft('99100', null, '001', 'Lyon'))->all()[0];
        static::assertSame('The birth department and municipality are required for a birth in France.', $required->message());

        $forbidden = (new BirthPlaceValidator())->validate(new BirthPlaceDraft('99134', '069', null, null))->all()[0];
        static::assertSame('The birth department and municipality must be empty for a birth abroad.', $forbidden->message());
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Text;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Text\AsciiNameNormalizer;

final class AsciiNameNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, string, string}>
     */
    public static function names(): iterable
    {
        yield 'accents' => [true, 'Élodie Müller', 'Elodie Muller'];
        yield 'accents, fallback' => [false, 'Élodie Müller', 'Elodie Muller'];
        yield 'compound name' => [true, "François-Xavier d'Aubigné", "Francois-Xavier d'Aubigne"];
        yield 'compound name, fallback' => [false, "François-Xavier d'Aubigné", "Francois-Xavier d'Aubigne"];
        yield 'typographic apostrophe' => [true, 'O’Neil', "O'Neil"];
        yield 'typographic apostrophe, fallback' => [false, 'O’Neil', "O'Neil"];
        yield 'ligatures and sharp s' => [true, 'Æsa Œuvre Straße', 'AEsa OEuvre Strasse'];
        yield 'ligatures and sharp s, fallback' => [false, 'Æsa Œuvre Straße', 'AEsa OEuvre Strasse'];
        yield 'surrounding blanks' => [true, '  Zoë  ', 'Zoe'];
        yield 'surrounding blanks, fallback' => [false, '  Zoë  ', 'Zoe'];
        yield 'tabs and newlines collapse' => [true, "Jean\t\n Pierre", 'Jean Pierre'];
        yield 'tabs and newlines collapse, fallback' => [false, "Jean\t\n Pierre", 'Jean Pierre'];
        yield 'digits removed and blanks trimmed' => [true, '123 Jean 456', 'Jean'];
        yield 'digits removed and blanks trimmed, fallback' => [false, '123 Jean 456', 'Jean'];
        yield 'digits removed without blank' => [true, 'Jean1Pierre', 'JeanPierre'];
        yield 'digits removed without blank, fallback' => [false, 'Jean1Pierre', 'JeanPierre'];
        yield 'punctuation removed' => [true, 'Jean (dupont)', 'Jean dupont'];
        yield 'punctuation removed, fallback' => [false, 'Jean (dupont)', 'Jean dupont'];
        yield 'ampersand removed' => [true, 'Jean&Co', 'JeanCo'];
        yield 'ampersand removed, fallback' => [false, 'Jean&Co', 'JeanCo'];
        yield 'leading and trailing hyphens trimmed' => [true, '--Jean--', 'Jean'];
        yield 'leading and trailing hyphens trimmed, fallback' => [false, '--Jean--', 'Jean'];
        yield 'quotes trimmed' => [true, "'Jean'", 'Jean'];
        yield 'quotes trimmed, fallback' => [false, "'Jean'", 'Jean'];
        yield 'blanks around a hyphen collapse' => [true, 'Jean  -  Pierre', 'Jean - Pierre'];
        yield 'blanks around a hyphen collapse, fallback' => [false, 'Jean  -  Pierre', 'Jean - Pierre'];
        yield 'emoji removed' => [true, 'Jean 😀', 'Jean'];
        yield 'emoji removed, fallback' => [false, 'Jean 😀', 'Jean'];
        yield 'empty' => [true, '', ''];
        yield 'empty, fallback' => [false, '', ''];
        yield 'only blanks' => [true, '   ', ''];
        yield 'only blanks, fallback' => [false, '   ', ''];
        yield 'only digits' => [true, '123', ''];
        yield 'only digits, fallback' => [false, '123', ''];
        yield 'invalid UTF-8 is cleaned up' => [true, "Jean\xC3\x28", 'Jean'];
        yield 'invalid UTF-8 is cleaned up, fallback' => [false, "Jean\xC3\x28", 'Jean'];
    }

    #[DataProvider('names')]
    public function testNormalizesToTheUrssafCharset(bool $useIntl, string $name, string $expected): void
    {
        static::assertSame($expected, (new AsciiNameNormalizer($useIntl))->normalize($name));
    }

    public function testIntlTransliteratesScriptsTheBuiltInMapCannot(): void
    {
        static::assertSame('Ivan', (new AsciiNameNormalizer(true))->normalize('Иван'));
    }

    public function testBuiltInMapDropsScriptsItDoesNotKnow(): void
    {
        static::assertSame('', (new AsciiNameNormalizer(false))->normalize('Иван'));
    }
}

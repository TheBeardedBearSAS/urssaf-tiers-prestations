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

/**
 * Every entry of the built-in transliteration map (used when ext-intl is not enabled), checked in isolation between two
 * ASCII letters so that trimming of leading and trailing characters does not interfere.
 */
final class AsciiNameNormalizerFallbackMapTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function lowercaseLatin(): iterable
    {
        yield 'à' => ['à', 'a'];
        yield 'á' => ['á', 'a'];
        yield 'â' => ['â', 'a'];
        yield 'ã' => ['ã', 'a'];
        yield 'ä' => ['ä', 'a'];
        yield 'å' => ['å', 'a'];
        yield 'æ' => ['æ', 'ae'];
        yield 'ç' => ['ç', 'c'];
        yield 'è' => ['è', 'e'];
        yield 'é' => ['é', 'e'];
        yield 'ê' => ['ê', 'e'];
        yield 'ë' => ['ë', 'e'];
        yield 'ì' => ['ì', 'i'];
        yield 'í' => ['í', 'i'];
        yield 'î' => ['î', 'i'];
        yield 'ï' => ['ï', 'i'];
        yield 'ð' => ['ð', 'd'];
        yield 'ñ' => ['ñ', 'n'];
        yield 'ò' => ['ò', 'o'];
        yield 'ó' => ['ó', 'o'];
        yield 'ô' => ['ô', 'o'];
        yield 'õ' => ['õ', 'o'];
        yield 'ö' => ['ö', 'o'];
        yield 'ø' => ['ø', 'o'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function lowercaseEuropean(): iterable
    {
        yield 'œ' => ['œ', 'oe'];
        yield 'ß' => ['ß', 'ss'];
        yield 'ù' => ['ù', 'u'];
        yield 'ú' => ['ú', 'u'];
        yield 'û' => ['û', 'u'];
        yield 'ü' => ['ü', 'u'];
        yield 'ý' => ['ý', 'y'];
        yield 'ÿ' => ['ÿ', 'y'];
        yield 'þ' => ['þ', 'th'];
        yield 'ł' => ['ł', 'l'];
        yield 'đ' => ['đ', 'd'];
        yield 'ś' => ['ś', 's'];
        yield 'š' => ['š', 's'];
        yield 'ş' => ['ş', 's'];
        yield 'ž' => ['ž', 'z'];
        yield 'ź' => ['ź', 'z'];
        yield 'ż' => ['ż', 'z'];
        yield 'ć' => ['ć', 'c'];
        yield 'č' => ['č', 'c'];
        yield 'ń' => ['ń', 'n'];
        yield 'ň' => ['ň', 'n'];
        yield 'ř' => ['ř', 'r'];
        yield 'ě' => ['ě', 'e'];
        yield 'ů' => ['ů', 'u'];
        yield 'ő' => ['ő', 'o'];
        yield 'ű' => ['ű', 'u'];
        yield 'ą' => ['ą', 'a'];
        yield 'ę' => ['ę', 'e'];
        yield 'ı' => ['ı', 'i'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function uppercaseLatin(): iterable
    {
        yield 'À' => ['À', 'A'];
        yield 'Á' => ['Á', 'A'];
        yield 'Â' => ['Â', 'A'];
        yield 'Ã' => ['Ã', 'A'];
        yield 'Ä' => ['Ä', 'A'];
        yield 'Å' => ['Å', 'A'];
        yield 'Æ' => ['Æ', 'AE'];
        yield 'Ç' => ['Ç', 'C'];
        yield 'È' => ['È', 'E'];
        yield 'É' => ['É', 'E'];
        yield 'Ê' => ['Ê', 'E'];
        yield 'Ë' => ['Ë', 'E'];
        yield 'Ì' => ['Ì', 'I'];
        yield 'Í' => ['Í', 'I'];
        yield 'Î' => ['Î', 'I'];
        yield 'Ï' => ['Ï', 'I'];
        yield 'Ð' => ['Ð', 'D'];
        yield 'Ñ' => ['Ñ', 'N'];
        yield 'Ò' => ['Ò', 'O'];
        yield 'Ó' => ['Ó', 'O'];
        yield 'Ô' => ['Ô', 'O'];
        yield 'Õ' => ['Õ', 'O'];
        yield 'Ö' => ['Ö', 'O'];
        yield 'Ø' => ['Ø', 'O'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function uppercaseEuropean(): iterable
    {
        yield 'Œ' => ['Œ', 'OE'];
        yield 'ẞ' => ['ẞ', 'SS'];
        yield 'Ù' => ['Ù', 'U'];
        yield 'Ú' => ['Ú', 'U'];
        yield 'Û' => ['Û', 'U'];
        yield 'Ü' => ['Ü', 'U'];
        yield 'Ý' => ['Ý', 'Y'];
        yield 'Ÿ' => ['Ÿ', 'Y'];
        yield 'Þ' => ['Þ', 'TH'];
        yield 'Ł' => ['Ł', 'L'];
        yield 'Đ' => ['Đ', 'D'];
        yield 'Ś' => ['Ś', 'S'];
        yield 'Š' => ['Š', 'S'];
        yield 'Ş' => ['Ş', 'S'];
        yield 'Ž' => ['Ž', 'Z'];
        yield 'Ź' => ['Ź', 'Z'];
        yield 'Ż' => ['Ż', 'Z'];
        yield 'Ć' => ['Ć', 'C'];
        yield 'Č' => ['Č', 'C'];
        yield 'Ń' => ['Ń', 'N'];
        yield 'Ň' => ['Ň', 'N'];
        yield 'Ř' => ['Ř', 'R'];
        yield 'Ě' => ['Ě', 'E'];
        yield 'Ů' => ['Ů', 'U'];
        yield 'Ő' => ['Ő', 'O'];
        yield 'Ű' => ['Ű', 'U'];
        yield 'Ą' => ['Ą', 'A'];
        yield 'Ę' => ['Ę', 'E'];
        yield 'İ' => ['İ', 'I'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function punctuation(): iterable
    {
        yield 'left single quotation mark' => ["\u{2018}", "'"];
        yield 'right single quotation mark' => ["\u{2019}", "'"];
        yield 'modifier letter apostrophe' => ["\u{02BC}", "'"];
        yield 'acute accent' => ["\u{00B4}", "'"];
        yield 'grave accent' => ['`', "'"];
        yield 'hyphen' => ["\u{2010}", '-'];
        yield 'non-breaking hyphen' => ["\u{2011}", '-'];
        yield 'figure dash' => ["\u{2012}", '-'];
        yield 'en dash' => ["\u{2013}", '-'];
        yield 'em dash' => ["\u{2014}", '-'];
    }

    #[DataProvider('lowercaseLatin')]
    #[DataProvider('lowercaseEuropean')]
    #[DataProvider('uppercaseLatin')]
    #[DataProvider('uppercaseEuropean')]
    #[DataProvider('punctuation')]
    public function testBuiltInMapTransliteratesEachKnownCharacter(string $character, string $expected): void
    {
        static::assertSame('A' . $expected . 'A', (new AsciiNameNormalizer(false))->normalize('A' . $character . 'A'));
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\ErrorPayloadParser;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Json;

final class ErrorPayloadParserTest extends TestCase
{
    public function testItParsesASingleErrorObject(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody(Fixtures::load('error_single'));

        static::assertSame(['ERREUR_VALIDATION'], $errors->codes());
        static::assertSame('Erreur de saisie', $errors->all()[0]->description());
        static::assertStringStartsWith('Erreur de saisie sur le champ civilite', $errors->all()[0]->message());
    }

    public function testItParsesAListOfErrorsAndTrimsCodes(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody(Fixtures::load('error_list'));

        static::assertSame(['ERREUR_VALIDATION', 'ERR_DATE_NAISSANCE_FUTUR'], $errors->codes());
    }

    public function testItParsesTheErrorsEnvelopeAndNormalisesInnerSpaces(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody(Fixtures::load('error_envelope'));

        static::assertSame(['ERR_ACOMPTE_BLOQUE'], $errors->codes());
    }

    public function testItParsesTheTechnicalError(): void
    {
        static::assertTrue((new ErrorPayloadParser())->parseBody(Fixtures::load('error_technical'))->hasCode('ERREUR_TECHNIQUE'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableBodies(): iterable
    {
        yield 'empty' => [''];
        yield 'html' => ['<html>Bad gateway</html>'];
        yield 'scalar' => ['"oops"'];
        yield 'empty object' => ['{}'];
        yield 'empty list' => ['[]'];
        yield 'list of scalars' => ['[1, 2]'];
        yield 'errors is not a list' => ['{"errors": {"code": "X"}}'];
    }

    #[DataProvider('unreadableBodies')]
    public function testItIsLenientWithUnreadableBodies(string $body): void
    {
        static::assertTrue((new ErrorPayloadParser())->parseBody($body)->isEmpty());
    }

    public function testEntriesWithoutACodeAreIgnoredAndMissingTextsDefaultToEmpty(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody('[{"message":"no code"},{"code":""},{"code":"ERR_X"}]');

        static::assertSame(['ERR_X'], $errors->codes());
        static::assertSame('', $errors->all()[0]->message());
        static::assertSame('', $errors->all()[0]->description());
    }

    public function testParsePayloadIsStrictOnUnreadableStructures(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        (new ErrorPayloadParser())->parsePayload(Json::payload(['errors' => 'text']));
    }

    public function testParseEntriesWorksOnAnAlreadyExtractedList(): void
    {
        $entries = Json::payload(['errors' => [['code' => 'A'], ['code' => ' B ']]])->items('errors');

        static::assertSame(['A', 'B'], (new ErrorPayloadParser())->parseEntries($entries)->codes());
    }
}

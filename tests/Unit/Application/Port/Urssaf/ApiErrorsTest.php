<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;

final class ApiErrorsTest extends TestCase
{
    public function testApiError(): void
    {
        $error = ApiError::of('ERR_CMPT_BLOQUE', 'Compte bloqué.', 'Le compte du particulier est bloqué.');

        static::assertSame('ERR_CMPT_BLOQUE', $error->code());
        static::assertSame('Compte bloqué.', $error->message());
        static::assertSame('Le compte du particulier est bloqué.', $error->description());
        static::assertTrue($error->hasCode('ERR_CMPT_BLOQUE'));
        static::assertFalse($error->hasCode('ERR_COMPTE_BLOQUE'));
        static::assertTrue($error->equals(ApiError::of('ERR_CMPT_BLOQUE', 'Compte bloqué.', 'Le compte du particulier est bloqué.')));
        static::assertFalse($error->equals(ApiError::of('X', 'Compte bloqué.', 'Le compte du particulier est bloqué.')));
        static::assertFalse($error->equals(ApiError::of('ERR_CMPT_BLOQUE', 'X', 'Le compte du particulier est bloqué.')));
        static::assertFalse($error->equals(ApiError::of('ERR_CMPT_BLOQUE', 'Compte bloqué.', 'X')));
    }

    public function testCollection(): void
    {
        $first = ApiError::of('ERREUR_VALIDATION', 'm', 'd');
        $second = ApiError::of('PARAM_INVALIDE', 'm', 'd');

        $errors = ApiErrors::of($first, $second);

        static::assertFalse($errors->isEmpty());
        static::assertCount(2, $errors);
        static::assertSame([$first, $second], $errors->all());
        static::assertSame(['ERREUR_VALIDATION', 'PARAM_INVALIDE'], $errors->codes());
        static::assertTrue($errors->hasCode('PARAM_INVALIDE'));
        static::assertTrue($errors->hasCode('ERREUR_VALIDATION'));
        static::assertFalse($errors->hasCode('ERREUR_TECHNIQUE'));
    }

    public function testEmptyCollections(): void
    {
        static::assertTrue(ApiErrors::none()->isEmpty());
        static::assertCount(0, ApiErrors::none());
        static::assertSame([], ApiErrors::none()->all());
        static::assertTrue(ApiErrors::of()->isEmpty());
        static::assertFalse(ApiErrors::none()->hasCode('X'));
    }

    public function testOfNormalisesKeys(): void
    {
        $error = ApiError::of('X', 'm', 'd');

        static::assertSame([$error], ApiErrors::of(...['named' => $error])->all());
    }
}

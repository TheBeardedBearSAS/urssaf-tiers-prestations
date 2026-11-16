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
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RefusalReason;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\ErrorClassifier;

final class ErrorClassifierTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, RejectionKind}>
     */
    public static function provideRejections(): iterable
    {
        yield 'validation' => [['ERR_DATE_FUTUR'], RejectionKind::Final];
        yield 'unknown code' => [['ERR_NEW'], RejectionKind::Final];
        yield 'no error' => [[], RejectionKind::Final];
        yield 'technical' => [['ERREUR_TECHNIQUE'], RejectionKind::Retryable];
        yield 'technical twice' => [['ERREUR_TECHNIQUE', 'ERREUR_TECHNIQUE'], RejectionKind::Retryable];
        yield 'technical and validation' => [['ERREUR_TECHNIQUE', 'ERR_DATE_FUTUR'], RejectionKind::Final];
        yield 'validation and technical' => [['ERR_DATE_FUTUR', 'ERREUR_TECHNIQUE'], RejectionKind::Final];
        yield 'technical and unknown' => [['ERREUR_TECHNIQUE', 'ERR_NEW'], RejectionKind::Final];
        yield 'blocked' => [['ERR_CMPT_BLOQUE'], RejectionKind::CustomerBlocked];
        yield 'blocked with validation' => [['ERR_DATE_FUTUR', 'ERR_CMPT_BLOQUE'], RejectionKind::CustomerBlocked];
        yield 'mandate expired' => [['ERR_MANDAT_ECHU'], RejectionKind::MandateExpired];
        yield 'blocked before mandate' => [['ERR_MANDAT_ECHU', 'ERR_CMPT_BLOQUE'], RejectionKind::CustomerBlocked];
        yield 'pairing missing' => [['ERR_LIEN_PARTICULIER_PRESTATAIRE'], RejectionKind::PairingMissing];
        yield 'mandate before pairing' => [['ERR_LIEN_PARTICULIER_PRESTATAIRE', 'ERR_MANDAT_ECHU'], RejectionKind::MandateExpired];
        yield 'pairing before technical' => [['ERREUR_TECHNIQUE', 'ERR_LIEN_PARTICULIER_PRESTATAIRE'], RejectionKind::PairingMissing];
        yield 'duplicate' => [['ERR_FACTURE_DOUBLON'], RejectionKind::Duplicate];
        yield 'duplicate first of all' => [['ERR_CMPT_BLOQUE', 'ERR_FACTURE_DOUBLON'], RejectionKind::Duplicate];
        yield 'normalised code' => [[' ERR_FACTURE_DOUBLON '], RejectionKind::Duplicate];
    }

    /**
     * @param list<string> $codes
     */
    #[DataProvider('provideRejections')]
    public function testClassifiesThePerItemRejections(array $codes, RejectionKind $kind): void
    {
        static::assertSame($kind, ErrorClassifier::rejectionKind(self::errors($codes)));
    }

    /**
     * @return iterable<string, array{list<string>, RefusalReason}>
     */
    public static function provideRefusals(): iterable
    {
        yield 'validation' => [['ERREUR_VALIDATION'], RefusalReason::Generic];
        yield 'none' => [[], RefusalReason::Generic];
        yield 'no active registration' => [['ERREUR_VALIDATION', 'ERR_RECHERCHE_PARTICULIER'], RefusalReason::NoActiveRegistration];
        yield 'already registered' => [['ERR_LIEN_TIERS_PRESTATION_PARTICULIER_EXISTE'], RefusalReason::AlreadyRegistered];
        yield 'link failure' => [['ERR_LIEN_TIERS_PRESTATION_PARTICULIER'], RefusalReason::Generic];
    }

    /**
     * @param list<string> $codes
     */
    #[DataProvider('provideRefusals')]
    public function testClassifiesTheRefusedCalls(array $codes, RefusalReason $reason): void
    {
        static::assertSame($reason, ErrorClassifier::refusalReason(self::errors($codes)));
    }

    /**
     * @param list<string> $codes
     */
    private static function errors(array $codes): ApiErrors
    {
        return ApiErrors::of(...array_map(static fn(string $code): ApiError => ApiError::of($code, '', ''), $codes));
    }
}

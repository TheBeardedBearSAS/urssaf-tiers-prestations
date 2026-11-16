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
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\PaymentRequestSnapshotMapper;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Json;

final class PaymentRequestSnapshotMapperTest extends TestCase
{
    private const ID = 'aaaa1111-2222-3b3b-4d4-55e666ffff77';

    public function testItMapsAFullItemWithAnObjectStatus(): void
    {
        $snapshots = (new PaymentRequestSnapshotMapper())->mapAll(Json::fixture('search_response'));

        static::assertCount(2, $snapshots);
        $first = $snapshots[0];
        static::assertSame(self::ID, $first->paymentRequestId()->value());
        static::assertSame('2022-AZ-00001', $first->invoiceNumber());
        static::assertSame('70', $first->status()->code());
        static::assertSame('Payee', $first->status()->label());
        $rejection = $first->rejection();
        static::assertNotNull($rejection);
        static::assertSame('CONTEST_AUTRE', $rejection->code());
        static::assertSame("La prestation n'a pas ete honoree.", $rejection->comment());
        $transfer = $first->transfer();
        static::assertNotNull($transfer);
        static::assertSame(200_000, $transfer->amount()->cents());
        static::assertSame('2019-12-05', $transfer->valueDate()->toString());
    }

    public function testItMapsAnItemWithAStringStatusAndNoOptionalBlocks(): void
    {
        $second = (new PaymentRequestSnapshotMapper())->mapAll(Json::fixture('search_response'))[1];

        static::assertSame('bbbb1111-2222-3b3b-4d4-55e666ffff88', $second->paymentRequestId()->value());
        static::assertSame('2022-AZ-00002', $second->invoiceNumber());
        static::assertSame('30', $second->status()->code());
        static::assertSame('', $second->status()->label());
        static::assertNull($second->rejection());
        static::assertNull($second->transfer());
    }

    public function testNumericCodesAreRead(): void
    {
        $mapper = new PaymentRequestSnapshotMapper();

        $plain = $mapper->map(Json::payload(['idDemandePaiement' => self::ID, 'statut' => 70]));
        $object = $mapper->map(Json::payload([
            'idDemandePaiement' => self::ID,
            'statut' => ['code' => 120, 'libelle' => 'Recouvree'],
            'infoRejet' => ['code' => 3],
        ]));

        static::assertSame('70', $plain->status()->code());
        static::assertSame('120', $object->status()->code());
        static::assertSame('Recouvree', $object->status()->label());
        static::assertSame('3', $object->rejection()?->code());
    }

    public function testAResponseWithoutItemsIsEmpty(): void
    {
        static::assertSame([], (new PaymentRequestSnapshotMapper())->mapAll(Json::payload(['errors' => []])));
    }

    public function testTheInvoiceNumberFallsBackToTheTopLevelThenToEmpty(): void
    {
        $mapper = new PaymentRequestSnapshotMapper();

        $top = $mapper->map(Json::payload(['idDemandePaiement' => self::ID, 'numFactureTiers' => 'TOP', 'statut' => '10']));
        $none = $mapper->map(Json::payload(['idDemandePaiement' => self::ID, 'statut' => '10']));

        static::assertSame('TOP', $top->invoiceNumber());
        static::assertSame('', $none->invoiceNumber());
    }

    public function testTheNestedInvoiceNumberWinsOverTheTopLevelOne(): void
    {
        $snapshot = (new PaymentRequestSnapshotMapper())->map(Json::payload([
            'idDemandePaiement' => self::ID,
            'numFactureTiers' => 'TOP',
            'demandePaiement' => ['numFactureTiers' => 'NESTED'],
            'statut' => '10',
        ]));

        static::assertSame('NESTED', $snapshot->invoiceNumber());
    }

    public function testAMissingRejectionCommentDefaultsToEmpty(): void
    {
        $snapshot = (new PaymentRequestSnapshotMapper())->map(Json::payload([
            'idDemandePaiement' => self::ID,
            'statut' => ['code' => '40'],
            'infoRejet' => ['code' => 'CONTEST_ERR_FACT'],
        ]));

        static::assertSame('', $snapshot->rejection()?->comment());
        static::assertSame('', $snapshot->status()->label());
    }

    public function testTransferAmountsAreDecodedWithoutFloatDrift(): void
    {
        $snapshot = (new PaymentRequestSnapshotMapper())->map(Json::payload([
            'idDemandePaiement' => self::ID,
            'statut' => '70',
            'infoVirement' => ['mntVirement' => 11.02, 'dateVirement' => '2022-02-10T00:00:00.000Z'],
        ]));

        $transfer = $snapshot->transfer();
        static::assertNotNull($transfer);
        static::assertSame(1102, $transfer->amount()->cents());
        static::assertSame('2022-02-10', $transfer->valueDate()->toString());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function invalidItems(): iterable
    {
        yield 'no id' => [['statut' => '10']];
        yield 'id too long' => [['idDemandePaiement' => str_repeat('a', 65), 'statut' => '10']];
        yield 'no status' => [['idDemandePaiement' => self::ID]];
        yield 'status object without code' => [['idDemandePaiement' => self::ID, 'statut' => ['libelle' => 'x']]];
        yield 'status object with an empty code' => [['idDemandePaiement' => self::ID, 'statut' => ['code' => '']]];
        yield 'status as a boolean' => [['idDemandePaiement' => self::ID, 'statut' => true]];
        yield 'rejection without code' => [['idDemandePaiement' => self::ID, 'statut' => '10', 'infoRejet' => ['commentaire' => 'x']]];
        yield 'transfer without amount' => [['idDemandePaiement' => self::ID, 'statut' => '10', 'infoVirement' => ['dateVirement' => '2022-02-10T00:00:00Z']]];
        yield 'transfer without date' => [['idDemandePaiement' => self::ID, 'statut' => '10', 'infoVirement' => ['mntVirement' => 10]]];
        yield 'transfer with negative amount' => [['idDemandePaiement' => self::ID, 'statut' => '10', 'infoVirement' => ['mntVirement' => -1, 'dateVirement' => '2022-02-10T00:00:00Z']]];
    }

    /**
     * @param array<array-key, mixed> $item
     */
    #[DataProvider('invalidItems')]
    public function testItRejectsMalformedItems(array $item): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        (new PaymentRequestSnapshotMapper())->map(Json::payload($item));
    }
}

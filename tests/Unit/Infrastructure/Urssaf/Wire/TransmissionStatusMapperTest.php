<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Wire;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\TransmissionStatusMapper;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Json;

final class TransmissionStatusMapperTest extends TestCase
{
    private const CUSTOMER = 'af0be430-908a-11eb-a8b3-0242ac130003';

    public function testItMapsAValidPairingWithItsAcceptanceDate(): void
    {
        $status = (new TransmissionStatusMapper())->map(
            UrssafCustomerId::fromString(self::CUSTOMER),
            Json::fixture('customer_status_pairing_valid'),
        );

        static::assertTrue($status->isTransmissionAllowed());
        static::assertSame('APPAREILLAGE_VALIDE', $status->code());
        static::assertSame(self::CUSTOMER, $status->customerId()->value());
        static::assertSame('Transmission DP possible depuis le 15/03/2023 14:30:05', $status->description());
        static::assertSame('2023-03-15T13:30:05Z', $status->pairingAcceptedAt()?->toIso8601());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function acceptanceDates(): iterable
    {
        yield 'winter time (UTC+1)' => ['le 15/01/2023 08:00:00', '2023-01-15T07:00:00Z'];
        yield 'summer time (UTC+2)' => ['le 15/07/2023 08:00:00', '2023-07-15T06:00:00Z'];
        yield 'date alone in the description' => ['01/02/2023 00:00:00', '2023-01-31T23:00:00Z'];
    }

    #[DataProvider('acceptanceDates')]
    public function testItReadsTheAcceptanceDateInParisTime(string $description, string $expectedUtc): void
    {
        $status = (new TransmissionStatusMapper())->map(
            UrssafCustomerId::fromString(self::CUSTOMER),
            Json::payload(['statut' => ['code' => 'APPAREILLAGE_VALIDE', 'description' => $description, 'etat' => 'OK']]),
        );

        static::assertSame($expectedUtc, $status->pairingAcceptedAt()?->toIso8601());
    }

    public function testItReadsTheAcceptanceDateInTheConfiguredTimeZone(): void
    {
        $status = (new TransmissionStatusMapper(new DateTimeZone('America/New_York')))->map(
            UrssafCustomerId::fromString(self::CUSTOMER),
            Json::payload(['statut' => ['code' => 'APPAREILLAGE_VALIDE', 'description' => 'le 15/01/2023 08:00:00', 'etat' => 'OK']]),
        );

        static::assertSame('2023-01-15T13:00:00Z', $status->pairingAcceptedAt()?->toIso8601());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function blockedFixtures(): iterable
    {
        yield 'pairing in progress' => ['pairing_in_progress', 'APPAREILLAGE_EC'];
        yield 'pairing not finalised' => ['pairing_not_finalised', 'APPAREILLAGE_NF'];
        yield 'customer blocked' => ['customer_blocked', 'PARTICULIER_BLOQUE'];
        yield 'mandate expired' => ['mandate_expired', 'MANDAT_ECHU'];
    }

    #[DataProvider('blockedFixtures')]
    public function testItMapsEveryBlockedStatus(string $fixture, string $expectedCode): void
    {
        $status = (new TransmissionStatusMapper())->map(
            UrssafCustomerId::fromString(self::CUSTOMER),
            Json::fixture('customer_status_' . $fixture),
        );

        static::assertFalse($status->isTransmissionAllowed());
        static::assertSame($expectedCode, $status->code());
        static::assertNull($status->pairingAcceptedAt());
        static::assertNotSame('', $status->description());
    }

    public function testAnUnknownCodeIsKeptRawAsBlocked(): void
    {
        $status = (new TransmissionStatusMapper())->map(
            UrssafCustomerId::fromString(self::CUSTOMER),
            Json::payload(['statut' => ['code' => 'NEW_CODE', 'etat' => 'OK']]),
        );

        static::assertFalse($status->isTransmissionAllowed());
        static::assertSame('NEW_CODE', $status->code());
        static::assertSame('', $status->description());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function unparseableStatuses(): iterable
    {
        yield 'no status' => [['idClient' => self::CUSTOMER]];
        yield 'no code' => [['statut' => ['description' => 'x']]];
        yield 'no date in description' => [['statut' => ['code' => 'APPAREILLAGE_VALIDE', 'description' => 'Transmission possible']]];
        yield 'no description' => [['statut' => ['code' => 'APPAREILLAGE_VALIDE']]];
        yield 'impossible day' => [['statut' => ['code' => 'APPAREILLAGE_VALIDE', 'description' => '31/02/2023 10:00:00']]];
        yield 'impossible hour' => [['statut' => ['code' => 'APPAREILLAGE_VALIDE', 'description' => '15/03/2023 25:00:00']]];
        yield 'american format' => [['statut' => ['code' => 'APPAREILLAGE_VALIDE', 'description' => '2023-03-15 10:00:00']]];
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    #[DataProvider('unparseableStatuses')]
    public function testItRejectsUnusableResponses(array $payload): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        (new TransmissionStatusMapper())->map(UrssafCustomerId::fromString(self::CUSTOMER), Json::payload($payload));
    }

    public function testItNamesTheInvalidAcceptanceDate(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('pairing acceptance date "31/02/2023 10:00:00" is not a valid date');

        (new TransmissionStatusMapper())->map(
            UrssafCustomerId::fromString(self::CUSTOMER),
            Json::payload(['statut' => ['code' => 'APPAREILLAGE_VALIDE', 'description' => 'le 31/02/2023 10:00:00']]),
        );
    }
}

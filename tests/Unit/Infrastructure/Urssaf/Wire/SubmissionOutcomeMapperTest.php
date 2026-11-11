<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SubmissionOutcome;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\SubmissionOutcomeMapper;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Json;

final class SubmissionOutcomeMapperTest extends TestCase
{
    public function testAnIntegratedRequestIsAccepted(): void
    {
        $outcomes = (new SubmissionOutcomeMapper())->mapAll(Json::fixture('submit_response_accepted'));

        static::assertCount(1, $outcomes);
        static::assertTrue($outcomes[0]->isAccepted());
        static::assertSame('2022-AZ-00001', $outcomes[0]->invoiceNumber());
        static::assertSame('10', $outcomes[0]->rawStatus());
        static::assertSame('aaaa1111-2222-3b3b-4d4-55e666ffff77', $outcomes[0]->paymentRequestId()?->value());
        static::assertTrue($outcomes[0]->errors()->isEmpty());
    }

    public function testAParamInvalidItemIsRejectedAndKeepsTheStatus(): void
    {
        $outcome = (new SubmissionOutcomeMapper())->mapAll(Json::fixture('submit_response_param_invalid'))[0];

        static::assertFalse($outcome->isAccepted());
        static::assertSame('11000000000104', $outcome->invoiceNumber());
        static::assertSame('10', $outcome->rawStatus());
        static::assertSame(['PARAM_INVALIDE'], $outcome->errors()->codes());
        static::assertNull($outcome->paymentRequestId());
    }

    public function testABlockedAccountItemIsRejectedWithAnErrorStatus(): void
    {
        $outcome = (new SubmissionOutcomeMapper())->mapAll(Json::fixture('submit_response_account_blocked'))[0];

        static::assertFalse($outcome->isAccepted());
        static::assertSame('ERR_CMPT_BLOQUE', $outcome->rawStatus());
        static::assertSame(['ERR_CMPT_BLOQUE'], $outcome->errors()->codes());
        static::assertSame('Compte bloque.', $outcome->errors()->all()[0]->message());
    }

    public function testOutcomesKeepTheSubmissionOrder(): void
    {
        $outcomes = (new SubmissionOutcomeMapper())->mapAll(Json::payload([
            ['numFactureTiers' => 'A', 'idDemandePaiement' => 'id-a', 'statut' => '10'],
            ['numFactureTiers' => 'B', 'statut' => 'ERR_X', 'errors' => [['code' => 'ERR_X']]],
        ]));

        static::assertSame(['A', 'B'], array_map(static fn(SubmissionOutcome $outcome): string => $outcome->invoiceNumber(), $outcomes));
    }

    public function testAnItemWithAnIdAndErrorsIsIntegratedWithWarnings(): void
    {
        $outcome = (new SubmissionOutcomeMapper())->map(Json::payload([
            'numFactureTiers' => 'A',
            'idDemandePaiement' => 'id-a',
            'statut' => 10,
            'errors' => [['code' => 'ERR_X']],
        ]));

        static::assertTrue($outcome->isAccepted());
        static::assertSame('id-a', $outcome->paymentRequestId()?->value());
        static::assertSame('10', $outcome->rawStatus());
        static::assertSame(['ERR_X'], $outcome->errors()->codes());
        static::assertNull($outcome->rejectionKind());
    }

    public function testARejectedItemIsClassified(): void
    {
        $outcome = (new SubmissionOutcomeMapper())->map(Json::payload([
            'numFactureTiers' => 'A',
            'statut' => 'ERR_FACTURE_DOUBLON',
            'errors' => [['code' => 'ERR_FACTURE_DOUBLON']],
        ]));

        static::assertSame(RejectionKind::Duplicate, $outcome->rejectionKind());
    }

    public function testMissingTextualFieldsDefaultToEmptyStrings(): void
    {
        $outcome = (new SubmissionOutcomeMapper())->map(Json::payload(['errors' => [['code' => 'ERR_X']]]));

        static::assertSame('', $outcome->invoiceNumber());
        static::assertSame('', $outcome->rawStatus());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function invalidItems(): iterable
    {
        yield 'neither id nor error' => [['numFactureTiers' => 'A', 'idDemandePaiement' => '', 'statut' => '10', 'errors' => []]];
        yield 'no id and no errors key' => [['numFactureTiers' => 'A', 'statut' => '10']];
        yield 'id too long' => [['numFactureTiers' => 'A', 'idDemandePaiement' => str_repeat('a', 65), 'statut' => '10']];
        yield 'errors is not a list' => [['numFactureTiers' => 'A', 'errors' => ['code' => 'X']]];
    }

    /**
     * @param array<array-key, mixed> $item
     */
    #[DataProvider('invalidItems')]
    public function testItRejectsInconsistentItems(array $item): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        (new SubmissionOutcomeMapper())->map(Json::payload($item));
    }

    public function testItRejectsAResponseThatIsNotAList(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        (new SubmissionOutcomeMapper())->mapAll(Json::payload(['statut' => '10']));
    }
}

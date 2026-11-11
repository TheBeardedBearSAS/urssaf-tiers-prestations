<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewaySearchTest extends TestCase
{
    private function byIds(): PaymentRequestSearch
    {
        return PaymentRequestSearch::byPaymentRequestIds(
            PaymentRequestId::fromString('aaaa1111-2222-3b3b-4d4-55e666ffff77'),
            PaymentRequestId::fromString('bbbb1111-2222-3b3b-4d4-55e666ffff88'),
        );
    }

    public function testItSearchesByPaymentRequestIdsAndMapsTheSnapshots(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('search_response'));

        $snapshots = $stack->gateway()->search(Stacks::tenant(), $this->byIds());

        static::assertCount(2, $snapshots);
        static::assertSame('70', $snapshots[0]->status()->code());
        static::assertSame('30', $snapshots[1]->status()->code());
        $request = $stack->lastRequest();
        static::assertSame('POST', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/atp/v1/tiersPrestations/demandePaiement/rechercher',
            (string) $request->getUri(),
        );
        static::assertSame(
            '{"idDemandePaiements":["aaaa1111-2222-3b3b-4d4-55e666ffff77","bbbb1111-2222-3b3b-4d4-55e666ffff88"]}',
            (string) $request->getBody(),
        );
        static::assertSame(['tenant-1:search_payment_requests'], $stack->rateLimiter->acquired());
    }

    public function testItSearchesByInvoiceNumbers(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('search_response'));

        $stack->gateway()->search(Stacks::tenant(), PaymentRequestSearch::byInvoiceNumbers(InvoiceNumber::fromString('2022-AZ-00001')));

        static::assertSame('{"numFactureTiers":["2022-AZ-00001"]}', (string) $stack->lastRequest()->getBody());
    }

    public function testItSearchesByInvoicePeriod(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('search_response'));

        $stack->gateway()->search(
            Stacks::tenant(),
            PaymentRequestSearch::byInvoicePeriod(CalendarDate::fromString('2022-02-01'), CalendarDate::fromString('2022-02-28')),
        );

        static::assertSame(
            '{"dateDebut":"2022-02-01T00:00:00Z","dateFin":"2022-02-28T00:00:00Z"}',
            (string) $stack->lastRequest()->getBody(),
        );
    }

    public function testNoResultReportedInA200ErrorsListIsAnEmptyResult(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('search_response_empty'));

        static::assertSame([], $stack->gateway()->search(Stacks::tenant(), $this->byIds()));
    }

    public function testNoResultReportedAsAnHttp400IsAnEmptyResult(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(400, Fixtures::load('error_search_empty'));

        static::assertSame([], $stack->gateway()->search(Stacks::tenant(), $this->byIds()));
    }

    public function testAResponseWithoutItemsNorErrorsIsEmpty(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"errors":[],"infoDemandePaiements":[]}');

        static::assertSame([], $stack->gateway()->search(Stacks::tenant(), $this->byIds()));
    }

    public function testTooManyResultsIsARejection(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('search_response_too_many'));

        try {
            $stack->gateway()->search(
                Stacks::tenant(),
                PaymentRequestSearch::byInvoicePeriod(CalendarDate::fromString('2022-01-01'), CalendarDate::fromString('2022-12-31')),
            );
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $exception) {
            static::assertTrue($exception->hasErrorCode('ERR_NBRE_MAX_RESULTAT'));
            static::assertSame(200, $exception->httpStatus());
        }
    }

    public function testOtherHttp400ErrorsAreRethrown(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(400, Fixtures::load('error_single'));

        try {
            $stack->gateway()->search(Stacks::tenant(), $this->byIds());
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $exception) {
            static::assertTrue($exception->hasErrorCode('ERREUR_VALIDATION'));
            static::assertSame(400, $exception->httpStatus());
        }
    }

    public function testAMalformedItemIsAnUnexpectedResponse(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"infoDemandePaiements":[{"statut":"10"}]}');

        $this->expectException(UnexpectedUrssafResponse::class);

        $stack->gateway()->search(Stacks::tenant(), $this->byIds());
    }
}

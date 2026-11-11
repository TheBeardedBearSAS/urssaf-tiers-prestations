<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures as DomainFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewaySubmitTest extends TestCase
{
    public function testItSubmitsAndMapsAcceptedAndRejectedOutcomesInOrder(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('050-submit-response-mixed'));

        $outcomes = $stack->gateway()->submit(
            Stacks::tenant(),
            DomainFixtures::pendingRequest(),
            DomainFixtures::pendingRequest(),
        );

        static::assertCount(2, $outcomes);
        static::assertTrue($outcomes[0]->isAccepted());
        static::assertSame('2022-AZ-00001', $outcomes[0]->invoiceNumber());
        static::assertSame('10', $outcomes[0]->rawStatus());
        static::assertFalse($outcomes[1]->isAccepted());
        static::assertSame('2022-AZ-00002', $outcomes[1]->invoiceNumber());
        static::assertSame('ERR_CMPT_BLOQUE', $outcomes[1]->rawStatus());
        static::assertTrue($outcomes[1]->errors()->hasCode('ERR_CMPT_BLOQUE'));
        $request = $stack->lastRequest();
        static::assertSame('POST', $request->getMethod());
        static::assertSame('https://api-edi.urssaf.fr/atp/v1/tiersPrestations/demandePaiement', (string) $request->getUri());
        static::assertSame(['tenant-1:submit_payment_requests'], $stack->rateLimiter->acquired());
        static::assertSame(2, substr_count((string) $request->getBody(), '"numFactureTiers"'));
    }

    public function testItSendsTheBodyOfTheMapper(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('submit_response_accepted'));

        $stack->gateway()->submit(Stacks::tenant(), DomainFixtures::pendingRequest());

        static::assertStringStartsWith(
            '[{"idTiersFacturation":"id-facturation","idClient":"6a56a628-b09e-8707-787e-10f218a2d550",',
            (string) $stack->lastRequest()->getBody(),
        );
    }

    public function testAnAcceptedOutcomeCarriesTheUrssafId(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('submit_response_accepted'));

        $outcomes = $stack->gateway()->submit(Stacks::tenant(), DomainFixtures::pendingRequest());

        static::assertSame('aaaa1111-2222-3b3b-4d4-55e666ffff77', $outcomes[0]->paymentRequestId()?->value());
    }

    public function testAnAnswerCountDifferentFromTheRequestCountIsLogged(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('submit_response_accepted'));

        $outcomes = $stack->gateway()->submit(Stacks::tenant(), DomainFixtures::pendingRequest(), DomainFixtures::pendingRequest());

        static::assertCount(1, $outcomes);
        $records = $stack->logger->records();
        static::assertSame([
            'level' => 'error',
            'message' => 'URSSAF method 050 answered a different number of outcomes than submitted payment requests.',
            'context' => ['request_id' => 'request-id-1', 'submitted' => 2, 'answered' => 1],
        ], $records[\count($records) - 1]);
    }

    public function testAMatchingAnswerCountIsNotLoggedAsAnError(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('submit_response_accepted'));

        $stack->gateway()->submit(Stacks::tenant(), DomainFixtures::pendingRequest());

        static::assertSame(['info', 'info'], array_column($stack->logger->records(), 'level'));
    }

    public function testMalformedInputIsARejection(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(400, Fixtures::load('error_single'));

        $this->expectException(UrssafRequestRejected::class);

        $stack->gateway()->submit(Stacks::tenant(), DomainFixtures::pendingRequest());
    }

    public function testNoRequestIsRefusedWithoutAnyCall(): void
    {
        $stack = Stacks::sandbox();

        try {
            $stack->gateway()->submit(Stacks::tenant());
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('payment_request_submission.empty', $exception->violationCode());
        }
        static::assertSame([], $stack->requests());
    }

    public function testMoreThanTenRequestsAreRefusedWithoutAnyCall(): void
    {
        $stack = Stacks::sandbox();

        try {
            $stack->gateway()->submit(Stacks::tenant(), ...$this->requests(11));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('payment_request_submission.too_many_items', $exception->violationCode());
            static::assertSame(['max' => 10, 'given' => 11], $exception->params());
        }
        static::assertSame([], $stack->requests());
    }

    public function testExactlyTenRequestsAreAccepted(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '[' . implode(',', array_fill(0, 10, '{"idClient":"c","idDemandePaiement":"p","numFactureTiers":"n","statut":"10","errors":[]}')) . ']');

        static::assertCount(10, $stack->gateway()->submit(Stacks::tenant(), ...$this->requests(10)));
    }

    /**
     * @param positive-int $count
     *
     * @return list<PaymentRequest>
     */
    private function requests(int $count): array
    {
        return array_map(DomainFixtures::pendingRequest(...), range(1, $count));
    }
}

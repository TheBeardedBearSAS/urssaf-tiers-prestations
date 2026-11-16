<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Testing;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\CustomerTransmissionStatus;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSnapshot;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafTiersPrestations\Testing\SimulatedUrssafGateway;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\SequentialIdGenerator;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory\PaymentRequestBuilder;

/**
 * One method per behaviour of the simulator.
 *
 * @mago-expect lint:too-many-methods
 */
final class SimulatedUrssafGatewayTest extends TestCase
{
    private const NOW = '2022-03-01T23:30:00Z';
    private const ID_PREFIX = '00000000-0000-4000-8000-00000000000';

    public function testARegistrationIsAcceptedWithANewCustomerId(): void
    {
        $urssaf = self::gateway();

        $first = $urssaf->register(self::tenant(), CustomerFixtures::newRegistration());
        $second = $urssaf->register(self::tenant(), CustomerFixtures::newRegistration());

        static::assertSame(self::ID_PREFIX . '1', $first->value());
        static::assertSame(self::ID_PREFIX . '2', $second->value());
        static::assertEquals([$first, $second], $urssaf->customerIds());
        static::assertSame([Operation::RegisterCustomer, Operation::RegisterCustomer], $urssaf->calls());
    }

    public function testTheNextRegistrationIsRefusedWithTheGivenCodes(): void
    {
        $urssaf = self::gateway();
        $urssaf->refuseNextRegistration('ERR_DGFIP_R2P_INCONNU', ...['more' => 'ERR_PARTICULIER_INCONNU']);

        $refusal = self::refusal(static fn(): UrssafCustomerId => $urssaf->register(self::tenant(), CustomerFixtures::newRegistration()));

        static::assertSame(Operation::RegisterCustomer, $refusal->operation());
        static::assertSame(400, $refusal->httpStatus());
        static::assertSame(['ERR_DGFIP_R2P_INCONNU', 'ERR_PARTICULIER_INCONNU'], $refusal->errors()->codes());
        static::assertFalse($refusal->isAlreadyRegistered());
        static::assertSame([], $urssaf->customerIds());
        $urssaf->register(self::tenant(), CustomerFixtures::newRegistration());
        static::assertCount(1, $urssaf->customerIds());
    }

    public function testARefusalCarriesTheReasonOfItsCodes(): void
    {
        $urssaf = self::gateway();
        $urssaf->refuseNextRegistration('ERR_LIEN_TIERS_PRESTATION_PARTICULIER_EXISTE');

        static::assertTrue(self::refusal(static fn(): UrssafCustomerId => $urssaf->register(self::tenant(), CustomerFixtures::newRegistration()))->isAlreadyRegistered());
    }

    public function testQueuedFailuresAreThrownInOrderByTheirOperationOnly(): void
    {
        $urssaf = self::gateway();
        $first = new RuntimeException('first');
        $second = new RuntimeException('second');
        $urssaf->failNext(Operation::RegisterCustomer, $first);
        $urssaf->failNext(Operation::RegisterCustomer, $second);

        $id = self::registered($urssaf);
        static::assertSame(PairingStatus::Validated->value, $urssaf->transmissionStatus(self::tenant(), $id)->code());

        static::assertSame([Operation::RegisterCustomer, Operation::RegisterCustomer, Operation::RegisterCustomer, Operation::GetCustomerStatus], $urssaf->calls());
        static::assertSame(['first', 'second'], $this->thrown);
    }

    public function testTheDefaultPairingIsValidAtOnce(): void
    {
        $urssaf = self::gateway();
        $id = $urssaf->register(self::tenant(), CustomerFixtures::newRegistration());

        $status = $urssaf->transmissionStatus(self::tenant(), $id);

        static::assertTrue($status->isTransmissionAllowed());
        static::assertSame('APPAREILLAGE_VALIDE', $status->code());
        static::assertSame('Simulated pairing status.', $status->description());
        static::assertEquals($id, $status->customerId());
        static::assertSame(self::NOW, $status->pairingAcceptedAt()?->toIso8601());
    }

    public function testEachPollReturnsTheNextPairingStatusThenRepeatsTheLastOne(): void
    {
        $urssaf = self::gateway();
        $before = $urssaf->register(self::tenant(), CustomerFixtures::newRegistration());
        $urssaf->pairCustomersThrough(PairingStatus::InProgress, ...['then' => PairingStatus::Validated]);
        $after = $urssaf->register(self::tenant(), CustomerFixtures::newRegistration());

        $codes = [];
        for ($poll = 0; $poll < 3; ++$poll) {
            $codes[] = $urssaf->transmissionStatus(self::tenant(), $after)->code();
        }

        static::assertSame(['APPAREILLAGE_EC', 'APPAREILLAGE_VALIDE', 'APPAREILLAGE_VALIDE'], $codes);
        static::assertSame('APPAREILLAGE_VALIDE', $urssaf->transmissionStatus(self::tenant(), $before)->code());
    }

    public function testABlockingPairingStatusForbidsTheTransmission(): void
    {
        $urssaf = self::gateway();
        $urssaf->pairCustomersThrough(PairingStatus::CustomerBlocked);
        $id = $urssaf->register(self::tenant(), CustomerFixtures::newRegistration());

        $status = $urssaf->transmissionStatus(self::tenant(), $id);

        static::assertFalse($status->isTransmissionAllowed());
        static::assertSame('PARTICULIER_BLOQUE', $status->code());
        static::assertSame('Simulated pairing status.', $status->description());
        static::assertNull($status->pairingAcceptedAt());
    }

    public function testThePairingStatusOfACustomerCanBeForced(): void
    {
        $urssaf = self::gateway();
        $id = $urssaf->register(self::tenant(), CustomerFixtures::newRegistration());

        $urssaf->setPairingStatus($id, PairingStatus::MandateExpired);

        static::assertSame('MANDAT_ECHU', $urssaf->transmissionStatus(self::tenant(), $id)->code());
        static::assertSame('MANDAT_ECHU', $urssaf->transmissionStatus(self::tenant(), $id)->code());
    }

    public function testForcingTheStatusOfAnUnknownCustomerIsAMistakeOfTheTest(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The customer "' . self::ID_PREFIX . '9" is not registered with the simulator.');

        self::gateway()->setPairingStatus(UrssafCustomerId::fromString(self::ID_PREFIX . '9'), PairingStatus::Validated);
    }

    public function testTheStatusOfACustomerUnknownToTheTenantIsRefused(): void
    {
        $urssaf = self::gateway();
        $id = $urssaf->register(self::tenant(), CustomerFixtures::newRegistration());

        $refusals = [
            self::refusal(static fn(): CustomerTransmissionStatus => $urssaf->transmissionStatus(TenantId::fromString('tenant-2'), $id)),
            self::refusal(static fn(): CustomerTransmissionStatus => $urssaf->transmissionStatus(self::tenant(), UrssafCustomerId::fromString(self::ID_PREFIX . '9'))),
        ];

        foreach ($refusals as $refusal) {
            static::assertSame(Operation::GetCustomerStatus, $refusal->operation());
            static::assertTrue($refusal->isNoActiveRegistration());
            static::assertSame(['ERR_RECHERCHE_PARTICULIER'], $refusal->errors()->codes());
        }
    }

    public function testEverySubmittedRequestIsAcceptedWithANewIdAndTheFirstStatus(): void
    {
        $urssaf = self::gateway();

        $outcomes = $urssaf->submit(self::tenant(), ...['first' => self::request('1', 'INV-1'), 'second' => self::request('2', 'INV-2')]);

        static::assertCount(2, $outcomes);
        static::assertTrue($outcomes[0]->isAccepted());
        static::assertSame('INV-1', $outcomes[0]->invoiceNumber());
        static::assertSame('10', $outcomes[0]->rawStatus());
        static::assertSame(self::ID_PREFIX . '1', $outcomes[0]->paymentRequestId()?->value());
        static::assertSame(self::ID_PREFIX . '2', $outcomes[1]->paymentRequestId()?->value());
        static::assertEquals([$outcomes[0]->paymentRequestId(), $outcomes[1]->paymentRequestId()], $urssaf->paymentRequestIds());
        static::assertSame([Operation::SubmitPaymentRequests], $urssaf->calls());
    }

    public function testASubmissionHoldsOneToTenRequests(): void
    {
        $urssaf = self::gateway();
        $requests = array_map(static fn(int $n): PaymentRequest => self::request((string) $n, 'INV-' . $n), range(1, 11));

        foreach ([[], $requests] as $batch) {
            try {
                $urssaf->submit(self::tenant(), ...$batch);
                static::fail('The submission should be refused.');
            } catch (InvariantViolation $violation) {
                static::assertSame('payment_request_submission.too_many_items', $violation->violationCode());
                static::assertSame('Between 1 and 10 payment requests can be submitted at once.', $violation->getMessage());
            }
        }
        static::assertCount(10, $urssaf->submit(self::tenant(), ...\array_slice($requests, 0, 10)));
    }

    public function testAnInvoiceNumberAlreadySubmittedByTheTenantIsADuplicate(): void
    {
        $urssaf = self::gateway();
        $urssaf->submit(self::tenant(), self::request('1', 'INV-1'));

        $duplicate = $urssaf->submit(self::tenant(), self::request('2', 'INV-1'))[0];
        $otherTenant = $urssaf->submit(TenantId::fromString('tenant-2'), self::request('3', 'INV-1', 'tenant-2'))[0];

        static::assertFalse($duplicate->isAccepted());
        static::assertSame(RejectionKind::Duplicate, $duplicate->rejectionKind());
        static::assertSame(['ERR_FACTURE_DOUBLON'], $duplicate->errors()->codes());
        static::assertSame('SIMULATED', $duplicate->rawStatus());
        static::assertTrue($otherTenant->isAccepted());
        static::assertCount(2, $urssaf->paymentRequestIds());
    }

    public function testTheNextSubmissionOfAnInvoiceIsRejectedWithTheKindOfItsCodes(): void
    {
        $urssaf = self::gateway();
        $urssaf->rejectNextSubmissionOf('INV-1', 'ERR_DATE_FUTUR', ...['more' => 'ERR_MONTANT_ACOMPTE']);
        $urssaf->rejectNextSubmissionOf('INV-2', 'ERREUR_TECHNIQUE');

        [$final, $retryable, $accepted] = $urssaf->submit(self::tenant(), self::request('1', 'INV-1'), self::request('2', 'INV-2'), self::request('3', 'INV-3'));

        static::assertSame(RejectionKind::Final, $final->rejectionKind());
        static::assertSame(['ERR_DATE_FUTUR', 'ERR_MONTANT_ACOMPTE'], $final->errors()->codes());
        static::assertSame('Simulated error.', $final->errors()->all()[0]->message());
        static::assertSame('Simulated by the test gateway.', $final->errors()->all()[0]->description());
        static::assertSame(RejectionKind::Retryable, $retryable->rejectionKind());
        static::assertTrue($accepted->isAccepted());
        static::assertTrue($urssaf->submit(self::tenant(), self::request('1', 'INV-1'))[0]->isAccepted());
    }

    public function testEachSearchReturnsTheNextStatusOfTheRequestsThenRepeatsTheLastOne(): void
    {
        $urssaf = self::gateway();
        $id = self::submitted($urssaf, 'INV-1');

        $statuses = [];
        for ($search = 0; $search < 4; ++$search) {
            $statuses[] = self::only($urssaf->search(self::tenant(), PaymentRequestSearch::byPaymentRequestIds($id)))->status()->code();
        }

        static::assertSame(['20', '30', '70', '70'], $statuses);
    }

    public function testASnapshotDescribesTheRequestAndItsStatus(): void
    {
        $urssaf = self::gateway();
        $id = self::submitted($urssaf, 'INV-1');

        $snapshot = self::only($urssaf->search(self::tenant(), PaymentRequestSearch::byPaymentRequestIds($id)));

        static::assertEquals($id, $snapshot->paymentRequestId());
        static::assertSame('INV-1', $snapshot->invoiceNumber());
        static::assertSame('Awaiting customer validation', $snapshot->status()->label());
        static::assertNull($snapshot->rejection());
        static::assertNull($snapshot->transfer());
    }

    public function testASettledRequestCarriesATransferOfItsTotalOnTheBusinessDate(): void
    {
        foreach ([PaymentRequestStatus::Paid, PaymentRequestStatus::Recovered] as $status) {
            $urssaf = self::gateway();
            $urssaf->progressPaymentRequestsThrough($status);
            $id = self::submitted($urssaf, 'INV-1');

            $transfer = self::only($urssaf->search(self::tenant(), PaymentRequestSearch::byPaymentRequestIds($id)))->transfer();

            static::assertNotNull($transfer);
            static::assertSame('11.02', $transfer->amount()->toDecimalString());
            static::assertSame('2022-03-02', $transfer->valueDate()->toString());
        }
    }

    public function testTheBusinessTimeZoneOfTheTransferDateCanBeChanged(): void
    {
        $urssaf = new SimulatedUrssafGateway(self::clock(), new SequentialIdGenerator(self::ID_PREFIX), new DateTimeZone('UTC'));
        $urssaf->progressPaymentRequestsThrough(PaymentRequestStatus::Paid);
        $id = self::submitted($urssaf, 'INV-1');

        $transfer = self::only($urssaf->search(self::tenant(), PaymentRequestSearch::byPaymentRequestIds($id)))->transfer();

        static::assertSame('2022-03-01', $transfer?->valueDate()->toString());
    }

    public function testARefusedRequestCarriesARejection(): void
    {
        foreach ([PaymentRequestStatus::Refused, PaymentRequestStatus::DebitRefused] as $status) {
            $urssaf = self::gateway();
            $urssaf->progressPaymentRequestsThrough(PaymentRequestStatus::Integrated, $status);
            $id = self::submitted($urssaf, 'INV-1');

            $snapshot = self::only($urssaf->search(self::tenant(), PaymentRequestSearch::byPaymentRequestIds($id)));

            static::assertSame((string) $status->value, $snapshot->status()->code());
            $rejection = $snapshot->rejection();
            static::assertNotNull($rejection);
            static::assertSame('SIMULATED', $rejection->code());
            static::assertSame('Simulated refusal.', $rejection->comment());
            static::assertNull($snapshot->transfer());
        }
    }

    public function testTheStatusSequenceAppliesToTheRequestsSubmittedAfterwards(): void
    {
        $urssaf = self::gateway();
        $before = self::submitted($urssaf, 'INV-1');
        $urssaf->progressPaymentRequestsThrough(PaymentRequestStatus::AwaitingValidation, ...['then' => PaymentRequestStatus::Cancelled]);

        $outcome = $urssaf->submit(self::tenant(), self::request('2', 'INV-2'))[0];
        $after = $outcome->paymentRequestId();
        static::assertNotNull($after);

        static::assertSame('20', $outcome->rawStatus());
        static::assertSame('110', self::only($urssaf->search(self::tenant(), PaymentRequestSearch::byPaymentRequestIds($after)))->status()->code());
        static::assertSame('20', self::only($urssaf->search(self::tenant(), PaymentRequestSearch::byPaymentRequestIds($before)))->status()->code());
    }

    public function testTheStatusOfARequestCanBeForced(): void
    {
        $urssaf = self::gateway();
        $id = self::submitted($urssaf, 'INV-1');

        $urssaf->setPaymentRequestStatus($id, PaymentRequestStatus::ProviderUnpaid);

        static::assertSame('260', self::only($urssaf->search(self::tenant(), PaymentRequestSearch::byPaymentRequestIds($id)))->status()->code());
        static::assertSame('260', self::only($urssaf->search(self::tenant(), PaymentRequestSearch::byPaymentRequestIds($id)))->status()->code());
    }

    public function testForcingTheStatusOfAnUnknownRequestIsAMistakeOfTheTest(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The payment request "urssaf-9" was not submitted to the simulator.');

        self::gateway()->setPaymentRequestStatus(PaymentRequestId::fromString('urssaf-9'), PaymentRequestStatus::Paid);
    }

    public function testASearchFindsTheRequestsOfTheTenantOnly(): void
    {
        $urssaf = self::gateway();
        $first = self::submitted($urssaf, 'INV-1');
        $second = self::submitted($urssaf, 'INV-2');
        $urssaf->submit(TenantId::fromString('tenant-2'), self::request('3', 'INV-3', 'tenant-2'));
        $foreign = $urssaf->paymentRequestIds()[2];

        $byIds = $urssaf->search(self::tenant(), PaymentRequestSearch::byPaymentRequestIds($second, $foreign, PaymentRequestId::fromString('unknown')));
        $byNumbers = $urssaf->search(self::tenant(), PaymentRequestSearch::byInvoiceNumbers(InvoiceNumber::fromString('INV-1'), InvoiceNumber::fromString('INV-3')));

        static::assertSame(['INV-2'], self::invoiceNumbers($byIds));
        static::assertSame(['INV-1'], self::invoiceNumbers($byNumbers));
        static::assertEquals($first, $byNumbers[0]->paymentRequestId());
        static::assertSame([Operation::SubmitPaymentRequests, Operation::SubmitPaymentRequests, Operation::SubmitPaymentRequests, Operation::SearchPaymentRequests, Operation::SearchPaymentRequests], $urssaf->calls());
    }

    public function testAPeriodSearchFindsTheInvoicesDatedWithinItsBounds(): void
    {
        $urssaf = self::gateway();
        self::submitted($urssaf, 'INV-1');   // dated 2022-02-01

        static::assertSame(['INV-1'], self::invoicesDatedWithin($urssaf, '2022-02-01', '2022-02-01'));
        static::assertSame(['INV-1'], self::invoicesDatedWithin($urssaf, '2022-01-01', '2022-02-28'));
        static::assertSame([], self::invoicesDatedWithin($urssaf, '2022-02-02', '2022-02-28'));
        static::assertSame([], self::invoicesDatedWithin($urssaf, '2022-01-01', '2022-01-31'));
    }

    public function testASearchWithMoreThanTenResultsIsRefused(): void
    {
        $urssaf = self::gateway();
        $search = PaymentRequestSearch::byInvoicePeriod(CalendarDate::fromString('2022-01-01'), CalendarDate::fromString('2022-12-31'));
        for ($n = 1; $n <= 10; ++$n) {
            self::submitted($urssaf, 'INV-' . $n);
        }
        static::assertCount(10, $urssaf->search(self::tenant(), $search));
        self::submitted($urssaf, 'INV-11');

        $refusal = self::refusal(static fn(): array => $urssaf->search(self::tenant(), $search));

        static::assertSame(Operation::SearchPaymentRequests, $refusal->operation());
        static::assertSame(['ERR_NBRE_MAX_RESULTAT'], $refusal->errors()->codes());
    }

    /** @var list<string> */
    private array $thrown = [];

    /**
     * @return list<string>
     */
    private static function invoicesDatedWithin(SimulatedUrssafGateway $urssaf, string $from, string $to): array
    {
        return self::invoiceNumbers($urssaf->search(
            self::tenant(),
            PaymentRequestSearch::byInvoicePeriod(CalendarDate::fromString($from), CalendarDate::fromString($to)),
        ));
    }

    private static function gateway(): SimulatedUrssafGateway
    {
        return new SimulatedUrssafGateway(self::clock(), new SequentialIdGenerator(self::ID_PREFIX));
    }

    private static function clock(): FrozenClock
    {
        return new FrozenClock(new DateTimeImmutable(self::NOW));
    }

    private static function tenant(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    private function registered(SimulatedUrssafGateway $urssaf): UrssafCustomerId
    {
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            try {
                return $urssaf->register(self::tenant(), CustomerFixtures::newRegistration());
            } catch (RuntimeException $failure) {
                $this->thrown[] = $failure->getMessage();
            }
        }

        throw new LogicException('Not registered.');
    }

    private static function request(string $id, string $invoiceNumber, string $tenant = 'tenant-1'): PaymentRequest
    {
        return PaymentRequestBuilder::pending('local-' . $id, $tenant, $invoiceNumber, '2022-02-15T10:00:00Z');
    }

    private static function submitted(SimulatedUrssafGateway $urssaf, string $invoiceNumber): PaymentRequestId
    {
        $id = $urssaf->submit(self::tenant(), self::request($invoiceNumber, $invoiceNumber))[0]->paymentRequestId();
        static::assertNotNull($id);

        return $id;
    }

    /**
     * @param list<PaymentRequestSnapshot> $snapshots
     */
    private static function only(array $snapshots): PaymentRequestSnapshot
    {
        static::assertCount(1, $snapshots);

        return $snapshots[0];
    }

    /**
     * @param list<PaymentRequestSnapshot> $snapshots
     *
     * @return list<string>
     */
    private static function invoiceNumbers(array $snapshots): array
    {
        return array_map(static fn(PaymentRequestSnapshot $snapshot): string => $snapshot->invoiceNumber(), $snapshots);
    }

    /**
     * @param Closure(): mixed $call
     *
     * @param-immediately-invoked-callable $call
     */
    private static function refusal(Closure $call): UrssafRequestRejected
    {
        try {
            $call();
        } catch (UrssafRequestRejected $refusal) {
            return $refusal;
        }

        static::fail('The call should be refused.');
    }
}

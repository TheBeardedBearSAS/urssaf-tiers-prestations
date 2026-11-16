<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Contract;

use DateInterval;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PollingWindow;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory\PaymentRequestBuilder;

/**
 * Behaviour every {@see PaymentRequestRepository} must have, independently of the storage. Identity-related behaviour
 * (copies versus managed instances) is deliberately not part of the contract.
 *
 * Contract and behaviour scenarios of one repository: one method per scenario.
 * @phpstan-require-extends TestCase
 * @require-extends TestCase
 * @mago-expect lint:too-many-methods
 */
trait PaymentRequestRepositoryContract
{
    protected PaymentRequestRepository $repository;

    abstract protected function createRepository(): PaymentRequestRepository;

    /**
     * @param list<PaymentRequest> $requests
     *
     * @return list<string>
     */
    protected static function ids(array $requests): array
    {
        return array_map(static fn(PaymentRequest $r): string => $r->id()->value(), $requests);
    }

    protected static function now(): Instant
    {
        return Instant::fromIso8601('2022-04-01T00:00:00Z');
    }

    /**
     * Settled statuses are followed for 30 days; the other limits are wide enough not to interfere.
     */
    protected static function window(): PollingWindow
    {
        return new PollingWindow(new DateInterval('P30D'), new DateInterval('P1000D'), new DateInterval('P5000D'));
    }

    protected function setUp(): void
    {
        $this->repository = $this->createRepository();
    }

    public function testASavedRequestIsReadBack(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));

        $read = $this->repository->get(LocalPaymentRequestId::fromString('pr-1'));

        static::assertSame('INV-1', $read->invoiceNumber()->value());
        static::assertSame(SubmissionState::Pending, $read->state());
    }

    public function testDomainEventsAreNotPersistedNorConsumedFromTheCaller(): void
    {
        $request = PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z');

        $this->repository->save($request);

        static::assertCount(1, $request->releaseEvents());
    }

    public function testASavedChangeIsReadBack(): void
    {
        $request = PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z');
        $this->repository->save($request);

        $request->startSubmission(Instant::fromIso8601('2022-03-02T00:00:00Z'));
        $this->repository->save($request);

        static::assertSame(SubmissionState::Submitting, $this->repository->get($request->id())->state());
    }

    public function testAnUnknownRequestIsNotFound(): void
    {
        try {
            $this->repository->get(LocalPaymentRequestId::fromString('missing'));
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $exception) {
            static::assertSame('payment_request.not_found', $exception->violationCode());
            static::assertSame('missing', $exception->identifier());
        }
    }

    public function testTheSameInvoiceNumberOfAnotherRequestOfTheTenantIsRefused(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));

        try {
            $this->repository->save(PaymentRequestBuilder::pending('pr-2', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('payment_request.duplicate_invoice_number', $exception->violationCode());
            static::assertSame(['invoice_number' => 'INV-1'], $exception->params());
        }

        $this->expectException(RecordNotFound::class);
        $this->repository->get(LocalPaymentRequestId::fromString('pr-2'));
    }

    public function testTheSameInvoiceNumberIsAllowedForAnotherTenantAndForTheSameRequest(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-2', 'tenant-2', 'INV-1', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-3', 'tenant-1', 'INV-3', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));

        static::assertSame('tenant-2', $this->repository->get(LocalPaymentRequestId::fromString('pr-2'))->tenantId()->value());
    }

    public function testItFindsByInvoiceNumber(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-2', 'tenant-2', 'INV-1', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-3', 'tenant-2', 'INV-3', '2022-03-01T00:00:00Z'));
        $tenant = TenantId::fromString('tenant-2');

        static::assertSame('pr-2', $this->repository->findByInvoiceNumber($tenant, InvoiceNumber::fromString('INV-1'))?->id()->value());
        static::assertNull($this->repository->findByInvoiceNumber($tenant, InvoiceNumber::fromString('INV-9')));
        static::assertNull($this->repository->findByInvoiceNumber(TenantId::fromString('tenant-3'), InvoiceNumber::fromString('INV-1')));
    }

    public function testItFindsByUrssafIdsIgnoringUnknownOnesAndOtherTenants(): void
    {
        // Every natural order (insertion, identifier, reference, invoice number, URSSAF id) is the reverse of the creation order.
        $this->repository->save(PaymentRequestBuilder::submitted('pr-a-late', 'tenant-1', 'INV-1', '2022-03-05T00:00:00Z', 'urssaf-1'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-z-early', 'tenant-1', 'INV-2', '2022-03-02T00:00:00Z', 'urssaf-2'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-other', 'tenant-2', 'INV-3', '2022-03-01T00:00:00Z', 'urssaf-1'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-unwanted', 'tenant-1', 'INV-4', '2022-03-01T00:00:00Z', 'urssaf-4'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-pending', 'tenant-1', 'INV-5', '2022-03-01T00:00:00Z'));

        $found = $this->repository->findByUrssafIds(TenantId::fromString('tenant-1'), [
            PaymentRequestId::fromString('urssaf-1'),
            PaymentRequestId::fromString('urssaf-2'),
            PaymentRequestId::fromString('unknown'),
        ]);

        static::assertSame(['pr-z-early', 'pr-a-late'], self::ids($found));
        static::assertSame([], $this->repository->findByUrssafIds(TenantId::fromString('tenant-1'), []));
    }

    public function testReplacingARequestLeavesTheOthersUntouched(): void
    {
        $this->repository->save(PaymentRequestBuilder::submitted('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z', 'urssaf-1'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-2', 'tenant-1', 'INV-2', '2022-03-02T00:00:00Z', 'urssaf-2'));

        $this->repository->save(PaymentRequestBuilder::submitted('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z', 'urssaf-3'));

        $tenant = TenantId::fromString('tenant-1');
        static::assertSame(['pr-2'], self::ids($this->repository->findByUrssafIds($tenant, [PaymentRequestId::fromString('urssaf-2')])));
        static::assertSame(['pr-1'], self::ids($this->repository->findByUrssafIds($tenant, [PaymentRequestId::fromString('urssaf-3')])));
        static::assertSame([], $this->repository->findByUrssafIds($tenant, [PaymentRequestId::fromString('urssaf-1')]));
        static::assertSame(['pr-1', 'pr-2'], self::ids($this->repository->findPollable($tenant, self::now(), self::window(), 10)));
    }

    public function testItFindsByStateOrderedByCreationAndLimited(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-new', 'tenant-1', 'INV-1', '2022-03-05T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-old', 'tenant-1', 'INV-2', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-other', 'tenant-2', 'INV-3', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::submitting('pr-busy', 'tenant-1', 'INV-4', '2022-03-01T00:00:00Z', '2022-03-02T00:00:00Z'));
        $tenant = TenantId::fromString('tenant-1');

        static::assertSame(['pr-old', 'pr-new'], self::ids($this->repository->findByState($tenant, SubmissionState::Pending, 10)));
        static::assertSame(['pr-old'], self::ids($this->repository->findByState($tenant, SubmissionState::Pending, 1)));
        static::assertSame(['pr-busy'], self::ids($this->repository->findByState($tenant, SubmissionState::Submitting, 10)));
        static::assertSame([], $this->repository->findByState($tenant, SubmissionState::Error, 10));
    }

    public function testRequestsCreatedAtTheSameInstantAreOrderedByIdentifier(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-a', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-b', 'tenant-1', 'INV-2', '2022-03-01T00:00:00Z'));

        static::assertSame(
            ['pr-a', 'pr-b'],
            self::ids($this->repository->findByState(TenantId::fromString('tenant-1'), SubmissionState::Pending, 10)),
        );
    }

    public function testPollableRequestsAreTheLeastRecentlyPolledFirstNeverPolledFirst(): void
    {
        $this->repository->save(PaymentRequestBuilder::polledAt(PaymentRequestBuilder::submitted('pr-recent', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z', 'u-1'), '2022-03-30T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::polledAt(PaymentRequestBuilder::submitted('pr-old', 'tenant-1', 'INV-2', '2022-03-02T00:00:00Z', 'u-2'), '2022-03-10T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-never', 'tenant-1', 'INV-3', '2022-03-03T00:00:00Z', 'u-3'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-never-first', 'tenant-1', 'INV-4', '2022-03-01T12:00:00Z', 'u-4'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-other-tenant', 'tenant-2', 'INV-5', '2022-03-01T00:00:00Z', 'u-5'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-pending', 'tenant-1', 'INV-6', '2022-03-01T00:00:00Z'));
        $tenant = TenantId::fromString('tenant-1');

        $pollable = $this->repository->findPollable($tenant, self::now(), self::window(), 10);

        static::assertSame(['pr-never-first', 'pr-never', 'pr-old', 'pr-recent'], self::ids($pollable));
        static::assertSame(
            ['pr-never-first', 'pr-never'],
            self::ids($this->repository->findPollable($tenant, self::now(), self::window(), 2)),
        );
    }

    public function testPollableRequestsPolledAtTheSameInstantAreOrderedByCreation(): void
    {
        $this->repository->save(PaymentRequestBuilder::polledAt(PaymentRequestBuilder::submitted('pr-late', 'tenant-1', 'INV-1', '2022-03-05T00:00:00Z', 'u-1'), '2022-03-30T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::polledAt(PaymentRequestBuilder::submitted('pr-early', 'tenant-1', 'INV-2', '2022-03-01T00:00:00Z', 'u-2'), '2022-03-30T00:00:00Z'));

        static::assertSame(
            ['pr-early', 'pr-late'],
            self::ids($this->repository->findPollable(TenantId::fromString('tenant-1'), self::now(), self::window(), 10)),
        );
    }

    public function testTenantsWithPollableAreDistinctAndFollowTheCreationOrder(): void
    {
        $this->repository->save(PaymentRequestBuilder::submitted('pr-1', 'tenant-b', 'INV-1', '2022-03-05T00:00:00Z', 'u-1'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-2', 'tenant-a', 'INV-2', '2022-03-02T00:00:00Z', 'u-2'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-3', 'tenant-b', 'INV-3', '2022-03-03T00:00:00Z', 'u-3'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-4', 'tenant-c', 'INV-4', '2022-03-01T00:00:00Z'));

        $tenants = array_map(
            static fn(TenantId $t): string => $t->value(),
            $this->repository->tenantsWithPollable(self::now(), self::window()),
        );

        static::assertSame(['tenant-a', 'tenant-b'], $tenants);
    }

    public function testNoTenantWhenNothingIsPollable(): void
    {
        static::assertSame([], $this->repository->tenantsWithPollable(self::now(), self::window()));
    }

    /**
     * The domain rule {@see PaymentRequest::isPollable()} is the oracle: every status, observed before, exactly at and
     * after the settled maximum age, must be selected if and only if the aggregate says it is pollable.
     */
    public function testPollingSelectionMatchesTheDomainRuleForEveryStatusAndAge(): void
    {
        $statuses = array_map(static fn(PaymentRequestStatus $s): string => (string) $s->value, PaymentRequestStatus::cases());
        $statuses[] = 'ERR_CMPT_BLOQUE';
        $statuses[] = '';
        $observedAt = ['2022-03-02T00:00:00Z', '2022-03-02T00:00:01Z', '2022-03-01T23:59:59Z', '2022-03-31T00:00:00Z'];
        $expectedPollable = [];
        $count = 0;
        foreach ($statuses as $status) {
            foreach ($observedAt as $at) {
                ++$count;
                $request = PaymentRequestBuilder::submitting($id = 'pr-' . $count, 'tenant-1', 'INV-' . $count, '2022-02-01T00:00:00Z', '2022-02-01T00:00:00Z');
                $request->markAccepted(PaymentRequestId::fromString('u-' . $count), $status, Instant::fromIso8601($at));
                $request->releaseEvents();
                $this->repository->save($request);
                if ($request->isPollable(self::now(), self::window())) {
                    $expectedPollable[] = $id;
                }
            }
        }

        $found = self::ids($this->repository->findPollable(TenantId::fromString('tenant-1'), self::now(), self::window(), 1000));
        sort($found);
        sort($expectedPollable);

        static::assertSame($expectedPollable, $found);
        static::assertNotSame([], $found);
        static::assertLessThan($count, \count($found));
    }

    public function testPollingStopsForFinalStatusesAndAfterTheSettledMaximumAge(): void
    {
        $tenant = TenantId::fromString('tenant-1');
        $this->repository->save(self::submittedWithStatus('pr-paid-recent', 'INV-1', '70', '2022-03-10T00:00:00Z'));
        $this->repository->save(self::submittedWithStatus('pr-paid-edge', 'INV-2', '70', '2022-03-02T00:00:00Z'));
        $this->repository->save(self::submittedWithStatus('pr-paid-old', 'INV-3', '70', '2022-02-01T00:00:00Z'));
        $this->repository->save(self::submittedWithStatus('pr-refused', 'INV-4', '40', '2022-03-30T00:00:00Z'));
        $this->repository->save(self::submittedWithStatus('pr-unknown', 'INV-5', 'ERR_X', '2022-01-01T00:00:00Z'));
        $this->repository->save(self::submittedWithStatus('pr-validated-old', 'INV-6', '30', '2021-01-01T00:00:00Z'));

        static::assertSame(
            ['pr-paid-recent', 'pr-unknown', 'pr-validated-old'],
            self::ids($this->repository->findPollable($tenant, self::now(), self::window(), 10)),
        );
    }

    public function testADeletedRequestIsGoneAndItsInvoiceNumberIsFree(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-2', 'tenant-1', 'INV-2', '2022-03-01T00:00:00Z'));

        $this->repository->delete(LocalPaymentRequestId::fromString('pr-1'));
        $this->repository->delete(LocalPaymentRequestId::fromString('missing'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-3', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));

        static::assertSame(['pr-2', 'pr-3'], self::ids($this->repository->findByState(TenantId::fromString('tenant-1'), SubmissionState::Pending, 10)));
        $this->expectException(RecordNotFound::class);
        $this->repository->get(LocalPaymentRequestId::fromString('pr-1'));
    }

    public function testPollingStopsForUnknownStatusesAndForOldSubmissions(): void
    {
        $tenant = TenantId::fromString('tenant-1');
        $this->repository->save(self::submittedWithStatus('pr-unknown-recent', 'INV-1', 'ERR_X', '2022-03-25T00:00:00Z'));
        $this->repository->save(self::submittedWithStatus('pr-unknown-old', 'INV-2', 'ERR_X', '2022-03-20T00:00:00Z'));
        $this->repository->save(self::submittedWithStatus('pr-validated-recent', 'INV-3', '30', '2022-02-15T00:00:00Z'));
        $this->repository->save(self::submittedWithStatus('pr-validated-old', 'INV-4', '30', '2022-01-15T00:00:00Z'));
        $window = new PollingWindow(new DateInterval('P30D'), new DateInterval('P10D'), new DateInterval('P60D'));

        static::assertSame(
            ['pr-unknown-recent', 'pr-validated-recent'],
            self::ids($this->repository->findPollable($tenant, self::now(), $window, 10)),
        );
        static::assertEquals([$tenant], $this->repository->tenantsWithPollable(self::now(), $window));
    }

    public function testStaleSubmissionsAreThoseStartedAtOrBeforeTheThresholdOldestSubmissionFirst(): void
    {
        $this->repository->save(PaymentRequestBuilder::submitting('pr-b', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z', '2022-03-02T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::submitting('pr-a', 'tenant-2', 'INV-2', '2022-03-05T00:00:00Z', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::submitting('pr-edge', 'tenant-1', 'INV-3', '2022-03-04T00:00:00Z', '2022-03-03T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::submitting('pr-fresh', 'tenant-1', 'INV-4', '2022-03-01T00:00:00Z', '2022-03-03T00:00:01Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-pending', 'tenant-1', 'INV-5', '2022-03-01T00:00:00Z'));
        $threshold = Instant::fromIso8601('2022-03-03T00:00:00Z');

        $stale = $this->repository->findStaleSubmissions($threshold, 10);

        static::assertSame(['pr-a', 'pr-b', 'pr-edge'], self::ids($stale));
        static::assertSame(['pr-a'], self::ids($this->repository->findStaleSubmissions($threshold, 1)));
    }

    public function testStaleSubmissionsStartedAtTheSameInstantAreOrderedByCreation(): void
    {
        $this->repository->save(PaymentRequestBuilder::submitting('pr-late', 'tenant-1', 'INV-1', '2022-03-02T00:00:00Z', '2022-03-02T12:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::submitting('pr-early', 'tenant-1', 'INV-2', '2022-03-01T00:00:00Z', '2022-03-02T12:00:00Z'));

        static::assertSame(
            ['pr-early', 'pr-late'],
            self::ids($this->repository->findStaleSubmissions(Instant::fromIso8601('2022-03-03T00:00:00Z'), 10)),
        );
    }

    public function testOnlyOneWorkerWinsTheClaim(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
        $id = LocalPaymentRequestId::fromString('pr-1');
        $now = Instant::fromIso8601('2022-03-02T10:00:00Z');

        static::assertTrue($this->repository->claimForSubmission($id, $now));
        static::assertFalse($this->repository->claimForSubmission($id, $now));

        $claimed = $this->repository->get($id);
        static::assertSame(SubmissionState::Submitting, $claimed->state());
        static::assertSame($now->toIso8601(), $claimed->timeline()->submissionStartedAt()?->toIso8601());
        static::assertSame($now->toIso8601(), $claimed->timeline()->updatedAt()->toIso8601());
        static::assertSame([], $claimed->releaseEvents());
    }

    public function testAClaimDoesNotTouchTheCallerInstance(): void
    {
        $request = PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z');
        $this->repository->save($request);

        $this->repository->claimForSubmission($request->id(), Instant::fromIso8601('2022-03-02T10:00:00Z'));

        static::assertSame(SubmissionState::Pending, $request->state());
        static::assertCount(1, $request->releaseEvents());
    }

    public function testAnUnknownOrNotPendingRequestCannotBeClaimed(): void
    {
        $this->repository->save(PaymentRequestBuilder::submitting('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z', '2022-03-02T00:00:00Z'));
        $now = Instant::fromIso8601('2022-03-03T00:00:00Z');

        static::assertFalse($this->repository->claimForSubmission(LocalPaymentRequestId::fromString('pr-1'), $now));
        static::assertFalse($this->repository->claimForSubmission(LocalPaymentRequestId::fromString('missing'), $now));
        static::assertSame(
            '2022-03-02T00:00:00Z',
            $this->repository->get(LocalPaymentRequestId::fromString('pr-1'))->timeline()->submissionStartedAt()?->toIso8601(),
        );
    }

    /**
     * A submitted request whose current status was observed at the given instant.
     */
    private static function submittedWithStatus(string $id, string $invoiceNumber, string $status, string $observedAt): PaymentRequest
    {
        $request = PaymentRequestBuilder::submitting($id, 'tenant-1', $invoiceNumber, '2022-01-01T00:00:00Z', '2022-01-01T00:00:00Z');
        $request->markAccepted(PaymentRequestId::fromString('u-' . $id), $status, Instant::fromIso8601($observedAt));
        $request->releaseEvents();

        return $request;
    }
}

<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryPaymentRequestRepository;

final class InMemoryPaymentRequestRepositoryClaimTest extends TestCase
{
    private InMemoryPaymentRequestRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPaymentRequestRepository();
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
        $stored = $this->repository->get(LocalPaymentRequestId::fromString('pr-1'));
        static::assertSame('2022-03-02T00:00:00Z', $stored->timeline()->submissionStartedAt()?->toIso8601());
    }
}

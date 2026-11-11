<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryPaymentRequestRepository;

final class InMemoryPaymentRequestRepositoryStorageTest extends TestCase
{
    private InMemoryPaymentRequestRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPaymentRequestRepository();
    }

    public function testASavedRequestIsReadBackAsACopy(): void
    {
        $request = PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z');
        $this->repository->save($request);

        $read = $this->repository->get(LocalPaymentRequestId::fromString('pr-1'));

        static::assertNotSame($request, $read);
        static::assertSame('INV-1', $read->invoiceNumber()->value());
        static::assertNotSame($read, $this->repository->get(LocalPaymentRequestId::fromString('pr-1')));
    }

    public function testDomainEventsAreNotPersistedNorConsumedFromTheCaller(): void
    {
        $request = PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z');
        $this->repository->save($request);

        static::assertSame([], $this->repository->get($request->id())->releaseEvents());
        static::assertCount(1, $request->releaseEvents());
    }

    public function testAChangeIsOnlyVisibleOnceSaved(): void
    {
        $request = PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z');
        $this->repository->save($request);
        $request->startSubmission(Instant::fromIso8601('2022-03-02T00:00:00Z'));

        static::assertSame(SubmissionState::Pending, $this->repository->get($request->id())->state());

        $this->repository->save($request);

        static::assertSame(SubmissionState::Submitting, $this->repository->get($request->id())->state());
    }

    public function testADeletedRequestFreesItsInvoiceNumber(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-2', 'tenant-1', 'INV-2', '2022-03-01T00:00:00Z'));

        $this->repository->delete(LocalPaymentRequestId::fromString('pr-1'));
        $this->repository->delete(LocalPaymentRequestId::fromString('missing'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-3', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));

        static::assertSame('pr-3', $this->repository->findByInvoiceNumber(TenantId::fromString('tenant-1'), InvoiceNumber::fromString('INV-1'))?->id()->value());
        static::assertSame('INV-2', $this->repository->get(LocalPaymentRequestId::fromString('pr-2'))->invoiceNumber()->value());
        $this->expectException(RecordNotFound::class);
        $this->repository->get(LocalPaymentRequestId::fromString('pr-1'));
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
}

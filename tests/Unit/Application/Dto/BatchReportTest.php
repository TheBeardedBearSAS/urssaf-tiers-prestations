<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\RequeueReport;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;

final class BatchReportTest extends TestCase
{
    public function testReport(): void
    {
        $first = new BatchItem('a', ItemOutcome::Accepted, ['10']);
        $second = new BatchItem('b', ItemOutcome::Rejected);
        $third = new BatchItem('c', ItemOutcome::Accepted);

        $report = BatchReport::of(...['first' => $first, 'second' => $second])->merge(BatchReport::of($third));

        static::assertSame([$first, $second, $third], $report->items());
        static::assertSame(3, $report->total());
        static::assertSame(2, $report->count(ItemOutcome::Accepted));
        static::assertSame(1, $report->count(ItemOutcome::Rejected));
        static::assertSame(0, $report->count(ItemOutcome::Failed));
        static::assertSame([], $second->codes);
        static::assertSame(['10'], $first->codes);
        static::assertSame('a', $first->subject);
        static::assertSame(0, BatchReport::of()->total());
    }

    public function testRequeueReport(): void
    {
        $report = new RequeueReport(2, 3);

        static::assertSame(2, $report->registrations);
        static::assertSame(3, $report->paymentRequests);
    }

    public function testCustomerSelector(): void
    {
        $id = RegistrationId::fromString('reg-1');
        $reference = ExternalReference::fromString('customer-1');

        static::assertSame($id, CustomerSelector::byRegistrationId($id)->reference);
        static::assertSame($reference, CustomerSelector::byExternalReference($reference)->reference);
    }
}

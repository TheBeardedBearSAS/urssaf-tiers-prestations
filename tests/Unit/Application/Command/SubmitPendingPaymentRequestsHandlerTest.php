<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPendingPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPendingPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

final class SubmitPendingPaymentRequestsHandlerTest extends TestCase
{
    public function testSubmitsTheOldestPendingRequests(): void
    {
        $harness = SubmissionScenario::harness(3, new WorkflowPolicy());
        $harness->paymentRequest(4, SubmissionState::Pending, Harness::OTHER_TENANT);
        SubmissionScenario::acceptAll($harness);
        $handler = new SubmitPendingPaymentRequestsHandler($harness->paymentRequests, SubmissionScenario::handler($harness), $harness->policy);

        $report = $handler(new SubmitPendingPaymentRequests(Harness::tenant(), 2));

        static::assertSame(['pr-1', 'pr-2'], array_map(static fn(BatchItem $item): string => $item->subject, $report->items()));
        static::assertEquals([new BatchItem('pr-3', ItemOutcome::Accepted, ['10'])], $handler(new SubmitPendingPaymentRequests(Harness::tenant(), null))->items());
    }
}

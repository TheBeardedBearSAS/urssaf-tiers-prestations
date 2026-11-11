<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command\CommandReports;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

final class CommandReportsTest extends TestCase
{
    public function testAFailureIsDescribedByItsViolationCodeWhenItHasOne(): void
    {
        $item = CommandReports::failureItem('reg-1', RecordNotFound::withIdentifier('customer_registration', 'reg-1'));

        static::assertSame('reg-1', $item->subject);
        static::assertSame(ItemOutcome::Failed, $item->outcome);
        static::assertSame(['customer_registration.not_found'], $item->codes);
    }

    public function testATemporaryFailureIsDescribedAsUnavailability(): void
    {
        $item = CommandReports::failureItem('tenant-1', RetryableUrssafFailure::causedBy(UrssafUnavailable::dueToTransportError(Operation::RegisterCustomer, new LogicException('refused'))));

        static::assertSame(['urssaf.unavailable'], $item->codes);
    }

    public function testAnyOtherFailureIsDescribedByItsType(): void
    {
        $item = CommandReports::failureItem('tenant-1', UnexpectedUrssafResponse::invalidPayload('nope'));

        static::assertSame([UnexpectedUrssafResponse::class], $item->codes);
    }

    #[DataProvider('outcomes')]
    public function testOnlyTheOutcomesThatNeedAttentionFailTheCommand(ItemOutcome $outcome, int $expectedStatus): void
    {
        $output = new BufferedOutput();

        $status = CommandReports::renderReport($output, BatchReport::of(new BatchItem('record-1', $outcome)));

        static::assertSame($expectedStatus, $status);
        static::assertStringContainsString($outcome->value, $output->fetch());
    }

    /**
     * @return iterable<string, array{ItemOutcome, int}>
     */
    public static function outcomes(): iterable
    {
        $failing = [ItemOutcome::Failed, ItemOutcome::InvalidCredentials, ItemOutcome::Unconfirmed, ItemOutcome::ManualReview];
        foreach (ItemOutcome::cases() as $outcome) {
            yield $outcome->value => [$outcome, \in_array($outcome, $failing, true) ? 1 : 0];
        }
    }

    public function testAnOperationAlreadyRunningIsNotAFailure(): void
    {
        $output = new BufferedOutput();

        static::assertSame(0, CommandReports::renderBusy($output, 'urssaf_tps.op.tenant-1'));
        static::assertSame("Already running (lock \"urssaf_tps.op.tenant-1\" is held): skipped.\n", $output->fetch());
    }
}

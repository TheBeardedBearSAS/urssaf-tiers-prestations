<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Policy;

use DateInterval;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;

/**
 * One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class WorkflowPolicyTest extends TestCase
{
    public function testDefaults(): void
    {
        $policy = new WorkflowPolicy();

        static::assertSame('Europe/Paris', $policy->businessTimeZone()->getName());
        static::assertSame('90 0 0 0', $policy->settledMaxAge()->format('%d %h %i %s'));
        static::assertSame('0 0 15 0', $policy->staleSubmissionAfter()->format('%d %h %i %s'));
        static::assertSame('menage.fr', $policy->billingThirdPartyId()->value());
        static::assertSame(10, $policy->submissionBatchSize());
        static::assertSame(100, $policy->pageSize(null));
        static::assertSame(1, $policy->pageSize(1));
        static::assertSame(1000, $policy->pageSize(1000));
        static::assertSame(1000, $policy->pageSize(1001));
        static::assertSame(5, $policy->maxSubmissionAttempts());
        static::assertSame($policy->settledMaxAge(), $policy->pollingWindow()->settledMaxAge());
        static::assertSame('30 0 0 0', $policy->pollingWindow()->unknownStatusMaxAge()->format('%d %h %i %s'));
        static::assertSame('1 0', $policy->pollingWindow()->maxPollingAge()->format('%y %d'));
    }

    public function testPollingWindow(): void
    {
        $unknown = new DateInterval('P2D');
        $polling = new DateInterval('P6M');
        $policy = new WorkflowPolicy(new DateTimeZone('UTC'), new DateInterval('P30D'), new DateInterval('PT1H'), 'billing-1', 1, 5, 5, 1, $unknown, $polling);

        static::assertSame($unknown, $policy->pollingWindow()->unknownStatusMaxAge());
        static::assertSame($polling, $policy->pollingWindow()->maxPollingAge());
    }

    public function testCustomValues(): void
    {
        $timeZone = new DateTimeZone('UTC');
        $settled = new DateInterval('P30D');
        $stale = new DateInterval('PT1H');
        $policy = new WorkflowPolicy($timeZone, $settled, $stale, 'billing-1', 1, 5, 5, 1);

        static::assertSame($timeZone, $policy->businessTimeZone());
        static::assertSame($settled, $policy->settledMaxAge());
        static::assertSame($stale, $policy->staleSubmissionAfter());
        static::assertSame('billing-1', $policy->billingThirdPartyId()->value());
        static::assertSame(1, $policy->submissionBatchSize());
        static::assertSame(5, $policy->pageSize(null));
        static::assertSame(5, $policy->pageSize(6));
        static::assertSame(4, $policy->pageSize(4));
        static::assertSame(1, $policy->maxSubmissionAttempts());
    }

    public function testSmallestValues(): void
    {
        static::assertSame(1, self::policy('menage.fr', 1, 1, 1)->pageSize(null));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidBatchSizes(): iterable
    {
        yield 'zero' => [0];
        yield 'above the URSSAF maximum' => [11];
    }

    #[DataProvider('invalidBatchSizes')]
    public function testRefusesAnInvalidBatchSize(int $batchSize): void
    {
        try {
            self::policy('menage.fr', $batchSize, 100, 1000);
            static::fail('Expected an invariant violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('workflow_policy.invalid_batch_size', $exception->violationCode());
            static::assertSame(['batch_size' => $batchSize], $exception->params());
            static::assertSame('The submission batch size must be between 1 and 10.', $exception->getMessage());
        }
    }

    public function testRefusesANonPositivePageSize(): void
    {
        try {
            self::policy('menage.fr', 10, 0, 0);
            static::fail('Expected an invariant violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('workflow_policy.invalid_page_size', $exception->violationCode());
            static::assertSame(['page_size' => 0], $exception->params());
        }
    }

    public function testRefusesADefaultPageSizeAboveTheMaximum(): void
    {
        try {
            self::policy('menage.fr', 10, 11, 10);
            static::fail('Expected an invariant violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('workflow_policy.default_page_size_above_max', $exception->violationCode());
            static::assertSame(['default_page_size' => 11, 'max_page_size' => 10], $exception->params());
        }
    }

    public function testRefusesAnInvalidBillingThirdPartyId(): void
    {
        $this->expectException(InvalidValue::class);

        self::policy('', 10, 100, 1000);
    }

    private static function policy(string $billingThirdPartyId, int $batchSize, int $defaultPageSize, int $maxPageSize): WorkflowPolicy
    {
        return new WorkflowPolicy(
            new DateTimeZone('Europe/Paris'),
            new DateInterval('P90D'),
            new DateInterval('PT15M'),
            $billingThirdPartyId,
            $batchSize,
            $defaultPageSize,
            $maxPageSize,
        );
    }

    public function testRefusesANonPositiveLimit(): void
    {
        try {
            (new WorkflowPolicy())->pageSize(0);
            static::fail('Expected an invariant violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('workflow_policy.invalid_limit', $exception->violationCode());
            static::assertSame(['limit' => 0], $exception->params());
        }
    }

    public function testRefusesANonPositiveMaximumNumberOfSubmissionAttempts(): void
    {
        try {
            new WorkflowPolicy(new DateTimeZone('UTC'), new DateInterval('P1D'), new DateInterval('PT1M'), 'menage.fr', 10, 100, 1000, 0);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('workflow_policy.invalid_max_submission_attempts', $exception->violationCode());
            static::assertSame(['max_submission_attempts' => 0], $exception->params());
            static::assertSame('The maximum number of submission attempts must be a positive integer.', $exception->getMessage());
        }
    }
}

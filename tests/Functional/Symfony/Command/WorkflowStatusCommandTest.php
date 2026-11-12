<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory\PaymentRequestBuilder;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory\RegistrationBuilder;

final class WorkflowStatusCommandTest extends TestCase
{
    private const AT = '2022-03-01T10:00:00Z';

    public function testTheCountsArePrintedAsTablesWithEveryState(): void
    {
        $harness = self::withRecords();
        $command = $harness->command('urssaf:status');

        static::assertSame(0, $command->execute([]));

        $display = $command->getDisplay();
        static::assertStringContainsString('Customer registrations', $display);
        static::assertMatchesRegularExpression('/^\| Tenant +\| pending +\| submitting +\| awaiting_pairing +\| registered +\| rejected +\| blocked +\| mandate_expired +\| error +\| invalid_credentials \|$/m', $display);
        static::assertMatchesRegularExpression('/^\| tenant-1 +\| 2 +\| 0 +\| 0 +\| 0 +\| 0 +\| 0 +\| 0 +\| 0 +\| 0 +\|$/m', $display);
        static::assertStringContainsString('Payment requests', $display);
        static::assertMatchesRegularExpression('/^\| Tenant +\| pending +\| submitting +\| submitted +\| rejected +\| error +\| invalid_credentials \|$/m', $display);
        static::assertMatchesRegularExpression('/^\| tenant-2 +\| 0 +\| 0 +\| 1 +\| 0 +\| 0 +\| 0 +\|$/m', $display);
        static::assertLessThan(strpos($display, 'Payment requests'), strpos($display, 'Customer registrations'));
    }

    public function testTheCountsArePrintedAsJsonForOneTenant(): void
    {
        $command = self::withRecords()->command('urssaf:status');

        static::assertSame(0, $command->execute(['--tenant' => 'tenant-2', '--format' => 'json']));

        // @mago-expect analysis:mixed-assignment JSON is untyped: the assertions below narrow it.
        $decoded = json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertCount(1, $decoded);
        static::assertIsArray($decoded[0]);
        static::assertSame('tenant-2', $decoded[0]['tenant_id']);
        static::assertSame(['pending' => 0, 'submitting' => 0, 'submitted' => 1, 'rejected' => 0, 'error' => 0, 'invalid_credentials' => 0], $decoded[0]['payment_requests']);
        static::assertSame(0, $decoded[0]['needing_attention']);
        static::assertStringContainsString("\n    ", $command->getDisplay());
    }

    public function testAnUnknownFormatIsRefused(): void
    {
        $command = Harness::boot()->command('urssaf:status');

        static::assertSame(1, $command->execute(['--format' => 'xml']));
        static::assertStringContainsString('The --format option must be "table" or "json".', $command->getDisplay());
    }

    public function testEachDriverHasItsStatistics(): void
    {
        static::assertInstanceOf(InMemoryWorkflowStatistics::class, Harness::boot()->privateService(WorkflowStatistics::class, WorkflowStatistics::class));
        $doctrine = Harness::boot(['persistence' => ['driver' => 'doctrine']], true);
        static::assertInstanceOf(DoctrineWorkflowStatistics::class, $doctrine->privateService(WorkflowStatistics::class, WorkflowStatistics::class));
        $definition = (new TestKernel(['persistence' => ['driver' => 'doctrine', 'entity_manager' => 'urssaf']], true))->definitions()->getDefinition(WorkflowStatistics::class);
        static::assertEquals([new Reference('doctrine.orm.urssaf_entity_manager')], $definition->getArguments());
    }

    public function testACustomDriverProvidesItsStatisticsOrGoesWithout(): void
    {
        $custom = ['driver' => 'custom', 'customer_registration_repository' => 'test.customers', 'payment_request_repository' => 'test.payment_requests'];

        $without = Harness::boot(['persistence' => $custom]);
        $with = Harness::boot(['persistence' => [...$custom, 'workflow_statistics' => 'test.statistics']]);

        static::assertFalse($without->has(GetWorkflowOverviewHandler::class));
        static::assertFalse($without->hasPrivate(WorkflowStatistics::class));
        static::assertTrue($with->has(GetWorkflowOverviewHandler::class));
        static::assertSame($with->privateService('test.statistics', InMemoryWorkflowStatistics::class), $with->privateService(WorkflowStatistics::class, WorkflowStatistics::class));
        static::assertSame(0, $with->command('urssaf:status')->execute([]));
    }

    private static function withRecords(): Harness
    {
        $harness = Harness::boot();
        $registrations = $harness->privateService(CustomerRegistrationRepository::class, CustomerRegistrationRepository::class);
        $registrations->save(RegistrationBuilder::pending('r1', 'tenant-1', 'ref-1', self::AT));
        $registrations->save(RegistrationBuilder::pending('r2', 'tenant-1', 'ref-2', self::AT));
        $harness->privateService(PaymentRequestRepository::class, PaymentRequestRepository::class)
            ->save(PaymentRequestBuilder::submitted('p1', 'tenant-2', 'INV-1', self::AT, 'urssaf-1'));

        return $harness;
    }
}

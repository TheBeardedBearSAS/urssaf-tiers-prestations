<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\PaymentRequestView;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Scenario;

final class SyncPaymentStatusesCommandTest extends TestCase
{
    private const SUBMIT = 'urssaf:payment-requests:submit-pending';
    private const SYNC = 'urssaf:payment-requests:sync-statuses';

    public function testSyncDryRunListsThePaymentRequestsToPoll(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingPaymentRequest($harness);
        $harness->command(self::SUBMIT)->execute([]);

        $tester = $harness->command(self::SYNC);

        static::assertSame(0, $tester->execute(['--dry-run' => true]));
        static::assertSame([['Tenant', 'Record'], ['tenant-1', $id->value()]], Output::rows($tester->getDisplay()));
        static::assertSame(0, $tester->execute(['--dry-run' => true, '--tenant' => 'tenant-1', '--limit' => '2']));
        static::assertStringContainsString('Dry run: 1 record(s)', $tester->getDisplay());
        static::assertSame(0, $tester->execute(['--dry-run' => true, '--tenant' => 'tenant-2']));
        static::assertStringContainsString('Dry run: 0 record(s)', $tester->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_SEARCH));
    }

    public function testSyncPollsTheStatuses(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingPaymentRequest($harness);
        $harness->command(self::SUBMIT)->execute([]);

        $tester = $harness->command(self::SYNC);

        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-2']));
        static::assertSame('10', self::urssafStatus($harness, $id));
        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-1', '--limit' => '5']));
        static::assertSame('70', self::urssafStatus($harness, $id));
        static::assertSame(0, $tester->execute([]));
        static::assertStringContainsString('unchanged', $tester->getDisplay());
    }

    public function testSyncFailsWhenUrssafIsUnavailable(): void
    {
        $harness = Harness::boot();
        Scenario::pendingPaymentRequest($harness);
        $harness->command(self::SUBMIT)->execute([]);
        $harness->urssaf->respond(HttpScript::ROUTE_SEARCH, 503, '{}');

        $tester = $harness->command(self::SYNC);

        static::assertSame(1, $tester->execute(['--tenant' => 'tenant-1']));
        static::assertStringContainsString('is temporarily unavailable (HTTP 503)', $tester->getDisplay());
    }

    private static function urssafStatus(Harness $harness, LocalPaymentRequestId $id): ?string
    {
        return self::view($harness, $id)->urssaf->status;
    }

    private static function view(Harness $harness, LocalPaymentRequestId $id): PaymentRequestView
    {
        return $harness->get(FindPaymentRequestsHandler::class)(FindPaymentRequests::byIds(TenantId::fromString('tenant-1'), $id))[0];
    }
}

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

final class ReconcileStaleSubmissionsCommandTest extends TestCase
{
    private const SUBMIT = 'urssaf:payment-requests:submit-pending';
    private const RECONCILE = 'urssaf:payment-requests:reconcile-stale';

    public function testReconcilesTheSubmissionsLeftWithoutAnswer(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingPaymentRequest($harness);
        // Method 050 answers with the wrong number of results: the request stays "submitting".
        $harness->urssaf->respond(HttpScript::ROUTE_SUBMIT, 200, '[]');
        $submit = $harness->command(self::SUBMIT);
        static::assertSame(1, $submit->execute([]));
        static::assertStringContainsString('unconfirmed', $submit->getDisplay());
        static::assertSame('submitting', self::state($harness, $id));

        $reconcile = $harness->command(self::RECONCILE);
        static::assertSame(0, $reconcile->execute(['--dry-run' => true]));
        static::assertStringContainsString('Dry run: 0 record(s)', $reconcile->getDisplay());

        $harness->clock->sleep(20 * 60);
        static::assertSame(0, $reconcile->execute(['--dry-run' => true]));
        static::assertSame([['Tenant', 'Record'], ['tenant-1', $id->value()]], Output::rows($reconcile->getDisplay()));
        static::assertStringContainsString('Dry run: 1 record(s)', $reconcile->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_SEARCH));

        static::assertSame(0, $reconcile->execute(['--limit' => '4']));
        static::assertStringContainsString('reconciled', $reconcile->getDisplay());
        static::assertSame('submitted', self::state($harness, $id));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_SEARCH));
    }

    private static function state(Harness $harness, LocalPaymentRequestId $id): string
    {
        return self::view($harness, $id)->state;
    }

    private static function view(Harness $harness, LocalPaymentRequestId $id): PaymentRequestView
    {
        return $harness->get(FindPaymentRequestsHandler::class)(FindPaymentRequests::byIds(TenantId::fromString('tenant-1'), $id))[0];
    }
}

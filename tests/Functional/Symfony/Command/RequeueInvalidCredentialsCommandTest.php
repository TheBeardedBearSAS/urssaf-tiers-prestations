<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Scenario;

final class RequeueInvalidCredentialsCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:requeue-invalid-credentials';

    public function testTheTenantIsRequired(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));
        static::assertStringContainsString('The --tenant option is required.', $tester->getDisplay());
    }

    public function testRequeuesTheRecordsRefusedForInvalidCredentials(): void
    {
        $harness = Harness::boot();
        $firstRequest = Scenario::pendingPaymentRequest($harness);
        Scenario::registeredCustomer($harness, 'tenant-1', 'customer-2');
        $secondRequest = Scenario::createPaymentRequest($harness, 'tenant-1', 'customer-2');
        $firstRegistration = Scenario::pendingRegistration($harness, 'tenant-1', 'customer-3');
        $secondRegistration = Scenario::pendingRegistration($harness, 'tenant-1', 'customer-4');
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{}');
        static::assertSame(1, $harness->command('urssaf:customers:submit-pending')->execute([]));
        static::assertSame(1, $harness->command('urssaf:payment-requests:submit-pending')->execute([]));

        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-1', '--dry-run' => true]));
        $rows = Output::rows($tester->getDisplay());
        static::assertSame(['Tenant', 'Record'], $rows[0]);
        static::assertEqualsCanonicalizing(
            array_map(static fn(string $id): array => ['tenant-1', $id], [$firstRegistration->value(), $secondRegistration->value(), $firstRequest->value(), $secondRequest->value()]),
            \array_slice($rows, 1),
        );
        static::assertStringContainsString('Dry run: 4 record(s)', $tester->getDisplay());
        static::assertSame('invalid_credentials', self::registrationState($harness, $firstRegistration->value()));

        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-1', '--limit' => '10']));
        static::assertSame([['Records', 'Requeued'], ['customer registrations', '2'], ['payment requests', '2']], Output::rows($tester->getDisplay()));
        static::assertSame('pending', self::registrationState($harness, $firstRegistration->value()));
        static::assertSame('pending', self::registrationState($harness, $secondRegistration->value()));
        foreach ([$firstRequest, $secondRequest] as $id) {
            static::assertSame('pending', $harness->get(FindPaymentRequestsHandler::class)(FindPaymentRequests::byIds(TenantId::fromString('tenant-1'), $id))[0]->state);
        }
    }

    private static function registrationState(Harness $harness, string $id): string
    {
        return $harness->get(GetCustomerRegistrationHandler::class)(new GetCustomerRegistration(
            TenantId::fromString('tenant-1'),
            CustomerSelector::byRegistrationId(RegistrationId::fromString($id)),
        ))->state;
    }
}

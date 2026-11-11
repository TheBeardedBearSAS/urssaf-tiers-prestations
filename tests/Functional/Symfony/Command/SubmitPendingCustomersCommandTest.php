<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Scenario;

final class SubmitPendingCustomersCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:customers:submit-pending';

    public function testDryRunListsThePendingRegistrationsWithoutCallingUrssaf(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingRegistration($harness);

        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--dry-run' => true]));
        static::assertSame([['Tenant', 'Record'], ['tenant-1', $id->value()]], Output::rows($tester->getDisplay()));
        static::assertStringContainsString('Dry run: 1 record(s) would be processed, URSSAF was not called.', $tester->getDisplay());
        static::assertSame([], $harness->client->getRequests());
        static::assertSame('pending', self::state($harness, $id));
    }

    public function testSubmitsThePendingRegistrations(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingRegistration($harness);

        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));
        static::assertSame([['Record', 'Outcome', 'Codes'], [$id->value(), 'accepted', '']], Output::rows($tester->getDisplay()));
        static::assertStringContainsString('1 record(s) processed (accepted: 1).', $tester->getDisplay());
        static::assertSame('awaiting_pairing', self::state($harness, $id));
    }

    public function testTheTenantOptionSelectsTheTenant(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingRegistration($harness);

        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-2']));
        static::assertStringContainsString('0 record(s) processed.', $tester->getDisplay());
        static::assertSame('pending', self::state($harness, $id));
        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-1']));
        static::assertSame('awaiting_pairing', self::state($harness, $id));
    }

    public function testTheLimitOptionCapsTheNumberOfRegistrations(): void
    {
        $harness = Harness::boot();
        Scenario::pendingRegistration($harness, 'tenant-1', 'customer-1');
        Scenario::pendingRegistration($harness, 'tenant-1', 'customer-2');

        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--limit' => '1']));
        static::assertStringContainsString('1 record(s) processed', $tester->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_REGISTER));
    }

    public function testAServerErrorLeavesTheRegistrationForAManualReviewAndFailsTheCommand(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingRegistration($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_REGISTER, 503, '{}');

        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));
        static::assertStringContainsString('manual_review', $tester->getDisplay());
        static::assertSame('error', self::state($harness, $id));
        $calls = $harness->urssaf->calls(HttpScript::ROUTE_REGISTER);
        static::assertSame(0, $tester->execute([]));
        static::assertSame($calls, $harness->urssaf->calls(HttpScript::ROUTE_REGISTER), 'Never sent again automatically.');
    }

    public function testATemporaryFailureBeforeUrssafProcessedTheCallFailsTheCommandAndKeepsTheRegistrationPending(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingRegistration($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_REGISTER, 429, '{}');

        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));
        static::assertStringContainsString('failed', $tester->getDisplay());
        static::assertStringContainsString('urssaf.unavailable', $tester->getDisplay());
        static::assertSame('pending', self::state($harness, $id));
    }

    public function testAnUnexpectedAnswerFailsTheCommandWithTheTypeOfTheFailure(): void
    {
        $harness = Harness::boot();
        Scenario::pendingRegistration($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_REGISTER, 200, '{}');

        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));
        static::assertStringContainsString('UnexpectedUrssafResponse', $tester->getDisplay());
    }

    public function testARejectionIsAFinalBusinessOutcomeNotAFailure(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingRegistration($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_REGISTER, 400, (string) file_get_contents(__DIR__ . '/../../../Fixtures/wire/010-register-error-unknown-taxpayer.json'));

        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));
        static::assertStringContainsString('rejected', $tester->getDisplay());
        static::assertSame('rejected', self::state($harness, $id));
    }

    public function testRefusedCredentialsFailTheCommand(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingRegistration($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{}');

        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));
        static::assertStringContainsString('invalid_credentials', $tester->getDisplay());
        static::assertSame('invalid_credentials', self::state($harness, $id));
    }

    private static function state(Harness $harness, RegistrationId $id): string
    {
        return $harness->get(GetCustomerRegistrationHandler::class)(
            new GetCustomerRegistration(TenantId::fromString('tenant-1'), CustomerSelector::byRegistrationId($id)),
        )->state;
    }
}

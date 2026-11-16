<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Diagnostic\InstallationCheck;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\HttpScript;

/**
 * One method per check outcome.
 *
 * @mago-expect lint:too-many-methods
 */
final class CheckInstallationCommandTest extends TestCase
{
    public function testEveryCheckIsReportedInOrderAndWarningsDoNotFail(): void
    {
        $harness = Harness::boot(['lock' => ['enabled' => false]]);
        $command = $harness->command('urssaf:check');

        static::assertSame(0, $command->execute([]));

        static::assertSame([
            ['credentials', 'tenant-1', 'ok', 'Credentials found.'],
            ['authentication', 'tenant-1', 'ok', 'Access token granted by URSSAF.'],
            ['token store', '', 'warning', 'Cache pool "cache.app": declare a dedicated pool shared by every process, so that clearing the application cache does not drop the tokens (docs/symfony.md).'],
            ['lock', '', 'warning', 'Disabled: overlapping runs are only protected by the claims (install symfony/lock, see docs/symfony.md).'],
        ], self::rows($command));
        static::assertMatchesRegularExpression('/^\| Check +\| Subject +\| Status +\| Detail +\|$/m', $command->getDisplay());
        static::assertStringContainsString('The URSSAF integration is ready.', $command->getDisplay());
    }

    public function testTheAuthenticationIsAskedToUrssafOnEveryRunWhateverTheTokenStore(): void
    {
        $harness = Harness::boot();

        $harness->command('urssaf:check')->execute([]);
        $harness->command('urssaf:check')->execute([]);

        static::assertSame(2, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testRefusedCredentialsFailTheCheck(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{"error":"invalid_client"}');
        $command = $harness->command('urssaf:check');

        static::assertSame(1, $command->execute([]));

        static::assertSame('failure', self::rows($command)[1][2]);
        static::assertStringContainsString('1 check(s) failed.', $command->getDisplay());
        static::assertStringNotContainsString('ready', $command->getDisplay());
    }

    public function testATenantWithoutCredentialsFailsWithoutCallingUrssafForIt(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:check');

        static::assertSame(1, $command->execute(['--tenant' => 'tenant-9']));

        $rows = self::rows($command);
        static::assertSame(['credentials', 'tenant-9', 'failure'], \array_slice($rows[0], 0, 3));
        static::assertSame(['authentication', 'tenant-9', 'failure'], \array_slice($rows[1], 0, 3));
        static::assertStringContainsString('2 check(s) failed.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testSkipRemoteDoesNotCallUrssaf(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:check');

        static::assertSame(0, $command->execute(['--skip-remote' => true]));

        static::assertSame(['authentication', '', 'skipped', 'Not checked (--skip-remote).'], self::rows($command)[1]);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testNoTenantToCheckIsAnError(): void
    {
        $command = Harness::boot(['credentials' => null, 'credentials_provider' => 'test.credentials'])->command('urssaf:check');

        static::assertSame(1, $command->execute([]));
        static::assertStringContainsString('No tenant to process', $command->getDisplay());
    }

    public function testTheSimulatedGatewayIsReportedInsteadOfTheAuthentication(): void
    {
        $harness = Harness::boot(['gateway' => ['simulated' => true]]);
        $command = $harness->command('urssaf:check');

        static::assertSame(0, $command->execute([]));

        static::assertSame(['gateway', '', 'warning', 'Simulated gateway ("gateway.simulated"): nothing is sent to URSSAF.'], self::rows($command)[1]);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testADedicatedTokenStoreAndTheLockAreOk(): void
    {
        $pool = self::executedRows(Harness::boot(['token_store' => ['cache_pool' => 'cache.system']], false, false, true)->command('urssaf:check'));
        $service = self::executedRows(Harness::boot(['token_store' => ['service' => 'test.token_store']])->command('urssaf:check'));

        static::assertSame(['token store', '', 'ok', 'Cache pool "cache.system": it must be shared by every process that calls URSSAF.'], $pool[2]);
        static::assertSame(['lock', '', 'ok', 'Enabled: use a store shared by every host that runs the crons and workers.'], $pool[3]);
        static::assertSame(['token store', '', 'ok', 'Service "test.token_store".'], $service[2]);
    }

    public function testTheDatabaseSchemaMustBeCreated(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'doctrine']], true);

        $missing = $harness->command('urssaf:check');
        static::assertSame(1, $missing->execute(['--skip-remote' => true]));
        $rows = self::rows($missing);
        static::assertSame(['database schema', 'default', 'failure'], \array_slice($rows[3], 0, 3));
        static::assertMatchesRegularExpression('/^\d+ schema change\(s\) pending on the package tables: run doctrine:migrations:diff then doctrine:migrations:migrate\.$/', $rows[3][3]);
        static::assertSame(['entity manager', 'default', 'warning', 'Shared with the application: use a dedicated entity manager (docs/doctrine.md).'], $rows[4]);

        // A table of the application in the same database is not a pending change of the package tables.
        $harness->privateService('doctrine.dbal.default_connection', Connection::class)->executeStatement('CREATE TABLE host_invoices (id INTEGER NOT NULL)');
        $harness->createDatabaseSchema();
        $created = $harness->command('urssaf:check');
        static::assertSame(0, $created->execute(['--skip-remote' => true]));
        static::assertSame(['database schema', 'default', 'ok', 'The package tables match the mapping.'], self::rows($created)[3]);
    }

    public function testADedicatedEntityManagerIsNotReported(): void
    {
        $doctrine = ['dbal' => ['driver' => 'pdo_sqlite', 'memory' => true], 'orm' => ['entity_managers' => ['urssaf' => ['connection' => 'default']]]];
        $command = Harness::boot(['persistence' => ['driver' => 'doctrine', 'entity_manager' => 'urssaf']], $doctrine)->command('urssaf:check');

        $command->execute(['--skip-remote' => true]);

        $rows = self::rows($command);
        static::assertSame(['database schema', 'urssaf'], \array_slice($rows[3], 0, 2));
        static::assertSame('lock', $rows[4][0]);
    }

    public function testTheChecksOfTheHostAreAutoconfigured(): void
    {
        $autoconfigured = (new TestKernel())->definitions()->getAutoconfiguredInstanceof();

        static::assertArrayHasKey(InstallationCheck::class, $autoconfigured);
        static::assertSame([InstallationCheck::TAG => [[]]], $autoconfigured[InstallationCheck::class]->getTags());
    }

    /**
     * The rows of the report of a run with --skip-remote.
     *
     * @return list<list<string>>
     */
    private static function executedRows(CommandTester $command): array
    {
        $command->execute(['--skip-remote' => true]);

        return self::rows($command);
    }

    /**
     * The rows of the report table.
     *
     * @return list<list<string>>
     */
    private static function rows(CommandTester $command): array
    {
        $rows = [];
        foreach (explode("\n", $command->getDisplay()) as $line) {
            $matches = [];
            if (preg_match('/^\| (.*) \|$/', $line, $matches) !== 1) {
                continue;
            }
            $rows[] = array_map(trim(...), explode(' | ', $matches[1]));
        }

        return \array_slice($rows, 1);
    }
}

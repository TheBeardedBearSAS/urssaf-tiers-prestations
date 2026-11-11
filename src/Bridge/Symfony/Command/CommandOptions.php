<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * The options shared by the console commands (--tenant, --limit, --dry-run) and their reading.
 */
final class CommandOptions
{
    public static function addTenant(Command $command, string $description): void
    {
        $command->addOption('tenant', null, InputOption::VALUE_REQUIRED, $description);
    }

    public static function addLimit(Command $command): void
    {
        $command->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of records to process (capped by the workflow policy)');
    }

    public static function addDryRun(Command $command): void
    {
        $command->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be done without calling URSSAF or changing anything');
    }

    public static function dryRun(InputInterface $input): bool
    {
        return $input->getOption('dry-run') === true;
    }

    public static function tenant(InputInterface $input): ?TenantId
    {
        $tenant = self::text($input, 'tenant');

        return $tenant === null ? null : TenantId::fromString($tenant);
    }

    /**
     * @throws InvalidOptionException when the option is missing
     */
    public static function requiredTenant(InputInterface $input): TenantId
    {
        return self::tenant($input) ?? throw new InvalidOptionException('The --tenant option is required.');
    }

    /**
     * @throws InvalidOptionException for a value that is not a positive integer
     */
    public static function limit(InputInterface $input): ?int
    {
        $limit = self::text($input, 'limit');
        if ($limit === null) {
            return null;
        }
        if (filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new InvalidOptionException('The --limit option must be a positive integer.');
        }

        return (int) $limit;
    }

    /**
     * The tenant given with --tenant, else the configured tenants.
     *
     * @param list<string> $configured
     *
     * @return non-empty-list<TenantId>
     *
     * @throws InvalidOptionException when no tenant can be determined
     */
    public static function tenantsOrConfigured(InputInterface $input, array $configured): array
    {
        $tenant = self::tenant($input);
        if ($tenant instanceof TenantId) {
            return [$tenant];
        }
        $tenants = array_map(TenantId::fromString(...), $configured);

        return $tenants === [] ? throw new InvalidOptionException('No tenant to process: use --tenant=<id> or configure "urssaf_tiers_prestations.tenants".') : $tenants;
    }

    private static function text(InputInterface $input, string $name): ?string
    {
        // @mago-expect analysis:mixed-assignment Symfony options are untyped: the value is narrowed on the next line.
        $value = $input->getOption($name);

        return \is_string($value) ? $value : null;
    }
}

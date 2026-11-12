<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Diagnostic;

use Override;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Every tenant has credentials in the configured provider (nothing is sent to URSSAF).
 */
final readonly class CredentialsCheck implements InstallationCheck
{
    private const NAME = 'credentials';

    public function __construct(private CredentialsProvider $credentials) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        return array_map($this->check(...), $context->tenants);
    }

    private function check(TenantId $tenantId): CheckResult
    {
        try {
            $this->credentials->forTenant($tenantId);
        } catch (UrssafException $failure) {
            return new CheckResult(self::NAME, $tenantId->value(), CheckStatus::Failure, $failure->getMessage());
        }

        return new CheckResult(self::NAME, $tenantId->value(), CheckStatus::Ok, 'Credentials found.');
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Diagnostic;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineIntegration;

/**
 * The tables of the package exist and match the mapping (driver "doctrine").
 */
final readonly class DatabaseSchemaCheck implements InstallationCheck
{
    private const NAME = 'database schema';

    public function __construct(private EntityManagerInterface $entityManager, private string $entityManagerName) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        $pending = array_filter(
            (new SchemaTool($this->entityManager))->getUpdateSchemaSql($this->entityManager->getMetadataFactory()->getAllMetadata()),
            static fn(string $sql): bool => str_contains($sql, DoctrineIntegration::TABLE_PREFIX),
        );
        $results = [$pending === []
            ? new CheckResult(self::NAME, $this->entityManagerName, CheckStatus::Ok, 'The package tables match the mapping.')
            : new CheckResult(self::NAME, $this->entityManagerName, CheckStatus::Failure, \sprintf(
                '%d schema change(s) pending on the package tables: run doctrine:migrations:diff then doctrine:migrations:migrate.',
                \count($pending),
            ))];
        if ($this->entityManagerName === 'default') {
            $results[] = new CheckResult('entity manager', $this->entityManagerName, CheckStatus::Warning, 'Shared with the application: use a dedicated entity manager (docs/doctrine.md).');
        }

        return $results;
    }
}

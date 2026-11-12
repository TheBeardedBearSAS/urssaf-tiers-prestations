<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Diagnostic;

/**
 * A check run by `urssaf:check`. Services tagged "urssaf_tiers_prestations.installation_check" (autoconfigured) are
 * run after the checks of the package, unless their tag has a positive priority.
 */
interface InstallationCheck
{
    public const TAG = 'urssaf_tiers_prestations.installation_check';

    /**
     * @return list<CheckResult>
     */
    public function run(CheckContext $context): array;
}

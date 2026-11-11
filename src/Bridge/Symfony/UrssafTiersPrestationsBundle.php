<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection\BundleConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection\ServiceRegistrar;

/**
 * Symfony integration of the URSSAF "API Tiers de prestations" client (Symfony 6.4 and 7.x).
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final class UrssafTiersPrestationsBundle extends AbstractBundle
{
    public const ALIAS = 'urssaf_tiers_prestations';
    public const PARAMETER_TENANTS = 'urssaf_tiers_prestations.tenants';

    protected string $extensionAlias = self::ALIAS;

    public function configure(DefinitionConfigurator $definition): void
    {
        BundleConfiguration::define($definition);
    }

    /**
     * Maps the aggregates with Doctrine when the "doctrine" persistence driver is selected (the default when the ORM is
     * installed) and DoctrineBundle is registered.
     */
    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine')) {
            return;
        }
        $persistence = self::selectedPersistence($container->getExtensionConfig(self::ALIAS));
        if ($persistence['driver'] !== BundleConfiguration::DRIVER_DOCTRINE) {
            return;
        }
        $doctrine = DoctrineIntegration::doctrineBundleConfiguration();
        if ($persistence['entity_manager'] !== 'default') {
            $mappings = $doctrine['orm']['mappings'];
            unset($doctrine['orm']['mappings']);
            $doctrine['orm']['entity_managers'] = [$persistence['entity_manager'] => ['mappings' => $mappings]];
        }
        $container->prependExtensionConfig('doctrine', $doctrine);
    }

    /**
     * @param array<array-key, mixed> $config the configuration validated by {@see BundleConfiguration}
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        /** @var Config $validated */
        $validated = $config;
        $tenants = $validated['tenants'] !== [] ? $validated['tenants'] : array_map(strval(...), array_keys($validated['credentials']));
        $configurator->parameters()->set(self::PARAMETER_TENANTS, $tenants);
        (new ServiceRegistrar($configurator->services()))->register($validated);
    }

    /**
     * @param array<array-key, array<array-key, mixed>> $configs raw configurations of the bundle, in merge order
     *
     * @return array{driver: string, entity_manager: string}
     */
    private static function selectedPersistence(array $configs): array
    {
        $selected = ['driver' => BundleConfiguration::defaultDriver(), 'entity_manager' => 'default'];
        foreach ($configs as $config) {
            $persistence = \is_array($config['persistence'] ?? null) ? $config['persistence'] : [];
            $selected['driver'] = self::stringOr($persistence['driver'] ?? null, $selected['driver']);
            $selected['entity_manager'] = self::stringOr($persistence['entity_manager'] ?? null, $selected['entity_manager']);
        }

        return $selected;
    }

    private static function stringOr(mixed $value, string $default): string
    {
        return \is_string($value) ? $value : $default;
    }
}

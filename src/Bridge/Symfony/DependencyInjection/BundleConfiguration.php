<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

/**
 * Configuration tree of the "urssaf_tiers_prestations" bundle configuration.
 */
final class BundleConfiguration
{
    public const DRIVER_IN_MEMORY = 'in_memory';
    public const DRIVER_DOCTRINE = 'doctrine';
    public const DRIVER_CUSTOM = 'custom';

    public static function defaultDriver(): string
    {
        return interface_exists(EntityManagerInterface::class) ? self::DRIVER_DOCTRINE : self::DRIVER_IN_MEMORY;
    }

    public static function define(DefinitionConfigurator $definition): void
    {
        $root = $definition->rootNode();
        $root
            ->validate()
                ->ifTrue(ConfigurationRules::hasBothCredentialSources(...))
                ->thenInvalid('"credentials" and "credentials_provider" are mutually exclusive.')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::hasNoCredentialSource(...))
                ->thenInvalid('Configure either "credentials" or "credentials_provider".')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::lockEnabledWithoutComponent(...))
                ->thenInvalid('"lock.enabled: true" requires symfony/lock (composer require symfony/lock).')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::autoSubmitWithoutMessenger(...))
                ->thenInvalid('"messenger.auto_submit" requires "messenger.enabled".')
            ->end()
            ->children()
                ->enumNode('environment')->isRequired()->values(['sandbox', 'production'])->end()
                ->scalarNode('base_uri')->defaultNull()->info('Overrides the base URI of the selected environment.')->end()
                ->scalarNode('application_id')->defaultNull()->info('Value of the "recouv.application.id" header.')->end()
                ->integerNode('token_safety_margin')->min(0)->defaultValue(60)->info('Seconds before expiry from which an access token is renewed.')->end()
                ->scalarNode('pairing_time_zone')
                    ->defaultValue('Europe/Paris')
                    ->info('Time zone of the pairing acceptance date that URSSAF writes in the method 020 status description (French local time).')
                    ->validate()
                        ->ifTrue(ConfigurationRules::isUnknownTimeZone(...))
                        ->thenInvalid('Unknown time zone %s.')
                    ->end()
                ->end()
                ->scalarNode('http_client')->defaultNull()->info('Service id of a PSR-18 client (default: a dedicated Symfony HttpClient).')->end()
                ->floatNode('http_timeout')->min(0.1)->defaultValue(15.0)->info('Timeout in seconds of the default HTTP client.')->end()
                ->scalarNode('credentials_provider')->defaultNull()->info('Service id of a CredentialsProvider.')->end()
                ->arrayNode('credentials')
                    ->info('Credentials by tenant id; use "%env(...)%" placeholders.')
                    ->useAttributeAsKey('tenant_id')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('client_secret')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tenants')
                    ->info('Tenants processed by commands that cannot discover them (default: the keys of "credentials").')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('token_store')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('cache_pool')->defaultValue('cache.app')->cannotBeEmpty()->end()
                        ->scalarNode('service')->defaultNull()->info('Service id of a TokenStore (takes precedence over the cache pool).')->end()
                    ->end()
                ->end()
                ->arrayNode('rate_limiter')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->info('Enforces the URSSAF quotas per tenant in the current process.')->end()
                    ->end()
                ->end()
                ->arrayNode('lock')
                    ->info('Non-blocking Symfony Lock per command / handler and tenant ("urssaf_tps.<operation>.<tenant>"): overlapping runs are skipped (command) or retried later (Messenger).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('enabled')->values(['auto', true, false])->defaultValue('auto')->info('"auto": enabled when symfony/lock is installed and the factory service exists.')->end()
                        ->scalarNode('factory')->defaultValue('lock.factory')->cannotBeEmpty()->info('Service id of a Symfony\\Component\\Lock\\LockFactory.')->end()
                        ->floatNode('ttl')->min(1)->defaultValue(3600.0)->info('Seconds after which a lock of a crashed process expires.')->end()
                    ->end()
                ->end()
                ->append(self::workflow())
                ->append(self::persistence())
                ->arrayNode('messenger')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('bus')->defaultValue('messenger.default_bus')->cannotBeEmpty()->end()
                        ->booleanNode('auto_submit')->defaultFalse()->info('Dispatches the submit messages when a registration or payment request is created.')->end()
                    ->end()
                ->end()
            ->end();
    }

    private static function workflow(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('workflow');
        $node
            ->addDefaultsIfNotSet()
            ->validate()
                ->ifTrue(ConfigurationRules::defaultPageAboveMax(...))
                ->thenInvalid('"default_page_size" must not be greater than "max_page_size".')
            ->end()
            ->children()
                ->scalarNode('business_timezone')
                    ->defaultValue('Europe/Paris')
                    ->validate()
                        ->ifTrue(ConfigurationRules::isUnknownTimeZone(...))
                        ->thenInvalid('Unknown time zone %s.')
                    ->end()
                ->end()
                ->scalarNode('settled_max_age')->defaultValue('P90D')->validate()->ifTrue(ConfigurationRules::isInvalidInterval(...))->thenInvalid('Invalid ISO 8601 duration %s.')->end()->end()
                ->scalarNode('unknown_status_max_age')->defaultValue('P30D')->info('How long a payment request is still polled after a status code unknown to this package version was observed.')->validate()->ifTrue(ConfigurationRules::isInvalidInterval(...))->thenInvalid('Invalid ISO 8601 duration %s.')->end()->end()
                ->scalarNode('max_polling_age')->defaultValue('P1Y')->info('How long a payment request is still polled after its submission, whatever its status.')->validate()->ifTrue(ConfigurationRules::isInvalidInterval(...))->thenInvalid('Invalid ISO 8601 duration %s.')->end()->end()
                ->scalarNode('stale_submission_after')->defaultValue('PT15M')->validate()->ifTrue(ConfigurationRules::isInvalidInterval(...))->thenInvalid('Invalid ISO 8601 duration %s.')->end()->end()
                ->scalarNode('billing_third_party_id')->defaultValue('menage.fr')->cannotBeEmpty()->end()
                ->integerNode('batch_size')->min(1)->max(10)->defaultValue(10)->info('Payment requests per submission call (URSSAF accepts 1 to 10).')->end()
                ->integerNode('default_page_size')->min(1)->defaultValue(100)->end()
                ->integerNode('max_page_size')->min(1)->defaultValue(1000)->end()
                ->integerNode('max_submission_attempts')->min(1)->defaultValue(5)->end()
            ->end();

        return $node;
    }

    private static function persistence(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('persistence');
        $node
            ->addDefaultsIfNotSet()
            ->validate()
                ->ifTrue(ConfigurationRules::customDriverWithoutRepositories(...))
                ->thenInvalid('The "custom" driver requires "customer_registration_repository" and "payment_request_repository".')
            ->end()
            ->children()
                ->enumNode('driver')->values([self::DRIVER_IN_MEMORY, self::DRIVER_DOCTRINE, self::DRIVER_CUSTOM])->defaultValue(self::defaultDriver())->end()
                ->scalarNode('customer_registration_repository')->defaultNull()->end()
                ->scalarNode('payment_request_repository')->defaultNull()->end()
                ->scalarNode('entity_manager')->defaultValue('default')->cannotBeEmpty()
                    ->info('Name of the Doctrine entity manager. Use a DEDICATED entity manager (own connection) for this package: its repositories flush the whole unit of work of the entity manager they use, which would also write pending changes of the host entities sharing it.')
                ->end()
            ->end();

        return $node;
    }
}

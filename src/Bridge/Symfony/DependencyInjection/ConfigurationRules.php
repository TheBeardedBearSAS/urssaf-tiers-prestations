<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection;

use DateInterval;
use DateTimeZone;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Throwable;

/**
 * Validation predicates of the configuration tree (each one is true when the value is invalid).
 *
 * One small predicate per rule of the tree, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class ConfigurationRules
{
    /**
     * @param array<array-key, mixed> $value
     */
    public static function hasBothCredentialSources(array $value): bool
    {
        /** @var array{credentials: array<array-key, mixed>, credentials_provider: ?string} $config */
        $config = $value;

        return $config['credentials'] !== [] && $config['credentials_provider'] !== null;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function hasNoCredentialSource(array $value): bool
    {
        /** @var array{credentials: array<array-key, mixed>, credentials_provider: ?string} $config */
        $config = $value;

        return $config['credentials'] === [] && $config['credentials_provider'] === null;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function simulatedGatewayInProduction(array $value): bool
    {
        /** @var array{environment: string, gateway: array{simulated: bool}} $config */
        $config = $value;

        return $config['gateway']['simulated'] && $config['environment'] === 'production';
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function autoSubmitWithoutMessenger(array $value): bool
    {
        /** @var array{messenger: array{enabled: bool, auto_submit: bool}} $config */
        $config = $value;

        return $config['messenger']['auto_submit'] && !$config['messenger']['enabled'];
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function lockEnabledWithoutComponent(array $value): bool
    {
        return self::lockRequiredButMissing($value, class_exists(LockFactory::class));
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function lockRequiredButMissing(array $value, bool $componentInstalled): bool
    {
        /** @var array{lock: array{enabled: bool|string}} $config */
        $config = $value;

        return $config['lock']['enabled'] === true && !$componentInstalled;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function schedulerEnabledWithoutComponent(array $value): bool
    {
        return self::schedulerRequiredButMissing($value, interface_exists(ScheduleProviderInterface::class));
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function schedulerRequiredButMissing(array $value, bool $componentInstalled): bool
    {
        /** @var array{scheduler: array{enabled: bool}} $config */
        $config = $value;

        return $config['scheduler']['enabled'] && !$componentInstalled;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function defaultPageAboveMax(array $value): bool
    {
        /** @var array{default_page_size: int, max_page_size: int, max_submission_attempts: int} $workflow */
        $workflow = $value;

        return $workflow['default_page_size'] > $workflow['max_page_size'];
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function customDriverWithoutRepositories(array $value): bool
    {
        /** @var array{driver: string, customer_registration_repository: ?string, payment_request_repository: ?string} $persistence */
        $persistence = $value;

        return $persistence['driver'] === BundleConfiguration::DRIVER_CUSTOM
            && ($persistence['customer_registration_repository'] === null || $persistence['payment_request_repository'] === null);
    }

    public static function isUnknownTimeZone(mixed $zone): bool
    {
        return !\is_string($zone) || !\in_array($zone, DateTimeZone::listIdentifiers(), true);
    }

    public static function isInvalidOptionalInterval(mixed $value): bool
    {
        return $value !== null && self::isInvalidInterval($value);
    }

    public static function isInvalidInterval(mixed $value): bool
    {
        try {
            new DateInterval(\is_string($value) ? $value : '');
        } catch (Throwable) {
            return true;
        }

        return false;
    }
}

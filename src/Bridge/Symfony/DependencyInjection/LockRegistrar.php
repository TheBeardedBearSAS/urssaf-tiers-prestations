<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;

/**
 * The operation locks: backed by the configured lock factory when enabled ("auto": when symfony/lock is installed and
 * the factory service exists), a pass-through otherwise.
 */
final readonly class LockRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param array{enabled: bool|string, factory: string, ttl: float} $lock
     */
    public function register(array $lock): void
    {
        $factory = match (true) {
            $lock['enabled'] === false || !class_exists(LockFactory::class) => null,
            $lock['enabled'] === true => new Reference($lock['factory']),
            default => new Reference($lock['factory'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
        };
        $this->services->set(OperationLocks::class)->args([$factory, $lock['ttl']]);
    }
}

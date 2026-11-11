<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrineCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrinePaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryPaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Transaction\ImmediateTransactionManager;

/**
 * Repositories and transaction manager of the selected persistence driver.
 */
final readonly class PersistenceRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param array{driver: string, customer_registration_repository: ?string, payment_request_repository: ?string, entity_manager: string} $persistence
     */
    public function register(array $persistence): void
    {
        if ($persistence['driver'] === BundleConfiguration::DRIVER_DOCTRINE) {
            $this->registerDoctrine(new Reference(\sprintf('doctrine.orm.%s_entity_manager', $persistence['entity_manager'])));

            return;
        }
        $this->services->set(TransactionManager::class, ImmediateTransactionManager::class);
        if ($persistence['driver'] === BundleConfiguration::DRIVER_CUSTOM) {
            $this->services->alias(CustomerRegistrationRepository::class, (string) $persistence['customer_registration_repository']);
            $this->services->alias(PaymentRequestRepository::class, (string) $persistence['payment_request_repository']);

            return;
        }
        $this->services->set(CustomerRegistrationRepository::class, InMemoryCustomerRegistrationRepository::class);
        $this->services->set(PaymentRequestRepository::class, InMemoryPaymentRequestRepository::class);
    }

    private function registerDoctrine(Reference $entityManager): void
    {
        $this->services->set(CustomerRegistrationRepository::class, DoctrineCustomerRegistrationRepository::class)->args([$entityManager]);
        $this->services->set(PaymentRequestRepository::class, DoctrinePaymentRequestRepository::class)->args([$entityManager]);
        $this->services->set(TransactionManager::class, DoctrineTransactionManager::class)->args([$entityManager]);
    }
}

<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\ProxyFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrineCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrinePaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryPaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory\RegistrationBuilder;

final class DoctrineWiringTest extends TestCase
{
    public function testTheDoctrineDriverWiresTheRepositoriesAndTheTransactionManager(): void
    {
        $harness = self::doctrine('default');

        static::assertInstanceOf(DoctrineCustomerRegistrationRepository::class, $harness->privateService(CustomerRegistrationRepository::class, CustomerRegistrationRepository::class));
        static::assertInstanceOf(DoctrinePaymentRequestRepository::class, $harness->privateService(PaymentRequestRepository::class, PaymentRequestRepository::class));
        static::assertInstanceOf(DoctrineTransactionManager::class, $harness->privateService(TransactionManager::class, TransactionManager::class));
    }

    public function testTheMappingAndTheTypesAreRegisteredInTheDefaultEntityManager(): void
    {
        $harness = self::doctrine('default');
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);

        static::assertFalse($entityManager->getMetadataFactory()->isTransient(CustomerRegistration::class));
        static::assertFalse($entityManager->getMetadataFactory()->isTransient(PaymentRequest::class));
        // The bundle leaves the proxy generation mode to the host (doctrine-bundle default outside debug: never).
        static::assertSame(ProxyFactory::AUTOGENERATE_NEVER, $entityManager->getConfiguration()->getAutoGenerateProxyClasses());
        foreach (array_keys(DoctrineIntegration::types()) as $type) {
            static::assertTrue(Type::hasType($type), $type);
        }
    }

    public function testAnotherEntityManagerCanBeSelected(): void
    {
        $harness = Harness::boot(
            ['persistence' => ['driver' => 'doctrine', 'entity_manager' => 'urssaf']],
            [
                'dbal' => ['connections' => ['default' => ['driver' => 'pdo_sqlite', 'memory' => true], 'urssaf' => ['driver' => 'pdo_sqlite', 'memory' => true]]],
                'orm' => [
                    'enable_lazy_ghost_objects' => true,
                    'entity_managers' => ['default' => ['connection' => 'default'], 'urssaf' => ['connection' => 'urssaf']],
                ],
            ],
        );

        $mapped = $harness->privateService('doctrine.orm.urssaf_entity_manager', EntityManagerInterface::class);
        $other = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);
        static::assertFalse($mapped->getMetadataFactory()->isTransient(CustomerRegistration::class));
        static::assertTrue($other->getMetadataFactory()->isTransient(CustomerRegistration::class));
    }

    public function testNothingIsMappedWhenAnotherDriverIsSelected(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'in_memory']], true);
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);

        static::assertTrue($entityManager->getMetadataFactory()->isTransient(CustomerRegistration::class));
        static::assertInstanceOf(InMemoryCustomerRegistrationRepository::class, $harness->privateService(CustomerRegistrationRepository::class, CustomerRegistrationRepository::class));
        static::assertInstanceOf(InMemoryPaymentRequestRepository::class, $harness->privateService(PaymentRequestRepository::class, PaymentRequestRepository::class));
        static::assertInstanceOf(ImmediateTransactionManager::class, $harness->privateService(TransactionManager::class, TransactionManager::class));
    }

    public function testTheProxyCacheWarmerGeneratesTheProxiesOfTheMappedEntitiesWhichThenLoadLazily(): void
    {
        $harness = Harness::boot(
            ['persistence' => ['driver' => 'doctrine']],
            ['dbal' => ['driver' => 'pdo_sqlite', 'memory' => true], 'orm' => ['enable_lazy_ghost_objects' => true, 'auto_generate_proxy_classes' => false]],
        );
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);
        $proxyDirectory = $entityManager->getConfiguration()->getProxyDir();
        static::assertIsString($proxyDirectory);
        (new Filesystem())->remove($proxyDirectory);

        $harness->privateService('cache_warmer', CacheWarmerAggregate::class)->warmUp($harness->kernel->getCacheDir());

        $files = glob($proxyDirectory . '/*.php');
        static::assertIsArray($files);
        $proxies = array_map(basename(...), $files);
        sort($proxies);
        static::assertSame(
            [
                '__CG__TheBeardedBearUrssafTiersPrestationsBridgeDoctrineEntityPaymentRequestProgress.php',
                '__CG__TheBeardedBearUrssafTiersPrestationsDomainCustomerCustomerRegistration.php',
                '__CG__TheBeardedBearUrssafTiersPrestationsDomainPaymentRequestPaymentRequest.php',
            ],
            $proxies,
        );
        $harness->createDatabaseSchema();
        $harness->privateService(CustomerRegistrationRepository::class, CustomerRegistrationRepository::class)
            ->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
        $entityManager->clear();

        $reference = $entityManager->getReference(CustomerRegistration::class, RegistrationId::fromString('reg-1'));

        static::assertInstanceOf(CustomerRegistration::class, $reference);
        static::assertTrue($entityManager->getUnitOfWork()->isUninitializedObject($reference));
        static::assertSame('ref-1', $reference->externalReference()->value());
        static::assertFalse($entityManager->getUnitOfWork()->isUninitializedObject($reference));
    }

    private static function doctrine(string $entityManager): Harness
    {
        return Harness::boot(['persistence' => ['driver' => 'doctrine', 'entity_manager' => $entityManager]], true);
    }
}

<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Psr18Client;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomerHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TokenStore;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection\BundleConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Scenario;

final class BundleConfigurationTest extends TestCase
{
    public function testTheDriverDefaultsToDoctrineWhenTheOrmIsInstalled(): void
    {
        static::assertSame('doctrine', BundleConfiguration::defaultDriver());
    }

    public function testTheMinimalConfigurationCompiles(): void
    {
        $harness = Harness::boot();

        static::assertInstanceOf(RegisterCustomerHandler::class, $harness->get(RegisterCustomerHandler::class));
        static::assertInstanceOf(NoopRateLimiter::class, $harness->privateService(RateLimiter::class, RateLimiter::class));
        static::assertInstanceOf(Psr18Client::class, $harness->privateService('urssaf_tiers_prestations.psr18_client', Psr18Client::class));
    }

    public function testTheMinimalConfigurationUsesTheDocumentedDefaults(): void
    {
        $harness = Harness::boot();
        $policy = $harness->privateService(WorkflowPolicy::class, WorkflowPolicy::class);

        static::assertSame('Europe/Paris', $policy->businessTimeZone()->getName());
        static::assertSame(90, $policy->settledMaxAge()->d);
        static::assertSame(15, $policy->staleSubmissionAfter()->i);
        static::assertSame(90, $policy->pollingWindow()->settledMaxAge()->d);
        static::assertSame(30, $policy->pollingWindow()->unknownStatusMaxAge()->d);
        static::assertSame(1, $policy->pollingWindow()->maxPollingAge()->y);
        static::assertSame(5, $policy->maxSubmissionAttempts());
        static::assertSame('Europe/Paris', $harness->privateService('urssaf_tiers_prestations.api_configuration', ApiConfiguration::class)->pairingTimeZone()->getName());
        static::assertSame('menage.fr', $policy->billingThirdPartyId()->value());
        static::assertSame(10, $policy->submissionBatchSize());
        static::assertSame(100, $policy->pageSize(null));
        static::assertSame(1000, $policy->pageSize(5000));
        static::assertSame(['tenant-1'], $harness->kernel->getContainer()->getParameter('urssaf_tiers_prestations.tenants'));
    }

    public function testOnlyTheHandlersArePublic(): void
    {
        $harness = Harness::boot();

        static::assertTrue($harness->has(RegisterCustomerHandler::class));
        static::assertFalse($harness->has(WorkflowPolicy::class));
        static::assertFalse($harness->has(Clock::class));
        static::assertTrue($harness->hasPrivate(Clock::class));
    }

    public function testTheTokenStoreCanBeProvidedByTheApplication(): void
    {
        $harness = Harness::boot(['token_store' => ['service' => 'test.token_store']]);
        Scenario::pendingRegistration($harness);
        Scenario::pendingRegistration($harness, 'tenant-1', 'customer-2');

        static::assertSame($harness->privateService('test.token_store', TokenStore::class), $harness->privateService(TokenStore::class, TokenStore::class));
        static::assertSame(0, $harness->command('urssaf:customers:submit-pending')->execute(['--limit' => '1']));
        $harness->urssaf->respond(HttpScript::ROUTE_REGISTER, 200, '{"idClient":"bf0be430-908a-11eb-a8b3-0242ac130003"}');
        static::assertSame(0, $harness->command('urssaf:customers:submit-pending')->execute(['--limit' => '1']));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
        static::assertSame(2, $harness->urssaf->calls(HttpScript::ROUTE_REGISTER));
    }

    public function testTheLimitsOfTheConfigurationAreAccepted(): void
    {
        $harness = Harness::boot([
            'token_safety_margin' => 0,
            'workflow' => ['batch_size' => 1, 'default_page_size' => 1, 'max_page_size' => 1, 'max_submission_attempts' => 1],
        ]);
        $policy = $harness->privateService(WorkflowPolicy::class, WorkflowPolicy::class);

        static::assertSame(0, $harness->privateService('urssaf_tiers_prestations.api_configuration', ApiConfiguration::class)->tokenSafetyMarginSeconds());
        static::assertSame(1, $policy->submissionBatchSize());
        static::assertSame(1, $policy->pageSize(5));
        static::assertSame(1, $policy->maxSubmissionAttempts());
        static::assertSame(5, Harness::boot()->privateService(WorkflowPolicy::class, WorkflowPolicy::class)->maxSubmissionAttempts());
        static::assertSame(10, Harness::boot(['workflow' => ['batch_size' => 10]])->privateService(WorkflowPolicy::class, WorkflowPolicy::class)->submissionBatchSize());
    }

    public function testTheTokenSafetyMarginDefaultsToSixtySeconds(): void
    {
        $harness = Harness::boot();

        static::assertSame(60, $harness->privateService('urssaf_tiers_prestations.api_configuration', ApiConfiguration::class)->tokenSafetyMarginSeconds());
    }

    public function testTenantsAreStringsEvenWhenTheyLookLikeNumbers(): void
    {
        // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
        $harness = Harness::boot(['credentials' => ['42' => ['client_id' => 'client-42', 'client_secret' => 'secret-42']]]);

        static::assertSame(['tenant-1', '42'], $harness->kernel->getContainer()->getParameter('urssaf_tiers_prestations.tenants'));
    }

    public function testTheApplicationProvidesTheCredentialsAndTheRepositories(): void
    {
        $harness = Harness::boot([
            'credentials' => null,
            'credentials_provider' => 'test.credentials',
            'persistence' => ['driver' => 'custom', 'customer_registration_repository' => 'test.customers', 'payment_request_repository' => 'test.payment_requests'],
        ]);

        static::assertSame($harness->privateService('test.credentials', CredentialsProvider::class), $harness->privateService(CredentialsProvider::class, CredentialsProvider::class));
        static::assertSame($harness->privateService('test.customers', CustomerRegistrationRepository::class), $harness->privateService(CustomerRegistrationRepository::class, CustomerRegistrationRepository::class));
        static::assertSame($harness->privateService('test.payment_requests', PaymentRequestRepository::class), $harness->privateService(PaymentRequestRepository::class, PaymentRequestRepository::class));
        static::assertInstanceOf(ImmediateTransactionManager::class, $harness->privateService(TransactionManager::class, TransactionManager::class));
    }


    public function testTheFullConfigurationCompiles(): void
    {
        $harness = Harness::boot([
            'environment' => 'production',
            'base_uri' => 'https://sandbox.example.test',
            'application_id' => 'my-application',
            'token_safety_margin' => 120,
            'pairing_time_zone' => 'America/Martinique',
            'http_client' => null,
            'http_timeout' => 5.5,
            // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
            'credentials' => [
                'tenant-1' => ['client_id' => 'client-1', 'client_secret' => 'secret-1'],
                // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
                'tenant-2' => ['client_id' => 'client-2', 'client_secret' => 'secret-2'],
            ],
            'tenants' => ['tenant-2'],
            'token_store' => ['cache_pool' => 'cache.app'],
            'rate_limiter' => ['enabled' => true],
            'workflow' => [
                'business_timezone' => 'America/Martinique',
                'settled_max_age' => 'P30D',
                'stale_submission_after' => 'PT5M',
                'billing_third_party_id' => 'billing-x',
                'batch_size' => 4,
                'default_page_size' => 20,
                'max_page_size' => 50,
                'max_submission_attempts' => 3,
                'unknown_status_max_age' => 'P7D',
                'max_polling_age' => 'P6M',
            ],
            'lock' => ['enabled' => false, 'factory' => 'lock.factory', 'ttl' => 120.5],
            'persistence' => ['driver' => 'in_memory', 'entity_manager' => 'unused'],
            'messenger' => ['enabled' => true, 'bus' => 'messenger.default_bus', 'auto_submit' => true],
        ], false, true);

        $policy = $harness->privateService(WorkflowPolicy::class, WorkflowPolicy::class);
        static::assertSame('America/Martinique', $policy->businessTimeZone()->getName());
        static::assertSame(30, $policy->settledMaxAge()->d);
        static::assertSame(5, $policy->staleSubmissionAfter()->i);
        static::assertSame('billing-x', $policy->billingThirdPartyId()->value());
        static::assertSame(4, $policy->submissionBatchSize());
        static::assertSame(20, $policy->pageSize(null));
        static::assertSame(50, $policy->pageSize(500));
        static::assertSame(3, $policy->maxSubmissionAttempts());
        static::assertSame(7, $policy->pollingWindow()->unknownStatusMaxAge()->d);
        static::assertSame(6, $policy->pollingWindow()->maxPollingAge()->m);
        static::assertInstanceOf(SlidingWindowRateLimiter::class, $harness->privateService(RateLimiter::class, RateLimiter::class));
        static::assertSame(['tenant-2'], $harness->kernel->getContainer()->getParameter('urssaf_tiers_prestations.tenants'));
        static::assertSame(
            'https://sandbox.example.test',
            $harness->privateService('urssaf_tiers_prestations.api_configuration', ApiConfiguration::class)->baseUri(),
        );
        static::assertSame(
            'America/Martinique',
            $harness->privateService('urssaf_tiers_prestations.api_configuration', ApiConfiguration::class)->pairingTimeZone()->getName(),
        );
    }
}

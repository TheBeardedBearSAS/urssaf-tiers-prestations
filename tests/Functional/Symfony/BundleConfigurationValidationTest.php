<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;

final class BundleConfigurationValidationTest extends TestCase
{
    public function testTheDoctrineDriverNeedsDoctrineBundle(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('doctrine.orm.default_entity_manager');

        Harness::boot(['persistence' => ['driver' => 'doctrine']]);
    }
    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testRejectsAnInvalidConfiguration(array $config, string $message, bool $messenger = false): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        Harness::boot($config, false, $messenger);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string, 2?: bool}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'unknown environment' => [['environment' => 'staging'], 'environment'];
        yield 'batch size above 10' => [['workflow' => ['batch_size' => 11]], 'batch_size'];
        yield 'batch size below 1' => [['workflow' => ['batch_size' => 0]], 'batch_size'];
        yield 'negative safety margin' => [['token_safety_margin' => -1], 'token_safety_margin'];
        yield 'default page size above max' => [['workflow' => ['default_page_size' => 200, 'max_page_size' => 100]], '"default_page_size" must not be greater'];
        yield 'unknown pairing time zone' => [['pairing_time_zone' => 'Mars/Phobos'], 'Unknown time zone'];
        yield 'unknown time zone' => [['workflow' => ['business_timezone' => 'Mars/Phobos']], 'Unknown time zone'];
        yield 'invalid duration' => [['workflow' => ['settled_max_age' => 'three days']], 'Invalid ISO 8601 duration'];
        yield 'invalid stale duration' => [['workflow' => ['stale_submission_after' => 'soon']], 'Invalid ISO 8601 duration'];
        yield 'invalid unknown status duration' => [['workflow' => ['unknown_status_max_age' => 'later']], 'Invalid ISO 8601 duration'];
        yield 'invalid polling age duration' => [['workflow' => ['max_polling_age' => 'never']], 'Invalid ISO 8601 duration'];
        yield 'lock mode' => [['lock' => ['enabled' => 'sometimes']], 'lock.enabled'];
        yield 'lock ttl below 1' => [['lock' => ['ttl' => 0.5]], 'lock.ttl'];
        yield 'empty lock factory' => [['lock' => ['factory' => '']], 'lock.factory'];
        yield 'credentials and provider' => [['credentials_provider' => 'my.provider'], 'mutually exclusive'];
        yield 'custom driver without repositories' => [['persistence' => ['driver' => 'custom']], 'requires "customer_registration_repository"'];
        yield 'custom driver with one repository' => [['persistence' => ['driver' => 'custom', 'customer_registration_repository' => 'a']], 'requires "customer_registration_repository"'];
        yield 'simulated gateway in production' => [['environment' => 'production', 'gateway' => ['simulated' => true]], '"gateway.simulated" is meant for tests'];
        yield 'auto submit without messenger' => [['messenger' => ['enabled' => false, 'auto_submit' => true]], 'requires "messenger.enabled"'];
        // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
        yield 'null default page size' => [['workflow' => ['default_page_size' => 0]], 'too small'];
        yield 'null max page size' => [['workflow' => ['default_page_size' => 1, 'max_page_size' => 0]], 'too small'];
        yield 'null max submission attempts' => [['workflow' => ['max_submission_attempts' => 0]], 'too small'];
        yield 'empty client id' => [['credentials' => ['tenant-1' => ['client_id' => '', 'client_secret' => 'x']]], 'client_id'];
    }

    public function testTheEnvironmentIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('environment');

        (new TestKernel(['environment' => null]))->boot();
    }

    public function testSomeCredentialsSourceIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Configure either "credentials" or "credentials_provider"');

        Harness::boot(['credentials' => null]);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomerHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\FailureReporter;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TokenStore;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafGateway;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\EventListener\AutoSubmitListener;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRequested;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestCreated;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Logging\PsrLoggerFailureReporter;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Text\AsciiNameNormalizer;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\ExtensionConfigSnapshot;

/**
 * The services as declared by the bundle, before the container removes, inlines and resolves anything.
 */
final class ServiceDefinitionsTest extends TestCase
{
    public function testTheDedicatedHttpClientUsesTheConfiguredTimeout(): void
    {
        $container = (new TestKernel(['http_timeout' => 5.5]))->definitions();

        static::assertSame([['timeout' => 5.5]], $container->getDefinition(ServiceIds::SYMFONY_HTTP_CLIENT)->getArguments());
        $psr18 = $container->getDefinition(ServiceIds::PSR18_CLIENT)->getArguments();
        static::assertCount(1, $psr18);
        static::assertEquals(new Reference(ServiceIds::SYMFONY_HTTP_CLIENT), $psr18[0]);
    }

    public function testTheDedicatedHttpClientTimeoutDefaultsToFifteenSeconds(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertSame([['timeout' => 15.0]], $container->getDefinition(ServiceIds::SYMFONY_HTTP_CLIENT)->getArguments());
    }

    public function testTheHttpServicesLogOnTheUrssafChannel(): void
    {
        $container = (new TestKernel())->definitions();

        foreach ([ServiceIds::TOKEN_PROVIDER, ServiceIds::JSON_CLIENT, UrssafGateway::class, FailureReporter::class] as $id) {
            static::assertSame(['monolog.logger' => [['channel' => 'urssaf']]], $container->getDefinition($id)->getTags(), $id);
        }
    }

    public function testTheFailureReporterAndTheGatewayReceiveTheOptionalPackageLogger(): void
    {
        $container = (new TestKernel())->definitions();

        $reporter = $container->getDefinition(FailureReporter::class);
        static::assertSame(PsrLoggerFailureReporter::class, $reporter->getClass());
        static::assertEquals([new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)], $reporter->getArguments());
        static::assertEquals(
            new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            $container->getDefinition(UrssafGateway::class)->getArgument('$logger'),
        );
    }

    public function testTheNameNormalizerFollowsTheAvailabilityOfIntl(): void
    {
        $definition = (new TestKernel())->definitions()->getDefinition(AsciiNameNormalizer::class);

        static::assertSame([\extension_loaded('intl')], $definition->getArguments());
    }

    public function testOnlyTheHandlersAreDeclaredPublic(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertTrue($container->getDefinition(RegisterCustomerHandler::class)->isPublic());
        static::assertFalse($container->getDefinition(WorkflowPolicy::class)->isPublic());
        static::assertFalse($container->getDefinition(AsciiNameNormalizer::class)->isPublic());
        static::assertFalse($container->getDefinition(ServiceIds::JSON_CLIENT)->isPublic());
    }

    public function testTheAutoSubmitListenerListensToTheTwoCreationEvents(): void
    {
        $container = (new TestKernel(['messenger' => ['enabled' => true, 'auto_submit' => true]], false, true))->definitions();

        static::assertSame(
            [
                ['event' => CustomerRegistrationRequested::class, 'method' => 'onCustomerRegistrationRequested'],
                ['event' => PaymentRequestCreated::class, 'method' => 'onPaymentRequestCreated'],
            ],
            $container->getDefinition(AutoSubmitListener::class)->getTag('kernel.event_listener'),
        );
    }

    public function testTheServicesOfTheApplicationAreAliasedInsteadOfDuplicated(): void
    {
        $container = (new TestKernel([
            'credentials' => null,
            'credentials_provider' => 'test.credentials',
            'token_store' => ['service' => 'test.token_store'],
            'persistence' => ['driver' => 'custom', 'customer_registration_repository' => 'my.customers', 'payment_request_repository' => 'my.payment_requests'],
        ]))->definitions();

        static::assertSame('test.credentials', (string) $container->getAlias(CredentialsProvider::class));
        static::assertFalse($container->hasDefinition(CredentialsProvider::class));
        static::assertSame('test.token_store', (string) $container->getAlias(TokenStore::class));
        static::assertFalse($container->hasDefinition(TokenStore::class));
        static::assertSame('my.customers', (string) $container->getAlias(CustomerRegistrationRepository::class));
        static::assertSame('my.payment_requests', (string) $container->getAlias(PaymentRequestRepository::class));
        static::assertFalse($container->hasDefinition(CustomerRegistrationRepository::class));
        static::assertFalse($container->hasDefinition(PaymentRequestRepository::class));
    }

    public function testNothingIsPreparedForDoctrineWhenDoctrineBundleIsNotRegistered(): void
    {
        $snapshot = new ExtensionConfigSnapshot('doctrine');

        (new TestKernel(['persistence' => ['driver' => 'doctrine']]))->definitionsRecordedBy($snapshot);

        static::assertSame([], $snapshot->configs());
    }

    public function testTheInMemoryCredentialsAreBuiltFromTheConfiguration(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertSame(InMemoryCredentialsProvider::class, $container->getDefinition(CredentialsProvider::class)->getClass());
        static::assertFalse($container->hasAlias(CredentialsProvider::class));
    }
}

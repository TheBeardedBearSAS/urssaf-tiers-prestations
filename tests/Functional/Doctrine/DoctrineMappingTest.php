<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine;

use DateTimeZone;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrineCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrinePaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\UrssafStatusChange;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\Samples;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory\PaymentRequestBuilder;

/**
 * Mapping scenarios (schema, round trips, time zones): one method per scenario.
 * @mago-expect lint:too-many-methods
 */
final class DoctrineMappingTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
    }

    public function testTheMappingIsValidAndInSyncWithTheSchema(): void
    {
        $validator = new SchemaValidator($this->entityManager);

        static::assertSame([], $validator->validateMapping());
        static::assertTrue($validator->schemaInSyncWithMetadata());
    }

    public function testTablesArePrefixedAndConstraintsAndIndexesExist(): void
    {
        $manager = $this->entityManager->getConnection()->createSchemaManager();
        $tables = array_map(static fn(Table $table): string => $table->getObjectName()->getUnqualifiedName()->getValue(), $manager->introspectTables());
        sort($tables);

        static::assertSame(
            ['urssaf_tps_customer_registrations', 'urssaf_tps_outbox', 'urssaf_tps_payment_request_progress', 'urssaf_tps_payment_requests'],
            $tables,
        );
        static::assertSame(
            [
                'idx_urssaf_tps_registration_stale' => [false, ['state', 'last_attempt_at']],
                'idx_urssaf_tps_registration_state_updated' => [false, ['state', 'updated_at']],
                'idx_urssaf_tps_registration_tenant_state' => [false, ['tenant_id', 'state', 'updated_at']],
                'uniq_urssaf_tps_registration_external_reference' => [true, ['tenant_id', 'external_reference']],
                'uniq_urssaf_tps_registration_urssaf_customer_id' => [true, ['tenant_id', 'urssaf_customer_id']],
            ],
            self::indexes($manager->introspectTableByUnquotedName('urssaf_tps_customer_registrations')),
        );
        static::assertSame(
            [
                'idx_urssaf_tps_payment_request_stale' => [false, ['state', 'submission_started_at']],
                'uniq_urssaf_tps_payment_request_external_reference' => [true, ['tenant_id', 'external_reference']],
                'uniq_urssaf_tps_payment_request_invoice_number' => [true, ['tenant_id', 'invoice_number']],
            ],
            self::indexes($manager->introspectTableByUnquotedName('urssaf_tps_payment_requests')),
        );
        static::assertSame(
            [
                'idx_urssaf_tps_progress_polling' => [false, ['tenant_id', 'state', 'urssaf_status', 'last_polled_at']],
                'idx_urssaf_tps_progress_urssaf_id' => [false, ['tenant_id', 'urssaf_payment_request_id']],
            ],
            self::indexes($manager->introspectTableByUnquotedName('urssaf_tps_payment_request_progress')),
        );
    }

    /**
     * @return iterable<string, array{string}> case names of {@see Samples::registrationCase()}
     */
    public static function registrations(): iterable
    {
        yield 'born abroad, locality only' => [Samples::MINIMAL];
        foreach (RegistrationState::cases() as $state) {
            yield 'official, ' . $state->value => [$state->value];
        }
    }

    #[DataProvider('registrations')]
    public function testARegistrationIsReadBackIdentically(string $case): void
    {
        $registration = Samples::registrationCase($case);
        $repository = new DoctrineCustomerRegistrationRepository($this->entityManager);
        $repository->save($registration);
        $this->entityManager->clear();

        $read = $repository->get($registration->id());

        static::assertNotSame($registration, $read);
        static::assertEquals($registration, $read);
        static::assertSame([], $read->releaseEvents());
        static::assertSame(
            $registration->customer()->contactDetails()->address()->street()->name()?->value(),
            $read->customer()->contactDetails()->address()->street()->name()?->value(),
        );
    }

    /**
     * @return iterable<string, array{string}> case names of {@see Samples::paymentRequestCase()}
     */
    public static function paymentRequests(): iterable
    {
        yield 'official, pending' => ['official'];
        yield 'followed, with transfer and rejection' => ['followed'];
        yield 'rejected, with errors' => ['rejected'];
        yield 'polled without status' => ['polled-without-status'];
    }

    #[DataProvider('paymentRequests')]
    public function testAPaymentRequestIsReadBackIdentically(string $case): void
    {
        $request = Samples::paymentRequestCase($case);
        $repository = new DoctrinePaymentRequestRepository($this->entityManager);
        $repository->save($request);
        $this->entityManager->clear();

        $read = $repository->get($request->id());

        static::assertNotSame($request, $read);
        static::assertEquals($request, $read);
        static::assertSame([], $read->releaseEvents());
        static::assertSame($request->deposit()?->amount()->cents(), $read->deposit()?->amount()->cents());
        static::assertCount(\count($request->lines()->all()), $read->lines()->all());
        static::assertSame(self::statusCodes($request), self::statusCodes($read));
    }

    public function testAnAbsentDepositIsStoredAsNullAndReadBackAsNull(): void
    {
        $request = $this->requestWithoutDeposit();
        $repository = new DoctrinePaymentRequestRepository($this->entityManager);
        $repository->save($request);
        $this->entityManager->clear();

        static::assertNull($repository->get($request->id())->deposit());
        static::assertNull($this->entityManager->getConnection()->fetchOne('SELECT deposit FROM urssaf_tps_payment_requests'));
    }

    public function testInstantsAreStoredInUtcWhateverTheDefaultTimeZone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            $request = Samples::followedPaymentRequest();
            $registration = Samples::registration(RegistrationState::Registered);
            $requests = new DoctrinePaymentRequestRepository($this->entityManager);
            $registrations = new DoctrineCustomerRegistrationRepository($this->entityManager);
            $requests->save($request);
            $registrations->save($registration);
            $this->entityManager->clear();

            static::assertEquals($request, $requests->get($request->id()));
            static::assertEquals($registration, $registrations->get($registration->id()));
            static::assertSame(
                '2022-03-01 00:00:00',
                $this->entityManager->getConnection()->fetchOne('SELECT created_at FROM urssaf_tps_payment_requests'),
            );
            static::assertSame(
                '2026-11-11 10:00:00',
                $this->entityManager->getConnection()->fetchOne('SELECT updated_at FROM urssaf_tps_customer_registrations'),
            );
            static::assertSame(
                'Europe/Paris',
                $requests->get($request->id())->employmentPeriod()->businessTimeZone()->getName(),
            );
        } finally {
            date_default_timezone_set($previous);
        }
        static::assertSame('UTC', (new DateTimeZone('UTC'))->getName());
    }

    public function testThePollingColumnsMirrorTheTracking(): void
    {
        $request = Samples::followedPaymentRequest();
        (new DoctrinePaymentRequestRepository($this->entityManager))->save($request);

        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT state, urssaf_payment_request_id, urssaf_status, last_status_change_at, last_polled_at FROM urssaf_tps_payment_request_progress',
        );

        static::assertSame(
            [
                'state' => 'submitted',
                'urssaf_payment_request_id' => 'urssaf-official',
                'urssaf_status' => '70',
                'last_status_change_at' => '2022-03-05 00:00:00',
                'last_polled_at' => '2022-03-05 00:00:00',
            ],
            $row,
        );
    }

    /**
     * @return list<string>
     */
    private static function statusCodes(PaymentRequest $request): array
    {
        return array_map(static fn(UrssafStatusChange $change): string => $change->rawCode(), $request->tracking()->history());
    }

    /**
     * @return array<string, array{bool, list<string>}> unique flag and columns by index name
     */
    private static function indexes(Table $table): array
    {
        $indexes = [];
        foreach ($table->getIndexes() as $index) {
            $name = $index->getObjectName()->getIdentifier()->getValue();
            if (str_starts_with($name, 'idx_') || str_starts_with($name, 'uniq_')) {
                $indexes[$name] = [
                    $index->getType() === IndexType::UNIQUE,
                    array_map(static fn(IndexedColumn $column): string => $column->getColumnName()->getIdentifier()->getValue(), $index->getIndexedColumns()),
                ];
            }
        }
        ksort($indexes);

        return $indexes;
    }

    private function requestWithoutDeposit(): PaymentRequest
    {
        $request = PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z');
        $request->releaseEvents();

        return $request;
    }
}

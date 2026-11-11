<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Command;

use DateInterval;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\CreatePaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\CreatePaymentRequestHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Application\Exception\DuplicateInvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\PaymentRequestBuilder;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RegistrationLocator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PaymentRequestDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Input\DraftFixtures;

/**
 * One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class CreatePaymentRequestHandlerTest extends TestCase
{
    public function testStoresAPendingPaymentRequestForTheRegisteredCustomer(): void
    {
        $harness = self::harness(new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval('P90D'), new DateInterval('PT15M'), 'billing-x'));

        $id = self::create($harness, self::byId(), DraftFixtures::official());

        static::assertSame('id-1', $id->value());
        $stored = $harness->paymentRequests->stored('id-1');
        static::assertSame(SubmissionState::Pending, $stored->state());
        static::assertSame('tenant-1', $stored->tenantId()->value());
        static::assertSame('invoice-42', $stored->externalReference()->value());
        static::assertSame('2022-AZ-00001', $stored->invoiceNumber()->value());
        static::assertSame(Harness::urssafCustomerId(1)->value(), $stored->urssafCustomerId()->value());
        static::assertSame('1980-03-29', $stored->customerBirthDate()->toString());
        static::assertSame('billing-x', $stored->billingThirdPartyId()->value());
        static::assertSame(Harness::NOW, $stored->timeline()->createdAt()->toIso8601());
        static::assertSame(['begin', 'save:id-1', 'commit', 'publish:PaymentRequestCreated'], $harness->journal->entries());
    }

    public function testLocatesTheCustomerByExternalReference(): void
    {
        $harness = self::harness(new WorkflowPolicy());

        static::assertSame('id-1', self::create($harness, CustomerSelector::byExternalReference(ExternalReference::fromString('customer-1')), DraftFixtures::official())->value());
    }

    public function testCreatingTheSameRequestAgainReturnsTheExistingId(): void
    {
        $harness = self::harness(new WorkflowPolicy());
        self::create($harness, self::byId(), DraftFixtures::official());
        $harness->journal->clear();

        static::assertSame('id-1', self::create($harness, self::byId(), DraftFixtures::official())->value());
        static::assertSame([], $harness->journal->entries());
    }

    public function testRefusesAnInvoiceNumberUsedByAnotherRequest(): void
    {
        $harness = self::harness(new WorkflowPolicy());
        self::create($harness, self::byId(), DraftFixtures::official());
        $harness->journal->clear();
        $official = DraftFixtures::official();
        $draft = new PaymentRequestDraft(
            'invoice-43',
            $official->invoiceNumber,
            $official->invoiceDate,
            $official->employmentPeriod,
            $official->totalInclTax,
            $official->totalExclTax,
            $official->lines,
            $official->deposit,
        );

        try {
            self::create($harness, self::byId(), $draft);
            static::fail('Expected DuplicateInvoiceNumber.');
        } catch (DuplicateInvoiceNumber $exception) {
            static::assertSame(['invoice_number' => '2022-AZ-00001', 'payment_request_id' => 'id-1'], $exception->params());
        }
        static::assertSame([], $harness->journal->entries());
    }

    public function testRefusesTheSameReferenceWithAnotherInvoice(): void
    {
        $harness = self::harness(new WorkflowPolicy());
        self::create($harness, self::byId(), DraftFixtures::official());
        $harness->journal->clear();
        $official = DraftFixtures::official();
        $withoutDeposit = new PaymentRequestDraft(
            $official->externalReference,
            $official->invoiceNumber,
            $official->invoiceDate,
            $official->employmentPeriod,
            $official->totalInclTax,
            $official->totalExclTax,
            $official->lines,
            null,
        );

        $this->expectException(DuplicateInvoiceNumber::class);

        try {
            self::create($harness, self::byId(), $withoutDeposit);
        } finally {
            static::assertSame([], $harness->journal->entries());
        }
    }

    public function testSupersedesARequestRejectedByUrssaf(): void
    {
        $harness = self::harness(new WorkflowPolicy());
        self::create($harness, self::byId(), DraftFixtures::official());
        $rejected = $harness->paymentRequests->get(LocalPaymentRequestId::fromString('id-1'));
        $rejected->startSubmission(Harness::at(Harness::NOW));
        $rejected->markRejected([], 'ERR_CMPT_BLOQUE', Harness::at(Harness::NOW));
        $harness->paymentRequests->add($rejected);
        $harness->journal->clear();

        static::assertSame('id-2', self::create($harness, self::byId(), DraftFixtures::official())->value());
        static::assertSame(['id-2'], $harness->paymentRequests->ids());
        static::assertSame(SubmissionState::Pending, $harness->paymentRequests->stored('id-2')->state());
        static::assertSame(['begin', 'delete:id-1', 'save:id-2', 'commit', 'publish:PaymentRequestCreated'], $harness->journal->entries());
    }

    public function testRefusesACustomerThatIsNotRegistered(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::Blocked);

        try {
            self::create($harness, self::byId(), DraftFixtures::official());
            static::fail('Expected an invariant violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('customer_registration.not_registered', $exception->violationCode());
        }
        static::assertSame([], $harness->journal->entries());
    }

    public function testRefusesAnUnknownCustomer(): void
    {
        $this->expectException(RecordNotFound::class);

        self::create(new Harness(), self::byId(), DraftFixtures::official());
    }

    public function testValidatesTheDraftAgainstTheCurrentTime(): void
    {
        $harness = self::harness(new WorkflowPolicy());
        $harness->clock->moveTo('2022-02-01T10:00:00Z');

        try {
            self::create($harness, self::byId(), DraftFixtures::official());
            static::fail('Expected a validation failure.');
        } catch (ValidationFailed $exception) {
            static::assertFalse($exception->result()->isValid());
        }
        static::assertSame([], $harness->journal->entries());
    }

    private static function harness(WorkflowPolicy $policy): Harness
    {
        $harness = new Harness($policy);
        $harness->registration(1, RegistrationState::Registered);

        return $harness;
    }

    private static function byId(): CustomerSelector
    {
        return CustomerSelector::byRegistrationId(RegistrationId::fromString('reg-1'));
    }

    private static function create(Harness $harness, CustomerSelector $customer, PaymentRequestDraft $draft): LocalPaymentRequestId
    {
        $handler = new CreatePaymentRequestHandler(
            $harness->paymentRequests,
            new RegistrationLocator($harness->registrations),
            $harness->ids,
            new PaymentRequestBuilder($harness->clock, $harness->policy),
            $harness->unitOfWork,
        );

        return $handler(new CreatePaymentRequest(Harness::tenant(), $customer, $draft));
    }
}

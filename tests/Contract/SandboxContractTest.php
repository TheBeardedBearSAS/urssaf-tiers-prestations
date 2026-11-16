<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Contract;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\ApiErrorCode;

/**
 * Live checks against the URSSAF sandbox. Opt-in, never part of coverage or mutation gates.
 *
 * Optional environment: URSSAF_SANDBOX_CUSTOMER_ID (paired sandbox customer), URSSAF_SANDBOX_CUSTOMER_BIRTH_DATE
 * (Y-m-d, default 1980-03-29), URSSAF_SANDBOX_INTERVENANT (SAP/SIR id) and URSSAF_SANDBOX_BILLING_ID
 * (default "menage.fr") for the payment request test.
 */
#[Group('contract')]
final class SandboxContractTest extends TestCase
{
    private const UNKNOWN_CUSTOMER_ID = '00000000-0000-4000-8000-000000000000';

    private SandboxGateway $sandbox;

    protected function setUp(): void
    {
        $sandbox = SandboxGateway::fromEnvironment();
        if (!$sandbox instanceof SandboxGateway) {
            static::markTestSkipped(
                'URSSAF sandbox contract tests are opt-in: set URSSAF_SANDBOX_CLIENT_ID and URSSAF_SANDBOX_CLIENT_SECRET (see `make test-contract`).',
            );
        }

        $this->sandbox = $sandbox;
    }

    public function testObtainsAnAccessToken(): void
    {
        $token = $this->sandbox->tokenProvider->accessToken($this->sandbox->credentials, Operation::GetCustomerStatus);

        static::assertNotSame('', $token);
    }

    public function testStatusOfAnUnknownCustomerIsRejected(): void
    {
        try {
            $status = $this->sandbox->gateway->transmissionStatus(
                $this->sandbox->tenantId,
                UrssafCustomerId::fromString(self::UNKNOWN_CUSTOMER_ID),
            );
        } catch (UrssafRequestRejected $rejected) {
            static::assertSame(Operation::GetCustomerStatus, $rejected->operation());
            static::assertAllCodesAreCatalogued($rejected->errors());
            static::assertTrue(
                $rejected->hasErrorCode(ApiErrorCode::NoActiveRegistration->value)
                || $rejected->hasErrorCode(ApiErrorCode::UnknownCustomer->value),
                'Unexpected rejection: ' . $rejected->getMessage(),
            );

            return;
        }

        // Documented fallback: some sandboxes answer with a non allowed status instead of an error.
        static::assertFalse($status->isTransmissionAllowed(), 'An unknown customer must not be allowed to transmit.');
    }

    public function testSearchByUnknownInvoiceNumberReturnsNothing(): void
    {
        $snapshots = $this->sandbox->gateway->search(
            $this->sandbox->tenantId,
            PaymentRequestSearch::byInvoiceNumbers(InvoiceNumber::fromString(SandboxData::uniqueInvoiceNumber())),
        );

        static::assertSame([], $snapshots);
    }

    public function testRegistersAFictitiousCustomer(): void
    {
        $result = $this->tryRegister();

        if ($result instanceof UrssafCustomerId) {
            static::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $result->value());

            return;
        }

        static::assertGreaterThan(0, $result->errors()->count(), 'A rejection carries at least one error.');
        static::assertAllCodesAreCatalogued($result->errors());
    }

    public function testDateEncodingIsAcceptedByRegistration(): void
    {
        $result = $this->tryRegister();

        static::assertNoDateValidationError($result instanceof UrssafRequestRejected ? $result->errors() : ApiErrors::none());
    }

    public function testSubmitsAPaymentRequestThenFindsItByInvoiceNumber(): void
    {
        $customerId = SandboxGateway::env('URSSAF_SANDBOX_CUSTOMER_ID');
        $intervenant = SandboxGateway::env('URSSAF_SANDBOX_INTERVENANT');
        if ($customerId === null || $intervenant === null) {
            static::markTestSkipped('Set URSSAF_SANDBOX_CUSTOMER_ID (paired customer) and URSSAF_SANDBOX_INTERVENANT (SAP/SIR id) to submit a payment request.');
        }

        $invoiceNumber = SandboxData::uniqueInvoiceNumber();
        $request = SandboxData::paymentRequest($this->sandbox->tenantId, UrssafCustomerId::fromString($customerId), $intervenant, $invoiceNumber);

        $outcomes = $this->sandbox->gateway->submit($this->sandbox->tenantId, $request);

        static::assertCount(1, $outcomes);
        $outcome = $outcomes[0];
        static::assertSame($invoiceNumber, $outcome->invoiceNumber());
        static::assertNoDateValidationError($outcome->errors());

        if (!$outcome->isAccepted()) {
            // Documented business rejection (e.g. pairing date, billing id, intervenant): codes must be known.
            static::assertGreaterThan(0, $outcome->errors()->count());
            static::assertAllCodesAreCatalogued($outcome->errors());

            return;
        }

        $submittedId = $outcome->paymentRequestId();
        static::assertNotNull($submittedId);

        $snapshots = $this->sandbox->gateway->search(
            $this->sandbox->tenantId,
            PaymentRequestSearch::byInvoiceNumbers(InvoiceNumber::fromString($invoiceNumber)),
        );

        static::assertCount(1, $snapshots);
        static::assertTrue($submittedId->equals($snapshots[0]->paymentRequestId()));
        static::assertSame($invoiceNumber, $snapshots[0]->invoiceNumber());
        static::assertNotSame('', $snapshots[0]->status()->code());
    }

    private function tryRegister(): UrssafCustomerId|UrssafRequestRejected
    {
        try {
            return $this->sandbox->gateway->register(
                $this->sandbox->tenantId,
                SandboxData::registration($this->sandbox->tenantId),
            );
        } catch (UrssafRequestRejected $rejected) {
            return $rejected;
        }
    }

    private static function assertAllCodesAreCatalogued(ApiErrors $errors): void
    {
        foreach ($errors->codes() as $code) {
            static::assertNotNull(ApiErrorCode::fromCode($code), \sprintf('Error code "%s" is not in the ApiErrorCode catalogue.', $code));
        }
    }

    private static function assertNoDateValidationError(ApiErrors $errors): void
    {
        $offending = [];
        foreach ($errors->all() as $error) {
            if (!$error->hasCode(ApiErrorCode::ValidationError->value)) {
                continue;
            }

            if (preg_match('/\bdate/i', $error->message() . ' ' . $error->description()) === 1) {
                $offending[] = $error->message();
            }
        }

        static::assertSame([], $offending, 'Date encoding rejected by URSSAF.');
    }
}

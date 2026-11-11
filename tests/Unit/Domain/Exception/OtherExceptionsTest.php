<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Exception;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

final class OtherExceptionsTest extends TestCase
{
    public function testInvariantViolation(): void
    {
        $exception = InvariantViolation::because('payment_request.deposit_too_high', 'Deposit too high.', ['max' => 50]);

        static::assertSame('payment_request.deposit_too_high', $exception->violationCode());
        static::assertSame('Deposit too high.', $exception->getMessage());
        static::assertSame(['max' => 50], $exception->params());
        static::assertSame([], InvariantViolation::because('a.b', 'm')->params());
    }

    public function testInvalidStateTransition(): void
    {
        $exception = InvalidStateTransition::between('payment_request', 'paid', 'submitted');

        static::assertSame('payment_request.invalid_state_transition', $exception->violationCode());
        static::assertSame('Invalid payment_request state transition from "paid" to "submitted".', $exception->getMessage());
        static::assertSame('payment_request', $exception->aggregate());
        static::assertSame('paid', $exception->from());
        static::assertSame('submitted', $exception->to());
        static::assertSame(['aggregate' => 'payment_request', 'from' => 'paid', 'to' => 'submitted'], $exception->params());
    }

    public function testRecordNotFound(): void
    {
        $exception = RecordNotFound::withIdentifier('customer_registration', 'abc-1');

        static::assertSame('customer_registration.not_found', $exception->violationCode());
        static::assertSame('No customer_registration found for identifier "abc-1".', $exception->getMessage());
        static::assertSame('customer_registration', $exception->recordType());
        static::assertSame('abc-1', $exception->identifier());
        static::assertSame(['record_type' => 'customer_registration', 'identifier' => 'abc-1'], $exception->params());
    }

    public function testValidationFailedCarriesTheResult(): void
    {
        $result = ValidationResult::of(
            Violation::error('a', 'a.invalid', 'A.'),
            Violation::warning('b', 'b.odd', 'B.'),
            Violation::error('c', 'c.invalid', 'C.'),
        );

        $exception = ValidationFailed::withResult($result);

        static::assertSame($result, $exception->result());
        static::assertSame('validation.failed', $exception->violationCode());
        static::assertSame('Validation failed with 2 error(s).', $exception->getMessage());
        static::assertSame(['error_count' => 2], $exception->params());
    }
}

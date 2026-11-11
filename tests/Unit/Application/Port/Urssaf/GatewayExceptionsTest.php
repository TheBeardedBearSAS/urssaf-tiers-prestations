<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RefusalReason;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;

final class GatewayExceptionsTest extends TestCase
{
    public function testRequestRejected(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERREUR_VALIDATION', 'm', 'd'), ApiError::of('ERR_CONTACT', 'm', 'd'));

        $exception = UrssafRequestRejected::withErrors(Operation::RegisterCustomer, 400, $errors);

        static::assertSame('URSSAF method 010 rejected the request (HTTP 400): ERREUR_VALIDATION, ERR_CONTACT.', $exception->getMessage());
        static::assertSame(Operation::RegisterCustomer, $exception->operation());
        static::assertSame(400, $exception->httpStatus());
        static::assertSame(400, $exception->getCode());
        static::assertSame($errors, $exception->errors());
        static::assertTrue($exception->hasErrorCode('ERR_CONTACT'));
        static::assertFalse($exception->hasErrorCode('ERR_COMPTE_BLOQUE'));
        static::assertSame(RefusalReason::Generic, $exception->reason());
        static::assertFalse($exception->isNoActiveRegistration());
        static::assertFalse($exception->isAlreadyRegistered());
    }

    public function testRequestRejectedWithAReason(): void
    {
        $noRegistration = UrssafRequestRejected::withErrors(Operation::GetCustomerStatus, 400, ApiErrors::none(), RefusalReason::NoActiveRegistration);
        $alreadyRegistered = UrssafRequestRejected::withErrors(Operation::RegisterCustomer, 400, ApiErrors::none(), RefusalReason::AlreadyRegistered);

        static::assertSame(RefusalReason::NoActiveRegistration, $noRegistration->reason());
        static::assertTrue($noRegistration->isNoActiveRegistration());
        static::assertFalse($noRegistration->isAlreadyRegistered());
        static::assertTrue($alreadyRegistered->isAlreadyRegistered());
        static::assertFalse($alreadyRegistered->isNoActiveRegistration());
    }

    public function testRequestRejectedWithoutErrors(): void
    {
        $exception = UrssafRequestRejected::withErrors(Operation::SearchPaymentRequests, 400, ApiErrors::none());

        static::assertSame('URSSAF method 070 rejected the request (HTTP 400): no error code.', $exception->getMessage());
    }

    public function testAuthenticationFailed(): void
    {
        $exception = UrssafAuthenticationFailed::withStatus(401);

        static::assertSame('URSSAF authentication or authorisation failed (HTTP 401).', $exception->getMessage());
        static::assertSame(401, $exception->httpStatus());
        static::assertSame(401, $exception->getCode());
    }

    public function testUnavailableWithStatus(): void
    {
        $exception = UrssafUnavailable::withStatus(Operation::SubmitPaymentRequests, 429, 30);

        static::assertSame('URSSAF method 050 is temporarily unavailable (HTTP 429).', $exception->getMessage());
        static::assertSame(Operation::SubmitPaymentRequests, $exception->operation());
        static::assertSame(429, $exception->httpStatus());
        static::assertSame(429, $exception->getCode());
        static::assertSame(30, $exception->retryAfterSeconds());
        static::assertTrue($exception->isQuotaExceeded());
        static::assertNull($exception->getPrevious());
        static::assertFalse($exception->mayHaveReachedUrssaf());

        $serverError = UrssafUnavailable::withStatus(Operation::SubmitPaymentRequests, 500, null);
        static::assertFalse($serverError->isQuotaExceeded());
        static::assertNull($serverError->retryAfterSeconds());
        static::assertTrue($serverError->mayHaveReachedUrssaf());
        static::assertFalse(UrssafUnavailable::withStatus(Operation::SubmitPaymentRequests, 499, null)->mayHaveReachedUrssaf());
    }

    public function testUnavailableWhileAuthenticating(): void
    {
        $previous = new RuntimeException('Connection refused');

        $exception = UrssafUnavailable::whileAuthenticating(Operation::RegisterCustomer, 503, 12, $previous);

        static::assertSame('URSSAF authentication for method 010 is temporarily unavailable.', $exception->getMessage());
        static::assertSame(Operation::RegisterCustomer, $exception->operation());
        static::assertSame(503, $exception->httpStatus());
        static::assertSame(503, $exception->getCode());
        static::assertSame(12, $exception->retryAfterSeconds());
        static::assertSame($previous, $exception->getPrevious());
        static::assertFalse($exception->mayHaveReachedUrssaf());
        static::assertSame(0, UrssafUnavailable::whileAuthenticating(Operation::RegisterCustomer, null, null, null)->getCode());
    }

    public function testUnavailableDueToTransportError(): void
    {
        $previous = new RuntimeException('Connection refused');

        $exception = UrssafUnavailable::dueToTransportError(Operation::GetCustomerStatus, $previous);

        static::assertSame('URSSAF method 020 could not be reached.', $exception->getMessage());
        static::assertSame(Operation::GetCustomerStatus, $exception->operation());
        static::assertNull($exception->httpStatus());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->retryAfterSeconds());
        static::assertFalse($exception->isQuotaExceeded());
        static::assertSame($previous, $exception->getPrevious());
        static::assertTrue($exception->mayHaveReachedUrssaf());
    }
}

<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\BillingThirdPartyId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LineComplement;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;

final class IdentifiersTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<LocalPaymentRequestId|BillingThirdPartyId|LineComplement>, string, positive-int}>
     */
    public static function provideFactories(): iterable
    {
        yield 'local payment request id' => [LocalPaymentRequestId::class, 'local_payment_request_id', 64];
        yield 'billing third party id' => [BillingThirdPartyId::class, 'billing_third_party_id', 50];
        yield 'line complement' => [LineComplement::class, 'line_complement', 255];
    }

    /**
     * @param class-string<LocalPaymentRequestId|BillingThirdPartyId|LineComplement> $class
     * @param positive-int                                                           $maxLength
     */
    #[DataProvider('provideFactories')]
    public function testValuesAreKeptVerbatim(string $class, string $subject, int $maxLength): void
    {
        foreach (['a', ' padded value ', str_repeat('x', $maxLength), str_repeat('é', $maxLength)] as $value) {
            $identifier = $class::fromString($value);
            static::assertSame($value, $identifier->value());
            static::assertSame($value, (string) $identifier);
        }
        static::assertSame($class::MAX_LENGTH, $maxLength);
    }

    /**
     * @param class-string<LocalPaymentRequestId|BillingThirdPartyId|LineComplement> $class
     * @param positive-int                                                           $maxLength
     */
    #[DataProvider('provideFactories')]
    public function testBlankValuesAreRejected(string $class, string $subject, int $maxLength): void
    {
        foreach (['', ' ', "\t\n"] as $value) {
            try {
                $class::fromString($value);
                static::fail('Expected exception');
            } catch (InvalidValue $exception) {
                static::assertSame($subject . '.empty', $exception->violationCode());
            }
        }
    }

    /**
     * @param class-string<LocalPaymentRequestId|BillingThirdPartyId|LineComplement> $class
     * @param positive-int                                                           $maxLength
     */
    #[DataProvider('provideFactories')]
    public function testTooLongValuesAreRejected(string $class, string $subject, int $maxLength): void
    {
        try {
            $class::fromString(str_repeat('x', $maxLength + 1));
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($subject . '.too_long', $exception->violationCode());
            static::assertSame(['max_length' => $maxLength], $exception->params());
        }
    }

    public function testEquality(): void
    {
        static::assertTrue(LocalPaymentRequestId::fromString('a')->equals(LocalPaymentRequestId::fromString('a')));
        static::assertFalse(LocalPaymentRequestId::fromString('a')->equals(LocalPaymentRequestId::fromString('A')));
        static::assertTrue(BillingThirdPartyId::fromString('menage.fr')->equals(BillingThirdPartyId::fromString('menage.fr')));
        static::assertFalse(BillingThirdPartyId::fromString('menage.fr')->equals(BillingThirdPartyId::fromString('other')));
        static::assertTrue(LineComplement::fromString('x')->equals(LineComplement::fromString('x')));
        static::assertFalse(LineComplement::fromString('x')->equals(LineComplement::fromString('y')));
    }
}

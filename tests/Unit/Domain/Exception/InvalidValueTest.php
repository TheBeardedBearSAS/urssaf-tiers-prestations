<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Severity;

final class InvalidValueTest extends TestCase
{
    /**
     * @return iterable<string, array{InvalidValue, string, string, array<string, scalar>}>
     */
    public static function provideNamedConstructors(): iterable
    {
        yield 'because' => [
            InvalidValue::because('foo.bar', 'Custom message.', ['limit' => 3]),
            'foo.bar',
            'Custom message.',
            ['limit' => 3],
        ];
        yield 'because without params' => [InvalidValue::because('foo.bar', 'Custom message.'), 'foo.bar', 'Custom message.', []];
        yield 'empty' => [InvalidValue::empty('tenant_id'), 'tenant_id.empty', 'Invalid tenant_id: the value must not be empty.', []];
        yield 'too long' => [
            InvalidValue::tooLong('tenant_id', 64),
            'tenant_id.too_long',
            'Invalid tenant_id: the value must not exceed 64 characters.',
            ['max_length' => 64],
        ];
        yield 'invalid format' => [
            InvalidValue::invalidFormat('country_code', '5 digits'),
            'country_code.invalid_format',
            'Invalid country_code: the value does not match the expected format (5 digits).',
            ['expected_format' => '5 digits'],
        ];
        yield 'negative' => [InvalidValue::negative('money'), 'money.negative', 'Invalid money: the value must not be negative.', []];
        yield 'not positive' => [
            InvalidValue::notPositive('quantity'),
            'quantity.not_positive',
            'Invalid quantity: the value must be strictly positive.',
            [],
        ];
        yield 'too many decimals' => [
            InvalidValue::tooManyDecimals('money', 2),
            'money.too_many_decimals',
            'Invalid money: the value must not have more than 2 decimals.',
            ['max_decimals' => 2],
        ];
        yield 'too large' => [
            InvalidValue::tooLarge('money'),
            'money.too_large',
            'Invalid money: the value exceeds the supported range.',
            [],
        ];
    }

    /**
     * @param array<string, scalar> $params
     */
    #[DataProvider('provideNamedConstructors')]
    public function testNamedConstructors(InvalidValue $exception, string $code, string $message, array $params): void
    {
        static::assertSame($code, $exception->violationCode());
        static::assertSame($message, $exception->getMessage());
        static::assertSame($params, $exception->params());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->getPrevious());
    }

    public function testItConvertsToAnErrorViolation(): void
    {
        $violation = InvalidValue::tooLong('invoice_number', 50)->toViolation('lines[1].invoiceNumber');

        static::assertSame('lines[1].invoiceNumber', $violation->path());
        static::assertSame('invoice_number.too_long', $violation->code());
        static::assertSame('Invalid invoice_number: the value must not exceed 50 characters.', $violation->message());
        static::assertSame(Severity::Error, $violation->severity());
        static::assertSame(['max_length' => 50], $violation->params());
    }
}

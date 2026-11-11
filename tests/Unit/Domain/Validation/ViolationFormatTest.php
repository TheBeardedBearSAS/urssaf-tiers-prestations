<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

final class ViolationFormatTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidPaths(): iterable
    {
        yield 'root' => [''];
        yield 'single' => ['quantity'];
        yield 'underscore start' => ['_x'];
        yield 'digits inside' => ['line2'];
        yield 'nested' => ['address.postalCode'];
        yield 'indexed' => ['lines[2].quantity'];
        yield 'multi indexed' => ['grid[0][12].cell'];
        yield 'indexed last' => ['lines[10]'];
    }

    #[DataProvider('provideValidPaths')]
    public function testValidPaths(string $path): void
    {
        static::assertSame($path, Violation::error($path, 'a.b', 'm')->path());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidPaths(): iterable
    {
        yield 'leading dot' => ['.quantity'];
        yield 'trailing dot' => ['quantity.'];
        yield 'leading digit' => ['2lines'];
        yield 'leading index' => ['[2].quantity'];
        yield 'prefix junk' => ['-quantity'];
        yield 'suffix junk' => ['quantity-'];
        yield 'space' => ['address postalCode'];
        yield 'trailing newline' => ["quantity\n"];
        yield 'non numeric index' => ['lines[a]'];
    }

    #[DataProvider('provideInvalidPaths')]
    public function testInvalidPathsAreRejected(string $path): void
    {
        try {
            Violation::error($path, 'a.b', 'm');
            static::fail('Expected exception');
        } catch (InvariantViolation $exception) {
            static::assertSame('violation.invalid_path', $exception->violationCode());
            static::assertSame('A violation path must be a dotted path such as "address.postalCode" or "lines[2].quantity".', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidCodes(): iterable
    {
        yield 'two segments' => ['birth_name.invalid_format'];
        yield 'three segments' => ['payment_request.line.too_many'];
        yield 'digits' => ['line2.r3'];
    }

    #[DataProvider('provideValidCodes')]
    public function testValidCodes(string $code): void
    {
        static::assertSame($code, Violation::warning('a', $code, 'm')->code());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'single segment' => ['invalid'];
        yield 'upper case' => ['Birth.invalid'];
        yield 'leading digit' => ['1a.b'];
        yield 'segment leading digit' => ['a.1b'];
        yield 'prefix junk' => ['-a.b'];
        yield 'suffix junk' => ['a.b-'];
        yield 'trailing dot' => ['a.b.'];
        yield 'trailing newline' => ["a.b\n"];
    }

    #[DataProvider('provideInvalidCodes')]
    public function testInvalidCodesAreRejected(string $code): void
    {
        try {
            Violation::error('a', $code, 'm');
            static::fail('Expected exception');
        } catch (InvariantViolation $exception) {
            static::assertSame('violation.invalid_code', $exception->violationCode());
            static::assertSame('A violation code must be a dotted snake_case identifier such as "subject.reason".', $exception->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Validation;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Severity;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

final class ViolationTest extends TestCase
{
    public function testError(): void
    {
        $violation = Violation::error('address.postalCode', 'postal_code.invalid_format', 'Invalid.', ['length' => 5]);

        static::assertSame('address.postalCode', $violation->path());
        static::assertSame('postal_code.invalid_format', $violation->code());
        static::assertSame('Invalid.', $violation->message());
        static::assertSame(Severity::Error, $violation->severity());
        static::assertSame(['length' => 5], $violation->params());
        static::assertTrue($violation->isError());
    }

    public function testWarning(): void
    {
        $violation = Violation::warning('', 'customer.usage_name_redundant', 'Redundant.');

        static::assertSame('', $violation->path());
        static::assertSame(Severity::Warning, $violation->severity());
        static::assertSame([], $violation->params());
        static::assertFalse($violation->isError());
        static::assertSame([], Violation::error('a', 'a.b', 'm')->params());
    }

    public function testWithPathPrefix(): void
    {
        $violation = Violation::warning('quantity', 'quantity.odd', 'Odd.', ['x' => 1]);

        $prefixed = $violation->withPathPrefix('lines[2]');

        static::assertSame('lines[2].quantity', $prefixed->path());
        static::assertSame('quantity.odd', $prefixed->code());
        static::assertSame('Odd.', $prefixed->message());
        static::assertSame(Severity::Warning, $prefixed->severity());
        static::assertSame(['x' => 1], $prefixed->params());
        static::assertSame('quantity', $violation->path());
    }

    public function testWithPathPrefixOnRootViolation(): void
    {
        static::assertSame('address', Violation::error('', 'a.b', 'm')->withPathPrefix('address')->path());
    }

    public function testWithEmptyPathPrefixReturnsTheSameViolation(): void
    {
        $violation = Violation::error('a', 'a.b', 'm');

        static::assertSame($violation, $violation->withPathPrefix(''));
    }

    public function testWithInvalidPathPrefixIsRejected(): void
    {
        $this->expectException(InvariantViolation::class);

        Violation::error('a', 'a.b', 'm')->withPathPrefix('lines[');
    }

    public function testWithInvalidPathPrefixIsRejectedWithAPathViolationCode(): void
    {
        try {
            Violation::error('a', 'a.b', 'm')->withPathPrefix('lines[');
            static::fail('Expected exception');
        } catch (InvariantViolation $exception) {
            static::assertSame('violation.invalid_path', $exception->violationCode());
        }
    }

    public function testEquals(): void
    {
        $violation = Violation::error('a', 'a.b', 'm', ['k' => 1]);

        static::assertTrue($violation->equals(Violation::error('a', 'a.b', 'm', ['k' => 1])));
        static::assertFalse($violation->equals(Violation::error('b', 'a.b', 'm', ['k' => 1])));
        static::assertFalse($violation->equals(Violation::error('a', 'a.c', 'm', ['k' => 1])));
        static::assertFalse($violation->equals(Violation::error('a', 'a.b', 'n', ['k' => 1])));
        static::assertFalse($violation->equals(Violation::warning('a', 'a.b', 'm', ['k' => 1])));
        static::assertFalse($violation->equals(Violation::error('a', 'a.b', 'm', ['k' => '1'])));
    }
}

<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\ViolationCollector;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

final class ViolationCollectorTest extends TestCase
{
    public function testRequired(): void
    {
        $collector = new ViolationCollector();

        static::assertSame('69001', $collector->required('postalCode', 'postal_code', '69001', PostalCode::fromString(...))?->value());
        static::assertNull($collector->required('postalCode', 'postal_code', null, PostalCode::fromString(...)));
        static::assertNull($collector->required('postalCode', 'postal_code', '', PostalCode::fromString(...)));
        static::assertNull($collector->required('postalCode', 'postal_code', '6900', PostalCode::fromString(...)));

        $all = $collector->result()->all();
        static::assertSame(
            ['postalCode: postal_code.required', 'postalCode: postal_code.required', 'postalCode: postal_code.invalid_format'],
            ViolationSummary::of($collector->result()),
        );
        static::assertSame('The postal code is required.', $all[0]->message());
        static::assertSame(['expected_format' => '5 digits'], $all[2]->params());
    }

    public function testOptional(): void
    {
        $collector = new ViolationCollector();

        static::assertSame('69001', $collector->optional('postalCode', '69001', PostalCode::fromString(...))?->value());
        static::assertNull($collector->optional('postalCode', null, PostalCode::fromString(...)));
        static::assertNull($collector->optional('postalCode', '', PostalCode::fromString(...)));
        static::assertTrue($collector->result()->isValid());
        static::assertNull($collector->optional('postalCode', 'x', PostalCode::fromString(...)));
        static::assertSame(['postalCode: postal_code.invalid_format'], ViolationSummary::of($collector->result()));
    }

    public function testAttemptAddAndMerge(): void
    {
        $collector = new ViolationCollector();

        static::assertSame('69001', $collector->attempt('a', static fn(): PostalCode => PostalCode::fromString('69001'))?->value());
        static::assertNull($collector->attempt('b', static fn(): PostalCode => PostalCode::fromString('1')));
        $collector->add(Violation::warning('c', 'c.warning', 'Warning.'));
        $collector->merge(ValidationResult::of(Violation::error('d', 'd.error', 'Error.')), 'parent');
        $collector->merge(ValidationResult::of(Violation::error('', 'e.error', 'Error.')), 'root');

        static::assertSame(
            ['b: postal_code.invalid_format', 'c: c.warning', 'parent.d: d.error', 'root: e.error'],
            ViolationSummary::of($collector->result()),
        );
    }
}

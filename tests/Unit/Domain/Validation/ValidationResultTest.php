<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Validation;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Severity;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

final class ValidationResultTest extends TestCase
{
    public function testValidResultIsEmpty(): void
    {
        $result = ValidationResult::valid();

        static::assertTrue($result->isValid());
        static::assertSame([], $result->all());
        static::assertSame([], $result->errors());
        static::assertSame([], $result->warnings());
        $result->throwIfInvalid();
    }

    public function testOfNormalisesKeysToAList(): void
    {
        $violation = Violation::error('a', 'a.b', 'm');

        static::assertSame([$violation], ValidationResult::of(...['named' => $violation])->all());
    }

    public function testErrorsAndWarningsAreSplitAndReindexed(): void
    {
        $warning1 = Violation::warning('w1', 'w.one', 'm');
        $error1 = Violation::error('e1', 'e.one', 'm');
        $warning2 = Violation::warning('w2', 'w.two', 'm');
        $error2 = Violation::error('e2', 'e.two', 'm');

        $result = ValidationResult::of($warning1, $error1, $warning2, $error2);

        static::assertSame([$warning1, $error1, $warning2, $error2], $result->all());
        static::assertSame([$error1, $error2], $result->errors());
        static::assertSame([$warning1, $warning2], $result->warnings());
        static::assertFalse($result->isValid());
    }

    public function testWarningsDoNotInvalidate(): void
    {
        $result = ValidationResult::of(Violation::warning('w', 'w.one', 'm'));

        static::assertTrue($result->isValid());
        $result->throwIfInvalid();
    }

    public function testWithIsImmutable(): void
    {
        $first = Violation::error('a', 'a.b', 'm');
        $second = Violation::warning('b', 'b.c', 'm');
        $initial = ValidationResult::of($first);

        $result = $initial->with($second);

        static::assertSame([$first, $second], $result->all());
        static::assertSame([$first], $initial->all());
    }

    public function testMergeKeepsOrder(): void
    {
        $a = Violation::error('a', 'a.b', 'm');
        $b = Violation::error('b', 'b.c', 'm');
        $c = Violation::warning('c', 'c.d', 'm');
        $left = ValidationResult::of($a);
        $right = ValidationResult::of($b, $c);

        static::assertSame([$a, $b, $c], $left->merge($right)->all());
        static::assertSame([$a], $left->all());
        static::assertSame([$b, $c], $right->all());
    }

    public function testWithPathPrefix(): void
    {
        $result = ValidationResult::of(
            Violation::error('quantity', 'quantity.not_positive', 'm'),
            Violation::warning('', 'line.odd', 'm'),
        )->withPathPrefix('lines[3]');

        static::assertSame(['lines[3].quantity', 'lines[3]'], array_map(
            static fn(Violation $violation): string => $violation->path(),
            $result->all(),
        ));
        static::assertSame(Severity::Warning, $result->all()[1]->severity());
    }

    public function testThrowIfInvalid(): void
    {
        $result = ValidationResult::of(Violation::error('a', 'a.b', 'm'));

        try {
            $result->throwIfInvalid();
            static::fail('Expected exception');
        } catch (ValidationFailed $exception) {
            static::assertSame($result, $exception->result());
        }
    }
}

<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Address;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\AddressText;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

final class AddressTextTest extends TestCase
{
    /**
     * @return iterable<string, array{string, positive-int}>
     */
    public static function provideFactories(): iterable
    {
        yield 'street name' => ['street_name', 28];
        yield 'complement' => ['address_complement', 38];
        yield 'locality' => ['locality', 38];
    }

    /**
     * @param positive-int $maxLength
     */
    #[DataProvider('provideFactories')]
    public function testValidValues(string $subject, int $maxLength): void
    {
        foreach (['du Soleil', 'Bâtiment A, 3e étage', 'x', str_repeat('é', $maxLength)] as $value) {
            $text = self::create($subject, $value);
            static::assertSame($value, $text->value());
            static::assertSame(['value' => '***'], $text->__debugInfo());
            static::assertTrue($text->equals(self::create($subject, $value)));
            static::assertFalse($text->equals(self::create($subject, 'other')));
        }
    }

    /**
     * @param positive-int $maxLength
     */
    #[DataProvider('provideFactories')]
    public function testInvalidValues(string $subject, int $maxLength): void
    {
        $cases = [
            '' => 'empty',
            '  ' => 'empty',
            "du\tSoleil" => 'invalid_characters',
            "du Soleil\n" => 'invalid_characters',
            "\ndu Soleil" => 'invalid_characters',
            "Soleil\xC3" => 'invalid_characters',
            str_repeat('é', $maxLength + 1) => 'too_long',
        ];
        foreach ($cases as $value => $reason) {
            try {
                self::create($subject, $value);
                static::fail('Expected an invalid value.');
            } catch (InvalidValue $exception) {
                static::assertSame($subject . '.' . $reason, $exception->violationCode());
            }
        }
    }

    public function testMessages(): void
    {
        static::assertSame(['max_length' => 38], self::exceptionOf(static fn(): AddressText => AddressText::locality(str_repeat('a', 39)))->params());

        $this->expectExceptionObject(InvalidValue::because(
            'address_complement.invalid_characters',
            'Invalid address_complement: control characters are not allowed.',
        ));

        AddressText::complement("a\x00b");
    }

    /**
     * @param Closure(): mixed $action
     *
     * @param-immediately-invoked-callable $action
     */
    private static function exceptionOf(Closure $action): InvalidValue
    {
        try {
            $action();
        } catch (InvalidValue $exception) {
            return $exception;
        }
        static::fail('Expected an invalid value.');
    }

    private static function create(string $subject, string $value): AddressText
    {
        return match ($subject) {
            'street_name' => AddressText::streetName($value),
            'address_complement' => AddressText::complement($value),
            default => AddressText::locality($value),
        };
    }
}

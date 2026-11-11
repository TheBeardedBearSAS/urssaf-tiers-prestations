<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\IntervenantId;

final class IntervenantIdTest extends TestCase
{
    public function testIntervenantIds(): void
    {
        $nova = IntervenantId::fromString('SAP123456789');
        $siret = IntervenantId::fromString('SIR12345678901234');

        static::assertTrue($nova->isNova());
        static::assertFalse($nova->isSiret());
        static::assertSame('SAP123456789', $nova->value());
        static::assertSame('SAP123456789', (string) $nova);
        static::assertTrue($siret->isSiret());
        static::assertFalse($siret->isNova());
        static::assertSame('SIR12345678901234', $siret->value());
    }

    public function testEmptyIntervenantId(): void
    {
        $this->expectExceptionObject(InvalidValue::empty('intervenant_id'));

        IntervenantId::fromString('');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidIntervenantIds(): iterable
    {
        yield 'nova too short' => ['SAP12345678'];
        yield 'nova too long' => ['SAP1234567890'];
        yield 'siret too short' => ['SIR1234567890123'];
        yield 'siret too long' => ['SIR123456789012345'];
        yield 'nova with siret length' => ['SAP12345678901234'];
        yield 'siret with nova length' => ['SIR123456789'];
        yield 'lower case' => ['sap123456789'];
        yield 'bare siren' => ['123456789'];
        yield 'nova prefix' => ['XSAP123456789'];
        yield 'siret prefix' => ['XSIR12345678901234'];
        yield 'nova trailing newline' => ["SAP123456789\n"];
        yield 'siret trailing newline' => ["SIR12345678901234\n"];
    }

    #[DataProvider('provideInvalidIntervenantIds')]
    public function testInvalidIntervenantIds(string $id): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('intervenant_id', 'SAP + 9 digits or SIR + 14 digits'));

        IntervenantId::fromString($id);
    }

    public function testIntervenantEquality(): void
    {
        static::assertTrue(IntervenantId::fromString('SAP123456789')->equals(IntervenantId::fromString('SAP123456789')));
        static::assertFalse(IntervenantId::fromString('SAP123456789')->equals(IntervenantId::fromString('SAP123456780')));
    }
}

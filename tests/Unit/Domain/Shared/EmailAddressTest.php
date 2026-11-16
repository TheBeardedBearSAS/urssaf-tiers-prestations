<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmailAddress;

final class EmailAddressTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidEmails(): iterable
    {
        yield 'official example' => ['jeanne.durand@contact.fr'];
        yield 'special chars' => ['a%b+c_d-e@x-y.example.com'];
        yield 'upper case' => ['Jeanne@Contact.FR'];
        yield 'two char tld' => ['a@b.fr'];
    }

    #[DataProvider('provideValidEmails')]
    public function testValidEmails(string $email): void
    {
        $address = EmailAddress::fromString($email);

        static::assertSame($email, $address->value());
        static::assertSame(['value' => '***'], $address->__debugInfo());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'no at' => ['jeanne.contact.fr'];
        yield 'no domain dot' => ['jeanne@contact'];
        yield 'leading dot' => ['.jeanne@contact.fr'];
        yield 'double dot' => ['jeanne..durand@contact.fr'];
        yield 'one char tld' => ['jeanne@contact.f'];
        yield 'tld ends with hyphen' => ['jeanne@contact.fr-'];
        yield 'accent' => ['jéanne@contact.fr'];
        yield 'prefix space' => [' jeanne@contact.fr'];
        yield 'trailing newline' => ["jeanne@contact.fr\n"];
    }

    #[DataProvider('provideInvalidEmails')]
    public function testInvalidEmails(string $email): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('email_address', 'official URSSAF e-mail pattern'));

        EmailAddress::fromString($email);
    }

    public function testEmailEquality(): void
    {
        static::assertTrue(EmailAddress::fromString('a@b.fr')->equals(EmailAddress::fromString('a@b.fr')));
        static::assertFalse(EmailAddress::fromString('a@b.fr')->equals(EmailAddress::fromString('A@b.fr')));
    }
}

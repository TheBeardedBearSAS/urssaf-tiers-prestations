<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Port;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Credentials;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\CredentialsNotSerializable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

final class CredentialsTest extends TestCase
{
    public function testAccessors(): void
    {
        $secret = bin2hex(random_bytes(8));
        $credentials = Credentials::of('client-id', $secret);

        static::assertSame('client-id', $credentials->clientId());
        static::assertSame($secret, $credentials->clientSecret());
    }

    public function testDebugOutputMasksTheSecret(): void
    {
        $secret = bin2hex(random_bytes(8));
        $credentials = Credentials::of('client-id', $secret);

        $debugInfo = $credentials->__debugInfo();
        static::assertSame('client-id', $debugInfo['clientId']);
        static::assertSame(str_repeat('*', 3), $debugInfo['clientSecret']);
        $dump = print_r($credentials, true);
        static::assertStringNotContainsString($secret, $dump);
        static::assertStringContainsString('client-id', $dump);
        static::assertSame('{}', json_encode($credentials));
    }

    public function testSerializationIsRefused(): void
    {
        $secret = bin2hex(random_bytes(8));
        try {
            serialize(Credentials::of('client-id', $secret));
            static::fail('Expected exception');
        } catch (CredentialsNotSerializable $exception) {
            static::assertSame('API credentials must never be serialized.', $exception->getMessage());
        }
    }

    public function testUnserializationIsRefused(): void
    {
        $this->expectException(CredentialsNotSerializable::class);

        unserialize(\sprintf('O:%d:"%s":0:{}', \strlen(Credentials::class), Credentials::class));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideBlankValues(): iterable
    {
        yield 'empty id' => ['', 'secret', 'credentials.client_id_empty'];
        yield 'blank id' => [' ', 'secret', 'credentials.client_id_empty'];
        yield 'empty secret' => ['id', '', 'credentials.client_secret_empty'];
        yield 'blank secret' => ['id', ' ', 'credentials.client_secret_empty'];
    }

    #[DataProvider('provideBlankValues')]
    public function testBlankValuesAreRejected(string $clientId, #[SensitiveParameter] string $clientSecret, string $code): void
    {
        try {
            Credentials::of($clientId, $clientSecret);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testBlankMessages(): void
    {
        try {
            Credentials::of('', 'x');
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame('The OAuth2 client id must not be empty.', $exception->getMessage());
        }
        $this->expectExceptionObject(
            InvalidValue::because('credentials.client_secret_empty', 'The OAuth2 client secret must not be empty.'),
        );

        Credentials::of('x', '');
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port;

use SensitiveParameter;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * OAuth2 client credentials of a tenant. Environment independent (the base URLs are configuration).
 *
 * The secret is masked in var_dump()/print_r() output and the object refuses to be serialized.
 */
final readonly class Credentials
{
    private const MASK = '***';

    private function __construct(
        private string $clientId,
        #[SensitiveParameter]
        private string $clientSecret,
    ) {}

    /**
     * @throws InvalidValue "credentials.client_id_empty" or "credentials.client_secret_empty"
     */
    public static function of(string $clientId, #[SensitiveParameter] string $clientSecret): self
    {
        if (trim($clientId) === '') {
            throw InvalidValue::because('credentials.client_id_empty', 'The OAuth2 client id must not be empty.');
        }
        if (trim($clientSecret) === '') {
            throw InvalidValue::because(
                'credentials.client_secret_empty',
                'The OAuth2 client secret must not be empty.',
            );
        }

        return new self($clientId, $clientSecret);
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function clientSecret(): string
    {
        return $this->clientSecret;
    }

    /**
     * @return array{clientId: string, clientSecret: string}
     */
    public function __debugInfo(): array
    {
        return ['clientId' => $this->clientId, 'clientSecret' => self::MASK];
    }

    /**
     * @return array<string, string>
     */
    public function __serialize(): array
    {
        throw CredentialsNotSerializable::create();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw CredentialsNotSerializable::create();
    }
}

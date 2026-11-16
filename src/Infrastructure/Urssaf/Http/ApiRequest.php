<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;

/**
 * Description of one API call, independent from the HTTP library.
 */
final readonly class ApiRequest
{
    /**
     * @param array<string, string> $query
     */
    private function __construct(
        private Operation $operation,
        private string $method,
        private string $path,
        private array $query,
        private ?string $jsonBody,
    ) {}

    /**
     * @param array<string, string> $query
     */
    public static function get(Operation $operation, string $path, array $query): self
    {
        return new self($operation, 'GET', $path, $query, null);
    }

    public static function post(Operation $operation, string $path, string $jsonBody): self
    {
        return new self($operation, 'POST', $path, [], $jsonBody);
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, string>
     */
    public function query(): array
    {
        return $this->query;
    }

    public function jsonBody(): ?string
    {
        return $this->jsonBody;
    }
}

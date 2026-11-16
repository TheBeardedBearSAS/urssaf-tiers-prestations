<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf;

use DateTimeZone;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Immutable connection settings of the URSSAF gateway.
 */
final readonly class ApiConfiguration
{
    public const DEFAULT_TOKEN_SAFETY_MARGIN_SECONDS = 60;
    public const DEFAULT_USER_AGENT = 'thebeardedbear-urssaf-tiers-prestations';
    public const DEFAULT_PAIRING_TIME_ZONE = 'Europe/Paris';

    /**
     * @param string|null  $baseUriOverride          replaces the environment host (e.g. a sandbox host or a proxy)
     * @param string|null  $applicationId            value of the optional `recouv.application.id` header (omitted when null)
     * @param int          $tokenSafetyMarginSeconds a cached token is considered expired this many seconds early
     * @param DateTimeZone $pairingTimeZone          time zone of the pairing acceptance date written in the method 020
     *                                               status description (French local time)
     *
     * @throws InvalidValue "token_safety_margin.negative"
     */
    public function __construct(
        private Environment $environment,
        private ?string $baseUriOverride,
        private ?string $applicationId,
        private int $tokenSafetyMarginSeconds = self::DEFAULT_TOKEN_SAFETY_MARGIN_SECONDS,
        private string $userAgent = self::DEFAULT_USER_AGENT,
        private DateTimeZone $pairingTimeZone = new DateTimeZone(self::DEFAULT_PAIRING_TIME_ZONE),
    ) {
        if ($tokenSafetyMarginSeconds < 0) {
            throw InvalidValue::negative('token_safety_margin');
        }
    }

    public static function forEnvironment(Environment $environment): self
    {
        return new self($environment, null, null);
    }

    public function environment(): Environment
    {
        return $this->environment;
    }

    public function baseUri(): string
    {
        return rtrim($this->baseUriOverride ?? $this->environment->baseUri(), '/');
    }

    public function tokenUri(): string
    {
        return $this->baseUri() . UrssafEndpoints::TOKEN_PATH;
    }

    /**
     * @param string $path one of the {@see UrssafEndpoints} paths
     */
    public function apiUri(string $path): string
    {
        return $this->baseUri() . $path;
    }

    public function applicationId(): ?string
    {
        return $this->applicationId;
    }

    public function tokenSafetyMarginSeconds(): int
    {
        return $this->tokenSafetyMarginSeconds;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    public function pairingTimeZone(): DateTimeZone
    {
        return $this->pairingTimeZone;
    }
}

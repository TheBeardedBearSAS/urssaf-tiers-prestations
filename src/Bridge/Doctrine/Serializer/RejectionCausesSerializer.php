<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Explicit JSON shape of the causes of the last refusal or failure of a customer registration.
 *
 * @phpstan-type CauseData array{code: string, message: string}
 */
final class RejectionCausesSerializer
{
    /**
     * @param list<RejectionCause> $causes
     *
     * @return list<CauseData>
     */
    public static function toArray(array $causes): array
    {
        return array_map(
            static fn(RejectionCause $cause): array => ['code' => $cause->code(), 'message' => $cause->message()],
            $causes,
        );
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape list<CauseData>
     *
     * @return list<RejectionCause>
     *
     * @throws InvalidValue
     */
    public static function fromArray(array $data): array
    {
        /** @var list<CauseData> $data */
        return array_map(
            static fn(array $cause): RejectionCause => RejectionCause::of($cause['code'], $cause['message']),
            $data,
        );
    }
}

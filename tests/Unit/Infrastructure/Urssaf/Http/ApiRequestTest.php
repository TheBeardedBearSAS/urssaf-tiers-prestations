<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\ApiRequest;

final class ApiRequestTest extends TestCase
{
    public function testGetCarriesAQueryAndNoBody(): void
    {
        $request = ApiRequest::get(Operation::GetCustomerStatus, '/x', ['a' => 'b']);

        static::assertSame(Operation::GetCustomerStatus, $request->operation());
        static::assertSame('GET', $request->method());
        static::assertSame('/x', $request->path());
        static::assertSame(['a' => 'b'], $request->query());
        static::assertNull($request->jsonBody());
    }

    public function testPostCarriesABodyAndNoQuery(): void
    {
        $request = ApiRequest::post(Operation::SearchPaymentRequests, '/y', '{"k":1}');

        static::assertSame('POST', $request->method());
        static::assertSame([], $request->query());
        static::assertSame('{"k":1}', $request->jsonBody());
    }
}

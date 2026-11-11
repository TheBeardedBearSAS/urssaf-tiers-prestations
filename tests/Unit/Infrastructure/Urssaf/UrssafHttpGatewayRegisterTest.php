<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewayRegisterTest extends TestCase
{
    public function testItRegistersTheCustomerAndReturnsTheUrssafId(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('register_customer_response'));

        $id = $stack->gateway()->register(Stacks::tenant(), CustomerFixtures::newRegistration());

        static::assertSame('af0be430-908a-11eb-a8b3-0242ac130003', $id->value());
        $request = $stack->lastRequest();
        static::assertSame('POST', $request->getMethod());
        static::assertSame('https://api-edi.urssaf.fr/atp/v1/tiersPrestations/particulier', (string) $request->getUri());
        static::assertSame(
            json_decode(Fixtures::load('010-register-request'), true, 512, JSON_THROW_ON_ERROR),
            json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
        static::assertSame(['tenant-1:register_customer'], $stack->rateLimiter->acquired());
    }

    public function testABusinessErrorIsARejection(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(400, Fixtures::load('010-register-error-unknown-taxpayer'));

        try {
            $stack->gateway()->register(Stacks::tenant(), CustomerFixtures::newRegistration());
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $exception) {
            static::assertTrue($exception->hasErrorCode('ERR_DGFIP_R2P_INCONNU'));
            static::assertSame(400, $exception->httpStatus());
        }
    }

    public function testRefusedCredentialsAreAnAuthenticationFailure(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(403, '{}');

        $this->expectException(UrssafAuthenticationFailed::class);

        $stack->gateway()->register(Stacks::tenant(), CustomerFixtures::newRegistration());
    }

    public function testAResponseWithoutCustomerIdIsUnexpected(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{}');

        $this->expectException(UnexpectedUrssafResponse::class);

        $stack->gateway()->register(Stacks::tenant(), CustomerFixtures::newRegistration());
    }
}

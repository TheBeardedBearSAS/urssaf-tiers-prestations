<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Credentials;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class OAuth2TokenResponseTest extends TestCase
{
    private function fetch(Stack $stack): string
    {
        return $stack->tokenProvider()->accessToken(
            Credentials::of(StaticCredentialsProvider::CLIENT_ID, StaticCredentialsProvider::SHARED_VALUE),
            Operation::SubmitPaymentRequests,
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function unexpectedStatuses(): iterable
    {
        yield 'redirect' => [302];
        yield 'multiple choices' => [300];
        yield 'not found' => [404];
        yield 'conflict' => [409];
        yield 'informational' => [199];
    }

    #[DataProvider('unexpectedStatuses')]
    public function testOtherStatusesAreUnexpected(int $status): void
    {
        $stack = Stacks::sandbox();
        $stack->respond($status, '{"access_token":"tok","expires_in":3600}');

        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage(\sprintf('HTTP %d', $status));

        $this->fetch($stack);
    }

    public function testAnyTwoHundredStatusIsASuccess(): void
    {
        $stack = Stacks::sandbox();
        $stack->respond(299, '{"access_token":"edge-token","expires_in":10}');

        static::assertSame('edge-token', $this->fetch($stack));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'not json' => ['<html>'];
        yield 'not an object' => ['"token"'];
        yield 'missing token' => ['{"expires_in":3600}'];
        yield 'empty token' => ['{"access_token":"","expires_in":3600}'];
        yield 'missing lifetime' => ['{"access_token":"t"}'];
        yield 'sub-second lifetime' => ['{"access_token":"t","expires_in":0.5}'];
        yield 'zero lifetime' => ['{"access_token":"t","expires_in":0}'];
        yield 'negative lifetime' => ['{"access_token":"t","expires_in":-5}'];
        yield 'textual lifetime' => ['{"access_token":"t","expires_in":"soon"}'];
    }

    #[DataProvider('invalidBodies')]
    public function testUnusableTokenResponsesAreRejected(string $body): void
    {
        $stack = Stacks::sandbox();
        $stack->respond(200, $body);

        $this->expectException(UnexpectedUrssafResponse::class);

        $this->fetch($stack);
    }

    public function testAOneSecondLifetimeIsAccepted(): void
    {
        $stack = Stacks::sandbox();
        $stack->respond(200, '{"access_token":"short","expires_in":1}');

        static::assertSame('short', $this->fetch($stack));
    }

    public function testAFractionalLifetimeIsTruncatedToWholeSeconds(): void
    {
        $stack = Stacks::sandbox();
        $stack->respond(200, '{"access_token":"fractional","expires_in":3600.9}');

        static::assertSame('fractional', $this->fetch($stack));
    }
}

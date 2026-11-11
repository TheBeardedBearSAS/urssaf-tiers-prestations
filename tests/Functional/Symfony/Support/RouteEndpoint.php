<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * One route of the scripted URSSAF platform: answers a JSON response, or fails like a broken network, and counts calls.
 */
final class RouteEndpoint
{
    private int $calls = 0;
    private ResponseInterface|ClientExceptionInterface $answer;

    public function __construct(int $status, string $body)
    {
        $this->respond($status, $body);
    }

    /**
     * @param array<string, string> $headers
     */
    public function respond(int $status, string $body, array $headers = []): void
    {
        $this->answer = new Response($status, ['Content-Type' => 'application/json'] + $headers, $body);
    }

    public function failTransport(): void
    {
        $this->answer = new TransportFailure();
    }

    public function calls(): int
    {
        return $this->calls;
    }

    /**
     * @throws ClientExceptionInterface the scripted network failure
     */
    public function __invoke(): ResponseInterface
    {
        ++$this->calls;
        if ($this->answer instanceof ClientExceptionInterface) {
            throw $this->answer;
        }
        return $this->answer;
    }
}

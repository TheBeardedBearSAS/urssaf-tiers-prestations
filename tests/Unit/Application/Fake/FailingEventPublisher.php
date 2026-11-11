<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake;

use RuntimeException;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;

final readonly class FailingEventPublisher implements EventPublisher
{
    public function __construct(public RuntimeException $failure) {}

    public function publish(DomainEvent ...$events): void
    {
        throw $this->failure;
    }
}

<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Service;

use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;

/**
 * Turns the domain events that matter to the host application into public integration events; the others (submission
 * started, technical failures...) have none.
 */
final class IntegrationEventTranslator
{
    /**
     * @return list<IntegrationEvent>
     */
    public static function translate(DomainEvent ...$events): array
    {
        $translated = [];
        foreach ($events as $event) {
            array_push($translated, ...CustomerEventTranslator::translate($event), ...PaymentRequestEventTranslator::translate($event));
        }

        return $translated;
    }
}

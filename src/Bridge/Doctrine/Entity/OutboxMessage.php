<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Entity;

use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEventCodec;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Row of the outbox table. Mapped so that the schema tools and migrations know the table; written and read with DBAL by
 * {@see \TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrineOutbox}, never through the ORM.
 *
 * Not final only because Doctrine may generate a proxy for any mapped entity: final by convention, never extend it.
 *
 * @final
 */
class OutboxMessage
{
    /** DBAL types of the columns of {@see self::row()} and of the updates (the others are plain strings). */
    public const TYPES = [
        'version' => 'integer',
        'payload' => 'json',
        'data' => 'text',
        'dispatched_at' => InstantType::NAME,
        'attempts' => 'integer',
        'last_error' => 'text',
    ];

    /** Assigned by the database (identity column); read with DBAL only. */
    // @mago-expect analysis:unused-property Mapped identifier, never read through the ORM.
    protected ?int $sequence = null;

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        private readonly string $eventId,
        private readonly string $type,
        private readonly int $version,
        private readonly string $tenantId,
        private readonly string $aggregateId,
        private readonly string $occurredAt,
        private readonly array $payload,
        private readonly string $data,
        private readonly ?Instant $dispatchedAt,
        private readonly int $attempts,
        private readonly ?string $lastError,
    ) {}

    public static function of(IntegrationEvent $event): self
    {
        return new self(
            $event->eventId,
            $event->type(),
            $event->version(),
            $event->tenantId,
            $event->aggregateId,
            $event->occurredAt,
            $event->toArray(),
            IntegrationEventCodec::encode($event),
            null,
            0,
            null,
        );
    }

    /**
     * The columns of the new, pending entry (the sequence is assigned by the database).
     *
     * @return array<string, mixed>
     */
    public function row(): array
    {
        return [
            'event_id' => $this->eventId,
            'type' => $this->type,
            'version' => $this->version,
            'tenant_id' => $this->tenantId,
            'aggregate_id' => $this->aggregateId,
            'occurred_at' => $this->occurredAt,
            'payload' => $this->payload,
            'data' => $this->data,
            'dispatched_at' => $this->dispatchedAt,
            'attempts' => $this->attempts,
            'last_error' => $this->lastError,
        ];
    }
}

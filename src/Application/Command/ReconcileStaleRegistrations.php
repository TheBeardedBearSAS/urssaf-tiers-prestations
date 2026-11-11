<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

/**
 * Settles the customer registrations (every tenant) left submitting for too long. Null limit: policy default page size.
 */
final readonly class ReconcileStaleRegistrations
{
    public function __construct(public ?int $limit) {}
}

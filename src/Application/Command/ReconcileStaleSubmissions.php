<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

/**
 * Reconciles the payment requests of every tenant left submitting for too long (no answer from method 050).
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class ReconcileStaleSubmissions
{
    public function __construct(public ?int $limit) {}
}

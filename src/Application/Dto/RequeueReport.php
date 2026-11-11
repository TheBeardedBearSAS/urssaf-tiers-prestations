<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Dto;

/**
 * Number of records moved back from invalid_credentials to pending.
 */
final readonly class RequeueReport
{
    public function __construct(public int $registrations, public int $paymentRequests) {}
}

<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Exception;

/**
 * Marker for transient failures: the same operation may succeed when retried later.
 */
interface RetryableException extends UrssafException {}

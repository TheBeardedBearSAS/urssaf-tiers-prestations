<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support;

use LogicException;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * A PSR-18 failure (connection refused...).
 */
final class TransportFailure extends LogicException implements ClientExceptionInterface
{
    public function __construct()
    {
        parent::__construct('connection refused');
    }
}

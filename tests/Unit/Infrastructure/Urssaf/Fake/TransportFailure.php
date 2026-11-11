<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

final class TransportFailure extends RuntimeException implements ClientExceptionInterface {}

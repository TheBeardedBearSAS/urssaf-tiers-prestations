<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\IgnoredOutbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\PaymentRequestSubmitted;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Event\NullEventPublisher;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\DomainEventSamples;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Journal;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\JournalingOutbox;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\RecordingTransactionManager;

final class UnitOfWorkOutboxTest extends TestCase
{
    public function testTheIntegrationEventsAreAppendedInsideTheTransactionAfterTheOperation(): void
    {
        $journal = new Journal();
        $unitOfWork = new UnitOfWork(new RecordingTransactionManager($journal), new NullEventPublisher(), new IgnoredFailures(), new JournalingOutbox($journal));

        $result = $unitOfWork->run(static function (RecordedEvents $events) use ($journal): string {
            $journal->add('work');
            $events->record(DomainEventSamples::customerSubmitted(), DomainEventSamples::submissionStarted(), DomainEventSamples::paymentRequestSubmitted());

            return 'done';
        });

        static::assertSame('done', $result);
        static::assertSame(['begin', 'work', 'append:urssaf.customer_registration.submitted,urssaf.payment_request.submitted', 'commit'], $journal->entries());
    }

    public function testNothingIsAppendedWhenTheOperationFails(): void
    {
        $journal = new Journal();
        $unitOfWork = new UnitOfWork(new RecordingTransactionManager($journal), new NullEventPublisher(), new IgnoredFailures(), new JournalingOutbox($journal));

        try {
            $unitOfWork->run(static function (RecordedEvents $events): never {
                $events->record(DomainEventSamples::customerSubmitted());

                throw new RuntimeException('failed');
            });
        } catch (RuntimeException $failure) {
            static::assertNotSame('', $failure->getMessage());
        }

        static::assertSame(['begin', 'rollback'], $journal->entries());
    }

    public function testTheDefaultOutboxIgnoresTheEvents(): void
    {
        $outbox = new IgnoredOutbox();
        $outbox->append(new PaymentRequestSubmitted('e1', '2022-03-01T10:00:00Z', 'tenant-1', 'local-1', 'ref', 'urssaf-1'));

        static::assertSame([], $outbox->pending(10));
        $entry = new OutboxEntry(1, new PaymentRequestSubmitted('e1', '2022-03-01T10:00:00Z', 'tenant-1', 'local-1', 'ref', 'urssaf-1'), 0);
        $outbox->markDispatched($entry, Instant::fromDateTime(new DateTimeImmutable()));
        $outbox->markFailed($entry, 'ignored');
        static::assertSame([], $outbox->pending(10));
        static::assertSame(0, $outbox->purgeDispatchedBefore(Instant::fromDateTime(new DateTimeImmutable())));
    }
}

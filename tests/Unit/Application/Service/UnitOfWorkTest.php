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

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRetried;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerSubmissionStarted;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\FailingEventPublisher;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\RecordingFailureReporter;

final class UnitOfWorkTest extends TestCase
{
    public function testPublishesTheRecordedEventsAfterTheCommit(): void
    {
        $harness = new Harness();
        $first = new CustomerSubmissionStarted(RegistrationId::fromString('a'), Harness::tenant(), Harness::at(Harness::NOW));
        $second = new CustomerRegistrationRetried(RegistrationId::fromString('b'), Harness::tenant(), Harness::at(Harness::NOW));

        $result = $harness->unitOfWork->run(static function (RecordedEvents $events) use ($first, $second, $harness): string {
            $harness->journal->add('work');
            $events->record($first);
            $events->record($second);

            return 'done';
        });

        static::assertSame('done', $result);
        static::assertSame([$first, $second], $harness->events->events());
        static::assertSame(
            ['begin', 'work', 'commit', 'publish:CustomerSubmissionStarted', 'publish:CustomerRegistrationRetried'],
            $harness->journal->entries(),
        );
    }

    public function testPublishesNothingWhenTheOperationFails(): void
    {
        $harness = new Harness();
        $event = new CustomerSubmissionStarted(RegistrationId::fromString('a'), Harness::tenant(), Harness::at(Harness::NOW));

        $failure = new RuntimeException('failure');
        $this->expectExceptionObject($failure);

        try {
            $harness->unitOfWork->run(static function (RecordedEvents $events) use ($event, $failure): never {
                $events->record($event);

                throw $failure;
            });
        } finally {
            static::assertSame([], $harness->events->events());
            static::assertSame(['begin', 'rollback'], $harness->journal->entries());
        }
    }

    public function testRecordedEventsKeepTheOrder(): void
    {
        $first = new CustomerSubmissionStarted(RegistrationId::fromString('a'), Harness::tenant(), Harness::at(Harness::NOW));
        $second = new CustomerRegistrationRetried(RegistrationId::fromString('b'), Harness::tenant(), Harness::at(Harness::NOW));
        $events = new RecordedEvents();

        $events->record();
        $events->record(...['first' => $first, 'second' => $second]);
        $events->record($first);

        static::assertSame([$first, $second, $first], $events->all());
    }

    public function testReportsAPublicationFailureWithoutFailingTheCommittedOperation(): void
    {
        $harness = new Harness();
        $publisher = new FailingEventPublisher(new RuntimeException('broker down'));
        $reporter = new RecordingFailureReporter();
        $unitOfWork = new UnitOfWork($harness->transactions, $publisher, $reporter);
        $event = new CustomerSubmissionStarted(RegistrationId::fromString('a'), Harness::tenant(), Harness::at(Harness::NOW));

        $result = $unitOfWork->run(static function (RecordedEvents $events) use ($event): string {
            $events->record($event);

            return 'done';
        });

        static::assertSame('done', $result);
        static::assertSame([[$publisher->failure, [$event]]], $reporter->reports());
        static::assertSame(['begin', 'commit'], $harness->journal->entries());
    }

    public function testIgnoresAPublicationFailureByDefault(): void
    {
        $harness = new Harness();
        $unitOfWork = new UnitOfWork($harness->transactions, new FailingEventPublisher(new RuntimeException('broker down')));

        static::assertSame('done', $unitOfWork->run(static fn(): string => 'done'));
        (new IgnoredFailures())->eventPublicationFailed(new RuntimeException('ignored'), []);
        static::assertSame(1, $harness->transactions->committed());
    }
}

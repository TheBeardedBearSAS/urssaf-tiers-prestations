<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;

final class SubmissionStateReconciliationTest extends TestCase
{
    public function testReconciledAsSubmitted(): void
    {
        static::assertSame(SubmissionState::Submitted, SubmissionState::Submitting->reconciledAsSubmitted());
        static::assertSame(SubmissionState::Submitted, SubmissionState::Error->reconciledAsSubmitted());
    }

    /**
     * @return iterable<string, array{SubmissionState}>
     */
    public static function provideNotReconcilable(): iterable
    {
        yield 'pending' => [SubmissionState::Pending];
        yield 'submitted' => [SubmissionState::Submitted];
        yield 'rejected' => [SubmissionState::Rejected];
        yield 'invalid credentials' => [SubmissionState::InvalidCredentials];
    }

    #[DataProvider('provideNotReconcilable')]
    public function testReconciliationIsRefused(SubmissionState $from): void
    {
        $this->expectExceptionObject(InvalidStateTransition::between('payment_request', $from->value, 'submitted'));

        $from->reconciledAsSubmitted();
    }

    #[DoesNotPerformAssertions]
    public function testAssertIsAcceptsTheExpectedState(): void
    {
        SubmissionState::Submitted->assertIs(SubmissionState::Submitted);
        SubmissionState::Pending->assertIs(SubmissionState::Pending);
    }

    public function testAssertIsRefusesAnotherState(): void
    {
        $this->expectExceptionObject(InvalidStateTransition::between('payment_request', 'pending', 'submitted'));

        SubmissionState::Pending->assertIs(SubmissionState::Submitted);
    }
}

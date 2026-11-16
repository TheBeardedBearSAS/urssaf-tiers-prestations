<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Policy;

use DateInterval;
use DateTimeZone;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSubmitter;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\BillingThirdPartyId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PollingWindow;

/**
 * Configuration shared by the use case handlers (provided by the framework bridge).
 *
 * - businessTimeZone: time zone of the provider business rules (same month, not in the future);
 * - settledMaxAge: how long a paid / recovered payment request keeps being polled;
 * - unknownStatusMaxAge: how long a payment request keeps being polled after a status code unknown to this package
 *   version was observed;
 * - maxPollingAge: how long a payment request keeps being polled after its submission, whatever its status;
 * - staleSubmissionAfter: how long a submission may stay unanswered before being reconciled;
 * - billingThirdPartyId: billing third-party id sent with every payment request;
 * - submissionBatchSize: payment requests per method 050 call (1 to 10);
 * - maxSubmissionAttempts: submission attempts after which a technical failure is not requeued automatically;
 * - defaultPageSize / maxPageSize: number of records loaded by a batch use case when no / a larger limit is asked.
 */
final readonly class WorkflowPolicy
{
    public const DEFAULT_BILLING_THIRD_PARTY_ID = 'menage.fr';
    public const DEFAULT_PAGE_SIZE = 100;
    public const MAX_PAGE_SIZE = 1000;
    public const DEFAULT_MAX_SUBMISSION_ATTEMPTS = 5;

    private BillingThirdPartyId $billingThirdPartyId;

    private PollingWindow $pollingWindow;

    /** @var positive-int */
    private int $submissionBatchSize;

    /** @var positive-int */
    private int $defaultPageSize;

    /** @var positive-int */
    private int $maxPageSize;

    /** @var positive-int */
    private int $maxSubmissionAttempts;

    /**
     * @throws InvalidValue       for an invalid billing third-party id
     * @throws InvariantViolation "workflow_policy.invalid_batch_size", "workflow_policy.invalid_page_size",
     *                            "workflow_policy.default_page_size_above_max" or
     *                            "workflow_policy.invalid_max_submission_attempts"
     */
    public function __construct(
        private DateTimeZone $businessTimeZone = new DateTimeZone('Europe/Paris'),
        private DateInterval $settledMaxAge = new DateInterval('P90D'),
        private DateInterval $staleSubmissionAfter = new DateInterval('PT15M'),
        string $billingThirdPartyId = self::DEFAULT_BILLING_THIRD_PARTY_ID,
        int $submissionBatchSize = PaymentRequestSubmitter::MAX_REQUESTS_PER_CALL,
        int $defaultPageSize = self::DEFAULT_PAGE_SIZE,
        int $maxPageSize = self::MAX_PAGE_SIZE,
        int $maxSubmissionAttempts = self::DEFAULT_MAX_SUBMISSION_ATTEMPTS,
        DateInterval $unknownStatusMaxAge = new DateInterval('P30D'),
        DateInterval $maxPollingAge = new DateInterval('P1Y'),
    ) {
        if ($submissionBatchSize < 1 || $submissionBatchSize > PaymentRequestSubmitter::MAX_REQUESTS_PER_CALL) {
            throw InvariantViolation::because(
                'workflow_policy.invalid_batch_size',
                \sprintf('The submission batch size must be between 1 and %d.', PaymentRequestSubmitter::MAX_REQUESTS_PER_CALL),
                ['batch_size' => $submissionBatchSize],
            );
        }
        if ($maxPageSize < $defaultPageSize) {
            throw InvariantViolation::because(
                'workflow_policy.default_page_size_above_max',
                'The default page size must not be greater than the maximum page size.',
                ['default_page_size' => $defaultPageSize, 'max_page_size' => $maxPageSize],
            );
        }
        $this->billingThirdPartyId = BillingThirdPartyId::fromString($billingThirdPartyId);
        $this->submissionBatchSize = $submissionBatchSize;
        $this->defaultPageSize = self::positivePageSize($defaultPageSize);
        $this->maxPageSize = self::positivePageSize($maxPageSize);
        if ($maxSubmissionAttempts < 1) {
            throw InvariantViolation::because(
                'workflow_policy.invalid_max_submission_attempts',
                'The maximum number of submission attempts must be a positive integer.',
                ['max_submission_attempts' => $maxSubmissionAttempts],
            );
        }
        $this->maxSubmissionAttempts = $maxSubmissionAttempts;
        $this->pollingWindow = new PollingWindow($settledMaxAge, $unknownStatusMaxAge, $maxPollingAge);
    }

    public function businessTimeZone(): DateTimeZone
    {
        return $this->businessTimeZone;
    }

    public function settledMaxAge(): DateInterval
    {
        return $this->settledMaxAge;
    }

    /**
     * The settled, unknown status and submission age limits of the status polling.
     */
    public function pollingWindow(): PollingWindow
    {
        return $this->pollingWindow;
    }

    public function staleSubmissionAfter(): DateInterval
    {
        return $this->staleSubmissionAfter;
    }

    public function billingThirdPartyId(): BillingThirdPartyId
    {
        return $this->billingThirdPartyId;
    }

    /**
     * @return positive-int
     */
    public function submissionBatchSize(): int
    {
        return $this->submissionBatchSize;
    }

    /**
     * Submission attempts after which a technical failure is not requeued automatically any more (the registration
     * or payment request stays in error until handled manually).
     *
     * @return positive-int
     */
    public function maxSubmissionAttempts(): int
    {
        return $this->maxSubmissionAttempts;
    }

    /**
     * Number of records to load: the requested limit (default page size when null), capped by the maximum page size.
     *
     * @return positive-int
     *
     * @throws InvariantViolation "workflow_policy.invalid_limit" for a limit lower than 1
     */
    public function pageSize(?int $requestedLimit): int
    {
        $limit = $requestedLimit ?? $this->defaultPageSize;
        if ($limit < 1) {
            throw InvariantViolation::because(
                'workflow_policy.invalid_limit',
                'The limit must be a positive integer.',
                ['limit' => $limit],
            );
        }

        return min($limit, $this->maxPageSize);
    }

    /**
     * @return positive-int
     */
    private static function positivePageSize(int $pageSize): int
    {
        if ($pageSize < 1) {
            throw InvariantViolation::because(
                'workflow_policy.invalid_page_size',
                'Page sizes must be positive integers.',
                ['page_size' => $pageSize],
            );
        }

        return $pageSize;
    }
}

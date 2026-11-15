<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Benchmark;

use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\PaymentRequestSnapshotMapper;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\SubmissionOutcomeMapper;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

/**
 * Decoding of the official URSSAF responses, from the raw JSON string: the outcome of a payment request submission
 * (050) and a search result (070).
 */
final readonly class WireDecodingBench
{
    private SubmissionOutcomeMapper $submissionMapper;

    private PaymentRequestSnapshotMapper $snapshotMapper;

    private string $submissionResponse;

    private string $searchResponse;

    public function __construct()
    {
        $this->submissionMapper = new SubmissionOutcomeMapper();
        $this->snapshotMapper = new PaymentRequestSnapshotMapper();
        $this->submissionResponse = Fixtures::load('050-submit-response-mixed');
        $this->searchResponse = Fixtures::load('search_response');
    }

    public function benchDecodeASubmissionResponse(): void
    {
        $this->submissionMapper->mapAll(JsonDecoder::decode($this->submissionResponse));
    }

    public function benchDecodeASearchResponse(): void
    {
        $this->snapshotMapper->mapAll(JsonDecoder::decode($this->searchResponse));
    }
}

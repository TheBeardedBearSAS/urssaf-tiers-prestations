<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\CustomerTransmissionStatus;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSnapshot;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSubmitter;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SubmissionOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafGateway;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\ApiErrorCode;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\CustomerRegistrationMapper;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\ErrorPayloadParser;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonObject;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\PaymentRequestMapper;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\PaymentRequestSearchMapper;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\PaymentRequestSnapshotMapper;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\SubmissionOutcomeMapper;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\TransmissionStatusMapper;

/**
 * HTTP implementation of the URSSAF ports: methods 010 (register), 020 (transmission status), 050 (submit) and
 * 070 (search). Build it with {@see UrssafGatewayFactory}.
 */
final readonly class UrssafHttpGateway implements UrssafGateway
{
    public function __construct(
        private CredentialsProvider $credentialsProvider,
        private JsonApiClient $client,
        private TransmissionStatusMapper $statusMapper = new TransmissionStatusMapper(),
        private PaymentRequestSearchMapper $searchMapper = new PaymentRequestSearchMapper(),
        private PaymentRequestSnapshotMapper $snapshotMapper = new PaymentRequestSnapshotMapper(),
        private ErrorPayloadParser $errorParser = new ErrorPayloadParser(),
        private CustomerRegistrationMapper $registrationMapper = new CustomerRegistrationMapper(),
        private PaymentRequestMapper $paymentRequestMapper = new PaymentRequestMapper(),
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function register(TenantId $tenantId, CustomerRegistration $registration): UrssafCustomerId
    {
        $payload = $this->client->send(
            $tenantId,
            $this->credentialsProvider->forTenant($tenantId),
            ApiRequest::post(
                Operation::RegisterCustomer,
                UrssafEndpoints::CUSTOMER_PATH,
                JsonEncoder::encode(JsonObject::of($this->registrationMapper->toBody($registration))),
            ),
        );

        return $this->registrationMapper->mapResponse($payload);
    }

    /**
     * When URSSAF answers a different number of outcomes than submitted requests, the outcomes are returned as they
     * are (callers match them by invoice number) and the anomaly is logged at error level with the request id.
     *
     * @return list<SubmissionOutcome>
     *
     * @throws InvariantViolation "payment_request_submission.empty" / "payment_request_submission.too_many_items"
     */
    public function submit(TenantId $tenantId, PaymentRequest ...$requests): array
    {
        $count = \count($requests);
        if ($count === 0) {
            throw InvariantViolation::because(
                'payment_request_submission.empty',
                'At least one payment request must be submitted.',
            );
        }

        if ($count > PaymentRequestSubmitter::MAX_REQUESTS_PER_CALL) {
            throw InvariantViolation::because(
                'payment_request_submission.too_many_items',
                \sprintf('At most %d payment requests can be submitted per call.', PaymentRequestSubmitter::MAX_REQUESTS_PER_CALL),
                ['max' => PaymentRequestSubmitter::MAX_REQUESTS_PER_CALL, 'given' => $count],
            );
        }

        $response = $this->client->exchange(
            $tenantId,
            $this->credentialsProvider->forTenant($tenantId),
            ApiRequest::post(
                Operation::SubmitPaymentRequests,
                UrssafEndpoints::PAYMENT_REQUEST_PATH,
                JsonEncoder::encode($this->paymentRequestMapper->toBody(...$requests)),
            ),
        );

        $outcomes = (new SubmissionOutcomeMapper($this->errorParser))->mapAll($response->payload());
        if (\count($outcomes) !== $count) {
            $this->logger->error('URSSAF method 050 answered a different number of outcomes than submitted payment requests.', [
                'request_id' => $response->requestId(),
                'submitted' => $count,
                'answered' => \count($outcomes),
            ]);
        }

        return $outcomes;
    }

    public function transmissionStatus(TenantId $tenantId, UrssafCustomerId $customerId): CustomerTransmissionStatus
    {
        // UrssafCustomerId is lower-cased by construction (the 020 search is case sensitive).
        $payload = $this->client->send(
            $tenantId,
            $this->credentialsProvider->forTenant($tenantId),
            ApiRequest::get(Operation::GetCustomerStatus, UrssafEndpoints::CUSTOMER_PATH, ['idClient' => $customerId->value()]),
        );

        return $this->statusMapper->map($customerId, $payload);
    }

    /**
     * @return list<PaymentRequestSnapshot>
     */
    public function search(TenantId $tenantId, PaymentRequestSearch $search): array
    {
        $noResult = ApiErrorCode::NoSearchResult->value;

        try {
            $payload = $this->client->send(
                $tenantId,
                $this->credentialsProvider->forTenant($tenantId),
                ApiRequest::post(
                    Operation::SearchPaymentRequests,
                    UrssafEndpoints::PAYMENT_REQUEST_SEARCH_PATH,
                    JsonEncoder::encode(JsonObject::of($this->searchMapper->toBody($search))),
                ),
            );
        } catch (UrssafRequestRejected $rejected) {
            if ($rejected->hasErrorCode($noResult)) {
                return [];
            }

            throw $rejected;
        }

        $errors = $this->errorParser->parseEntries($payload->items('errors'));
        if ($errors->hasCode($noResult)) {
            return [];
        }

        if (!$errors->isEmpty()) {
            throw UrssafRequestRejected::withErrors(Operation::SearchPaymentRequests, 200, $errors);
        }

        return $this->snapshotMapper->mapAll($payload);
    }
}

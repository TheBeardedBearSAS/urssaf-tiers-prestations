<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Method 050 - POST /demandePaiement (1 to 10 payment requests per call).
 */
interface PaymentRequestSubmitter
{
    public const MAX_REQUESTS_PER_CALL = 10;

    /**
     * Per-item business errors (returned by URSSAF with HTTP 200) are returned as rejected outcomes, never thrown.
     * URSSAF normally answers one outcome per request, in submission order; callers match them by invoice number
     * ({@see SubmissionOutcome::concerns()}) and treat a request without a matching outcome as unconfirmed.
     *
     * @return list<SubmissionOutcome>
     *
     * @throws InvariantViolation         payment_request_submission.too_many_items / .empty
     * @throws UrssafRequestRejected      malformed input (HTTP 400)
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable; see {@see UrssafUnavailable::mayHaveReachedUrssaf()}
     */
    public function submit(TenantId $tenantId, PaymentRequest ...$requests): array;
}

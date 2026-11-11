<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Service;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;

/**
 * Converts the errors reported by URSSAF into the Domain error value objects.
 */
final class UrssafErrorTranslator
{
    /**
     * @return list<RejectionCause>
     */
    public static function rejectionCauses(ApiErrors $errors): array
    {
        return array_map(
            static fn(ApiError $error): RejectionCause => RejectionCause::of($error->code(), $error->message()),
            $errors->all(),
        );
    }

    /**
     * @return list<SubmissionError>
     */
    public static function submissionErrors(ApiErrors $errors): array
    {
        return array_map(
            static fn(ApiError $error): SubmissionError => SubmissionError::of(
                $error->code(),
                $error->message(),
                $error->description(),
            ),
            $errors->all(),
        );
    }
}

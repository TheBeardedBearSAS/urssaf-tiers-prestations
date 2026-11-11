<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;

/**
 * Explicit JSON shape of the errors of the last failed or rejected submission.
 *
 * @phpstan-type ErrorData array{code: string, message: string, description: string}
 */
final class SubmissionErrorsSerializer
{
    /**
     * @param list<SubmissionError> $errors
     *
     * @return list<ErrorData>
     */
    public static function toArray(array $errors): array
    {
        return array_map(
            static fn(SubmissionError $error): array => [
                'code' => $error->code(),
                'message' => $error->message(),
                'description' => $error->description(),
            ],
            $errors,
        );
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape list<ErrorData>
     *
     * @return list<SubmissionError>
     *
     * @throws InvalidValue
     */
    public static function fromArray(array $data): array
    {
        /** @var list<ErrorData> $data */
        return array_map(
            static fn(array $error): SubmissionError => SubmissionError::of($error['code'], $error['message'], $error['description']),
            $data,
        );
    }
}
